<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotAbuseReport;
use App\Models\IpBan;
use App\Models\User;
use App\Notifications\AccountModerationNotice;
use App\Services\AdminAuditService;
use App\Services\Support\SupportQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisteredUserController extends Controller
{
    public function __construct(
        protected SupportQueueService $supportQueue,
    ) {}

    /**
     * Display a listing of registered users with filter tabs and moderation metrics.
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'all');
        $search = $request->query('search');

        $query = User::withCount('abuseReports');

        if ($tab === 'flagged') {
            $query->where('chatbot_flag_count', '>', 0);
        } elseif ($tab === 'warned') {
            $query->where('ban_level', User::BAN_LEVEL_WARNING);
        } elseif ($tab === 'temporary') {
            $query->where('ban_level', User::BAN_LEVEL_TEMPORARY);
        } elseif ($tab === 'permanent') {
            $query->where('ban_level', User::BAN_LEVEL_PERMANENT);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone_number', 'ilike', "%{$search}%")
                    ->orWhere('address', 'ilike', "%{$search}%");
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        $allCount = User::count();
        $flaggedCount = User::where('chatbot_flag_count', '>', 0)->count();
        $warnedCount = User::where('ban_level', User::BAN_LEVEL_WARNING)->count();
        $temporaryCount = User::where('ban_level', User::BAN_LEVEL_TEMPORARY)->count();
        $permanentCount = User::where('ban_level', User::BAN_LEVEL_PERMANENT)->count();

        if ($request->ajax()) {
            return view('admin.users._table', compact(
                'users',
                'tab',
                'search',
                'allCount',
                'flaggedCount',
                'warnedCount',
                'temporaryCount',
                'permanentCount'
            ));
        }

        return view('admin.users.index', compact(
            'users',
            'tab',
            'search',
            'allCount',
            'flaggedCount',
            'warnedCount',
            'temporaryCount',
            'permanentCount'
        ));
    }

    /**
     * Display detailed profile for a registered user and their full chatbot abuse history.
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        $abuseReports = ChatbotAbuseReport::with('admin')
            ->where('user_id', $id)
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.users.show', compact('user', 'abuseReports'));
    }

    /**
     * Apply a leveled action to a registered user: warning, temporary ban, or permanent ban.
     */
    public function ban(Request $request, $id)
    {
        $request->validate([
            'ban_level' => ['required', 'in:warning,temporary,permanent'],
            'ban_reason' => ['required_unless:ban_level,warning', 'nullable', 'string', 'max:500'],
            'ban_duration_days' => ['nullable', 'required_if:ban_level,temporary', 'integer', 'min:1', 'max:365'],
            'also_ban_ip' => ['nullable', 'boolean'],
        ]);

        $user = User::findOrFail($id);
        $oldValues = $user->getOriginal();
        $user->ban_level = $request->ban_level;
        $user->banned_at = now();
        $user->ban_reason = $request->ban_reason ?: null;

        if ($request->ban_level === 'temporary') {
            $user->ban_expires_at = now()->addDays((int) $request->ban_duration_days);
        } else {
            $user->ban_expires_at = null;
        }

        $user->save();
        AdminAuditService::log($user, $oldValues);

        $user->notify(new AccountModerationNotice(
            $request->ban_level,
            $user->ban_reason,
            $user->ban_expires_at?->format('M d, Y'),
        ));

        if ($request->boolean('also_ban_ip')) {
            $targetIp = $user->consent_ip_address
                ?: DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->value('ip_address');

            if ($targetIp && filter_var($targetIp, FILTER_VALIDATE_IP) && ! IpBan::active()->where('ip_address', $targetIp)->exists()) {
                $ipBan = IpBan::create([
                    'ip_address' => $targetIp,
                    'ban_level' => $request->ban_level,
                    'reason' => $request->ban_reason ?: 'Banned via user account action.',
                    'banned_at' => now(),
                    'expires_at' => $request->ban_level === 'temporary' ? now()->addDays((int) $request->ban_duration_days) : null,
                    'banned_by' => Auth::guard('admin')->id(),
                ]);
                AdminAuditService::log($ipBan);
            }
        }

        // Resolve any pending abuse reports (best effort; chatbot flow is a future feature).
        $reportStatus = $request->ban_level === 'warning' ? 'reviewed_dismissed' : 'banned';
        ChatbotAbuseReport::where('user_id', $id)
            ->where('status', 'pending')
            ->update([
                'status' => $reportStatus,
                'reviewed_by' => Auth::guard('admin')->id(),
            ]);

        $message = match ($request->ban_level) {
            'warning' => "Warning recorded for user account {$user->name}.",
            'temporary' => "User account {$user->name} has been temporarily suspended until {$user->ban_expires_at->format('M d, Y')}.",
            default => "User account {$user->name} has been permanently suspended.",
        };

        return redirect()->back()->with('success', $message);
    }

    /**
     * Unban a registered user and restore normal access.
     */
    public function unban($id)
    {
        $user = User::findOrFail($id);
        $oldValues = $user->getOriginal();
        $targetIp = $user->consent_ip_address
            ?: DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->value('ip_address');
        $user->ban_level = null;
        $user->banned_at = null;
        $user->ban_expires_at = null;
        $user->ban_reason = null;
        $user->save();
        AdminAuditService::log($user, $oldValues);

        if ($oldValues['ban_level'] ?? null) {
            $user->notify(new AccountModerationNotice('restored'));
        }

        if ($targetIp) {
            $ipBans = IpBan::active()->where('ip_address', $targetIp)->get();
            foreach ($ipBans as $ipBan) {
                $oldIp = $ipBan->getOriginal();
                $ipBan->delete();
                AdminAuditService::log($ipBan, $oldIp);
            }
        }

        return redirect()->back()->with('success', "User account {$user->name} has been restored to normal access.");
    }

    /**
     * Dismiss a flagged chatbot abuse report.
     */
    public function dismissReport($reportId)
    {
        $report = ChatbotAbuseReport::findOrFail($reportId);
        $oldValues = $report->getOriginal();
        $report->status = 'reviewed_dismissed';
        $report->reviewed_by = Auth::guard('admin')->id();
        $report->save();
        AdminAuditService::log($report, $oldValues);

        // Decrement flag count on user if greater than 0
        if ($report->user && $report->user->chatbot_flag_count > 0) {
            $report->user->decrement('chatbot_flag_count');
        }

        // Close any open handoff tickets tied to this user's sessions so the bot resumes
        if ($report->user) {
            $this->supportQueue->closeOpenInquiriesForUser($report->user->id);
        }

        return redirect()->back()->with('success', 'Abuse report warning dismissed.');
    }

    /**
     * Clear all chatbot abuse flags for a user. Resolved reports stay in
     * history as dismissed; only the active flag counter is reset.
     */
    public function clearFlags($id)
    {
        $user = User::findOrFail($id);
        $oldValues = $user->getOriginal();
        $user->chatbot_flag_count = 0;
        $user->save();
        AdminAuditService::log($user, $oldValues);

        ChatbotAbuseReport::where('user_id', $id)
            ->where('status', 'pending')
            ->update([
                'status' => 'reviewed_dismissed',
                'reviewed_by' => Auth::guard('admin')->id(),
            ]);

        $this->supportQueue->closeOpenInquiriesForUser($user->id);

        return redirect()->back()->with('success', "All chatbot abuse flags cleared for {$user->name}.");
    }
}

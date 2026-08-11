<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotAbuseReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
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
        } elseif ($tab === 'banned') {
            $query->activeBan();
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
        $bannedCount = User::activeBan()->count();

        if ($request->ajax()) {
            return view('admin.users._table', compact(
                'users',
                'tab',
                'search',
                'allCount',
                'flaggedCount',
                'bannedCount'
            ));
        }

        return view('admin.users.index', compact(
            'users',
            'tab',
            'search',
            'allCount',
            'flaggedCount',
            'bannedCount'
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
        ]);

        $user = User::findOrFail($id);
        $user->ban_level = $request->ban_level;
        $user->banned_at = now();
        $user->ban_reason = $request->ban_reason ?: null;

        if ($request->ban_level === 'temporary') {
            $user->ban_expires_at = now()->addDays((int) $request->ban_duration_days);
        } else {
            $user->ban_expires_at = null;
        }

        $user->save();

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
        $user->ban_level = null;
        $user->banned_at = null;
        $user->ban_expires_at = null;
        $user->ban_reason = null;
        $user->save();

        return redirect()->back()->with('success', "User account {$user->name} has been restored to normal access.");
    }

    /**
     * Dismiss a flagged chatbot abuse report.
     */
    public function dismissReport($reportId)
    {
        $report = ChatbotAbuseReport::findOrFail($reportId);
        $report->status = 'reviewed_dismissed';
        $report->reviewed_by = Auth::guard('admin')->id();
        $report->save();

        // Decrement flag count on user if greater than 0
        if ($report->user && $report->user->chatbot_flag_count > 0) {
            $report->user->decrement('chatbot_flag_count');
        }

        return redirect()->back()->with('success', 'Abuse report warning dismissed.');
    }
}

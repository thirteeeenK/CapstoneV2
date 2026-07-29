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
            $query->where('is_banned', true);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        $allCount = User::count();
        $flaggedCount = User::where('chatbot_flag_count', '>', 0)->count();
        $bannedCount = User::where('is_banned', true)->count();

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
     * Ban a registered user and update pending abuse reports.
     */
    public function ban(Request $request, $id)
    {
        $request->validate([
            'ban_reason' => 'required|string|max:500',
        ]);

        $user = User::findOrFail($id);
        $user->is_banned = true;
        $user->ban_reason = $request->ban_reason;
        $user->save();

        // Update pending abuse reports for this user
        ChatbotAbuseReport::where('user_id', $id)
            ->where('status', 'pending')
            ->update([
                'status' => 'banned',
                'reviewed_by' => Auth::guard('admin')->id(),
            ]);

        return redirect()->back()->with('success', "User account {$user->name} has been suspended.");
    }

    /**
     * Unban a registered user and restore normal access.
     */
    public function unban($id)
    {
        $user = User::findOrFail($id);
        $user->is_banned = false;
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

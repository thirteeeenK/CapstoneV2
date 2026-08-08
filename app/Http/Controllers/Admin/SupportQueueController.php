<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\SupportInquiry;
use App\Services\Support\SupportQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SupportQueueController extends Controller
{
    public function __construct(
        protected SupportQueueService $supportQueue,
    ) {}

    public function index(): View
    {
        return view('admin.support.index');
    }

    public function poll(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        $pending = SupportInquiry::with(['chatSession', 'user'])
            ->where('status', SupportInquiry::STATUS_PENDING)
            ->orderBy('requested_at', 'asc')
            ->get()
            ->map(function ($inquiry) {
                $session = $inquiry->chatSession;
                $lastMsg = $session?->messages()->where('sender', 'user')->latest('created_at')->first();
                return [
                    'id' => $inquiry->id,
                    'ticket_number' => $inquiry->ticket_number,
                    'user_name' => $inquiry->user?->name ?? 'Guest',
                    'user_id' => $inquiry->user_id,
                    'last_message' => $lastMsg?->message ?? '',
                    'last_message_at' => $lastMsg?->created_at?->diffForHumans() ?? '',
                    'requested_at' => $inquiry->requested_at?->diffForHumans() ?? '',
                    'status' => $inquiry->status,
                ];
            });

        $myActive = SupportInquiry::with(['chatSession', 'user'])
            ->where('assigned_admin_id', $admin->id)
            ->where('status', SupportInquiry::STATUS_HUMAN_ACTIVE)
            ->orderBy('assigned_at', 'asc')
            ->get()
            ->map(function ($inquiry) {
                $session = $inquiry->chatSession;
                $lastMsg = $session?->messages()->latest('created_at')->first();
                return [
                    'id' => $inquiry->id,
                    'ticket_number' => $inquiry->ticket_number,
                    'user_name' => $inquiry->user?->name ?? 'Guest',
                    'user_id' => $inquiry->user_id,
                    'last_message' => $lastMsg?->message ?? '',
                    'last_message_at' => $lastMsg?->created_at?->diffForHumans() ?? '',
                    'requested_at' => $inquiry->requested_at?->diffForHumans() ?? '',
                    'status' => $inquiry->status,
                ];
            });

        $myReturned = SupportInquiry::with(['chatSession', 'user'])
            ->where('assigned_admin_id', $admin->id)
            ->where('status', SupportInquiry::STATUS_RETURNED_AI)
            ->orderBy('returned_to_ai_at', 'desc')
            ->get()
            ->map(function ($inquiry) {
                return [
                    'id' => $inquiry->id,
                    'ticket_number' => $inquiry->ticket_number,
                    'user_name' => $inquiry->user?->name ?? 'Guest',
                    'user_id' => $inquiry->user_id,
                    'requested_at' => $inquiry->requested_at?->diffForHumans() ?? '',
                    'returned_to_ai_at' => $inquiry->returned_to_ai_at?->diffForHumans() ?? '',
                    'status' => $inquiry->status,
                ];
            });

        return response()->json([
            'pending' => $pending,
            'my_active' => $myActive,
            'my_returned' => $myReturned,
            'pending_count' => $pending->count(),
        ]);
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('admin');
        $afterId = (int) ($request->query('after_id', 0));

        $inquiry = SupportInquiry::findOrFail($id);

        if ($inquiry->status !== SupportInquiry::STATUS_PENDING
            && $inquiry->assigned_admin_id !== $admin->id) {
            abort(403, 'This inquiry is assigned to another administrator.');
        }

        $session = $inquiry->chatSession;
        $messages = $session->messages()
            ->when($afterId > 0, fn($q) => $q->where('id', '>', $afterId))
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($msg) => [
                'id' => $msg->id,
                'sender' => $msg->sender,
                'text' => $msg->message,
                'created_at' => $msg->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'inquiry' => [
                'id' => $inquiry->id,
                'ticket_number' => $inquiry->ticket_number,
                'status' => $inquiry->status,
                'assigned_admin_id' => $inquiry->assigned_admin_id,
                'user_name' => $inquiry->user?->name ?? 'Guest',
            ],
            'messages' => $messages,
        ]);
    }

    public function claim(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('admin');

        try {
            $inquiry = $this->supportQueue->claimInquiry($id, $admin->id);
            return response()->json(['status' => 'success', 'inquiry' => ['id' => $inquiry->id, 'ticket_number' => $inquiry->ticket_number, 'status' => $inquiry->status]]);
        } catch (RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        }
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('admin');
        $validated = $request->validate(['message' => 'required|string|max:2000']);

        $inquiry = SupportInquiry::findOrFail($id);

        if ($inquiry->assigned_admin_id !== $admin->id) {
            abort(403);
        }

        $msg = $this->supportQueue->sendAdminMessage($inquiry, $validated['message']);

        return response()->json([
            'status' => 'success',
            'message' => ['id' => $msg->id, 'sender' => 'admin', 'text' => $msg->message, 'created_at' => $msg->created_at?->toIso8601String()],
        ]);
    }

    public function resumeAi(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('admin');
        $inquiry = SupportInquiry::findOrFail($id);

        if ($inquiry->assigned_admin_id !== $admin->id) {
            abort(403);
        }

        $this->supportQueue->resumeAi($inquiry);

        return response()->json(['status' => 'success']);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('admin');
        $inquiry = SupportInquiry::findOrFail($id);

        if ($inquiry->assigned_admin_id !== $admin->id) {
            abort(403);
        }

        $this->supportQueue->resolve($inquiry);

        return response()->json(['status' => 'success']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SupportInquiry;
use App\Services\Chat\ChatbotService;
use App\Services\Chat\ConversationManager;
use App\Services\Support\SupportQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    private const WARNING_MARKER = '⚠️ Account notice:';

    public function __construct(
        protected ChatbotService $chatbot,
        protected ConversationManager $conversation,
        protected SupportQueueService $supportQueue,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'session_token' => 'nullable|string|max:255',
            'user_lat' => 'nullable|numeric|between:-90,90',
            'user_lng' => 'nullable|numeric|between:-180,180',
        ]);

        $user = $request->user();
        $session = $this->conversation->resolveSession(
            $validated['session_token'] ?? null,
            $user
        );

        $result = $this->chatbot->handle(
            $session,
            $user,
            $validated['message'],
            isset($validated['user_lat']) ? (float) $validated['user_lat'] : null,
            isset($validated['user_lng']) ? (float) $validated['user_lng'] : null
        );

        if (! empty($result['blocked'])) {
            return response()->json([
                'status' => 'blocked',
                'reply' => $result['response'],
            ], 403);
        }

        if (($result['status'] ?? null) === 'success' && isset($result['reply']) && $user && $user->isWarned()) {
            $alreadyNotified = $session->messages()
                ->where('sender', 'bot')
                ->where('message', 'like', self::WARNING_MARKER.'%')
                ->exists();

            if (! $alreadyNotified) {
                $notice = self::WARNING_MARKER.' Your account has been warned for inappropriate usage.'
                    .($user->ban_reason ? ' Admin note: '.$user->ban_reason : '');
                $result['reply'] = $notice."\n\n".$result['reply'];
                $session->messages()->where('sender', 'bot')->latest('id')->first()?->update(['message' => $result['reply']]);
            }
        }

        return response()->json($result);
    }

    public function active(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['has_active' => false]);
        }

        $session = $this->conversation->latestForUser($user);
        if (! $session) {
            return response()->json(['has_active' => false]);
        }

        $inquiry = SupportInquiry::where('chat_session_id', $session->id)
            ->whereIn('status', [SupportInquiry::STATUS_PENDING, SupportInquiry::STATUS_HUMAN_ACTIVE])
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $inquiry) {
            // No active handoff but still return latest session so widget can re-sync token after logout rotation.
            return response()->json([
                'has_active' => false,
                'session_token' => $session->session_token,
                'handoff_status' => null,
            ]);
        }

        return response()->json([
            'has_active' => true,
            'session_token' => $session->session_token,
            'handoff_status' => $inquiry->status,
            'ticket_number' => $inquiry->ticket_number,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $token = $request->query('session_token');
        $user = $request->user();

        // Authenticated users without a token (lost localStorage) recover via latestForUser instead of empty.
        if (! $token) {
            if ($user) {
                $latest = $this->conversation->latestForUser($user);
                if ($latest) {
                    $token = $latest->session_token;
                } else {
                    return response()->json(['messages' => [], 'session_token' => null, 'handoff_status' => null]);
                }
            } else {
                return response()->json(['messages' => [], 'session_token' => null, 'handoff_status' => null]);
            }
        }

        $session = $this->conversation->resolveSession($token, $user);

        // If guest just probed an authed token, resolveSession created a fresh guest session.
        // Signal isolation so the widget keeps the owner's original token instead of overwriting it.
        $isolatedGuest = ! $user && $token !== $session->session_token;

        $messages = $session->messages()
            ->oldest('created_at')
            ->get()
            ->values()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'sender' => $msg->sender,
                'text' => $msg->message,
                'context_data' => $msg->context_data,
            ]);

        $inquiry = SupportInquiry::where('chat_session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'session_token' => $session->session_token,
            'handoff_status' => $inquiry?->status ?? null,
            'messages' => $messages,
            'isolated' => $isolatedGuest,
        ]);
    }

    public function handoff(Request $request): JsonResponse
    {
        $token = $request->input('session_token');
        if (! $token) {
            return response()->json(['status' => 'error', 'message' => 'No session token.'], 400);
        }

        $session = $this->conversation->resolveSession($token, $request->user());
        $inquiry = $this->supportQueue->requestHandoff($session);

        return response()->json([
            'status' => 'success',
            'handoff_status' => $inquiry->status,
            'ticket_number' => $inquiry->ticket_number,
            'session_token' => $session->session_token,
        ]);
    }

    public function cancelHandoff(Request $request): JsonResponse
    {
        $token = $request->input('session_token');
        if (! $token) {
            return response()->json(['status' => 'error', 'message' => 'No session token.'], 400);
        }

        $session = $this->conversation->resolveSession($token, $request->user());
        $this->supportQueue->cancelHandoff($session);

        return response()->json(['status' => 'success', 'handoff_status' => SupportInquiry::STATUS_AI_ACTIVE]);
    }

    public function returnToBot(Request $request): JsonResponse
    {
        $token = $request->input('session_token');
        if (! $token) {
            return response()->json(['status' => 'error', 'message' => 'No session token.'], 400);
        }

        $session = $this->conversation->resolveSession($token, $request->user());

        $inquiry = SupportInquiry::where('chat_session_id', $session->id)
            ->where('status', SupportInquiry::STATUS_HUMAN_ACTIVE)
            ->orderBy('created_at', 'desc')
            ->first();

        $message = null;
        if ($inquiry) {
            $message = $this->supportQueue->resumeAi($inquiry);
        }

        return response()->json([
            'status' => 'success',
            'handoff_status' => $inquiry?->status ?? SupportInquiry::STATUS_AI_ACTIVE,
            'session_token' => $session->session_token,
            'message' => $message ? [
                'id' => $message->id,
                'sender' => $message->sender,
                'text' => $message->message,
                'created_at' => $message->created_at->toIso8601String(),
            ] : null,
        ]);
    }

    public function poll(Request $request): JsonResponse
    {
        $token = $request->query('session_token');
        $afterId = (int) ($request->query('after_id', 0));
        $user = $request->user();

        if (! $token) {
            if ($user) {
                $latest = $this->conversation->latestForUser($user);
                if ($latest) {
                    $token = $latest->session_token;
                } else {
                    return response()->json(['status' => 'error', 'message' => 'No session token.'], 400);
                }
            } else {
                return response()->json(['status' => 'error'], 400);
            }
        }

        $session = $this->conversation->resolveSession($token, $user);
        $isolatedGuest = ! $user && $token !== $session->session_token;

        $inquiry = SupportInquiry::where('chat_session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $messages = $session->messages()
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->whereIn('sender', ['admin', 'bot'])
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'sender' => $msg->sender,
                'text' => $msg->message,
                'context_data' => $msg->context_data,
            ]);

        return response()->json([
            'session_token' => $session->session_token,
            'handoff_status' => $inquiry?->status ?? null,
            'messages' => $messages,
            'isolated' => $isolatedGuest,
        ]);
    }
}

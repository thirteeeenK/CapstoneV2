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
        ]);

        $user = $request->user();
        $session = $this->conversation->resolveSession(
            $validated['session_token'] ?? null,
            $user
        );

        $result = $this->chatbot->handle($session, $user, $validated['message']);

        if (!empty($result['blocked'])) {
            return response()->json([
                'status' => 'blocked',
                'reply' => $result['response'],
            ], 403);
        }

        return response()->json($result);
    }

    public function history(Request $request): JsonResponse
    {
        $token = $request->query('session_token');
        if (!$token) {
            return response()->json(['messages' => []]);
        }

        $session = $this->conversation->resolveSession($token, $request->user());

        $messages = $session->messages()
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($msg) => [
                'id' => $msg->id,
                'sender' => $msg->sender,
                'text' => $msg->message,
                'context_data' => $msg->context_data,
            ]);

        return response()->json([
            'session_token' => $session->session_token,
            'messages' => $messages,
        ]);
    }

    public function handoff(Request $request): JsonResponse
    {
        $token = $request->input('session_token');
        if (!$token) {
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
        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'No session token.'], 400);
        }

        $session = $this->conversation->resolveSession($token, $request->user());
        $this->supportQueue->cancelHandoff($session);

        return response()->json(['status' => 'success', 'handoff_status' => SupportInquiry::STATUS_AI_ACTIVE]);
    }

    public function poll(Request $request): JsonResponse
    {
        $token = $request->query('session_token');
        $afterId = (int) ($request->query('after_id', 0));

        if (!$token) {
            return response()->json(['status' => 'error'], 400);
        }

        $session = $this->conversation->resolveSession($token, $request->user());

        $inquiry = SupportInquiry::where('chat_session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $messages = $session->messages()
            ->when($afterId > 0, fn($q) => $q->where('id', '>', $afterId))
            ->whereIn('sender', ['admin', 'bot'])
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($msg) => [
                'id' => $msg->id,
                'sender' => $msg->sender,
                'text' => $msg->message,
                'context_data' => $msg->context_data,
            ]);

        return response()->json([
            'session_token' => $session->session_token,
            'handoff_status' => $inquiry?->status ?? null,
            'messages' => $messages,
        ]);
    }
}

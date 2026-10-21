<?php

namespace App\Http\Controllers;

use App\Models\ChatFeedback;
use App\Models\ChatSession;
use App\Models\SupportInquiry;
use App\Services\Chat\ChatbotService;
use App\Services\Chat\ConversationManager;
use App\Services\Chat\DialogueManager;
use App\Services\Chat\TripDetailsForm;
use App\Services\Support\SupportAvailabilityService;
use App\Services\Support\SupportQueueService;
use App\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ChatbotController extends Controller
{
    private const WARNING_MARKER = '⚠️ Account notice:';

    private const GUEST_NUDGE_THRESHOLD = 10;

    /**
     * Longest message the chat turn accepts. Surfaced to the widget so the
     * limit is visible before a send rather than after a failed one.
     */
    public const MESSAGE_LIMIT = 1000;

    public function __construct(
        protected ChatbotService $chatbot,
        protected ConversationManager $conversation,
        protected SupportQueueService $supportQueue,
        protected DialogueManager $dialogue,
        protected TripDetailsForm $tripDetailsForm,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // A structured answer replaces the typed message, so exactly one
            // of the two must be present.
            'message' => 'required_without_all:answer,trip_details|nullable|string|prohibits:answer,trip_details|max:'.self::MESSAGE_LIMIT,
            'answer' => 'required_without_all:message,trip_details|nullable|array|prohibits:trip_details|max:8',
            'trip_details' => 'sometimes|required|array|max:13',
            'form_id' => 'required_with:trip_details|uuid',
            'form_revision' => 'required_with:trip_details|integer|min:0',
            'scope_id' => 'required_with:trip_details|string|max:100',
            'answer.*' => 'nullable',
            'session_token' => 'nullable|string|max:255',
            'user_lat' => 'nullable|numeric|between:-90,90',
            'user_lng' => 'nullable|numeric|between:-180,180',
            'question_id' => 'nullable|string|max:80|required_with:question_revision,answer|prohibits:trip_details',
            'question_revision' => 'nullable|integer|min:0|required_with:question_id',
        ], [
            'message.max' => 'Your message is longer than '.self::MESSAGE_LIMIT.' characters. Please shorten it and send again.',
        ]);

        $user = $request->user();
        $session = $this->conversation->resolveSession(
            $validated['session_token'] ?? null,
            $user
        );

        $lock = Cache::lock('chat-turn:'.$session->id, 300);
        if (! $lock->get()) {
            return response()->json(['status' => 'busy', 'reply' => 'Your previous request is still being checked. Please try again when it finishes.'], 409);
        }
        try {
            $session->refresh();
            $tripDetails = null;
            $message = isset($validated['message']) ? (string) $validated['message'] : '';
            if (isset($validated['trip_details'])) {
                $state = $this->dialogue->state($session);
                $form = $state['trip_details_form'] ?? null;
                if (! config('services.chatbot.guided_conversation_enabled', false) || ! is_array($form)
                    || $form['id'] !== $validated['form_id'] || $state['revision'] !== (int) $validated['form_revision'] || $form['scope_id'] !== $validated['scope_id']
                    || ! collect($state['tasks'])->contains(fn (array $task): bool => $task['scope_id'] === $validated['scope_id'] && ! in_array($task['status'], ['cancelled', 'suspended'], true))) {
                    return response()->json(['status' => 'stale_form', 'reply' => 'Your trip details changed. Please use the latest form.', 'trip_details_form' => $form], 409);
                }
                $tripDetails = $this->tripDetailsForm->validate($form, $validated['trip_details']);
                $tripDetails['scope_id'] = $form['scope_id'];
                $message = $tripDetails['message'];
            }
            if (! empty($validated['question_id'])) {
                $state = $this->dialogue->state($session);
                $question = $state['active_question'] ?? null;
                if (! config('services.chatbot.guided_conversation_enabled', false) || ($question['id'] ?? null) !== $validated['question_id'] || ($question['revision'] ?? null) !== (int) $validated['question_revision']) {
                    // Checked inside the turn lock, before anything is stored:
                    // a stale submission never mutates history or dialogue.
                    return response()->json(['status' => 'stale_question', 'reply' => 'That question has changed. '.($question['text'] ?? 'Please send your next request.'), 'clarification' => $question], 409);
                }
                if (isset($validated['answer']) && is_array($validated['answer'])) {
                    $normalized = $this->dialogue->answerFromControl($question, $validated['answer']);
                    if (isset($normalized['errors'])) {
                        return response()->json([
                            'status' => 'invalid_answer',
                            'message' => 'Please correct the highlighted fields.',
                            'errors' => $normalized['errors'],
                            'clarification' => $question,
                        ], 422);
                    }
                    $message = $normalized['message'];
                }
            }
            if (trim($message) === '') {
                return response()->json([
                    'status' => 'invalid_answer',
                    'message' => 'Please type a message or answer the question.',
                    'errors' => ['message' => 'A message is required.'],
                ], 422);
            }
            $result = $this->chatbot->checkRepetition($session, $user, $message, $request->ip())
                ?? $this->chatbot->handle(
                    $session,
                    $user,
                    $message,
                    isset($validated['user_lat']) ? (float) $validated['user_lat'] : null,
                    isset($validated['user_lng']) ? (float) $validated['user_lng'] : null,
                    $tripDetails
                );

            if (! empty($result['blocked'])) {
                return response()->json(array_filter([
                    'status' => 'blocked',
                    'reply' => $result['response'],
                    'suggested_actions' => $result['suggested_actions'] ?? null,
                ], fn ($value) => $value !== null), 403);
            }

            if (! $user && ($result['status'] ?? null) === 'success' && ($result['control'] ?? null) === 'ai' && isset($result['reply'])) {
                $nudge = $this->guestNudgeSuffix($session);
                if ($nudge !== null && ! str_ends_with($result['reply'], $nudge)) {
                    $result['reply'] .= "\n\n".$nudge;
                    $session->messages()->where('sender', 'bot')->latest('id')->first()?->update(['message' => $result['reply']]);
                }
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

            if ((isset($validated['answer']) || $tripDetails !== null) && ($result['status'] ?? null) === 'success') {
                $result['user_message'] = $message;
            }

            return response()->json($result);
        } finally {
            $lock->release();
        }
    }

    /**
     * RSC action: guests chatting past the question threshold get a login /
     * agency-contact nudge on every AI reply. Returns null for authed users
     * or sessions still under the threshold.
     */
    private function guestNudgeSuffix(ChatSession $session): ?string
    {
        $questionCount = $session->messages()->where('sender', 'user')->count();

        if ($questionCount < self::GUEST_NUDGE_THRESHOLD) {
            return null;
        }
        $settings = app(SystemSettingsService::class);

        return $settings->revision('business') === 0 ? ChatbotService::GUEST_NUDGE
            : "You're chatting as a guest — log in or register for the full experience. For more inquiries, contact ".$settings->businessContact().'.';
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

    public function feedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trace_id' => 'required|string|max:255',
            'helpful' => 'required|boolean',
            'expected_answer' => 'nullable|string|max:2000',
            'session_token' => 'nullable|string|max:255',
        ]);

        $session = null;
        if (! empty($validated['session_token'])) {
            $session = $this->conversation->resolveSession($validated['session_token'], $request->user());
        }

        $feedback = ChatFeedback::create([
            'trace_id' => $validated['trace_id'],
            'chat_session_id' => $session?->id,
            'helpful' => $validated['helpful'],
            'expected_answer' => $validated['expected_answer'] ?? null,
        ]);

        return response()->json(['status' => 'success', 'id' => $feedback->id], 201);
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

        if (! $inquiry) {
            $availability = app(SupportAvailabilityService::class);

            return response()->json([
                'status' => 'support_unavailable', 'message' => $availability->status()['message'],
                'office_hours' => $availability->summary(),
            ]);
        }

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

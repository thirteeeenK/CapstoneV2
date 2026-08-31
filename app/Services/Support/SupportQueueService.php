<?php

namespace App\Services\Support;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\SupportInquiry;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SupportQueueService
{
    public function initiateDirectMessage(int $userId, int $adminId, string $message): array
    {
        return DB::transaction(function () use ($userId, $adminId, $message) {
            $user = User::findOrFail($userId);

            if ($user->isBanned()) {
                throw new RuntimeException('Cannot message a banned user.');
            }

            // If user already has an active inquiry, reuse it — prevents duplicate active per user.
            $active = SupportInquiry::where('user_id', $userId)
                ->whereIn('status', [SupportInquiry::STATUS_PENDING, SupportInquiry::STATUS_HUMAN_ACTIVE])
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($active) {
                if ($active->status === SupportInquiry::STATUS_HUMAN_ACTIVE && (int) $active->assigned_admin_id !== $adminId) {
                    throw new RuntimeException('This user already has an active conversation with another administrator.');
                }

                // Reuse existing inquiry — just append admin message.
                if ($active->status === SupportInquiry::STATUS_PENDING) {
                    $active->update([
                        'status' => SupportInquiry::STATUS_HUMAN_ACTIVE,
                        'assigned_admin_id' => $adminId,
                        'assigned_at' => now(),
                    ]);
                }

                $chatMessage = $this->sendAdminMessage($active, $message);

                return ['inquiry' => $active->fresh(), 'message' => $chatMessage];
            }

            // Find or create a ChatSession for this user (reuse latest if exists).
            $session = ChatSession::where('user_id', $userId)->latest('updated_at')->first();
            if (! $session) {
                $session = ChatSession::create([
                    'session_token' => ChatSession::generateToken(),
                    'user_id' => $userId,
                ]);
            }

            $ticket = 'TKT-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            while (SupportInquiry::where('ticket_number', $ticket)->exists()) {
                $ticket = 'TKT-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            }

            try {
                $inquiry = SupportInquiry::create([
                    'ticket_number' => $ticket,
                    'chat_session_id' => $session->id,
                    'user_id' => $userId,
                    'assigned_admin_id' => $adminId,
                    'status' => SupportInquiry::STATUS_HUMAN_ACTIVE,
                    'initiated_by' => SupportInquiry::INITIATED_BY_ADMIN,
                    'requested_at' => now(),
                    'assigned_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                $inquiry = SupportInquiry::where('chat_session_id', $session->id)
                    ->whereIn('status', [SupportInquiry::STATUS_PENDING, SupportInquiry::STATUS_HUMAN_ACTIVE])
                    ->firstOrFail();
                $chatMessage = $this->sendAdminMessage($inquiry, $message);

                return ['inquiry' => $inquiry, 'message' => $chatMessage];
            }

            $chatMessage = $this->sendAdminMessage($inquiry, $message);

            return ['inquiry' => $inquiry, 'message' => $chatMessage];
        });
    }

    public function requestHandoff(ChatSession $session): SupportInquiry
    {
        $existing = SupportInquiry::where('chat_session_id', $session->id)
            ->whereIn('status', [SupportInquiry::STATUS_PENDING, SupportInquiry::STATUS_HUMAN_ACTIVE])
            ->first();

        if ($existing) {
            return $existing;
        }

        $ticket = 'TKT-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

        while (SupportInquiry::where('ticket_number', $ticket)->exists()) {
            $ticket = 'TKT-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        }

        try {
            return SupportInquiry::create([
                'ticket_number' => $ticket,
                'chat_session_id' => $session->id,
                'user_id' => $session->user_id,
                'status' => SupportInquiry::STATUS_PENDING,
                'initiated_by' => SupportInquiry::INITIATED_BY_USER,
                'requested_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Concurrent request won the race — return its ticket.
            return SupportInquiry::where('chat_session_id', $session->id)
                ->whereIn('status', [SupportInquiry::STATUS_PENDING, SupportInquiry::STATUS_HUMAN_ACTIVE])
                ->firstOrFail();
        }
    }

    public function claimInquiry(int $inquiryId, int $adminId): SupportInquiry
    {
        return DB::transaction(function () use ($inquiryId, $adminId) {
            $inquiry = SupportInquiry::where('id', $inquiryId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inquiry->status !== SupportInquiry::STATUS_PENDING) {
                throw new RuntimeException('This inquiry has already been claimed by another administrator.');
            }

            $inquiry->update([
                'status' => SupportInquiry::STATUS_HUMAN_ACTIVE,
                'assigned_admin_id' => $adminId,
                'assigned_at' => now(),
            ]);

            return $inquiry;
        });
    }

    public function sendAdminMessage(SupportInquiry $inquiry, string $message): ChatMessage
    {
        return ChatMessage::create([
            'chat_session_id' => $inquiry->chat_session_id,
            'sender' => 'admin',
            'message' => $message,
        ]);
    }

    public function resumeAi(SupportInquiry $inquiry): ChatMessage
    {
        $inquiry->update([
            'status' => SupportInquiry::STATUS_RETURNED_AI,
            'returned_to_ai_at' => now(),
        ]);

        return $this->createHandoffEndedMessage($inquiry->chat_session_id);
    }

    public function resolve(SupportInquiry $inquiry): ChatMessage
    {
        $inquiry->update([
            'status' => SupportInquiry::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);

        return $this->createHandoffEndedMessage($inquiry->chat_session_id);
    }

    public function cancelHandoff(ChatSession $session): void
    {
        SupportInquiry::where('chat_session_id', $session->id)
            ->where('status', SupportInquiry::STATUS_PENDING)
            ->update(['status' => SupportInquiry::STATUS_AI_ACTIVE]);
    }

    public function closeOpenInquiriesForUser(int $userId): int
    {
        $sessionIds = ChatSession::where('user_id', $userId)->pluck('id');

        return SupportInquiry::whereIn('chat_session_id', $sessionIds)
            ->whereIn('status', [SupportInquiry::STATUS_PENDING, SupportInquiry::STATUS_HUMAN_ACTIVE])
            ->update([
                'status' => SupportInquiry::STATUS_RESOLVED,
                'resolved_at' => now(),
            ]);
    }

    private function createHandoffEndedMessage(int $chatSessionId): ChatMessage
    {
        $message = ChatMessage::create([
            'chat_session_id' => $chatSessionId,
            'sender' => 'bot',
            'message' => 'The support chat has ended. SunnyBot is back! You can ask me anything about your island trip.',
        ]);

        return $message->fresh();
    }
}

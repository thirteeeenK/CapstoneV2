<?php

namespace App\Services\Support;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\AdminModel;
use App\Models\SupportInquiry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SupportQueueService
{
    public function requestHandoff(ChatSession $session): SupportInquiry
    {
        $existing = SupportInquiry::where('chat_session_id', $session->id)
            ->whereIn('status', [SupportInquiry::STATUS_PENDING, SupportInquiry::STATUS_HUMAN_ACTIVE])
            ->first();

        if ($existing) {
            return $existing;
        }

        $ticket = 'TKT-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

        while (SupportInquiry::where('ticket_number', $ticket)->exists()) {
            $ticket = 'TKT-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        }

        return SupportInquiry::create([
            'ticket_number' => $ticket,
            'chat_session_id' => $session->id,
            'user_id' => $session->user_id,
            'status' => SupportInquiry::STATUS_PENDING,
            'requested_at' => now(),
        ]);
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

    public function resumeAi(SupportInquiry $inquiry): void
    {
        $inquiry->update([
            'status' => SupportInquiry::STATUS_RETURNED_AI,
            'returned_to_ai_at' => now(),
        ]);
    }

    public function resolve(SupportInquiry $inquiry): void
    {
        $inquiry->update([
            'status' => SupportInquiry::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
    }

    public function cancelHandoff(ChatSession $session): void
    {
        SupportInquiry::where('chat_session_id', $session->id)
            ->where('status', SupportInquiry::STATUS_PENDING)
            ->update(['status' => SupportInquiry::STATUS_AI_ACTIVE]);
    }
}

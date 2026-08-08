<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Support\Str;

class ConversationManager
{
    public function resolveSession(?string $token, ?User $user): ChatSession
    {
        $session = null;

        if ($token) {
            $session = ChatSession::where('session_token', $token)->first();
        }

        if ($session && $session->user_id !== null
            && (!$user || (int) $session->user_id !== (int) $user->id)) {
            $session = null;
        }

        if (!$session) {
            $token = ChatSession::generateToken();
            $session = ChatSession::create([
                'session_token' => $token,
                'user_id' => $user?->id,
            ]);
        }

        if ($user && $session->isGuest()) {
            $session->claimFor($user);
        }

        return $session;
    }

    public function findOrCreateGuest(string $token): ChatSession
    {
        return ChatSession::firstOrCreate(
            ['session_token' => $token],
            ['user_id' => null]
        );
    }

    public function history(ChatSession $session, int $turns = 6): array
    {
        $messages = $session->messages()
            ->latest('created_at')
            ->limit($turns * 2)
            ->get()
            ->reverse()
            ->values();

        $contents = [];
        foreach ($messages as $msg) {
            if ($msg->sender === 'admin') {
                $contents[] = [
                    'role' => 'model',
                    'parts' => [['text' => 'Support Agent (human): ' . $msg->message]],
                ];
            } else {
                $role = $msg->sender === 'user' ? 'user' : 'model';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg->message]],
                ];
            }
        }

        return $contents;
    }

    public function persist(ChatSession $session, string $sender, string $message, ?array $contextData = null): ChatMessage
    {
        return ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender' => $sender,
            'message' => $message,
            'context_data' => $contextData,
        ]);
    }

    public function summarizeHistory(ChatSession $session): ?string
    {
        $count = $session->messages()->count();
        if ($count < 20) {
            return null;
        }

        $oldMessages = $session->messages()
            ->where('sender', 'user')
            ->oldest('created_at')
            ->limit(max(1, $count - 12))
            ->pluck('message');

        return 'Earlier in the conversation, the user asked: ' . $oldMessages->implode('; ') . '.';
    }
}

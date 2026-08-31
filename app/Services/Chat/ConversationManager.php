<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;

class ConversationManager
{
    /**
     * Most recent session belonging to the user that has at least one message.
     * Used so authenticated history survives logout+login even when localStorage token is stale.
     */
    public function latestForUser(User $user): ?ChatSession
    {
        return ChatSession::where('user_id', $user->id)
            ->whereHas('messages')
            ->latest('updated_at')
            ->first()
            ?? ChatSession::where('user_id', $user->id)->latest('updated_at')->first();
    }

    public function resolveSession(?string $token, ?User $user): ChatSession
    {
        $session = null;

        if ($token) {
            $session = ChatSession::where('session_token', $token)->first();
        }

        // Isolation: guest or different user probing someone else's authed session.
        if ($session && $session->user_id !== null && (! $user || (int) $session->user_id !== (int) $user->id)) {
            if (! $user) {
                // Guest probing authed token — give them a fresh guest session (security) without leaking.
                return ChatSession::create([
                    'session_token' => ChatSession::generateToken(),
                    'user_id' => null,
                ]);
            }

            // Authed user probing another user's token — return own latest instead of leaking.
            return $this->latestForUser($user)
                ?? ChatSession::create([
                    'session_token' => ChatSession::generateToken(),
                    'user_id' => $user->id,
                ]);
        }

        if (! $session) {
            if ($user) {
                // No token or token not found — recover latest account-bound session so history survives logout+login.
                return $this->latestForUser($user)
                    ?? ChatSession::create([
                        'session_token' => ChatSession::generateToken(),
                        'user_id' => $user->id,
                    ]);
            }

            return ChatSession::create([
                'session_token' => ChatSession::generateToken(),
                'user_id' => null,
            ]);
        }

        // Session found and accessible.
        if ($user && $session->isGuest()) {
            // If this guest session is an empty probe but the user already has a real history, prefer the real history.
            $latest = $this->latestForUser($user);
            if ($latest && $latest->id !== $session->id && $session->messages()->count() === 0) {
                return $latest;
            }
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
                    'parts' => [['text' => 'Support Agent (human): '.$msg->message]],
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

        return 'Earlier in the conversation, the user asked: '.$oldMessages->implode('; ').'.';
    }
}

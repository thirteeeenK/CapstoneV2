<?php

namespace App\Services\Chat;

final class ChatCapabilities
{
    /** @return array<string, string> */
    public function operations(): array
    {
        return [
            'book' => 'I can help you find a room, but I cannot complete a booking directly in chat. Use Add to Trip Basket on a room card, then continue through checkout.',
            'cancel' => 'I cannot cancel a reservation directly in chat. Open your booking details or request a human support agent to check the available options.',
            'pay' => 'I cannot make a payment directly in chat. Continue through the checkout and payment options shown for your booking.',
            'refund' => 'I cannot issue a refund directly in chat. Request a human support agent to review your booking and the applicable refund policy.',
            'contact' => 'I cannot send messages or call a hotel directly from chat. I can help draft a message for you to send.',
            'monitor' => 'I cannot keep checking prices or send a notification after this conversation. I can check the current catalog when you ask again.',
            'basket' => 'I cannot add an item to your basket through a chat command. Use Add to Trip Basket on the relevant result card.',
            'account' => 'I cannot change your account details directly in chat. Use your account settings or request a human support agent.',
        ];
    }

    public function detect(string $query): ?string
    {
        $lower = mb_strtolower(trim($query));
        if (preg_match('/^(?:how|where|what|can i|may i|paano|saan)\b/u', $lower)) {
            return null;
        }
        $patterns = [
            'monitor' => '/\b(?:monitor|watch|remind|notify|alert|keep\s+checking|tell\s+me\s+(?:tomorrow|later)|paalalahanan)\b/u',
            'refund' => '/\b(?:refund|ibalik\s+.*bayad)\b/u',
            'cancel' => '/\b(?:cancel|kanselahin)\b.*\b(?:booking|reservation|trip|it|this|that)\b/u',
            'contact' => '/\b(?:email|call|message|contact|text|send)\b.*\b(?:hotel|resort|them|host|email|message|request)\b/u',
            'basket' => '/\b(?:add|put|ilagay)\b.*\b(?:basket|cart)\b/u',
            'pay' => '/\b(?:pay|bayaran|charge\s+my)\b/u',
            'account' => '/\b(?:change|update|delete|reset)\b.*\b(?:my\s+)?(?:account|password|email\s+address|profile)\b/u',
            'book' => '/\b(?:book|reserve|i-book|magbook)\s+(?:(?:me|us)\s+)?(?:this|that|it|the|a|an|my|our|hotel|room|resort|ako|kami)\b/u',
        ];
        foreach ($patterns as $operation => $pattern) {
            if (preg_match($pattern, $lower)) {
                return $operation;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function limitation(string $operation, bool $taglish = false): array
    {
        $reply = $this->operations()[$operation] ?? 'I cannot perform that action directly in chat. I can help with travel information and planning.';
        if ($taglish) {
            $reply = 'Hindi ko ito magagawa directly sa chat. '.$reply;
        }

        return [
            'reply' => $reply,
            'retrieval_outcome' => RetrievalOutcome::make(RetrievalOutcome::Unsupported, 'action_not_supported'),
            'suggested_actions' => in_array($operation, ['cancel', 'refund', 'account'], true)
                ? [['id' => 'talk-to-agent', 'label' => 'Talk to a human agent', 'handoff' => true]] : [],
        ];
    }
}

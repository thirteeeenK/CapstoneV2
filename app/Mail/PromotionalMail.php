<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * An admin email campaign as one traveler receives it. Carries one-click
 * unsubscribe headers (RFC 8058), which Gmail and Yahoo require of bulk senders.
 */
class PromotionalMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload  rendered by EmailCampaignService::payload()
     */
    public function __construct(public array $payload, public string $idempotencyKey) {}

    public function headers(): Headers
    {
        $text = ['Resend-Idempotency-Key' => $this->idempotencyKey];
        if (! empty($this->payload['unsubscribe_url'])) {
            $text['List-Unsubscribe'] = '<'.$this->payload['unsubscribe_url'].'>';
            $text['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        return new Headers(text: $text);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->payload['subject']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.promotional', text: 'emails.promotional-text');
    }
}

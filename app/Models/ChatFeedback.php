<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['trace_id', 'chat_session_id', 'user_id', 'helpful', 'reason', 'task_id', 'expected_answer'])]
class ChatFeedback extends Model
{
    public const REASONS = [
        'wrong_information' => 'Wrong information',
        'missing_part' => 'Missing part',
        'not_helpful' => 'Not helpful',
    ];

    public $timestamps = false;

    /**
     * Free-text feedback keeps the diagnostic gist but not contact or
     * payment details a traveler may have typed into it.
     */
    public static function redact(string $text): string
    {
        $text = (string) preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', '[email]', $text);
        $text = (string) preg_replace('/(?:\+?63|0)9\d{2}[\s-]?\d{3}[\s-]?\d{4}\b/u', '[phone]', $text);

        return (string) preg_replace('/\b(?:\d[\s-]?){12,19}\b/u', '[number]', $text);
    }
}

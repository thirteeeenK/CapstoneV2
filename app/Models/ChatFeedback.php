<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['trace_id', 'chat_session_id', 'helpful', 'expected_answer'])]
class ChatFeedback extends Model
{
    public $timestamps = false;
}

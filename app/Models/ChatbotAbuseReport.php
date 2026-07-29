<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotAbuseReport extends Model
{
    use HasFactory;

    protected $table = 'chatbot_abuse_reports';

    protected $fillable = [
        'user_id',
        'message',
        'category',
        'reason',
        'status',
        'reviewed_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}

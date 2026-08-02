<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
// use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


#[Fillable([
    'name', 'email', 'phone_number', 'address', 'password',
    'is_banned', 'chatbot_flag_count', 'ban_reason',
    'terms_accepted_at', 'privacy_accepted_at', 'ai_disclosure_accepted_at',
    'terms_version', 'privacy_version', 'ai_disclosure_version',
    'consent_ip_address', 'consent_user_agent',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    // /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_banned' => 'boolean',
            'chatbot_flag_count' => 'integer',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'ai_disclosure_accepted_at' => 'datetime',
        ];
    }

    public function abuseReports()
    {
        return $this->hasMany(ChatbotAbuseReport::class, 'user_id');
    }
}

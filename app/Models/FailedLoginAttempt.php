<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedLoginAttempt extends Model
{
    protected $table = 'failed_login_attempts';

    protected $fillable = [
        'email',
        'ip_address',
        'guard',
        'attempted_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
    ];
}

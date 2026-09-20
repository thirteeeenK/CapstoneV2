<?php

use App\Models\ChatSession;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

ChatSession::where('session_token', 'browser-nudge-test')->delete();

$s = ChatSession::create(['session_token' => 'browser-nudge-test', 'user_id' => null]);
for ($i = 1; $i <= 9; $i++) {
    $s->messages()->create(['sender' => 'user', 'message' => "seed question {$i} about Boracay"]);
    $s->messages()->create(['sender' => 'bot', 'message' => "seed reply {$i}"]);
}
echo 'seeded: '.$s->session_token.' user_msgs='.$s->messages()->where('sender', 'user')->count().PHP_EOL;

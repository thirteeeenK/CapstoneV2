<?php

use App\Models\Faq;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

foreach (Faq::take(5)->get(['question']) as $f) {
    echo $f->question.PHP_EOL;
}

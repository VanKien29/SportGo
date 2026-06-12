<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apps = \App\Models\PartnerApplication::latest()->take(5)->get(['user_id', 'venue_name', 'created_at', 'status']);
foreach($apps as $a) {
    echo $a->user_id . ' | ' . $a->venue_name . ' | ' . $a->status . ' | ' . $a->created_at . "\n";
}

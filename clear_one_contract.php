<?php
use App\Models\PartnerApplication;
use App\Models\PartnerContract;
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$appToTest = PartnerApplication::where('status', 'approved')->first();
if ($appToTest) {
    PartnerContract::where('partner_application_id', $appToTest->id)->forceDelete();
    echo "Deleted contracts for application: " . $appToTest->venue_name . "\n";
}

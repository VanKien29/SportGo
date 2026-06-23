<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$application = \App\Models\PartnerApplication::latest()->first();
if (!$application) {
    die("No application found\n");
}
$svc = app(\App\Services\Partner\PartnerApplicationService::class);
$reflection = new ReflectionClass($svc);
$method = $reflection->getMethod('generateApplicationForm');
$method->setAccessible(true);

echo "Generating for app ID: " . $application->id . "\n";
$doc = $method->invoke($svc, $application, $application->user, []);
$path = \Illuminate\Support\Facades\Storage::disk('local')->path($doc->generated_file_path);
echo "Generated path: " . $path . "\n";

$zip = new ZipArchive();
if ($zip->open($path) === true) {
    $xml = $zip->getFromName('word/document.xml');
    if (strpos($xml, '{{') !== false) {
        echo "WARNING: Placeholders {{ still exist!\n";
        preg_match_all('/{{.*?}}/', $xml, $matches);
        print_r($matches[0]);
    } else {
        echo "SUCCESS: No {{ placeholders found.\n";
    }
} else {
    echo "Could not open ZIP file.\n";
}

<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pdf = '%PDF-1.4' . "\n" . '1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>/Contents 4 0 R>>endobj 4 0 obj<</Length 21>>stream' . "\n" . 'BT /F1 24 Tf 100 700 Td (Contract) Tj ET' . "\n" . 'endstream' . "\n" . 'endobj' . "\n" . 'xref' . "\n" . '0 5' . "\n" . '0000000000 65535 f ' . "\n" . '0000000009 00000 n ' . "\n" . '0000000052 00000 n ' . "\n" . '0000000101 00000 n ' . "\n" . '0000000188 00000 n ' . "\n" . 'trailer<</Size 5/Root 1 0 R>>' . "\n" . 'startxref' . "\n" . '256' . "\n" . '%%EOF';

$contracts = App\Models\PartnerContract::whereNotNull('generated_file_path')->get();
foreach ($contracts as $c) {
    Illuminate\Support\Facades\Storage::disk('public')->put($c->generated_file_path, $pdf);
    echo $c->generated_file_path . " created\n";
}

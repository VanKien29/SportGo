<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contracts = App\Models\PartnerContract::whereNotNull('generated_file_path')->get();
$sourceFile = base_path('database/seeders/templates/partner-documents/Mau_02_Hop_dong_hop_tac_doi_tac_SportGo.docx');

foreach ($contracts as $c) {
    $newPath = str_replace('.pdf', '.docx', $c->generated_file_path);
    Illuminate\Support\Facades\Storage::disk('public')->put($newPath, file_get_contents($sourceFile));
    $c->generated_file_path = $newPath;
    $c->save();
    echo $newPath . " updated\n";
}

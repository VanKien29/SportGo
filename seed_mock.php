<?php
use App\Models\PartnerApplication;
use App\Models\ContractTemplate;
use App\Models\PartnerContract;
use App\Models\PartnerDocument;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$template = ContractTemplate::firstOrCreate(
    ['name' => 'Hợp đồng tiêu chuẩn'],
    ['file_path' => '/storage/template.pdf', 'is_active' => true]
);

$applications = PartnerApplication::where('status', 'approved')->get();
foreach ($applications as $application) {
    if (!$application->contracts()->count()) {
        PartnerContract::forceCreate([
            'partner_application_id' => $application->id,
            'contract_template_id' => $template->id,
            'contract_number' => 'HD-TEST-' . uniqid(),
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    if (!$application->documents()->count()) {
        PartnerDocument::forceCreate([
            'partner_application_id' => $application->id,
            'type' => 'business_license',
            'file_name' => 'GPKD.pdf',
            'file_path' => '/storage/gpkd.pdf',
        ]);
    }
}
echo "Seeded mock data successfully!\n";

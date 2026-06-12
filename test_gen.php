<?php
$application = \App\Models\PartnerApplication::find('019eb738-7284-7001-8a02-b3f4df9cd42d');
$template = \App\Models\ContractTemplate::first();

if ($template) {
    try {
        $contract = app(\App\Services\Partner\ContractGenerationService::class)->generate($application->id, $template->id);
        echo "Contract created: " . $contract->id . "\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "No template found.\n";
}

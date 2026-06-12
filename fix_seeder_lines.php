<?php
$lines = file('database/seeders/PartnerApplicationsTableSeeder.php');
if (substr($lines[0], 0, 3) === "\xEF\xBB\xBF") {
    $lines[0] = substr($lines[0], 3);
}

$ignoreFields = ['applicant_full_name', 'applicant_phone', 'applicant_email', 'applicant_address', 'applicant_type', 'representative_name', 'representative_identity_type', 'representative_identity_number', 'representative_identity_issued_date', 'representative_identity_issued_place', 'representative_position', 'business_code', 'business_license_number', 'business_address', 'business_representative_name', 'business_representative_position', 'venue_province', 'venue_district', 'venue_ward', 'venue_phone', 'venue_email', 'venue_description', 'expected_opening_hours', 'parking_info', 'amenities', 'court_count_total', 'current_contract_id', 'bank_name', 'bank_code', 'account_number', 'account_holder_name', 'bank_branch', 'bank_verification_status'];

$output = '';
$skipForceFill = false;

foreach ($lines as $line) {
    if (strpos($line, '->forceFill([') !== false) {
        $skipForceFill = true;
        continue;
    }
    if ($skipForceFill) {
        if (strpos($line, '])->save') !== false) {
            $skipForceFill = false;
        }
        continue;
    }

    $skipLine = false;
    foreach ($ignoreFields as $field) {
        if (strpos($line, "'" . $field . "'") !== false && strpos($line, '=>') !== false) {
            $skipLine = true;
            break;
        }
    }
    if ($skipLine) continue;

    $line = str_replace("'contract_code'", "'contract_number'", $line);
    $line = str_replace("'status' => 'completed'", "'status' => 'approved'", $line);
    $line = str_replace("'status' => 'contract_pending_owner_signature'", "'status' => 'approved'", $line);
    $line = str_replace("'status' => 'contract_pending_sportgo_signature'", "'status' => 'approved'", $line);
    $line = str_replace("'status' => 'approved_pending_contract'", "'status' => 'approved'", $line);
    $line = str_replace("'status' => 'submitted'", "'status' => 'pending'", $line);
    $line = str_replace("'status' => 'need_supplement'", "'status' => 'reviewing'", $line);

    $output .= $line;
}

file_put_contents('database/seeders/PartnerApplicationsTableSeeder.php', $output);

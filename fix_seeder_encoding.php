<?php
$content = file_get_contents('database/seeders/PartnerApplicationsTableSeeder.php');
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    $content = substr($content, 3);
}

$content = str_replace("'status' => 'completed'", "'status' => 'approved'", $content);
$content = str_replace("'status' => 'contract_pending_owner_signature'", "'status' => 'approved'", $content);
$content = str_replace("'status' => 'contract_pending_sportgo_signature'", "'status' => 'approved'", $content);
$content = str_replace("'status' => 'approved_pending_contract'", "'status' => 'approved'", $content);
$content = str_replace("'status' => 'submitted'", "'status' => 'pending'", $content);
$content = str_replace("'status' => 'need_supplement'", "'status' => 'reviewing'", $content);

$content = str_replace("'contract_code'", "'contract_number'", $content);
$content = preg_replace("/\\\->forceFill\(\\[.*?\\]\\)->save(?:Quietly)?\(\);/s", "", $content);

$fields = ['applicant_full_name', 'applicant_phone', 'applicant_email', 'applicant_address', 'applicant_type', 'representative_name', 'representative_identity_type', 'representative_identity_number', 'representative_identity_issued_date', 'representative_identity_issued_place', 'representative_position', 'business_code', 'business_license_number', 'business_address', 'business_representative_name', 'business_representative_position', 'venue_province', 'venue_district', 'venue_ward', 'venue_phone', 'venue_email', 'venue_description', 'expected_opening_hours', 'parking_info', 'amenities', 'court_count_total', 'current_contract_id'];

foreach ($fields as $field) {
    $content = preg_replace("/'".$field."' =>.*?\,/s", "", $content);
}

file_put_contents('database/seeders/PartnerApplicationsTableSeeder.php', $content);

<?php
$content = file_get_contents('database/seeders/PartnerApplicationsTableSeeder.php');
$content = str_replace("'contract_code'", "'contract_number'", $content);
$content = preg_replace("/'applicant_full_name' =>.*?'business_name' =>/s", "'business_name' =>", $content);
$content = preg_replace("/'business_code' =>.*?'tax_code' =>/s", "'tax_code' =>", $content);
$content = preg_replace("/'business_license_number' =>.*?'venue_address' =>/s", "'venue_address' =>", $content);
$content = preg_replace("/'venue_province' =>.*?'venue_map_url' =>/s", "'venue_map_url' =>", $content);
$content = preg_replace("/'venue_phone' =>.*?'status' =>/s", "'status' =>", $content);
$content = preg_replace("/'current_contract_id' =>.*?\,/s", "", $content);
file_put_contents('database/seeders/PartnerApplicationsTableSeeder.php', $content);

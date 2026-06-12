<?php
$content = file_get_contents('database/seeders/PartnerApplicationsTableSeeder.php');
$content = preg_replace("/'status' => 'completed'/", "'status' => 'approved'", $content);
$content = preg_replace("/'status' => 'contract_pending_owner_signature'/", "'status' => 'approved'", $content);
$content = preg_replace("/'status' => 'contract_pending_sportgo_signature'/", "'status' => 'approved'", $content);
$content = preg_replace("/'status' => 'approved_pending_contract'/", "'status' => 'approved'", $content);
$content = preg_replace("/'status' => 'submitted'/", "'status' => 'pending'", $content);
$content = preg_replace("/'status' => 'need_supplement'/", "'status' => 'reviewing'", $content);
file_put_contents('database/seeders/PartnerApplicationsTableSeeder.php', $content);

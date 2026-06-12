<?php
$content = file_get_contents('database/seeders/PartnerApplicationsTableSeeder.php');
$content = preg_replace("/\->forceFill\(\[.*?\)\->save\(\)\;/s", "", $content);
file_put_contents('database/seeders/PartnerApplicationsTableSeeder.php', $content);

<?php
$content = file_get_contents('database/seeders/PartnerApplicationsTableSeeder.php');
$content = str_replace("\", "", $content);
file_put_contents('database/seeders/PartnerApplicationsTableSeeder.php', $content);

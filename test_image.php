<?php
require 'vendor/autoload.php';
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

$manager = new ImageManager(new Driver());
$image = $manager->createImage(10, 10);
// V4 syntax:
$image->save('test.webp', quality: 80);
echo file_exists('test.webp') ? "SUCCESS_SAVE\n" : "FAIL\n";

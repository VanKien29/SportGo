<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$templatePath = storage_path('app/private/document-templates/partner_application_form_v1.docx');
$processor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
$processor->setMacroChars('{{', '}}');
$processor->setValue('full_name', 'TEST NAME');

// Try replacing signature_owner
try {
    // Generate a dummy image first
    $img = imagecreatetruecolor(100, 50);
    imagecolorallocate($img, 255, 255, 255);
    $textColor = imagecolorallocate($img, 0, 0, 0);
    imagestring($img, 5, 5, 5, 'SIGN', $textColor);
    $imgPath = storage_path('app/private/dummy_sign.png');
    imagepng($img, $imgPath);
    imagedestroy($img);

    $processor->setImageValue('signature_owner', ['path' => $imgPath, 'width' => 150, 'height' => 75]);
    $outPath = storage_path('app/private/test_signed.docx');
    $processor->saveAs($outPath);
    echo "Saved to: $outPath\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

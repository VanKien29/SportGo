<?php
$zip = new ZipArchive();
if ($zip->open('d:\FPT\laragon\www\DATN\storage\app\private\generated-documents\2026\06\DKDT-20260623-LKT6WI.docx') === true) {
    $xml = $zip->getFromName('word/document.xml');
    echo "Length of XML: " . strlen($xml) . "\n";
    $xmlText = strip_tags($xml);
    echo substr($xmlText, 0, 2000) . "...\n";
} else {
    echo "Could not open ZIP file.\n";
}

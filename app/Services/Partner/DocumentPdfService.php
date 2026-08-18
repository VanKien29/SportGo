<?php

namespace App\Services\Partner;

use Illuminate\Support\Facades\File;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;

/**
 * Converts private partner source files to controlled PDF copies.
 *
 * The source DOCX remains private and is never returned by the partner API.
 * PDF rendering is intentionally isolated here so a later renderer upgrade
 * does not change the authorization or audit code around it.
 */
class DocumentPdfService
{
    public function convertDocx(string $sourcePath, string $targetPath, string $watermark): void
    {
        $this->assertRendererAvailable();

        if (! is_file($sourcePath)) {
            throw new RuntimeException('Không tìm thấy file DOCX để chuyển sang PDF.');
        }

        $word = IOFactory::load($sourcePath, 'Word2007');
        $writer = IOFactory::createWriter($word, 'HTML');
        $html = $writer->getContent();

        $this->writeHtmlPdf($html, $targetPath, $watermark);
    }

    public function convertSource(
        string $sourcePath,
        string $targetPath,
        string $mimeType,
        string $watermark
    ): void {
        if (! is_file($sourcePath)) {
            throw new RuntimeException('Không tìm thấy file tài liệu nguồn.');
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $mimeType = strtolower(trim($mimeType));

        if ($mimeType === 'application/pdf' || $extension === 'pdf') {
            $this->ensureTargetDirectory($targetPath);
            if (! copy($sourcePath, $targetPath)) {
                throw new RuntimeException('Không thể lưu bản PDF bảo mật.');
            }

            return;
        }

        if (str_starts_with($mimeType, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $binary = file_get_contents($sourcePath);
            if ($binary === false) {
                throw new RuntimeException('Không thể đọc file hình ảnh tài liệu.');
            }

            $detectedMime = $mimeType !== '' && $mimeType !== 'application/octet-stream'
                ? $mimeType
                : (mime_content_type($sourcePath) ?: 'image/jpeg');
            $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
                . '@page{margin:28mm 16mm 22mm}body{margin:0;text-align:center}'
                . 'img{max-width:100%;max-height:245mm;object-fit:contain}'
                . '</style></head><body><img src="data:' . e($detectedMime) . ';base64,' . base64_encode($binary) . '"></body></html>';

            $this->writeHtmlPdf($html, $targetPath, $watermark);

            return;
        }

        if (in_array($extension, ['doc', 'docx', 'dotx'], true)
            || str_contains($mimeType, 'wordprocessingml')
            || $mimeType === 'application/msword'
        ) {
            $this->convertDocx($sourcePath, $targetPath, $watermark);

            return;
        }

        throw new RuntimeException('Định dạng tài liệu này chưa hỗ trợ xuất PDF.');
    }

    private function writeHtmlPdf(string $html, string $targetPath, string $watermark): void
    {
        $this->assertRendererAvailable();
        $this->ensureTargetDirectory($targetPath);

        $options = new \Dompdf\Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $pdf = new \Dompdf\Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(34, 806, $watermark, $font, 7, [0.48, 0.48, 0.48]);
        $canvas->page_text(34, 820, 'SportGo | Tài liệu kiểm soát truy cập', $font, 6, [0.58, 0.58, 0.58]);

        $output = $pdf->output();
        if ($output === '' || ! str_starts_with($output, '%PDF-')) {
            throw new RuntimeException('Bộ chuyển đổi không tạo được PDF hợp lệ.');
        }

        File::put($targetPath, $output);
    }

    private function assertRendererAvailable(): void
    {
        // A few local Laravel installs keep vendor's generated autoload file
        // stale after adding a renderer. Register the package namespaces as a
        // harmless fallback; Composer's autoloader remains the normal path.
        if (! class_exists(\Dompdf\Dompdf::class)) {
            $vendor = base_path('vendor');
            spl_autoload_register(static function (string $class) use ($vendor): void {
                $prefixes = [
                    'Dompdf\\' => $vendor . '/dompdf/dompdf/src/',
                    'FontLib\\' => $vendor . '/dompdf/php-font-lib/src/FontLib/',
                    'Svg\\' => $vendor . '/dompdf/php-svg-lib/src/Svg/',
                    'Masterminds\\' => $vendor . '/masterminds/html5/src/',
                ];
                foreach ($prefixes as $prefix => $base) {
                    if (! str_starts_with($class, $prefix)) {
                        continue;
                    }
                    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
                    $file = $base . $relative . '.php';
                    if (is_file($file)) {
                        require_once $file;
                    }
                }
            }, true, true);
        }

        if (! class_exists(\Dompdf\Dompdf::class)) {
            throw new RuntimeException('Chưa cài bộ chuyển đổi PDF. Hãy chạy composer install để cài dompdf/dompdf.');
        }
    }

    private function ensureTargetDirectory(string $targetPath): void
    {
        $directory = dirname($targetPath);
        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0750, true);
        }
    }
}

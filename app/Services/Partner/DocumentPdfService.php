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

        $this->writeHtmlPdf($html, $targetPath, $watermark, true);
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

    private function writeHtmlPdf(
        string $html,
        string $targetPath,
        string $watermark,
        bool $formalDocument = false
    ): void
    {
        $this->assertRendererAvailable();
        $this->ensureTargetDirectory($targetPath);

        $options = new \Dompdf\Options();
        $options->setIsRemoteEnabled(false);
        $formalFont = 'DejaVu Serif';
        $timesFontDirectory = $formalDocument ? $this->timesNewRomanDirectory() : null;
        if ($timesFontDirectory !== null) {
            $fontCacheDirectory = storage_path('framework/dompdf-fonts');
            if (! is_dir($fontCacheDirectory)) {
                File::makeDirectory($fontCacheDirectory, 0750, true);
            }
            $options->setFontDir($fontCacheDirectory);
            $options->setFontCache($fontCacheDirectory);
            $options->setChroot([base_path(), storage_path(), $timesFontDirectory]);
        }

        $pdf = new \Dompdf\Dompdf($options);
        if ($timesFontDirectory !== null && $this->registerTimesNewRoman($pdf, $timesFontDirectory)) {
            $formalFont = 'SportGo Times New Roman';
        }
        $options->setDefaultFont($formalFont);

        if ($formalDocument) {
            $html = $this->applyFormalDocumentStyles($html, $formalFont);
        }

        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $fontMetrics = $pdf->getFontMetrics();
        $font = $fontMetrics->getFont($formalFont, 'normal');
        $canvas->page_text(85, 806, $watermark, $font, 7, [0, 0, 0]);

        if ($formalDocument && method_exists($canvas, 'page_script')) {
            $canvas->page_script(static function (
                int $pageNumber,
                int $pageCount,
                $pageCanvas,
                $pageFontMetrics
            ) use ($formalFont): void {
                if ($pageNumber <= 1) {
                    return;
                }

                $pageFont = $pageFontMetrics->getFont($formalFont, 'normal');
                $number = (string) $pageNumber;
                $width = $pageFontMetrics->getTextWidth($number, $pageFont, 13);
                $pageCanvas->text((595.28 - $width) / 2, 22, $number, $pageFont, 13, [0, 0, 0]);
            });
        }

        $output = $pdf->output();
        if ($output === '' || ! str_starts_with($output, '%PDF-')) {
            throw new RuntimeException('Bộ chuyển đổi không tạo được PDF hợp lệ.');
        }

        File::put($targetPath, $output);
    }

    private function applyFormalDocumentStyles(string $html, string $fontName = 'DejaVu Serif'): string
    {
        // Signature markers are intentionally hidden in unsigned DOCX files.
        // Converting all text to black must not make those internal markers visible.
        $html = preg_replace(
            '/\{\{\s*(?:signature_(?:owner|sportgo)|(?:owner|sportgo)_signer_(?:name|full_name))\s*\}\}/iu',
            '',
            $html
        ) ?? $html;
        $html = $this->markBorderlessLayoutTables($html);
        $html = $this->groupSignatureParagraphs($html);
        $fontFamily = $fontName === 'SportGo Times New Roman'
            ? '"SportGo Times New Roman","DejaVu Serif",serif'
            : '"DejaVu Serif",serif';

        $style = '<style data-sportgo-formal-document>'
            . '@page{size:A4 portrait;margin:20mm 20mm 20mm 30mm}'
            . 'html,body,p,span,strong,em,b,i,u,div,table,td,th{font-family:'.$fontFamily.'!important}'
            . 'html,body{'
            . 'font-size:13pt;line-height:1.3;color:#000!important;background:#fff!important}'
            . 'body{margin:0;padding:0}'
            . 'p{margin:0 0 6pt;color:#000!important;background:transparent!important}'
            . 'table{border-collapse:collapse;width:100%!important;max-width:100%;table-layout:fixed;'
            . 'background:transparent!important}'
            . 'td,th{color:#000!important;background:transparent!important;border-color:#000!important;'
            . 'padding:3pt 5pt;vertical-align:middle;white-space:normal!important;word-wrap:break-word;overflow-wrap:break-word}'
            . 'table.sportgo-borderless,table.sportgo-borderless td,table.sportgo-borderless th{border:none!important}'
            . '.sportgo-signature-block,table.sportgo-borderless{page-break-inside:avoid!important;break-inside:avoid}'
            . 'span,strong,em,b,i,u{color:#000!important;background:transparent!important}'
            . '</style>';

        if (stripos($html, '</head>') !== false) {
            return preg_replace('/<\/head>/i', $style.'</head>', $html, 1) ?? $html;
        }

        return '<!doctype html><html><head><meta charset="utf-8">'.$style.'</head><body>'.$html.'</body></html>';
    }

    private function markBorderlessLayoutTables(string $html): string
    {
        return preg_replace_callback('/<table\b[^>]*>.*?<\/table>/isu', static function (array $match): string {
            $table = $match[0];
            $hasVisibleCellBorder = preg_match(
                '/<(?:td|th)\b[^>]*style="[^"]*border-(?:top|right|bottom|left)-style:\s*(?:solid|double|dashed|dotted)/iu',
                $table
            ) === 1 || preg_match('/<th\b/iu', $table) === 1;

            if ($hasVisibleCellBorder) {
                return $table;
            }

            return preg_replace_callback('/<table\b([^>]*)>/iu', static function (array $opening): string {
                $attributes = $opening[1];
                if (preg_match('/\bclass="([^"]*)"/iu', $attributes) === 1) {
                    $attributes = preg_replace(
                        '/\bclass="([^"]*)"/iu',
                        'class="$1 sportgo-borderless"',
                        $attributes,
                        1
                    ) ?? $attributes;
                } else {
                    $attributes .= ' class="sportgo-borderless"';
                }

                return '<table'.$attributes.'>';
            }, $table, 1) ?? $table;
        }, $html) ?? $html;
    }

    private function groupSignatureParagraphs(string $html): string
    {
        return preg_replace(
            '~(<p\b[^>]*>(?:(?!</p>).)*(?:NGƯỜI LÀM ĐƠN|NGƯỜI ĐỀ NGHỊ|CHỦ SÂN/ĐỐI TÁC)'
                .'(?:(?!</p>).)*</p>\s*<p\b[^>]*>(?:(?!</p>).)*\(\s*Ký\b(?:(?!</p>).)*</p>)~isu',
            '<div class="sportgo-signature-block">$1</div>',
            $html
        ) ?? $html;
    }

    private function timesNewRomanDirectory(): ?string
    {
        $directory = 'C:\\Windows\\Fonts';
        foreach (['times.ttf', 'timesbd.ttf', 'timesi.ttf', 'timesbi.ttf'] as $fileName) {
            if (! is_file($directory.DIRECTORY_SEPARATOR.$fileName)) {
                return null;
            }
        }

        return $directory;
    }

    private function registerTimesNewRoman(\Dompdf\Dompdf $pdf, string $directory): bool
    {
        $fontMetrics = $pdf->getFontMetrics();
        $fontUriRoot = 'file://'.str_replace('\\', '/', $directory);
        $registered = true;

        foreach ([
            ['normal', 'normal', 'times.ttf'],
            ['bold', 'normal', 'timesbd.ttf'],
            ['normal', 'italic', 'timesi.ttf'],
            ['bold', 'italic', 'timesbi.ttf'],
        ] as [$weight, $style, $fileName]) {
            $registered = $fontMetrics->registerFont([
                'family' => 'SportGo Times New Roman',
                'weight' => $weight,
                'style' => $style,
            ], $fontUriRoot.'/'.$fileName) && $registered;
        }

        return $registered;
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

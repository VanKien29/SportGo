<?php

namespace App\Services\Partner;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use RuntimeException;
use ZipArchive;

final class PartnerDocumentFormatter
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const OFFICE_REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const CONTENT_TYPE_NS = 'http://schemas.openxmlformats.org/package/2006/content-types';

    private const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    private const PAGE_WIDTH = '11906';

    private const PAGE_HEIGHT = '16838';

    private const MARGIN_TOP = '1134';

    private const MARGIN_RIGHT = '1134';

    private const MARGIN_BOTTOM = '1134';

    private const MARGIN_LEFT = '1701';

    private const HEADER_DISTANCE = '709';

    private const FOOTER_DISTANCE = '709';

    private const CONTENT_WIDTH = 8800;

    public function normalize(string $docxPath, string $documentType): void
    {
        if (! is_file($docxPath) || ! class_exists(ZipArchive::class) || ! class_exists(DOMDocument::class)) {
            throw new RuntimeException('Không thể chuẩn hóa định dạng DOCX vì file hoặc thư viện xử lý không sẵn sàng.');
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            throw new RuntimeException('Không thể mở DOCX để chuẩn hóa định dạng.');
        }

        try {
            $headerRelationshipId = $this->ensurePageNumberHeader($zip);
            $this->normalizeStyles($zip);
            $this->normalizeDocumentXml($zip, $documentType, $headerRelationshipId);
            $this->normalizeSettings($zip);
            $this->clearLegacyFooters($zip);
        } finally {
            $zip->close();
        }
    }

    private function normalizeDocumentXml(ZipArchive $zip, string $documentType, string $headerRelationshipId): void
    {
        $entry = 'word/document.xml';
        $xml = $zip->getFromName($entry);
        if (! is_string($xml) || trim($xml) === '') {
            throw new RuntimeException('DOCX không có nội dung document.xml hợp lệ.');
        }

        $dom = $this->loadXml($xml);
        $xpath = $this->wordXPath($dom);

        $this->removeInstructionalText($xpath);
        $this->removeStandaloneTemplateLines($xpath);
        $this->convertRequestFieldGroupsToTables($xpath, $documentType);
        $this->removeDuplicateSignatureBlocks($xpath, $documentType);
        $this->removeEmptyPageBreaks($xpath);
        $this->compactEmptyParagraphs($xpath);
        $this->keepReceptionBlockTogether($xpath, $documentType);

        foreach ($this->nodes($xpath->query('//w:shd')) as $shading) {
            $shading->parentNode?->removeChild($shading);
        }

        foreach ($this->nodes($xpath->query('//w:r')) as $run) {
            $this->normalizeRun($run, $xpath);
        }

        foreach ($this->nodes($xpath->query('//w:p')) as $paragraph) {
            $this->normalizeParagraph($paragraph, $xpath);
        }

        foreach ($this->nodes($xpath->query('//w:tbl')) as $table) {
            $this->normalizeTable($table, $xpath);
        }

        foreach ($this->nodes($xpath->query('//w:sectPr')) as $sectionProperties) {
            $this->normalizeSection($sectionProperties, $headerRelationshipId);
        }

        $zip->addFromString($entry, $dom->saveXML());
    }

    private function removeInstructionalText(DOMXPath $xpath): void
    {
        foreach ($this->nodes($xpath->query('//w:p')) as $paragraph) {
            $text = trim($this->nodeText($paragraph, $xpath));
            if ($text === '') {
                continue;
            }

            $normalizedText = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text), 'UTF-8');
            $ancestorTables = $this->nodes($xpath->query('ancestor::w:tbl', $paragraph));
            $signatureTableText = $ancestorTables !== []
                ? mb_strtolower($this->nodeText($ancestorTables[0], $xpath), 'UTF-8')
                : '';
            if ($normalizedText === 'họ và tên'
                && ($ancestorTables === []
                    || (str_contains($signatureTableText, 'ký')
                        && (str_contains($signatureTableText, 'đại diện') || str_contains($signatureTableText, 'người làm đơn'))))) {
                $this->removeOrClearParagraph($paragraph, $xpath);
                continue;
            }
            if (str_contains($signatureTableText, 'ký')) {
                foreach ($this->nodes($xpath->query('.//w:t', $paragraph)) as $textNode) {
                    if (mb_strtolower(trim($textNode->textContent), 'UTF-8') === 'họ và tên') {
                        $textNode->nodeValue = '';
                    }
                }
                $text = trim($this->nodeText($paragraph, $xpath));
            }

            if (preg_match('/^\s*\[TÊN CƠ QUAN CHỦ QUẢN\]\s*$/iu', $text)
                || preg_match('/^\s*\((?:Dùng|Dành)\s+cho\b.*\)\s*$/iu', $text)
                || preg_match('/^Mẫu văn bản SportGo\s*-\s*dùng\b/iu', $text)
                || preg_match('/^Phần này được hệ thống tự động\b/iu', $text)) {
                $this->removeOrClearParagraph($paragraph, $xpath);
                continue;
            }

            $cleaned = preg_replace('/\s*\[TÊN CƠ QUAN CHỦ QUẢN\]\s*/iu', '', $text) ?? $text;
            $cleaned = preg_replace('/^\s*Chưa cung cấp\s*(?=CÔNG TY\/ĐƠN VỊ VẬN HÀNH SPORTGO)/iu', '', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/\s*\((?:Dùng|Dành)\s+cho\b[^)]*\)/iu', '', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/\[[^\[\]\r\n]{2,300}\]/u', 'Chưa cung cấp', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/Chưa cung cấp\/Chưa cung cấp\/Chưa cung cấp/u', 'Chưa xác định', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/Chưa cung cấp giờ Chưa cung cấp phút,\s*ngày Chưa xác định/u', 'Chưa xác định', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/số Chưa cung cấp\/HDHT-SG ký ngày Chưa xác định/iu', 'số Chưa cung cấp, ký ngày Chưa xác định', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/hoặc từ ngày Chưa xác định theo thỏa thuận/iu', 'hoặc theo thời điểm khác được Các Bên thống nhất bằng văn bản', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/Biên bản được lập thành Chưa cung cấp bản có giá trị như nhau, mỗi Bên giữ Chưa cung cấp bản/iu', 'Biên bản được lập thành 02 bản có giá trị như nhau, mỗi Bên giữ 01 bản', $cleaned) ?? $cleaned;
            $cleaned = trim(preg_replace('/[ \t]{2,}/u', ' ', $cleaned) ?? $cleaned);

            if ($cleaned !== $text) {
                $this->replaceParagraphText($paragraph, $xpath, $cleaned);
            }
        }
    }

    private function convertRequestFieldGroupsToTables(DOMXPath $xpath, string $documentType): void
    {
        if (! in_array($documentType, ['venue_scale_request', 'venue_location_change_request'], true)) {
            return;
        }

        $sectionNumbers = [1, 2, 3];
        foreach ($this->nodes($xpath->query('/w:document/w:body/w:p')) as $heading) {
            $headingText = trim($this->nodeText($heading, $xpath));
            if (! preg_match('/^(\d+)\.\s+/u', $headingText, $match)
                || ! in_array((int) $match[1], $sectionNumbers, true)) {
                continue;
            }

            $fieldParagraphs = [];
            $emptyParagraphs = [];
            $rows = [];
            for ($sibling = $heading->nextSibling; $sibling instanceof DOMNode; $sibling = $sibling->nextSibling) {
                if (! $sibling instanceof DOMElement || $sibling->namespaceURI !== self::WORD_NS) {
                    continue;
                }
                if ($sibling->localName !== 'p') {
                    break;
                }

                $text = trim($this->nodeText($sibling, $xpath));
                if ($text === '') {
                    $emptyParagraphs[] = $sibling;
                    continue;
                }
                if (preg_match('/^\d+\.\s+/u', $text)) {
                    break;
                }
                if (! preg_match('/^-\s*([^:]+):\s*(.*)$/u', $text, $fieldMatch)) {
                    break;
                }

                $label = trim($fieldMatch[1]);
                $value = trim($fieldMatch[2]);
                if ($label === '') {
                    break;
                }
                $rows[] = [$label, $value !== '' ? $value : 'Chưa cung cấp'];
                $fieldParagraphs[] = $sibling;
            }

            if ($rows === [] || $fieldParagraphs === []) {
                continue;
            }

            $table = $this->createFieldTable($heading->ownerDocument, $rows);
            $firstField = $fieldParagraphs[0];
            $body = $firstField->parentNode;
            $body?->insertBefore($table, $firstField);
            foreach (array_merge($fieldParagraphs, $emptyParagraphs) as $paragraph) {
                if ($paragraph->parentNode === $body) {
                    $paragraph->parentNode?->removeChild($paragraph);
                }
            }
        }
    }

    /** @param array<int, array{0: string, 1: string}> $rows */
    private function createFieldTable(DOMDocument $dom, array $rows): DOMElement
    {
        $table = $dom->createElementNS(self::WORD_NS, 'w:tbl');
        $table->appendChild($dom->createElementNS(self::WORD_NS, 'w:tblPr'));
        $grid = $dom->createElementNS(self::WORD_NS, 'w:tblGrid');
        foreach ([2700, 6300] as $width) {
            $column = $dom->createElementNS(self::WORD_NS, 'w:gridCol');
            $column->setAttributeNS(self::WORD_NS, 'w:w', (string) $width);
            $grid->appendChild($column);
        }
        $table->appendChild($grid);

        foreach ($rows as [$label, $value]) {
            $row = $dom->createElementNS(self::WORD_NS, 'w:tr');
            foreach ([$label, $value] as $cellText) {
                $cell = $dom->createElementNS(self::WORD_NS, 'w:tc');
                $cell->appendChild($dom->createElementNS(self::WORD_NS, 'w:tcPr'));
                $paragraph = $dom->createElementNS(self::WORD_NS, 'w:p');
                $run = $dom->createElementNS(self::WORD_NS, 'w:r');
                $text = $dom->createElementNS(self::WORD_NS, 'w:t');
                $text->setAttributeNS(self::XML_NS, 'xml:space', 'preserve');
                $text->appendChild($dom->createTextNode($cellText));
                $run->appendChild($text);
                $paragraph->appendChild($run);
                $cell->appendChild($paragraph);
                $row->appendChild($cell);
            }
            $table->appendChild($row);
        }

        return $table;
    }

    private function removeDuplicateSignatureBlocks(DOMXPath $xpath, string $documentType): void
    {
        if (! in_array($documentType, ['venue_scale_appendix', 'venue_location_appendix'], true)) {
            return;
        }

        $signatureTables = [];
        foreach ($this->nodes($xpath->query('//w:tbl')) as $table) {
            $text = mb_strtoupper($this->nodeText($table, $xpath), 'UTF-8');
            if (! $this->isSignatureTable($table, $xpath)) {
                continue;
            }

            $signatureTables[] = $table;
        }

        if (count($signatureTables) <= 1) {
            return;
        }

        $tableToKeep = null;
        foreach ($signatureTables as $table) {
            $text = $this->nodeText($table, $xpath);
            if (str_contains($text, '{{signature_sportgo}}') && str_contains($text, '{{signature_owner}}')) {
                $tableToKeep = $table;
            }
        }
        $tableToKeep ??= end($signatureTables) ?: null;

        foreach ($signatureTables as $table) {
            if ($table === $tableToKeep) {
                continue;
            }
            $this->removeAdjacentEmptyParagraphs($table, $xpath);
            $table->parentNode?->removeChild($table);
        }
    }

    private function keepReceptionBlockTogether(DOMXPath $xpath, string $documentType): void
    {
        if ($documentType !== 'partner_application_form') {
            return;
        }

        foreach ($this->nodes($xpath->query('/w:document/w:body/w:p')) as $paragraph) {
            $text = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $this->nodeText($paragraph, $xpath)) ?? ''), 'UTF-8');
            if (! str_starts_with($text, 'PHẦN XÁC NHẬN TIẾP NHẬN CỦA SPORTGO')) {
                continue;
            }

            $this->singleProperty($this->propertiesElement($paragraph, 'pPr'), 'pageBreakBefore');
            break;
        }
    }

    private function removeEmptyPageBreaks(DOMXPath $xpath): void
    {
        foreach ($this->nodes($xpath->query('//w:p[not(.//w:t[normalize-space(.) != ""])]//w:br[@w:type="page"]')) as $pageBreak) {
            $pageBreak->parentNode?->removeChild($pageBreak);
        }
    }

    private function removeStandaloneTemplateLines(DOMXPath $xpath): void
    {
        foreach ($this->nodes($xpath->query('//w:p')) as $paragraph) {
            $text = trim($this->nodeText($paragraph, $xpath));
            if (! preg_match('/^[\.\s_…]{3,}$/u', $text)) {
                continue;
            }

            $parent = $paragraph->parentNode;
            if ($parent instanceof DOMElement && $parent->namespaceURI === self::WORD_NS && $parent->localName === 'tc') {
                $this->replaceParagraphText($paragraph, $xpath, 'Chưa cung cấp');
                continue;
            }

            $paragraph->parentNode?->removeChild($paragraph);
        }
    }

    private function compactEmptyParagraphs(DOMXPath $xpath): void
    {
        foreach ($this->nodes($xpath->query('//w:tc')) as $cell) {
            $emptyParagraphs = [];
            foreach ($this->nodes($xpath->query('./w:p', $cell)) as $paragraph) {
                if (trim($this->nodeText($paragraph, $xpath)) === ''
                    && $this->nodes($xpath->query('.//w:drawing | .//w:pict | .//w:object', $paragraph)) === []) {
                    $emptyParagraphs[] = $paragraph;
                }
            }

            foreach (array_slice($emptyParagraphs, 1) as $paragraph) {
                $paragraph->parentNode?->removeChild($paragraph);
            }
        }

        $previousWasEmpty = false;
        foreach ($this->nodes($xpath->query('/w:document/w:body/w:p')) as $paragraph) {
            $isEmpty = trim($this->nodeText($paragraph, $xpath)) === ''
                && $this->nodes($xpath->query('.//w:drawing | .//w:pict | .//w:object | ./w:pPr/w:sectPr', $paragraph)) === [];
            if ($isEmpty && $previousWasEmpty) {
                $paragraph->parentNode?->removeChild($paragraph);
                continue;
            }
            $previousWasEmpty = $isEmpty;
        }
    }

    private function removeAdjacentEmptyParagraphs(DOMElement $node, DOMXPath $xpath): void
    {
        foreach ([$node->previousSibling, $node->nextSibling] as $sibling) {
            if ($sibling instanceof DOMElement
                && $sibling->namespaceURI === self::WORD_NS
                && $sibling->localName === 'p'
                && trim($this->nodeText($sibling, $xpath)) === '') {
                $sibling->parentNode?->removeChild($sibling);
            }
        }
    }

    private function removeOrClearParagraph(DOMElement $paragraph, DOMXPath $xpath): void
    {
        $parent = $paragraph->parentNode;
        if ($parent instanceof DOMElement && $parent->localName === 'tc') {
            $paragraphs = $this->nodes($xpath->query('./w:p', $parent));
            if (count($paragraphs) <= 1) {
                $this->replaceParagraphText($paragraph, $xpath, '');

                return;
            }
        }

        $parent?->removeChild($paragraph);
    }

    private function replaceParagraphText(DOMElement $paragraph, DOMXPath $xpath, string $text): void
    {
        $textNodes = $this->nodes($xpath->query('.//w:t', $paragraph));
        if ($textNodes === []) {
            $run = $paragraph->ownerDocument->createElementNS(self::WORD_NS, 'w:r');
            $textNode = $paragraph->ownerDocument->createElementNS(self::WORD_NS, 'w:t');
            $textNode->setAttributeNS(self::XML_NS, 'xml:space', 'preserve');
            $textNode->appendChild($paragraph->ownerDocument->createTextNode($text));
            $run->appendChild($textNode);
            $paragraph->appendChild($run);

            return;
        }

        foreach ($textNodes as $index => $textNode) {
            $textNode->nodeValue = '';
            if ($index === 0 && $text !== '') {
                $textNode->appendChild($textNode->ownerDocument->createTextNode($text));
            }
            $textNode->setAttributeNS(self::XML_NS, 'xml:space', 'preserve');
        }
    }

    private function normalizeRun(DOMElement $run, DOMXPath $xpath): void
    {
        $runProperties = $this->propertiesElement($run, 'rPr');
        $runText = $this->nodeText($run, $xpath);
        $isHiddenSignerNameMacro = str_contains($runText, '{{owner_signer_')
            || str_contains($runText, '{{sportgo_signer_');
        $isHiddenSignatureMacro = str_contains($runText, '{{signature_')
            || $isHiddenSignerNameMacro;

        $fonts = $this->singleProperty($runProperties, 'rFonts');
        foreach (['ascii', 'hAnsi', 'eastAsia', 'cs'] as $attribute) {
            $fonts->setAttributeNS(self::WORD_NS, 'w:'.$attribute, 'Times New Roman');
        }

        foreach ($this->childrenByName($runProperties, ['highlight', 'shd']) as $child) {
            $runProperties->removeChild($child);
        }

        if (! $isHiddenSignatureMacro) {
            $color = $this->singleProperty($runProperties, 'color');
            $color->setAttributeNS(self::WORD_NS, 'w:val', '000000');
            $color->removeAttributeNS(self::WORD_NS, 'themeColor');
        }

        foreach (['sz', 'szCs'] as $sizeName) {
            $size = $this->singleProperty($runProperties, $sizeName);
            if ($isHiddenSignerNameMacro) {
                $size->setAttributeNS(self::WORD_NS, 'w:val', '4');
            } elseif ((int) $size->getAttributeNS(self::WORD_NS, 'val') < 26) {
                $size->setAttributeNS(self::WORD_NS, 'w:val', '26');
            }
        }
    }

    private function normalizeParagraph(DOMElement $paragraph, DOMXPath $xpath): void
    {
        $text = trim($this->nodeText($paragraph, $xpath));
        $paragraphProperties = $this->propertiesElement($paragraph, 'pPr');
        $inTable = $this->hasAncestor($paragraph, 'tc');
        $isEmpty = $text === ''
            && $this->nodes($xpath->query('.//w:drawing | .//w:pict | .//w:object | ./w:pPr/w:sectPr', $paragraph)) === [];
        $isTitle = $this->isDocumentTitle($text);
        $isHeading = $this->isHeading($text);
        $isHiddenSignerNameMacro = str_contains($text, '{{owner_signer_')
            || str_contains($text, '{{sportgo_signer_');
        $isListItem = (bool) preg_match('/^[•●▪◦]\s*/u', $text)
            || (bool) preg_match('/^-\s+/u', $text)
            || $this->nodes($xpath->query('./w:pPr/w:numPr', $paragraph)) !== [];
        $isAttachmentLine = (bool) preg_match('/^(?:Tệp\s+\d+\s*:|Không có tệp đính kèm)/iu', $text);
        $isCompact = $isTitle || $isHeading || $this->isHeaderOrSignatureText($text);

        $spacing = $this->singleProperty($paragraphProperties, 'spacing');
        $spacing->setAttributeNS(self::WORD_NS, 'w:before', $isHeading && ! $inTable ? '120' : '0');
        $spacing->setAttributeNS(self::WORD_NS, 'w:after', $isHeading && ! $inTable ? '60' : ($inTable || $isCompact ? '0' : '120'));
        $spacing->setAttributeNS(self::WORD_NS, 'w:line', $isHiddenSignerNameMacro || $isEmpty ? '40' : ($inTable || $isCompact ? '276' : '300'));
        $spacing->setAttributeNS(self::WORD_NS, 'w:lineRule', $isHiddenSignerNameMacro || $isEmpty ? 'exact' : ($inTable || $isCompact ? 'auto' : 'exact'));

        if ($isTitle) {
            $this->setParagraphAlignment($paragraphProperties, 'center');
            $this->setParagraphRuns($paragraph, $xpath, 28, true);
        } elseif ($isHeading) {
            $this->setParagraphAlignment($paragraphProperties, 'left');
            $this->setParagraphRuns($paragraph, $xpath, 26, true);
            $this->singleProperty($paragraphProperties, 'keepNext');
        } elseif (! $inTable && $isAttachmentLine) {
            $this->setParagraphAlignment($paragraphProperties, 'left');
            $this->setParagraphRuns($paragraph, $xpath, 26, false);
            $indent = $this->singleProperty($paragraphProperties, 'ind');
            $indent->setAttributeNS(self::WORD_NS, 'w:left', '0');
            $indent->setAttributeNS(self::WORD_NS, 'w:firstLine', '0');
            $indent->removeAttributeNS(self::WORD_NS, 'hanging');
        } elseif (! $inTable && $isListItem) {
            $this->setParagraphAlignment($paragraphProperties, 'both');
            $this->setParagraphRuns($paragraph, $xpath, 26, false);
            $indent = $this->singleProperty($paragraphProperties, 'ind');
            $indent->setAttributeNS(self::WORD_NS, 'w:left', '567');
            $indent->setAttributeNS(self::WORD_NS, 'w:hanging', '283');
            $indent->removeAttributeNS(self::WORD_NS, 'firstLine');
        } elseif (! $inTable && $this->isProseParagraph($text)) {
            $this->setParagraphAlignment($paragraphProperties, 'both');
            $this->setParagraphRuns($paragraph, $xpath, 26, false);
            $indent = $this->singleProperty($paragraphProperties, 'ind');
            if (! $indent->hasAttributeNS(self::WORD_NS, 'left')
                && ! $indent->hasAttributeNS(self::WORD_NS, 'hanging')) {
                $indent->setAttributeNS(self::WORD_NS, 'w:firstLine', '567');
            }
        }

        if (str_starts_with($text, 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM')) {
            $this->setParagraphRuns($paragraph, $xpath, 26, true);
        } elseif (str_starts_with($text, 'Độc lập - Tự do - Hạnh phúc')) {
            $this->setParagraphRuns($paragraph, $xpath, 28, true);
        }
    }

    private function normalizeTable(DOMElement $table, DOMXPath $xpath): void
    {
        $tableProperties = $this->propertiesElement($table, 'tblPr');
        foreach ($this->childrenByName($tableProperties, ['tblStyle', 'shd']) as $child) {
            $tableProperties->removeChild($child);
        }

        $tableWidth = $this->singleProperty($tableProperties, 'tblW');
        $tableWidth->setAttributeNS(self::WORD_NS, 'w:w', (string) self::CONTENT_WIDTH);
        $tableWidth->setAttributeNS(self::WORD_NS, 'w:type', 'dxa');

        $tableIndent = $this->singleProperty($tableProperties, 'tblInd');
        $tableIndent->setAttributeNS(self::WORD_NS, 'w:w', '0');
        $tableIndent->setAttributeNS(self::WORD_NS, 'w:type', 'dxa');

        $layout = $this->singleProperty($tableProperties, 'tblLayout');
        $layout->setAttributeNS(self::WORD_NS, 'w:type', 'fixed');
        $this->setTableCellMargins($tableProperties);

        foreach ($this->nodes($xpath->query('.//w:tblBorders/* | .//w:tcBorders/*', $table)) as $border) {
            $border->setAttributeNS(self::WORD_NS, 'w:color', '000000');
            $border->removeAttributeNS(self::WORD_NS, 'themeColor');
        }

        $rows = $this->nodes($xpath->query('./w:tr', $table));
        $columnCount = 0;
        foreach ($rows as $row) {
            $columnCount = max($columnCount, $this->rowGridColumnCount($row, $xpath));
        }

        if ($columnCount < 1) {
            return;
        }

        $balancedTwoColumn = $columnCount === 2 && $this->isBalancedTwoColumnTable($table, $xpath);
        $signatureTable = $this->isSignatureTable($table, $xpath);
        $noticeDispatchTable = $this->isNoticeDispatchSignatureTable($table, $xpath);
        if ($columnCount >= 3 || ($columnCount === 2 && ! $balancedTwoColumn)) {
            $this->ensureTableBorders($tableProperties);
        } elseif ($balancedTwoColumn) {
            $this->removeTableBorders($tableProperties, $table, $xpath);
        }

        $widths = $this->tableColumnWidths($table, $xpath, $columnCount);
        $this->setTableGrid($table, $xpath, $widths);

        foreach ($rows as $rowIndex => $row) {
            $rowProperties = $this->propertiesElement($row, 'trPr');
            foreach ($this->childrenByName($rowProperties, ['trHeight']) as $height) {
                $rowProperties->removeChild($height);
            }
            $this->singleProperty($rowProperties, 'cantSplit');

            $cells = $this->nodes($xpath->query('./w:tc', $row));
            $this->applyRowCellWidths($cells, $widths, $xpath);

            if ($rowIndex === 0 && $columnCount >= 3 && count($rows) > 1) {
                $this->singleProperty($rowProperties, 'tblHeader');
                foreach ($this->nodes($xpath->query('.//w:p', $row)) as $paragraph) {
                    $this->setParagraphAlignment($this->propertiesElement($paragraph, 'pPr'), 'center');
                    $this->setParagraphRuns($paragraph, $xpath, 26, true);
                }
            } elseif ($noticeDispatchTable) {
                foreach ($cells as $cellIndex => $cell) {
                    foreach ($this->nodes($xpath->query('.//w:p', $cell)) as $paragraph) {
                        $paragraphProperties = $this->propertiesElement($paragraph, 'pPr');
                        $this->setParagraphAlignment($paragraphProperties, $cellIndex === 0 ? 'left' : 'center');
                        $this->setParagraphRuns($paragraph, $xpath, $cellIndex === 0 ? 22 : 26, false);
                    }
                }
            } elseif ($signatureTable) {
                foreach ($cells as $cell) {
                    foreach ($this->nodes($xpath->query('.//w:p', $cell)) as $paragraph) {
                        $paragraphProperties = $this->propertiesElement($paragraph, 'pPr');
                        $this->setParagraphAlignment($paragraphProperties, 'center');
                        if ($rowIndex < count($rows) - 1) {
                            $this->singleProperty($paragraphProperties, 'keepNext');
                        }
                    }
                }
            } elseif ($columnCount >= 3) {
                foreach ($cells as $cellIndex => $cell) {
                    foreach ($this->nodes($xpath->query('.//w:p', $cell)) as $paragraph) {
                        $this->setParagraphAlignment(
                            $this->propertiesElement($paragraph, 'pPr'),
                            $cellIndex === 0 ? 'center' : 'left'
                        );
                        $this->setParagraphRuns($paragraph, $xpath, 26, false);
                    }
                }
            } elseif ($columnCount === 2 && ! $balancedTwoColumn) {
                foreach ($cells as $cellIndex => $cell) {
                    foreach ($this->nodes($xpath->query('.//w:p', $cell)) as $paragraph) {
                        $this->setParagraphAlignment($this->propertiesElement($paragraph, 'pPr'), 'left');
                        $this->setParagraphRuns($paragraph, $xpath, 26, $cellIndex === 0);
                    }
                }
            }
        }
    }

    /** @return array<int, int> */
    private function tableColumnWidths(DOMElement $table, DOMXPath $xpath, int $columnCount): array
    {
        if ($columnCount === 2) {
            if ($this->isNoticeDispatchSignatureTable($table, $xpath)) {
                return [3000, 5800];
            }

            return $this->isBalancedTwoColumnTable($table, $xpath) ? [4400, 4400] : [2600, 6200];
        }

        $formalWidths = match ($columnCount) {
            3 => [700, 3250, 4850],
            4 => [600, 2500, 2750, 2950],
            5 => [800, 1750, 2050, 2200, 2000],
            6 => [800, 1650, 1300, 1500, 1550, 2000],
            default => null,
        };
        if ($formalWidths !== null) {
            return $formalWidths;
        }

        $existing = [];
        foreach ($this->nodes($xpath->query('./w:tblGrid/w:gridCol', $table)) as $gridColumn) {
            $width = (int) $gridColumn->getAttributeNS(self::WORD_NS, 'w');
            if ($width > 0) {
                $existing[] = $width;
            }
        }

        if (count($existing) !== $columnCount || array_sum($existing) <= 0) {
            $base = intdiv(self::CONTENT_WIDTH, $columnCount);
            $existing = array_fill(0, $columnCount, $base);
            $existing[$columnCount - 1] += self::CONTENT_WIDTH - array_sum($existing);

            return $existing;
        }

        $sum = array_sum($existing);
        $scaled = array_map(
            fn (int $width): int => max(360, (int) round(($width / $sum) * self::CONTENT_WIDTH)),
            $existing
        );
        $scaled[$columnCount - 1] += self::CONTENT_WIDTH - array_sum($scaled);

        return $scaled;
    }

    private function isBalancedTwoColumnTable(DOMElement $table, DOMXPath $xpath): bool
    {
        $tableText = mb_strtoupper($this->nodeText($table, $xpath), 'UTF-8');

        return str_contains($tableText, 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM')
            || $this->isSignatureTable($table, $xpath)
            || $this->isNoticeDispatchSignatureTable($table, $xpath);
    }

    private function isSignatureTable(DOMElement $table, DOMXPath $xpath): bool
    {
        $tableText = mb_strtoupper($this->nodeText($table, $xpath), 'UTF-8');

        $hasSigningInstruction = str_contains($tableText, 'KÝ, GHI RÕ HỌ TÊN')
            || str_contains($tableText, 'KÝ VÀ GHI RÕ HỌ TÊN')
            || str_contains($tableText, '{{SIGNATURE_');

        return $hasSigningInstruction
            && (str_contains($tableText, 'ĐẠI DIỆN BÊN A')
                || str_contains($tableText, 'ĐẠI DIỆN BÊN B')
                || str_contains($tableText, 'NGƯỜI LÀM ĐƠN')
                || str_contains($tableText, 'NGƯỜI ĐỀ NGHỊ')
                || str_contains($tableText, 'CHỦ SÂN/ĐỐI TÁC'));
    }

    private function isNoticeDispatchSignatureTable(DOMElement $table, DOMXPath $xpath): bool
    {
        $tableText = mb_strtoupper($this->nodeText($table, $xpath), 'UTF-8');

        return str_contains($tableText, 'NƠI NHẬN')
            && (str_contains($tableText, 'QUYỀN HẠN')
                || str_contains($tableText, 'CHỨC VỤ CỦA NGƯỜI KÝ')
                || str_contains($tableText, '{{SIGNATURE_SPORTGO}}'));
    }

    /** @param array<int, DOMElement> $cells
     *  @param array<int, int> $widths
     */
    private function applyRowCellWidths(array $cells, array $widths, DOMXPath $xpath): void
    {
        $gridIndex = 0;
        foreach ($cells as $cell) {
            if ($gridIndex >= count($widths)) {
                break;
            }

            $cellProperties = $this->propertiesElement($cell, 'tcPr');
            $gridSpan = $this->firstNode($xpath->query('./w:gridSpan', $cellProperties));
            $span = $gridSpan instanceof DOMElement
                ? max(1, (int) $gridSpan->getAttributeNS(self::WORD_NS, 'val'))
                : 1;
            $span = min($span, count($widths) - $gridIndex);
            $cellWidth = $this->singleProperty($cellProperties, 'tcW');
            $cellWidth->setAttributeNS(self::WORD_NS, 'w:w', (string) array_sum(array_slice($widths, $gridIndex, $span)));
            $cellWidth->setAttributeNS(self::WORD_NS, 'w:type', 'dxa');
            $verticalAlignment = $this->singleProperty($cellProperties, 'vAlign');
            $verticalAlignment->setAttributeNS(self::WORD_NS, 'w:val', 'center');
            $gridIndex += $span;
        }
    }

    private function rowGridColumnCount(DOMElement $row, DOMXPath $xpath): int
    {
        $count = 0;
        foreach ($this->nodes($xpath->query('./w:tc', $row)) as $cell) {
            $gridSpan = $this->firstNode($xpath->query('./w:tcPr/w:gridSpan', $cell));
            $count += $gridSpan instanceof DOMElement
                ? max(1, (int) $gridSpan->getAttributeNS(self::WORD_NS, 'val'))
                : 1;
        }

        return $count;
    }

    private function ensureTableBorders(DOMElement $tableProperties): void
    {
        $borders = $this->singleProperty($tableProperties, 'tblBorders');
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $edge) {
            $border = $this->singleProperty($borders, $edge);
            $border->setAttributeNS(self::WORD_NS, 'w:val', 'single');
            $border->setAttributeNS(self::WORD_NS, 'w:sz', '4');
            $border->setAttributeNS(self::WORD_NS, 'w:space', '0');
            $border->setAttributeNS(self::WORD_NS, 'w:color', '000000');
            $border->removeAttributeNS(self::WORD_NS, 'themeColor');
        }
    }

    private function removeTableBorders(DOMElement $tableProperties, DOMElement $table, DOMXPath $xpath): void
    {
        foreach ($this->childrenByName($tableProperties, ['tblBorders']) as $borders) {
            $tableProperties->removeChild($borders);
        }
        foreach ($this->nodes($xpath->query('.//w:tcPr/w:tcBorders', $table)) as $borders) {
            $borders->parentNode?->removeChild($borders);
        }
    }

    /** @param array<int, int> $widths */
    private function setTableGrid(DOMElement $table, DOMXPath $xpath, array $widths): void
    {
        $grid = $this->firstNode($xpath->query('./w:tblGrid', $table));
        if (! $grid instanceof DOMElement) {
            $grid = $table->ownerDocument->createElementNS(self::WORD_NS, 'w:tblGrid');
            $tableProperties = $this->firstNode($xpath->query('./w:tblPr', $table));
            if ($tableProperties?->nextSibling) {
                $table->insertBefore($grid, $tableProperties->nextSibling);
            } else {
                $table->appendChild($grid);
            }
        }

        while ($grid->firstChild) {
            $grid->removeChild($grid->firstChild);
        }

        foreach ($widths as $width) {
            $column = $grid->ownerDocument->createElementNS(self::WORD_NS, 'w:gridCol');
            $column->setAttributeNS(self::WORD_NS, 'w:w', (string) $width);
            $grid->appendChild($column);
        }
    }

    private function setTableCellMargins(DOMElement $tableProperties): void
    {
        $margins = $this->singleProperty($tableProperties, 'tblCellMar');
        foreach (['top' => '100', 'left' => '120', 'bottom' => '100', 'right' => '120'] as $edge => $width) {
            $property = $this->singleProperty($margins, $edge);
            $property->setAttributeNS(self::WORD_NS, 'w:w', $width);
            $property->setAttributeNS(self::WORD_NS, 'w:type', 'dxa');
        }
    }

    private function normalizeSection(DOMElement $sectionProperties, string $headerRelationshipId): void
    {
        foreach ($this->childrenByName($sectionProperties, ['headerReference', 'footerReference']) as $reference) {
            $sectionProperties->removeChild($reference);
        }

        $headerReference = $sectionProperties->ownerDocument->createElementNS(self::WORD_NS, 'w:headerReference');
        $headerReference->setAttributeNS(self::WORD_NS, 'w:type', 'default');
        $headerReference->setAttributeNS(self::OFFICE_REL_NS, 'r:id', $headerRelationshipId);
        $sectionProperties->insertBefore($headerReference, $sectionProperties->firstChild);

        $pageSize = $this->singleProperty($sectionProperties, 'pgSz');
        $pageSize->setAttributeNS(self::WORD_NS, 'w:w', self::PAGE_WIDTH);
        $pageSize->setAttributeNS(self::WORD_NS, 'w:h', self::PAGE_HEIGHT);
        $pageSize->removeAttributeNS(self::WORD_NS, 'orient');

        $margins = $this->singleProperty($sectionProperties, 'pgMar');
        foreach ([
            'top' => self::MARGIN_TOP,
            'right' => self::MARGIN_RIGHT,
            'bottom' => self::MARGIN_BOTTOM,
            'left' => self::MARGIN_LEFT,
            'header' => self::HEADER_DISTANCE,
            'footer' => self::FOOTER_DISTANCE,
            'gutter' => '0',
        ] as $attribute => $value) {
            $margins->setAttributeNS(self::WORD_NS, 'w:'.$attribute, $value);
        }

        $pageNumbering = $this->singleProperty($sectionProperties, 'pgNumType');
        $pageNumbering->setAttributeNS(self::WORD_NS, 'w:start', '1');
        $this->singleProperty($sectionProperties, 'titlePg');
    }

    private function normalizeStyles(ZipArchive $zip): void
    {
        $entry = 'word/styles.xml';
        $xml = $zip->getFromName($entry);
        if (! is_string($xml) || trim($xml) === '') {
            return;
        }

        $dom = $this->loadXml($xml);
        $xpath = $this->wordXPath($dom);
        $root = $dom->documentElement;

        $docDefaults = $this->firstNode($xpath->query('/w:styles/w:docDefaults'));
        if (! $docDefaults instanceof DOMElement) {
            $docDefaults = $dom->createElementNS(self::WORD_NS, 'w:docDefaults');
            $root->insertBefore($docDefaults, $root->firstChild);
        }
        $runDefaults = $this->singleProperty($docDefaults, 'rPrDefault');
        $runProperties = $this->singleProperty($runDefaults, 'rPr');
        $fonts = $this->singleProperty($runProperties, 'rFonts');
        foreach (['ascii', 'hAnsi', 'eastAsia', 'cs'] as $attribute) {
            $fonts->setAttributeNS(self::WORD_NS, 'w:'.$attribute, 'Times New Roman');
        }
        foreach (['sz', 'szCs'] as $sizeName) {
            $size = $this->singleProperty($runProperties, $sizeName);
            $size->setAttributeNS(self::WORD_NS, 'w:val', '26');
        }
        $color = $this->singleProperty($runProperties, 'color');
        $color->setAttributeNS(self::WORD_NS, 'w:val', '000000');

        foreach ($this->nodes($xpath->query('//w:shd | //w:highlight')) as $decoration) {
            $decoration->parentNode?->removeChild($decoration);
        }
        foreach ($this->nodes($xpath->query('//w:color')) as $styleColor) {
            $styleColor->setAttributeNS(self::WORD_NS, 'w:val', '000000');
            $styleColor->removeAttributeNS(self::WORD_NS, 'themeColor');
        }

        foreach ($this->nodes($xpath->query('//w:style[@w:styleId="Normal"]')) as $normalStyle) {
            $paragraphProperties = $this->propertiesElement($normalStyle, 'pPr');
            $spacing = $this->singleProperty($paragraphProperties, 'spacing');
            $spacing->setAttributeNS(self::WORD_NS, 'w:after', '120');
            $spacing->setAttributeNS(self::WORD_NS, 'w:line', '300');
            $spacing->setAttributeNS(self::WORD_NS, 'w:lineRule', 'exact');
        }

        $zip->addFromString($entry, $dom->saveXML());
    }

    private function normalizeSettings(ZipArchive $zip): void
    {
        $entry = 'word/settings.xml';
        $xml = $zip->getFromName($entry);
        if (! is_string($xml) || trim($xml) === '') {
            return;
        }

        $dom = $this->loadXml($xml);
        $xpath = $this->wordXPath($dom);
        foreach ($this->nodes($xpath->query('/w:settings/w:evenAndOddHeaders')) as $setting) {
            $setting->parentNode?->removeChild($setting);
        }
        $updateFields = $this->firstNode($xpath->query('/w:settings/w:updateFields'));
        if (! $updateFields instanceof DOMElement) {
            $updateFields = $dom->createElementNS(self::WORD_NS, 'w:updateFields');
            $dom->documentElement->appendChild($updateFields);
        }
        $updateFields->setAttributeNS(self::WORD_NS, 'w:val', 'true');

        $zip->addFromString($entry, $dom->saveXML());
    }

    private function ensurePageNumberHeader(ZipArchive $zip): string
    {
        $relationshipsEntry = 'word/_rels/document.xml.rels';
        $relationshipsXml = $zip->getFromName($relationshipsEntry);
        if (! is_string($relationshipsXml) || trim($relationshipsXml) === '') {
            throw new RuntimeException('DOCX thiếu document.xml.rels.');
        }

        $relationships = $this->loadXml($relationshipsXml);
        $relationshipXPath = new DOMXPath($relationships);
        $relationshipXPath->registerNamespace('pr', self::PACKAGE_REL_NS);
        $target = 'headerPartnerPageNumber.xml';
        $relationshipId = null;
        $maxId = 0;

        foreach ($this->nodes($relationshipXPath->query('/pr:Relationships/pr:Relationship')) as $relationship) {
            if (preg_match('/^rId(\d+)$/', $relationship->getAttribute('Id'), $match)) {
                $maxId = max($maxId, (int) $match[1]);
            }
            if ($relationship->getAttribute('Type') === self::OFFICE_REL_NS.'/header'
                && $relationship->getAttribute('Target') === $target) {
                $relationshipId = $relationship->getAttribute('Id');
            }
        }

        if (! $relationshipId) {
            $relationshipId = 'rId'.($maxId + 1);
            $relationship = $relationships->createElementNS(self::PACKAGE_REL_NS, 'Relationship');
            $relationship->setAttribute('Id', $relationshipId);
            $relationship->setAttribute('Type', self::OFFICE_REL_NS.'/header');
            $relationship->setAttribute('Target', $target);
            $relationships->documentElement->appendChild($relationship);
            $zip->addFromString($relationshipsEntry, $relationships->saveXML());
        }

        $this->ensureHeaderContentType($zip, '/word/'.$target);
        $zip->addFromString('word/'.$target, $this->pageNumberHeaderXml());

        return $relationshipId;
    }

    private function ensureHeaderContentType(ZipArchive $zip, string $partName): void
    {
        $entry = '[Content_Types].xml';
        $xml = $zip->getFromName($entry);
        if (! is_string($xml) || trim($xml) === '') {
            throw new RuntimeException('DOCX thiếu [Content_Types].xml.');
        }

        $dom = $this->loadXml($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ct', self::CONTENT_TYPE_NS);
        foreach ($this->nodes($xpath->query('/ct:Types/ct:Override')) as $override) {
            if ($override->getAttribute('PartName') === $partName) {
                return;
            }
        }

        $override = $dom->createElementNS(self::CONTENT_TYPE_NS, 'Override');
        $override->setAttribute('PartName', $partName);
        $override->setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml');
        $dom->documentElement->appendChild($override);
        $zip->addFromString($entry, $dom->saveXML());
    }

    private function pageNumberHeaderXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:hdr xmlns:w="'.self::WORD_NS.'">'
            .'<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="0" w:after="0"/></w:pPr>'
            .'<w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="26"/><w:szCs w:val="26"/><w:color w:val="000000"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r>'
            .'<w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="26"/><w:szCs w:val="26"/><w:color w:val="000000"/></w:rPr><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>'
            .'<w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="26"/><w:szCs w:val="26"/><w:color w:val="000000"/></w:rPr><w:fldChar w:fldCharType="end"/></w:r>'
            .'</w:p></w:hdr>';
    }

    private function clearLegacyFooters(ZipArchive $zip): void
    {
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            if (is_string($entry) && preg_match('~^word/footer\d+\.xml$~', $entry)) {
                $entries[] = $entry;
            }
        }

        $emptyFooter = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:ftr xmlns:w="'.self::WORD_NS.'"><w:p/></w:ftr>';
        foreach ($entries as $entry) {
            $zip->addFromString($entry, $emptyFooter);
        }
    }

    private function isDocumentTitle(string $text): bool
    {
        if ($text === '' || mb_strlen($text, 'UTF-8') > 180) {
            return false;
        }

        $uppercase = mb_strtoupper($text, 'UTF-8');
        if ($text !== $uppercase) {
            return false;
        }

        return (bool) preg_match(
            '/^(?:ĐƠN ĐỀ NGHỊ|ĐƠN YÊU CẦU|ĐƠN XÁC NHẬN|HỢP ĐỒNG|BIÊN BẢN|CÔNG VĂN|THÔNG TIN THAY ĐỔI)\b/u',
            $uppercase
        );
    }

    private function isHeading(string $text): bool
    {
        return (bool) preg_match('/^(?:Điều\s+\d+\.?|[IVX]+\.|\d+\.)\s+/u', $text);
    }

    private function isHeaderOrSignatureText(string $text): bool
    {
        return $text === ''
            || str_starts_with($text, 'CỘNG HÒA')
            || str_starts_with($text, 'Độc lập - Tự do - Hạnh phúc')
            || str_starts_with($text, 'Kính gửi:')
            || str_starts_with($text, 'Căn cứ ')
            || str_starts_with($text, 'ĐẠI DIỆN ')
            || str_starts_with($text, 'CHỦ SÂN/')
            || str_starts_with($text, 'BỘ PHẬN ')
            || str_starts_with($text, '(Ký')
            || (bool) preg_match('/^-{6,}$/', $text);
    }

    private function isProseParagraph(string $text): bool
    {
        if (mb_strlen($text, 'UTF-8') < 45 || $this->isHeaderOrSignatureText($text)) {
            return false;
        }

        return ! preg_match('/^(?:•|☐|-\s|Điều\s+\d+|[IVX]+\.|\d+\.)/u', $text);
    }

    private function setParagraphRuns(DOMElement $paragraph, DOMXPath $xpath, int $size, bool $bold): void
    {
        foreach ($this->nodes($xpath->query('.//w:r', $paragraph)) as $run) {
            $runProperties = $this->propertiesElement($run, 'rPr');
            foreach (['sz', 'szCs'] as $sizeName) {
                $sizeElement = $this->singleProperty($runProperties, $sizeName);
                $sizeElement->setAttributeNS(self::WORD_NS, 'w:val', (string) $size);
            }
            $boldElement = $this->singleProperty($runProperties, 'b');
            $boldElement->setAttributeNS(self::WORD_NS, 'w:val', $bold ? '1' : '0');
        }
    }

    private function setParagraphAlignment(DOMElement $paragraphProperties, string $alignment): void
    {
        $justification = $this->singleProperty($paragraphProperties, 'jc');
        $justification->setAttributeNS(self::WORD_NS, 'w:val', $alignment);
    }

    private function propertiesElement(DOMElement $parent, string $name): DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::WORD_NS && $child->localName === $name) {
                return $child;
            }
        }

        $properties = $parent->ownerDocument->createElementNS(self::WORD_NS, 'w:'.$name);
        $parent->insertBefore($properties, $parent->firstChild);

        return $properties;
    }

    private function singleProperty(DOMElement $parent, string $name): DOMElement
    {
        $matches = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::WORD_NS && $child->localName === $name) {
                $matches[] = $child;
            }
        }

        if ($matches !== []) {
            foreach (array_slice($matches, 1) as $duplicate) {
                $parent->removeChild($duplicate);
            }

            return $matches[0];
        }

        $property = $parent->ownerDocument->createElementNS(self::WORD_NS, 'w:'.$name);
        $parent->appendChild($property);

        return $property;
    }

    /** @param array<int, string> $names
     *  @return array<int, DOMElement>
     */
    private function childrenByName(DOMElement $parent, array $names): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::WORD_NS && in_array($child->localName, $names, true)) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function hasAncestor(DOMNode $node, string $localName): bool
    {
        $parent = $node->parentNode;
        while ($parent) {
            if ($parent instanceof DOMElement && $parent->namespaceURI === self::WORD_NS && $parent->localName === $localName) {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    private function nodeText(DOMNode $node, DOMXPath $xpath): string
    {
        $parts = [];
        foreach ($this->nodes($xpath->query('.//w:t', $node)) as $textNode) {
            $parts[] = $textNode->nodeValue ?? '';
        }

        return implode('', $parts);
    }

    /** @return array<int, DOMElement> */
    private function nodes(DOMNodeList|false|null $nodeList): array
    {
        if (! $nodeList) {
            return [];
        }

        $nodes = [];
        foreach ($nodeList as $node) {
            if ($node instanceof DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function firstNode(DOMNodeList|false|null $nodeList): ?DOMElement
    {
        $node = $nodeList instanceof DOMNodeList ? $nodeList->item(0) : null;

        return $node instanceof DOMElement ? $node : null;
    }

    private function wordXPath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);
        $xpath->registerNamespace('r', self::OFFICE_REL_NS);

        return $xpath;
    }

    private function loadXml(string $xml): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            throw new RuntimeException('Không thể đọc cấu trúc XML của DOCX.');
        }

        return $dom;
    }
}

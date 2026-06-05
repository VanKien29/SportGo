<?php

namespace App\Services\Finance;

use App\Models\OwnerWithdrawalRequest;
use Illuminate\Support\Collection;

class MBBankBulkTransferExportService
{
    /**
     * @param  Collection<int, OwnerWithdrawalRequest>  $withdrawals
     */
    public function build(Collection $withdrawals): string
    {
        $entries = [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelsXml(),
            'docProps/app.xml' => $this->appPropsXml(),
            'docProps/core.xml' => $this->corePropsXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelsXml(),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $this->transactionsSheetXml($withdrawals),
            'xl/worksheets/sheet2.xml' => $this->bankSuggestionSheetXml(),
            'xl/worksheets/sheet3.xml' => $this->emptySheetXml(),
        ];

        return $this->zipStored($entries);
    }

    private function transactionsSheetXml(Collection $withdrawals): string
    {
        $rows = [];
        $rowIndex = 3;

        foreach ($withdrawals->values() as $index => $withdrawal) {
            $bankAccount = $withdrawal->bankAccount;
            $rows[] = '<row r="'.$rowIndex.'" spans="1:6">'
                .$this->numberCell('A'.$rowIndex, $index + 1)
                .$this->inlineCell('B'.$rowIndex, (string) $bankAccount?->account_number, 2)
                .$this->inlineCell('C'.$rowIndex, (string) $bankAccount?->account_holder_name, 2)
                .$this->inlineCell('D'.$rowIndex, $this->bankNameForTemplate((string) $bankAccount?->bank_code, (string) $bankAccount?->bank_name), 2)
                .$this->numberCell('E'.$rowIndex, (int) $withdrawal->amount, 4)
                .$this->inlineCell('F'.$rowIndex, $withdrawal->request_code, 2)
                .'</row>';
            $rowIndex++;
        }

        $lastRow = max(2, $rowIndex - 1);

        return $this->xmlHeader()
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<dimension ref="A1:F'.$lastRow.'"/>'
            .'<sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols>'
            .'<col min="1" max="1" width="11.7109375" customWidth="1"/>'
            .'<col min="2" max="2" width="27.42578125" style="2" customWidth="1"/>'
            .'<col min="3" max="3" width="32.7109375" style="2" customWidth="1"/>'
            .'<col min="4" max="4" width="36.85546875" style="2" customWidth="1"/>'
            .'<col min="5" max="5" width="24.7109375" style="4" customWidth="1"/>'
            .'<col min="6" max="6" width="44.28515625" style="2" customWidth="1"/>'
            .'</cols>'
            .'<sheetData>'
            .'<row r="1" spans="1:6" ht="43.5" customHeight="1">'
            .$this->inlineCell('B1', "DANH SÁCH GIAO DỊCH\n(LIST OF TRANSACTIONS)", 5)
            .'<c r="C1" s="5"/><c r="D1" s="5"/><c r="E1" s="5"/><c r="F1" s="5"/>'
            .'</row>'
            .'<row r="2" spans="1:6" ht="47.25">'
            .$this->inlineCell('A2', "STT\n(Ord. No.)\n(1)", 3)
            .$this->inlineCell('B2', "Số tài khoản\n(Account No.)\n(2)", 3)
            .$this->inlineCell('C2', "Tên người thụ hưởng\n(Beneficiary)\n(3)", 3)
            .$this->inlineCell('D2', "Ngân hàng thụ hưởng/Chi nhánh\n(Beneficiary Bank)\n(4)", 3)
            .$this->inlineCell('E2', "Số tiền\n(Amount)\n(5)", 3)
            .$this->inlineCell('F2', "Nội dung chuyển khoản\n(Payment Detail)\n(6)", 3)
            .'</row>'
            .implode('', $rows)
            .'</sheetData>'
            .'<mergeCells count="1"><mergeCell ref="B1:F1"/></mergeCells>'
            .'<dataValidations count="1"><dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="D3:D1048576"><formula1>\'Tên NH Gợi ý\'!$A$2:$A$45</formula1></dataValidation></dataValidations>'
            .'<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            .'</worksheet>';
    }

    private function bankSuggestionSheetXml(): string
    {
        $rows = [];

        foreach ($this->bankSuggestions() as $index => $name) {
            $row = $index + 1;
            $style = $row === 1 ? 3 : 2;
            $rows[] = '<row r="'.$row.'">'.$this->inlineCell('A'.$row, $name, $style).'</row>';
        }

        return $this->xmlHeader()
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:A'.count($this->bankSuggestions()).'"/>'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols><col min="1" max="1" width="55" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $rows).'</sheetData>'
            .'</worksheet>';
    }

    private function emptySheetXml(): string
    {
        return $this->xmlHeader()
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1"/>'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<sheetData/>'
            .'</worksheet>';
    }

    private function bankNameForTemplate(string $bankCode, string $fallback): string
    {
        $map = [
            'ABBANK' => 'An Bình (ABBANK)',
            'ACB' => 'Á Châu (ACB)',
            'BIDV' => 'Đầu tư và phát triển (BIDV)',
            'EIB' => 'Xuất nhập khẩu (EIB)',
            'HDB' => 'Phát triển nhà TP HCM (HDB)',
            'MB' => 'Quân đội (MB)',
            'MSB' => 'Hàng hải (MSB)',
            'NCB' => 'Quốc Dân (NCB)',
            'OCB' => 'Phương Đông (OCB)',
            'SHB' => 'Sài Gòn Hà Nội (SHB)',
            'STB' => 'Sacombank (STB)',
            'TCB' => 'Kỹ Thương (TCB)',
            'TPB' => 'Tiên Phong (TPB)',
            'VAB' => 'Việt Á (VAB)',
            'VBA' => 'Nông nghiệp và Phát triển nông thôn (VBA)',
            'VCB' => 'Ngoại thương Việt Nam (VCB)',
            'VIB' => 'Quốc tế (VIB)',
            'VIETINBANK' => 'Công Thương Việt Nam (VIETINBANK)',
            'VPB' => 'Việt Nam Thịnh Vượng (VPB)',
        ];

        return $map[strtoupper($bankCode)] ?? $fallback;
    }

    /**
     * @return array<int, string>
     */
    private function bankSuggestions(): array
    {
        return [
            'BANK_NAME',
            'Nông nghiệp và Phát triển nông thôn (VBA)',
            'Ngoại thương Việt Nam (VCB)',
            'Đầu tư và phát triển (BIDV)',
            'Công Thương Việt Nam (VIETINBANK)',
            'Việt Nam Thịnh Vượng (VPB)',
            'Quốc tế (VIB)',
            'Xuất nhập khẩu (EIB)',
            'Sài Gòn Hà Nội (SHB)',
            'Tiên Phong (TPB)',
            'Kỹ Thương (TCB)',
            'Hàng hải (MSB)',
            'Ngân hàng Thương mại Cổ phần Lộc Phát Việt Nam (LPB)',
            'Đông Á (DAB)',
            'Bắc Á (NASB)',
            'Sài Gòn Công thương (SGB)',
            'Việt Nam Thương tín (VIETBANK)',
            'BVBank – Ngân hàng TMCP Bản Việt (VCCB)',
            'Kiên Long (KLB)',
            'Ngân hàng TMCP Thịnh vượng và Phát triển (PGB)',
            'Đại chúng Việt Nam (PVC)',
            'Á Châu (ACB)',
            'Nam Á (NAMABANK)',
            'Sài Gòn (SCB)',
            'Đông Nam Á (SEAB)',
            'Phương Đông (OCB)',
            'Việt Á (VAB)',
            'Quốc Dân (NCB)',
            'Liên doanh VID Public Bank (VID)',
            'Bảo Việt (BVB)',
            'Ngân hàng TNHH MTV Việt Nam Hiện Đại (MBV)',
            'Phát triển nhà TP HCM (HDB)',
            'Dầu khí toàn cầu (GPB)',
            'Sacombank (STB)',
            'An Bình (ABBANK)',
            'TNHH MTV Hong Leong VN (HLB)',
            'MTV Shinhan Việt Nam (SHBVN)',
            'Liên Doanh Việt Nga (VRB)',
            'Xây dựng Việt Nam (CBB)',
            'United Overseas Bank Việt Nam (UOB)',
            'Woori Việt Nam (Woori)',
            'Indovina (IVB)',
            'Việt Nam Thịnh Vượng CAKE BANK (CAKEVPB)',
            'Việt Nam Thịnh Vượng UBANK (UBANKVPB)',
            'Quân đội (MB)',
        ];
    }

    private function inlineCell(string $cell, string $value, int $style = 0): string
    {
        return '<c r="'.$cell.'" s="'.$style.'" t="inlineStr"><is><t>'.$this->xml($value).'</t></is></c>';
    }

    private function numberCell(string $cell, int|float $value, int $style = 0): string
    {
        return '<c r="'.$cell.'" s="'.$style.'"><v>'.$value.'</v></c>';
    }

    private function workbookXml(): string
    {
        return $this->xmlHeader()
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView windowWidth="29040" windowHeight="15840"/></bookViews>'
            .'<sheets>'
            .'<sheet name="eMB_BulkPayment" sheetId="1" r:id="rId1"/>'
            .'<sheet name="Tên NH Gợi ý" sheetId="2" r:id="rId2"/>'
            .'<sheet name="Sheet3" sheetId="3" r:id="rId3"/>'
            .'</sheets>'
            .'<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">eMB_BulkPayment!$B$2:$F$2</definedName></definedNames>'
            .'<calcPr calcId="144525"/>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return $this->xmlHeader()
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
            .'<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function contentTypesXml(): string
    {
        return $this->xmlHeader()
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return $this->xmlHeader()
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return $this->xmlHeader()
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts>'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="16"/><name val="Cambria"/></font>'
            .'<font><b/><sz val="12"/><name val="Times New Roman"/></font>'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFD8DAD9"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="3">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border>'
            .'<border><left/><right/><top/><bottom style="thin"/><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="6">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" shrinkToFit="1"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'<dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleMedium9"/>'
            .'</styleSheet>';
    }

    private function appPropsXml(): string
    {
        return $this->xmlHeader()
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>Microsoft Excel</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
            .'<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>3</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            .'<TitlesOfParts><vt:vector size="3" baseType="lpstr"><vt:lpstr>eMB_BulkPayment</vt:lpstr><vt:lpstr>Tên NH Gợi ý</vt:lpstr><vt:lpstr>Sheet3</vt:lpstr></vt:vector></TitlesOfParts>'
            .'</Properties>';
    }

    private function corePropsXml(): string
    {
        $now = now()->toIso8601ZuluString();

        return $this->xmlHeader()
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>SportGo</dc:creator><cp:lastModifiedBy>SportGo</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    /**
     * Build a simple uncompressed ZIP archive. PHP ZipArchive is not required.
     *
     * @param  array<string, string>  $entries
     */
    private function zipStored(array $entries): string
    {
        $files = '';
        $central = '';
        $offset = 0;
        [$dosTime, $dosDate] = $this->dosTimestamp();

        foreach ($entries as $name => $content) {
            $crc = (int) hexdec(hash('crc32b', $content));
            $size = strlen($content);
            $nameLength = strlen($name);

            $localHeader = pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0);
            $files .= $localHeader.$name.$content;

            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
            $offset += strlen($localHeader) + $nameLength + $size;
        }

        $centralOffset = strlen($files);
        $centralSize = strlen($central);
        $count = count($entries);

        return $files.$central.pack('VvvvvVVv', 0x06054B50, 0, 0, $count, $count, $centralSize, $centralOffset, 0);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dosTimestamp(): array
    {
        $date = now();
        $time = ($date->hour << 11) | ($date->minute << 5) | intdiv($date->second, 2);
        $day = ($date->year - 1980) << 9 | ($date->month << 5) | $date->day;

        return [$time, $day];
    }

    private function xmlHeader(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}

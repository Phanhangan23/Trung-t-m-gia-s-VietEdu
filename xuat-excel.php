<?php
/**
 * XUẤT FILE EXCEL (.xlsx) BẰNG PHP THUẦN
 * Không cần extension zip hay thư viện ngoài: tự đóng gói ZIP và viết XML theo chuẩn Office Open XML.
 *
 * Cách dùng:
 *     $sheet = [
 *         'name'   => 'Giao dịch',
 *         'cols'   => [14, 30, 18],            // độ rộng cột (ký tự)
 *         'rows'   => [                         // mỗi ô: giá trị thường, hoặc ['v' => giá trị, 's' => kiểu]
 *             [['v' => 'BÁO CÁO', 's' => 'title']],
 *             [['v' => 'Mã', 's' => 'head'], ['v' => 'Tên', 's' => 'head']],
 *             ['#TX-1', 'Nguyễn Văn A'],
 *         ],
 *         'freeze' => 'A5',                     // cố định các dòng phía trên ô này (tùy chọn)
 *         'filter' => 'A4:F4',                  // bật bộ lọc trên dòng tiêu đề (tùy chọn)
 *         'merge'  => ['A1:F1'],                // gộp ô (tùy chọn)
 *     ];
 *     xlsx_tai_ve('bao-cao.xlsx', [$sheet]);   // gửi về trình duyệt rồi dừng
 *
 * Kiểu ô ('s'): text, bold, title, sub, head, cell, money, money_total, datetime, label, total_label, percent
 * Giá trị bắt đầu bằng "=" được ghi thành công thức Excel. DateTimeInterface -> ngày giờ thật của Excel.
 */

const XLSX_KIEU = [            // tên kiểu => chỉ số trong <cellXfs> của styles.xml
    'text' => 0, 'title' => 1, 'head' => 2, 'cell' => 3, 'money' => 4, 'datetime' => 5,
    'bold' => 6, 'money_total' => 7, 'total_label' => 8, 'sub' => 9, 'label' => 10, 'percent' => 11, 'number' => 12,
];

function xlsx_esc(string $s): string
{
    // Bỏ ký tự điều khiển không hợp lệ trong XML
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Số thứ tự cột (0-based) -> chữ cái: 0 -> A, 26 -> AA. */
function xlsx_cot(int $i): string
{
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
    return $s;
}

function xlsx_sheet_xml(array $sh): string
{
    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
       . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

    if (!empty($sh['freeze']) && preg_match('/^([A-Z]+)(\d+)$/', $sh['freeze'], $m)) {
        $x .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . ((int) $m[2] - 1) . '" topLeftCell="' . $sh['freeze']
            . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
    }
    if (!empty($sh['cols'])) {
        $x .= '<cols>';
        foreach (array_values($sh['cols']) as $i => $w) {
            $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $w . '" customWidth="1"/>';
        }
        $x .= '</cols>';
    }

    $x .= '<sheetData>';
    foreach (array_values($sh['rows']) as $r => $row) {
        $soDong = $r + 1;
        $x .= '<row r="' . $soDong . '">';
        foreach (array_values($row) as $c => $o) {
            if (!is_array($o)) $o = ['v' => $o];
            $v   = $o['v'] ?? null;
            $ref = xlsx_cot($c) . $soDong;
            $s   = XLSX_KIEU[$o['s'] ?? 'text'] ?? 0;
            $sa  = $s ? ' s="' . $s . '"' : '';
            if ($v === null || $v === '') {
                $x .= '<c r="' . $ref . '"' . $sa . '/>';
            } elseif ($v instanceof DateTimeInterface) {
                // Ngày giờ Excel = số ngày kể từ 30/12/1899 (tính theo giờ địa phương đã lưu)
                $goc = new DateTimeImmutable('1899-12-30 00:00:00', $v->getTimezone());
                $ngay = ($v->getTimestamp() + $v->getOffset() - ($goc->getTimestamp() + $goc->getOffset())) / 86400;
                $x .= '<c r="' . $ref . '"' . $sa . '><v>' . round($ngay, 8) . '</v></c>';
            } elseif (is_int($v) || is_float($v)) {
                $x .= '<c r="' . $ref . '"' . $sa . '><v>' . $v . '</v></c>';
            } elseif (is_string($v) && $v[0] === '=') {
                $x .= '<c r="' . $ref . '"' . $sa . '><f>' . xlsx_esc(substr($v, 1)) . '</f></c>';
            } else {
                $x .= '<c r="' . $ref . '"' . $sa . ' t="inlineStr"><is><t xml:space="preserve">' . xlsx_esc((string) $v) . '</t></is></c>';
            }
        }
        $x .= '</row>';
    }
    $x .= '</sheetData>';

    if (!empty($sh['filter'])) $x .= '<autoFilter ref="' . xlsx_esc($sh['filter']) . '"/>';
    if (!empty($sh['merge'])) {
        $x .= '<mergeCells count="' . count($sh['merge']) . '">';
        foreach ($sh['merge'] as $mg) $x .= '<mergeCell ref="' . xlsx_esc($mg) . '"/>';
        $x .= '</mergeCells>';
    }
    $x .= '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
        . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
    return $x . '</worksheet>';
}

function xlsx_styles_xml(): string
{
    $do = 'FF800020';   // đỏ đô – màu chủ đạo của VietEdu
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="3">'
        .   '<numFmt numFmtId="164" formatCode="#,##0&quot; ₫&quot;"/>'
        .   '<numFmt numFmtId="165" formatCode="dd/mm/yyyy hh:mm"/>'
        .   '<numFmt numFmtId="166" formatCode="0.0%"/>'
        . '</numFmts>'
        . '<fonts count="5">'
        .   '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
        .   '<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
        .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
        .   '<font><b/><sz val="15"/><color rgb="' . $do . '"/><name val="Calibri"/><family val="2"/></font>'
        .   '<font><i/><sz val="10"/><color rgb="FF666666"/><name val="Calibri"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        .   '<fill><patternFill patternType="none"/></fill>'
        .   '<fill><patternFill patternType="gray125"/></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="' . $do . '"/><bgColor indexed="64"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FFF8ECEE"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        .   '<border><left/><right/><top/><bottom/><diagonal/></border>'
        .   '<border><left style="thin"><color rgb="FFD9CDD0"/></left><right style="thin"><color rgb="FFD9CDD0"/></right>'
        .   '<top style="thin"><color rgb="FFD9CDD0"/></top><bottom style="thin"><color rgb="FFD9CDD0"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="13">'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                     // 0 text
        .   '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                       // 1 title
        .   '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 2 head
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>' // 3 cell
        .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'           // 4 money
        .   '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>' // 5 datetime
        .   '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                       // 6 bold
        .   '<xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>' // 7 money_total
        .   '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>' // 8 total_label
        .   '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                       // 9 sub
        .   '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'         // 10 label
        .   '<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'           // 11 percent
        .   '<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'             // 12 number
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

/** Đóng gói danh sách [đường dẫn => nội dung] thành 1 file ZIP (nén deflate nếu có zlib). */
function xlsx_zip(array $files): string
{
    $data = '';
    $dir  = '';
    $t    = getdate();
    $dosTime = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
    $dosDate = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
    foreach ($files as $name => $raw) {
        $crc  = crc32($raw);
        $nen  = function_exists('gzdeflate') ? gzdeflate($raw, 6) : false;
        $kieu = $nen !== false ? 8 : 0;
        $body = $nen !== false ? $nen : $raw;
        $head = pack('vvvvvVVVvv', 20, 0x0800, $kieu, $dosTime, $dosDate, $crc, strlen($body), strlen($raw), strlen($name), 0);
        $dir .= pack('V', 0x02014b50) . pack('v', 20) . $head . pack('vvvVV', 0, 0, 0, 0, strlen($data)) . $name;
        $data .= pack('V', 0x04034b50) . $head . $name . $body;
    }
    return $data . $dir . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($dir), strlen($data), 0);
}

/** Tạo nội dung file .xlsx từ danh sách sheet. */
function xlsx_tao(array $sheets, string $tacGia = 'VietEdu'): string
{
    $files = [];
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
    $wbSheets = '';
    $wbRels   = '';
    $defined  = '';
    foreach (array_values($sheets) as $i => $sh) {
        $n = $i + 1;
        $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        // Tên sheet: tối đa 31 ký tự, không chứa : \ / ? * [ ]
        $ten = mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', ' ', (string) ($sh['name'] ?? 'Sheet' . $n)), 0, 31, 'UTF-8');
        $wbSheets .= '<sheet name="' . xlsx_esc($ten) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $wbRels   .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        if (!empty($sh['filter'])) {
            $abs = preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $sh['filter']);
            $defined .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\'' . xlsx_esc(str_replace("'", "''", $ten)) . '\'!' . $abs . '</definedName>';
        }
        $files['xl/worksheets/sheet' . $n . '.xml'] = xlsx_sheet_xml($sh);
    }
    $ct .= '</Types>';
    $n++;

    $files['[Content_Types].xml'] = $ct;
    $files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '</Relationships>';
    $files['docProps/core.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
        . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:creator>' . xlsx_esc($tacGia) . '</dc:creator>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
        . '</cp:coreProperties>';
    $files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $wbSheets . '</sheets>' . ($defined ? '<definedNames>' . $defined . '</definedNames>' : '') . '</workbook>';
    $files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels
        . '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
    $files['xl/styles.xml'] = xlsx_styles_xml();

    return xlsx_zip($files);
}

/** Gửi file .xlsx về trình duyệt để tải xuống, rồi dừng chương trình. */
function xlsx_tai_ve(string $tenFile, array $sheets): void
{
    $noiDung = xlsx_tao($sheets);
    while (ob_get_level()) ob_end_clean();
    $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $tenFile);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($tenFile));
    header('Content-Length: ' . strlen($noiDung));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    echo $noiDung;
    exit;
}

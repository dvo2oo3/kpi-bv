<?php
/**
 * Đọc và ghi file Excel .xlsx bằng PHP thuần.
 *
 * VÌ SAO PHẢI TỰ VIẾT: InfinityFree không chạy được Composer nên không cài
 * được PhpSpreadsheet, và phần mở rộng ZipArchive cũng có thể không có.
 * File .xlsx thực chất là một file ZIP chứa vài file XML, mà ZIP thì dựng
 * được bằng zlib (gzdeflate/gzinflate) — thứ gần như máy chủ nào cũng có.
 *
 * Phạm vi: một trang tính, ô chỉ chứa chữ hoặc số. Đủ cho biểu mẫu nhập liệu,
 * không nhằm thay thế một thư viện đầy đủ.
 */

function xlsx_kha_dung(): bool
{
    return function_exists('gzdeflate') && function_exists('gzinflate')
        && function_exists('simplexml_load_string');
}

/* ============================================================
 * ZIP
 * ========================================================== */

/** Đóng gói các file thành một chuỗi ZIP. $tep = [đường dẫn trong zip => nội dung] */
function zip_tao(array $tep): string
{
    $cucBo = '';      // phần dữ liệu
    $trungTam = '';   // thư mục trung tâm
    $viTri = 0;

    foreach ($tep as $ten => $noiDung) {
        $crc  = crc32($noiDung);
        $goc  = strlen($noiDung);
        $nen  = gzdeflate($noiDung, 6);
        $sNen = strlen($nen);

        $dauCucBo = "\x50\x4b\x03\x04"
            . pack('v', 20)        // cần phiên bản 2.0
            . pack('v', 0)         // cờ
            . pack('v', 8)         // phương pháp: deflate
            . pack('v', 0)         // giờ
            . pack('v', 0)         // ngày
            . pack('V', $crc)
            . pack('V', $sNen)
            . pack('V', $goc)
            . pack('v', strlen($ten))
            . pack('v', 0);

        $cucBo .= $dauCucBo . $ten . $nen;

        $trungTam .= "\x50\x4b\x01\x02"
            . pack('v', 20) . pack('v', 20)
            . pack('v', 0) . pack('v', 8)
            . pack('v', 0) . pack('v', 0)
            . pack('V', $crc) . pack('V', $sNen) . pack('V', $goc)
            . pack('v', strlen($ten))
            . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('v', 0)
            . pack('V', 0)
            . pack('V', $viTri)
            . $ten;

        $viTri += strlen($dauCucBo) + strlen($ten) + $sNen;
    }

    return $cucBo . $trungTam . "\x50\x4b\x05\x06"
        . pack('v', 0) . pack('v', 0)
        . pack('v', count($tep)) . pack('v', count($tep))
        . pack('V', strlen($trungTam))
        . pack('V', $viTri)
        . pack('v', 0);
}

/** Giải nén một chuỗi ZIP. Trả về [đường dẫn trong zip => nội dung]. */
function zip_doc(string $zip): array
{
    // Tìm bản ghi kết thúc thư mục trung tâm, quét ngược từ cuối file
    $viTriKet = -1;
    for ($i = strlen($zip) - 22; $i >= 0 && $i > strlen($zip) - 65558; $i--) {
        if (substr($zip, $i, 4) === "\x50\x4b\x05\x06") {
            $viTriKet = $i;
            break;
        }
    }
    if ($viTriKet < 0) {
        return [];
    }
    $ket = unpack('vsoTep/Vkichthuoc/Vviitri', substr($zip, $viTriKet + 10, 10));
    $n = $ket['soTep'];
    $p = $ket['viitri'];

    $tep = [];
    for ($i = 0; $i < $n; $i++) {
        if (substr($zip, $p, 4) !== "\x50\x4b\x01\x02") {
            break;
        }
        $h = unpack('vphuongphap', substr($zip, $p + 10, 2))
           + unpack('VsNen', substr($zip, $p + 20, 4))
           + unpack('Vgoc', substr($zip, $p + 24, 4))
           + unpack('vlTen/vlPhu/vlChuThich', substr($zip, $p + 28, 6))
           + unpack('VviTriCucBo', substr($zip, $p + 42, 4));
        $ten = substr($zip, $p + 46, $h['lTen']);

        // Nhảy tới dữ liệu: bỏ qua phần đầu cục bộ (30 byte + tên + phần phụ)
        $lb = $h['viTriCucBo'];
        $hb = unpack('vlTen/vlPhu', substr($zip, $lb + 26, 4));
        $duLieu = substr($zip, $lb + 30 + $hb['lTen'] + $hb['lPhu'], $h['sNen']);

        if ($h['phuongphap'] === 8) {
            $duLieu = @gzinflate($duLieu);
        }
        if ($duLieu !== false) {
            $tep[$ten] = $duLieu;
        }
        $p += 46 + $h['lTen'] + $h['lPhu'] + $h['lChuThich'];
    }
    return $tep;
}

/* ============================================================
 * XLSX
 * ========================================================== */

/** Số cột 1 -> "A", 27 -> "AA" */
function xlsx_cot(int $n): string
{
    $s = '';
    while ($n > 0) {
        $d = ($n - 1) % 26;
        $s = chr(65 + $d) . $s;
        $n = (int)(($n - $d - 1) / 26);
    }
    return $s;
}

function xlsx_thoat(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Tạo file .xlsx một trang tính.
 *
 * @param array  $dong    mảng các dòng, mỗi dòng là mảng ô (chuỗi hoặc số)
 * @param array  $rongCot bề rộng từng cột, tính theo ký tự
 * @param int    $soDongDau  số dòng đầu được tô đậm làm tiêu đề
 */
function xlsx_tao(array $dong, string $tenTrang = 'Sheet1',
                  array $rongCot = [], int $soDongDau = 1): string
{
    $o = '';
    foreach ($dong as $i => $cot) {
        $r = $i + 1;
        $o .= '<row r="' . $r . '">';
        foreach (array_values($cot) as $j => $v) {
            $ref = xlsx_cot($j + 1) . $r;
            $kieu = $r <= $soDongDau ? ' s="1"' : '';
            if ($v === null || $v === '') {
                continue;
            }
            if (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v) && trim($v) !== '')) {
                $o .= '<c r="' . $ref . '"' . $kieu . '><v>' . (0 + $v) . '</v></c>';
            } else {
                $o .= '<c r="' . $ref . '"' . $kieu . ' t="inlineStr"><is><t xml:space="preserve">'
                    . xlsx_thoat((string)$v) . '</t></is></c>';
            }
        }
        $o .= '</row>';
    }

    $cols = '';
    if ($rongCot) {
        $cols = '<cols>';
        foreach ($rongCot as $j => $w) {
            $cols .= '<col min="' . ($j + 1) . '" max="' . ($j + 1)
                   . '" width="' . (float)$w . '" customWidth="1"/>';
        }
        $cols .= '</cols>';
    }

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0">'
        . '<pane ySplit="' . $soDongDau . '" topLeftCell="A' . ($soDongDau + 1)
        . '" activePane="bottomLeft" state="frozen"/>'
        . '</sheetView></sheetViews>'
        . $cols . '<sheetData>' . $o . '</sheetData></worksheet>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border/></borders>'
        . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
        . '<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs>'
        // Thiếu kiểu mặc định thì trình đọc cảnh báo và Excel có thể kêu file hỏng
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    return zip_tao([
        '[Content_Types].xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . xlsx_thoat($tenTrang) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>',
        'xl/_rels/workbook.xml.rels' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => $styles,
        'xl/worksheets/sheet1.xml' => $sheet,
    ]);
}

/**
 * Đọc trang tính đầu tiên của file .xlsx.
 * @return array mảng dòng, mỗi dòng là [chỉ số cột từ 0 => giá trị chuỗi]
 */
function xlsx_doc(string $noiDung): array
{
    $tep = zip_doc($noiDung);
    if (!$tep) {
        return [];
    }

    // Chuỗi dùng chung — Excel thường lưu chữ ở đây thay vì trong ô
    $chung = [];
    if (isset($tep['xl/sharedStrings.xml'])) {
        $xml = @simplexml_load_string($tep['xl/sharedStrings.xml']);
        if ($xml) {
            foreach ($xml->si as $si) {
                $chung[] = isset($si->t) ? (string)$si->t
                    : implode('', array_map(fn($r) => (string)$r->t, iterator_to_array($si->r ?? [])));
            }
        }
    }

    $tenSheet = null;
    foreach ($tep as $ten => $_) {
        if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $ten)) {
            $tenSheet = $ten;
            break;
        }
    }
    if ($tenSheet === null) {
        return [];
    }
    $xml = @simplexml_load_string($tep[$tenSheet]);
    if (!$xml) {
        return [];
    }

    $bang = [];
    foreach ($xml->sheetData->row as $row) {
        $soDong = (int)$row['r'];
        $dong = [];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            preg_match('/^([A-Z]+)/', $ref, $m);
            $cot = 0;
            foreach (str_split($m[1] ?? 'A') as $ch) {
                $cot = $cot * 26 + (ord($ch) - 64);
            }
            $cot--;

            $t = (string)$c['t'];
            if ($t === 's') {
                $v = $chung[(int)$c->v] ?? '';
            } elseif ($t === 'inlineStr') {
                $v = isset($c->is->t) ? (string)$c->is->t : '';
            } elseif ($t === 'str') {
                $v = (string)$c->v;
            } else {
                $v = isset($c->v) ? (string)$c->v : '';
            }
            $dong[$cot] = trim($v);
        }
        $bang[$soDong - 1] = $dong;
    }
    ksort($bang);
    return $bang;
}

<?php
/**
 * Dựng bảng báo cáo và xuất Excel.
 *
 * Toàn viện được CỘNG TỪ SỐ LIỆU KHOA, không nhập tay — đây là chỗ
 * bảng Excel hiện tại lệch nhau (X-quang 11.540 ở sheet toàn viện
 * so với 13.029 ở khoa Chẩn đoán hình ảnh).
 */
require_once __DIR__ . '/chi_tieu.php';

/**
 * Phần trăm so với chỉ tiêu CẢ NĂM — đúng cách tính của cột "So KH" trong
 * hai file Excel đang dùng, và là con số phải in ra báo cáo gửi Sở.
 *
 * Khác với phần trăm trong danh_gia_kpi(): ở đó so với chỉ tiêu của riêng kỳ
 * (đã chia theo số ngày) để chấm đạt/chưa đạt cho công bằng khi năm chưa hết.
 * Ví dụ khoa Nhi hết 6 tháng: 503/1825 = 27,6% so KH năm, nhưng so với
 * chỉ tiêu 6 tháng (905) thì được 55,6% — mới là mức phản ánh đúng tiến độ.
 */
function pt_so_ke_hoach_nam(?float $thucHien, ?float $chiTieuNam): ?float
{
    if ($thucHien === null || $chiTieuNam === null || abs($chiTieuNam) < 1e-9) {
        return null;
    }
    return $thucHien / $chiTieuNam * 100;
}

/** Một dòng báo cáo cho một khoa. */
function bang_theo_khoa(int $nam, array $cacThang, int $idKhoa, string $goc = 'giao'): array
{
    $ds = [];
    foreach (chi_tieu_cua_khoa($idKhoa) as $ct) {
        $th = gia_tri_luy_ke($nam, $cacThang, $idKhoa, $ct['id']);
        $kh = chi_tieu_cua_ky($nam, $cacThang, $idKhoa, $ct['id'], $goc);
        $khNam = chi_tieu_cua_ky($nam, range(1, 12), $idKhoa, $ct['id'], $goc);
        $namTruoc = null;
        $r = ke_hoach_nam($nam, $idKhoa)[$ct['id']] ?? null;
        if ($r && $r['th_nam_truoc'] !== null) {
            $namTruoc = (float)$r['th_nam_truoc'];
        }
        $ds[] = [
            'ct'           => $ct,
            'cap'          => $ct['cap'],
            'chi_tieu'     => $kh,
            'chi_tieu_nam' => $khNam,
            'thuc_hien'    => $th,
            'nam_truoc'    => $namTruoc,
            'kpi'          => danh_gia_kpi($ct, $th, $kh),
            'pt_nam'       => pt_so_ke_hoach_nam($th, $khNam),
        ];
    }
    return $ds;
}

/**
 * Toàn viện. Cộng số liệu tất cả khoa cho từng chỉ tiêu.
 *
 * Xét nghiệm và chẩn đoán hình ảnh chỉ lấy ở KHOA THỰC HIỆN (XN, CDHA)
 * để không cộng trùng với chỉ định của các khoa lâm sàng.
 */
const CHI_TIEU_LAY_O_KHOA_THUC_HIEN = [
    'XN' => 'XN', 'XN_HH' => 'XN', 'XN_HS' => 'XN', 'XN_VS' => 'XN', 'XN_NT' => 'XN',
    'XN_HIV' => 'XN',
    'XQ' => 'CDHA', 'CT' => 'CDHA', 'MRI' => 'CDHA', 'SA' => 'CDHA',
    'DT' => 'CDHA', 'NS' => 'CDHA', 'DEXA' => 'CDHA',
];

function bang_toan_vien(int $nam, array $cacThang, string $goc = 'giao'): array
{
    $dsKhoa = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');
    $idTheoMa = [];
    foreach ($dsKhoa as $k) {
        $idTheoMa[$k['ma']] = (int)$k['id'];
    }

    // Tập hợp chỉ tiêu xuất hiện ở ít nhất một khoa
    $dungO = [];
    foreach ($dsKhoa as $k) {
        foreach (chi_tieu_cua_khoa((int)$k['id']) as $ct) {
            $dungO[$ct['id']][] = (int)$k['id'];
        }
    }

    $ds = [];
    foreach (cay_tat_ca() as $ct) {   // theo thứ tự cây, $ct['cap'] là độ sâu thật
        // Biến thể "gộp vào" không hiện dòng riêng — số liệu đã cộng vào chỉ tiêu chuẩn.
        if (!empty($ct['gop_vao'])) {
            continue;
        }

        $dacBiet = isset(CHI_TIEU_LAY_O_KHOA_THUC_HIEN[$ct['ma']]);
        // Nhóm id cần cộng: chỉ tiêu này + các biến thể gộp vào nó
        // (chỉ tiêu đặc biệt chống đếm trùng và công thức thì không gộp).
        $idsCong = ($dacBiet || $ct['nguon'] === 'CONG_THUC')
            ? [(int)$ct['id']] : ids_gop_vao($ct['ma']);

        // Khoa dùng bất kỳ id nào trong nhóm
        $khoaSet = [];
        foreach ($idsCong as $idCT) {
            foreach ($dungO[$idCT] ?? [] as $idK) { $khoaSet[$idK] = true; }
        }
        if (!$khoaSet) {
            continue;   // cả nhóm không khoa nào dùng
        }
        $khoaCong = array_keys($khoaSet);
        if ($dacBiet) {
            $maTH = CHI_TIEU_LAY_O_KHOA_THUC_HIEN[$ct['ma']];
            $khoaCong = isset($idTheoMa[$maTH]) ? [$idTheoMa[$maTH]] : [];
        }

        // $ct['cap'] đã có từ cay_tat_ca() (độ sâu thật trong cây)
        $th = null; $kh = null; $khNam = null; $truoc = null;

        if ($ct['nguon'] === 'CONG_THUC') {
            // Tính lại trên số liệu toàn viện, KHÔNG cộng phần trăm các khoa
            $th    = cong_thuc_toan_vien($ct['ma'], $nam, $cacThang, $dsKhoa);
            $kh    = chi_tieu_toan_vien_cong_thuc($ct['ma']);
            $khNam = $kh;
        } else {
            foreach ($khoaCong as $idK) {
                foreach ($idsCong as $idCT) {
                    $v = gia_tri_luy_ke($nam, $cacThang, $idK, $idCT);
                    if ($v !== null) {
                        $th = ($th ?? 0) + $v;
                    }
                    $c = chi_tieu_cua_ky($nam, $cacThang, $idK, $idCT, $goc);
                    if ($c !== null) {
                        $kh = ($kh ?? 0) + $c;
                    }
                    $cn = chi_tieu_cua_ky($nam, range(1, 12), $idK, $idCT, $goc);
                    if ($cn !== null) {
                        $khNam = ($khNam ?? 0) + $cn;
                    }
                    $r = ke_hoach_nam($nam, $idK)[$idCT] ?? null;
                    if ($r && $r['th_nam_truoc'] !== null) {
                        $truoc = ($truoc ?? 0) + (float)$r['th_nam_truoc'];
                    }
                }
            }
        }

        $ds[] = [
            'ct'           => $ct,
            'cap'          => $ct['cap'],
            'chi_tieu'     => $kh,
            'chi_tieu_nam' => $khNam,
            'thuc_hien'    => $th,
            'nam_truoc'    => $truoc,
            'kpi'          => danh_gia_kpi($ct, $th, $kh),
            'pt_nam'       => pt_so_ke_hoach_nam($th, $khNam),
        ];
    }
    return $ds;
}

/**
 * Thực hiện lũy kế và chỉ tiêu cả năm của một chỉ tiêu.
 * $idKhoa = null nghĩa là toàn viện, có áp dụng quy tắc chống đếm trùng.
 *
 * @return array{th: ?float, kh_nam: ?float}
 */
function tri_chi_tieu(int $nam, array $cacThang, ?int $idKhoa, string $ma,
                      string $goc = 'giao'): array
{
    $ct = chi_tieu_theo_ma($ma);
    if (!$ct) {
        return ['th' => null, 'kh_nam' => null];
    }

    if ($idKhoa !== null) {
        return [
            'th'     => gia_tri_luy_ke($nam, $cacThang, $idKhoa, $ct['id']),
            'kh_nam' => chi_tieu_cua_ky($nam, range(1, 12), $idKhoa, $ct['id'], $goc),
        ];
    }

    $dsK = qAll('SELECT * FROM khoa WHERE hoat_dong = 1');
    if ($ct['nguon'] === 'CONG_THUC') {
        return [
            'th'     => cong_thuc_toan_vien($ma, $nam, $cacThang, $dsK),
            'kh_nam' => chi_tieu_toan_vien_cong_thuc($ma),
        ];
    }

    // Chỉ tiêu đặc biệt (chống đếm trùng) chỉ lấy ở đúng một khoa và không gộp.
    if (isset(CHI_TIEU_LAY_O_KHOA_THUC_HIEN[$ma])) {
        $khoaCong = array_values(array_filter($dsK,
            fn($k) => $k['ma'] === CHI_TIEU_LAY_O_KHOA_THUC_HIEN[$ma]));
        $idsCong = [$ct['id']];
    } else {
        $khoaCong = $dsK;
        $idsCong  = ids_gop_vao($ma);   // cộng cả các chỉ tiêu riêng "gộp vào"
    }

    $th = null; $kh = null;
    foreach ($khoaCong as $k) {
        foreach ($idsCong as $idCT) {
            $v = gia_tri_luy_ke($nam, $cacThang, (int)$k['id'], $idCT);
            if ($v !== null) {
                $th = ($th ?? 0) + $v;
            }
            $c = chi_tieu_cua_ky($nam, range(1, 12), (int)$k['id'], $idCT, $goc);
            if ($c !== null) {
                $kh = ($kh ?? 0) + $c;
            }
        }
    }
    return ['th' => $th, 'kh_nam' => $kh];
}

/**
 * Chuỗi 12 tháng của một chỉ tiêu, để vẽ biểu đồ xu hướng.
 *
 * $idKhoa = null nghĩa là toàn viện — vẫn áp dụng quy tắc chống đếm trùng
 * xét nghiệm / chẩn đoán hình ảnh như bảng báo cáo toàn viện.
 *
 * @return array [thang => giá trị|null]  null = tháng chưa có số liệu
 */
function chuoi_12_thang(int $nam, ?int $idKhoa, string $maChiTieu): array
{
    $ct = chi_tieu_theo_ma($maChiTieu);
    if (!$ct) {
        return [];
    }

    if ($idKhoa !== null) {
        $kq = [];
        for ($t = 1; $t <= 12; $t++) {
            $kq[$t] = gia_tri_thang($nam, $t, $idKhoa, $ct['id']);
        }
        return $kq;
    }

    $dsKhoa = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');
    if (isset(CHI_TIEU_LAY_O_KHOA_THUC_HIEN[$maChiTieu])) {
        $maTH = CHI_TIEU_LAY_O_KHOA_THUC_HIEN[$maChiTieu];
        $dsKhoa = array_values(array_filter($dsKhoa, fn($k) => $k['ma'] === $maTH));
    }

    $kq = [];
    for ($t = 1; $t <= 12; $t++) {
        if ($ct['nguon'] === 'CONG_THUC') {
            $kq[$t] = cong_thuc_toan_vien($maChiTieu, $nam, [$t], $dsKhoa);
            continue;
        }
        $tong = null;
        foreach ($dsKhoa as $k) {
            $v = gia_tri_thang($nam, $t, (int)$k['id'], $ct['id']);
            if ($v !== null) {
                $tong = ($tong ?? 0) + $v;
            }
        }
        $kq[$t] = $tong;
    }
    return $kq;
}

/** Chỉ tiêu toàn viện cho các chỉ tiêu dẫn xuất là mức đích, không cộng. */
function chi_tieu_toan_vien_cong_thuc(string $ma): ?float
{
    return match ($ma) {
        'CSGB'   => 100.0,   // đích công suất giường bệnh
        'NDT_TB' => (float)(qVal(
            'SELECT AVG(chi_tieu_giao) FROM ke_hoach kh
               JOIN chi_tieu ct ON ct.id = kh.id_chi_tieu
              WHERE ct.ma = ?', ['NDT_TB']) ?? 0) ?: null,
        default  => null,
    };
}

/** Công thức tính trên số liệu gộp của toàn viện. */
function cong_thuc_toan_vien(string $ma, int $nam, array $cacThang, array $dsKhoa): ?float
{
    $ctNDT = chi_tieu_theo_ma('NDT');
    $ctNT  = chi_tieu_theo_ma('NT');
    $ctGB  = chi_tieu_theo_ma('GB');
    if (!$ctNDT) {
        return null;
    }

    $tongNgay = null;
    foreach ($dsKhoa as $k) {
        $v = gia_tri_luy_ke($nam, $cacThang, (int)$k['id'], $ctNDT['id']);
        if ($v !== null) {
            $tongNgay = ($tongNgay ?? 0) + $v;
        }
    }
    if ($tongNgay === null) {
        return null;
    }

    if ($ma === 'NDT_TB') {
        if (!$ctNT) {
            return null;
        }
        $tongBN = null;
        foreach ($dsKhoa as $k) {
            $v = gia_tri_luy_ke($nam, $cacThang, (int)$k['id'], $ctNT['id']);
            if ($v !== null) {
                $tongBN = ($tongBN ?? 0) + $v;
            }
        }
        return ($tongBN && $tongBN > 0) ? $tongNgay / $tongBN : null;
    }

    if ($ma === 'CSGB') {
        $mau = 0.0;
        foreach ($dsKhoa as $k) {
            $idK = (int)$k['id'];
            foreach ($cacThang as $t) {
                if (gia_tri_thang($nam, $t, $idK, $ctNDT['id']) === null) {
                    continue;
                }
                $gb = $ctGB ? gia_tri_thang($nam, $t, $idK, $ctGB['id']) : null;
                if ($gb === null) {
                    $gb = (float)$k['giuong_benh'];
                }
                $mau += $gb * so_ngay_thang($nam, $t);
            }
        }
        return $mau > 0 ? $tongNgay / $mau * 100 : null;
    }
    return null;
}

/* ============================================================
 * Xuất Excel
 *
 * Xuất dạng SpreadsheetML 2003 (.xls) — Excel mở trực tiếp, không cần
 * thư viện ngoài. Quan trọng vì InfinityFree không chạy được Composer.
 * ========================================================== */

function xuat_excel(int $nam, string $ky, string $phamVi, int $idKhoa, string $goc): void
{
    $cacThang = cac_thang_cua_ky($ky);

    if ($phamVi === 'toan_vien') {
        $bang    = bang_toan_vien($nam, $cacThang, $goc);
        $tenBang = 'TOÀN VIỆN';
        $tenFile = "chi-tieu-toan-vien-{$ky}-{$nam}.xls";
    } else {
        $khoa    = q1('SELECT * FROM khoa WHERE id = ?', [$idKhoa]);
        $bang    = bang_theo_khoa($nam, $cacThang, $idKhoa, $goc);
        $tenBang = chu_hoa($khoa['ten'] ?? '');
        $tenFile = 'chi-tieu-' . strtolower($khoa['ma'] ?? 'khoa') . "-{$ky}-{$nam}.xls";
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $tenFile . '"');
    header('Cache-Control: max-age=0');

    $x = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
  <Style ss:ID="tieude"><Font ss:Bold="1" ss:Size="13"/>
    <Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="phu"><Font ss:Italic="1" ss:Size="9"/></Style>
  <Style ss:ID="dau"><Font ss:Bold="1"/>
    <Interior ss:Color="#DDE5EC" ss:Pattern="Solid"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous"/></Borders></Style>
  <Style ss:ID="cha"><Font ss:Bold="1"/></Style>
  <Style ss:ID="con"><Alignment ss:Indent="1"/></Style>
  <Style ss:ID="con1"><Alignment ss:Indent="1"/></Style>
  <Style ss:ID="con2"><Alignment ss:Indent="2"/></Style>
  <Style ss:ID="con3"><Alignment ss:Indent="3"/></Style>
  <Style ss:ID="con4"><Alignment ss:Indent="4"/></Style>
  <Style ss:ID="so"><NumberFormat ss:Format="#,##0"/></Style>
  <Style ss:ID="so1"><NumberFormat ss:Format="#,##0.0"/></Style>
  <Style ss:ID="pt"><NumberFormat ss:Format="0.0&quot;%&quot;"/></Style>
</Styles>
<Worksheet ss:Name="Bao cao">
<Table>
  <Column ss:Width="300"/><Column ss:Width="55"/><Column ss:Width="80"/>
  <Column ss:Width="80"/><Column ss:Width="80"/><Column ss:Width="70"/>
  <Column ss:Width="70"/><Column ss:Width="130"/><Column ss:Width="80"/>
  <Row><Cell ss:StyleID="tieude" ss:MergeAcross="8">
    <Data ss:Type="String"><?= $x(TEN_DON_VI) ?></Data></Cell></Row>
  <Row><Cell ss:StyleID="tieude" ss:MergeAcross="8">
    <Data ss:Type="String">THỰC HIỆN CHỈ TIÊU KẾ HOẠCH CHUYÊN MÔN <?= $x($tenBang) ?></Data></Cell></Row>
  <Row><Cell ss:StyleID="tieude" ss:MergeAcross="8">
    <Data ss:Type="String"><?= $x(ten_ky($ky)) ?> năm <?= $nam ?></Data></Cell></Row>
  <Row><Cell ss:StyleID="phu" ss:MergeAcross="8">
    <Data ss:Type="String">Mốc chỉ tiêu: <?= $goc === 'giao' ? 'theo quyết định giao' : 'theo năng lực tính toán' ?>. Kết xuất <?= date('d/m/Y H:i') ?></Data></Cell></Row>
  <Row/>
  <Row>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Nội dung</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Đơn vị</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Chỉ tiêu kỳ</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Chỉ tiêu năm</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Thực hiện</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">So KH kỳ</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">So KH năm</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Đánh giá</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Cùng kỳ <?= $nam - 1 ?></Data></Cell>
  </Row>
<?php foreach ($bang as $d):
    $le  = in_array($d['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true);
    $kSo = $le ? 'so1' : 'so'; ?>
  <Row>
    <Cell ss:StyleID="<?= $d['cap'] ? 'con' . min(4, (int)$d['cap']) : 'cha' ?>">
      <Data ss:Type="String"><?= $x($d['ct']['ten']) ?></Data></Cell>
    <Cell><Data ss:Type="String"><?= $x($d['ct']['don_vi']) ?></Data></Cell>
    <?php if ($d['chi_tieu'] !== null): ?>
      <Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($d['chi_tieu'], 2) ?></Data></Cell>
    <?php else: ?><Cell/><?php endif; ?>
    <?php if ($d['chi_tieu_nam'] !== null): ?>
      <Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($d['chi_tieu_nam'], 2) ?></Data></Cell>
    <?php else: ?><Cell/><?php endif; ?>
    <?php if ($d['thuc_hien'] !== null): ?>
      <Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($d['thuc_hien'], 2) ?></Data></Cell>
    <?php else: ?><Cell/><?php endif; ?>
    <?php if ($d['kpi']['phan_tram'] !== null): ?>
      <Cell ss:StyleID="pt"><Data ss:Type="Number"><?= round($d['kpi']['phan_tram'], 2) ?></Data></Cell>
    <?php else: ?><Cell/><?php endif; ?>
    <?php if ($d['pt_nam'] !== null): ?>
      <Cell ss:StyleID="pt"><Data ss:Type="Number"><?= round($d['pt_nam'], 2) ?></Data></Cell>
    <?php else: ?><Cell/><?php endif; ?>
    <Cell><Data ss:Type="String"><?= $x($d['kpi']['mo_ta']) ?></Data></Cell>
    <?php if ($d['nam_truoc'] !== null): ?>
      <Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($d['nam_truoc'], 2) ?></Data></Cell>
    <?php else: ?><Cell/><?php endif; ?>
  </Row>
<?php endforeach; ?>
</Table>
</Worksheet>
</Workbook>
<?php
    ghi_nhat_ky('XUAT_BAO_CAO', $phamVi, "$ky/$nam");
}

require_once __DIR__ . '/bao_cao_workbook.php';

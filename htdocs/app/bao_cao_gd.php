<?php
/**
 * Báo cáo cho Giám đốc — theo mẫu Quyết định, cấu hình được:
 * chọn khoa, chỉ tiêu (ẩn/hiện), cột hiển thị, kỳ (mấy tháng).
 * Xuất SpreadsheetML (.xls) nhiều sheet: mỗi khoa 1 sheet + Toàn viện.
 * Cấu hình lưu ở cai_dat khóa 'baocao_gd' (JSON).
 */

/**
 * Làm tròn số cho ô Excel ĐÚNG số lẻ mà ô sẽ hiển thị — tránh "làm tròn 2 lần".
 * Nếu ghi round(5.149, 2) = 5.15 vào ô định dạng #,##0.0 thì Excel lại làm tròn
 * 5.15 → 5.2 (trong khi web number_format(5.149, 1) = 5.1). Round thẳng về đúng
 * bậc của ô: so/lk = 0 lẻ, so1/lk1/pt = 1 lẻ.
 */
function gd_so_o($v, string $sty): string
{
    static $dp = ['so' => 0, 'lk' => 0, 'so1' => 1, 'lk1' => 1, 'pt' => 1];
    return (string) round((float) $v, $dp[$sty] ?? 2);
}

/**
 * Lấy dữ liệu MỘT dòng khuôn theo mã. $maCt có thể là:
 *  - null  → dòng không có số.
 *  - chuỗi → một mã (tra thẳng trong $idx).
 *  - mảng  → CỘNG GỘP nhiều mã vào một dòng, vd khoa XN: "Tổng số vi sinh + Miễn dịch"
 *            = TONG_SO_XN_VSV_KST + MIEN_DICH (khoa nhập ở 2 mã riêng).
 * $idx: map mã → dòng bang_theo_khoa/bang_toan_vien (chi_tieu_nam, thuc_hien, nam_truoc…).
 */
function gd_dong_khuon($maCt, array $idx): ?array
{
    if ($maCt === null) { return null; }
    if (!is_array($maCt)) { return $idx[$maCt] ?? null; }
    $tong = ['nam_truoc' => null, 'chi_tieu_nam_truoc' => null,
             'chi_tieu_nam' => null, 'thuc_hien' => null, 'ct' => ['loai_gia_tri' => 'DEM']];
    $coGi = false;
    foreach ($maCt as $m) {
        $r = $idx[$m] ?? null;
        if (!$r) { continue; }
        $coGi = true;
        foreach (['nam_truoc', 'chi_tieu_nam_truoc', 'chi_tieu_nam', 'thuc_hien'] as $k) {
            if (($r[$k] ?? null) !== null) { $tong[$k] = ($tong[$k] ?? 0) + (float) $r[$k]; }
        }
    }
    if (!$coGi) { return null; }
    $tong['pt_nam'] = pt_so_ke_hoach_nam($tong['thuc_hien'], $tong['chi_tieu_nam']);
    return $tong;
}

/* ====================================================================
 * NỐI NGUỒN KHUÔN (cấu hình được trong UI, không cần sửa code).
 * Lưu ở cai_dat['khuon_nguon'] (JSON): { khoa_ma => { rowIndex => [mã,...] } }.
 *   - không có khóa  → dùng mã MẶC ĐỊNH trong KHUON_SO_KHOA.
 *   - mảng [mã,…]    → dòng này lấy số từ các mã đó (nhiều = cộng gộp).
 *   - mảng rỗng []   → CỐ Ý để trống.
 * ==================================================================== */

/** Đọc toàn bộ map nối nguồn khuôn. */
function khuon_map(): array
{
    $v = cai_dat_lay('khuon_nguon');
    $d = $v ? json_decode($v, true) : null;
    return is_array($d) ? $d : [];
}

/** Mã HIỆU DỤNG của dòng khuôn thứ $i ở khoa $khoaMa: override (nếu có) hoặc mặc định. */
function khuon_ma_hieu_dung(array $map, string $khoaMa, int $i, $maMacDinh)
{
    if (isset($map[$khoaMa]) && array_key_exists((string) $i, $map[$khoaMa])) {
        $ov = $map[$khoaMa][(string) $i];
        if (!is_array($ov) || $ov === []) { return null; }           // cố ý để trống
        return count($ov) === 1 ? $ov[0] : array_values($ov);        // 1 mã = chuỗi; nhiều = mảng (cộng gộp)
    }
    return $maMacDinh;
}

/** Lưu override 1 dòng. $dsMa: mảng mã (rỗng = cố ý trống); null = bỏ override (về mặc định).
 *  Trả về MAP MỚI (trong bộ nhớ) để gọi tiếp mà không vướng cache tĩnh của cai_dat. */
function khuon_map_dat(string $khoaMa, int $i, ?array $dsMa): array
{
    $map = khuon_map();
    if ($dsMa === null) {
        unset($map[$khoaMa][(string) $i]);
        if (isset($map[$khoaMa]) && !$map[$khoaMa]) { unset($map[$khoaMa]); }
    } else {
        $map[$khoaMa][(string) $i] = array_values(array_unique($dsMa));
    }
    cai_dat_dat('khuon_nguon', $map ? json_encode($map, JSON_UNESCAPED_UNICODE) : null);
    return $map;
}

/**
 * Bộ "chỉ tiêu chính" — bám theo mẫu "Theo dõi chỉ tiêu các khoa đạt được":
 * các chỉ tiêu QĐ 74 + chi tiết xét nghiệm, KHÔNG có các dòng con nhỏ
 * (BH/viện phí, khỏi/đỡ, chuyển viện chi tiết…). Mỗi khoa chỉ hiện phần được gán.
 */
const MA_CHI_TIEU_CHINH = [
    'GB', 'KB', 'KB_PK', 'KB_THAI',
    'NT', 'NDT', 'NDT_TB', 'CSGB',
    'TT', 'TT_L1', 'TT_L2', 'TT_L3',
    'PT', 'PT_L1', 'PT_L2', 'PT_L3',
    'DE', 'DE_THUONG', 'DE_MO', 'TSO_DT_NOI_TRU_PHU_KHOA',
    'XN', 'XN_HH', 'XN_HS', 'XN_VS', 'XN_NT',
    'TONG_SO_XN_HUYET_HOC', 'TONG_SO_XN_HOA_SINH', 'TONG_SO_XN_VSV_KST',
    'TONG_SO_XN_NUOC_TIEU', 'TONG_SO_XN_HIV', 'TONG_SO_HIV', 'MIEN_DICH',
    'XQ', 'SA', 'CT', 'MRI', 'DEXA', 'DT',
    'NS', 'CHI_DINH_NOI_SOI', 'NOI_SOI_TMH', 'NOI_SOI_DA_DAY', 'NS_TMH', 'NS_DD',
    'DT_NGOAI_TRU', 'COPD', 'TV_HIV', 'PNMT_HIV', 'NCKH',
];

/** Cấu hình mặc định của một mẫu xuất. */
function bao_cao_gd_mac_dinh(): array
{
    return [
        'mau'       => 'chi_tiet',            // chi_tiet | cha | custom
        'ky'        => '6thang',
        'khoa'      => 'all',                 // 'all' hoặc mảng id khoa
        'toan_vien' => 1,
        'cot'       => ['nam_truoc' => 1, 'chi_tieu_truoc' => 1, 'chi_tieu' => 1, 'ket_qua' => 1, 'pt' => 1],
        'bo_ma'     => [],                    // (cũ) mã ẩn toàn cục — không dùng cho custom mới
        'ct_khoa'   => [],                    // custom THEO KHOA: [id_khoa => [ma=>1,...]] các trường HIỆN
        'so_qd'     => '',                    // số quyết định in dưới tiêu đề
        'chi_tiet_thang' => 0,               // 1 = mẫu chi tiết từng tháng (như file theo dõi đạt được)
    ];
}

/**
 * Tập mã chỉ tiêu được HIỆN cho một sheet khi mẫu = custom.
 *   - Có cấu hình riêng cho khoa  -> dùng đúng cấu hình đó.
 *   - Toàn viện (id null)         -> hợp của tất cả khoa.
 *   - Chưa cấu hình               -> mặc định lấy bộ "chỉ tiêu chính".
 */
function bcgd_tap_hien(string $mau, array $cfg, ?int $idKhoa): array
{
    if ($mau !== 'custom') { return []; }
    $ck = $cfg['ct_khoa'] ?? [];
    if ($idKhoa !== null && isset($ck[(string)$idKhoa]) && is_array($ck[(string)$idKhoa])) {
        return $ck[(string)$idKhoa];
    }
    if ($idKhoa === null && $ck) {           // toàn viện = hợp của mọi khoa đã cấu hình
        $u = [];
        foreach ($ck as $arr) { if (is_array($arr)) { $u += $arr; } }
        if ($u) { return $u; }
    }
    return array_fill_keys(MA_CHI_TIEU_CHINH, 1);
}

/** Bù đủ khóa mặc định cho một cấu hình. */
/** Các cột TÙY CHỌN hiện/ẩn ở sheet Toàn viện (cột "Các tiêu chí" luôn hiện). */
function bcgd_cot_tv(): array
{
    return [
        'stt'       => 'STT',
        'dvt'       => 'Đơn vị tính',
        'tong_khoa' => 'Tổng khoa giao',
        'chi_tieu'  => 'Chỉ tiêu giao',
        'dat'       => 'Đạt (thực hiện)',
        'so_kh'     => 'So kế hoạch (%)',
    ];
}

/** [key => bool] cột nào HIỆN ở sheet Toàn viện. Mặc định hiện hết; tương thích
 *  tùy chọn cũ an_tong_khoa (ẩn riêng cột Tổng khoa giao). */
function bcgd_cot_tv_hien(array $cfg): array
{
    $keys = array_keys(bcgd_cot_tv());
    $out  = [];
    if (isset($cfg['tv_cot']) && is_array($cfg['tv_cot'])) {
        foreach ($keys as $k) { $out[$k] = !empty($cfg['tv_cot'][$k]); }
    } else {
        foreach ($keys as $k) { $out[$k] = true; }
        if (!empty($cfg['an_tong_khoa'])) { $out['tong_khoa'] = false; }
    }
    return $out;
}

function bao_cao_gd_chuan(array $cfg): array
{
    $m = array_merge(bao_cao_gd_mac_dinh(), $cfg);
    if (!is_array($m['cot'] ?? null)) { $m['cot'] = bao_cao_gd_mac_dinh()['cot']; }
    return $m;
}

/** Cấu hình đơn cũ (tương thích ngược). */
function bao_cao_gd_cau_hinh(): array
{
    $v = cai_dat_lay('baocao_gd');
    $d = $v ? json_decode($v, true) : null;
    return bao_cao_gd_chuan(is_array($d) ? $d : []);
}

/** Danh sách MẪU xuất đã lưu: mỗi mẫu ['id','ten','cfg'=>[...]]. */
function bao_cao_gd_ds(): array
{
    // Mẫu CỐ ĐỊNH luôn có: giống bản nộp Sở (khoa QĐ33b + toàn viện QĐ74, đủ chỉ tiêu).
    $giongSo = ['id' => 'giong_so', 'ten' => 'Giống bản Sở (QĐ33b khoa + QĐ74 toàn viện)',
        'cfg' => bao_cao_gd_chuan(['mau' => 'chi_tiet', 'chi_tiet_thang' => 0, 'theo_so' => 1,
                                   'ky' => 'nam', 'toan_vien' => 1,
                                   'so_qd' => 'Quyết định số 33b/QĐ-TTYT ngày 13/01/2026 của Trung tâm Y tế Nam Sách'])];
    $v = cai_dat_lay('baocao_gd_ds');
    $ds = $v ? json_decode($v, true) : null;
    if (is_array($ds) && $ds) {
        $saved = array_values(array_filter(array_map(fn($m) => [
            'id'  => (string)($m['id'] ?? uniqid('m')),
            'ten' => (string)($m['ten'] ?? 'Mẫu'),
            'cfg' => bao_cao_gd_chuan(is_array($m['cfg'] ?? null) ? $m['cfg'] : []),
        ], $ds), fn($m) => $m['id'] !== 'giong_so'));   // không nhân đôi mẫu cố định
        return array_merge([$giongSo], $saved);
    }
    // Lần đầu: mẫu Giống Sở + Giám đốc (chi tiết tháng) + Tổng gọn.
    return [
        $giongSo,
        ['id' => 'giam_doc', 'ten' => 'Báo cáo Giám đốc (chi tiết từng tháng)',
         'cfg' => bao_cao_gd_chuan(['chi_tiet_thang' => 1, 'mau' => 'chinh', 'ky' => 'nam', 'toan_vien' => 1])],
        ['id' => 'tong_gon', 'ten' => 'Báo cáo tổng (gọn, 1 dòng mỗi chỉ tiêu)',
         'cfg' => bao_cao_gd_chuan(['chi_tiet_thang' => 0, 'mau' => 'cha', 'ky' => 'nam', 'toan_vien' => 1])],
    ];
}

/** Lưu danh sách mẫu. */
function bao_cao_gd_luu_ds(array $ds): void
{
    $sach = [];
    foreach ($ds as $m) {
        if (!is_array($m) || trim((string)($m['ten'] ?? '')) === '') { continue; }
        $sach[] = [
            'id'  => (string)($m['id'] ?? uniqid('m')),
            'ten' => (string)$m['ten'],
            'cfg' => bao_cao_gd_chuan(is_array($m['cfg'] ?? null) ? $m['cfg'] : []),
        ];
    }
    cai_dat_dat('baocao_gd_ds', json_encode(array_values($sach), JSON_UNESCAPED_UNICODE));
}

function bao_cao_gd_thang(string $ky): array
{
    switch ($ky) {
        case 'nam':  return range(1, 12);
        case 'quy1': return [1, 2, 3];
        case 'quy2': return [4, 5, 6];
        case 'quy3': return [7, 8, 9];
        case 'quy4': return [10, 11, 12];
        default:     return range(1, 6);      // 6thang
    }
}
function bao_cao_gd_ten_ky(string $ky): string
{
    return ['nam'=>'cả năm','quy1'=>'quý I','quy2'=>'quý II','quy3'=>'quý III',
            'quy4'=>'quý IV','6thang'=>'6 tháng đầu'][$ky] ?? '6 tháng đầu';
}

function xuat_bao_cao_giam_doc(int $nam, array $cfg): void
{
    if (!empty($cfg['theo_so']))       { xuat_bao_cao_theo_so($nam, $cfg); return; }
    if (!empty($cfg['chi_tiet_thang'])) { xuat_bao_cao_gd_thang($nam, $cfg); return; }
    $cacThang = bao_cao_gd_thang($cfg['ky']);
    $cot   = $cfg['cot'];
    $boMa  = array_flip($cfg['bo_ma'] ?? []);
    $namTr = $nam - 1;
    $tenKy = bao_cao_gd_ten_ky($cfg['ky']);

    $dsKhoaAll = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');
    $chon = $cfg['khoa'] ?? 'all';
    $dsKhoa = ($chon === 'all' || !is_array($chon)) ? $dsKhoaAll
        : array_values(array_filter($dsKhoaAll,
            fn($k) => in_array((int)$k['id'], array_map('intval', $chon), true)));

    $sheets = [];
    foreach ($dsKhoa as $k) {
        $sheets[] = ['ten' => $k['ma'], 'tenDay' => chu_hoa($k['ten']), 'id' => (int)$k['id'],
                     'bang' => bang_theo_khoa($nam, $cacThang, (int)$k['id'], 'giao')];
    }
    if (!empty($cfg['toan_vien'])) {
        $sheets[] = ['ten' => 'TOAN VIEN', 'tenDay' => 'TOÀN VIỆN', 'id' => null,
                     'bang' => bang_toan_vien($nam, $cacThang, 'giao')];
    }

    // Các cột bật
    $colTH = !empty($cot['nam_truoc']);
    $colCT = !empty($cot['chi_tieu_truoc']);   // chỉ tiêu giao NĂM TRƯỚC
    $colKH = !empty($cot['chi_tieu']);
    $colKQ = !empty($cot['ket_qua']);
    $colPT = !empty($cot['pt']);
    $soCot = 3 + ($colTH?1:0) + ($colCT?1:0) + ($colKH?1:0) + ($colKQ?1:0) + ($colPT?1:0);

    $tenFile = 'bao-cao-giam-doc-' . $cfg['ky'] . "-{$nam}.xls";
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $tenFile . '"');
    header('Cache-Control: max-age=0');
    $x = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $vien = '<Borders>'
        . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>'
        . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>'
        . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>'
        . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/></Borders>';

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    ?>
<Styles>
  <Style ss:ID="td"><Font ss:Bold="1" ss:Size="13"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/></Style>
  <Style ss:ID="qd"><Font ss:Italic="1" ss:Size="10"/><Alignment ss:Horizontal="Center" ss:WrapText="1"/></Style>
  <Style ss:ID="dau"><Font ss:Bold="1" ss:Size="10"/><Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><?= $vien ?></Style>
  <Style ss:ID="tt"><Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
  <Style ss:ID="cha"><Font ss:Bold="1"/><?= $vien ?></Style>
  <Style ss:ID="con"><Alignment ss:Indent="2"/><Font ss:Color="#334155"/><?= $vien ?></Style>
  <Style ss:ID="dv"><Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
  <Style ss:ID="so"><NumberFormat ss:Format="#,##0"/><Alignment ss:Horizontal="Right"/><?= $vien ?></Style>
  <Style ss:ID="so1"><NumberFormat ss:Format="#,##0.0"/><Alignment ss:Horizontal="Right"/><?= $vien ?></Style>
  <Style ss:ID="pt"><NumberFormat ss:Format="0.0&quot;%&quot;"/><Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
</Styles>
<?php
    $mau = $cfg['mau'] ?? 'chi_tiet';
    foreach ($sheets as $sh):
        // ----- Sheet TOÀN VIỆN theo khuôn QĐ74 (16 tiêu chí chính, đơn vị chuẩn của Sở) -----
        if ($sh['id'] === null):
            $tv = [];
            foreach ($sh['bang'] as $dd) { $tv[$dd['ct']['ma']] = $dd; }
            $qd74 = [
                ['GB',     'Giường bệnh nội trú kế hoạch',                              'Giường', 0],
                ['NDT',    'Tổng số ngày điều trị của bệnh nhân nội trú',               'Ngày',   0],
                ['NT',     'Tổng số bệnh nhân điều trị nội trú',                        'Người',  0],
                ['CSGB',   'Công suất giường bệnh',                                     '%',      0],
                ['NDT_TB', 'Số ngày điều trị nội trú trung bình của bệnh nhân nội trú', 'Ngày',   0],
                ['KB',     'Tổng số lượt khám bệnh',                                    'Lượt',   0],
                ['XN',     'Tổng số các xét nghiệm',                                    '',       0],
                ['TONG_SO_XN_HUYET_HOC', 'Huyết học',                                   'Lần',    1],
                ['TONG_SO_XN_HOA_SINH',  'Hóa sinh',                                    'Lần',    1],
                ['TONG_SO_XN_VSV_KST',   'Vi sinh vật',                                 'Lần',    1],
                ['XQ',     'Tổng số chụp X Quang',                                      'Lần',    0],
                ['CT',     'Tổng số chụp CT-Scanner',                                   'Lần',    0],
                ['MRI',    'Tổng số chụp MRI',                                          'Lần',    0],
                ['SA',     'Tổng số siêu âm chẩn đoán và điều trị',                     'Lần',    0],
                ['NS',     'Tổng số nội soi chẩn đoán và can thiệp',                    'Lần',    0],
                ['PT',     'Tổng số phẫu thuật',                                        'Ca',     0],
                ['TT',     'Tổng số thủ thuật',                                         'Ca',     0],
                ['NCKH',   'Tổng số đề tài nghiên cứu',                                 'Đề tài', 0],
            ];
?>
<Worksheet ss:Name="TOAN VIEN">
<Table>
  <Column ss:Width="34"/><Column ss:Width="330"/><Column ss:Width="64"/>
  <Column ss:Width="94"/><Column ss:Width="82"/><Column ss:Width="72"/>
  <Row ss:Height="32"><Cell ss:StyleID="td" ss:MergeAcross="5">
    <Data ss:Type="String">CHỈ TIÊU KẾ HOẠCH CHUYÊN MÔN — TRUNG TÂM Y TẾ NAM SÁCH NĂM <?= $nam ?></Data></Cell></Row>
  <?php if (trim((string)($cfg['so_qd'] ?? '')) !== ''): ?>
  <Row><Cell ss:StyleID="qd" ss:MergeAcross="5"><Data ss:Type="String">(Kèm theo <?= $x($cfg['so_qd']) ?>)</Data></Cell></Row>
  <?php endif; ?>
  <Row><Cell ss:StyleID="qd" ss:MergeAcross="5"><Data ss:Type="String">Kết quả đạt được <?= $x($tenKy) ?> năm <?= $nam ?></Data></Cell></Row>
  <Row/>
  <Row ss:Height="30">
    <Cell ss:StyleID="dau"><Data ss:Type="String">STT</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">CÁC TIÊU CHÍ HOẠT ĐỘNG</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">ĐƠN VỊ TÍNH</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">CHỈ TIÊU GIAO KẾ HOẠCH</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">ĐẠT</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">SO KẾ HOẠCH</Data></Cell>
  </Row>
  <?php $stt74 = 0; foreach ($qd74 as [$ma74, $ten74, $dv74, $cap74]):
      $dRow = $ma74 !== null ? ($tv[$ma74] ?? null) : null;
      $le74 = $dRow && in_array($dRow['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true);
      $k74  = $le74 ? 'so1' : 'so';
      if (!$cap74) { $stt74++; } ?>
    <Row>
      <Cell ss:StyleID="tt"><Data ss:Type="String"><?= $cap74 ? '' : $stt74 ?></Data></Cell>
      <Cell ss:StyleID="<?= $cap74 ? 'con' : 'cha' ?>"><Data ss:Type="String"><?= $x($ten74) ?></Data></Cell>
      <Cell ss:StyleID="dv"><Data ss:Type="String"><?= $x($dv74) ?></Data></Cell>
      <?php if ($dRow && $dRow['chi_tieu_nam'] !== null): ?><Cell ss:StyleID="<?= $k74 ?>"><Data ss:Type="Number"><?= gd_so_o($dRow['chi_tieu_nam'], $k74) ?></Data></Cell><?php else: ?><Cell ss:StyleID="<?= $k74 ?>"/><?php endif; ?>
      <?php if ($dRow && $dRow['thuc_hien'] !== null): ?><Cell ss:StyleID="<?= $k74 ?>"><Data ss:Type="Number"><?= gd_so_o($dRow['thuc_hien'], $k74) ?></Data></Cell><?php else: ?><Cell ss:StyleID="<?= $k74 ?>"/><?php endif; ?>
      <?php if ($dRow && $dRow['pt_nam'] !== null): ?><Cell ss:StyleID="pt"><Data ss:Type="Number"><?= gd_so_o($dRow['pt_nam'], 'pt') ?></Data></Cell><?php else: ?><Cell ss:StyleID="pt"/><?php endif; ?>
    </Row>
  <?php endforeach; ?>
</Table>
</Worksheet>
<?php
            continue;
        endif;
        // Lọc theo mẫu: cha = chỉ nội dung lớn; chi_tiet = tất cả; custom = theo tick
        $tapHien = bcgd_tap_hien($mau, $cfg, $sh['id']);
        $rows = array_values(array_filter($sh['bang'], function ($d) use ($mau, $tapHien) {
            if ($mau === 'cha')      { return $d['cap'] === 0; }
            if ($mau === 'chi_tiet') { return true; }
            if ($mau === 'chinh')    { return in_array($d['ct']['ma'], MA_CHI_TIEU_CHINH, true); }
            return isset($tapHien[$d['ct']['ma']]);   // custom theo khoa
        }));
?>
<Worksheet ss:Name="<?= $x($sh['ten']) ?>">
<Table>
  <Column ss:Width="30"/><Column ss:Width="300"/><Column ss:Width="46"/>
  <?php if ($colTH): ?><Column ss:Width="70"/><?php endif; ?>
  <?php if ($colCT): ?><Column ss:Width="80"/><?php endif; ?>
  <?php if ($colKH): ?><Column ss:Width="80"/><?php endif; ?>
  <?php if ($colKQ): ?><Column ss:Width="80"/><?php endif; ?>
  <?php if ($colPT): ?><Column ss:Width="62"/><?php endif; ?>
  <Row ss:Height="34"><Cell ss:StyleID="td" ss:MergeAcross="<?= $soCot - 1 ?>">
    <Data ss:Type="String">GIAO CHỈ TIÊU KẾ HOẠCH CHUYÊN MÔN <?= $x($sh['tenDay']) ?> NĂM <?= $nam ?></Data></Cell></Row>
  <?php if (trim((string)($cfg['so_qd'] ?? '')) !== ''): ?>
  <Row><Cell ss:StyleID="qd" ss:MergeAcross="<?= $soCot - 1 ?>">
    <Data ss:Type="String">(Kèm theo <?= $x($cfg['so_qd']) ?>)</Data></Cell></Row>
  <?php endif; ?>
  <Row><Cell ss:StyleID="qd" ss:MergeAcross="<?= $soCot - 1 ?>">
    <Data ss:Type="String">Kết quả đạt được <?= $x($tenKy) ?> năm <?= $nam ?> · kết xuất <?= date('d/m/Y H:i') ?></Data></Cell></Row>
  <Row/>
  <Row ss:Height="30">
    <Cell ss:StyleID="dau"><Data ss:Type="String">TT</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Nội dung</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">ĐVT</Data></Cell>
    <?php if ($colTH): ?><Cell ss:StyleID="dau"><Data ss:Type="String">TH năm <?= $namTr ?></Data></Cell><?php endif; ?>
    <?php if ($colCT): ?><Cell ss:StyleID="dau"><Data ss:Type="String">Chỉ tiêu <?= $namTr ?></Data></Cell><?php endif; ?>
    <?php if ($colKH): ?><Cell ss:StyleID="dau"><Data ss:Type="String">Chỉ tiêu giao <?= $nam ?></Data></Cell><?php endif; ?>
    <?php if ($colKQ): ?><Cell ss:StyleID="dau"><Data ss:Type="String">Kết quả đạt được</Data></Cell><?php endif; ?>
    <?php if ($colPT): ?><Cell ss:StyleID="dau"><Data ss:Type="String">So KH (%)</Data></Cell><?php endif; ?>
  </Row>
<?php
        $stt = 0;
        foreach ($rows as $d):
            $le  = in_array($d['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true);
            $kSo = $le ? 'so1' : 'so';
            $laCon = $d['cap'] !== 0;
            if (!$laCon) { $stt++; }
?>
  <Row>
    <Cell ss:StyleID="tt"><Data ss:Type="String"><?= $laCon ? '' : $stt ?></Data></Cell>
    <Cell ss:StyleID="<?= $laCon ? 'con' : 'cha' ?>"><Data ss:Type="String"><?= $x($d['ct']['ten']) ?></Data></Cell>
    <Cell ss:StyleID="dv"><Data ss:Type="String"><?= $x($d['ct']['don_vi']) ?></Data></Cell>
    <?php if ($colTH): ?>
      <?php if ($d['nam_truoc'] !== null): ?><Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= gd_so_o($d['nam_truoc'], $kSo) ?></Data></Cell>
      <?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?>
    <?php endif; ?>
    <?php if ($colCT): ?>
      <?php if (($d['chi_tieu_nam_truoc'] ?? null) !== null): ?><Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= gd_so_o($d['chi_tieu_nam_truoc'], $kSo) ?></Data></Cell>
      <?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?>
    <?php endif; ?>
    <?php if ($colKH): ?>
      <?php if ($d['chi_tieu_nam'] !== null): ?><Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= gd_so_o($d['chi_tieu_nam'], $kSo) ?></Data></Cell>
      <?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?>
    <?php endif; ?>
    <?php if ($colKQ): ?>
      <?php if ($d['thuc_hien'] !== null): ?><Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= gd_so_o($d['thuc_hien'], $kSo) ?></Data></Cell>
      <?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?>
    <?php endif; ?>
    <?php if ($colPT): ?>
      <?php if ($d['pt_nam'] !== null): ?><Cell ss:StyleID="pt"><Data ss:Type="Number"><?= gd_so_o($d['pt_nam'], 'pt') ?></Data></Cell>
      <?php else: ?><Cell ss:StyleID="pt"/><?php endif; ?>
    <?php endif; ?>
  </Row>
<?php endforeach; ?>
</Table>
</Worksheet>
<?php endforeach; ?>
</Workbook>
<?php
    ghi_nhat_ky('XUAT_BAO_CAO_GD', $cfg['ky'], "$nam · " . count($sheets) . ' sheet');
}

/**
 * MẪU GIỐNG BẢN SỞ ("theo_so"): mỗi sheet khoa dựng y khuôn QĐ33b của Sở
 * (lấy template cố định trong khuon_so_khoa.php), + sheet Toàn viện khuôn QĐ74.
 * Số nào app có (theo mã) thì điền; dòng khoa chưa có (vd khối XN/CĐHA của
 * khoa lâm sàng) để trống cho người vận hành nhập tay.
 * Cột: TT · Nội dung · ĐVT · [Năm {t}: Chỉ tiêu | Thực hiện] · Chỉ tiêu giao {n} · Kết quả · So KH(%).
 */
function xuat_bao_cao_theo_so(int $nam, array $cfg): void
{
    require_once __DIR__ . '/khuon_so_khoa.php';
    $cacThang = bao_cao_gd_thang($cfg['ky']);
    $namTr    = $nam - 1;
    $soQd     = trim((string)($cfg['so_qd'] ?? ''));

    $khoaMa = [];
    foreach (qAll('SELECT * FROM khoa WHERE hoat_dong = 1') as $k) { $khoaMa[$k['ma']] = $k; }

    $tenFile = 'bao-cao-giam-doc-' . $nam . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $tenFile . '"');
    header('Cache-Control: max-age=0');
    $x = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $vien = '<Borders>'
        . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>'
        . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>'
        . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>'
        . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/></Borders>';
    // Ô số: trả Cell có số, hoặc Cell rỗng (để nhập tay).
    $oSo = function ($v, string $kSo) {
        if ($v === null || $v === '') { return '<Cell ss:StyleID="' . $kSo . '"/>'; }
        return '<Cell ss:StyleID="' . $kSo . '"><Data ss:Type="Number">' . gd_so_o($v, $kSo) . '</Data></Cell>';
    };

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    ?>
<Styles>
  <Style ss:ID="td"><Font ss:Bold="1" ss:Size="12"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/></Style>
  <Style ss:ID="qd"><Font ss:Italic="1" ss:Size="10"/><Alignment ss:Horizontal="Center" ss:WrapText="1"/></Style>
  <Style ss:ID="trai"><Font ss:Bold="1" ss:Size="10"/><Alignment ss:Horizontal="Left" ss:WrapText="1"/></Style>
  <Style ss:ID="trai2"><Font ss:Size="10"/><Alignment ss:Horizontal="Left" ss:WrapText="1"/></Style>
  <Style ss:ID="dau"><Font ss:Bold="1" ss:Size="10"/><Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><?= $vien ?></Style>
  <Style ss:ID="tt"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><?= $vien ?></Style>
  <Style ss:ID="ten"><Alignment ss:Vertical="Center" ss:WrapText="1"/><?= $vien ?></Style>
  <Style ss:ID="con"><Alignment ss:Indent="2" ss:Vertical="Center" ss:WrapText="1"/><Font ss:Italic="1" ss:Color="#334155"/><?= $vien ?></Style>
  <Style ss:ID="dv"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><?= $vien ?></Style>
  <Style ss:ID="so"><NumberFormat ss:Format="#,##0"/><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><?= $vien ?></Style>
  <Style ss:ID="so1"><NumberFormat ss:Format="#,##0.0"/><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><?= $vien ?></Style>
  <Style ss:ID="pt"><NumberFormat ss:Format="0.0&quot;%&quot;"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><?= $vien ?></Style>
</Styles>
<?php
    // ===================== SHEET TỪNG KHOA (QĐ33b) =====================
    $soSheet = 0;
    $khuonMap = khuon_map();   // nối nguồn RIÊNG (cấu hình ở trang Xem trước khuôn)
    foreach (KHUON_SO_META as $ma => $meta):
        [$tab, $tieuDe] = $meta;
        $k    = $khoaMa[$ma] ?? null;
        $rows = KHUON_SO_KHOA[$ma] ?? [];
        $idx  = [];
        if ($k) {
            foreach (bang_theo_khoa($nam, $cacThang, (int)$k['id'], 'giao') as $d) {
                $idx[$d['ct']['ma']] = $d;
            }
        }
        $soSheet++;
?>
<Worksheet ss:Name="<?= $x($tab) ?>">
<Table>
  <Column ss:Width="34"/><Column ss:Width="320"/><Column ss:Width="60"/>
  <Column ss:Width="78"/><Column ss:Width="78"/><Column ss:Width="92"/><Column ss:Width="86"/><Column ss:Width="64"/>
  <Row ss:Height="40"><Cell ss:StyleID="td" ss:MergeAcross="7"><Data ss:Type="String"><?= $x($tieuDe) ?> NĂM <?= $nam ?></Data></Cell></Row>
  <?php if ($soQd !== ''): ?>
  <Row><Cell ss:StyleID="qd" ss:MergeAcross="7"><Data ss:Type="String">(Kèm theo <?= $x($soQd) ?>)</Data></Cell></Row>
  <?php endif; ?>
  <Row/>
  <Row ss:Height="28">
    <Cell ss:StyleID="dau" ss:MergeDown="1"><Data ss:Type="String">TT</Data></Cell>
    <Cell ss:StyleID="dau" ss:MergeDown="1"><Data ss:Type="String">Nội dung</Data></Cell>
    <Cell ss:StyleID="dau" ss:MergeDown="1"><Data ss:Type="String">Đơn vị tính</Data></Cell>
    <Cell ss:StyleID="dau" ss:MergeAcross="1"><Data ss:Type="String">Năm <?= $namTr ?></Data></Cell>
    <Cell ss:StyleID="dau" ss:MergeDown="1"><Data ss:Type="String">Chỉ tiêu giao năm <?= $nam ?></Data></Cell>
    <Cell ss:StyleID="dau" ss:MergeDown="1"><Data ss:Type="String">Kết quả đạt được</Data></Cell>
    <Cell ss:StyleID="dau" ss:MergeDown="1"><Data ss:Type="String">So KH(%)</Data></Cell>
  </Row>
  <Row ss:Height="18">
    <Cell ss:Index="4" ss:StyleID="dau"><Data ss:Type="String">Chỉ tiêu</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Thực hiện</Data></Cell>
  </Row>
<?php
        foreach ($rows as $ri => [$stt, $ten, $dvt, $cap, $maCt]):
            $maEff = khuon_ma_hieu_dung($khuonMap, $ma, $ri, $maCt);   // override hoặc mặc định
            $d   = gd_dong_khuon($maEff, $idx);
            $le  = $d && in_array($d['ct']['loai_gia_tri'] ?? 'DEM', ['TY_LE', 'TRUNG_BINH'], true);
            $kSo = $le ? 'so1' : 'so';
?>
  <Row>
    <Cell ss:StyleID="tt"><Data ss:Type="String"><?= $x($stt) ?></Data></Cell>
    <Cell ss:StyleID="<?= $cap ? 'con' : 'ten' ?>"><Data ss:Type="String"><?= $x($ten) ?></Data></Cell>
    <Cell ss:StyleID="dv"><Data ss:Type="String"><?= $x($dvt) ?></Data></Cell>
    <?= $oSo($d['chi_tieu_nam_truoc'] ?? null, $kSo) ?>
    <?= $oSo($d['nam_truoc'] ?? null, $kSo) ?>
    <?= $oSo($d['chi_tieu_nam'] ?? null, $kSo) ?>
    <?= $oSo($d['thuc_hien'] ?? null, $kSo) ?>
    <?= $oSo($d['pt_nam'] ?? null, 'pt') ?>
  </Row>
<?php endforeach; ?>
</Table>
</Worksheet>
<?php endforeach; ?>
<?php
    // ===================== SHEET TOÀN VIỆN (QĐ74) =====================
    $tv = [];
    foreach (bang_toan_vien($nam, $cacThang, 'giao') as $d) { $tv[$d['ct']['ma']] = $d; }
    // Khuôn 16 tiêu chí QĐ74 (đúng thứ tự bản Sở). [stt, tên, đvt, cấp, mã|null]
    $qd74 = [
        ['1',  'Giường bệnh nội trú kế hoạch',                              'Giường', 0, 'GB'],
        ['2',  'Tổng số ngày điều trị của bệnh nhân nội trú',               'Ngày',   0, 'NDT'],
        ['3',  'Tổng số bệnh nhân điều trị nội trú',                        'Người',  0, 'NT'],
        ['4',  'Công suất giường bệnh',                                     '%',      0, 'CSGB'],
        ['5',  'Số ngày điều trị nội trú trung bình của bệnh nhân nội trú', 'Ngày',   0, 'NDT_TB'],
        ['6',  'Tổng số lượt khám bệnh',                                    'Lượt',   0, 'KB'],
        ['7',  'Tổng số các xét nghiệm',                                    '',       0, 'XN'],
        ['',   'Huyết học',                                                 'Lần',    1, 'TONG_SO_XN_HUYET_HOC'],
        ['',   'Hóa sinh',                                                  'Lần',    1, 'TONG_SO_XN_HOA_SINH'],
        ['',   'Vi sinh vật',                                               'Lần',    1, 'TONG_SO_XN_VSV_KST'],
        ['8',  'Tổng số xét nghiệm về giải phẫu bệnh lý',                   'Lần',    0, null],
        ['9',  'Tổng số chụp X Quang',                                      'Lần',    0, 'XQ'],
        ['10', 'Tổng số chụp CT- Scanner',                                  'Lần',    0, 'CT'],
        ['11', 'Tổng số chụp MRI',                                          'Lần',    0, 'MRI'],
        ['12', 'Tổng số siêu âm chẩn đoán và điều trị',                     'Lần',    0, 'SA'],
        ['13', 'Tổng số nội soi chẩn đoán và can thiệp',                    'Lần',    0, 'NS'],
        ['14', 'Tổng số phẫu thuật',                                        'Ca',     0, 'PT'],
        ['',   'Đặc biệt',                                                  'Ca',     1, 'PT_DB'],
        ['',   'Loại I',                                                    'Ca',     1, 'PT_L1'],
        ['',   'Loại II',                                                   'Ca',     1, 'PT_L2'],
        ['',   'Loại III',                                                  'Ca',     1, 'PT_L3'],
        ['15', 'Tổng số thủ thuật',                                        'Ca',     0, 'TT'],
        ['16', 'Tổng số đề tài nghiên cứu',                                 'Đề tài', 0, 'NCKH'],
    ];
    $soSheet++;
    // Sheet "toàn viện" theo khuôn QĐ74 của Sở — số quyết định cố định, KHÔNG dùng
    // chung với dòng "(Kèm theo …)" ở sheet khoa (đó là QĐ33b của Trung tâm).
    $soSoQd = '74/QĐ-SYT';
    // Cột sheet Toàn viện — dựng theo danh sách, ẩn cột nào thì bỏ hẳn cột đó.
    // 'ten' (Các tiêu chí) luôn hiện; nhóm 'trai' = nhãn, 'phai' = số (để gộp tiêu đề đúng).
    $hienTV = bcgd_cot_tv_hien($cfg);
    $cotTV  = [
        'stt'       => ['w' => 34,  'nhan' => 'STT',                    'nhom' => 'trai'],
        'ten'       => ['w' => 330, 'nhan' => 'CÁC TIÊU CHÍ HOẠT ĐỘNG', 'nhom' => 'trai'],
        'dvt'       => ['w' => 64,  'nhan' => 'ĐƠN VỊ TÍNH',            'nhom' => 'trai'],
        'tong_khoa' => ['w' => 92,  'nhan' => 'TỔNG KHOA GIAO',         'nhom' => 'phai'],
        'chi_tieu'  => ['w' => 94,  'nhan' => 'CHỈ TIÊU GIAO KẾ HOẠCH', 'nhom' => 'phai'],
        'dat'       => ['w' => 82,  'nhan' => 'ĐẠT',                    'nhom' => 'phai'],
        'so_kh'     => ['w' => 72,  'nhan' => 'SO KẾ HOẠCH',            'nhom' => 'phai'],
    ];
    $cotHienTV = array_filter($cotTV, fn($c, $k) => $k === 'ten' || !empty($hienTV[$k]), ARRAY_FILTER_USE_BOTH);
    $nTrai = count(array_filter($cotHienTV, fn($c) => $c['nhom'] === 'trai'));
    $nPhai = count(array_filter($cotHienTV, fn($c) => $c['nhom'] === 'phai'));
?>
<Worksheet ss:Name="toàn viện">
<Table>
  <?php foreach ($cotHienTV as $c): ?><Column ss:Width="<?= $c['w'] ?>"/><?php endforeach; ?>
  <Row><Cell ss:StyleID="trai" ss:MergeAcross="<?= max(0, $nTrai - 1) ?>"><Data ss:Type="String">SỞ Y TẾ THÀNH PHỐ HẢI PHÒNG</Data></Cell>
    <?php if ($nPhai > 0): ?><Cell ss:StyleID="td" ss:MergeAcross="<?= $nPhai - 1 ?>"><Data ss:Type="String">CHỈ TIÊU KẾ HOẠCH CHUYÊN MÔN</Data></Cell><?php endif; ?></Row>
  <Row><Cell ss:StyleID="trai2" ss:MergeAcross="<?= max(0, $nTrai - 1) ?>"><Data ss:Type="String">Phụ lục kèm theo Quyết định</Data></Cell>
    <?php if ($nPhai > 0): ?><Cell ss:StyleID="td" ss:MergeAcross="<?= $nPhai - 1 ?>"><Data ss:Type="String">TRUNG TÂM Y TẾ NAM SÁCH NĂM <?= $nam ?></Data></Cell><?php endif; ?></Row>
  <Row><Cell ss:StyleID="trai2" ss:MergeAcross="<?= max(0, $nTrai - 1) ?>"><Data ss:Type="String">Số: <?= $x($soSoQd) ?></Data></Cell></Row>
  <Row/>
  <Row ss:Height="30">
    <?php foreach ($cotHienTV as $c): ?><Cell ss:StyleID="dau"><Data ss:Type="String"><?= $x($c['nhan']) ?></Data></Cell><?php endforeach; ?>
  </Row>
<?php
        foreach ($qd74 as [$stt, $ten, $dvt, $cap, $maCt]):
            $d   = ($maCt !== null) ? ($tv[$maCt] ?? null) : null;
            $le  = $d && in_array($d['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true);
            $kSo = $le ? 'so1' : 'so';
            $oCot = [
                'stt'       => '<Cell ss:StyleID="tt"><Data ss:Type="String">' . $x($stt) . '</Data></Cell>',
                'ten'       => '<Cell ss:StyleID="' . ($cap ? 'con' : 'ten') . '"><Data ss:Type="String">' . $x($ten) . '</Data></Cell>',
                'dvt'       => '<Cell ss:StyleID="dv"><Data ss:Type="String">' . $x($dvt) . '</Data></Cell>',
                'tong_khoa' => $oSo($d['may_cong_kh'] ?? null, $kSo),
                'chi_tieu'  => $oSo($d['chi_tieu_nam'] ?? null, $kSo),
                'dat'       => $oSo($d['thuc_hien'] ?? null, $kSo),
                'so_kh'     => $oSo($d['pt_nam'] ?? null, 'pt'),
            ];
?>
  <Row>
    <?php foreach (array_keys($cotHienTV) as $k) { echo $oCot[$k]; } ?>
  </Row>
<?php endforeach; ?>
</Table>
</Worksheet>
</Workbook>
<?php
    ghi_nhat_ky('XUAT_BAO_CAO_GD', 'theo_so', "$nam · " . $soSheet . ' sheet');
}

/**
 * Mẫu CHI TIẾT TỪNG THÁNG — giống file "Theo dõi chỉ tiêu các khoa đạt được".
 * Mỗi khoa 1 sheet, cột: TT · Nội dung · ĐVT · [TH năm trước] · [Chỉ tiêu giao] ·
 * T1 T2 T3 · Quý I · T4 T5 T6 · 6 tháng · T7 T8 T9 · 9 tháng · T10 T11 T12 · Cả năm.
 */
function xuat_bao_cao_gd_thang(int $nam, array $cfg): void
{
    $full  = range(1, 12);
    $cot   = $cfg['cot'];
    $boMa  = array_flip($cfg['bo_ma'] ?? []);
    $namTr = $nam - 1;
    $mau   = $cfg['mau'] ?? 'chi_tiet';
    $colTH = !empty($cot['nam_truoc']);
    $colKH = !empty($cot['chi_tieu']);

    $dsKhoaAll = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');
    $chon = $cfg['khoa'] ?? 'all';
    $dsKhoa = ($chon === 'all' || !is_array($chon)) ? $dsKhoaAll
        : array_values(array_filter($dsKhoaAll,
            fn($k) => in_array((int)$k['id'], array_map('intval', $chon), true)));

    $sheets = [];
    foreach ($dsKhoa as $k) {
        $sheets[] = ['ten' => $k['ma'], 'tenDay' => chu_hoa($k['ten']), 'id' => (int)$k['id'],
                     'bang' => bang_theo_khoa($nam, $full, (int)$k['id'], 'giao')];
    }
    if (!empty($cfg['toan_vien'])) {
        $sheets[] = ['ten' => 'TOAN VIEN', 'tenDay' => 'TOÀN VIỆN', 'id' => null,
                     'bang' => bang_toan_vien($nam, $full, 'giao')];
    }

    // Thứ tự 16 cột tháng/lũy kế: [loại,'m'|'c', chỉ số]
    $seq = [['m',1],['m',2],['m',3],['c',0],['m',4],['m',5],['m',6],['c',1],
            ['m',7],['m',8],['m',9],['c',2],['m',10],['m',11],['m',12],['c',3]];
    $nhanCot = ['Tháng 1','Tháng 2','Tháng 3','Quý I','Tháng 4','Tháng 5','Tháng 6','6 tháng',
                'Tháng 7','Tháng 8','Tháng 9','9 tháng','Tháng 10','Tháng 11','Tháng 12','Cả năm'];
    $soCot = 3 + ($colTH?1:0) + ($colKH?1:0) + 16;

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bao-cao-chi-tiet-thang-' . $nam . '.xls"');
    header('Cache-Control: max-age=0');
    $x = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $vien = '<Borders>'
        . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>'
        . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>'
        . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>'
        . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/></Borders>';

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    ?>
<Styles>
  <Style ss:ID="td"><Font ss:Bold="1" ss:Size="13"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/></Style>
  <Style ss:ID="qd"><Font ss:Italic="1" ss:Size="10"/><Alignment ss:Horizontal="Center" ss:WrapText="1"/></Style>
  <Style ss:ID="dau"><Font ss:Bold="1" ss:Size="9"/><Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><?= $vien ?></Style>
  <Style ss:ID="dauLK"><Font ss:Bold="1" ss:Size="9"/><Interior ss:Color="#FDE68A" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><?= $vien ?></Style>
  <Style ss:ID="tt"><Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
  <Style ss:ID="cha"><Font ss:Bold="1"/><?= $vien ?></Style>
  <Style ss:ID="con"><Alignment ss:Indent="1"/><Font ss:Color="#334155"/><?= $vien ?></Style>
  <Style ss:ID="dv"><Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
  <Style ss:ID="so"><NumberFormat ss:Format="#,##0"/><Alignment ss:Horizontal="Right"/><?= $vien ?></Style>
  <Style ss:ID="so1"><NumberFormat ss:Format="#,##0.0"/><Alignment ss:Horizontal="Right"/><?= $vien ?></Style>
  <Style ss:ID="lk"><NumberFormat ss:Format="#,##0"/><Font ss:Bold="1"/><Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/><Alignment ss:Horizontal="Right"/><?= $vien ?></Style>
  <Style ss:ID="lk1"><NumberFormat ss:Format="#,##0.0"/><Font ss:Bold="1"/><Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/><Alignment ss:Horizontal="Right"/><?= $vien ?></Style>
</Styles>
<?php
    foreach ($sheets as $sh):
        $tapHien = bcgd_tap_hien($mau, $cfg, $sh['id']);
        $rows = array_values(array_filter($sh['bang'], function ($d) use ($mau, $tapHien) {
            if ($mau === 'cha')      { return $d['cap'] === 0; }
            if ($mau === 'chi_tiet') { return true; }
            if ($mau === 'chinh')    { return in_array($d['ct']['ma'], MA_CHI_TIEU_CHINH, true); }
            return isset($tapHien[$d['ct']['ma']]);   // custom theo khoa
        }));
        $idK = $sh['id'];

        // Tính giá trị từng tháng + lũy kế cho từng chỉ tiêu của sheet này
        $val = [];
        if ($idK !== null) {
            foreach ($rows as $d) {
                $cid = (int)$d['ct']['id']; $m = [];
                for ($t = 1; $t <= 12; $t++) { $m[$t] = gia_tri_thang($nam, $t, $idK, $cid); }
                $val[$d['ct']['ma']] = ['m' => $m, 'c' => [
                    gia_tri_luy_ke($nam, [1,2,3], $idK, $cid),
                    gia_tri_luy_ke($nam, [1,2,3,4,5,6], $idK, $cid),
                    gia_tri_luy_ke($nam, range(1,9), $idK, $cid),
                    gia_tri_luy_ke($nam, range(1,12), $idK, $cid)]];
            }
        } else { // TOÀN VIỆN: tổng hợp theo tháng + lũy kế
            $mm = [];
            for ($t = 1; $t <= 12; $t++) { $mm[$t] = [];
                foreach (bang_toan_vien($nam, [$t], 'giao') as $r) { $mm[$t][$r['ct']['ma']] = $r['thuc_hien']; } }
            $cc = [];
            foreach ([[1,2,3],[1,2,3,4,5,6],range(1,9),range(1,12)] as $i => $rng) { $cc[$i] = [];
                foreach (bang_toan_vien($nam, $rng, 'giao') as $r) { $cc[$i][$r['ct']['ma']] = $r['thuc_hien']; } }
            foreach ($rows as $d) { $ma = $d['ct']['ma']; $m = [];
                for ($t = 1; $t <= 12; $t++) { $m[$t] = $mm[$t][$ma] ?? null; }
                $val[$ma] = ['m' => $m, 'c' => [$cc[0][$ma]??null,$cc[1][$ma]??null,$cc[2][$ma]??null,$cc[3][$ma]??null]]; }
        }
?>
<Worksheet ss:Name="<?= $x($sh['ten']) ?>">
<Table>
  <Column ss:Width="28"/><Column ss:Width="250"/><Column ss:Width="40"/>
  <?php if ($colTH): ?><Column ss:Width="60"/><?php endif; ?>
  <?php if ($colKH): ?><Column ss:Width="66"/><?php endif; ?>
  <?php for ($i = 0; $i < 16; $i++): ?><Column ss:Width="<?= in_array($i,[3,7,11,15],true)?54:44 ?>"/><?php endfor; ?>
  <Row ss:Height="32"><Cell ss:StyleID="td" ss:MergeAcross="<?= $soCot - 1 ?>">
    <Data ss:Type="String">THEO DÕI CHỈ TIÊU <?= $x($sh['tenDay']) ?> — ĐẠT ĐƯỢC NĂM <?= $nam ?></Data></Cell></Row>
  <?php if (trim((string)($cfg['so_qd'] ?? '')) !== ''): ?>
  <Row><Cell ss:StyleID="qd" ss:MergeAcross="<?= $soCot - 1 ?>"><Data ss:Type="String">(Kèm theo <?= $x($cfg['so_qd']) ?>)</Data></Cell></Row>
  <?php endif; ?>
  <Row><Cell ss:StyleID="qd" ss:MergeAcross="<?= $soCot - 1 ?>"><Data ss:Type="String">Kết xuất <?= date('d/m/Y H:i') ?></Data></Cell></Row>
  <Row/>
  <Row ss:Height="26">
    <Cell ss:StyleID="dau"><Data ss:Type="String">TT</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Nội dung</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">ĐVT</Data></Cell>
    <?php if ($colTH): ?><Cell ss:StyleID="dau"><Data ss:Type="String">TH <?= $namTr ?></Data></Cell><?php endif; ?>
    <?php if ($colKH): ?><Cell ss:StyleID="dau"><Data ss:Type="String">Giao <?= $nam ?></Data></Cell><?php endif; ?>
    <?php foreach ($nhanCot as $i => $nh): ?>
      <Cell ss:StyleID="<?= in_array($i,[3,7,11,15],true)?'dauLK':'dau' ?>"><Data ss:Type="String"><?= $x($nh) ?></Data></Cell>
    <?php endforeach; ?>
  </Row>
<?php
        $stt = 0;
        foreach ($rows as $d):
            $le  = in_array($d['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true);
            $kSo = $le ? 'so1' : 'so';
            $kLk = $le ? 'lk1' : 'lk';
            $laCon = $d['cap'] !== 0;
            if (!$laCon) { $stt++; }
            $v = $val[$d['ct']['ma']] ?? ['m'=>[], 'c'=>[]];
?>
  <Row>
    <Cell ss:StyleID="tt"><Data ss:Type="String"><?= $laCon ? '' : $stt ?></Data></Cell>
    <Cell ss:StyleID="<?= $laCon ? 'con' : 'cha' ?>"><Data ss:Type="String"><?= $x($d['ct']['ten']) ?></Data></Cell>
    <Cell ss:StyleID="dv"><Data ss:Type="String"><?= $x($d['ct']['don_vi']) ?></Data></Cell>
    <?php if ($colTH): ?><?php if ($d['nam_truoc'] !== null): ?><Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= gd_so_o($d['nam_truoc'], $kSo) ?></Data></Cell><?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?><?php endif; ?>
    <?php if ($colKH): ?><?php if ($d['chi_tieu_nam'] !== null): ?><Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= gd_so_o($d['chi_tieu_nam'], $kSo) ?></Data></Cell><?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?><?php endif; ?>
    <?php foreach ($seq as $sp):
        $gt = $sp[0] === 'm' ? ($v['m'][$sp[1]] ?? null) : ($v['c'][$sp[1]] ?? null);
        $st = $sp[0] === 'm' ? $kSo : $kLk; ?>
      <?php if ($gt !== null): ?><Cell ss:StyleID="<?= $st ?>"><Data ss:Type="Number"><?= gd_so_o($gt, $st) ?></Data></Cell><?php else: ?><Cell ss:StyleID="<?= $st ?>"/><?php endif; ?>
    <?php endforeach; ?>
  </Row>
<?php endforeach; ?>
</Table>
</Worksheet>
<?php endforeach; ?>
</Workbook>
<?php
    ghi_nhat_ky('XUAT_BAO_CAO_GD', 'chi_tiet_thang', "$nam · " . count($sheets) . ' sheet');
}

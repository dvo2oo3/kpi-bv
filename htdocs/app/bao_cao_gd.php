<?php
/**
 * Báo cáo cho Giám đốc — theo mẫu Quyết định, cấu hình được:
 * chọn khoa, chỉ tiêu (ẩn/hiện), cột hiển thị, kỳ (mấy tháng).
 * Xuất SpreadsheetML (.xls) nhiều sheet: mỗi khoa 1 sheet + Toàn viện.
 * Cấu hình lưu ở cai_dat khóa 'baocao_gd' (JSON).
 */

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
        'cot'       => ['nam_truoc' => 1, 'chi_tieu' => 1, 'ket_qua' => 1, 'pt' => 1],
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
    $v = cai_dat_lay('baocao_gd_ds');
    $ds = $v ? json_decode($v, true) : null;
    if (is_array($ds) && $ds) {
        return array_map(fn($m) => [
            'id'  => (string)($m['id'] ?? uniqid('m')),
            'ten' => (string)($m['ten'] ?? 'Mẫu'),
            'cfg' => bao_cao_gd_chuan(is_array($m['cfg'] ?? null) ? $m['cfg'] : []),
        ], $ds);
    }
    // Lần đầu: dựng 2 mẫu sẵn. Mẫu Giám đốc = chi tiết từng tháng (giống file
    // "Theo dõi chỉ tiêu các khoa đạt được"); thêm 1 mẫu tổng gọn.
    return [
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
    $colKH = !empty($cot['chi_tieu']);
    $colKQ = !empty($cot['ket_qua']);
    $colPT = !empty($cot['pt']);
    $soCot = 3 + ($colTH?1:0) + ($colKH?1:0) + ($colKQ?1:0) + ($colPT?1:0);

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
      <?php if ($d['nam_truoc'] !== null): ?><Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($d['nam_truoc'], 2) ?></Data></Cell>
      <?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?>
    <?php endif; ?>
    <?php if ($colKH): ?>
      <?php if ($d['chi_tieu_nam'] !== null): ?><Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($d['chi_tieu_nam'], 2) ?></Data></Cell>
      <?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?>
    <?php endif; ?>
    <?php if ($colKQ): ?>
      <?php if ($d['thuc_hien'] !== null): ?><Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($d['thuc_hien'], 2) ?></Data></Cell>
      <?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?>
    <?php endif; ?>
    <?php if ($colPT): ?>
      <?php if ($d['pt_nam'] !== null): ?><Cell ss:StyleID="pt"><Data ss:Type="Number"><?= round($d['pt_nam'], 2) ?></Data></Cell>
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
    <?php if ($colTH): ?><?php if ($d['nam_truoc'] !== null): ?><Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($d['nam_truoc'],2) ?></Data></Cell><?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?><?php endif; ?>
    <?php if ($colKH): ?><?php if ($d['chi_tieu_nam'] !== null): ?><Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($d['chi_tieu_nam'],2) ?></Data></Cell><?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?><?php endif; ?>
    <?php foreach ($seq as $sp):
        $gt = $sp[0] === 'm' ? ($v['m'][$sp[1]] ?? null) : ($v['c'][$sp[1]] ?? null);
        $st = $sp[0] === 'm' ? $kSo : $kLk; ?>
      <?php if ($gt !== null): ?><Cell ss:StyleID="<?= $st ?>"><Data ss:Type="Number"><?= round($gt,2) ?></Data></Cell><?php else: ?><Cell ss:StyleID="<?= $st ?>"/><?php endif; ?>
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

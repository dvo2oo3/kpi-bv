<?php
/**
 * Tổng quan — trang đầu tiên người dùng nhìn thấy.
 *
 * Thứ tự trả lời:
 *   1. Kỳ vừa rồi khoa nào chưa nộp?            (việc phải làm hôm nay)
 *   2. Bốn chỉ tiêu trụ cột đang ở đâu?
 *   3. Khối lượng chuyên môn (thủ thuật, xét nghiệm, chẩn đoán hình ảnh)
 *   4. Từng khoa ra sao — khoa nào yếu, tháng nào còn trống
 *   5. Chỉ tiêu nào tụt xa tiến độ
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/bao_cao.php';
require_once __DIR__ . '/app/bieu_do.php';
require_once __DIR__ . '/app/cai_dat.php';

$nd = bat_buoc_dang_nhap();

$nam = (int)($_GET['nam'] ?? NAM_MAC_DINH);

// Kỳ báo cáo — lũy kế từ đầu năm. Mặc định "đến nay": lũy kế tới tháng gần nhất
// đã kết thúc (năm cũ = cả 12 tháng), nên nhãn tự nhảy theo thời gian.
$namNay = (int)date('Y');
$thangDenNay = ($nam === $namNay) ? min(12, max(1, (int)date('n') - 1)) : 12;
$KY_THANG = ['nay' => $thangDenNay, '3t' => 3, '6t' => 6, '9t' => 9, 'nam' => 12];
$KY_TEN   = ['nay' => $thangDenNay . ' tháng đầu năm (đến nay)',
             '3t' => 'Quý I · 3 tháng', '6t' => '6 tháng đầu năm',
             '9t' => '9 tháng đầu năm', 'nam' => 'Cả năm'];
$ky = (string)($_GET['ky'] ?? 'nay');
if (!isset($KY_THANG[$ky])) { $ky = 'nay'; }
$soThangKy = $KY_THANG[$ky];

// Kỳ gần nhất đã kết thúc — dùng cho banner "khoa chưa nộp"
$thangKy = (int)date('n') - 1;
$namKy   = (int)date('Y');
if ($thangKy === 0) { $thangKy = 12; $namKy--; }
if ($nam !== $namKy) { $thangKy = 12; }

$xemToanVien = co_quyen('baocao.toan_vien');
$duocPhep = cac_khoa_duoc_phep();
$dsKhoa = $duocPhep === null
    ? qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten')
    : ($duocPhep ? qAll('SELECT * FROM khoa WHERE hoat_dong = 1 AND id IN ('
        . implode(',', array_fill(0, count($duocPhep), '?')) . ') ORDER BY thu_tu', $duocPhep)
      : []);

$idKhoaXem = $xemToanVien ? null : (int)($dsKhoa[0]['id'] ?? 0);
$tenPhamVi = $xemToanVien ? 'Toàn viện' : ($dsKhoa[0]['ten'] ?? '');

// Người dùng chỉ thấy phạm vi mình được phép (bác sĩ = khoa mình). Vì %KH hiển
// thị luôn là của chính khoa đó nên không còn là "bí mật với người xem" — cho
// hiện. Các khối TOÀN VIỆN vẫn gói riêng theo $xemToanVien phía dưới.
$anKpi = false;

$cacThangLuyKe = range(1, $soThangKy);
$tienDoKyVong  = round($soThangKy / 12 * 100);

/* ---------- 1. Tiến độ nộp kỳ ---------- */
$demTT = ['MO' => 0, 'DA_NOP' => 0, 'DA_DUYET' => 0, 'DA_KHOA' => 0, 'CHUA_DEN' => 0];
$khoaChuaNop = [];
foreach ($dsKhoa as $k) {
    $tt = trang_thai_ky($namKy, $thangKy, (int)$k['id']);
    $demTT[$tt] = ($demTT[$tt] ?? 0) + 1;
    if ($tt === 'MO') {
        $soCT = count(array_filter(chi_tieu_cua_khoa((int)$k['id']),
            fn($c) => $c['nguon'] === 'NHAP_TAY'));
        $daNhap = (int)qVal('SELECT COUNT(*) FROM so_lieu
                              WHERE nam=? AND thang=? AND id_khoa=? AND gia_tri IS NOT NULL',
            [$namKy, $thangKy, (int)$k['id']]);
        $khoaChuaNop[] = ['khoa' => $k, 'da' => $daNhap, 'tong' => $soCT];
    }
}

// Chờ duyệt TỔNG — mọi tháng, không chỉ tháng đang xem, để admin không bỏ sót
// (bác sĩ có thể nộp cho tháng khác với "tháng trước" mà dashboard đang tính).
$choDuyetDs = co_quyen('ky.duyet')
    ? qAll("SELECT k.nam, k.thang, kh.ma, kh.ten
            FROM ky k JOIN khoa kh ON kh.id = k.id_khoa
            WHERE k.trang_thai = 'DA_NOP'
            ORDER BY k.nam DESC, k.thang DESC, kh.thu_tu, kh.ten")
    : [];

/* ---------- 2 & 3. Các ô trên trang chủ ----------
 * Lấy chỉ tiêu theo CẤU HÌNH (id) nếu đã đặt; chưa đặt thì dùng mã mặc định.
 * Nhờ vậy đổi tên/đổi mã không làm mất số — chỉ cần chọn lại chỉ tiêu.
 */
const DASHBOARD_TRU_COT_MD    = ['KB', 'NT', 'NDT', 'CSGB'];
const DASHBOARD_KHOI_LUONG_MD = ['TT', 'PT', 'XN', 'XQ', 'SA', 'NS'];

$dashCfg = dashboard_o();
$layTheoId = function (array $ids): array {
    $tc = tat_ca_chi_tieu();
    $r = [];
    foreach ($ids as $id) { if (isset($tc[(int)$id])) { $r[] = $tc[(int)$id]; } }
    return $r;
};
$layTheoMa = fn(array $mas) => array_values(array_filter(array_map('chi_tieu_theo_ma', $mas)));

// Nhãn ngắn gọn cho các chỉ tiêu chuẩn; chỉ tiêu tự tạo thì lấy tên của nó.
$NHAN_NGAN = [
    'KB' => 'Lượt khám bệnh', 'NT' => 'Bệnh nhân nội trú',
    'NDT' => 'Ngày điều trị nội trú', 'CSGB' => 'Công suất giường bệnh',
    'TT' => 'Thủ thuật', 'PT' => 'Phẫu thuật', 'XN' => 'Xét nghiệm',
    'XQ' => 'X-quang', 'SA' => 'Siêu âm', 'NS' => 'Nội soi',
];
$nhanThe = fn(array $ct) => $NHAN_NGAN[$ct['ma']] ?? $ct['ten'];

$rowsTruCot = !empty($dashCfg['tru_cot'])
    ? $layTheoId($dashCfg['tru_cot']) : $layTheoMa(DASHBOARD_TRU_COT_MD);
$rowsKhoiLuong = !empty($dashCfg['khoi_luong'])
    ? $layTheoId($dashCfg['khoi_luong']) : $layTheoMa(DASHBOARD_KHOI_LUONG_MD);

$oSoLieu = [];
foreach ($rowsTruCot as $ct) {
    $ma = $ct['ma'];
    // Tỷ lệ / trung bình hiển thị 1 số lẻ; số đếm để nguyên
    $le = in_array($ct['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true) ? 1 : 0;
    $v = tri_chi_tieu($nam, $cacThangLuyKe, $idKhoaXem, $ma);

    // Cùng kỳ năm trước, tính từ số liệu thật chứ không lấy chỉ tiêu
    $cungKy = tri_chi_tieu($nam - 1, $cacThangLuyKe, $idKhoaXem, $ma)['th'];

    $oSoLieu[] = [
        'ten' => $nhanThe($ct), 'le' => $le, 'don_vi' => $ct['don_vi'],
        'thuc_hien' => $v['th'], 'chi_tieu_nam' => $v['kh_nam'],
        'pt' => pt_so_ke_hoach_nam($v['th'], $v['kh_nam']),
        'cung_ky' => $cungKy,
        'chuoi' => chuoi_12_thang($nam, $idKhoaXem, $ma),
        'la_ty_le' => $ct['loai_gia_tri'] === 'TY_LE',
        'chu_thich' => mo_ta_cong_thuc($ct),
    ];
}

$chuyenMon = [];
foreach ($rowsKhoiLuong as $ct) {
    // Vẫn hiện cả khi chưa có số liệu — ô trống là tín hiệu có khoa chưa nhập,
    // ẩn đi thì người xem không biết là đang thiếu.
    $v = tri_chi_tieu($nam, $cacThangLuyKe, $idKhoaXem, $ct['ma']);
    $chuyenMon[] = ['ten' => $nhanThe($ct), 'don_vi' => $ct['don_vi'],
                    'th' => $v['th'], 'kh' => $v['kh_nam'],
                    'pt' => pt_so_ke_hoach_nam($v['th'], $v['kh_nam'])];
}

/* ---------- 3b. Chất lượng điều trị và cơ cấu bệnh nhân ---------- */
// Lấy ĐỘNG các mục con của "Kết quả điều trị nội trú" (KQDT) — thêm mục mới
// (khỏi/đỡ/nặng…) tự hiện, không cần sửa code.
$chatLuong = [];
$tongKQ = null;
$ctKQDT = chi_tieu_theo_ma('KQDT');
if ($ctKQDT) {
    foreach (tat_ca_chi_tieu() as $c) {
        if ($c['id_cha'] !== (int)$ctKQDT['id']) { continue; }
        $v = tri_chi_tieu($nam, $cacThangLuyKe, $idKhoaXem, $c['ma'])['th'];
        if ($v !== null) { $tongKQ = ($tongKQ ?? 0) + $v; }
        $chatLuong[] = ['ten' => $c['ten'], 'th' => $v];
    }
}
foreach ($chatLuong as &$c) {
    $c['pt'] = ($tongKQ && $tongKQ > 0 && $c['th'] !== null) ? $c['th'] / $tongKQ * 100 : null;
}
unset($c);

// Chỉ tiêu càng thấp càng tốt — không chấm theo % kế hoạch
$anToan = [];
foreach ([['TV','Bệnh nhân tử vong'], ['CV_NT','Chuyển viện nội trú'],
          ['CV_NGT','Chuyển viện ngoại trú']] as [$ma, $ten]) {
    $ct = chi_tieu_theo_ma($ma);
    if (!$ct) { continue; }
    $anToan[] = ['ten' => $ten, 'don_vi' => $ct['don_vi'],
                 'th' => tri_chi_tieu($nam, $cacThangLuyKe, $idKhoaXem, $ma)['th']];
}

// Cơ cấu bảo hiểm y tế và viện phí của bệnh nhân nội trú
$coCau = [];
$tongCC = null;
foreach ([['NT_BH','Bảo hiểm y tế'], ['NT_ND','Viện phí']] as [$ma, $ten]) {
    $ct = chi_tieu_theo_ma($ma);
    if (!$ct) { continue; }
    $v = tri_chi_tieu($nam, $cacThangLuyKe, $idKhoaXem, $ma)['th'];
    if ($v !== null) { $tongCC = ($tongCC ?? 0) + $v; }
    $coCau[] = ['ten' => $ten, 'th' => $v];
}
foreach ($coCau as &$c) {
    $c['pt'] = ($tongCC && $tongCC > 0 && $c['th'] !== null) ? $c['th'] / $tongCC * 100 : null;
}
unset($c);

/* ---------- 4. Bảng từng khoa ---------- */
$bangKhoa = [];
foreach ($dsKhoa as $k) {
    $idK = (int)$k['id'];
    $d = ['khoa' => $k, 'thang' => []];
    foreach (['NT','NDT','NDT_TB','CSGB','TT','KB'] as $ma) {
        $d[$ma] = tri_chi_tieu($nam, $cacThangLuyKe, $idK, $ma);
    }
    $d['pt_nt'] = pt_so_ke_hoach_nam($d['NT']['th'], $d['NT']['kh_nam']);
    for ($t = 1; $t <= 12; $t++) {
        $d['thang'][$t] = trang_thai_ky($nam, $t, $idK);
    }
    $bangKhoa[] = $d;
}

/* ---------- 5. Chỉ tiêu chậm tiến độ ---------- */
$chamTienDo = [];
$bang = $idKhoaXem === null
    ? bang_toan_vien($nam, $cacThangLuyKe, 'giao')
    : bang_theo_khoa($nam, $cacThangLuyKe, $idKhoaXem, 'giao');
foreach ($bang as $d) {
    if ($d['cap'] !== 0 || $d['pt_nam'] === null) { continue; }
    if ($d['ct']['huong'] !== 'CAO_TOT') { continue; }
    $thieu = $tienDoKyVong - $d['pt_nam'];
    if ($thieu >= 10) {
        $chamTienDo[] = ['ten' => $d['ct']['ten'], 'don_vi' => $d['ct']['don_vi'],
                         'th' => $d['thuc_hien'], 'kh' => $d['chi_tieu_nam'],
                         'pt' => $d['pt_nam'], 'thieu' => $thieu];
    }
}
usort($chamTienDo, fn($a, $b) => $b['thieu'] <=> $a['thieu']);

/* ---------- 6. Báo cáo theo khoa ---------- */
// Chọn khoa để xem chi tiết. Người xem toàn viện chọn khoa bất kỳ; bác sĩ chỉ
// có khoa của mình.
$idsChoPhep = array_map(fn($k) => (int)$k['id'], $dsKhoa);
// Mặc định chọn khoa đầu tiên đã có số liệu năm này — đầu năm nhiều khoa chưa
// nộp, chọn bừa khoa đầu danh sách thì thấy bảng trống, tưởng hỏng.
$khoaMacDinh = (int)($dsKhoa[0]['id'] ?? 0);
if ($idsChoPhep) {
    $coSL = (int)qVal('SELECT id_khoa FROM so_lieu
                       WHERE nam = ? AND gia_tri IS NOT NULL AND id_khoa IN ('
        . implode(',', array_fill(0, count($idsChoPhep), '?')) . ')
                       ORDER BY id_khoa LIMIT 1', array_merge([$nam], $idsChoPhep));
    if ($coSL) { $khoaMacDinh = $coSL; }
}
$idKhoaBC = (int)($_GET['bc'] ?? $khoaMacDinh);
if (!in_array($idKhoaBC, $idsChoPhep, true)) {
    $idKhoaBC = $khoaMacDinh;
}
$khoaBC = null;
foreach ($dsKhoa as $k) {
    if ((int)$k['id'] === $idKhoaBC) { $khoaBC = $k; break; }
}

// TẤT CẢ nội dung lớn của khoa — mỗi cái một dòng trong bảng gọn. Không chốt
// cứng chỉ tiêu nào, thêm bao nhiêu đầu mục cũng tự vào bảng.
$dongBC = [];
if ($khoaBC) {
    foreach (bang_theo_khoa($nam, $cacThangLuyKe, $idKhoaBC, 'giao') as $d) {
        if ($d['cap'] !== 0) { continue; }   // chỉ nội dung lớn
        $chuoi = chuoi_12_thang($nam, $idKhoaBC, $d['ct']['ma']);
        $peakV = null; $peakT = null;
        for ($t = 1; $t <= 12; $t++) {
            if ($chuoi[$t] !== null && ($peakV === null || $chuoi[$t] > $peakV)) {
                $peakV = $chuoi[$t]; $peakT = $t;
            }
        }
        $laTyLe = $d['ct']['loai_gia_tri'] === 'TY_LE';
        $laTB   = $d['ct']['loai_gia_tri'] === 'TRUNG_BINH';
        $dongBC[] = [
            'ct' => $d['ct'], 'th' => $d['thuc_hien'], 'kh' => $d['chi_tieu_nam'],
            'pt' => $d['pt_nam'], 'chuoi' => $chuoi, 'peakV' => $peakV, 'peakT' => $peakT,
            'le' => ($laTyLe || $laTB) ? 1 : 0,
            'la_muc_dich' => $laTyLe || $laTB,   // tỷ lệ/trung bình: không chấm %KH
        ];
    }
}

// Vài chỉ tiêu đưa lên thẻ vòng tròn (đếm được, có %KH, bỏ hằng số vì luôn 100%)
// + một chỉ tiêu mức đích lên gauge (ưu tiên công suất giường).
$theVong = []; $theGauge = null; $theDuong = null;
foreach ($dongBC as $r) {
    $loai = $r['ct']['loai_gia_tri'];
    if (!$r['la_muc_dich'] && $loai !== 'HANG_SO' && $r['pt'] !== null && count($theVong) < 4) {
        $theVong[] = $r;
        if ($theDuong === null) { $theDuong = $r; }   // đường: chỉ tiêu đếm, có biến động
    } elseif ($r['la_muc_dich'] && $theGauge === null && $r['th'] !== null) {
        $theGauge = $r;
    }
}
foreach ($dongBC as $r) {   // công suất giường ưu tiên cho gauge nếu có
    if ($r['ct']['ma'] === 'CSGB' && $r['th'] !== null) { $theGauge = $r; break; }
}

// Đổi khoa ở khối "Báo cáo theo khoa" gọi AJAX vào đây — trả về ĐÚNG khối đó,
// không dựng lại cả trang (tránh tải lại, không nhảy về đầu trang).
if (($_GET['phan'] ?? '') === 'bao_cao_khoa') {
    header('Content-Type: text/html; charset=UTF-8');
    require __DIR__ . '/app/phan_bao_cao_khoa.php';
    exit;
}

mo_trang('Tổng quan');
?>
<div class="dau-muc">
  <div>
    <h1>Tổng quan — <?= e($tenPhamVi) ?></h1>
    <p class="phu">
      <?= e($KY_TEN[$ky]) ?> · năm <?= $nam ?><?php if (!$anKpi): ?> ·
      tiến độ kỳ vọng <strong><?= $tienDoKyVong ?>%</strong> kế hoạch năm<?php endif; ?>
      <?php if (co_quyen('he_thong.trang_chu')): ?>
        · <a href="/cau-hinh-trang-chu.php">⚙ Chọn ô hiển thị</a>
      <?php endif; ?>
    </p>
  </div>
  <form method="get" class="hang-nut">
    <input type="hidden" name="bc" value="<?= (int)$idKhoaBC ?>">
    <label class="an-nhan">Năm
      <select name="nam" onchange="this.form.submit()">
        <?php for ($n = NAM_MAC_DINH; $n >= NAM_MAC_DINH - 3; $n--): ?>
          <option value="<?= $n ?>" <?= $n === $nam ? 'selected' : '' ?>><?= $n ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <label class="an-nhan">Kỳ
      <select name="ky" onchange="this.form.submit()">
        <?php foreach ($KY_TEN as $mã => $tên): ?>
          <option value="<?= e($mã) ?>" <?= $mã === $ky ? 'selected' : '' ?>><?= e($tên) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>
</div>

<!-- ============ Việc cần làm ============ -->
<?php if ($choDuyetDs): ?>
<div class="tb tb-canh-bao">
  <strong><?= count($choDuyetDs) ?> kỳ đang chờ bạn duyệt</strong> (gồm mọi tháng có khoa đã nộp):
  <div class="ds-cho-nop">
    <?php foreach ($choDuyetDs as $c): ?>
      <a class="the-cho-nop"
         href="/duyet-ky.php?nam=<?= (int)$c['nam'] ?>&thang=<?= (int)$c['thang'] ?>">
        <strong><?= e($c['ma']) ?></strong>
        <span>tháng <?= (int)$c['thang'] ?>/<?= (int)$c['nam'] ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($khoaChuaNop): ?>
<div class="tb tb-canh-bao">
  <strong><?= count($khoaChuaNop) ?> khoa chưa nộp số liệu tháng <?= $thangKy ?>/<?= $namKy ?></strong>
  — hạn nộp <?= date('d/m/Y', han_nop($namKy, $thangKy)) ?>.
  <div class="ds-cho-nop">
    <?php foreach ($khoaChuaNop as $c): ?>
      <a class="the-cho-nop"
         href="/nhap-so-lieu.php?khoa=<?= (int)$c['khoa']['id'] ?>&nam=<?= $namKy ?>&thang=<?= $thangKy ?>">
        <strong><?= e($c['khoa']['ma']) ?></strong>
        <span><?= $c['da'] ?>/<?= $c['tong'] ?> ô</span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php elseif ($dsKhoa): ?>
<div class="tb tb-ok">
  Tất cả <?= count($dsKhoa) ?> khoa đã nộp số liệu tháng <?= $thangKy ?>/<?= $namKy ?>.
  <?php if ($demTT['DA_NOP'] > 0 && co_quyen('ky.duyet')): ?>
    <a href="/duyet-ky.php?nam=<?= $namKy ?>&thang=<?= $thangKy ?>">
      <?= $demTT['DA_NOP'] ?> khoa đang chờ duyệt</a>.
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($xemToanVien): /* Phần toàn viện + KPI — chỉ admin/quản lý */ ?>
<!-- ============ Bốn chỉ tiêu trụ cột — biểu đồ toàn viện ============ -->
<h2 class="tieu-de-phan">
  Toàn viện
  <span class="phu">· biểu đồ xu hướng 12 tháng, lũy kế <?= $soThangKy ?> tháng</span>
</h2>
<div class="luoi-kpi">
  <?php foreach ($oSoLieu as $o):
      $dat = $o['pt'] !== null && $o['pt'] >= $tienDoKyVong; ?>
    <section class="the-kpi">
      <header class="kpi-dau">
        <h3><?= e($o['ten']) ?></h3>
        <span class="kpi-don-vi"><?= e($o['la_ty_le'] ? '%' : $o['don_vi']) ?></span>
      </header>

      <div class="kpi-so"><?= $o['thuc_hien'] === null ? '—' : so($o['thuc_hien'], $o['le']) ?></div>

      <?php if (!empty($o['chu_thich'])): ?>
        <p class="kpi-tu-tinh"><span class="nhan-tu-tinh">Tự tính</span> <?= e($o['chu_thich']) ?></p>
      <?php endif; ?>

      <?php if (!$o['la_ty_le'] && $o['pt'] !== null): ?>
        <div class="thanh-tien-do rong">
          <span style="width:<?= min(100, max(0, round($o['pt']))) ?>%"></span>
          <i class="vach-ky-vong" style="left:<?= $tienDoKyVong ?>%"></i>
        </div>
        <p class="kpi-chu-thich">
          <strong><?= phan_tram($o['pt']) ?></strong> kế hoạch năm
          <span class="phu"><?= so($o['chi_tieu_nam'], 0) ?></span>
          <span class="<?= $dat ? 'nhan-dat' : 'nhan-cham' ?>">
            <?= $dat ? '✓ đúng tiến độ' : '▾ chậm ' . phan_tram($tienDoKyVong - $o['pt']) ?>
          </span>
        </p>
      <?php else: ?>
        <p class="kpi-chu-thich"><span class="phu">Mức mong muốn 100%</span></p>
      <?php endif; ?>

      <?php if ($o['cung_ky'] !== null && $o['cung_ky'] > 0 && $o['thuc_hien'] !== null):
          $doi = ($o['thuc_hien'] - $o['cung_ky']) / $o['cung_ky'] * 100; ?>
        <p class="kpi-cung-ky">
          Cùng kỳ <?= $nam - 1 ?>: <?= so($o['cung_ky'], $o['le']) ?>
          <span class="<?= $doi >= 0 ? 'chenh-duong' : 'chenh-am' ?>">
            <?= $doi >= 0 ? '▴ +' : '▾ ' ?><?= phan_tram(abs($doi)) ?>
          </span>
        </p>
      <?php endif; ?>

      <div class="kpi-bieu-do">
        <?= bieu_do_duong($o['chuoi'], $o['la_ty_le'] ? '%' : $o['don_vi'], $o['le']) ?>
        <?= nhan_thang() ?>
      </div>

      <details class="bang-thay-the">
        <summary>Số liệu từng tháng</summary>
        <?php
          $mxK = null; $mxT = null;
          for ($t = 1; $t <= 12; $t++) {
              $vv = $o['chuoi'][$t] ?? null;
              if ($vv !== null && ($mxK === null || $vv > $mxK)) { $mxK = $vv; $mxT = $t; }
          } ?>
        <div class="luoi-thang-so">
          <?php for ($t = 1; $t <= 12; $t++):
              $vv = $o['chuoi'][$t] ?? null; ?>
            <div class="ot <?= $vv === null ? 'ot-trong' : '' ?> <?= $t === $mxT ? 'ot-cao' : '' ?>"
                 <?= $t === $mxT ? 'title="Tháng cao nhất"' : '' ?>>
              <span class="ot-t">T<?= $t ?></span>
              <span class="ot-v"><?= $vv === null ? '–' : so($vv, $o['le']) ?></span>
            </div>
          <?php endfor; ?>
        </div>
      </details>
    </section>
  <?php endforeach; ?>
</div>

<!-- ============ Khối lượng chuyên môn ============ -->
<?php if ($chuyenMon): ?>
<h2>Khối lượng chuyên môn <span class="phu">· lũy kế <?= $soThangKy ?> tháng</span></h2>
<div class="luoi-cm">
  <?php foreach ($chuyenMon as $c):
      $dat = $c['pt'] !== null && $c['pt'] >= $tienDoKyVong; ?>
    <div class="o-cm">
      <div class="cm-ten"><?= e($c['ten']) ?></div>
      <div class="cm-so"><?= so($c['th'], 0) ?><span class="cm-dv"><?= e($c['don_vi']) ?></span></div>
      <div class="cm-day">
        <?php if ($c['pt'] !== null): ?>
          <div class="thanh-tien-do rong nho-hon">
            <span style="width:<?= min(100, max(0, round($c['pt']))) ?>%"></span>
            <i class="vach-ky-vong" style="left:<?= $tienDoKyVong ?>%"></i>
          </div>
          <div class="cm-pt <?= $dat ? 'nhan-dat' : 'nhan-cham' ?>">
            <?= phan_tram($c['pt']) ?> KH
          </div>
        <?php elseif ($c['th'] === null): ?>
          <div class="cm-pt phu">chưa có số liệu</div>
        <?php else: ?>
          <div class="cm-pt phu">chưa giao chỉ tiêu</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============ Chất lượng điều trị ============ -->
<?php if ($tongKQ || $anToan || $tongCC): ?>
<h2>Chất lượng và cơ cấu điều trị nội trú
  <span class="phu">· lũy kế <?= $soThangKy ?> tháng</span></h2>
<div class="luoi-cl">

  <?php if ($tongKQ): ?>
  <section class="the-cl">
    <h3>Kết quả điều trị <span class="phu"><?= so($tongKQ, 0) ?> lượt ra viện</span></h3>
    <table class="bang-gon">
      <?php foreach ($chatLuong as $c): ?>
        <tr>
          <td><?= e($c['ten']) ?></td>
          <td class="phai"><?= so($c['th'], 0) ?></td>
          <td class="phai nho"><?= phan_tram($c['pt']) ?></td>
          <td class="cot-thanh">
            <span class="thanh-ty-le">
              <span style="width:<?= $c['pt'] === null ? 0 : round($c['pt']) ?>%"></span>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </section>
  <?php endif; ?>

  <?php if ($tongCC): ?>
  <section class="the-cl">
    <h3>Cơ cấu bệnh nhân nội trú</h3>
    <table class="bang-gon">
      <?php foreach ($coCau as $c): ?>
        <tr>
          <td><?= e($c['ten']) ?></td>
          <td class="phai"><?= so($c['th'], 0) ?></td>
          <td class="phai nho"><?= phan_tram($c['pt']) ?></td>
          <td class="cot-thanh">
            <span class="thanh-ty-le">
              <span style="width:<?= $c['pt'] === null ? 0 : round($c['pt']) ?>%"></span>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </section>
  <?php endif; ?>

  <?php if ($anToan): ?>
  <section class="the-cl">
    <h3>An toàn người bệnh <span class="phu">càng thấp càng tốt</span></h3>
    <table class="bang-gon">
      <?php foreach ($anToan as $c): ?>
        <tr>
          <td><?= e($c['ten']) ?></td>
          <td class="phai"><strong><?= so($c['th'], 0) ?></strong></td>
          <td class="nho phu"><?= e($c['don_vi']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <p class="phu ghi-chu-cl">
      Không chấm theo phần trăm kế hoạch — với các chỉ tiêu này, vượt kế hoạch là xấu.
    </p>
  </section>
  <?php endif; ?>

</div>
<?php endif; /* tongKQ... */ ?>
<?php endif; /* xemToanVien */ ?>

<!-- ============ Báo cáo / Số liệu theo khoa ============ -->
<div id="phan-bc">
<?php require __DIR__ . '/app/phan_bao_cao_khoa.php'; ?>
</div>
<script>
(function () {
  var wrap = document.getElementById('phan-bc');
  if (!wrap) { return; }
  var NAM = <?= (int)$nam ?>, KY = <?= json_encode($ky, JSON_UNESCAPED_UNICODE) ?>;
  document.addEventListener('change', function (e) {
    var sel = e.target.closest('#phan-bc select[data-bc-select]');
    if (!sel) { return; }
    var url = '/index.php?phan=bao_cao_khoa&bc=' + encodeURIComponent(sel.value)
            + '&nam=' + NAM + '&ky=' + encodeURIComponent(KY);
    wrap.style.opacity = '.5'; wrap.style.pointerEvents = 'none';
    fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) { throw new Error('http'); } return r.text(); })
      .then(function (html) {
        wrap.innerHTML = html;
        wrap.style.opacity = ''; wrap.style.pointerEvents = '';
        if (window.history && history.replaceState) {
          var u = new URL(location.href); u.searchParams.set('bc', sel.value);
          history.replaceState(null, '', u);
        }
      })
      .catch(function () { if (sel.form) { sel.form.submit(); } });   // lỗi mạng → quay lại cách cũ
  });
})();
</script>

<!-- ============ Bảng từng khoa ============ -->
<?php if (count($bangKhoa) > 1): ?>
<h2>Toàn bộ các khoa <span class="phu">· lũy kế <?= $soThangKy ?> tháng năm <?= $nam ?></span></h2>
<div class="cuon-ngang">
<table class="bang">
  <thead>
    <tr>
      <th>Khoa</th>
      <th class="phai">Giường</th>
      <th class="phai">BN nội trú</th>
      <th class="phai">% KH</th>
      <th class="phai">Ngày ĐT</th>
      <th class="phai">Ngày ĐT TB</th>
      <th>Công suất giường</th>
      <th class="phai">Thủ thuật</th>
      <th class="phai">Khám bệnh</th>
      <th>Tiến độ 12 tháng</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($bangKhoa as $d):
      $k = $d['khoa'];
      $cs = $d['CSGB']['th']; ?>
    <tr>
      <td>
        <a class="lien-ket-khoa" href="/bao-cao.php?nam=<?= $nam ?>&ky=<?= $thangKy ?>T&pv=khoa&khoa=<?= (int)$k['id'] ?>"
           title="<?= e($k['ten']) ?>"><strong><?= e($k['ma']) ?></strong></a>
      </td>
      <td class="phai"><?= $k['loai'] === 'NOI_TRU' ? (int)$k['giuong_benh'] : '—' ?></td>
      <td class="phai"><?= so($d['NT']['th'], 0) ?></td>
      <td class="phai nho">
        <?php if ($d['pt_nt'] !== null): ?>
          <span class="<?= $d['pt_nt'] >= $tienDoKyVong ? 'nhan-dat' : 'nhan-cham' ?>">
            <?= phan_tram($d['pt_nt']) ?></span>
        <?php else: ?><span class="phu">—</span><?php endif; ?>
      </td>
      <td class="phai"><?= so($d['NDT']['th'], 0) ?></td>
      <td class="phai"><?= so($d['NDT_TB']['th'], 1) ?></td>
      <td>
        <?php if ($cs !== null): ?>
          <div class="cs-o">
            <span class="cs-so"><?= phan_tram($cs) ?></span>
            <span class="cs-ranh"><span class="cs-day"
                  style="width:<?= min(100, max(0, round($cs / 1.2))) ?>%"></span>
              <i class="cs-moc"></i></span>
          </div>
        <?php else: ?><span class="phu">—</span><?php endif; ?>
      </td>
      <td class="phai"><?= so($d['TT']['th'], 0) ?></td>
      <td class="phai"><?= so($d['KB']['th'], 0) ?></td>
      <td>
        <div class="dai-thang">
          <?php for ($t = 1; $t <= 12; $t++):
              $tt = $d['thang'][$t]; ?>
            <i class="o-thang tt-<?= e(chu_thuong($tt)) ?>"
               title="Tháng <?= $t ?>: <?= e(ten_trang_thai($tt)) ?>"><?= $t ?></i>
          <?php endfor; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="chu-giai-thang">
  Tiến độ 12 tháng:
  <span><i class="o-thang tt-chua_den"></i> chưa đến kỳ</span>
  <span><i class="o-thang tt-mo"></i> đang mở</span>
  <span><i class="o-thang tt-da_nop"></i> chờ duyệt</span>
  <span><i class="o-thang tt-da_duyet"></i> đã duyệt</span>
  <span><i class="o-thang tt-da_khoa"></i> đã khóa</span>
</p>
<?php endif; ?>

<!-- ============ Chậm tiến độ (KPI — chỉ admin/quản lý) ============ -->
<?php if ($chamTienDo && $xemToanVien): ?>
<h2>Chỉ tiêu chậm tiến độ <span class="phu">· thiếu từ 10% trở lên</span></h2>
<div class="cuon-ngang">
<table class="bang">
  <thead>
    <tr><th>Nội dung</th><th>Đơn vị</th><th class="phai">Thực hiện</th>
        <th class="phai">Kế hoạch năm</th><th class="phai">Đạt</th><th class="phai">Còn thiếu</th></tr>
  </thead>
  <tbody>
  <?php foreach (array_slice($chamTienDo, 0, 12) as $c): ?>
    <tr>
      <td><?= e($c['ten']) ?></td>
      <td class="nho"><?= e($c['don_vi']) ?></td>
      <td class="phai"><?= so($c['th'], 0) ?></td>
      <td class="phai"><?= so($c['kh'], 0) ?></td>
      <td class="phai"><strong><?= phan_tram($c['pt']) ?></strong></td>
      <td class="phai"><span class="nhan-cham"><?= phan_tram($c['thieu']) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php if (count($chamTienDo) > 12): ?>
  <p class="phu">Còn <?= count($chamTienDo) - 12 ?> chỉ tiêu nữa —
    xem đầy đủ ở <a href="/bao-cao.php">Báo cáo</a>.</p>
<?php endif; ?>
<?php endif; ?>

<!-- ============ Tất cả chỉ tiêu (thu gọn) — gồm cả chỉ tiêu mới thêm ============ -->
<?php if (!empty($bang)): ?>
<details class="tat-ca-ct" style="margin-top:1.5rem">
  <summary style="cursor:pointer;font-weight:700;color:var(--xanh-900)">
    Tất cả chỉ tiêu — <?= e($tenPhamVi) ?>
    <span class="phu" style="font-weight:400">· <?= count($bang) ?> dòng · bấm để mở/đóng</span>
  </summary>
  <div class="cuon-ngang" style="margin-top:.75rem">
  <table class="bang">
    <thead><tr><th>Nội dung</th><th>Đơn vị</th><th class="phai">Thực hiện</th>
      <th class="phai">Kế hoạch năm</th><th class="phai">%KH</th></tr></thead>
    <tbody>
    <?php foreach ($bang as $d): $ct = $d['ct'];
        $le = in_array($ct['loai_gia_tri'], ['TY_LE','TRUNG_BINH'], true) ? 1 : 0; ?>
      <tr class="<?= $d['cap'] ? 'dong-con' : '' ?>">
        <td><?= $d['cap'] ? '<span class="thut">↳</span> ' : '' ?><?= e($ct['ten']) ?></td>
        <td class="nho"><?= e($ct['don_vi']) ?></td>
        <td class="phai"><?= $d['thuc_hien'] === null ? '—' : so($d['thuc_hien'], $le) ?></td>
        <td class="phai"><?= $d['chi_tieu_nam'] === null ? '—' : so($d['chi_tieu_nam'], 0) ?></td>
        <td class="phai"><?= $d['pt_nam'] === null ? '—' : phan_tram($d['pt_nam']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</details>
<?php endif; ?>
<?php dong_trang();

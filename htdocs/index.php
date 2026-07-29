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

$nd = bat_buoc_dang_nhap();

$nam = (int)($_GET['nam'] ?? NAM_MAC_DINH);

// Kỳ báo cáo — lũy kế từ đầu năm, cho chọn, mặc định 6 tháng.
$KY_THANG = ['3t' => 3, '6t' => 6, '9t' => 9, 'nam' => 12];
$KY_TEN   = ['3t' => 'Quý I · 3 tháng', '6t' => '6 tháng đầu năm',
             '9t' => '9 tháng đầu năm', 'nam' => 'Cả năm'];
$ky = (string)($_GET['ky'] ?? '6t');
if (!isset($KY_THANG[$ky])) { $ky = '6t'; }
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

/* ---------- 2. Bốn chỉ tiêu trụ cột ---------- */
$chiTieuChinh = [
    ['KB',   'Lượt khám bệnh',        0],
    ['NT',   'Bệnh nhân nội trú',     0],
    ['NDT',  'Ngày điều trị nội trú', 0],
    ['CSGB', 'Công suất giường bệnh', 1],
];

$oSoLieu = [];
foreach ($chiTieuChinh as [$ma, $ten, $le]) {
    $ct = chi_tieu_theo_ma($ma);
    if (!$ct) { continue; }
    $v = tri_chi_tieu($nam, $cacThangLuyKe, $idKhoaXem, $ma);

    // Cùng kỳ năm trước, tính từ số liệu thật chứ không lấy chỉ tiêu
    $cungKy = tri_chi_tieu($nam - 1, $cacThangLuyKe, $idKhoaXem, $ma)['th'];

    $oSoLieu[] = [
        'ten' => $ten, 'le' => $le, 'don_vi' => $ct['don_vi'],
        'thuc_hien' => $v['th'], 'chi_tieu_nam' => $v['kh_nam'],
        'pt' => pt_so_ke_hoach_nam($v['th'], $v['kh_nam']),
        'cung_ky' => $cungKy,
        'chuoi' => chuoi_12_thang($nam, $idKhoaXem, $ma),
        'la_ty_le' => $ct['loai_gia_tri'] === 'TY_LE',
    ];
}

/* ---------- 3. Khối lượng chuyên môn ---------- */
$chuyenMon = [];
foreach ([['TT','Thủ thuật'], ['PT','Phẫu thuật'], ['XN','Xét nghiệm'],
          ['XQ','X-quang'], ['SA','Siêu âm'], ['NS','Nội soi']] as [$ma, $ten]) {
    $ct = chi_tieu_theo_ma($ma);
    if (!$ct) { continue; }
    // Vẫn hiện cả khi chưa có số liệu — ô trống là tín hiệu có khoa chưa nhập,
    // ẩn đi thì người xem không biết là đang thiếu.
    $v = tri_chi_tieu($nam, $cacThangLuyKe, $idKhoaXem, $ma);
    $chuyenMon[] = ['ten' => $ten, 'don_vi' => $ct['don_vi'],
                    'th' => $v['th'], 'kh' => $v['kh_nam'],
                    'pt' => pt_so_ke_hoach_nam($v['th'], $v['kh_nam'])];
}

/* ---------- 3b. Chất lượng điều trị và cơ cấu bệnh nhân ---------- */
$chatLuong = [];
$tongKQ = null;
foreach ([['KQ_KHOI','Khỏi'], ['KQ_DO','Đỡ'],
          ['KQ_KTD','Không thay đổi'], ['KQ_NANG','Nặng hơn']] as [$ma, $ten]) {
    $ct = chi_tieu_theo_ma($ma);
    if (!$ct) { continue; }
    $v = tri_chi_tieu($nam, $cacThangLuyKe, $idKhoaXem, $ma)['th'];
    if ($v !== null) { $tongKQ = ($tongKQ ?? 0) + $v; }
    $chatLuong[] = ['ten' => $ten, 'th' => $v];
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

mo_trang('Tổng quan');
?>
<div class="dau-muc">
  <div>
    <h1>Tổng quan — <?= e($tenPhamVi) ?></h1>
    <p class="phu">
      <?= e($KY_TEN[$ky]) ?> · năm <?= $nam ?><?php if (!$anKpi): ?> ·
      tiến độ kỳ vọng <strong><?= $tienDoKyVong ?>%</strong> kế hoạch năm<?php endif; ?>
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
<?php if ($khoaBC): ?>
<div class="dau-muc dau-muc-bc">
  <div>
    <h2 class="tieu-de-phan" style="margin:0"><?= $xemToanVien
        ? 'Báo cáo theo khoa — ' . e($khoaBC['ten'])
        : 'Số liệu Khoa ' . e(preg_replace('/^Khoa\s+/u', '', $khoaBC['ten'])) ?></h2>
    <p class="phu" style="margin:3px 0 0">
      Lũy kế <?= e($KY_TEN[$ky]) ?> · % kế hoạch năm · diễn biến 12 tháng.
    </p>
  </div>
  <form method="get" class="hang-nut">
    <input type="hidden" name="nam" value="<?= $nam ?>">
    <input type="hidden" name="ky" value="<?= e($ky) ?>">
    <?php if (count($dsKhoa) > 1): ?>
    <label class="an-nhan">Khoa
      <select name="bc" onchange="this.form.submit()">
        <?php foreach ($dsKhoa as $k): ?>
          <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $idKhoaBC ? 'selected' : '' ?>>
            <?= e($k['ten']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>
    <?php if (co_quyen('baocao.xuat')): ?>
      <a class="nut nut-phu" href="/bao-cao.php?nam=<?= $nam ?>&ky=<?= $soThangKy ?>T&pv=khoa&khoa=<?= (int)$idKhoaBC ?>">Xuất Excel khoa</a>
    <?php endif; ?>
  </form>
</div>

<?php if ($theVong): ?>
<!-- Thẻ vòng tròn %KH cho vài chỉ tiêu chính của khoa -->
<div class="luoi-vong">
  <?php foreach ($theVong as $i => $r):
      $ct = $r['ct'];
      $dat = $r['pt'] >= $tienDoKyVong;
      $mau = $dat ? '#0f766e' : '#d97706'; ?>
    <div class="the-vong">
      <div class="tv-trai">
        <div class="tv-vong"><?= svg_donut((float)$r['pt'], $mau) ?>
          <span class="tv-pt"><?= phan_tram($r['pt']) ?></span></div>
      </div>
      <div class="tv-phai">
        <div class="tv-ten"><?= e($ct['ten']) ?></div>
        <div class="tv-so"><?= so($r['th'], $r['le']) ?><span class="tv-dv"> <?= e($ct['don_vi']) ?></span></div>
        <div class="tv-kh phu">/ <?= so($r['kh'], 0) ?> kế hoạch năm</div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($theDuong || $theGauge): ?>
<div class="luoi-bd">
  <?php if ($theDuong): ?>
  <div class="khung-bd">
    <div class="bd-dau">
      <h3>Diễn biến 12 tháng — <?= e($theDuong['ct']['ten']) ?></h3>
      <span class="phu nho">đơn vị: <?= e($theDuong['ct']['don_vi']) ?></span>
    </div>
    <?= bieu_do_duong($theDuong['chuoi'], $theDuong['ct']['don_vi'], $theDuong['le']) ?>
    <?= nhan_thang() ?>
  </div>
  <?php endif; ?>
  <?php if ($theGauge): ?>
  <div class="khung-gauge">
    <div class="bd-dau"><h3><?= e($theGauge['ct']['ten']) ?></h3></div>
    <?= svg_gauge($theGauge['th'], 120, 100) ?>
    <div class="gauge-so"><?= so($theGauge['th'], $theGauge['le']) ?><?= $theGauge['ct']['loai_gia_tri'] === 'TY_LE' ? '%' : '' ?></div>
    <div class="gauge-nhan phu">Mức mong muốn 100%</div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($dongBC): ?>
<h3 class="td-bang">Tất cả chỉ tiêu của khoa</h3>
<div class="cuon-ngang">
<table class="bang bang-bc">
  <thead>
    <tr>
      <th>Nội dung</th>
      <th class="phai">KH năm</th>
      <th class="phai">Đạt <?= $soThangKy ?> tháng</th>
      <?php if (!$anKpi): ?><th class="giua">%KH</th><?php endif; ?>
      <th>Diễn biến 12 tháng</th>
      <th class="phai">Tháng cao nhất</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($dongBC as $r):
      $ct = $r['ct'];
      $mx = 0;
      foreach ($r['chuoi'] as $v) { if ($v !== null && $v > $mx) { $mx = $v; } }
      $dvi = $ct['loai_gia_tri'] === 'TY_LE' ? '%' : $ct['don_vi']; ?>
    <tr>
      <td><strong><?= e($ct['ten']) ?></strong>
        <span class="phu nho">· <?= e($dvi) ?></span></td>
      <td class="phai nho"><?= $r['la_muc_dich'] ? '<span class="phu">—</span>' : so($r['kh'], 0) ?></td>
      <td class="phai"><strong><?= $r['th'] === null ? '<span class="phu">—</span>' : so($r['th'], $r['le']) ?></strong></td>
      <?php if (!$anKpi): ?>
      <td class="giua">
        <?php if (!$r['la_muc_dich'] && $r['pt'] !== null):
            $dat = $r['pt'] >= $tienDoKyVong; ?>
          <span class="vien-pt <?= $dat ? 'pt-dat' : 'pt-cham' ?>"><?= phan_tram($r['pt']) ?></span>
        <?php elseif ($r['la_muc_dich']): ?>
          <span class="phu nho">mức đích</span>
        <?php else: ?><span class="phu">—</span><?php endif; ?>
      </td>
      <?php endif; ?>
      <td>
        <div class="spark" aria-hidden="true">
          <?php for ($t = 1; $t <= 12; $t++):
              $v = $r['chuoi'][$t];
              $h = ($mx > 0 && $v !== null) ? max(6, (int)round($v / $mx * 100)) : 0; ?>
            <span class="spark-c <?= $t === $r['peakT'] ? 'cao' : '' ?>"
                  title="Tháng <?= $t ?>: <?= $v === null ? 'chưa có' : so($v, $r['le']) ?>">
              <i style="height:<?= $h ?>%"></i>
            </span>
          <?php endfor; ?>
        </div>
      </td>
      <td class="phai nho"><?= $r['peakT'] === null
          ? '—' : 'T' . $r['peakT'] . ' · ' . so($r['peakV'], $r['le']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<p class="phu">Khoa chưa có số liệu kỳ này.</p>
<?php endif; ?>
<?php endif; /* khoaBC */ ?>

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
      <td class="phai nhan-cham"><?= phan_tram($c['thieu']) ?></td>
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
<?php dong_trang();

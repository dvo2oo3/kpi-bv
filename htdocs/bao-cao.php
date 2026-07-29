<?php
/**
 * Báo cáo và KPI: theo khoa hoặc toàn viện, mọi kỳ.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/bao_cao.php';

$toi = bat_buoc_dang_nhap();
if (!co_quyen('baocao.toan_vien') && !co_quyen('baocao.khoa_minh')) {
    bat_buoc_quyen('baocao.khoa_minh');
}

$nam = (int)($_GET['nam'] ?? NAM_MAC_DINH);
$ky  = (string)($_GET['ky'] ?? '6T');
if (!in_array($ky, CAC_KY, true)) {
    $ky = '6T';
}
$goc = ($_GET['goc'] ?? 'giao') === 'nang_luc' ? 'nang_luc' : 'giao';

$duocPhep = cac_khoa_duoc_phep();
$dsKhoa = $duocPhep === null
    ? qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten')
    : ($duocPhep ? qAll('SELECT * FROM khoa WHERE hoat_dong = 1 AND id IN ('
        . implode(',', array_fill(0, count($duocPhep), '?')) . ') ORDER BY thu_tu, ten', $duocPhep)
      : []);

$xemToanVien = co_quyen('baocao.toan_vien');
$pham_vi = $_GET['pv'] ?? ($xemToanVien ? 'toan_vien' : 'khoa');
if ($pham_vi === 'toan_vien' && !$xemToanVien) {
    $pham_vi = 'khoa';
}
$idKhoa = (int)($_GET['khoa'] ?? ($dsKhoa[0]['id'] ?? 0));
if ($pham_vi === 'khoa' && $idKhoa && !co_quyen_voi_khoa($idKhoa)) {
    bat_buoc_quyen('baocao.toan_vien');
}

$cacThang = cac_thang_cua_ky($ky);

// Xuất Excel
$xuat = (string)($_GET['xuat'] ?? '');
if ($xuat !== '' && co_quyen('baocao.xuat')) {
    if (($xuat === 'tong_quat' || $xuat === 'day_du') && $xemToanVien) {
        // Cả viện: mỗi khoa một sheet + sheet toàn viện. Có sheet toàn viện nên
        // chỉ người được xem toàn viện mới xuất được cả bộ.
        xuat_bao_cao_bo($nam, $ky, $xuat, $goc, $dsKhoa);
    } else {
        xuat_excel($nam, $ky, $pham_vi, $idKhoa, $goc);   // bản đơn theo phạm vi đang xem
    }
    exit;
}

mo_trang('Báo cáo & KPI');
?>
<h1>Báo cáo thực hiện chỉ tiêu — <?= e(ten_ky($ky)) ?> năm <?= $nam ?></h1>

<form method="get" class="thanh-loc">
  <label>Năm
    <select name="nam" onchange="this.form.submit()">
      <?php for ($n = NAM_MAC_DINH; $n >= NAM_MAC_DINH - 3; $n--): ?>
        <option value="<?= $n ?>" <?= $n === $nam ? 'selected' : '' ?>><?= $n ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <label>Kỳ
    <select name="ky" onchange="this.form.submit()">
      <?php foreach (CAC_KY as $k): ?>
        <option value="<?= $k ?>" <?= $k === $ky ? 'selected' : '' ?>><?= e(ten_ky($k)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Phạm vi
    <select name="pv" onchange="this.form.submit()">
      <?php if ($xemToanVien): ?>
        <option value="toan_vien" <?= $pham_vi === 'toan_vien' ? 'selected' : '' ?>>Toàn viện</option>
      <?php endif; ?>
      <option value="khoa" <?= $pham_vi === 'khoa' ? 'selected' : '' ?>>Theo khoa</option>
    </select>
  </label>
  <?php if ($pham_vi === 'khoa'): ?>
  <label>Khoa
    <select name="khoa" onchange="this.form.submit()">
      <?php foreach ($dsKhoa as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $idKhoa ? 'selected' : '' ?>>
          <?= e($k['ten']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <label>Mốc chỉ tiêu
    <select name="goc" onchange="this.form.submit()">
      <option value="giao"     <?= $goc === 'giao' ? 'selected' : '' ?>>Chỉ tiêu giao (QĐ)</option>
      <option value="nang_luc" <?= $goc === 'nang_luc' ? 'selected' : '' ?>>Chỉ tiêu năng lực</option>
    </select>
  </label>
  <?php if (co_quyen('baocao.xuat')): ?>
    <?php if ($xemToanVien): ?>
      <a class="nut nut-nho nut-phu"
         href="?<?= e(http_build_query(array_merge($_GET, ['xuat' => 'tong_quat']))) ?>"
         title="Cả viện, mỗi khoa một sheet, chỉ nội dung lớn">Xuất tổng quát</a>
      <a class="nut nut-nho nut-phu"
         href="?<?= e(http_build_query(array_merge($_GET, ['xuat' => 'day_du']))) ?>"
         title="Cả viện, có nội dung nhỏ và 12 cột tháng">Xuất đầy đủ</a>
    <?php else: ?>
      <a class="nut nut-nho nut-phu"
         href="?<?= e(http_build_query(array_merge($_GET, ['xuat' => 'excel']))) ?>">Xuất Excel</a>
    <?php endif; ?>
  <?php endif; ?>
</form>

<?php
$bang = $pham_vi === 'toan_vien'
    ? bang_toan_vien($nam, $cacThang, $goc)
    : bang_theo_khoa($nam, $cacThang, $idKhoa, $goc);

// Tổng hợp mức độ đạt
$dem = ['dat' => 0, 'canh_bao' => 0, 'khong_dat' => 0, 'vuot' => 0, 'na' => 0];
foreach ($bang as $d) {
    if ($d['cap'] === 0) {
        $dem[$d['kpi']['danh_gia']]++;
    }
}
$tienDoKyVong = count($cacThang) === 12 ? 100 : round(max($cacThang) / 12 * 100);
?>

<div class="the-tom-tat">
  <div class="o-tom-tat o-dat"><strong><?= $dem['dat'] ?></strong><span>Đạt</span></div>
  <div class="o-tom-tat o-canh-bao"><strong><?= $dem['canh_bao'] ?></strong><span>Gần đạt</span></div>
  <div class="o-tom-tat o-khong-dat"><strong><?= $dem['khong_dat'] ?></strong><span>Chưa đạt</span></div>
  <div class="o-tom-tat o-vuot"><strong><?= $dem['vuot'] ?></strong><span>Vượt/quá tải</span></div>
  <div class="o-tom-tat o-na"><strong><?= $dem['na'] ?></strong><span>Chưa đủ dữ liệu</span></div>
</div>

<p class="phu">
  Hết <?= e(ten_ky($ky)) ?>, tiến độ kỳ vọng là <strong><?= $tienDoKyVong ?>%</strong> kế hoạch năm.
</p>
<p class="phu">
  <strong>So KH kỳ</strong> so với chỉ tiêu đã chia cho riêng kỳ này — dùng để chấm đạt/chưa đạt,
  công bằng khi năm chưa hết. <strong>So KH năm</strong> so với chỉ tiêu cả năm — đúng cách tính
  trong bảng Excel đang dùng, là con số in ra báo cáo gửi Sở.
  Chỉ tiêu <em>thấp là tốt</em> và <em>đích 100%</em> được chấm theo cách riêng, không so thẳng phần trăm.
</p>

<div class="cuon-ngang">
<table class="bang">
  <thead>
    <tr>
      <th style="width:30%">Nội dung</th>
      <th>Đơn vị</th>
      <th class="phai">Chỉ tiêu kỳ</th>
      <th class="phai">Thực hiện</th>
      <th class="phai">So KH kỳ</th>
      <th class="phai">So KH năm</th>
      <th>Đánh giá</th>
      <th class="phai">Cùng kỳ <?= $nam - 1 ?></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($bang as $d):
      $le = in_array($d['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true) ? 1 : 0; ?>
    <tr class="<?= $d['cap'] ? 'dong-con' : '' ?>">
      <td><?= $d['cap'] ? '<span class="thut">↳</span> ' : '' ?><?= e($d['ct']['ten']) ?></td>
      <td class="nho"><?= e($d['ct']['don_vi']) ?></td>
      <td class="phai"><?= so($d['chi_tieu'], $le) ?></td>
      <td class="phai"><strong><?= so($d['thuc_hien'], $le) ?></strong></td>
      <td class="phai"><?= phan_tram($d['kpi']['phan_tram']) ?></td>
      <td class="phai nho"><?= phan_tram($d['pt_nam']) ?></td>
      <td><span class="dg dg-<?= e($d['kpi']['danh_gia']) ?>"><?= e($d['kpi']['mo_ta']) ?></span></td>
      <td class="phai nho"><?= so($d['nam_truoc'], $le) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if (!$bang): ?>
  <div class="tb tb-canh-bao">
    Chưa có dữ liệu. Cần nạp <a href="/danh-muc-chi-tieu.php">danh mục chỉ tiêu</a>,
    <a href="/giao-chi-tieu.php">giao chỉ tiêu</a> rồi nhập số liệu.
  </div>
<?php endif; ?>
<?php dong_trang();

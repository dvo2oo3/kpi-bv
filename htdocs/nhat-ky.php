<?php
require_once __DIR__ . '/app/layout.php';

$toi = bat_buoc_quyen('nhatky.xem');

$trang = max(1, (int)($_GET['trang'] ?? 1));
$moiTrang = 100;
$loc = trim((string)($_GET['loc'] ?? ''));

$dieuKien = '';
$thamSo = [];
if ($loc !== '') {
    $dieuKien = 'WHERE hanh_dong LIKE ? OR ten_dang_nhap LIKE ? OR doi_tuong LIKE ?';
    $thamSo = ["%$loc%", "%$loc%", "%$loc%"];
}

$tong = (int)qVal("SELECT COUNT(*) FROM nhat_ky $dieuKien", $thamSo);
$soTrang = max(1, (int)ceil($tong / $moiTrang));
$trang = min($trang, $soTrang);

$ds = qAll("SELECT * FROM nhat_ky $dieuKien ORDER BY thoi_diem DESC, id DESC
            LIMIT $moiTrang OFFSET " . (($trang - 1) * $moiTrang), $thamSo);

mo_trang('Nhật ký hệ thống');
?>
<h1>Nhật ký hệ thống</h1>

<form method="get" class="thanh-loc">
  <label>Tìm
    <input name="loc" value="<?= e($loc) ?>" placeholder="hành động, người dùng, đối tượng">
  </label>
  <button class="nut nut-nho" type="submit">Lọc</button>
  <span class="phu"><?= so((float)$tong) ?> bản ghi</span>
</form>

<div class="cuon-ngang">
<table class="bang">
  <thead>
    <tr><th>Thời điểm</th><th>Người dùng</th><th>Hành động</th>
        <th>Đối tượng</th><th>Chi tiết</th><th>IP</th></tr>
  </thead>
  <tbody>
  <?php foreach ($ds as $r): ?>
    <tr>
      <td class="nho"><?= e(ngay_gio($r['thoi_diem'])) ?></td>
      <td><?= e($r['ten_dang_nhap'] ?? '—') ?></td>
      <td><code><?= e($r['hanh_dong']) ?></code></td>
      <td class="nho"><?= e($r['doi_tuong'] ?? '') ?></td>
      <td class="nho"><?= e($r['chi_tiet'] ?? '') ?></td>
      <td class="nho"><?= e($r['dia_chi_ip'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if ($soTrang > 1): ?>
<p class="phan-trang">
  <?php for ($i = max(1, $trang - 3); $i <= min($soTrang, $trang + 3); $i++): ?>
    <a class="<?= $i === $trang ? 'dang-xem' : '' ?>"
       href="?trang=<?= $i ?>&loc=<?= urlencode($loc) ?>"><?= $i ?></a>
  <?php endfor; ?>
  <span class="phu">/ <?= $soTrang ?> trang</span>
</p>
<?php endif; ?>
<?php dong_trang();

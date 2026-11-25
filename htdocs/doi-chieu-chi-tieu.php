<?php
/**
 * Đối chiếu chỉ tiêu toàn viện (CHỈ XEM): mỗi chỉ tiêu hiện cạnh nhau
 *   - Tổng đã giao khoa  (app cộng chỉ tiêu các khoa)
 *   - Viện đưa ra        (số chính thức đặt tay; chưa đặt = theo tổng khoa)
 *   - Chênh lệch + % lệch
 * Để thấy nhanh chỗ chỉ tiêu viện khác tổng khoa. Sửa số thì sang trang
 * "Chỉ tiêu toàn viện".
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/danh_muc.php';
require_once __DIR__ . '/app/bao_cao.php';

bat_buoc_quyen('baocao.toan_vien');

$nam     = nam_dang_lam();
$chiLech = !empty($_GET['lech']);
$hienCon = !empty($_GET['con']);
$bang    = bang_toan_vien($nam, range(1, 12), 'giao');
$STV     = so_toan_vien_cua_nam($nam);

$dong = []; $soLech = 0; $soRieng = 0; $soLon = 0;
foreach ($bang as $d) {
    $id   = (int)$d['ct']['id'];
    $tong = $d['may_cong_kh'];
    $ov   = (isset($STV[$id]) && $STV[$id]['chi_tieu_giao'] !== null && $STV[$id]['chi_tieu_giao'] !== '')
          ? (float)$STV[$id]['chi_tieu_giao'] : null;
    $lech = ($ov !== null && $tong !== null) ? ($ov - $tong) : null;
    $pct  = ($lech !== null && $tong != 0) ? ($lech / $tong * 100) : null;
    $laLech = $lech !== null && abs($lech) > 0.001;
    if ($ov !== null) { $soRieng++; }
    if ($laLech)      { $soLech++; }
    if ((int)$d['cap'] === 0) { $soLon++; }
    $dong[] = ['d' => $d, 'tong' => $tong, 'ov' => $ov, 'lech' => $lech, 'pct' => $pct, 'laLech' => $laLech,
               'le' => in_array($d['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true) ? 1 : 0];
}

mo_trang('Đối chiếu chỉ tiêu');
?>
<style>
  .dc-tt td { vertical-align: middle; }
  .dc-row-lech td { background: #fffbeb; }
  html[data-theme="dark"] .dc-row-lech td { background: #2a2310; }
  .dc-row-lech td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
  .dc-lech { font-weight: 600; color: #b45309; white-space: nowrap; }
  html[data-theme="dark"] .dc-lech { color: #fbbf24; }
  .dc-bang td, .dc-bang th { padding-top: 11px; padding-bottom: 11px; }
  .nhan-tay { display: inline-block; font-size: 10.5px; font-weight: 700; color: #6d28d9;
    background: #f3e8ff; border-radius: 999px; padding: 0 7px; margin-left: 4px; vertical-align: middle; }
  html[data-theme="dark"] .nhan-tay { background: #2a2440; color: #c4b5fd; }
</style>

<div class="dau-muc">
  <div>
    <h1 style="margin:0">Đối chiếu chỉ tiêu toàn viện</h1>
    <p class="phu" style="margin:3px 0 0">So <strong>tổng chỉ tiêu đã giao cho các khoa</strong> với
      <strong>chỉ tiêu viện đưa ra</strong> (số chính thức). Chỗ <span class="dc-lech">lệch</span> là viện giao
      khác với tổng đã chia về khoa.</p>
  </div>
  <a class="nut nut-phu" href="/chi-tieu-toan-vien.php?nam=<?= (int)$nam ?>">Sửa chỉ tiêu toàn viện →</a>
</div>

<div class="the-tom-tat">
  <div class="o-tom-tat"><strong><?= (int)$soLon ?></strong><span>Chỉ tiêu lớn</span></div>
  <div class="o-tom-tat o-vuot"><strong><?= (int)$soRieng ?></strong><span>Viện đặt riêng</span></div>
  <div class="o-tom-tat o-canh-bao"><strong><?= (int)$soLech ?></strong><span>Lệch tổng khoa</span></div>
</div>

<?php if ($soRieng === 0): ?>
<div class="tb tb-ok" style="margin:12px 0">Chưa đặt chỉ tiêu viện riêng nào — mọi dòng đang <strong>= tổng khoa</strong>.
  Vào <a href="/chi-tieu-toan-vien.php?nam=<?= (int)$nam ?>">Chỉ tiêu toàn viện</a> để đặt số chính thức, rồi quay lại đây đối chiếu.</div>
<?php endif; ?>

<div class="the-hop" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
  <label>Năm
    <input type="number" value="<?= (int)$nam ?>" onchange="location.href='/doi-chieu-chi-tieu.php?nam='+this.value<?= $chiLech ? "+'&lech=1'" : '' ?><?= $hienCon ? "+'&con=1'" : '' ?>" style="width:100px">
  </label>
  <label class="o-chon" style="display:inline-flex;gap:6px;align-items:center">
    <input type="checkbox" <?= $chiLech ? 'checked' : '' ?> onchange="location.href='/doi-chieu-chi-tieu.php?nam=<?= (int)$nam ?>'+(this.checked?'&lech=1':'')<?= $hienCon ? "+'&con=1'" : '' ?>"> Chỉ hiện dòng lệch
  </label>
  <label class="o-chon" style="display:inline-flex;gap:6px;align-items:center">
    <input type="checkbox" <?= $hienCon ? 'checked' : '' ?> onchange="location.href='/doi-chieu-chi-tieu.php?nam=<?= (int)$nam ?>'<?= $chiLech ? "+'&lech=1'" : '' ?>+(this.checked?'&con=1':'')"> Hiện cả nội dung nhỏ
  </label>
</div>

<div class="cuon-ngang">
  <table class="bang dc-bang">
    <thead>
      <tr>
        <th style="min-width:250px">Nội dung</th>
        <th>ĐVT</th>
        <th class="phai">Tổng đã giao khoa</th>
        <th class="phai">Viện đưa ra</th>
        <th class="phai">Chênh lệch</th>
        <th class="phai">% lệch</th>
      </tr>
    </thead>
    <tbody>
      <?php $hienDong = 0; foreach ($dong as $r):
          $d = $r['d'];
          if (!$hienCon && (int)$d['cap'] !== 0) { continue; }
          if ($chiLech && !$r['laLech']) { continue; }
          $hienDong++;
          $le = $r['le'];
      ?>
        <tr class="<?= $d['cap'] ? 'dong-con' : '' ?><?= $r['laLech'] ? ' dc-row-lech' : '' ?>">
          <td><?= $d['cap'] ? '<span class="thut">↳</span> ' : '' ?><strong><?= e($d['ct']['ten']) ?></strong></td>
          <td class="nho"><?= e($d['ct']['don_vi']) ?></td>
          <td class="phai"><?= $r['tong'] === null ? '<span class="phu">—</span>' : so($r['tong'], $le) ?></td>
          <td class="phai">
            <?php if ($r['ov'] !== null): ?>
              <strong><?= so($r['ov'], $le) ?></strong><span class="nhan-tay" title="Số chính thức viện đưa ra">chính thức</span>
            <?php else: ?><span class="phu">= tổng khoa</span><?php endif; ?>
          </td>
          <td class="phai"><?php if (!$r['laLech']): ?><span class="phu">—</span>
            <?php else: ?><span class="dc-lech"><?= ($r['lech'] > 0 ? '+' : '−') . so(abs($r['lech']), $le) ?></span><?php endif; ?></td>
          <td class="phai"><?php if (!$r['laLech'] || $r['pct'] === null): ?><span class="phu">—</span>
            <?php else: ?><span class="dc-lech"><?= ($r['pct'] > 0 ? '+' : '−') . number_format(abs($r['pct']), 1, ',', '.') ?>%</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($hienDong === 0): ?>
        <tr><td colspan="6" class="phu" style="text-align:center;padding:18px">Không có dòng nào khớp bộ lọc.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<p class="phu nho" style="margin-top:10px"><span class="nhan-tay">chính thức</span> = viện đặt số riêng (đè tổng khoa).
  Mặc định chỉ hiện <strong>chỉ tiêu lớn</strong> — tích “Hiện cả nội dung nhỏ” để xem chi tiết. Sửa số:
  <a href="/chi-tieu-toan-vien.php?nam=<?= (int)$nam ?>">Chỉ tiêu toàn viện</a>.</p>
<?php dong_trang();

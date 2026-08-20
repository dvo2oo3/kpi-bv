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
      <select name="bc" data-bc-select>
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

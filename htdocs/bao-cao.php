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
// Mặc định "đến nay": lũy kế tới tháng gần nhất đã xong (năm cũ = cả 12 tháng).
$namNay = (int)date('Y');
$thangDenNay = ($nam === $namNay) ? min(12, max(1, (int)date('n') - 1)) : 12;
$kyDenNay = $thangDenNay . 'T';
$ky = (string)($_GET['ky'] ?? $kyDenNay);
if (!in_array($ky, CAC_KY, true) && !preg_match('/^\d{1,2}T$/', $ky)) {
    $ky = $kyDenNay;
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

// Cho kéo-thả đổi vị trí NGAY trong báo cáo — chỉ ở kiểu "Theo khoa", người có
// quyền sửa chỉ tiêu và có quyền với khoa đó. Ghi vào thứ tự RIÊNG của khoa
// (chi_tieu_ap_dung.thu_tu) — giống hệt trang Giao chỉ tiêu.
$duocSapXep = $pham_vi === 'khoa' && $idKhoa
    && co_quyen('chitieu.sua') && co_quyen_voi_khoa($idKhoa);

if (la_post() && post('viec') === 'sap_xep' && $duocSapXep) {
    kiem_tra_csrf();
    $ids = array_values(array_filter(array_map('intval', explode(',', post('ids')))));
    $thuoc = [];
    foreach (qAll('SELECT id_chi_tieu FROM chi_tieu_ap_dung WHERE id_khoa = ?', [$idKhoa]) as $r) {
        $thuoc[(int)$r['id_chi_tieu']] = true;
    }
    $ids = array_values(array_filter($ids, fn($id) => isset($thuoc[$id])));
    if ($ids) {
        db()->beginTransaction();
        $tt = 10;
        foreach ($ids as $id) {
            q('UPDATE chi_tieu_ap_dung SET thu_tu = ? WHERE id_chi_tieu = ? AND id_khoa = ?',
                [$tt, $id, $idKhoa]);
            $tt += 10;
        }
        db()->commit();
        ghi_nhat_ky('SAP_XEP_CHI_TIEU', (string)$idKhoa, count($ids) . ' dòng (từ báo cáo)');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
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
      <?php if (!in_array($kyDenNay, CAC_KY, true)): ?>
        <option value="<?= $kyDenNay ?>" <?= $ky === $kyDenNay ? 'selected' : '' ?>><?= $thangDenNay ?> tháng (đến nay)</option>
      <?php endif; ?>
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

<?php if ($duocSapXep): ?>
  <p class="phu" style="margin:.25rem 0"><span class="the the-nho">Kéo <span aria-hidden="true">⠿</span> hoặc bấm ▲▼ để đổi vị trí — chỉ đổi thứ tự của khoa này.</span></p>
<?php endif; ?>
<div class="cuon-ngang">
<table class="bang<?= $duocSapXep ? ' bang-sapxep' : '' ?>" id="bang-bc"<?= $duocSapXep ? ' data-sapxep="1"' : '' ?>>
  <thead>
    <tr>
      <th class="giua" style="width:42px">STT</th>
      <th style="width:28%">Nội dung</th>
      <th>Đơn vị</th>
      <th class="phai">Chỉ tiêu kỳ</th>
      <th class="phai">Chỉ tiêu năm</th>
      <th class="phai">Thực hiện</th>
      <th class="phai">So KH kỳ</th>
      <th class="phai">So KH năm</th>
      <th>Đánh giá</th>
      <th class="phai">Cùng kỳ <?= $nam - 1 ?></th>
    </tr>
  </thead>
  <tbody>
  <?php $sttDem = 0; foreach ($bang as $d):
      $le = in_array($d['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true) ? 1 : 0;
      if (!$d['cap']) { $sttDem++; }   // đánh số chạy cho dòng lớn (đúng cả 2 kiểu xem) ?>
    <tr class="<?= $d['cap'] ? 'dong-con' : '' ?>"<?php if ($duocSapXep): ?> data-id="<?= (int)$d['ct']['id'] ?>" data-cha="<?= $d['ct']['id_cha'] !== null ? (int)$d['ct']['id_cha'] : '' ?>"<?php endif; ?>>
      <td class="giua nho bc-stt"><span class="bc-num"><?= $d['cap'] ? '' : $sttDem ?></span><?php if ($duocSapXep && !$d['cap']): ?><span class="bc-dk"><span class="bc-keo" draggable="true" title="Kéo để đổi vị trí">⠿</span><button type="button" class="bc-mui" title="Chuyển lên" onclick="bcDoiCho(<?= (int)$d['ct']['id'] ?>,-1)">▲</button><button type="button" class="bc-mui" title="Chuyển xuống" onclick="bcDoiCho(<?= (int)$d['ct']['id'] ?>,1)">▼</button></span><?php endif; ?></td>
      <td><?= $d['cap'] ? '<span class="thut">↳</span> ' : '' ?><?= e($d['ct']['ten']) ?></td>
      <td class="nho"><?= e($d['ct']['don_vi']) ?></td>
      <td class="phai"><?= so($d['chi_tieu'], $le) ?></td>
      <td class="phai nho"><?= so($d['chi_tieu_nam'], $le) ?></td>
      <td class="phai">
        <strong><?= so($d['thuc_hien'], $le) ?></strong>
        <?php // Thay đổi so với cùng kỳ năm trước — tô màu theo hướng tốt/xấu
        $th = $d['thuc_hien']; $tr = $d['nam_truoc'];
        if ($th !== null && $tr !== null && (float)$tr != 0.0):
            $tang  = (float)$th >= (float)$tr;
            $delta = ((float)$th - (float)$tr) / abs((float)$tr) * 100;
            $huong = $d['ct']['huong'] ?? 'CAO_TOT';
            $lop   = $huong === 'DICH_CO_DINH' ? 'trung'
                   : (($huong === 'THAP_TOT' ? !$tang : $tang) ? 'tot' : 'xau'); ?>
          <span class="delta delta-<?= $lop ?>" title="So với cùng kỳ <?= $nam - 1 ?>: <?= so($tr, $le) ?>">
            <?= $tang ? '▲' : '▼' ?> <?= so(abs($delta), 1) ?>%
          </span>
        <?php endif; ?>
      </td>
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
    Chưa có dữ liệu. Cần nạp <a href="/danh-muc-chi-tieu.php">thư viện chỉ tiêu</a>,
    <a href="/giao-chi-tieu.php">giao chỉ tiêu</a> rồi nhập số liệu.
  </div>
<?php endif; ?>

<?php if ($duocSapXep): ?>
<input type="hidden" id="bc-csrf" value="<?= e(csrf_token()) ?>">
<style>
  /* Cột STT: rộng cố định, để đủ chỗ cho nút → hover không làm bảng co giãn. */
  .bang-sapxep .bc-stt { position: relative; white-space: nowrap; }
  .bang-sapxep .bc-num { display: inline-block; min-width: 54px; text-align: center; }
  .bang-sapxep .bc-keo { cursor: grab; color: #94a3b8; user-select: none; }
  .bang-sapxep .bc-mui { border: 0; background: transparent; color: #64748b; cursor: pointer; font-size: 11px; padding: 0 1px; line-height: 1; }
  .bang-sapxep .bc-mui:hover { color: var(--xanh-500, #2563eb); }
  .bang-sapxep tr.dang-keo { opacity: .45; }
  /* Nút = lớp PHỦ tuyệt đối (không chiếm chỗ) → không đẩy cột; chỉ hiện khi hover. */
  .bang-sapxep .bc-dk {
    position: absolute; inset: 0; display: flex; align-items: center;
    justify-content: center; gap: 1px; visibility: hidden;
  }
  .bang-sapxep tr:hover .bc-dk { visibility: visible; }
  .bang-sapxep tr:hover .bc-num { visibility: hidden; }
  @media (hover: none) {
    .bang-sapxep .bc-dk { visibility: visible; }
    .bang-sapxep tr[data-id]:not(.dong-con) .bc-num { visibility: hidden; }
  }
</style>
<script>
(function () {
  var bang = document.getElementById('bang-bc');
  if (!bang || !bang.dataset.sapxep) { return; }
  var tbody = bang.querySelector('tbody');
  function laCon(tr) { return tr.classList.contains('dong-con'); }
  function khoiCua(tr) {
    var a = [tr];
    if (!laCon(tr)) { var n = tr.nextElementSibling; while (n && laCon(n)) { a.push(n); n = n.nextElementSibling; } }
    return a;
  }
  function luu() {
    var ids = Array.prototype.map.call(tbody.querySelectorAll('tr[data-id]'), function (r) { return r.dataset.id; });
    var fd = new FormData();
    fd.append('viec', 'sap_xep'); fd.append('ids', ids.join(','));
    fd.append('_csrf', document.getElementById('bc-csrf').value);
    fetch(location.pathname + location.search, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (!d.ok) { location.reload(); } })
      .catch(function () { location.reload(); });
  }
  /* Kéo-thả (chỉ kéo dòng lớn — cả khối cha + con di theo) */
  var keo = null, khoi = [];
  tbody.addEventListener('dragstart', function (e) {
    var h = e.target.closest('.bc-keo'); if (!h) { e.preventDefault(); return; }
    keo = h.closest('tr'); khoi = khoiCua(keo);
    e.dataTransfer.effectAllowed = 'move';
    setTimeout(function () { khoi.forEach(function (r) { r.classList.add('dang-keo'); }); }, 0);
  });
  tbody.addEventListener('dragend', function () { khoi.forEach(function (r) { r.classList.remove('dang-keo'); }); keo = null; khoi = []; });
  tbody.addEventListener('dragover', function (e) {
    if (!keo) { return; }
    var tr = e.target.closest('tr[data-id]');
    if (!tr || laCon(tr) || khoi.indexOf(tr) >= 0) { return; }
    e.preventDefault();
  });
  tbody.addEventListener('drop', function (e) {
    if (!keo) { return; }
    var tr = e.target.closest('tr[data-id]');
    if (!tr || laCon(tr) || khoi.indexOf(tr) >= 0) { return; }
    e.preventDefault();
    var tren = e.clientY < tr.getBoundingClientRect().top + tr.offsetHeight / 2;
    var moc = tren ? tr : (function () { var k = khoiCua(tr); return k[k.length - 1].nextElementSibling; })();
    khoi.forEach(function (r) { tbody.insertBefore(r, moc); });
    luu();
  });
  /* Nút ▲ ▼ (chạy cả trên điện thoại) */
  window.bcDoiCho = function (id, dir) {
    var tr = tbody.querySelector('tr[data-id="' + id + '"]'); if (!tr) { return; }
    var block = khoiCua(tr);
    var ke = dir > 0 ? block[block.length - 1].nextElementSibling : tr.previousElementSibling;
    while (ke && laCon(ke)) { ke = dir > 0 ? ke.nextElementSibling : ke.previousElementSibling; }
    if (!ke || !ke.dataset || !ke.dataset.id) {
      if (window.toast) { toast('Đã ở ' + (dir > 0 ? 'cuối' : 'đầu') + ' rồi.', 'canh-bao'); }
      return;
    }
    var blockKe = khoiCua(ke);
    var moc = dir > 0 ? blockKe[blockKe.length - 1].nextElementSibling : blockKe[0];
    block.forEach(function (r) { tbody.insertBefore(r, moc); });
    luu();
  };
})();
</script>
<?php endif; ?>
<?php dong_trang();

<?php
/**
 * Xem trước khuôn xuất "giống bản Sở" (QĐ33b từng khoa) — dạng HTML, TẤT CẢ khoa
 * trên một trang, bày thành TAB (như các sheet của Excel). Mỗi tab có badge:
 *   ✓ = đủ số · ⚠ N = còn N dòng sẽ TRỐNG khi xuất.
 *
 * NỐI NGUỒN NGAY TẠI CHỖ: mỗi dòng có ô "Nguồn (bấm để sửa)" → chọn 1 hay nhiều
 * chỉ tiêu thực tế của khoa (nhiều = cộng gộp). Lưu vào cai_dat['khuon_nguon'];
 * cả trang này lẫn bộ xuất Excel đều đọc map đó → gặp lệch mã admin TỰ sửa, không
 * cần đụng code.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/danh_muc.php';
require_once __DIR__ . '/app/bao_cao.php';
require_once __DIR__ . '/app/bao_cao_gd.php';
require_once __DIR__ . '/app/khuon_so_khoa.php';   // KHUON_SO_KHOA / KHUON_SO_META

bat_buoc_quyen('baocao.giam_doc');

$nam         = nam_dang_lam();
$namNay      = (int)date('Y');
$thangDenNay = ($nam === $namNay) ? min(12, max(1, (int)date('n') - 1)) : 12;
$cacThang    = range(1, $thangDenNay);

/** $idx (mã → dòng bang_theo_khoa) của một khoa theo kỳ đang xem. */
function xtk_idx_khoa(int $idKhoa, int $nam, array $cacThang): array
{
    $idx = [];
    foreach (bang_theo_khoa($nam, $cacThang, $idKhoa, 'giao') as $d) {
        $idx[$d['ct']['ma']] = $d;
    }
    return $idx;
}

/** Tính 1 dòng khuôn (đã áp map) → mảng dữ liệu hiển thị. */
function xtk_tinh_dong(array $map, string $khoaMa, int $ri, $maMacDinh, array $idx): array
{
    $maEff = khuon_ma_hieu_dung($map, $khoaMa, $ri, $maMacDinh);
    $d     = gd_dong_khuon($maEff, $idx);
    $trong = $maMacDinh !== null && ($d === null
        || (($d['chi_tieu_nam'] ?? null) === null && ($d['thuc_hien'] ?? null) === null));
    $maDung = $maEff === null ? [] : (is_array($maEff) ? array_values($maEff) : [$maEff]);
    $tenMa  = [];
    foreach ($maDung as $m) { $tenMa[] = $idx[$m]['ct']['ten'] ?? $m; }
    return [
        'maDung' => $maDung,
        'maTxt'  => implode(' + ', $tenMa),
        'kh'     => $d['chi_tieu_nam'] ?? null,
        'th'     => $d['thuc_hien'] ?? null,
        'pt'     => $d['pt_nam'] ?? null,
        'le'     => ($d && in_array($d['ct']['loai_gia_tri'] ?? 'DEM', ['TY_LE', 'TRUNG_BINH'], true)) ? 1 : 0,
        'trong'  => $trong,
    ];
}

/* ---------- AJAX: lưu nối nguồn 1 dòng, trả về dòng đã tính lại ---------- */
if (la_post() && post('viec') === 'luu_nguon') {
    header('Content-Type: application/json; charset=utf-8');
    kiem_tra_csrf();
    $khoaMa = (string)post('khoa_ma');
    $ri     = (int)post('ri');
    $dsMa   = $_POST['ma'] ?? [];
    $dsMa   = is_array($dsMa) ? array_values(array_filter(array_map('strval', $dsMa), fn($x) => $x !== '')) : [];

    $rows = KHUON_SO_KHOA[$khoaMa] ?? [];
    if (!isset($rows[$ri]) || $rows[$ri][4] === null) {
        echo json_encode(['ok' => false, 'loi' => 'Dòng không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $dsMaArg = post('macdinh') === '1' ? null : $dsMa;   // null = bỏ override, về mã mặc định
    $mapMoi  = khuon_map_dat($khoaMa, $ri, $dsMaArg);     // ghi + trả map mới (bộ nhớ)

    $kId = (int)(qVal('SELECT id FROM khoa WHERE ma = ? AND hoat_dong = 1', [$khoaMa]) ?? 0);
    $idx = $kId ? xtk_idx_khoa($kId, $nam, $cacThang) : [];
    $dong = xtk_tinh_dong($mapMoi, $khoaMa, $ri, $rows[$ri][4], $idx);

    // Đếm lại tổng dòng trống của khoa (để cập nhật badge).
    $soTrong = 0;
    foreach ($rows as $i => $r) {
        if (xtk_tinh_dong($mapMoi, $khoaMa, $i, $r[4], $idx)['trong']) { $soTrong++; }
    }
    echo json_encode([
        'ok' => true, 'soTrong' => $soTrong,
        'maTxt' => $dong['maTxt'], 'sel' => implode(',', $dong['maDung']), 'trong' => $dong['trong'],
        'kh' => $dong['kh'] === null ? null : so($dong['kh'], $dong['le']),
        'th' => $dong['th'] === null ? null : so($dong['th'], $dong['le']),
        'pt' => $dong['pt'] === null ? null : phan_tram($dong['pt']),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- GET: dựng sẵn mọi khoa có khuôn ---------- */
$dsKhoa = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');
$map    = khuon_map();
$ds     = [];      // mỗi phần tử: khoa + dòng + số trống + danh sách chỉ tiêu (cho ô chọn)
foreach ($dsKhoa as $k) {
    $maK  = $k['ma'];
    $rows = KHUON_SO_KHOA[$maK] ?? [];
    if (!$rows) { continue; }
    $idx = xtk_idx_khoa((int)$k['id'], $nam, $cacThang);
    // Danh sách chỉ tiêu của khoa cho ô chọn (ma, tên) — sắp theo tên.
    $dsCt = [];
    foreach ($idx as $m => $d) { $dsCt[] = ['ma' => $m, 'ten' => $d['ct']['ten']]; }
    usort($dsCt, fn($a, $b) => strcmp($a['ten'], $b['ten']));
    $dong = []; $soTrong = 0;
    foreach ($rows as $ri => [$stt, $ten, $dvt, $cap, $maCt]) {
        $t = xtk_tinh_dong($map, $maK, $ri, $maCt, $idx);
        if ($t['trong']) { $soTrong++; }
        $dong[] = ['ri' => $ri, 'stt' => $stt, 'ten' => $ten, 'dvt' => $dvt, 'cap' => $cap,
                   'coMa' => $maCt !== null] + $t;
    }
    $ds[] = ['id' => (int)$k['id'], 'ten' => $k['ten'], 'ma' => $maK, 'tab' => KHUON_SO_META[$maK][0] ?? $maK,
             'dong' => $dong, 'soTrong' => $soTrong, 'dsCt' => $dsCt];
}
$soKhoaLoi = count(array_filter($ds, fn($x) => $x['soTrong'] > 0));
$active = 0;
foreach ($ds as $i => $x) { if ($x['soTrong'] > 0) { $active = $i; break; } }

mo_trang('Xem trước khuôn');
?>
<input type="hidden" id="xtk-csrf" value="<?= e(csrf_token()) ?>">
<div class="dau-muc">
  <div>
    <h1 style="margin:0">Xem trước &amp; nối nguồn khuôn</h1>
    <p class="phu" style="margin:3px 0 0">Mỗi khoa một tab. Dòng sẽ <strong>trống</strong> khi xuất được tô cảnh báo —
      bấm ô <strong>Nguồn</strong> của dòng đó để chọn chỉ tiêu lấy số (nhiều = cộng gộp). Lũy kế đến hết tháng <?= (int)$thangDenNay ?>.</p>
  </div>
  <a class="nut nut-phu" href="/bao-cao-gd.php?nam=<?= (int)$nam ?>">Sang Xuất Excel →</a>
</div>

<form method="get" style="display:flex;gap:12px;align-items:flex-end;margin-bottom:12px">
  <label>Năm
    <input type="number" name="nam" value="<?= (int)$nam ?>" onchange="this.form.submit()" style="width:100px">
  </label>
  <div style="flex:1"></div>
  <div class="phu" id="xtk-tongket" style="padding-bottom:6px">
    <?php if (!$ds): ?>Chưa có khoa nào có khuôn.
    <?php elseif ($soKhoaLoi === 0): ?><span style="color:#15803d;font-weight:600">Tất cả <?= count($ds) ?> khoa đều đủ số.</span>
    <?php else: ?><strong style="color:#b45309"><?= (int)$soKhoaLoi ?>/<?= count($ds) ?> khoa</strong> còn dòng trống.<?php endif; ?>
  </div>
</form>

<?php if ($ds): ?>
<div class="xtk-tabs" role="tablist">
  <?php foreach ($ds as $i => $x): ?>
    <button type="button" class="xtk-tab<?= $i === $active ? ' active' : '' ?>" data-xtk="<?= $i ?>" role="tab">
      <span class="xtk-tab-ten"><?= e($x['tab']) ?></span>
      <span class="xtk-badge <?= $x['soTrong'] > 0 ? 'xtk-badge-loi' : 'xtk-badge-ok' ?>" data-khoa="<?= e($x['ma']) ?>">
        <?= $x['soTrong'] > 0 ? '⚠ ' . (int)$x['soTrong'] : '✓' ?></span>
    </button>
  <?php endforeach; ?>
</div>

<?php foreach ($ds as $i => $x): ?>
<section class="xtk-panel" data-xtk="<?= $i ?>" data-khoa="<?= e($x['ma']) ?>"<?= $i === $active ? '' : ' hidden' ?>>
  <div class="cuon-ngang">
    <table class="bang bang-xtk">
      <thead>
        <tr>
          <th class="giua" style="width:42px">TT</th>
          <th style="min-width:210px">Nội dung</th>
          <th>ĐVT</th>
          <th style="min-width:200px">Nguồn (bấm để sửa)</th>
          <th class="phai">Chỉ tiêu năm</th>
          <th class="phai">Đạt <?= (int)$thangDenNay ?> tháng</th>
          <th class="giua">%KH</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($x['dong'] as $r): ?>
          <tr class="<?= $r['cap'] ? 'dong-con' : '' ?><?= $r['trong'] ? ' dong-canh-bao' : '' ?>" data-ri="<?= (int)$r['ri'] ?>">
            <td class="giua nho"><?= e($r['stt']) ?></td>
            <td><?= $r['cap'] ? '<span class="thut">↳</span> ' : '' ?><?= e($r['ten']) ?></td>
            <td class="nho"><?= e($r['dvt']) ?></td>
            <td class="xtk-nguon-o">
              <?php if ($r['coMa']): ?>
                <button type="button" class="xtk-nguon" data-ki="<?= $i ?>" data-ri="<?= (int)$r['ri'] ?>"
                        data-sel="<?= e(implode(',', $r['maDung'])) ?>">
                  <span class="xtk-nguon-txt"><?= $r['maTxt'] !== '' ? e($r['maTxt']) : '<span class="phu">— chọn chỉ tiêu —</span>' ?></span>
                  <i class="xtk-but" aria-hidden="true">✎</i>
                </button>
              <?php else: ?><span class="phu">—</span><?php endif; ?>
            </td>
            <td class="phai nho xtk-kh"><?= so($r['kh'], $r['le']) ?></td>
            <td class="phai xtk-th"><strong><?= $r['trong'] ? '<span class="phu">— trống —</span>' : so($r['th'], $r['le']) ?></strong></td>
            <td class="giua xtk-pt"><?= $r['pt'] === null ? '<span class="phu">—</span>' : phan_tram($r['pt']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endforeach; ?>

<script>
window.XTK_CT = <?= json_encode(array_map(fn($x) => $x['dsCt'], $ds), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
(function () {
  var tabs = document.querySelectorAll('.xtk-tab');
  var panels = document.querySelectorAll('.xtk-panel');
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      var i = t.dataset.xtk;
      tabs.forEach(function (x) { x.classList.toggle('active', x.dataset.xtk === i); });
      panels.forEach(function (p) { p.hidden = (p.dataset.xtk !== i); });
    });
  });

  var csrf = document.getElementById('xtk-csrf').value;
  var pop = null;

  function dongPop() { if (pop) { pop.remove(); pop = null; } }
  document.addEventListener('click', function (e) {
    if (pop && !pop.contains(e.target) && !e.target.closest('.xtk-nguon')) { dongPop(); }
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.xtk-nguon');
    if (!btn) { return; }
    e.stopPropagation();
    if (pop && pop.dataset.for === btn.dataset.ki + ':' + btn.dataset.ri) { dongPop(); return; }
    dongPop();
    moPicker(btn);
  });

  function moPicker(btn) {
    var ki = +btn.dataset.ki, ri = btn.dataset.ri;
    var sel = (btn.dataset.sel || '').split(',').filter(Boolean);
    var ds = (window.XTK_CT[ki] || []);
    pop = document.createElement('div');
    pop.className = 'xtk-pop';
    pop.dataset.for = ki + ':' + ri;
    var html = '<div class="xtk-pop-tim"><input type="text" placeholder="Tìm chỉ tiêu…" class="xtk-pop-loc"></div>'
             + '<div class="xtk-pop-ds">';
    ds.forEach(function (c) {
      var ck = sel.indexOf(c.ma) >= 0 ? ' checked' : '';
      html += '<label class="xtk-pop-mu"><input type="checkbox" value="' + c.ma + '"' + ck + '> '
            + '<span>' + c.ten.replace(/</g, '&lt;') + '</span></label>';
    });
    html += '</div><div class="xtk-pop-chan">'
          + '<button type="button" class="nut nut-nho xtk-luu">Lưu</button>'
          + '<button type="button" class="nut nut-nho nut-phu xtk-huy">Hủy</button>'
          + '<button type="button" class="nut nut-nho nut-phu xtk-macdinh" title="Bỏ nối tay, quay về mã mặc định trong khuôn">Mặc định</button>'
          + '<span class="xtk-pop-bao phu"></span></div>';
    pop.innerHTML = html;
    btn.closest('.xtk-nguon-o').appendChild(pop);

    pop.querySelector('.xtk-pop-loc').addEventListener('input', function () {
      var q = this.value.toLowerCase();
      pop.querySelectorAll('.xtk-pop-mu').forEach(function (l) {
        l.style.display = l.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
      });
    });
    pop.querySelector('.xtk-huy').addEventListener('click', dongPop);
    pop.querySelector('.xtk-luu').addEventListener('click', function () {
      var ma = [].slice.call(pop.querySelectorAll('input[type=checkbox]:checked')).map(function (c) { return c.value; });
      luu(btn, ma, pop.querySelector('.xtk-pop-bao'));
    });
    pop.querySelector('.xtk-macdinh').addEventListener('click', function () {
      luu(btn, null, pop.querySelector('.xtk-pop-bao'));   // null = bỏ override
    });
  }

  function luu(btn, ma, elBao) {
    var panel = btn.closest('.xtk-panel');
    var fd = new FormData();
    fd.append('viec', 'luu_nguon');
    fd.append('csrf', csrf);
    fd.append('khoa_ma', panel.dataset.khoa);
    fd.append('ri', btn.dataset.ri);
    if (ma === null) { fd.append('macdinh', '1'); }
    else { ma.forEach(function (m) { fd.append('ma[]', m); }); }
    elBao.textContent = 'Đang lưu…';
    fetch(location.pathname, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { elBao.textContent = d.loi || 'Lỗi'; return; }
        capNhatDong(btn, d);
        dongPop();
      })
      .catch(function () { elBao.textContent = 'Lỗi mạng'; });
  }

  function capNhatDong(btn, d) {
    var tr = btn.closest('tr');
    tr.querySelector('.xtk-kh').textContent = d.kh === null ? '' : d.kh;
    tr.querySelector('.xtk-th').innerHTML = d.trong
      ? '<strong><span class="phu">— trống —</span></strong>'
      : '<strong>' + (d.th === null ? '' : d.th) + '</strong>';
    tr.querySelector('.xtk-pt').innerHTML = d.pt === null ? '<span class="phu">—</span>' : d.pt;
    tr.classList.toggle('dong-canh-bao', d.trong);
    btn.dataset.sel = d.sel || '';
    var txt = btn.querySelector('.xtk-nguon-txt');
    txt.innerHTML = d.maTxt ? d.maTxt.replace(/</g, '&lt;') : '<span class="phu">— chọn chỉ tiêu —</span>';
    capNhatBadge(btn.closest('.xtk-panel'), d.soTrong);
  }

  function capNhatBadge(panel, soTrong) {
    var ma = panel.dataset.khoa;
    var badge = document.querySelector('.xtk-badge[data-khoa="' + ma + '"]');
    if (badge) {
      badge.textContent = soTrong > 0 ? '⚠ ' + soTrong : '✓';
      badge.className = 'xtk-badge ' + (soTrong > 0 ? 'xtk-badge-loi' : 'xtk-badge-ok');
      badge.dataset.khoa = ma;
    }
    var loi = document.querySelectorAll('.xtk-badge-loi').length;
    var tong = document.querySelectorAll('.xtk-badge').length;
    var el = document.getElementById('xtk-tongket');
    if (el) {
      el.innerHTML = loi === 0
        ? '<span style="color:#15803d;font-weight:600">Tất cả ' + tong + ' khoa đều đủ số.</span>'
        : '<strong style="color:#b45309">' + loi + '/' + tong + ' khoa</strong> còn dòng trống.';
    }
  }
})();
</script>
<?php endif; ?>
<?php dong_trang();

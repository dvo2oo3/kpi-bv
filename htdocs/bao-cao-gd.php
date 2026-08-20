<?php
/**
 * Xuất báo cáo — quản lý NHIỀU mẫu xuất (chọn khoa / chỉ tiêu / cột / kỳ),
 * lưu lại nhiều mẫu, chọn mẫu để xuất. Chỉ admin/dev (quyền baocao.giam_doc).
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/danh_muc.php';
require_once __DIR__ . '/app/bao_cao.php';
require_once __DIR__ . '/app/bao_cao_gd.php';

$toi    = bat_buoc_quyen('baocao.giam_doc');
$namMac = (int)($_GET['nam'] ?? date('Y'));
$ds     = bao_cao_gd_ds();

/** Đọc cấu hình từ form POST (giữ bo_ma cũ khi mẫu != custom). */
function bcgd_doc_form(array $ds, string $pid): array
{
    $cu = ['bo_ma' => []];
    foreach ($ds as $m) { if ($m['id'] === $pid) { $cu = $m['cfg']; } }
    $cfg = [
        'mau'       => in_array(post('mau'), ['chi_tiet','cha','chinh','custom'], true) ? post('mau') : 'chi_tiet',
        'ky'        => in_array(post('ky'), ['6thang','nam','quy1','quy2','quy3','quy4'], true) ? post('ky') : '6thang',
        'so_qd'     => trim((string)($_POST['so_qd'] ?? '')),
        'toan_vien' => isset($_POST['toan_vien']) ? 1 : 0,
        'chi_tiet_thang' => isset($_POST['chi_tiet_thang']) ? 1 : 0,
        'cot'       => [
            'nam_truoc' => isset($_POST['cot']['nam_truoc']) ? 1 : 0,
            'chi_tieu'  => isset($_POST['cot']['chi_tieu'])  ? 1 : 0,
            'ket_qua'   => isset($_POST['cot']['ket_qua'])   ? 1 : 0,
            'pt'        => isset($_POST['cot']['pt'])         ? 1 : 0,
        ],
    ];
    $kc = $_POST['khoa'] ?? [];
    $cfg['khoa'] = (is_array($kc) && $kc) ? array_values(array_map('intval', $kc)) : 'all';
    if ($cfg['mau'] === 'custom') {
        // custom THEO KHOA: $_POST['ct_khoa'][id_khoa][ma] = 1 (trường được HIỆN)
        $post = $_POST['ct_khoa'] ?? [];
        $ck = [];
        foreach (qAll('SELECT id FROM khoa WHERE hoat_dong = 1') as $k) {
            $id = (string)(int)$k['id'];
            $mas = is_array($post[$id] ?? null) ? $post[$id] : [];
            $t = [];
            foreach ($mas as $ma => $v) { $t[(string)$ma] = 1; }
            $ck[$id] = $t;                        // mảng rỗng = khoa này ẩn hết
        }
        $cfg['ct_khoa'] = $ck;
    } else {
        $cfg['ct_khoa'] = $cu['ct_khoa'] ?? [];
    }
    return bao_cao_gd_chuan($cfg);
}

if (la_post()) {
    kiem_tra_csrf();
    $nam  = max(2000, (int)post('nam', (string)date('Y')));
    $viec = post('viec');
    $pid  = (string)($_POST['p'] ?? '');
    $ten  = trim((string)($_POST['ten_mau'] ?? '')) ?: 'Mẫu không tên';

    if ($viec === 'xoa') {
        $ds = array_values(array_filter($ds, fn($m) => $m['id'] !== $pid));
        bao_cao_gd_luu_ds($ds);
        nhan_tin('ok', 'Đã xóa mẫu.');
        chuyen_huong('/bao-cao-gd.php?nam=' . $nam);
    }

    $cfg = bcgd_doc_form($ds, $pid);
    $coSan = false;
    foreach ($ds as &$m) {
        if ($m['id'] === $pid && $viec !== 'luu_moi') { $m['ten'] = $ten; $m['cfg'] = $cfg; $coSan = true; }
    }
    unset($m);
    if (!$coSan) {                                   // tạo mẫu mới
        $pid = uniqid('m');
        $ds[] = ['id' => $pid, 'ten' => $ten, 'cfg' => $cfg];
    }
    bao_cao_gd_luu_ds($ds);

    if ($viec === 'xuat') { xuat_bao_cao_giam_doc($nam, $cfg); exit; }
    nhan_tin('ok', 'Đã lưu mẫu "' . $ten . '".');
    chuyen_huong('/bao-cao-gd.php?p=' . urlencode($pid) . '&nam=' . $nam);
}

// GET: chọn mẫu đang xem
$pid = (string)($_GET['p'] ?? ($ds[0]['id'] ?? ''));
$laMoi = ($pid === '__new__');
if ($laMoi) {
    $mau = ['id' => '', 'ten' => '', 'cfg' => bao_cao_gd_mac_dinh()];
} else {
    $mau = null;
    foreach ($ds as $m) { if ($m['id'] === $pid) { $mau = $m; break; } }
    if (!$mau) { $mau = $ds[0] ?? ['id' => '', 'ten' => '', 'cfg' => bao_cao_gd_mac_dinh()]; $pid = $mau['id']; }
}
$cfg    = bao_cao_gd_chuan($mau['cfg']);
$tenMau = $mau['ten'];

$dsKhoa   = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');
// Chỉ tiêu áp dụng cho từng khoa (theo cây, kèm cấp) — cho phần Custom theo khoa
$ctTheoKhoa = [];
foreach ($dsKhoa as $k) {
    $ctTheoKhoa[(int)$k['id']] = bang_theo_khoa($namMac, [1], (int)$k['id'], 'giao');
}
$khoaChon = $cfg['khoa'] ?? 'all';
$khoaHet  = ($khoaChon === 'all' || !is_array($khoaChon));

mo_trang('Xuất báo cáo');
?>
<h1>Xuất báo cáo</h1>
<p class="phu" style="margin-top:-6px">Lưu nhiều <strong>mẫu xuất</strong> khác nhau. Chọn mẫu để xem/sửa/xuất, hoặc tạo mẫu mới.</p>

<div class="the-hop" style="max-width:1500px;margin-bottom:14px">
  <div style="font-weight:600;margin-bottom:6px">Mẫu đã lưu <span class="phu" style="font-weight:400">· <?= count($ds) ?> mẫu</span></div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <select onchange="location.href='/bao-cao-gd.php?nam=<?= (int)$namMac ?>&p='+encodeURIComponent(this.value)" style="flex:1;min-width:280px">
      <?php foreach ($ds as $m): ?>
        <option value="<?= e($m['id']) ?>" <?= (!$laMoi && $m['id']===$pid)?'selected':'' ?>>
          <?= e($m['ten']) ?><?= !empty($m['cfg']['chi_tiet_thang']) ? ' · từng tháng' : '' ?></option>
      <?php endforeach; ?>
      <?php if ($laMoi): ?><option value="__new__" selected>— Mẫu mới —</option><?php endif; ?>
    </select>
    <a class="nut nut-phu" href="/bao-cao-gd.php?nam=<?= (int)$namMac ?>&p=__new__" style="white-space:nowrap">＋ Tạo mẫu mới</a>
  </div>
</div>

<form method="post" class="the-hop" style="max-width:1500px">
  <?= csrf_field() ?>
  <input type="hidden" name="p" value="<?= e($laMoi ? '' : $pid) ?>">

  <label style="display:block;margin-bottom:14px">Tên mẫu
    <input type="text" name="ten_mau" value="<?= e($tenMau) ?>" required placeholder="VD: Theo dõi đạt được từng tháng" style="width:100%;max-width:420px">
  </label>

  <div style="margin-bottom:16px;padding:12px 14px;background:var(--nen-nhe,#f8fafc);border:1px solid #bae6fd;border-radius:10px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
    <button type="button" class="nut nut-chinh" onclick="moChinhSuaTruong()">✏️ Chỉnh sửa trường theo khoa</button>
    <span class="phu" style="flex:1;min-width:220px">Bấm để <strong>thêm / bớt từng trường</strong> (và trường con) cho <strong>từng khoa</strong> — mỗi khoa một sheet riêng.</span>
  </div>

  <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start">
  <div style="flex:1;min-width:340px">

  <fieldset style="border:1px solid var(--vien,#e2e8f0);border-radius:10px;padding:12px;margin-bottom:14px">
    <legend style="font-weight:600;padding:0 6px">Lọc chỉ tiêu</legend>
    <?php $mauLoc = $cfg['mau'] ?? 'chi_tiet';
    foreach (['chinh'=>'Chỉ tiêu chính (theo QĐ 74 — như file Giám đốc)','chi_tiet'=>'Chi tiết (tất cả chỉ tiêu)','cha'=>'Chỉ danh mục cha (nội dung lớn)','custom'=>'Custom (tự chọn bên dưới)'] as $v=>$t): ?>
      <label class="o-chon" style="display:inline-flex;gap:6px;margin-right:20px">
        <input type="radio" name="mau" value="<?= $v ?>" <?= $mauLoc===$v?'checked':'' ?> onchange="capNhatMau()"> <?= $t ?>
      </label>
    <?php endforeach; ?>
  </fieldset>

  <fieldset style="border:1px solid var(--vien,#e2e8f0);border-radius:10px;padding:12px;margin-bottom:14px">
    <legend style="font-weight:600;padding:0 6px">Phạm vi &amp; tiêu đề</legend>
    <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">
      <label>Năm
        <input type="number" name="nam" value="<?= (int)$namMac ?>" style="width:100px">
      </label>
      <label style="flex:1;min-width:150px">Kỳ (kết quả tính tới)
        <select name="ky" style="width:100%">
          <?php foreach (['6thang'=>'6 tháng đầu','nam'=>'Cả năm','quy1'=>'Quý I','quy2'=>'Quý II','quy3'=>'Quý III','quy4'=>'Quý IV'] as $v=>$t): ?>
            <option value="<?= $v ?>" <?= ($cfg['ky']===$v)?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <label style="display:block;margin-top:10px">Số quyết định (in dưới tiêu đề)
      <input type="text" name="so_qd" value="<?= e($cfg['so_qd'] ?? '') ?>" placeholder="VD: Quyết định số 33b/QĐ-TTYT ngày 13/01/2026" style="width:100%">
    </label>
  </fieldset>

  <fieldset style="border:1px solid var(--vien,#e2e8f0);border-radius:10px;padding:12px;margin-bottom:14px">
    <legend style="font-weight:600;padding:0 6px">Cột hiển thị</legend>
    <label class="o-chon" style="display:block;gap:6px;margin-bottom:8px;font-weight:600;padding:6px 8px;background:var(--nen-nhe,#f8fafc);border-radius:8px">
      <input type="checkbox" name="chi_tiet_thang" value="1" <?= !empty($cfg['chi_tiet_thang'])?'checked':'' ?> onchange="capNhatThang()">
      📅 Chi tiết từng tháng <small class="nhan-phu">(mẫu "Theo dõi đạt được": T1…T12 + lũy kế Quý/6 tháng/9 tháng/năm, mỗi khoa 1 sheet)</small>
    </label>
    <div id="cot-tong">
    <?php $C=$cfg['cot']; foreach(['nam_truoc'=>'TH năm trước','chi_tieu'=>'Chỉ tiêu giao','ket_qua'=>'Kết quả đạt được','pt'=>'So KH (%)'] as $k=>$t): ?>
      <label class="o-chon" style="display:inline-flex;gap:6px;margin-right:18px">
        <input type="checkbox" name="cot[<?= $k ?>]" value="1" <?= !empty($C[$k])?'checked':'' ?>> <?= $t ?>
      </label>
    <?php endforeach; ?>
    <p class="phu" id="ghi-chu-thang" style="margin:6px 0 0;display:none">Khi bật <em>chi tiết từng tháng</em>: chỉ dùng <strong>TH năm trước</strong> và <strong>Chỉ tiêu giao</strong>; các cột còn lại thay bằng cột tháng. Ô "Kỳ" cũng bỏ qua (luôn xuất đủ 12 tháng).</p>
    </div>
  </fieldset>

  </div><!-- /cột trái -->
  <div style="flex:1;min-width:340px">

  <fieldset style="border:1px solid var(--vien,#e2e8f0);border-radius:10px;padding:12px;margin-bottom:14px">
    <legend style="font-weight:600;padding:0 6px">Khoa xuất</legend>
    <label class="o-chon" style="display:flex;gap:6px;align-items:center;margin-bottom:8px;font-weight:600;padding:6px 8px;background:var(--nen-nhe,#f8fafc);border-radius:8px">
      <input type="checkbox" name="toan_vien" value="1" <?= !empty($cfg['toan_vien'])?'checked':'' ?>> + sheet Toàn viện
    </label>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:4px 14px">
    <?php foreach ($dsKhoa as $k):
      $ch = $khoaHet || in_array((int)$k['id'], array_map('intval',(array)$khoaChon), true); ?>
      <label class="o-chon" style="display:flex;gap:6px;align-items:center">
        <input type="checkbox" name="khoa[]" value="<?= (int)$k['id'] ?>" <?= $ch?'checked':'' ?>> <?= e($k['ten']) ?>
      </label>
    <?php endforeach; ?>
    </div>
    <p class="phu" style="margin:8px 0 0">Bỏ chọn hết = xuất tất cả khoa.</p>
  </fieldset>

  </div><!-- /cột phải -->
  </div><!-- /hai cột -->

  <fieldset id="fs-ct" style="margin-top:4px;border:1px solid var(--vien,#e2e8f0);border-radius:10px;padding:12px">
    <legend style="font-weight:600;padding:0 6px">Chọn trường theo khoa <small class="nhan-phu">(chỉ dùng cho mẫu <strong>Custom</strong>)</small></legend>
    <p class="phu" style="margin:0 0 8px">Chọn khoa → tick những trường muốn hiện trong sheet của khoa đó. Mỗi khoa lưu riêng.</p>
    <p id="ct-goi-y" class="phu" style="margin:6px 0;display:none">👉 Chọn <strong>"Custom (tự chọn bên dưới)"</strong> ở khung <em>Lọc chỉ tiêu</em> để bật phần này.</p>
    <div style="font-weight:600;margin-bottom:6px">Khoa</div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
      <select id="ct-khoa-sel" onchange="capNhatKhoaCt()" style="flex:1;min-width:240px">
        <?php foreach ($dsKhoa as $k): ?><option value="<?= (int)$k['id'] ?>"><?= e($k['ten']) ?></option><?php endforeach; ?>
      </select>
      <button type="button" class="nut nut-phu" onclick="ctTickKhoa(true)">Chọn hết khoa này</button>
      <button type="button" class="nut nut-phu" onclick="ctTickKhoa(false)">Bỏ hết</button>
    </div>
    <?php foreach ($dsKhoa as $ki => $k):
        $kid  = (int)$k['id'];
        $cfgK = $cfg['ct_khoa'][(string)$kid] ?? null;   // null = chưa cấu hình → mặc định bộ chính
    ?>
    <div class="ct-khoa-panel" data-khoa="<?= $kid ?>" style="display:<?= $ki===0?'block':'none' ?>;border:1px solid var(--vien,#e2e8f0);border-radius:8px;padding:10px;column-width:320px;column-gap:26px">
      <?php foreach ($ctTheoKhoa[$kid] as $d):
          $ma = $d['ct']['ma']; $cap = (int)$d['cap'];
          $checked = $cfgK !== null ? isset($cfgK[$ma]) : in_array($ma, MA_CHI_TIEU_CHINH, true); ?>
        <label class="o-chon" style="display:block;padding:2px 0;padding-left:<?= $cap*20 ?>px;break-inside:avoid;-webkit-column-break-inside:avoid">
          <input type="checkbox" class="ck-<?= $kid ?>" data-cap="<?= $cap ?>" <?= $cap===0?'onchange="cascCon(this)"':'' ?> name="ct_khoa[<?= $kid ?>][<?= e($ma) ?>]" value="1" <?= $checked?'checked':'' ?>>
          <?= ($cap===0?'<strong>':'') . e($d['ct']['ten']) . ($cap===0?'</strong>':'') ?>
          <small class="nhan-phu">(<?= e($ma) ?>)</small>
        </label>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </fieldset>

  <div class="form-chan" style="margin-top:18px;padding-top:14px;border-top:1px solid var(--vien,#e2e8f0);display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <button class="nut nut-chinh" type="submit" name="viec" value="xuat">⬇ Xuất Excel</button>
    <button class="nut" type="submit" name="viec" value="luu">💾 Lưu mẫu này</button>
    <button class="nut nut-phu" type="submit" name="viec" value="luu_moi">＋ Lưu thành mẫu mới</button>
    <?php if (!$laMoi && count($ds) > 1): ?>
      <button class="nut nut-nguy" type="submit" name="viec" value="xoa" style="margin-left:18px"
              data-xac-nhan="Xóa mẫu &quot;<?= e($tenMau) ?>&quot;?">🗑 Xóa mẫu</button>
    <?php endif; ?>
  </div>
</form>
<script>
function capNhatMau(){
  var s=document.querySelector('input[name=mau]:checked');
  if(!s) return;
  var custom = s.value==='custom';
  var fs=document.getElementById('fs-ct');
  fs.style.opacity = custom?'1':'.5';
  fs.querySelectorAll('input,button,select').forEach(function(el){ el.disabled = !custom; });
  var goiy=document.getElementById('ct-goi-y');
  if(custom){ capNhatKhoaCt(); if(goiy) goiy.style.display='none'; }
  else {
    // không phải Custom → thu gọn panel cho gọn (2 cột cân, nút không bị đẩy xa)
    document.querySelectorAll('.ct-khoa-panel').forEach(function(p){ p.style.display='none'; });
    if(goiy) goiy.style.display='block';
  }
}
function capNhatThang(){
  var on=document.querySelector('input[name=chi_tiet_thang]').checked;
  document.getElementById('ghi-chu-thang').style.display = on?'block':'none';
}
function capNhatKhoaCt(){
  var sel=document.getElementById('ct-khoa-sel'); if(!sel) return;
  document.querySelectorAll('.ct-khoa-panel').forEach(function(p){
    p.style.display = (p.dataset.khoa===sel.value)?'block':'none';
  });
}
function ctTickKhoa(v){
  var sel=document.getElementById('ct-khoa-sel'); if(!sel) return;
  document.querySelectorAll('.ck-'+sel.value).forEach(function(c){ c.checked=v; });
}
// Nút "Chỉnh sửa trường theo khoa": bật Custom + mở panel + cuộn tới, làm nổi
function moChinhSuaTruong(){
  var r=document.querySelector('input[name=mau][value=custom]');
  if(r && !r.checked){ r.checked=true; }
  capNhatMau();
  var fs=document.getElementById('fs-ct');
  if(fs){
    fs.scrollIntoView({behavior:'smooth',block:'center'});
    fs.style.transition='box-shadow .3s'; fs.style.boxShadow='0 0 0 3px rgba(2,132,199,.45)';
    setTimeout(function(){ fs.style.boxShadow=''; },1600);
  }
}
// Tick/bỏ trường cha thì các con theo cùng (đến khi gặp trường cha kế tiếp)
function cascCon(cb){
  var lab=cb.closest('label'), n=lab?lab.nextElementSibling:null;
  while(n){
    var c=n.querySelector('input[type=checkbox]'); if(!c) break;
    if(+c.dataset.cap===0) break;
    c.checked=cb.checked; n=n.nextElementSibling;
  }
}
capNhatMau(); capNhatThang();
</script>
<?php dong_trang();

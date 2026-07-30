<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/cai_dat.php';

/** Menu chính — lọc theo quyền, đánh dấu trang đang xem. */
function menu_chinh(): array
{
    $m = [];
    $them = function (string $url, string $ten, string $quyen) use (&$m) {
        if ($quyen === '' || co_quyen($quyen)) {
            $m[] = ['url' => $url, 'ten' => $ten];
        }
    };
    $them('/', 'Trang chủ', '');
    if (co_quyen('solieu.nhap') || co_quyen('solieu.xem_tat_ca')) {
        $m[] = ['url' => '/nhap-so-lieu.php', 'ten' => 'Nhập số liệu'];
    }
    $them('/nhap-tu-excel.php', 'Nhập từ Excel', 'solieu.nhap_excel');
    if (co_quyen('baocao.toan_vien') || co_quyen('baocao.khoa_minh')) {
        $m[] = ['url' => '/bao-cao.php', 'ten' => 'Báo cáo'];
    }
    $them('/duyet-ky.php', 'Duyệt kỳ', 'ky.duyet');
    $them('/lich-ky.php', 'Lịch mở kỳ', 'ky.dat_lich');
    $them('/giao-chi-tieu.php', 'Giao chỉ tiêu', 'chitieu.giao');
    $them('/danh-muc-chi-tieu.php', 'Danh mục chỉ tiêu', 'chitieu.xem');
    $them('/khoa.php', 'Khoa', 'khoa.xem');
    $them('/nguoi-dung.php', 'Người dùng', 'nguoidung.xem');
    $them('/nhat-ky.php', 'Nhật ký', 'nhatky.xem');
    $them('/sao-luu.php', 'Sao lưu', 'sao_luu.tai_ve');
    return $m;
}

function mo_trang(string $tieu_de, bool $co_menu = true): void
{
    $nd = nguoi_dung_hien_tai();
    $trang = '/' . basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($trang === '/index.php') {
        $trang = '/';
    }
    // Trang con vẫn làm sáng mục menu cha
    if (str_starts_with($trang, '/chi-tieu-')) {
        $trang = '/danh-muc-chi-tieu.php';
    }
    ?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($tieu_de) ?> — <?= e(TEN_UNG_DUNG) ?></title>
<?php $LOGO_URL = logo_url(); ?>
<link rel="icon" href="<?= $LOGO_URL !== null ? e($LOGO_URL)
  : 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" rx="5" fill="#0d9488"/><path d="M19 10.5h-5.5V5c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v5.5H5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5h5.5V19c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5v-5.5H19c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5z" fill="#fff"/></svg>') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css?v=44">
</head>
<body>
<?php if ($co_menu && $nd): ?>
<header class="dinh">
  <div class="dinh-tren">
    <div class="khung">
      <?php
        $doiLogo = co_quyen('he_thong.logo');
        // Ruột của ô biểu tượng: ảnh đã tải lên, hoặc dấu "+" mặc định.
        $ruotDauHieu = $LOGO_URL !== null
          ? '<img src="' . e($LOGO_URL) . '" alt="Logo">'
          : '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M19 10.5h-5.5V5c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v5.5H5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5h5.5V19c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5v-5.5H19c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5z"/></svg>';
        $lopDauHieu = 'dau-hieu' . ($LOGO_URL !== null ? ' co-anh' : '');
      ?>
      <div class="thuong-hieu">
        <?php if ($doiLogo): ?>
          <button type="button" class="<?= $lopDauHieu ?> nut-logo" data-mo="doi-logo"
                  title="Đổi logo ứng dụng" aria-label="Đổi logo ứng dụng"><?= $ruotDauHieu ?></button>
        <?php else: ?>
          <a class="<?= $lopDauHieu ?>" href="/" aria-hidden="true"><?= $ruotDauHieu ?></a>
        <?php endif; ?>
        <a class="thuong-hieu-chu" href="/">
          <strong><?= e(TEN_DON_VI) ?></strong>
          <small><?= e(TEN_UNG_DUNG) ?></small>
        </a>
      </div>
      <?php if ($doiLogo): ?>
      <div class="lop-phu" id="doi-logo" hidden>
       <div class="hop-modal" role="dialog" aria-modal="true" aria-label="Đổi logo ứng dụng">
        <div class="modal-dau">
          <h2>Đổi logo ứng dụng</h2>
          <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
        </div>
        <div class="modal-than">
          <p class="phu" style="margin-top:0">
            Logo này hiển thị ở góc trái và dùng luôn làm <strong>favicon</strong> (biểu tượng trên tab trình duyệt).
            Nên dùng ảnh <strong>vuông</strong>, nền trong, định dạng PNG.
            Kích thước tối đa do máy chủ quy định (hiện <strong><?= e(ini_get('upload_max_filesize')) ?></strong>).
          </p>
          <div class="logo-xem-truoc">
            <span class="<?= $lopDauHieu ?> to"><?= $ruotDauHieu ?></span>
            <span class="phu"><?= $LOGO_URL !== null ? 'Logo hiện tại' : 'Đang dùng biểu tượng mặc định' ?></span>
          </div>
          <form method="post" action="/logo.php" enctype="multipart/form-data" class="form-logo">
            <?= csrf_field() ?>
            <label class="o-tep">Chọn ảnh logo
              <input type="file" name="tep" accept="image/png,image/jpeg,image/gif,image/webp" required>
            </label>
            <div class="form-chan">
              <button class="nut nut-chinh" type="submit">Tải lên &amp; áp dụng</button>
              <button type="button" class="nut nut-phu" data-dong>Hủy</button>
            </div>
          </form>
          <?php if ($LOGO_URL !== null): ?>
          <form method="post" action="/logo.php" class="form-go-logo">
            <?= csrf_field() ?>
            <input type="hidden" name="viec" value="go">
            <button class="nut nut-nho nut-nguy" type="submit">Gỡ logo, dùng lại mặc định</button>
          </form>
          <?php endif; ?>
        </div>
       </div>
      </div>
      <?php endif; ?>
      <div class="khoi-nguoi-dung">
        <div class="nd-chu">
          <strong><?= e($nd['ho_ten']) ?></strong>
          <small><?= e(ten_vai_tro($nd['vai_tro'])) ?><?php
            if ($nd['khoa']):
              echo ' · ' . e(implode(', ', array_column($nd['khoa'], 'ma')));
            endif; ?></small>
        </div>
        <div class="nd-nut">
          <a href="/doi-mat-khau.php" title="Đổi mật khẩu">Đổi mật khẩu</a>
          <a href="/dang-xuat.php" class="thoat">Đăng xuất</a>
        </div>
        <button type="button" class="nut-menu" aria-label="Mở menu"
                aria-controls="menu-chinh" aria-expanded="false">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
      </div>
    </div>
  </div>
  <nav class="dinh-duoi" id="menu-chinh">
    <div class="khung">
      <?php foreach (menu_chinh() as $m): ?>
        <a href="<?= e($m['url']) ?>" <?= $m['url'] === $trang ? 'class="dang-xem" aria-current="page"' : '' ?>>
          <?= e($m['ten']) ?>
        </a>
      <?php endforeach; ?>
      <!-- Chỉ hiện trong menu xổ trên điện thoại -->
      <div class="menu-nguoi-dung">
        <span class="menu-nd-ten"><?= e($nd['ho_ten']) ?>
          <small><?= e(ten_vai_tro($nd['vai_tro'])) ?></small></span>
        <a href="/doi-mat-khau.php">Đổi mật khẩu</a>
        <a href="/dang-xuat.php" class="thoat">Đăng xuất</a>
      </div>
    </div>
  </nav>
</header>
<?php endif; ?>
<main class="<?= $co_menu ? 'noi-dung' : 'noi-dung-giua' ?>">
<?php foreach (lay_thong_bao() as $tb): ?>
  <div class="tb tb-<?= e($tb['loai']) ?>"><?= e($tb['noi_dung']) ?></div>
<?php endforeach; ?>
<?php
}

/**
 * Nút "?" mở hộp giải thích dạng popup.
 */
function mo_tro_giup(string $id, string $tieu_de): void
{
    ?>
<button type="button" class="nut-tro-giup" data-mo="<?= e($id) ?>"
        title="<?= e($tieu_de) ?>" aria-label="<?= e($tieu_de) ?>">?</button>
<div class="lop-phu" id="<?= e($id) ?>" hidden>
  <div class="hop-tro-giup" role="dialog" aria-modal="true" aria-label="<?= e($tieu_de) ?>">
    <div class="tro-giup-dau">
      <h3><?= e($tieu_de) ?></h3>
      <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
    </div>
    <div class="tro-giup-than">
<?php
}

function dong_tro_giup(): void
{
    ?>
    </div>
  </div>
</div>
<?php
}

function dong_trang(): void
{
    ?>
</main>
<footer class="chan-trang">
  <?= e(TEN_DON_VI) ?> · <?= e(TEN_UNG_DUNG) ?> · <?= date('Y') ?>
</footer>
<script>
/* Popup giải thích: mở bằng nút "?", đóng bằng dấu ×, bấm ra ngoài hoặc phím Esc */
document.addEventListener('click', function (e) {
  var mo = e.target.closest('[data-mo]');
  if (mo) {
    var h = document.getElementById(mo.dataset.mo);
    if (h) { h.removeAttribute('hidden'); h.hidden = false; }
    return;
  }
  if (e.target.closest('.dong-tro-giup') || e.target.closest('[data-dong]')
      || e.target.classList.contains('lop-phu')) {
    var lp = e.target.closest('.lop-phu');
    if (lp) { lp.setAttribute('hidden', ''); lp.hidden = true; }
  }
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.lop-phu').forEach(function (x) {
      x.setAttribute('hidden', '');
      x.hidden = true;
    });
    document.querySelector('.dinh')?.classList.remove('mo-menu');
  }
});

/* Nút hamburger: xổ / thu menu chính trên điện thoại */
(function () {
  var nut = document.querySelector('.nut-menu');
  var dinh = document.querySelector('.dinh');
  if (!nut || !dinh) { return; }
  nut.addEventListener('click', function () {
    var mo = dinh.classList.toggle('mo-menu');
    nut.setAttribute('aria-expanded', mo ? 'true' : 'false');
  });
  // Bấm một mục thì đóng menu lại
  document.getElementById('menu-chinh')?.addEventListener('click', function (e) {
    if (e.target.closest('a')) { dinh.classList.remove('mo-menu'); }
  });
})();

/* Kéo giãn cột: giữ mép phải ô tiêu đề rồi kéo. Lần đầu kéo sẽ chốt bề rộng
   hiện tại của mọi cột (table-layout: fixed) để kéo được chính xác. */
(function () {
  document.querySelectorAll('table.bang').forEach(function (bang) {
    var ths = bang.querySelectorAll('thead th');
    ths.forEach(function (th, i) {
      if (i === ths.length - 1) { return; }   // cột cuối không cần tay kéo
      var tay = document.createElement('span');
      tay.className = 'cot-keo';
      th.appendChild(tay);
      tay.addEventListener('mousedown', function (e) {
        e.preventDefault(); e.stopPropagation();
        if (bang.style.tableLayout !== 'fixed') {
          ths.forEach(function (h) { h.style.width = h.offsetWidth + 'px'; });
          bang.style.tableLayout = 'fixed';
        }
        var x0 = e.pageX, w0 = th.offsetWidth;
        document.body.classList.add('dang-keo-cot');
        function di(ev) { th.style.width = Math.max(48, w0 + ev.pageX - x0) + 'px'; }
        function thoi() {
          document.removeEventListener('mousemove', di);
          document.removeEventListener('mouseup', thoi);
          document.body.classList.remove('dang-keo-cot');
        }
        document.addEventListener('mousemove', di);
        document.addEventListener('mouseup', thoi);
      });
      tay.addEventListener('click', function (e) { e.stopPropagation(); });
    });
  });
})();
</script>
</body>
</html><?php
}

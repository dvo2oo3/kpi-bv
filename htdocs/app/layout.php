<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/cai_dat.php';

/** Menu chính — lọc theo quyền, đánh dấu trang đang xem. */
function menu_chinh(): array
{
    $m = [];
    $them = function (string $url, string $ten, string $quyen, int $badge = 0) use (&$m) {
        if ($quyen === '' || co_quyen($quyen)) {
            $m[] = ['url' => $url, 'ten' => $ten, 'badge' => $badge];
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
    $them('/bao-cao-gd.php', 'Xuất báo cáo', 'baocao.giam_doc');
    // Badge trên tab "Duyệt kỳ" = số việc cần admin xử lý:
    //   kỳ đang chờ duyệt (DA_NOP) + khoa xin mở lại (ghi_chu 'YC:')
    $soChoDuyet = co_quyen('ky.duyet')
        ? (int)qVal("SELECT COUNT(*) FROM ky
                     WHERE trang_thai = 'DA_NOP'
                        OR (ghi_chu LIKE 'YC:%' AND trang_thai IN ('DA_DUYET','DA_KHOA'))") : 0;
    $them('/duyet-ky.php', 'Duyệt kỳ', 'ky.duyet', $soChoDuyet);
    $them('/lich-ky.php', 'Lịch mở kỳ', 'ky.dat_lich');
    $them('/giao-chi-tieu.php', 'Giao chỉ tiêu', 'chitieu.giao');
    $them('/danh-muc-chi-tieu.php', 'Thư viện', 'chitieu.xem');
    $them('/khoa.php', 'Khoa', 'khoa.xem');
    $them('/nguoi-dung.php', 'Người dùng', 'nguoidung.xem');
    $them('/nhat-ky.php', 'Nhật ký', 'nhatky.xem');
    $them('/sao-luu.php', 'Sao lưu', 'sao_luu.tai_ve');
    $them('/reset-du-lieu.php', 'Reset', 'he_thong.reset');
    $them('/bao-tri.php', 'Bảo trì', 'he_thong.bao_tri');
    return $m;
}

/** Thanh sub-tab cho khu "Nhập từ Excel" (2 chức năng trong 1 tab). */
function tab_nhap(): void
{
    $trang = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $tabs = [
        'nhap-tu-excel.php'  => 'Theo mẫu chuẩn của hệ thống',
        'nhap-file-khoa.php' => 'Theo file riêng của khoa',
    ];
    echo '<div class="tab-phu">';
    foreach ($tabs as $file => $ten) {
        $dang = $trang === $file ? ' dang' : '';
        echo '<a class="tab-phu-muc' . $dang . '" href="/' . $file . '">' . e($ten) . '</a>';
    }
    echo '</div>';
}

function mo_trang(string $tieu_de, bool $co_menu = true): void
{
    $nd = nguoi_dung_hien_tai();
    $trang = '/' . basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($trang === '/index.php') {
        $trang = '/';
    }
    // Trang con vẫn làm sáng mục menu cha
    if (str_starts_with($trang, '/chi-tieu-') || $trang === '/gop-trung-lap.php') {
        $trang = '/danh-muc-chi-tieu.php';
    }
    // "Nhập file khoa" gộp vào tab "Nhập từ Excel"
    if ($trang === '/nhap-file-khoa.php') {
        $trang = '/nhap-tu-excel.php';
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
<link rel="stylesheet" href="/assets/app.css?v=91">
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
          <?= e($m['ten']) ?><?php if (!empty($m['badge'])): ?><span class="menu-badge" title="<?= (int)$m['badge'] ?> kỳ đang chờ duyệt"><?= (int)$m['badge'] ?></span><?php endif; ?>
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
<?php if ($nd && dang_bao_tri()): ?>
  <div class="tb tb-nguy" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
    <span><?= bao_tri_muc() === 2
        ? '🔒 <strong>Đang KHÓA CỨNG</strong> — chỉ dev vào được, admin cũng bị chặn.'
        : '🛠️ <strong>Đang bảo trì thường</strong> — chỉ admin/dev và tài khoản được chọn vào được.' ?></span>
    <?php if (co_quyen('he_thong.bao_tri')): ?><a class="nut nut-nho" href="/bao-tri.php">Quản lý</a><?php endif; ?>
  </div>
<?php endif; ?>
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

  // Lăn chuột trên thanh menu (khi menu tràn ngang) = trượt ngang, khỏi cần
  // thanh cuộn. Chỉ chặn cuộn dọc khi thực sự còn chỗ để trượt ngang.
  var khungMenu = document.querySelector('.dinh-duoi .khung');
  if (khungMenu) {
    var dinhDuoi = khungMenu.closest('.dinh-duoi');
    // Bật/tắt vệt mờ hai mép theo vị trí cuộn (còn nội dung bên nào thì mờ bên đó).
    function capNhatMep() {
      if (!dinhDuoi) { return; }
      var max = khungMenu.scrollWidth - khungMenu.clientWidth;
      dinhDuoi.classList.toggle('co-trai', khungMenu.scrollLeft > 1);
      dinhDuoi.classList.toggle('co-phai', max > 1 && khungMenu.scrollLeft < max - 1);
    }
    capNhatMep();
    requestAnimationFrame(capNhatMep);          // sau khi trình bày xong khung
    window.addEventListener('load', capNhatMep); // chắc chắn khi tải xong hẳn
    khungMenu.addEventListener('scroll', capNhatMep, { passive: true });
    window.addEventListener('resize', capNhatMep);

    khungMenu.addEventListener('wheel', function (e) {
      if (e.deltaY === 0 || khungMenu.scrollWidth <= khungMenu.clientWidth) { return; }
      var toiTrai  = khungMenu.scrollLeft <= 0 && e.deltaY < 0;
      var toiPhai  = khungMenu.scrollLeft + khungMenu.clientWidth >= khungMenu.scrollWidth - 1 && e.deltaY > 0;
      if (toiTrai || toiPhai) { return; }   // hết mép thì để trang cuộn dọc bình thường
      e.preventDefault();
      khungMenu.scrollLeft += e.deltaY;
    }, { passive: false });
  }
})();

/* Toast: thông báo nổi góc phải, tự tắt — thay cho alert() thô. */
window.toast = function (msg, loai) {
  var box = document.getElementById('toast-vung');
  if (!box) { box = document.createElement('div'); box.id = 'toast-vung'; document.body.appendChild(box); }
  var t = document.createElement('div');
  t.className = 'toast toast-' + (loai || 'canh-bao');
  t.textContent = msg;
  box.appendChild(t);
  requestAnimationFrame(function () { t.classList.add('hien'); });
  setTimeout(function () {
    t.classList.remove('hien');
    setTimeout(function () { t.remove(); }, 300);
  }, 3600);
};

/* xacNhan: popup Đồng ý / Hủy trong app (thay confirm() của trình duyệt).
   Trả về Promise<boolean>. Enter = đồng ý, Esc/nền = hủy. */
window.xacNhan = function (msg, opt) {
  opt = opt || {};
  return new Promise(function (resolve) {
    var lp = document.createElement('div');
    lp.className = 'lop-phu lop-xac-nhan';
    var box = document.createElement('div');
    box.className = 'hop-modal hop-xac-nhan';
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
    var noi = document.createElement('div'); noi.className = 'xn-noi'; noi.textContent = msg;
    var hang = document.createElement('div'); hang.className = 'xn-nut';
    var bHuy = document.createElement('button');
    bHuy.type = 'button'; bHuy.className = 'nut nut-phu'; bHuy.textContent = opt.huy || 'Hủy';
    var bOk = document.createElement('button');
    bOk.type = 'button'; bOk.textContent = opt.ok || 'Đồng ý';
    bOk.className = 'nut ' + (opt.loai === 'nguy' ? 'nut-do' : 'nut-chinh');
    hang.appendChild(bHuy); hang.appendChild(bOk);
    box.appendChild(noi); box.appendChild(hang); lp.appendChild(box);
    document.body.appendChild(lp);
    function dong(kq) { lp.remove(); document.removeEventListener('keydown', onKey); resolve(kq); }
    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); dong(false); }
      else if (e.key === 'Enter') { e.preventDefault(); dong(true); }
    }
    bOk.addEventListener('click', function () { dong(true); });
    bHuy.addEventListener('click', function () { dong(false); });
    lp.addEventListener('click', function (e) { if (e.target === lp) { dong(false); } });
    document.addEventListener('keydown', onKey);
    setTimeout(function () { bOk.focus(); }, 30);
  });
};

/* Form/nút có data-xac-nhan="lời nhắn" → hiện popup xác nhận trước khi gửi
   (thay onsubmit="return confirm(...)"). data-xac-nhan-loai="nguy" cho nút đỏ. */
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (!f.dataset || !f.dataset.xacNhan || f.dataset.xnOk) { return; }
  e.preventDefault();
  if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
  window.xacNhan(f.dataset.xacNhan, { loai: f.dataset.xacNhanLoai }).then(function (ok) {
    if (ok) { f.dataset.xnOk = '1'; (f.requestSubmit ? f.requestSubmit() : f.submit()); }
  });
}, true);
/* Nút/link có data-xac-nhan (không phải form) → hỏi trước khi thực hiện */
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-xac-nhan]');
  if (!b || b.tagName === 'FORM' || b.dataset.xnOk) { return; }
  e.preventDefault();
  if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
  window.xacNhan(b.dataset.xacNhan, { loai: b.dataset.xacNhanLoai }).then(function (ok) {
    if (ok) { b.dataset.xnOk = '1'; b.click(); }
  });
}, true);

/* Ô tìm kiếm: lọc dòng bảng ngay khi gõ (bỏ qua cột Thao tác để khỏi khớp nhầm
   chữ trong nút). Dùng: <input class="o-tim" data-tim="#id-bang" data-dem="#id-dem"> */
document.addEventListener('input', function (e) {
  var o = e.target.closest('.o-tim');
  if (!o) { return; }
  var bang = document.querySelector(o.getAttribute('data-tim'));
  if (!bang) { return; }
  var tu = o.value.trim().toLowerCase();
  var hien = 0;
  bang.querySelectorAll('tbody > tr').forEach(function (tr) {
    if (tr.classList.contains('dong-them-con')) { return; }   // dòng phụ, bỏ qua
    var txt = '';
    tr.querySelectorAll('td:not(.thao-tac)').forEach(function (td) {
      txt += ' ' + td.textContent.toLowerCase();
    });
    var khop = !tu || txt.indexOf(tu) !== -1;
    tr.style.display = khop ? '' : 'none';
    if (khop) { hien++; }
  });
  var dem = o.getAttribute('data-dem') && document.querySelector(o.getAttribute('data-dem'));
  if (dem) { dem.textContent = tu ? (hien + ' kết quả') : ''; }
});

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

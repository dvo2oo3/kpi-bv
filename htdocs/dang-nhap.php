<?php
require_once __DIR__ . '/app/layout.php';

if (da_dang_nhap()) {
    chuyen_huong('/');
}

$loi = null;
if (isset($_GET['ly_do']) && $_GET['ly_do'] === 'het_phien') {
    $loi = 'Phiên làm việc đã hết hạn do không thao tác. Vui lòng đăng nhập lại.';
}

if (la_post()) {
    kiem_tra_csrf();
    $kq = dang_nhap(post('ten_dang_nhap'), (string)($_POST['mat_khau'] ?? ''));
    if ($kq['ok']) {
        $tiep = $_GET['tiep'] ?? '/';
        // Chỉ cho phép chuyển hướng nội bộ
        if (!preg_match('#^/[a-zA-Z0-9\-_/.]*$#', $tiep) || str_starts_with($tiep, '//')) {
            $tiep = '/';
        }
        chuyen_huong($tiep);
    }
    $loi = $kq['loi'];
}

mo_trang('Đăng nhập', false);

/** Logo dùng chung: ảnh admin đã tải lên, hoặc dấu "+" y tế mặc định. */
function dau_hieu_yte(int $size): string
{
    $logo = logo_url();
    if ($logo !== null) {
        return '<img src="' . e($logo) . '" alt="Logo">';
    }
    return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor">'
        . '<path d="M19 10.5h-5.5V5c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v5.5H5c-.83 0-1.5.67-1.5 1.5'
        . 's.67 1.5 1.5 1.5h5.5V19c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5v-5.5H19c.83 0 1.5-.67 1.5-1.5'
        . 's-.67-1.5-1.5-1.5z"/></svg>';
}
$coLogo = logo_url() !== null ? ' co-anh' : '';

/** Dấu "+" y tế thuần SVG, dùng cho hoa văn trang trí nền. */
function dau_cong_yte(): string
{
    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
        . '<path d="M19 10.5h-5.5V5c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v5.5H5c-.83 0-1.5.67-1.5 1.5'
        . 's.67 1.5 1.5 1.5h5.5V19c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5v-5.5H19c.83 0 1.5-.67 1.5-1.5'
        . 's-.67-1.5-1.5-1.5z"/></svg>';
}
?>
<div class="man-dang-nhap">

  <!-- Bảng thương hiệu -->
  <aside class="dn-thuong-hieu">
    <div class="dn-trang-tri" aria-hidden="true">
      <span class="dn-quang dn-quang-1"></span>
      <span class="dn-quang dn-quang-2"></span>
      <span class="dn-cong dn-cong-1"><?= dau_cong_yte() ?></span>
      <span class="dn-cong dn-cong-2"><?= dau_cong_yte() ?></span>
      <span class="dn-cong dn-cong-3"><?= dau_cong_yte() ?></span>
      <svg class="dn-ecg" viewBox="0 0 1200 120" preserveAspectRatio="none">
        <path pathLength="1" d="M0 60 H250 l18 0 l10 -8 l8 16 l10 -50 l10 64 l12 -30 l10 8 l18 0
          H620 l10 -8 l8 16 l10 -50 l10 64 l12 -30 l10 8 H980 l10 -8 l8 16 l10 -50 l10 64 l12 -30 l10 8 H1200"/>
      </svg>
    </div>
    <div class="dn-th-trong">
      <div class="dn-th-noi">
        <div class="dn-logo<?= $coLogo ?>"><?= dau_hieu_yte(30) ?></div>
        <h1><?= e(TEN_DON_VI) ?></h1>
      </div>
    </div>
  </aside>

  <!-- Bảng form -->
  <div class="dn-form-vung">
    <div class="dn-form">
      <div class="dn-mini-hieu">
        <span class="dn-logo dn-logo-nho<?= $coLogo ?>"><?= dau_hieu_yte(22) ?></span>
        <span><?= e(TEN_DON_VI) ?></span>
      </div>

      <div class="dn-form-dau">
        <h2>Đăng nhập</h2>
        <p>Dùng tài khoản do Phòng Kế hoạch Tổng hợp cấp.</p>
      </div>

      <?php if ($loi): ?><div class="tb tb-loi"><?= e($loi) ?></div><?php endif; ?>

      <form method="post">
        <?= csrf_field() ?>
        <label>Tên đăng nhập
          <input name="ten_dang_nhap" value="<?= e(post('ten_dang_nhap')) ?>"
                 required autofocus autocomplete="username">
        </label>
        <label>Mật khẩu
          <input type="password" name="mat_khau" required autocomplete="current-password">
        </label>
        <button class="nut nut-chinh dn-nut" type="submit">Đăng nhập</button>
      </form>

      <p class="dn-ghi-chu">
        Quên mật khẩu? Hệ thống không gửi email được — vui lòng liên hệ
        <strong>Phòng Kế hoạch Tổng hợp</strong> để được cấp lại.
      </p>
    </div>
  </div>

</div>
<?php dong_trang();

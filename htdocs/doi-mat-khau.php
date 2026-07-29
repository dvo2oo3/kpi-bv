<?php
require_once __DIR__ . '/app/layout.php';

$nd = nguoi_dung_hien_tai();
if (!$nd) {
    chuyen_huong('/dang-nhap.php');
}

$batBuoc = (int)$nd['doi_mat_khau'] === 1;
$loi = null;

if (la_post()) {
    kiem_tra_csrf();
    $cu  = (string)($_POST['mat_khau_cu'] ?? '');
    $moi = (string)($_POST['mat_khau_moi'] ?? '');
    $moi2 = (string)($_POST['mat_khau_moi_2'] ?? '');

    $hash = qVal('SELECT mat_khau_hash FROM nguoi_dung WHERE id = ?', [$nd['id']]);

    if (!password_verify($cu, $hash)) {
        $loi = 'Mật khẩu hiện tại không đúng.';
    } elseif ($moi !== $moi2) {
        $loi = 'Hai lần nhập mật khẩu mới không khớp.';
    } elseif ($moi === $cu) {
        $loi = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    } elseif ($e = kiem_tra_mat_khau($moi)) {
        $loi = $e;
    } else {
        q('UPDATE nguoi_dung SET mat_khau_hash = ?, doi_mat_khau = 0 WHERE id = ?',
            [password_hash($moi, PASSWORD_DEFAULT), $nd['id']]);
        ghi_nhat_ky('DOI_MAT_KHAU', $nd['ten_dang_nhap'], 'Tự đổi mật khẩu');
        nhan_tin('ok', 'Đã đổi mật khẩu thành công.');
        chuyen_huong('/');
    }
}

mo_trang('Đổi mật khẩu', !$batBuoc);
?>
<div class="the-hop hop-hep">
  <h1>Đổi mật khẩu</h1>

  <?php if ($batBuoc): ?>
    <div class="tb tb-canh-bao">
      Đây là lần đăng nhập đầu tiên hoặc mật khẩu của bạn vừa được cấp lại.
      Bạn cần đổi mật khẩu trước khi sử dụng hệ thống.
    </div>
  <?php endif; ?>

  <?php if ($loi): ?><div class="tb tb-loi"><?= e($loi) ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label>Mật khẩu hiện tại
      <input type="password" name="mat_khau_cu" required autofocus>
    </label>
    <label>Mật khẩu mới
      <input type="password" name="mat_khau_moi" required>
      <small>Tối thiểu 8 ký tự, có cả chữ và số.</small>
    </label>
    <label>Nhập lại mật khẩu mới
      <input type="password" name="mat_khau_moi_2" required>
    </label>
    <button class="nut nut-chinh" type="submit">Đổi mật khẩu</button>
    <?php if (!$batBuoc): ?>
      <a class="nut nut-phu" href="/">Hủy</a>
    <?php else: ?>
      <a class="nut nut-phu" href="/dang-xuat.php">Đăng xuất</a>
    <?php endif; ?>
  </form>
</div>
<?php dong_trang();

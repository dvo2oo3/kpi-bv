<?php
/**
 * Trang khởi tạo — chỉ chạy được KHI CHƯA CÓ TÀI KHOẢN NÀO.
 * Dùng để tạo tài khoản dev đầu tiên, vì InfinityFree không có dòng lệnh.
 *
 * SAU KHI TẠO XONG, XÓA CẢ THƯ MỤC /install TRÊN MÁY CHỦ.
 */
require_once __DIR__ . '/../app/layout.php';

$soNguoiDung = null;
$loiCsdl = null;
try {
    $soNguoiDung = (int)qVal('SELECT COUNT(*) FROM nguoi_dung');
} catch (Throwable $e) {
    $loiCsdl = 'Chưa tạo bảng. Hãy chạy install/schema.sql trong phpMyAdmin trước.';
}

$loi = null;
if ($loiCsdl === null && $soNguoiDung === 0 && la_post()) {
    kiem_tra_csrf();
    $ten   = post('ten_dang_nhap');
    $hoTen = post('ho_ten');
    $mk    = post('mat_khau');
    $mk2   = post('mat_khau_2');

    if (!preg_match('/^[a-zA-Z0-9._]{3,50}$/', $ten)) {
        $loi = 'Tên đăng nhập chỉ gồm chữ, số, dấu chấm và gạch dưới, dài 3–50 ký tự.';
    } elseif ($hoTen === '') {
        $loi = 'Vui lòng nhập họ tên.';
    } elseif ($mk !== $mk2) {
        $loi = 'Hai lần nhập mật khẩu không khớp.';
    } elseif ($e = kiem_tra_mat_khau($mk)) {
        $loi = $e;
    } else {
        q('INSERT INTO nguoi_dung (ten_dang_nhap, mat_khau_hash, ho_ten, vai_tro, doi_mat_khau)
           VALUES (?,?,?,?,0)',
            [$ten, password_hash($mk, PASSWORD_DEFAULT), $hoTen, 'dev']);
        ghi_nhat_ky_tho((int)db()->lastInsertId(), $ten, 'KHOI_TAO', 'Tạo tài khoản dev đầu tiên');
        nhan_tin('ok', 'Đã tạo tài khoản quản trị cao nhất. Hãy XÓA thư mục /install trên máy chủ ngay.');
        chuyen_huong('/dang-nhap.php');
    }
}

mo_trang('Khởi tạo hệ thống', false);
?>
<div class="the-hop hop-rong">
  <h1>Khởi tạo hệ thống</h1>

  <?php if ($loiCsdl): ?>
    <div class="tb tb-loi"><?= e($loiCsdl) ?></div>
    <ol class="huong-dan">
      <li>Vào InfinityFree Control Panel → <strong>phpMyAdmin</strong></li>
      <li>Chọn cơ sở dữ liệu của anh → tab <strong>SQL</strong></li>
      <li>Mở file <code>install/schema.sql</code>, sao chép toàn bộ nội dung, dán vào và bấm <strong>Go</strong></li>
      <li>Kiểm tra lại thông tin CSDL trong <code>app/config.php</code></li>
      <li>Tải lại trang này</li>
    </ol>

  <?php elseif ($soNguoiDung > 0): ?>
    <div class="tb tb-loi">
      Hệ thống đã có <?= $soNguoiDung ?> tài khoản. Trang khởi tạo đã bị vô hiệu hóa.
    </div>
    <p><strong>Hãy xóa thư mục <code>/install</code> trên máy chủ.</strong></p>
    <p><a class="nut" href="/dang-nhap.php">Đến trang đăng nhập</a></p>

  <?php else: ?>
    <p class="phu">
      Tạo tài khoản <strong>Người phát triển</strong> — quyền cao nhất của hệ thống.
      Chỉ tạo được một lần duy nhất.
    </p>
    <?php if ($loi): ?><div class="tb tb-loi"><?= e($loi) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label>Tên đăng nhập
        <input name="ten_dang_nhap" value="<?= e(post('ten_dang_nhap')) ?>" required autofocus>
      </label>
      <label>Họ và tên
        <input name="ho_ten" value="<?= e(post('ho_ten')) ?>" required>
      </label>
      <label>Mật khẩu
        <input type="password" name="mat_khau" required>
        <small>Tối thiểu 8 ký tự, có cả chữ và số.</small>
      </label>
      <label>Nhập lại mật khẩu
        <input type="password" name="mat_khau_2" required>
      </label>
      <button class="nut" type="submit">Tạo tài khoản</button>
    </form>
  <?php endif; ?>
</div>
<?php dong_trang();

<?php
/**
 * Bật / tắt chế độ bảo trì toàn hệ thống (chỉ dev).
 *
 * Hai mức:
 *   1 – Bảo trì thường : chặn tất cả TRỪ dev, admin và các tài khoản được chọn.
 *   2 – Khóa cứng      : CHỈ dev vào được (admin cũng bị chặn) — dùng khi dev sửa lỗi.
 */
require_once __DIR__ . '/app/layout.php';

$toi = bat_buoc_quyen('he_thong.bao_tri');

// Tài khoản bác sĩ (không phải admin/dev) — để chọn cho vào lúc bảo trì thường.
// Admin luôn vào được ở mức thường nên không cần liệt kê.
$dsChon = qAll("SELECT id, ho_ten, ten_dang_nhap FROM nguoi_dung
                WHERE hoat_dong = 1 AND vai_tro = 'bacsi' ORDER BY ho_ten, ten_dang_nhap");

if (la_post()) {
    kiem_tra_csrf();
    $muc = (string)post('muc');
    if (!in_array($muc, ['0', '1', '2'], true)) { $muc = '0'; }
    $msg = trim((string)($_POST['loi_nhan'] ?? ''));
    $choPhep = array_map('intval', (array)($_POST['cho_phep'] ?? []));

    cai_dat_dat('bao_tri', $muc === '0' ? null : $muc);
    cai_dat_dat('bao_tri_loi_nhan', $msg !== '' ? $msg : null);
    bao_tri_cho_phep_dat($choPhep);

    $nhan = ['0' => 'TAT', '1' => 'BAT_THUONG', '2' => 'KHOA_CUNG'][$muc];
    ghi_nhat_ky('BAO_TRI', $nhan, $msg !== '' ? $msg : null);

    nhan_tin('ok', [
        '0' => 'Đã TẮT bảo trì — mọi người dùng lại bình thường.',
        '1' => 'Đã bật BẢO TRÌ THƯỜNG — chỉ admin, dev và tài khoản được chọn vào được.',
        '2' => 'Đã bật KHÓA CỨNG — chỉ dev vào được, admin cũng bị chặn.',
    ][$muc]);
    chuyen_huong('/bao-tri.php');
}

$muc     = bao_tri_muc();
$choPhep = bao_tri_cho_phep();
$loiNhan = (string)(cai_dat_lay('bao_tri_loi_nhan') ?? '');

$nhanTrangThai = [
    0 => '✅ Đang hoạt động bình thường',
    1 => '🛠️ ĐANG BẢO TRÌ THƯỜNG (admin/dev + tài khoản được chọn vào được)',
    2 => '🔒 ĐANG KHÓA CỨNG (chỉ dev vào được)',
];

mo_trang('Bảo trì hệ thống');
?>
<h1>Bảo trì hệ thống</h1>

<div class="tb <?= $muc > 0 ? 'tb-nguy' : 'tb-ok' ?>" style="margin-bottom:16px">
  Trạng thái hiện tại: <strong><?= e($nhanTrangThai[$muc]) ?></strong>
</div>

<section class="the-hop" style="max-width:680px">
  <form method="post">
    <?= csrf_field() ?>

    <fieldset class="nhom-khoa">
      <legend>Mức bảo trì</legend>

      <label class="o-chon" style="align-items:flex-start">
        <input type="radio" name="muc" value="0" <?= $muc === 0 ? 'checked' : '' ?>>
        <span><strong>Tắt</strong> — mọi người dùng bình thường.</span>
      </label>

      <label class="o-chon" style="align-items:flex-start">
        <input type="radio" name="muc" value="1" <?= $muc === 1 ? 'checked' : '' ?>>
        <span><strong>Bảo trì thường</strong> — chặn tất cả, TRỪ <strong>dev</strong>,
          <strong>admin</strong> và các tài khoản được chọn bên dưới.</span>
      </label>

      <label class="o-chon" style="align-items:flex-start">
        <input type="radio" name="muc" value="2" <?= $muc === 2 ? 'checked' : '' ?>>
        <span><strong>Khóa cứng (dev sửa lỗi)</strong> — <strong>CHỈ dev</strong> vào được,
          admin cũng bị chặn. Dùng khi cần sửa lỗi/nâng cấp mà không ai được đụng vào.</span>
      </label>
    </fieldset>

    <fieldset class="nhom-khoa" style="margin-top:14px">
      <legend>Cho phép vào lúc bảo trì thường (tùy chọn)</legend>
      <p class="phu" style="margin-top:0">Chỉ áp dụng cho <strong>mức Bảo trì thường</strong>.
        Ở mức Khóa cứng, danh sách này bị bỏ qua.</p>
      <?php if (!$dsChon): ?>
        <p class="phu">Chưa có tài khoản bác sĩ nào.</p>
      <?php else: ?>
        <div class="luoi-o-chon">
          <?php foreach ($dsChon as $u): ?>
            <label class="o-chon">
              <input type="checkbox" name="cho_phep[]" value="<?= (int)$u['id'] ?>"
                     <?= in_array((int)$u['id'], $choPhep, true) ? 'checked' : '' ?>>
              <span><?= e($u['ho_ten'] ?: $u['ten_dang_nhap']) ?>
                <small class="nhan-phu">(<?= e($u['ten_dang_nhap']) ?>)</small></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </fieldset>

    <label class="o-rong-2" style="margin-top:14px">Lời nhắn hiển thị cho người bị chặn
      <small class="nhan-phu">(để trống sẽ dùng câu mặc định)</small>
      <textarea name="loi_nhan" rows="3"
        placeholder="Hệ thống đang được bảo trì, nâng cấp. Vui lòng quay lại sau ít phút. Rất xin lỗi vì sự bất tiện này."><?= e($loiNhan) ?></textarea>
    </label>

    <div class="form-chan" style="margin-top:14px">
      <button class="nut nut-chinh" type="submit"
              data-xac-nhan="Lưu cấu hình bảo trì?&#10;Người dùng bị chặn sẽ thấy màn bảo trì ngay."
              data-xac-nhan-loai="nguy">💾 Lưu cấu hình bảo trì</button>
      <a class="nut nut-phu" href="/">Hủy</a>
    </div>
  </form>
</section>

<?php dong_trang();

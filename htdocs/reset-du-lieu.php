<?php
/**
 * Reset dữ liệu — xóa số liệu / kế hoạch để làm lại từ đầu (trả thông số về 0).
 * Chỉ DEV. Giữ nguyên: chỉ tiêu, khoa, tài khoản, cài đặt. Không hoàn tác.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';

$toi = bat_buoc_quyen('he_thong.reset');   // dev-only (QUYEN_RIENG_DEV)

$namNay = (int)date('Y');
$dsNam = range($namNay, $namNay - 4);
$dsKhoa = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');

// Các phần có thể xóa: mã => [nhãn, bảng...]
const PHAN_RESET = [
    'so_lieu'    => ['Số liệu nhập (thực hiện)',            ['so_lieu']],
    'ke_hoach'   => ['Chỉ tiêu giao (kế hoạch năm/tháng)',  ['ke_hoach', 'ke_hoach_thang']],
    'trang_thai' => ['Trạng thái nộp/duyệt (về "trống")',   ['ky']],
    'lich_ky'    => ['Lịch mở kỳ (ngày mở/đóng)',           ['lich_ky']],
    'dieu_chinh' => ['Bút toán điều chỉnh',                 ['dieu_chinh']],
];

if (la_post()) {
    kiem_tra_csrf();
    if (post('viec') === 'reset') {
        $phan = array_values(array_intersect(array_keys(PHAN_RESET), $_POST['phan'] ?? []));
        $namR = (string)post('nam');
        $idKhoaR = (int)post('khoa');
        // Gộp điều kiện: năm + khoa (mọi bảng reset đều có cột nam và id_khoa)
        $conds = []; $params = [];
        $moTaNam = 'tất cả các năm';
        if (ctype_digit($namR) && $namR !== '0') {
            $conds[] = 'nam = ?'; $params[] = (int)$namR;
            $moTaNam = 'năm ' . (int)$namR;
        }
        $moTaKhoa = 'mọi khoa';
        if ($idKhoaR > 0) {
            $conds[] = 'id_khoa = ?'; $params[] = $idKhoaR;
            $moTaKhoa = 'khoa ' . (qVal('SELECT ma FROM khoa WHERE id = ?', [$idKhoaR]) ?: $idKhoaR);
        }
        $dieuKien = $conds ? ' WHERE ' . implode(' AND ', $conds) : '';

        if (trim((string)post('xac_nhan')) !== 'XOA') {
            nhan_tin('loi', 'Phải gõ đúng chữ XOA (in hoa) để xác nhận.');
        } elseif (!$phan) {
            nhan_tin('loi', 'Chưa chọn phần dữ liệu nào để xóa.');
        } else {
            $bang = [];
            foreach ($phan as $p) { foreach (PHAN_RESET[$p][1] as $b) { $bang[] = $b; } }
            $tong = 0;
            db()->beginTransaction();
            foreach ($bang as $b) {
                // $b lấy từ hằng số PHAN_RESET (không phải dữ liệu người dùng) → an toàn
                $tong += q("DELETE FROM $b" . $dieuKien, $params)->rowCount();
            }
            db()->commit();
            ghi_nhat_ky('RESET_DU_LIEU', "$moTaNam · $moTaKhoa",
                implode(', ', $bang) . " — $tong dòng");
            nhan_tin('ok', "Đã xóa $tong dòng ($moTaNam · $moTaKhoa): " . implode(', ', $bang)
                . '. Chỉ tiêu, khoa, tài khoản vẫn giữ nguyên.');
        }
        chuyen_huong('/reset-du-lieu.php');
    }
}

mo_trang('Reset dữ liệu');
?>
<h1>Reset dữ liệu — trả thông số về 0</h1>

<div class="tb tb-nguy" style="border:1px solid var(--loi-vien,#f0c);background:var(--loi-nen,#fee)">
  <strong>⚠ Xóa vĩnh viễn, KHÔNG hoàn tác.</strong> Thao tác này xóa số liệu/kế hoạch để làm lại từ đầu.
  <strong>Chỉ tiêu, khoa, tài khoản, cài đặt vẫn giữ nguyên.</strong>
  <?php if (co_quyen('sao_luu.tai_ve')): ?>
    <div style="margin-top:6px">Nên <a href="/sao-luu.php?tai=sql"><strong>tải bản sao lưu</strong></a> trước khi xóa.</div>
  <?php endif; ?>
</div>

<form method="post" class="form-tai-khoan" style="max-width:640px"
      data-xac-nhan="XÓA vĩnh viễn dữ liệu đã chọn? Không hoàn tác được." data-xac-nhan-loai="nguy">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="reset">

  <label>Phạm vi năm
    <select name="nam">
      <option value="0">Tất cả các năm</option>
      <?php foreach ($dsNam as $n): ?>
        <option value="<?= $n ?>" <?= $n === $namNay ? 'selected' : '' ?>>Năm <?= $n ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Phạm vi khoa
    <select name="khoa">
      <option value="0">Mọi khoa (toàn bộ)</option>
      <?php foreach ($dsKhoa as $k): ?>
        <option value="<?= (int)$k['id'] ?>">Chỉ khoa <?= e($k['ten']) ?> (<?= e($k['ma']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </label>

  <fieldset class="nhom-khoa" style="margin-top:1rem">
    <legend>Chọn phần dữ liệu cần xóa</legend>
    <div class="luoi-o-chon">
      <?php foreach (PHAN_RESET as $ma => [$nhan, $bangs]): ?>
        <label class="o-chon">
          <input type="checkbox" name="phan[]" value="<?= $ma ?>"
                 <?= in_array($ma, ['so_lieu','ke_hoach','trang_thai'], true) ? 'checked' : '' ?>>
          <span><?= e($nhan) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="phu">Mặc định tích <em>Số liệu nhập</em> + <em>Chỉ tiêu giao</em> + <em>Trạng thái nộp/duyệt</em>
      — con số về 0 và kỳ trở lại "trống" (chưa nộp) để khoa nhập lại từ đầu.
      <em>Lịch mở kỳ</em> giữ nguyên (không xóa lịch) trừ khi tích thêm.</p>
  </fieldset>

  <label style="margin-top:1rem">Gõ <code>XOA</code> để xác nhận
    <input type="text" name="xac_nhan" autocomplete="off" placeholder="XOA" required
           style="max-width:160px;letter-spacing:2px;font-weight:700;text-align:center">
  </label>

  <div class="form-chan">
    <button class="nut nut-nguy" type="submit">Xóa dữ liệu đã chọn</button>
    <a class="nut nut-phu" href="/">Hủy</a>
  </div>
</form>
<?php dong_trang();

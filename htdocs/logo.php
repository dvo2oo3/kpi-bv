<?php
/**
 * Đổi logo ứng dụng (đồng thời là favicon). Chỉ nhận ảnh, lưu vào bảng cai_dat
 * dưới dạng data URI nên không phụ thuộc thư mục ghi được trên máy chủ.
 *
 * Không đặt giới hạn dung lượng nhân tạo: trần thực tế là cấu hình PHP của
 * máy chủ (upload_max_filesize / post_max_size). Chỉ giữ một chốt an toàn để
 * không vượt sức chứa cột MEDIUMTEXT.
 */
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/cai_dat.php';

bat_buoc_quyen('he_thong.logo');

// Cột gia_tri là MEDIUMTEXT (~16 MB). Chuỗi base64 phồng ~33% nên chặn ở 15 MB.
const LOGO_CHUOI_TOI_DA = 15 * 1024 * 1024;
const LOGO_LOAI = [
    IMAGETYPE_PNG  => 'image/png',
    IMAGETYPE_JPEG => 'image/jpeg',
    IMAGETYPE_GIF  => 'image/gif',
    IMAGETYPE_WEBP => 'image/webp',
];

$quayVe = '/';

if (la_post()) {
    kiem_tra_csrf();

    // Gỡ logo → về mặc định
    if (post('viec') === 'go') {
        cai_dat_dat('logo', null);
        cai_dat_dat('logo_v', null);
        ghi_nhat_ky('DOI_LOGO', 'cai_dat', 'Gỡ logo, dùng mặc định');
        nhan_tin('ok', 'Đã gỡ logo, quay về biểu tượng mặc định.');
        chuyen_huong($quayVe);
    }

    $tep = $_FILES['tep'] ?? null;

    // Vượt trần PHP của máy chủ → báo rõ trần đó là bao nhiêu
    if ($tep && in_array($tep['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        nhan_tin('loi', 'Ảnh vượt mức máy chủ cho phép (tối đa '
            . ini_get('upload_max_filesize') . '). Hãy dùng ảnh nhỏ hơn.');
        chuyen_huong($quayVe);
    }
    if (!$tep || $tep['error'] === UPLOAD_ERR_NO_FILE) {
        nhan_tin('loi', 'Chưa chọn tệp ảnh.');
        chuyen_huong($quayVe);
    }
    if ($tep['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($tep['tmp_name'])) {
        nhan_tin('loi', 'Tải tệp lên thất bại, thử lại.');
        chuyen_huong($quayVe);
    }

    $tt = @getimagesize($tep['tmp_name']);
    if ($tt === false || !isset(LOGO_LOAI[$tt[2]])) {
        nhan_tin('loi', 'Tệp không phải ảnh hợp lệ (chỉ nhận PNG, JPG, GIF, WEBP).');
        chuyen_huong($quayVe);
    }

    $noi_dung = file_get_contents($tep['tmp_name']);
    if ($noi_dung === false) {
        nhan_tin('loi', 'Không đọc được tệp.');
        chuyen_huong($quayVe);
    }

    $dataUri = 'data:' . LOGO_LOAI[$tt[2]] . ';base64,' . base64_encode($noi_dung);
    if (strlen($dataUri) > LOGO_CHUOI_TOI_DA) {
        nhan_tin('loi', 'Ảnh quá lớn để lưu (giới hạn kỹ thuật ~11 MB ảnh gốc).');
        chuyen_huong($quayVe);
    }

    cai_dat_dat('logo', $dataUri);
    cai_dat_dat('logo_v', (string)time());
    ghi_nhat_ky('DOI_LOGO', 'cai_dat', 'Cập nhật logo (' . LOGO_LOAI[$tt[2]] . ')');
    nhan_tin('ok', 'Đã cập nhật logo. Tải lại trang nếu favicon chưa đổi ngay.');
    chuyen_huong($quayVe);
}

chuyen_huong($quayVe);

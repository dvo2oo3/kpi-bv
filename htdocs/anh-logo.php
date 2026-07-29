<?php
/**
 * Phục vụ ảnh logo ứng dụng (dùng cho <img> và favicon).
 * URL có ?v=<phiên bản> nên đặt cache dài; đổi logo → v đổi → tải lại.
 * Không cần đăng nhập: logo là nhận diện công khai (xuất hiện cả ở trang login).
 */
require_once __DIR__ . '/app/cai_dat.php';

$logo = logo_du_lieu();
if ($logo === null
    || !preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#is', $logo, $m)) {
    http_response_code(404);
    exit;
}

$bytes = base64_decode($m[2], true);
if ($bytes === false) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $m[1]);
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: public, max-age=31536000, immutable');
echo $bytes;

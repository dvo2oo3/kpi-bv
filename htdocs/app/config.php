<?php
/**
 * Cấu hình hệ thống.
 *
 * Trên InfinityFree, thông tin CSDL lấy ở: Control Panel > MySQL Databases.
 * Tên CSDL và tên đăng nhập đều có tiền tố dạng if0_XXXXXXXX_
 */

// ---- Cơ sở dữ liệu ----
define('DB_HOST', getenv('QLBV_DB_HOST') ?: 'sqlXXX.infinityfree.com');
define('DB_NAME', getenv('QLBV_DB_NAME') ?: 'if0_XXXXXXXX_qlbv');
define('DB_USER', getenv('QLBV_DB_USER') ?: 'if0_XXXXXXXX');
define('DB_PASS', getenv('QLBV_DB_PASS') ?: '');

// ---- Ứng dụng ----
define('TEN_DON_VI', 'TRUNG TÂM Y TẾ NAM SÁCH');
define('TEN_UNG_DUNG', 'Quản lý Chỉ tiêu Kế hoạch Chuyên môn');
define('NAM_MAC_DINH', (int)date('Y'));

// ---- Bảo mật đăng nhập ----
define('SO_LAN_SAI_TOI_DA', 5);      // sai quá số lần này thì khóa tạm
define('PHUT_KHOA_TAM', 15);         // số phút khóa tạm
define('PHUT_HET_PHIEN', 120);       // không thao tác bao lâu thì hết phiên

// ---- Múi giờ ----
date_default_timezone_set('Asia/Ho_Chi_Minh');

// ---- Bật lỗi khi phát triển, tắt khi chạy thật ----
// Đặt biến môi trường QLBV_GO_LOI=1 khi chạy thử ở máy cá nhân.
// Trên máy chủ thật KHÔNG đặt biến này, lỗi sẽ tự động bị ẩn.
define('CHE_DO_GO_LOI', getenv('QLBV_GO_LOI') === '1');
if (CHE_DO_GO_LOI) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

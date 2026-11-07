-- =====================================================================
-- Sửa lỗi mã hoá (mojibake) cho chỉ tiêu "Bệnh nhân quản lý TĐ"
-- ---------------------------------------------------------------------
-- Nguyên nhân: tên + đơn vị của chỉ tiêu này bị lưu KHÔNG phải UTF-8
-- (bộ mã CP1258/Latin), nên khi trang Giao chỉ tiêu trả JSON lúc lưu,
-- json_encode() bị lỗi -> trả về rỗng -> trình duyệt không cập nhật được
-- (trên host MySQL utf8mb4, câu UPDATE còn có thể bị từ chối thẳng),
-- khiến "Loại giá trị (riêng khoa)" vừa chọn bị quay về Hằng số.
--
-- An toàn: chỉ sửa đúng 1 chỉ tiêu, nhắm theo MÃ (không theo id, vì id
-- trên host có thể khác). KHÔNG khôi phục toàn bộ CSDL.
--
-- Cách chạy trên host (phpMyAdmin hoặc mysql CLI) — ĐẢM BẢO kết nối UTF-8:
--   mysql --default-character-set=utf8mb4 -u <user> -p <db> < sua_utf8_benh_nhan_quan_ly_td.sql
-- hoặc dán vào tab SQL của phpMyAdmin (phpMyAdmin đã dùng utf8mb4 sẵn).
-- =====================================================================

SET NAMES utf8mb4;

-- 1) Sửa tên + đơn vị về đúng UTF-8
UPDATE chi_tieu
   SET ten    = 'Bệnh nhân quản lý TĐ',
       don_vi = 'Lượt'
 WHERE ma = 'BENH_NHAN_QUAN_LY_TIEU_DUO';

-- 2) (Tuỳ chọn) dọn các dòng nhật ký cũ ghi lại tên bị lỗi mã hoá
UPDATE nhat_ky
   SET chi_tiet = 'Bệnh nhân quản lý TĐ'
 WHERE doi_tuong = 'BENH_NHAN_QUAN_LY_TIEU_DUO'
   AND HEX(chi_tiet) LIKE '%EA6E68%';   -- chỉ những dòng còn mojibake

-- 3) Kiểm tra lại (phải thấy tên hiển thị đúng, không còn dấu ? hay ký tự lạ)
SELECT id, ma, ten, don_vi FROM chi_tieu WHERE ma = 'BENH_NHAN_QUAN_LY_TIEU_DUO';

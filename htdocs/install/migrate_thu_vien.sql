-- Nâng cấp: thư viện chỉ tiêu chuẩn + gộp số liệu.
-- Chạy MỘT LẦN trên CSDL production (phpMyAdmin > SQL) sau migrate_cong_thuc.sql.
-- Nếu cột đã tồn tại MySQL báo "Duplicate column" — bỏ qua.

ALTER TABLE chi_tieu
  ADD COLUMN la_chuan TINYINT(1)  NOT NULL DEFAULT 1 AFTER nhan_so_ngay,
  ADD COLUMN gop_vao  VARCHAR(30) NULL              AFTER la_chuan;

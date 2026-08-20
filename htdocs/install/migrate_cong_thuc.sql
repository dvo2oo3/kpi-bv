-- Nâng cấp: công thức tự cấu hình cho chỉ tiêu.
-- Chạy MỘT LẦN trên CSDL production (phpMyAdmin > SQL) sau khi đã có bảng chi_tieu.
-- An toàn chạy lại: nếu cột đã tồn tại MySQL sẽ báo lỗi "Duplicate column" — bỏ qua.

ALTER TABLE chi_tieu
  ADD COLUMN phep_tinh    VARCHAR(10) NULL          AFTER phan_bo,
  ADD COLUMN ct_tu        VARCHAR(30) NULL          AFTER phep_tinh,
  ADD COLUMN ct_mau       VARCHAR(30) NULL          AFTER ct_tu,
  ADD COLUMN nhan_so_ngay TINYINT(1)  NOT NULL DEFAULT 0 AFTER ct_mau;

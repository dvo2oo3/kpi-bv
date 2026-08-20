-- Nâng cấp: thứ tự chỉ tiêu RIÊNG theo từng khoa.
-- Chạy MỘT LẦN trên CSDL production (phpMyAdmin > SQL).
-- Nếu cột đã tồn tại MySQL báo "Duplicate column" — bỏ qua bước ADD.
--
-- Trước đây thứ tự chỉ tiêu dùng chung toàn hệ thống (chi_tieu.thu_tu) nên sắp xếp
-- ở một khoa làm đổi thứ tự mọi khoa. Cột mới cho mỗi khoa một thứ tự độc lập.

-- 1) Thêm cột thứ tự riêng vào bảng liên kết (0 = chưa đặt → lùi về thứ tự thư viện).
ALTER TABLE chi_tieu_ap_dung
  ADD COLUMN thu_tu INT NOT NULL DEFAULT 0 AFTER id_khoa;

-- 2) Điền sẵn = thứ tự thư viện hiện tại, để lúc đầu mọi khoa trông y như cũ.
UPDATE chi_tieu_ap_dung a
  JOIN chi_tieu c ON c.id = a.id_chi_tieu
   SET a.thu_tu = c.thu_tu;

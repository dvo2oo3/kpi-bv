-- Nâng cấp: thêm loại giá trị "Ghi chú" (chỉ hiển thị, không cộng dồn, không chấm điểm).
-- Chạy MỘT LẦN trên CSDL production (phpMyAdmin > SQL).
--
-- loai_gia_tri là cột ENUM nên phải mở rộng danh sách cho phép thì mới lưu được
-- giá trị mới 'GHI_CHU'. Bản SQLite ở máy cá nhân là cột TEXT nên không cần chạy.

ALTER TABLE chi_tieu
  MODIFY loai_gia_tri ENUM('DEM','TRUNG_BINH','TY_LE','HANG_SO','GHI_CHU')
         NOT NULL DEFAULT 'DEM';

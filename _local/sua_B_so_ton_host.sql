-- =====================================================================
-- B) Sửa "số tồn bị cộng dồn" trên HOST
-- "Bệnh nhân quản lý TĐ/THA" là SỐ ĐANG QUẢN LÝ (số tồn) — phải lấy số kỳ
-- gần nhất, KHÔNG cộng 12 tháng. Hiện host để "Đếm" nên ra 29.468 (1.592%).
-- SQL này: (1) sửa mã hoá tên, (2) đổi loại = Hằng số, (3) bỏ ghi đè riêng khoa.
-- An toàn, đảo lại được. Chạy trên phpMyAdmin (tab SQL), DB utf8mb4.
-- =====================================================================
SET NAMES utf8mb4;

-- (1) Sửa mã hoá tên (hết chữ "Bênh")
UPDATE chi_tieu SET ten = 'Bệnh nhân quản lý TĐ'  WHERE ma = 'BENH_NHAN_QUAN_LY_TIEU_DUO';
UPDATE chi_tieu SET ten = 'Bệnh nhân quản lý THA' WHERE ma = 'BENH_NHAN_QUAN_LY_TANG_HUY';

-- (2) Đổi loại giá trị = Hằng số (số tồn)
UPDATE chi_tieu SET loai_gia_tri = 'HANG_SO'
 WHERE ma IN ('BENH_NHAN_QUAN_LY_TIEU_DUO','BENH_NHAN_QUAN_LY_TANG_HUY');

-- (3) Bỏ ghi đè loại riêng theo khoa (nếu có) để theo loại chung
UPDATE chi_tieu_ap_dung SET loai_gia_tri = NULL
 WHERE id_chi_tieu IN (
   SELECT id FROM chi_tieu
    WHERE ma IN ('BENH_NHAN_QUAN_LY_TIEU_DUO','BENH_NHAN_QUAN_LY_TANG_HUY')
 );

-- (4) Kiểm tra lại
SELECT id, ma, ten, loai_gia_tri
  FROM chi_tieu
 WHERE ma IN ('BENH_NHAN_QUAN_LY_TIEU_DUO','BENH_NHAN_QUAN_LY_TANG_HUY');

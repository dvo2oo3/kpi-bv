-- Nang cap: loai gia tri RIENG theo khoa (mo hinh 2 tang).
-- Chay MOT LAN tren host (phpMyAdmin > SQL) SAU khi da day code moi.
--
-- Them cot loai_gia_tri (NULL) vao chi_tieu_ap_dung. NULL = dung loai chung
-- cua chi_tieu; co gia tri = khoa do dung loai rieng.
-- Cho phep cung 1 ma: khoa A = Ghi chu (khong cong), khoa B = Dem (cong).
--
-- Neu cot da ton tai, MySQL bao "Duplicate column" -> bo qua.

ALTER TABLE chi_tieu_ap_dung
  ADD COLUMN loai_gia_tri ENUM('DEM','TRUNG_BINH','TY_LE','HANG_SO','GHI_CHU')
    NULL DEFAULT NULL AFTER thu_tu;

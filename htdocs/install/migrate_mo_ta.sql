-- Nang cap: them cot mo_ta (mo ta / ghi chu quan ly) cho bang chi_tieu.
-- Chay MOT LAN tren host (phpMyAdmin > SQL).
--
-- App co dung cot mo_ta (o "Mo ta / Ghi chu" khi Sua chi tieu) nhung schema MySQL
-- cu chua co cot nay -> moi lan Sua chi tieu, cau UPDATE ghi mo_ta bi loi
-- "Unknown column 'mo_ta'" -> khong luu duoc gi + trang reload. Them cot nay la het.
-- Neu cot da ton tai MySQL bao "Duplicate column" -> bo qua.

ALTER TABLE chi_tieu
  ADD COLUMN mo_ta VARCHAR(255) NULL AFTER gop_vao;

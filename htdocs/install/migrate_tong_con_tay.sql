-- Nang cap: them nguon so lieu "TONG_CON_TAY" (Tong cua con - sua tay duoc).
-- Chay MOT LAN tren host (phpMyAdmin > SQL) SAU khi da day code moi.
--
-- Nguon nay: mac dinh TU CONG cac con giong 'tong cua con', nhung cho phep
-- NHAP TAY de len khi tong tu cong bi sai (vd cac con la "trong do" = tap con).
--
-- ALTER MODIFY la idempotent: chay lai nhieu lan van an toan (khong bao loi).

ALTER TABLE chi_tieu
  MODIFY nguon ENUM('NHAP_TAY','TONG_CON','TONG_CON_TAY','CONG_THUC')
    NOT NULL DEFAULT 'NHAP_TAY';

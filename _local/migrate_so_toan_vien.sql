-- Migration HOST: thêm bảng số toàn viện nhập tay. Chạy 1 lần trên phpMyAdmin.
CREATE TABLE IF NOT EXISTS so_toan_vien (
  nam           INT NOT NULL,
  id_chi_tieu   INT NOT NULL,
  chi_tieu_giao DECIMAL(14,2) NULL,
  thuc_hien     DECIMAL(14,2) NULL,
  ghi_chu       VARCHAR(255) NULL,
  PRIMARY KEY (nam, id_chi_tieu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bổ sung: bảng đè số toàn viện theo từng THÁNG.
CREATE TABLE IF NOT EXISTS so_toan_vien_thang (
  nam INT NOT NULL, thang TINYINT NOT NULL, id_chi_tieu INT NOT NULL,
  gia_tri DECIMAL(14,2) NULL,
  PRIMARY KEY (nam, thang, id_chi_tieu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

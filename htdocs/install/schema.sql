-- =============================================================
-- Quản lý Chỉ tiêu Kế hoạch Chuyên môn — Trung tâm Y tế Nam Sách
-- Lược đồ đầy đủ (MySQL 8 / MariaDB)
--
-- Chạy trên InfinityFree: Control Panel > phpMyAdmin > tab SQL > dán và chạy.
-- Chạy lại nhiều lần được, không mất dữ liệu.
-- =============================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------
-- 0. Cài đặt chung (khóa – giá trị). Lưu logo, tên hiển thị…
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cai_dat (
  khoa      VARCHAR(50) NOT NULL,
  gia_tri   MEDIUMTEXT  NULL,
  PRIMARY KEY (khoa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 1. Danh mục khoa
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS khoa (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  ma           VARCHAR(20)  NOT NULL,
  ten          VARCHAR(150) NOT NULL,
  loai         ENUM('NOI_TRU','NGOAI_TRU','CAN_LAM_SANG') NOT NULL DEFAULT 'NOI_TRU',
  giuong_benh  INT          NOT NULL DEFAULT 0,
  thu_tu       INT          NOT NULL DEFAULT 0,
  hoat_dong    TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uq_khoa_ma (ma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 2. Người dùng
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nguoi_dung (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  ten_dang_nhap      VARCHAR(50)  NOT NULL,
  mat_khau_hash      VARCHAR(255) NOT NULL,
  ho_ten             VARCHAR(150) NOT NULL,
  vai_tro            ENUM('dev','admin','bacsi') NOT NULL DEFAULT 'bacsi',
  chuc_vu            VARCHAR(100) NULL,
  dien_thoai         VARCHAR(30)  NULL,
  hoat_dong          TINYINT(1)   NOT NULL DEFAULT 1,
  doi_mat_khau       TINYINT(1)   NOT NULL DEFAULT 1,
  so_lan_sai         INT          NOT NULL DEFAULT 0,
  khoa_den           DATETIME     NULL,
  lan_dang_nhap_cuoi DATETIME     NULL,
  nguoi_tao          INT          NULL,
  ngay_tao           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_nd_ten (ten_dang_nhap),
  KEY idx_nd_vai_tro (vai_tro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nguoi_dung_khoa (
  id_nguoi_dung INT NOT NULL,
  id_khoa       INT NOT NULL,
  PRIMARY KEY (id_nguoi_dung, id_khoa),
  KEY idx_ndk_khoa (id_khoa),
  CONSTRAINT fk_ndk_nd   FOREIGN KEY (id_nguoi_dung) REFERENCES nguoi_dung(id) ON DELETE CASCADE,
  CONSTRAINT fk_ndk_khoa FOREIGN KEY (id_khoa)       REFERENCES khoa(id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 3. Danh mục chỉ tiêu (cấu trúc cây)
--
--   loai_gia_tri  DEM        : số đếm, cộng dồn được
--                 TRUNG_BINH : trung bình, KHÔNG cộng dồn
--                 TY_LE      : tỷ lệ %, KHÔNG cộng dồn
--                 HANG_SO    : không đổi theo tháng (giường bệnh)
--   nguon         NHAP_TAY   : khoa nhập
--                 TONG_CON   : bằng tổng các chỉ tiêu con
--                 CONG_THUC  : tính theo công thức (xem app/chi_tieu.php)
--   huong         CAO_TOT | THAP_TOT | DICH_CO_DINH
--   phan_bo       THEO_NGAY  : chia chỉ tiêu năm theo số ngày của tháng
--                 KHONG_CHIA : giữ nguyên mức năm cho mọi tháng
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chi_tieu (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  ma           VARCHAR(30)  NOT NULL,
  ten          VARCHAR(200) NOT NULL,
  don_vi       VARCHAR(20)  NOT NULL DEFAULT '',
  id_cha       INT          NULL,
  thu_tu       INT          NOT NULL DEFAULT 0,
  loai_gia_tri ENUM('DEM','TRUNG_BINH','TY_LE','HANG_SO','GHI_CHU') NOT NULL DEFAULT 'DEM',
  nguon        ENUM('NHAP_TAY','TONG_CON','CONG_THUC')    NOT NULL DEFAULT 'NHAP_TAY',
  huong        ENUM('CAO_TOT','THAP_TOT','DICH_CO_DINH')  NOT NULL DEFAULT 'CAO_TOT',
  phan_bo      ENUM('THEO_NGAY','KHONG_CHIA')             NOT NULL DEFAULT 'THEO_NGAY',
  -- Công thức tự cấu hình (khi nguon='CONG_THUC' và không phải mã hệ thống):
  --   ket_qua = ct_tu / mau  (×100 nếu phep_tinh='TY_LE'); mau nhân số ngày nếu nhan_so_ngay=1
  phep_tinh    VARCHAR(10)  NULL,   -- 'TY_LE' | 'THUONG'
  ct_tu        VARCHAR(30)  NULL,   -- mã chỉ tiêu tử số
  ct_mau       VARCHAR(30)  NULL,   -- mã chỉ tiêu mẫu số
  nhan_so_ngay TINYINT(1)   NOT NULL DEFAULT 0,
  -- Thư viện chuẩn: la_chuan=1 là chỉ tiêu dùng chung (lên dashboard/tổng hợp),
  -- =0 là chỉ tiêu riêng của khoa. gop_vao = mã chỉ tiêu chuẩn để cộng số liệu vào.
  la_chuan     TINYINT(1)   NOT NULL DEFAULT 1,
  gop_vao      VARCHAR(30)  NULL,
  mo_ta        VARCHAR(255) NULL,      -- mô tả / ghi chú quản lý (chữ nhạt dưới tên)
  hoat_dong    TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uq_ct_ma (ma),
  KEY idx_ct_cha (id_cha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chỉ tiêu nào áp dụng cho khoa nào.
-- thu_tu: thứ tự RIÊNG của chỉ tiêu trong khoa này (0 = chưa đặt riêng → lùi về
-- thứ tự thư viện chi_tieu.thu_tu). Nhờ vậy sắp xếp ở một khoa không ảnh hưởng khoa khác.
CREATE TABLE IF NOT EXISTS chi_tieu_ap_dung (
  id_chi_tieu INT NOT NULL,
  id_khoa     INT NOT NULL,
  thu_tu      INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id_chi_tieu, id_khoa),
  KEY idx_ctad_khoa (id_khoa),
  CONSTRAINT fk_ctad_ct   FOREIGN KEY (id_chi_tieu) REFERENCES chi_tieu(id) ON DELETE CASCADE,
  CONSTRAINT fk_ctad_khoa FOREIGN KEY (id_khoa)     REFERENCES khoa(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 4. Chỉ tiêu giao theo năm
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ke_hoach (
  nam               INT NOT NULL,
  id_khoa           INT NOT NULL,
  id_chi_tieu       INT NOT NULL,
  chi_tieu_giao     DECIMAL(14,2) NULL,   -- theo quyết định giao khoa
  chi_tieu_nang_luc DECIMAL(14,2) NULL,   -- tính từ giường bệnh (tham khảo)
  th_nam_truoc      DECIMAL(14,2) NULL,   -- thực hiện năm trước, để so sánh
  PRIMARY KEY (nam, id_khoa, id_chi_tieu),
  KEY idx_kh_khoa (id_khoa),
  CONSTRAINT fk_kh_khoa FOREIGN KEY (id_khoa)     REFERENCES khoa(id)     ON DELETE CASCADE,
  CONSTRAINT fk_kh_ct   FOREIGN KEY (id_chi_tieu) REFERENCES chi_tieu(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hệ số mùa vụ ghi đè cho từng tháng (không bắt buộc).
-- Không có bản ghi thì phân bổ theo số ngày trong tháng.
CREATE TABLE IF NOT EXISTS ke_hoach_thang (
  nam         INT NOT NULL,
  thang       TINYINT NOT NULL,
  id_khoa     INT NOT NULL,
  id_chi_tieu INT NOT NULL,
  chi_tieu    DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (nam, thang, id_khoa, id_chi_tieu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5. Kỳ nhập liệu (mỗi khoa mỗi tháng một bản ghi)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ky (
  nam            INT NOT NULL,
  thang          TINYINT NOT NULL,
  id_khoa        INT NOT NULL,
  trang_thai     ENUM('MO','DA_NOP','DA_DUYET','DA_KHOA') NOT NULL DEFAULT 'MO',
  nguoi_nop      INT NULL,
  thoi_diem_nop  DATETIME NULL,
  nguoi_duyet    INT NULL,
  thoi_diem_duyet DATETIME NULL,
  ghi_chu        TEXT NULL,
  -- Gia hạn riêng cho kỳ này: admin trả lại hoặc mở thêm tới ngày nào
  mo_den         DATE NULL,
  PRIMARY KEY (nam, thang, id_khoa),
  KEY idx_ky_trang_thai (trang_thai),
  CONSTRAINT fk_ky_khoa FOREIGN KEY (id_khoa) REFERENCES khoa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5b. Lịch mở kỳ nhập liệu — do admin đặt
--
--   id_khoa = 0  : áp dụng cho MỌI khoa
--   id_khoa > 0  : lịch riêng của một khoa, đè lên lịch chung
--
-- Không đặt lịch thì hệ thống dùng quy tắc mặc định
-- (mở từ ngày 1 của tháng tới hết ngày 5 tháng sau).
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lich_ky (
  nam       INT NOT NULL,
  thang     TINYINT NOT NULL,
  id_khoa   INT NOT NULL DEFAULT 0,
  mo_tu     DATE NOT NULL,
  dong_sau  DATE NOT NULL,
  ghi_chu   VARCHAR(255) NULL,
  nguoi_dat INT NULL,
  thoi_diem DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (nam, thang, id_khoa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5c. Quyền cấp riêng cho từng người
--
-- Ngoài quyền theo vai trò, admin có thể ủy quyền một số việc
-- cho người cụ thể (ví dụ giao cho một trưởng khoa quyền duyệt kỳ).
-- Không cấp được các quyền dành riêng cho người phát triển.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS quyen_nguoi_dung (
  id_nguoi_dung INT NOT NULL,
  quyen         VARCHAR(50) NOT NULL,
  nguoi_cap     INT NULL,
  thoi_diem     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_nguoi_dung, quyen),
  CONSTRAINT fk_qnd_nd FOREIGN KEY (id_nguoi_dung) REFERENCES nguoi_dung(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 6. Số liệu thực hiện
--    gia_tri NULL nghĩa là CHƯA NHẬP, khác hẳn giá trị 0.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS so_lieu (
  nam           INT NOT NULL,
  thang         TINYINT NOT NULL,
  id_khoa       INT NOT NULL,
  id_chi_tieu   INT NOT NULL,
  gia_tri       DECIMAL(14,2) NULL,
  la_chien_dich TINYINT(1) NOT NULL DEFAULT 0,  -- số liệu từ đợt khám tập trung
  ghi_chu       VARCHAR(255) NULL,
  nguoi_nhap    INT NULL,
  thoi_diem     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (nam, thang, id_khoa, id_chi_tieu),
  KEY idx_sl_khoa_ct (id_khoa, id_chi_tieu),
  CONSTRAINT fk_sl_khoa FOREIGN KEY (id_khoa)     REFERENCES khoa(id)     ON DELETE CASCADE,
  CONSTRAINT fk_sl_ct   FOREIGN KEY (id_chi_tieu) REFERENCES chi_tieu(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 7. Bút toán điều chỉnh số liệu đã khóa
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dieu_chinh (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nam           INT NOT NULL,
  thang         TINYINT NOT NULL,
  id_khoa       INT NOT NULL,
  id_chi_tieu   INT NOT NULL,
  gia_tri_cu    DECIMAL(14,2) NULL,
  gia_tri_moi   DECIMAL(14,2) NULL,
  ly_do         TEXT NOT NULL,
  trang_thai    ENUM('CHO_DUYET','DA_DUYET','TU_CHOI') NOT NULL DEFAULT 'CHO_DUYET',
  nguoi_de_xuat INT NULL,
  nguoi_duyet   INT NULL,
  thoi_diem     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dc_ky (nam, thang, id_khoa),
  KEY idx_dc_trang_thai (trang_thai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 8. Nhật ký hệ thống
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nhat_ky (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_nguoi_dung INT          NULL,
  ten_dang_nhap VARCHAR(50)  NULL,
  hanh_dong     VARCHAR(50)  NOT NULL,
  doi_tuong     VARCHAR(150) NULL,
  chi_tiet      TEXT         NULL,
  dia_chi_ip    VARCHAR(45)  NULL,
  thoi_diem     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_nk_thoi_diem (thoi_diem),
  KEY idx_nk_nguoi (id_nguoi_dung),
  KEY idx_nk_hanh_dong (hanh_dong)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- DỮ LIỆU BAN ĐẦU
-- =============================================================

-- 11 khoa theo Quyết định 33b/QĐ-TTYT (tổng 215 giường, khớp QĐ 74/QĐ-SYT)
INSERT INTO khoa (ma, ten, loai, giuong_benh, thu_tu) VALUES
  ('PK',    'Khoa Khám bệnh',                      'NGOAI_TRU',     0,  1),
  ('HSCC',  'Khoa Cấp cứu - HSTC & Chống độc',     'NOI_TRU',      25,  2),
  ('NOI',   'Khoa Nội',                            'NOI_TRU',      60,  3),
  ('NGOAI', 'Khoa Ngoại - PT - GMHS',              'NOI_TRU',      20,  4),
  ('NHI',   'Khoa Nhi',                            'NOI_TRU',      25,  5),
  ('SAN',   'Khoa CSSKSS và Phụ sản',              'NOI_TRU',      15,  6),
  ('TN',    'Khoa Truyền nhiễm',                   'NOI_TRU',      20,  7),
  ('YHCT',  'Khoa YHCT - Phục hồi chức năng',      'NOI_TRU',      35,  8),
  ('LCK',   'Khoa Liên chuyên khoa RHM-Mắt-TMH',   'NOI_TRU',      15,  9),
  ('XN',    'Khoa Xét nghiệm',                     'CAN_LAM_SANG',  0, 10),
  ('CDHA',  'Khoa Chẩn đoán hình ảnh',             'CAN_LAM_SANG',  0, 11)
ON DUPLICATE KEY UPDATE ten = VALUES(ten);

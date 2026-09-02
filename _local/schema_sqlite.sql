-- Bản SQLite CHỈ dùng để chạy thử ở máy cá nhân.
-- Máy chủ thật dùng htdocs/install/schema.sql (MySQL).

CREATE TABLE cai_dat (
  khoa TEXT PRIMARY KEY,
  gia_tri TEXT
);

CREATE TABLE khoa (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ma TEXT NOT NULL UNIQUE, ten TEXT NOT NULL,
  loai TEXT NOT NULL DEFAULT 'NOI_TRU',
  giuong_benh INTEGER NOT NULL DEFAULT 0,
  thu_tu INTEGER NOT NULL DEFAULT 0,
  hoat_dong INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE nguoi_dung (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ten_dang_nhap TEXT NOT NULL UNIQUE,
  mat_khau_hash TEXT NOT NULL,
  ho_ten TEXT NOT NULL,
  vai_tro TEXT NOT NULL DEFAULT 'bacsi',
  chuc_vu TEXT, dien_thoai TEXT,
  hoat_dong INTEGER NOT NULL DEFAULT 1,
  doi_mat_khau INTEGER NOT NULL DEFAULT 1,
  so_lan_sai INTEGER NOT NULL DEFAULT 0,
  khoa_den TEXT, lan_dang_nhap_cuoi TEXT,
  nguoi_tao INTEGER,
  ngay_tao TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE nguoi_dung_khoa (
  id_nguoi_dung INTEGER NOT NULL REFERENCES nguoi_dung(id) ON DELETE CASCADE,
  id_khoa INTEGER NOT NULL REFERENCES khoa(id) ON DELETE CASCADE,
  PRIMARY KEY (id_nguoi_dung, id_khoa)
);

CREATE TABLE chi_tieu (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ma TEXT NOT NULL UNIQUE, ten TEXT NOT NULL,
  don_vi TEXT NOT NULL DEFAULT '',
  id_cha INTEGER, thu_tu INTEGER NOT NULL DEFAULT 0,
  loai_gia_tri TEXT NOT NULL DEFAULT 'DEM',
  nguon TEXT NOT NULL DEFAULT 'NHAP_TAY',
  huong TEXT NOT NULL DEFAULT 'CAO_TOT',
  phan_bo TEXT NOT NULL DEFAULT 'THEO_NGAY',
  phep_tinh TEXT, ct_tu TEXT, ct_mau TEXT,
  nhan_so_ngay INTEGER NOT NULL DEFAULT 0,
  la_chuan INTEGER NOT NULL DEFAULT 1,
  gop_vao TEXT,
  mo_ta TEXT,
  hoat_dong INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE chi_tieu_ap_dung (
  id_chi_tieu INTEGER NOT NULL REFERENCES chi_tieu(id) ON DELETE CASCADE,
  id_khoa INTEGER NOT NULL REFERENCES khoa(id) ON DELETE CASCADE,
  -- thu_tu: thứ tự RIÊNG của chỉ tiêu trong khoa này (0 = chưa đặt → lùi về
  -- thứ tự thư viện chi_tieu.thu_tu). Sắp xếp ở một khoa không ảnh hưởng khoa khác.
  thu_tu INTEGER NOT NULL DEFAULT 0,
  -- loai_gia_tri: loại giá trị RIÊNG của khoa này (NULL = theo loại chung
  -- chi_tieu.loai_gia_tri). Cho phép cùng 1 mã: khoa A là Ghi chú, khoa B Đếm.
  loai_gia_tri TEXT DEFAULT NULL,
  PRIMARY KEY (id_chi_tieu, id_khoa)
);

CREATE TABLE ke_hoach (
  nam INTEGER NOT NULL, id_khoa INTEGER NOT NULL, id_chi_tieu INTEGER NOT NULL,
  chi_tieu_giao REAL, chi_tieu_nang_luc REAL, th_nam_truoc REAL,
  PRIMARY KEY (nam, id_khoa, id_chi_tieu)
);

CREATE TABLE ke_hoach_thang (
  nam INTEGER NOT NULL, thang INTEGER NOT NULL,
  id_khoa INTEGER NOT NULL, id_chi_tieu INTEGER NOT NULL,
  chi_tieu REAL NOT NULL,
  PRIMARY KEY (nam, thang, id_khoa, id_chi_tieu)
);

CREATE TABLE ky (
  nam INTEGER NOT NULL, thang INTEGER NOT NULL, id_khoa INTEGER NOT NULL,
  trang_thai TEXT NOT NULL DEFAULT 'MO',
  nguoi_nop INTEGER, thoi_diem_nop TEXT,
  nguoi_duyet INTEGER, thoi_diem_duyet TEXT,
  ghi_chu TEXT,
  mo_den TEXT,
  PRIMARY KEY (nam, thang, id_khoa)
);

CREATE TABLE lich_ky (
  nam INTEGER NOT NULL, thang INTEGER NOT NULL,
  id_khoa INTEGER NOT NULL DEFAULT 0,
  mo_tu TEXT NOT NULL, dong_sau TEXT NOT NULL,
  ghi_chu TEXT, nguoi_dat INTEGER,
  thoi_diem TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (nam, thang, id_khoa)
);

CREATE TABLE quyen_nguoi_dung (
  id_nguoi_dung INTEGER NOT NULL REFERENCES nguoi_dung(id) ON DELETE CASCADE,
  quyen TEXT NOT NULL,
  nguoi_cap INTEGER,
  thoi_diem TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_nguoi_dung, quyen)
);

CREATE TABLE so_lieu (
  nam INTEGER NOT NULL, thang INTEGER NOT NULL,
  id_khoa INTEGER NOT NULL, id_chi_tieu INTEGER NOT NULL,
  gia_tri REAL,
  la_chien_dich INTEGER NOT NULL DEFAULT 0,
  ghi_chu TEXT, nguoi_nhap INTEGER,
  thoi_diem TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (nam, thang, id_khoa, id_chi_tieu)
);

CREATE TABLE dieu_chinh (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nam INTEGER NOT NULL, thang INTEGER NOT NULL,
  id_khoa INTEGER NOT NULL, id_chi_tieu INTEGER NOT NULL,
  gia_tri_cu REAL, gia_tri_moi REAL,
  ly_do TEXT NOT NULL,
  trang_thai TEXT NOT NULL DEFAULT 'CHO_DUYET',
  nguoi_de_xuat INTEGER, nguoi_duyet INTEGER,
  thoi_diem TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE nhat_ky (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_nguoi_dung INTEGER, ten_dang_nhap TEXT,
  hanh_dong TEXT NOT NULL, doi_tuong TEXT, chi_tiet TEXT,
  dia_chi_ip TEXT,
  thoi_diem TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO khoa (ma, ten, loai, giuong_benh, thu_tu) VALUES
  ('PK',    'Khoa Khám bệnh',                    'NGOAI_TRU',     0,  1),
  ('HSCC',  'Khoa Cấp cứu - HSTC & Chống độc',   'NOI_TRU',      25,  2),
  ('NOI',   'Khoa Nội',                          'NOI_TRU',      60,  3),
  ('NGOAI', 'Khoa Ngoại - PT - GMHS',            'NOI_TRU',      20,  4),
  ('NHI',   'Khoa Nhi',                          'NOI_TRU',      25,  5),
  ('SAN',   'Khoa CSSKSS và Phụ sản',            'NOI_TRU',      15,  6),
  ('TN',    'Khoa Truyền nhiễm',                 'NOI_TRU',      20,  7),
  ('YHCT',  'Khoa YHCT - Phục hồi chức năng',    'NOI_TRU',      35,  8),
  ('LCK',   'Khoa Liên chuyên khoa RHM-Mắt-TMH', 'NOI_TRU',      15,  9),
  ('XN',    'Khoa Xét nghiệm',                   'CAN_LAM_SANG',  0, 10),
  ('CDHA',  'Khoa Chẩn đoán hình ảnh',           'CAN_LAM_SANG',  0, 11);

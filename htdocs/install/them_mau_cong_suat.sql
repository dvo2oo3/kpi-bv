-- Them chi tieu "Mau cua cong suat" (= giuong x so ngay, mau so cua cong suat) de hien trong bao cao.
-- Chay tren host (phpMyAdmin) SAU khi da day code moi (app/chi_tieu.php, app/auth.php).
-- Dung INSERT IGNORE nen chay lai nhieu lan van an toan (khong bao loi Duplicate).

-- 1) Tao chi tieu (neu da co thi bo qua).
INSERT IGNORE INTO chi_tieu (ma, ten, don_vi, thu_tu, loai_gia_tri, nguon, huong, phan_bo)
  SELECT 'MAU_CSGB', 'Mẫu của công suất', 'giường-ngày', thu_tu + 5, 'DEM', 'CONG_THUC', 'CAO_TOT', 'KHONG_CHIA'
  FROM chi_tieu WHERE ma = 'CSGB';

-- 2) Gan vao dung cac khoa co Cong suat, dat ngay sau Cong suat (khoa nao da co thi bo qua).
INSERT IGNORE INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa, thu_tu)
  SELECT (SELECT id FROM chi_tieu WHERE ma = 'MAU_CSGB'), a.id_khoa, a.thu_tu + 5
  FROM chi_tieu_ap_dung a
  JOIN chi_tieu c ON c.id = a.id_chi_tieu
  WHERE c.ma = 'CSGB';

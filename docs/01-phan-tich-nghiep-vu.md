--# Phần mềm Quản lý Chỉ tiêu Kế hoạch Chuyên môn — Phân tích nghiệp vụ

**Đơn vị:** Trung tâm Y tế Nam Sách — Sở Y tế TP Hải Phòng
**Căn cứ:** QĐ 74/QĐ-SYT (Sở giao TTYT) → QĐ 33b/QĐ-TTYT ngày 13/01/2026 (TTYT giao các khoa)
**Nguồn khảo sát:** `THEO DOI THUC HIEN CHI TIEU KE HOACH KHOA 2026.xls` (29 sheet), `CHỈ TIÊU CÁC KHOA ĐẠT ĐƯỢC 6 THG ĐẦU NĂM 2026.xlsx` (12 sheet)

---

## 1. Mục tiêu

Thay thế toàn bộ bộ file Excel hiện tại bằng một hệ thống:

1. Khoa **nhập số liệu 1 lần/tháng** theo form có kiểm tra chéo.
2. Hệ thống **tự tính** lũy kế quý / 6 tháng / 9 tháng / năm và **% đạt kế hoạch (KPI)**.
3. **Chốt sổ theo tháng**: hết kỳ, khoa nộp → phòng KHTH duyệt → khóa bất biến.
4. **Tự tổng hợp toàn viện** từ số liệu khoa (không nhập tay).
5. Xuất báo cáo đúng mẫu quyết định đang dùng để ký và gửi Sở.

## 2. Quyết định thiết kế đã chốt

| # | Vấn đề | Quyết định |
|---|---|---|
| 1 | 2 file có 2 mức chỉ tiêu khác nhau | **Lưu cả hai**, hiển thị song song: `chi_tieu_giao` (QĐ 33b) và `chi_tieu_nang_luc` (tính từ giường bệnh). Báo cáo hiện 2 cột %. |
| 2 | Đếm trùng XN/XQ/SA giữa khoa lâm sàng và khoa CLS | Mỗi bản ghi CLS mang **2 khoa**: `khoa_chi_dinh` + `khoa_thuc_hien`. Báo cáo chọn chiều; toàn viện đếm 1 lần. |
| 3 | Nguồn số liệu | **Nhập tay theo form** (giai đoạn 1). Tích hợp HIS để sau. |
| 4 | Chốt kỳ | **Khoa nộp → KHTH duyệt → khóa cứng**. Sửa sau khóa = bút toán điều chỉnh có lý do + người duyệt. |

---

## 3. Mô hình dữ liệu

### 3.1 Danh mục Khoa (`khoa`)

11 khoa/phòng theo file giao chỉ tiêu:

| Mã | Tên | Loại |
|---|---|---|
| PK | Khoa Khám bệnh | Ngoại trú |
| NOI | Khoa Nội | Lâm sàng nội trú |
| NGOAI | Khoa Ngoại - PT - GMHS | Lâm sàng nội trú |
| NHI | Khoa Nhi | Lâm sàng nội trú |
| SAN | Khoa CSSKSS và Phụ sản | Lâm sàng nội trú |
| HSCC | Khoa Cấp cứu - HSTC & CĐ | Lâm sàng nội trú |
| TN | Khoa Truyền nhiễm | Lâm sàng nội trú |
| YHCT | Khoa YHCT - PHCN | Lâm sàng nội trú |
| LCK | Khoa Liên chuyên khoa RHM-Mắt-TMH | Lâm sàng nội trú |
| XN | Khoa Xét nghiệm | Cận lâm sàng |
| CDHA | Khoa Chẩn đoán hình ảnh | Cận lâm sàng |

Tổng giường bệnh kế hoạch toàn viện: **215**.

### 3.2 Danh mục Chỉ tiêu (`chi_tieu`) — cấu trúc CÂY

```
id, ma, ten, don_vi, id_cha, thu_tu,
loai_gia_tri  : DEM | TY_LE | TRUNG_BINH | HANG_SO
nguon         : NHAP_TAY | TONG_CON | CONG_THUC
cong_thuc     : biểu thức (khi nguon = CONG_THUC)
huong         : CAO_TOT | THAP_TOT | DICH_CO_DINH
phan_bo_thang : THEO_NGAY | DEU | THEO_HE_SO | KHONG_CHIA
```

**Ví dụ cây chỉ tiêu (rút từ file):**

```
1. Giường bệnh nội trú kế hoạch          [Giường] HANG_SO, KHONG_CHIA
2. Tổng số lượt khám bệnh                [Lượt]   DEM, CAO_TOT
   ├ BHYT
   ├ Người dân (viện phí)
   └ Khám sức khỏe
3. Tổng số BN điều trị nội trú           [Lượt]   DEM, CAO_TOT
   ├ BHYT
   └ Người dân
4. Tổng số ngày điều trị nội trú         [Ngày]   DEM, CAO_TOT
   ├ BHYT
   └ Người dân
5. Bệnh nhân < 6 tuổi / > 60 tuổi        [Lượt]   DEM  (mỗi loại tách BH/ND)
6. Tổng số thủ thuật                     [Ca]     DEM, CAO_TOT
   ├ Loại I / Loại II / Loại III
7. Tổng số phẫu thuật                    [Ca]     DEM, CAO_TOT
   ├ Đặc biệt / Loại I / Loại II / Loại III
8. Tổng số các xét nghiệm                [Lần]    DEM, CAO_TOT
   ├ Huyết học / Hóa sinh / Vi sinh vật / Nước tiểu
9. Chụp X-quang / CT-Scanner / MRI       [Lần]    DEM
10. Siêu âm chẩn đoán và điều trị        [Lần]    DEM
11. Điện tim                             [Lần]    DEM
12. Nội soi chẩn đoán và can thiệp       [Lần]    DEM
13. Đo mật độ xương (DEXA)               [Lần]    DEM
14. Ngày điều trị nội trú trung bình     [Ngày]   TRUNG_BINH, THAP_TOT, KHONG_CHIA
15. Công suất sử dụng giường bệnh        [%]      TY_LE, DICH_CO_DINH(100), KHONG_CHIA
16. Kết quả điều trị nội trú             [Lượt]   DEM
    ├ Khỏi / Đỡ / Không thay đổi / Nặng hơn
17. Bệnh nhân tử vong                    [BN]     DEM, THAP_TOT
18. Chuyển viện (nội trú / ngoại trú)    [Lượt]   DEM, THAP_TOT
19. Đề tài nghiên cứu khoa học           [Đề tài] DEM, CAO_TOT
```

**Chỉ tiêu riêng theo khoa** (không phải khoa nào cũng có — cần bảng `chi_tieu_ap_dung(khoa, chi_tieu)`):

- PK: BN quản lý ngoại trú THA, BN quản lý ngoại trú ĐTĐ, tư vấn/lấy mẫu XN HIV
- NOI: BN điều trị ngoại trú COPD-HPQ
- SAN: khám phụ khoa, khám thai, sản phụ đẻ (đẻ thường / mổ lấy thai), PNMT tư vấn XN HIV
- LCK: nội soi TMH, nội soi dạ dày
- XN: XN HIV, XN ma túy, tách vi sinh + miễn dịch
- CDHA: đo DEXA, nội soi

### 3.3 Công thức chỉ tiêu dẫn xuất

```
ngay_dt_trung_binh = tong_ngay_dieu_tri / tong_bn_noi_tru
cong_suat_gb (%)   = tong_ngay_dieu_tri / (giuong_benh_kh * so_ngay_trong_ky) * 100
```

`so_ngay_trong_ky`: 31/28/31/30/... theo tháng; lũy kế = tổng số ngày các tháng **đã chốt** (không phải các tháng đã trôi qua).

> **Quy tắc vàng:** chỉ tiêu `TY_LE` và `TRUNG_BINH` **không bao giờ cộng dồn** — luôn tính lại từ tử số và mẫu số lũy kế.

### 3.4 Bảng số liệu (`so_lieu`)

```
nam, thang, id_khoa, id_chi_tieu, gia_tri (nullable), ghi_chu,
la_chien_dich (bool), nguoi_nhap, thoi_diem_nhap
```

- `gia_tri = NULL` nghĩa là **chưa nhập**, khác hoàn toàn `0`.
- Chỉ tiêu `nguon = TONG_CON` hoặc `CONG_THUC` không lưu ở đây — tính khi đọc.

### 3.5 Bảng chỉ tiêu giao (`ke_hoach`)

```
nam, id_khoa, id_chi_tieu,
chi_tieu_giao,        -- theo QĐ 33b, số tròn
chi_tieu_nang_luc,    -- tính từ giường bệnh, số thập phân
th_nam_truoc,         -- để so sánh cùng kỳ
so_quyet_dinh, ngay_quyet_dinh
```

### 3.6 Kỳ nhập liệu (`ky`)

```
nam, thang, id_khoa,
trang_thai: MO | DA_NOP | DA_DUYET | DA_KHOA,
nguoi_nop, thoi_diem_nop, nguoi_duyet, thoi_diem_duyet
```

### 3.7 Bút toán điều chỉnh (`dieu_chinh`)

```
id_so_lieu, gia_tri_cu, gia_tri_moi, ly_do,
nguoi_de_xuat, nguoi_duyet, thoi_diem
```

Bắt buộc, vì file hiện tại đầy các sửa tay không truy vết được:
- *"Phẫu thuật 6 tháng đầu năm tăng lên 5 ca → 266 + 5 = 271"*
- *"XQ 11540 cộng thêm 72 CT và XQ thêm 1420"*
- *"Cuối năm + 224 lượt khám nhân viên"*

---

## 4. Quy trình nghiệp vụ

### 4.1 Đầu năm — Giao chỉ tiêu

1. Phòng KHTH nhập chỉ tiêu toàn viện theo QĐ của Sở.
2. Phân bổ xuống khoa → sinh bản giao chỉ tiêu theo mẫu QĐ 33b, in ký.
3. Hệ thống **phân bổ chỉ tiêu năm ra 12 tháng**:
   - `DEM` → theo số ngày trong tháng (mặc định), có thể ghi đè hệ số mùa vụ.
   - `TY_LE`, `TRUNG_BINH` → giữ nguyên mọi tháng.
   - `HANG_SO` (giường bệnh) → lặp lại.
4. Kiểm tra: `Σ chỉ tiêu các khoa` vs `chỉ tiêu toàn viện` — cảnh báo nếu lệch.

### 4.2 Hằng tháng — Nhập liệu

- Ngày 1–5 tháng sau: kỳ ở trạng thái `MO`.
- Khoa nhập, hệ thống **kiểm tra ngay khi nhập**:
  - `cha = Σ con` (Tổng XN = HH + HS + VS + NT; Tổng PT = ĐB + L1 + L2 + L3)
  - `BHYT + Người dân = Tổng`
  - `Khỏi + Đỡ + Không đổi + Nặng hơn = Tổng BN ra viện`
  - Công suất GB > 120% hoặc < 30% → cảnh báo xác nhận
  - Chênh > 50% so với trung bình 3 tháng gần nhất → cảnh báo
- Ô dẫn xuất (ngày ĐT TB, công suất) **hiển thị read-only**, tính realtime.
- Nhập xong bấm **Nộp** → `DA_NOP`.

### 4.3 Duyệt và chốt

- KHTH xem bảng đối chiếu, đối chiếu chéo khoa lâm sàng ↔ khoa CLS.
- Duyệt → `DA_DUYET` → hết ngày 5 tự chuyển `DA_KHOA`.
- Sau khóa: chỉ sửa qua bút toán điều chỉnh, báo cáo cũ vẫn tái lập được nguyên trạng.

### 4.4 Báo cáo

| Báo cáo | Kỳ | Mẫu |
|---|---|---|
| Theo dõi thực hiện chỉ tiêu từng khoa | Tháng / Quý / 6T / 9T / Năm | Mẫu file `.xls` (12 cột tháng + cột lũy kế) |
| Thực hiện chỉ tiêu toàn viện | như trên | Sheet `Toan vien` |
| Chỉ tiêu các khoa đạt được | 6T / Năm | Mẫu file `.xlsx` theo QĐ 33b |
| Báo cáo gửi Sở Y tế | 6T / Năm | Mẫu QĐ 74/QĐ-SYT |
| Dashboard cảnh báo tiến độ | realtime | Mới |

**Cảnh báo tiến độ:** tại tháng N, kỳ vọng `% đạt ≥ N/12`. Ví dụ hết 6 tháng 2026:

- Cờ đỏ: YHCT ngày ĐT nội trú 20,2% · Sản BN nội trú 13,7% · LCK BN nội trú 16,9% · PK tư vấn XN HIV 0% · TN đo DEXA 10%
- Vượt kế hoạch: CĐHA điện tim 105,5% · PK quản lý THA 115,7% · TN thủ thuật 122,1% · Nội COPD-HPQ 106,9% · Nhi nước tiểu 83,5%

---

## 5. Phân quyền

| Vai trò | Quyền |
|---|---|
| Nhân viên khoa | Nhập, sửa, nộp số liệu khoa mình khi kỳ `MO` |
| Trưởng khoa | Như trên + duyệt nội bộ + xem báo cáo khoa mình |
| Phòng KHTH | Giao chỉ tiêu, duyệt kỳ, mở lại kỳ có lý do, xem/xuất mọi báo cáo |
| Ban Giám đốc | Xem toàn bộ, dashboard, không sửa |
| Quản trị | Danh mục, người dùng, nhật ký hệ thống |

---

## 6. Sai sót của bảng tính hiện tại mà phần mềm phải xử lý

| # | Sai sót | Bằng chứng | Cách xử lý |
|---|---|---|---|
| 1 | Tháng chưa tới điền `0` làm sai lũy kế | `k.KNHI` r27: công suất 9 tháng = 39,5% (tử số 6 tháng ÷ mẫu 9 tháng) | Phân biệt `NULL` vs `0`; mẫu số chỉ tính tháng đã chốt |
| 2 | Toàn viện ≠ tổng các khoa | XQ: `Toan vien` 11.540 vs CĐHA 13.029; thủ thuật 37.918 vs 37.645 | Toàn viện do máy cộng |
| 3 | Đếm trùng CLS | Σ XN các khoa vs khoa XN 122.145 | 2 chiều khoa chỉ định / thực hiện |
| 4 | Chiến dịch làm méo xu hướng | Đợt khám người cao tuổi T6: 5.972 lượt cộng thẳng vào SA (7.693 vs ~1.200 các tháng) và ĐT (6.673) | Cờ `la_chien_dich`, tách khỏi thường quy |
| 5 | `#DIV/0!` khi chỉ tiêu = 0 | Đề tài NCKH, CT-Scanner, MRI | Hiển thị `N/A` |
| 6 | Kiểu dữ liệu hỏng | Ngày ĐT TB lưu text `"5,0"`, `"6,4"`; tháng chưa có điền mặc định `7` | Kiểu số chặt, không giá trị mặc định rác |
| 7 | Chỉ tiêu 2 file lệch nhau | Nội 2.300 vs 3.590,16; Ngoại 800 vs 1.489,80; Sản 500 vs 977,68; Nhi 1.200 vs 1.825 | Lưu 2 trường riêng, báo cáo 2 cột |
| 8 | Chấm % cho chỉ tiêu "càng thấp càng tốt" | Ngày ĐT TB hiện bỏ trống ô % ở mọi sheet | Thuộc tính `huong` trên chỉ tiêu |
| 9 | Dòng phụ trợ lẫn trong bảng | `k.KNHI` r33–r34: "Tổng số ngày", "Mẫu của công suất" | Sinh tự động, ẩn khỏi bảng nghiệp vụ |

---

## 7. Phạm vi giai đoạn 1

**Có:** danh mục khoa/chỉ tiêu, giao chỉ tiêu + phân bổ 12 tháng, form nhập tháng có kiểm tra chéo, quy trình nộp–duyệt–khóa, bút toán điều chỉnh, tính lũy kế + KPI, dashboard cảnh báo, xuất Excel 4 mẫu báo cáo, phân quyền.

**Chưa:** tích hợp HIS, so sánh nhiều năm (chỉ lưu `th_nam_truoc`), KPI cá nhân/nhân viên, ký số.

---

## 8. Việc cần làm tiếp

- [ ] Rà soát và chốt danh mục chỉ tiêu chuẩn (bản trên là rút từ file, cần khoa xác nhận)
- [ ] Chốt chỉ tiêu riêng của từng khoa (bảng `chi_tieu_ap_dung`)
- [ ] Chốt hệ số mùa vụ nếu có, hoặc dùng phân bổ theo ngày
- [ ] Chọn nền tảng kỹ thuật (web nội bộ / desktop)

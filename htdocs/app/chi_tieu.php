<?php
/**
 * Bộ máy tính chỉ tiêu, lũy kế và KPI.
 *
 * BA QUY TẮC XƯƠNG SỐNG — mọi lỗi của bảng Excel hiện tại đều xuất phát từ
 * việc vi phạm một trong ba quy tắc này:
 *
 *   1. gia_tri = NULL nghĩa là CHƯA NHẬP, khác hẳn giá trị 0.
 *      Tháng chưa nhập không được tính vào lũy kế, kể cả mẫu số.
 *
 *   2. Chỉ tiêu TỶ LỆ và TRUNG BÌNH KHÔNG BAO GIỜ cộng dồn.
 *      Lũy kế phải tính lại từ tử số và mẫu số của cả kỳ.
 *
 *   3. Chỉ tiêu cha bằng tổng các con, không nhập tay.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/* ============================================================
 * Danh mục
 * ========================================================== */

/** Toàn bộ danh mục chỉ tiêu, đánh chỉ mục theo id. */
function tat_ca_chi_tieu(): array
{
    static $ds = null;
    if ($ds === null) {
        $ds = [];
        foreach (qAll('SELECT * FROM chi_tieu WHERE hoat_dong = 1 ORDER BY thu_tu, id') as $r) {
            $r['id']     = (int)$r['id'];
            $r['id_cha'] = $r['id_cha'] !== null ? (int)$r['id_cha'] : null;
            $ds[$r['id']] = $r;
        }
    }
    return $ds;
}

function chi_tieu_theo_ma(string $ma): ?array
{
    foreach (tat_ca_chi_tieu() as $ct) {
        if ($ct['ma'] === $ma) {
            return $ct;
        }
    }
    return null;
}

/** Các chỉ tiêu áp dụng cho một khoa, đã sắp theo cây (cha rồi tới con). */
function chi_tieu_cua_khoa(int $idKhoa): array
{
    static $bo_nho = [];
    if (isset($bo_nho[$idKhoa])) {
        return $bo_nho[$idKhoa];
    }

    $ap = [];
    foreach (qAll('SELECT id_chi_tieu FROM chi_tieu_ap_dung WHERE id_khoa = ?', [$idKhoa]) as $r) {
        $ap[(int)$r['id_chi_tieu']] = true;
    }

    $tatCa = tat_ca_chi_tieu();
    $ketQua = [];
    foreach ($tatCa as $ct) {
        if ($ct['id_cha'] !== null || !isset($ap[$ct['id']])) {
            continue;   // vòng ngoài chỉ duyệt chỉ tiêu gốc
        }
        $ct['cap'] = 0;
        $ketQua[] = $ct;
        foreach ($tatCa as $con) {
            if ($con['id_cha'] === $ct['id'] && isset($ap[$con['id']])) {
                $con['cap'] = 1;
                $ketQua[] = $con;
            }
        }
    }
    return $bo_nho[$idKhoa] = $ketQua;
}

/** Các chỉ tiêu con trực tiếp. */
function con_cua(int $idChiTieu, int $idKhoa): array
{
    $con = [];
    foreach (chi_tieu_cua_khoa($idKhoa) as $ct) {
        if ($ct['id_cha'] === $idChiTieu) {
            $con[] = $ct;
        }
    }
    return $con;
}

/* ============================================================
 * Lịch
 * ========================================================== */

function so_ngay_thang(int $nam, int $thang): int
{
    return (int)date('t', mktime(0, 0, 0, $thang, 1, $nam));
}

function so_ngay_trong_nam(int $nam): int
{
    return (int)date('L', mktime(0, 0, 0, 1, 1, $nam)) === 1 ? 366 : 365;
}

/** Danh sách tháng của một kỳ. Ví dụ: 'Q1' => [1,2,3], '6T' => [1..6] */
function cac_thang_cua_ky(string $ky): array
{
    return match ($ky) {
        'T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'
                => [(int)substr($ky, 1)],
        'Q1'    => [1, 2, 3],
        'Q2'    => [4, 5, 6],
        'Q3'    => [7, 8, 9],
        'Q4'    => [10, 11, 12],
        '3T'    => [1, 2, 3],
        '6T'    => [1, 2, 3, 4, 5, 6],
        '9T'    => [1, 2, 3, 4, 5, 6, 7, 8, 9],
        'NAM'   => range(1, 12),
        default => range(1, 12),
    };
}

function ten_ky(string $ky): string
{
    if (preg_match('/^T(\d+)$/', $ky, $m)) {
        return 'Tháng ' . $m[1];
    }
    return match ($ky) {
        'Q1' => 'Quý I', 'Q2' => 'Quý II', 'Q3' => 'Quý III', 'Q4' => 'Quý IV',
        '3T' => '3 tháng', '6T' => '6 tháng', '9T' => '9 tháng', 'NAM' => 'Cả năm',
        default => $ky,
    };
}

const CAC_KY = ['T1','T2','T3','Q1','T4','T5','T6','Q2','6T',
                'T7','T8','T9','Q3','9T','T10','T11','T12','Q4','NAM'];

/* ============================================================
 * Đọc số liệu
 * ========================================================== */

/**
 * Số liệu thô của một khoa trong một năm.
 * Trả về [id_chi_tieu][thang] => giá trị (float) hoặc null nếu chưa nhập.
 */
function so_lieu_tho(int $nam, int $idKhoa): array
{
    static $bo_nho = [];
    $khoa_bo_nho = "$nam:$idKhoa";
    if (isset($bo_nho[$khoa_bo_nho])) {
        return $bo_nho[$khoa_bo_nho];
    }

    $bang = [];
    foreach (qAll(
        'SELECT thang, id_chi_tieu, gia_tri FROM so_lieu WHERE nam = ? AND id_khoa = ?',
        [$nam, $idKhoa]) as $r) {
        $bang[(int)$r['id_chi_tieu']][(int)$r['thang']] =
            $r['gia_tri'] === null ? null : (float)$r['gia_tri'];
    }
    return $bo_nho[$khoa_bo_nho] = $bang;
}

function xoa_bo_nho_so_lieu(): void
{
    // Gọi sau khi ghi số liệu để lần đọc kế tiếp lấy dữ liệu mới.
    // (Bộ nhớ tạm chỉ sống trong một lượt truy cập nên chỉ cần trong trang nhập liệu.)
}

/**
 * Giá trị một chỉ tiêu của một khoa trong một tháng.
 * Trả về null nếu chưa có dữ liệu.
 */
function gia_tri_thang(int $nam, int $thang, int $idKhoa, int $idChiTieu): ?float
{
    $tatCa = tat_ca_chi_tieu();
    $ct = $tatCa[$idChiTieu] ?? null;
    if (!$ct) {
        return null;
    }

    if ($ct['nguon'] === 'TONG_CON') {
        $tong = null;
        foreach (con_cua($idChiTieu, $idKhoa) as $con) {
            $v = gia_tri_thang($nam, $thang, $idKhoa, $con['id']);
            if ($v !== null) {
                $tong = ($tong ?? 0) + $v;
            }
        }
        return $tong;
    }

    if ($ct['nguon'] === 'CONG_THUC') {
        return tinh_cong_thuc($ct['ma'], $nam, [$thang], $idKhoa);
    }

    $tho = so_lieu_tho($nam, $idKhoa);
    return $tho[$idChiTieu][$thang] ?? null;
}

/**
 * Giá trị lũy kế của một chỉ tiêu qua nhiều tháng.
 *
 * DEM       -> cộng các tháng ĐÃ CÓ số liệu
 * HANG_SO   -> lấy giá trị tháng gần nhất có số liệu (giường bệnh không cộng dồn)
 * TY_LE / TRUNG_BINH -> tính lại từ công thức trên toàn kỳ
 */
function gia_tri_luy_ke(int $nam, array $cacThang, int $idKhoa, int $idChiTieu): ?float
{
    $ct = tat_ca_chi_tieu()[$idChiTieu] ?? null;
    if (!$ct) {
        return null;
    }

    if ($ct['nguon'] === 'CONG_THUC') {
        return tinh_cong_thuc($ct['ma'], $nam, $cacThang, $idKhoa);
    }

    if ($ct['loai_gia_tri'] === 'HANG_SO') {
        $cuoi = null;
        foreach ($cacThang as $t) {
            $v = gia_tri_thang($nam, $t, $idKhoa, $idChiTieu);
            if ($v !== null) {
                $cuoi = $v;
            }
        }
        return $cuoi;
    }

    $tong = null;
    foreach ($cacThang as $t) {
        $v = gia_tri_thang($nam, $t, $idKhoa, $idChiTieu);
        if ($v !== null) {
            $tong = ($tong ?? 0) + $v;
        }
    }
    return $tong;
}

/* ============================================================
 * Công thức
 * ========================================================== */

/**
 * Các chỉ tiêu dẫn xuất. Không dùng eval — chỉ nhận diện theo mã,
 * để không ai chèn được biểu thức lạ vào cơ sở dữ liệu.
 */
function tinh_cong_thuc(string $ma, int $nam, array $cacThang, int $idKhoa): ?float
{
    $ctNDT = chi_tieu_theo_ma('NDT');
    $ctNT  = chi_tieu_theo_ma('NT');
    $ctGB  = chi_tieu_theo_ma('GB');

    switch ($ma) {
        // Ngày điều trị trung bình = tổng ngày điều trị / tổng bệnh nhân nội trú
        case 'NDT_TB':
            if (!$ctNDT || !$ctNT) {
                return null;
            }
            $ngay = gia_tri_luy_ke($nam, $cacThang, $idKhoa, $ctNDT['id']);
            $bn   = gia_tri_luy_ke($nam, $cacThang, $idKhoa, $ctNT['id']);
            if ($ngay === null || $bn === null || $bn <= 0) {
                return null;
            }
            return $ngay / $bn;

        // Công suất giường bệnh = tổng ngày điều trị / (giường × số ngày) × 100
        // Mẫu số CHỈ tính những tháng đã có số liệu — đây là chỗ bảng Excel sai.
        case 'CSGB':
            if (!$ctNDT || !$ctGB) {
                return null;
            }
            $ngay = gia_tri_luy_ke($nam, $cacThang, $idKhoa, $ctNDT['id']);
            if ($ngay === null) {
                return null;
            }
            $mau = 0.0;
            foreach ($cacThang as $t) {
                $ndtThang = gia_tri_thang($nam, $t, $idKhoa, $ctNDT['id']);
                if ($ndtThang === null) {
                    continue;   // tháng chưa nhập thì không đưa vào mẫu số
                }
                $gb = gia_tri_thang($nam, $t, $idKhoa, $ctGB['id']);
                if ($gb === null) {
                    $gb = (float)qVal('SELECT giuong_benh FROM khoa WHERE id = ?', [$idKhoa]);
                }
                $mau += $gb * so_ngay_thang($nam, $t);
            }
            return $mau > 0 ? $ngay / $mau * 100 : null;
    }
    return null;
}

/* ============================================================
 * Chỉ tiêu kế hoạch
 * ========================================================== */

/** Kế hoạch năm của một khoa: [id_chi_tieu] => bản ghi ke_hoach */
function ke_hoach_nam(int $nam, int $idKhoa): array
{
    static $bo_nho = [];
    $k = "$nam:$idKhoa";
    if (isset($bo_nho[$k])) {
        return $bo_nho[$k];
    }
    $kq = [];
    foreach (qAll('SELECT * FROM ke_hoach WHERE nam = ? AND id_khoa = ?', [$nam, $idKhoa]) as $r) {
        $kq[(int)$r['id_chi_tieu']] = $r;
    }
    return $bo_nho[$k] = $kq;
}

/**
 * Chỉ tiêu của một kỳ, phân bổ từ chỉ tiêu năm.
 *
 *   THEO_NGAY  : chia theo số ngày của các tháng trong kỳ
 *                (tháng 2 có 28 ngày không thể cùng mức tháng 1)
 *   KHONG_CHIA : giữ nguyên mức năm (tỷ lệ, trung bình, hằng số)
 *
 * $goc: 'giao' dùng chỉ tiêu theo quyết định, 'nang_luc' dùng chỉ tiêu tính toán.
 */
function chi_tieu_cua_ky(int $nam, array $cacThang, int $idKhoa, int $idChiTieu,
                         string $goc = 'giao'): ?float
{
    $ct = tat_ca_chi_tieu()[$idChiTieu] ?? null;
    if (!$ct) {
        return null;
    }

    // Hệ số mùa vụ ghi đè, nếu có
    $ghiDe = qAll(
        'SELECT thang, chi_tieu FROM ke_hoach_thang
          WHERE nam = ? AND id_khoa = ? AND id_chi_tieu = ?',
        [$nam, $idKhoa, $idChiTieu]);
    if ($ghiDe) {
        $map = [];
        foreach ($ghiDe as $r) {
            $map[(int)$r['thang']] = (float)$r['chi_tieu'];
        }
        if (count($map) === 12) {
            if ($ct['phan_bo'] === 'KHONG_CHIA') {
                return $map[$cacThang[0]] ?? null;
            }
            $tong = 0.0;
            foreach ($cacThang as $t) {
                $tong += $map[$t] ?? 0;
            }
            return $tong;
        }
    }

    $kh = ke_hoach_nam($nam, $idKhoa)[$idChiTieu] ?? null;
    if (!$kh) {
        return null;
    }
    $cot = $goc === 'nang_luc' ? 'chi_tieu_nang_luc' : 'chi_tieu_giao';
    $namVal = $kh[$cot];
    if ($namVal === null || $namVal === '') {
        return null;
    }
    $namVal = (float)$namVal;

    if ($ct['phan_bo'] === 'KHONG_CHIA') {
        return $namVal;
    }

    $ngayKy  = 0;
    foreach ($cacThang as $t) {
        $ngayKy += so_ngay_thang($nam, $t);
    }
    return $namVal * $ngayKy / so_ngay_trong_nam($nam);
}

/* ============================================================
 * Chỉ tiêu theo năng lực giường bệnh
 *
 * Trần lý thuyết: giường bệnh chạy hết công suất 100% suốt cả năm.
 * Đây chính là cách Sở tính chỉ tiêu trong QĐ 74/QĐ-SYT — toàn viện
 * 215 giường × 365 ngày ÷ 6 ngày điều trị TB = 13.079 bệnh nhân,
 * đúng bằng con số ghi trong quyết định.
 *
 *   Tổng ngày điều trị = Giường bệnh × số ngày trong năm
 *   Tổng BN nội trú    = Giường bệnh × số ngày ÷ Ngày điều trị TB
 *   Công suất giường   = 100%
 *
 * Các chỉ tiêu khác (khám bệnh, thủ thuật, xét nghiệm…) không suy ra
 * được từ giường bệnh nên trả về null.
 * ========================================================== */

const MA_SUY_TU_GIUONG_BENH = ['GB', 'NDT', 'NT', 'CSGB'];

function nang_luc_theo_giuong_benh(int $nam, int $idKhoa, string $maChiTieu): ?float
{
    if (!in_array($maChiTieu, MA_SUY_TU_GIUONG_BENH, true)) {
        return null;
    }

    $kh   = ke_hoach_nam($nam, $idKhoa);
    $ctGB = chi_tieu_theo_ma('GB');
    $ctTB = chi_tieu_theo_ma('NDT_TB');

    // Số giường: ưu tiên chỉ tiêu giao của năm, không có thì lấy ở danh mục khoa
    $gb = null;
    if ($ctGB && isset($kh[$ctGB['id']]) && $kh[$ctGB['id']]['chi_tieu_giao'] !== null) {
        $gb = (float)$kh[$ctGB['id']]['chi_tieu_giao'];
    }
    if ($gb === null) {
        $gb = (float)qVal('SELECT giuong_benh FROM khoa WHERE id = ?', [$idKhoa]);
    }
    if ($gb <= 0) {
        return null;
    }

    $soNgay = so_ngay_trong_nam($nam);

    switch ($maChiTieu) {
        case 'GB':   return $gb;
        case 'NDT':  return $gb * $soNgay;
        case 'CSGB': return 100.0;
        case 'NT':
            $tb = ($ctTB && isset($kh[$ctTB['id']])) ? $kh[$ctTB['id']]['chi_tieu_giao'] : null;
            if ($tb === null || (float)$tb <= 0) {
                return null;   // chưa giao ngày điều trị trung bình thì chưa tính được
            }
            return $gb * $soNgay / (float)$tb;
    }
    return null;
}

/* ============================================================
 * Đánh giá KPI
 * ========================================================== */

/**
 * @return array{phan_tram: ?float, danh_gia: string, mo_ta: string}
 *   danh_gia: dat | canh_bao | khong_dat | vuot | na
 */
function danh_gia_kpi(array $ct, ?float $thucHien, ?float $chiTieu): array
{
    if ($thucHien === null || $chiTieu === null || abs($chiTieu) < 1e-9) {
        return ['phan_tram' => null, 'danh_gia' => 'na', 'mo_ta' => 'Chưa đủ dữ liệu'];
    }

    $pt = $thucHien / $chiTieu * 100;

    switch ($ct['huong']) {
        case 'THAP_TOT':
            // Thấp hơn kế hoạch là tốt (ngày điều trị TB, tử vong, chuyển viện)
            if ($pt <= 100) {
                return ['phan_tram' => $pt, 'danh_gia' => 'dat',
                        'mo_ta' => 'Đạt (thấp hơn kế hoạch là tốt)'];
            }
            if ($pt <= 110) {
                return ['phan_tram' => $pt, 'danh_gia' => 'canh_bao',
                        'mo_ta' => 'Cao hơn kế hoạch'];
            }
            return ['phan_tram' => $pt, 'danh_gia' => 'khong_dat',
                    'mo_ta' => 'Vượt kế hoạch quá nhiều'];

        case 'DICH_CO_DINH':
            // Đích là đúng 100% (công suất giường bệnh)
            if ($pt >= 85 && $pt <= 110) {
                return ['phan_tram' => $pt, 'danh_gia' => 'dat', 'mo_ta' => 'Đạt mức mong muốn'];
            }
            if ($pt > 110) {
                return ['phan_tram' => $pt, 'danh_gia' => 'vuot',
                        'mo_ta' => 'Vượt công suất, có nguy cơ quá tải'];
            }
            if ($pt >= 70) {
                return ['phan_tram' => $pt, 'danh_gia' => 'canh_bao', 'mo_ta' => 'Chưa đạt'];
            }
            return ['phan_tram' => $pt, 'danh_gia' => 'khong_dat', 'mo_ta' => 'Thấp hơn nhiều'];

        default: // CAO_TOT
            if ($pt >= 100) {
                return ['phan_tram' => $pt, 'danh_gia' => 'dat', 'mo_ta' => 'Đạt kế hoạch'];
            }
            if ($pt >= 85) {
                return ['phan_tram' => $pt, 'danh_gia' => 'canh_bao', 'mo_ta' => 'Gần đạt'];
            }
            return ['phan_tram' => $pt, 'danh_gia' => 'khong_dat', 'mo_ta' => 'Chưa đạt'];
    }
}

/* ============================================================
 * Kỳ nhập liệu
 * ========================================================== */

/**
 * Cửa sổ mở kỳ nhập liệu.
 *
 * Thứ tự ưu tiên:
 *   1. Lịch riêng Phòng KHTH đặt cho chính khoa này
 *   2. Lịch chung đặt cho mọi khoa
 *   3. Quy tắc mặc định khi chưa đặt lịch nào:
 *      mở từ ngày 1 của tháng tới hết ngày 5 tháng sau
 *
 * @return array{mo_tu:int, dong_sau:int, nguon:string}
 */
function cua_so_ky(int $nam, int $thang, int $idKhoa): array
{
    static $bo_nho = [];
    $k = "$nam:$thang:$idKhoa";
    if (isset($bo_nho[$k])) {
        return $bo_nho[$k];
    }

    // Lịch riêng của khoa trước, sau đó tới lịch chung (id_khoa = 0)
    $lich = q1('SELECT * FROM lich_ky WHERE nam = ? AND thang = ? AND id_khoa IN (?, 0)
                 ORDER BY id_khoa DESC LIMIT 1', [$nam, $thang, $idKhoa]);
    if ($lich) {
        return $bo_nho[$k] = [
            'mo_tu'    => strtotime($lich['mo_tu'] . ' 00:00:00'),
            'dong_sau' => strtotime($lich['dong_sau'] . ' 23:59:59'),
            'nguon'    => (int)$lich['id_khoa'] === 0 ? 'lich_chung' : 'lich_khoa',
        ];
    }

    $namSau   = $thang === 12 ? $nam + 1 : $nam;
    $thangSau = $thang === 12 ? 1 : $thang + 1;
    return $bo_nho[$k] = [
        'mo_tu'    => mktime(0, 0, 0, $thang, 1, $nam),
        'dong_sau' => mktime(23, 59, 59, $thangSau, 5, $namSau),
        'nguon'    => 'mac_dinh',
    ];
}

/** Hạn nộp của một kỳ, theo lịch đang áp dụng. */
function han_nop(int $nam, int $thang, int $idKhoa = 0): int
{
    return cua_so_ky($nam, $thang, $idKhoa)['dong_sau'];
}

/** Bản ghi kỳ, hoặc null. */
function ban_ghi_ky(int $nam, int $thang, int $idKhoa): ?array
{
    return q1('SELECT * FROM ky WHERE nam = ? AND thang = ? AND id_khoa = ?',
        [$nam, $thang, $idKhoa]);
}

/**
 * Kỳ có đang trong thời gian cho nhập không.
 * Gia hạn riêng (ky.mo_den) đè lên lịch — dùng khi Phòng KHTH trả lại
 * cho khoa nhập bổ sung sau khi đã quá hạn chung.
 */
function trong_cua_so(int $nam, int $thang, int $idKhoa): bool
{
    $ky = ban_ghi_ky($nam, $thang, $idKhoa);
    if ($ky && !empty($ky['mo_den'])) {
        $den = strtotime($ky['mo_den'] . ' 23:59:59');
        if (time() <= $den) {
            return true;
        }
    }
    $cs = cua_so_ky($nam, $thang, $idKhoa);
    return time() >= $cs['mo_tu'] && time() <= $cs['dong_sau'];
}

/**
 * Trạng thái kỳ. Không cần cron — suy từ lịch và bản ghi kỳ.
 *
 * Đã duyệt và đã khóa là trạng thái chốt, giữ nguyên bất kể lịch.
 * Trạng thái MO trong bản ghi KHÔNG có nghĩa là mở vĩnh viễn: kỳ vẫn
 * đóng khi hết cửa sổ. Trước đây bản ghi đè lên lịch nên khoa nào đã
 * lưu một lần là kỳ không bao giờ tự khóa nữa.
 */
function trang_thai_ky(int $nam, int $thang, int $idKhoa): string
{
    $ky = ban_ghi_ky($nam, $thang, $idKhoa);
    if ($ky && in_array($ky['trang_thai'], ['DA_DUYET', 'DA_KHOA'], true)) {
        return $ky['trang_thai'];
    }
    $daNop = $ky && $ky['trang_thai'] === 'DA_NOP';

    if (trong_cua_so($nam, $thang, $idKhoa)) {
        return $daNop ? 'DA_NOP' : 'MO';
    }
    $cs = cua_so_ky($nam, $thang, $idKhoa);
    if (time() < $cs['mo_tu']) {
        return 'CHUA_DEN';
    }
    // Hết hạn: đã nộp thì chờ duyệt, chưa nộp thì coi như khóa
    return $daNop ? 'DA_NOP' : 'DA_KHOA';
}

function ten_trang_thai(string $tt): string
{
    return match ($tt) {
        'CHUA_DEN' => 'Chưa đến kỳ',
        'MO'       => 'Đang mở',
        'DA_NOP'   => 'Đã nộp, chờ duyệt',
        'DA_DUYET' => 'Đã duyệt',
        'DA_KHOA'  => 'Đã khóa',
        default    => $tt,
    };
}

/**
 * Khoa có được sửa số liệu kỳ này không?
 *
 * Còn trong cửa sổ thì sửa thoải mái, kể cả đã bấm Nộp — nộp rồi phát hiện
 * sai vẫn sửa lại được, không phải xin Phòng KHTH trả lại.
 * Đã duyệt hoặc đã khóa thì dừng, chỉ còn đường bút toán điều chỉnh.
 */
function ky_cho_sua(int $nam, int $thang, int $idKhoa): bool
{
    $tt = trang_thai_ky($nam, $thang, $idKhoa);
    if (in_array($tt, ['DA_DUYET', 'DA_KHOA', 'CHUA_DEN'], true)) {
        return false;
    }
    return trong_cua_so($nam, $thang, $idKhoa);
}


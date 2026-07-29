<?php
/**
 * Phần dùng chung của ba trang danh mục chỉ tiêu:
 *   danh-muc-chi-tieu.php    — xem và sửa
 *   chi-tieu-them.php        — thêm mới
 *   chi-tieu-nap-mac-dinh.php— nạp lại bộ mặc định
 */
require_once __DIR__ . '/chi_tieu.php';

const NHAN = [
    'DEM'          => 'Đếm (cộng dồn được)',
    'TRUNG_BINH'   => 'Trung bình (không cộng dồn)',
    'TY_LE'        => 'Tỷ lệ % (không cộng dồn)',
    'HANG_SO'      => 'Hằng số (không đổi theo tháng)',
    'NHAP_TAY'     => 'Khoa nhập tay',
    'TONG_CON'     => 'Bằng tổng các nội dung nhỏ',
    'CONG_THUC'    => 'Tính theo công thức',
    'CAO_TOT'      => 'Càng cao càng tốt',
    'THAP_TOT'     => 'Càng thấp càng tốt',
    'DICH_CO_DINH' => 'Đích đúng 100%',
    'THEO_NGAY'    => 'Chia theo số ngày của tháng',
    'KHONG_CHIA'   => 'Giữ nguyên mức năm',
];

function danh_sach_khoa_hoat_dong(): array
{
    static $ds = null;
    if ($ds === null) {
        $ds = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');
    }
    return $ds;
}

/**
 * Số dòng số liệu/kế hoạch của từng chỉ tiêu.
 * Gom thành hai câu lệnh thay vì hỏi lại cho từng dòng, vì bảng có gần 60 dòng.
 */
function bang_dem_du_lieu(): array
{
    static $dem = null;
    if ($dem !== null) {
        return $dem;
    }
    $dem = [];
    foreach (['so_lieu', 'ke_hoach'] as $bang) {
        foreach (qAll("SELECT id_chi_tieu, COUNT(*) AS n FROM $bang GROUP BY id_chi_tieu") as $r) {
            $id = (int)$r['id_chi_tieu'];
            $dem[$id] = ($dem[$id] ?? 0) + (int)$r['n'];
        }
    }
    return $dem;
}

/** Chỉ tiêu đã có số liệu hoặc kế hoạch thì không xóa cứng. */
function chi_tieu_co_du_lieu(int $id): int
{
    return bang_dem_du_lieu()[$id] ?? 0;
}

/**
 * Sinh mã chỉ tiêu từ nội dung, để người nhập không phải tự nghĩ ra mã.
 * "Tổng số lượt tiêm chủng" -> "TONG_SO_LUOT_TIEM_CHUNG"
 * Trùng thì thêm số đằng sau.
 */
function ma_tu_ten(string $ten): string
{
    static $co = 'àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡ'
        . 'ùúụủũưừứựửữỳýỵỷỹđ';
    static $khong = 'aaaaaaaaaaaaaaaaaeeeeeeeeeeeiiiiiooooooooooooooooo'
        . 'uuuuuuuuuuuyyyyyd';

    $s = chu_thuong(trim($ten));
    $a = preg_split('//u', $co, -1, PREG_SPLIT_NO_EMPTY);
    $b = preg_split('//u', $khong, -1, PREG_SPLIT_NO_EMPTY);
    if (count($a) === count($b)) {
        $s = strtr($s, array_combine($a, $b));
    }
    $s = strtoupper($s);
    $s = preg_replace('/[^A-Z0-9]+/', '_', $s);
    $s = trim((string)$s, '_');
    if ($s === '') {
        $s = 'CT';
    }
    $s = substr($s, 0, 26);

    $goc = $s; $i = 1;
    while (qVal('SELECT 1 FROM chi_tieu WHERE ma = ?', [$s])) {
        $s = substr($goc, 0, 26) . '_' . (++$i);
    }
    return $s;
}

/** Mã mà bộ máy tính toán tham chiếu trực tiếp — không cho đổi hay xóa. */
function la_he_thong(string $ma): bool
{
    return in_array($ma, MA_CHI_TIEU_HE_THONG, true);
}

/**
 * Đọc các trường thông số của biểu mẫu thêm/sửa chỉ tiêu.
 * Trả về mảng rỗng nếu có giá trị không hợp lệ.
 */
function doc_bieu_mau(bool $laDev): array
{
    $loai   = post('loai_gia_tri', 'DEM');
    $nguon  = post('nguon', 'NHAP_TAY');
    $huong  = post('huong', 'CAO_TOT');
    $phanBo = post('phan_bo', 'THEO_NGAY');

    // Chỉ người phát triển được đặt chỉ tiêu dạng công thức, vì công thức
    // gắn cứng theo mã trong app/chi_tieu.php, không phải nhập tự do.
    if ($nguon === 'CONG_THUC' && !$laDev) {
        $nguon = 'NHAP_TAY';
    }
    foreach ([[$loai,   ['DEM', 'TRUNG_BINH', 'TY_LE', 'HANG_SO']],
              [$nguon,  ['NHAP_TAY', 'TONG_CON', 'CONG_THUC']],
              [$huong,  ['CAO_TOT', 'THAP_TOT', 'DICH_CO_DINH']],
              [$phanBo, ['THEO_NGAY', 'KHONG_CHIA']]] as [$v, $hopLe]) {
        if (!in_array($v, $hopLe, true)) {
            return [];
        }
    }
    return ['loai' => $loai, 'nguon' => $nguon, 'huong' => $huong, 'phan_bo' => $phanBo];
}

/**
 * Giữ cho cha và con luôn cùng phạm vi khoa.
 *
 * Trang nhập liệu duyệt theo cây: đi từ nội dung lớn rồi mới tới nội dung nhỏ.
 * Nếu con được gán cho một khoa mà cha không được gán, dòng con sẽ biến mất
 * khỏi biểu mẫu mà không báo gì — người dùng tưởng mất dữ liệu.
 *
 *   - Gán con cho khoa nào thì cha cũng phải có khoa đó.
 *   - Bỏ cha khỏi khoa nào thì các con cũng bị bỏ khỏi khoa đó.
 *
 * @return string[] mô tả những thay đổi kéo theo, để báo lại cho người dùng
 */
function dong_bo_cha_con(int $idChiTieu): array
{
    $ct = q1('SELECT id, id_cha FROM chi_tieu WHERE id = ?', [$idChiTieu]);
    if (!$ct) {
        return [];
    }
    $ghiChu = [];
    $maKhoa = fn($id) => qVal('SELECT ma FROM khoa WHERE id = ?', [$id]);

    if ($ct['id_cha'] !== null) {
        $them = [];
        foreach (qAll('SELECT id_khoa FROM chi_tieu_ap_dung WHERE id_chi_tieu = ?',
                [$idChiTieu]) as $r) {
            $idK = (int)$r['id_khoa'];
            if (!qVal('SELECT 1 FROM chi_tieu_ap_dung WHERE id_chi_tieu=? AND id_khoa=?',
                    [$ct['id_cha'], $idK])) {
                q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)',
                    [$ct['id_cha'], $idK]);
                $them[] = $maKhoa($idK);
            }
        }
        if ($them) {
            $tenCha = qVal('SELECT ten FROM chi_tieu WHERE id = ?', [$ct['id_cha']]);
            $ghiChu[] = "Đã gán thêm nội dung lớn \"$tenCha\" cho "
                . implode(', ', $them) . ' để dòng con hiện ra được.';
        }
    } else {
        $khoaCha = array_map('intval',
            array_column(qAll('SELECT id_khoa FROM chi_tieu_ap_dung WHERE id_chi_tieu = ?',
                [$idChiTieu]), 'id_khoa'));
        $bo = [];
        foreach (qAll('SELECT id FROM chi_tieu WHERE id_cha = ?', [$idChiTieu]) as $con) {
            foreach (qAll('SELECT id_khoa FROM chi_tieu_ap_dung WHERE id_chi_tieu = ?',
                    [$con['id']]) as $r) {
                $idK = (int)$r['id_khoa'];
                if (!in_array($idK, $khoaCha, true)) {
                    q('DELETE FROM chi_tieu_ap_dung WHERE id_chi_tieu=? AND id_khoa=?',
                        [$con['id'], $idK]);
                    $bo[$maKhoa($idK)] = true;
                }
            }
        }
        if ($bo) {
            $ghiChu[] = 'Các nội dung nhỏ bên trong cũng đã được bỏ khỏi '
                . implode(', ', array_keys($bo)) . '.';
        }
    }
    return $ghiChu;
}

/** Danh sách chỉ tiêu sắp theo cây: nội dung lớn rồi tới nội dung nhỏ của nó. */
function cay_chi_tieu_day_du(): array
{
    $tatCa = qAll('SELECT * FROM chi_tieu ORDER BY thu_tu, id');
    $cay = [];
    foreach ($tatCa as $ct) {
        if ($ct['id_cha'] !== null) {
            continue;
        }
        $ct['cap'] = 0;
        $cay[] = $ct;
        foreach ($tatCa as $con) {
            if ($con['id_cha'] !== null && (int)$con['id_cha'] === (int)$ct['id']) {
                $con['cap'] = 1;
                $cay[] = $con;
            }
        }
    }
    return $cay;
}

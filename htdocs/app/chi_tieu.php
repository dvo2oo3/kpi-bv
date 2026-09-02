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

/** Toàn bộ thư viện chỉ tiêu, đánh chỉ mục theo id. */
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

/**
 * Khi tổng hợp toàn viện cho một chỉ tiêu chuẩn (mã $ma), cần cộng cả số liệu
 * của những chỉ tiêu RIÊNG mà khoa khai báo "gộp vào" nó (gop_vao = $ma).
 * Trả về danh sách id: chính nó + các chỉ tiêu gộp vào.
 */
function ids_gop_vao(string $ma): array
{
    $ids = [];
    $chinh = chi_tieu_theo_ma($ma);
    if ($chinh) {
        $ids[] = (int)$chinh['id'];
    }
    foreach (tat_ca_chi_tieu() as $ct) {
        if (($ct['gop_vao'] ?? null) === $ma) {
            $ids[] = (int)$ct['id'];
        }
    }
    return $ids;
}

/** Loại giá trị RIÊNG mà mỗi khoa đặt đè (id_chi_tieu => loại). Chỉ gồm dòng có
 *  đặt riêng; dòng nào không có = theo loại chung của chi_tieu. Có bọc try/catch
 *  cho CSDL cũ chưa có cột loai_gia_tri ở chi_tieu_ap_dung. */
function loai_ap_dung(int $idKhoa): array
{
    static $bo_nho = [];
    if (!array_key_exists($idKhoa, $bo_nho)) {
        $m = [];
        try {
            foreach (qAll('SELECT id_chi_tieu, loai_gia_tri FROM chi_tieu_ap_dung
                            WHERE id_khoa = ? AND loai_gia_tri IS NOT NULL', [$idKhoa]) as $r) {
                $m[(int)$r['id_chi_tieu']] = $r['loai_gia_tri'];
            }
        } catch (Throwable $e) {
            // Cột chưa tồn tại (CSDL cũ) → coi như không khoa nào đặt riêng.
        }
        $bo_nho[$idKhoa] = $m;
    }
    return $bo_nho[$idKhoa];
}

/** Loại giá trị HIỆU DỤNG của một chỉ tiêu trong một khoa: loại riêng của khoa
 *  nếu có, không thì loại chung của thư viện. */
function loai_hieu_dung(int $idKhoa, array $ct): string
{
    return loai_ap_dung($idKhoa)[(int)$ct['id']] ?? ($ct['loai_gia_tri'] ?? 'DEM');
}

/** Các chỉ tiêu áp dụng cho một khoa, đã sắp theo cây (cha → con → cháu…).
 *  Lồng KHÔNG giới hạn số cấp; 'cap' là độ sâu (gốc = 0).
 *
 *  Thứ tự sắp theo chi_tieu_ap_dung.thu_tu — thứ tự RIÊNG của khoa; chỉ tiêu nào
 *  chưa đặt riêng (thu_tu = 0) thì lùi về thứ tự thư viện (chi_tieu.thu_tu). Nhờ
 *  vậy sắp xếp ở một khoa không làm xáo trộn thứ tự của khoa khác. Trường 'thu_tu'
 *  trả về đã là thứ tự hiệu dụng của khoa (để giao diện/báo cáo hiển thị đúng). */
function chi_tieu_cua_khoa(int $idKhoa): array
{
    static $bo_nho = [];
    if (isset($bo_nho[$idKhoa])) {
        return $bo_nho[$idKhoa];
    }

    // id_chi_tieu => thứ tự riêng của khoa (0 = chưa đặt).
    $ap = [];
    foreach (qAll('SELECT id_chi_tieu, thu_tu FROM chi_tieu_ap_dung WHERE id_khoa = ?', [$idKhoa]) as $r) {
        $ap[(int)$r['id_chi_tieu']] = (int)$r['thu_tu'];
    }

    $tatCa = tat_ca_chi_tieu();
    // Thứ tự hiệu dụng: riêng khoa nếu >0, không thì lùi về thứ tự thư viện.
    $ttKhoa = fn(int $id): int => ($ap[$id] ?? 0) > 0 ? $ap[$id] : (int)($tatCa[$id]['thu_tu'] ?? 0);
    // Sắp một danh sách id anh em theo thứ tự hiệu dụng (chốt bằng thư viện rồi id).
    $sapAnhEm = function (array $ids) use ($ttKhoa, $tatCa): array {
        usort($ids, function (int $a, int $b) use ($ttKhoa, $tatCa): int {
            $va = $ttKhoa($a); $vb = $ttKhoa($b);
            if ($va !== $vb) { return $va <=> $vb; }
            $la = (int)($tatCa[$a]['thu_tu'] ?? 0); $lb = (int)($tatCa[$b]['thu_tu'] ?? 0);
            return $la !== $lb ? $la <=> $lb : $a <=> $b;
        });
        return $ids;
    };

    // Gom con theo cha, mỗi nhóm sắp theo thứ tự riêng của khoa.
    $conCua = [];
    foreach ($tatCa as $ct) {
        $id = (int)$ct['id'];
        if ($ct['id_cha'] !== null && isset($ap[$id])) {
            $conCua[(int)$ct['id_cha']][] = $id;
        }
    }
    foreach ($conCua as &$ids) { $ids = $sapAnhEm($ids); }
    unset($ids);

    // Gốc = chỉ tiêu áp dụng có id_cha null, cũng sắp theo thứ tự riêng của khoa.
    $goc = [];
    foreach ($tatCa as $ct) {
        if ($ct['id_cha'] === null && isset($ap[(int)$ct['id']])) {
            $goc[] = (int)$ct['id'];
        }
    }
    $goc = $sapAnhEm($goc);

    $loaiRieng = loai_ap_dung($idKhoa);   // id => loại riêng khoa này (nếu có)
    $ketQua = [];
    $duyet = function (int $id, int $cap) use (&$duyet, &$ketQua, &$conCua, $tatCa, $ttKhoa, $loaiRieng) {
        $ct = $tatCa[$id];
        $ct['cap']    = $cap;
        $ct['thu_tu'] = $ttKhoa($id);   // trả về thứ tự RIÊNG của khoa (số mốc để sắp)
        // Loại giá trị theo khoa: giữ loại chung để tham chiếu, đặt loại hiệu dụng.
        $ct['loai_chung']    = $ct['loai_gia_tri'];
        $ct['loai_rieng']    = $loaiRieng[$id] ?? null;             // '' rỗng = theo chung
        $ct['loai_gia_tri']  = $ct['loai_rieng'] ?? $ct['loai_chung'];
        $ketQua[] = $ct;
        foreach ($conCua[$id] ?? [] as $cid) {
            $duyet($cid, $cap + 1);      // duyệt sâu: cháu, chắt…
        }
    };
    foreach ($goc as $id) {
        $duyet($id, 0);
    }

    // 'vi_tri' = số thứ hạng 1,2,3… trong nhóm anh em (gốc tính theo gốc, con theo
    // các con cùng cha) — để giao diện hiển thị vị trí thân thiện, không phải số mốc.
    $dem = [];
    foreach ($ketQua as &$row) {
        $k = $row['id_cha'] === null ? 'goc' : (int)$row['id_cha'];
        $dem[$k] = ($dem[$k] ?? 0) + 1;
        $row['vi_tri'] = $dem[$k];
    }
    unset($row);

    return $bo_nho[$idKhoa] = $ketQua;
}

/**
 * Đánh lại thu_tu toàn bộ cây thành bước đều 10 (10, 20, 30…) theo ĐÚNG thứ tự
 * hiển thị hiện tại (duyệt cây DFS). Giữ nguyên trật tự, chỉ làm sạch con số —
 * gọi sau khi kéo đổi vị trí / xóa để số không bị nhảy hay để lại lỗ hổng.
 */
function danh_lai_thu_tu(): void
{
    $tatCa = qAll('SELECT id, id_cha FROM chi_tieu ORDER BY thu_tu, id');
    $con = [];
    foreach ($tatCa as $r) {
        if ($r['id_cha'] !== null) { $con[(int)$r['id_cha']][] = (int)$r['id']; }
    }
    $order = [];
    $dfs = function (int $id) use (&$dfs, &$con, &$order) {
        $order[] = $id;
        foreach ($con[$id] ?? [] as $c) { $dfs($c); }
    };
    foreach ($tatCa as $r) {
        if ($r['id_cha'] === null) { $dfs((int)$r['id']); }
    }

    $tuMo = !db()->inTransaction();      // tự mở giao dịch nếu chưa có
    if ($tuMo) { db()->beginTransaction(); }
    $tt = 10;
    foreach ($order as $id) {
        q('UPDATE chi_tieu SET thu_tu = ? WHERE id = ?', [$tt, $id]);
        $tt += 10;
    }
    if ($tuMo) { db()->commit(); }
}

/** Độ sâu của một chỉ tiêu trong cây (gốc = 0). Có chặn vòng lặp. */
function cap_cua_ct(int $id): int
{
    $tatCa = tat_ca_chi_tieu();
    $cap = 0;
    $cur = $tatCa[$id] ?? null;
    while ($cur && $cur['id_cha'] !== null && $cap < 30) {
        $cap++;
        $cur = $tatCa[(int)$cur['id_cha']] ?? null;
    }
    return $cap;
}

/** Tất cả id hậu duệ (con, cháu, chắt…) của một chỉ tiêu. */
function hau_due_ids(int $id): array
{
    $conCua = [];
    foreach (tat_ca_chi_tieu() as $ct) {
        if ($ct['id_cha'] !== null) {
            $conCua[(int)$ct['id_cha']][] = (int)$ct['id'];
        }
    }
    $out = [];
    $duyet = function (int $id) use (&$duyet, &$out, &$conCua) {
        foreach ($conCua[$id] ?? [] as $con) {
            $out[] = $con;
            $duyet($con);
        }
    };
    $duyet($id);
    return $out;
}

/** Toàn bộ chỉ tiêu ĐANG DÙNG theo thứ tự cây, mỗi phần tử có 'cap' (độ sâu). */
function cay_tat_ca(): array
{
    $conCua = [];
    foreach (tat_ca_chi_tieu() as $ct) {
        if ($ct['id_cha'] !== null) {
            $conCua[(int)$ct['id_cha']][] = $ct;
        }
    }
    $out = [];
    $duyet = function (array $ct, int $cap) use (&$duyet, &$out, &$conCua) {
        $ct['cap'] = $cap;
        $out[] = $ct;
        foreach ($conCua[$ct['id']] ?? [] as $con) {
            $duyet($con, $cap + 1);
        }
    };
    foreach (tat_ca_chi_tieu() as $ct) {
        if ($ct['id_cha'] === null) {
            $duyet($ct, 0);
        }
    }
    return $out;
}

/** Bản đồ id => số thứ hạng 1,2,3… trong nhóm anh em của THƯ VIỆN (thứ tự chung).
 *  Tính trên TOÀN BỘ chỉ tiêu (kể cả ngừng dùng) theo đúng thứ tự thư viện. */
function vi_tri_thu_vien(int $id): int
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $dem = [];
        foreach (qAll('SELECT id, id_cha FROM chi_tieu ORDER BY thu_tu, id') as $r) {
            $k = $r['id_cha'] === null ? 'goc' : (int)$r['id_cha'];
            $dem[$k] = ($dem[$k] ?? 0) + 1;
            $map[(int)$r['id']] = $dem[$k];
        }
    }
    return $map[$id] ?? 0;
}

/** Chuyển một chỉ tiêu tới VỊ TRÍ thứ $viTri (1-based) trong nhóm anh em thư viện.
 *  Chỉ đảo chỗ trong nhóm (dùng lại đúng tập số mốc của nhóm nên không đụng nhóm
 *  khác), sau đó đánh lại số cả cây cho gọn. */
function dat_vi_tri_thu_vien(int $id, int $viTri): void
{
    $ct = q1('SELECT id, id_cha FROM chi_tieu WHERE id = ?', [$id]);
    if (!$ct) { return; }
    $cha = $ct['id_cha'] !== null ? (int)$ct['id_cha'] : null;

    $anhEm = $cha === null
        ? qAll('SELECT id, thu_tu FROM chi_tieu WHERE id_cha IS NULL ORDER BY thu_tu, id')
        : qAll('SELECT id, thu_tu FROM chi_tieu WHERE id_cha = ? ORDER BY thu_tu, id', [$cha]);
    if (count($anhEm) < 2) { return; }

    $ids  = array_map(fn($r) => (int)$r['id'], $anhEm);
    $slot = array_map(fn($r) => (int)$r['thu_tu'], $anhEm);
    sort($slot);   // giữ nguyên tập số mốc của nhóm

    $cur = array_search($id, $ids, true);
    if ($cur === false) { return; }
    array_splice($ids, $cur, 1);
    $viTri = max(1, min($viTri, count($ids) + 1));
    array_splice($ids, $viTri - 1, 0, [$id]);

    $tuMo = !db()->inTransaction();
    if ($tuMo) { db()->beginTransaction(); }
    foreach ($ids as $k => $sid) {
        q('UPDATE chi_tieu SET thu_tu = ? WHERE id = ?', [$slot[$k], $sid]);
    }
    danh_lai_thu_tu();   // dồn lại số cả cây cho gọn (đang trong giao dịch)
    if ($tuMo) { db()->commit(); }
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

/** Danh sách tháng của một kỳ. Ví dụ: 'Q1' => [1,2,3], '6T' => [1..6], '7T' => [1..7] */
function cac_thang_cua_ky(string $ky): array
{
    // "N T" = lũy kế N tháng đầu năm (3T, 6T, 7T, 9T… — bất kỳ N nào)
    if (preg_match('/^(\d{1,2})T$/', $ky, $m)) {
        return range(1, min(12, max(1, (int)$m[1])));
    }
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
    if (preg_match('/^(\d{1,2})T$/', $ky, $m)) {
        return $m[1] . ' tháng';
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

    // Ghi chú (theo loại HIỆU DỤNG của khoa): luôn là số nhập tay, không tự tính.
    if (loai_hieu_dung($idKhoa, $ct) === 'GHI_CHU') {
        $tho = so_lieu_tho($nam, $idKhoa);
        return $tho[$idChiTieu][$thang] ?? null;
    }

    if ($ct['nguon'] === 'TONG_CON') {
        $con = con_cua($idChiTieu, $idKhoa);
        if ($con) {                       // có con trong khoa → cộng các con
            return tong_con_thang($nam, $thang, $idKhoa, $idChiTieu);
        }
        // Không có con nào trong khoa này → coi như nhập tay (đọc số liệu thô bên dưới)
    }

    // Tổng của con — sửa tay được: mặc định tự cộng con, nhưng nếu đã nhập số
    // tay (đè) thì lấy số tay. Dùng cho dòng cha mà các con là "trong đó" (tập con).
    if ($ct['nguon'] === 'TONG_CON_TAY') {
        $tho = so_lieu_tho($nam, $idKhoa);
        $tay = $tho[$idChiTieu][$thang] ?? null;
        if ($tay !== null) {
            return $tay;                  // đã sửa tay → dùng số tay
        }
        return tong_con_thang($nam, $thang, $idKhoa, $idChiTieu);
    }

    if ($ct['nguon'] === 'CONG_THUC') {
        return tinh_cong_thuc($ct['ma'], $nam, [$thang], $idKhoa);
    }

    $tho = so_lieu_tho($nam, $idKhoa);
    return $tho[$idChiTieu][$thang] ?? null;
}

/**
 * Tổng các con của một chỉ tiêu trong một tháng (bỏ qua con là GHI_CHU).
 * Trả về null nếu không con nào có số. Dùng cho cả TONG_CON và TONG_CON_TAY,
 * và cho gợi ý "tự cộng" ở trang nhập số liệu.
 */
function tong_con_thang(int $nam, int $thang, int $idKhoa, int $idChiTieu): ?float
{
    $tong = null;
    foreach (con_cua($idChiTieu, $idKhoa) as $c) {
        if (loai_hieu_dung($idKhoa, $c) === 'GHI_CHU') {
            continue;                     // con là Ghi chú (theo khoa) → không cộng
        }
        $v = gia_tri_thang($nam, $thang, $idKhoa, (int)$c['id']);
        if ($v !== null) {
            $tong = ($tong ?? 0) + $v;
        }
    }
    return $tong;
}

/**
 * Ô này bác sĩ NHẬP TAY được không (trong bối cảnh một khoa)?
 *   - NHAP_TAY  : luôn nhập.
 *   - TONG_CON  : chỉ nhập khi KHÔNG có con nào trong khoa (tránh dòng "tổng của
 *                 con" mà rỗng con → không nhập được, cũng không tính được).
 *   - CONG_THUC : không (hệ thống tự tính).
 */
function nhap_tay_duoc(array $ct, int $idKhoa): bool
{
    // Ghi chú (theo loại hiệu dụng của khoa): luôn nhập tay (chỉ hiển thị, không
    // bao giờ tự tính/cộng con), kể cả khi có con hay nguồn = tổng của con/công thức.
    if (loai_hieu_dung($idKhoa, $ct) === 'GHI_CHU') {
        return true;
    }
    if (($ct['nguon'] ?? '') === 'NHAP_TAY') {
        return true;
    }
    // Tổng của con — sửa tay được: luôn cho nhập để đè lên tổng tự cộng.
    if (($ct['nguon'] ?? '') === 'TONG_CON_TAY') {
        return true;
    }
    if (($ct['nguon'] ?? '') === 'TONG_CON') {
        return count(con_cua((int)$ct['id'], $idKhoa)) === 0;
    }
    return false;
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

    // HANG_SO và GHI_CHU (theo loại hiệu dụng của khoa): không cộng dồn — lấy
    // giá trị tháng gần nhất có nhập.
    if (in_array(loai_hieu_dung($idKhoa, $ct), ['HANG_SO', 'GHI_CHU'], true)) {
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
 * Mô tả công thức ngắn gọn để chú thích trên giao diện (chỉ tiêu tự tính).
 * Trả về null với chỉ tiêu nhập tay / cộng con.
 */
function mo_ta_cong_thuc(array $ct): ?string
{
    if (($ct['nguon'] ?? '') !== 'CONG_THUC') {
        return null;
    }
    // Công thức tự cấu hình: dựng chuỗi từ tử/mẫu đã chọn
    if (!empty($ct['phep_tinh']) && !empty($ct['ct_tu']) && !empty($ct['ct_mau'])) {
        $tu  = chi_tieu_theo_ma($ct['ct_tu']);
        $mau = chi_tieu_theo_ma($ct['ct_mau']);
        $tenTu  = $tu  ? $tu['ten']  : $ct['ct_tu'];
        $tenMau = $mau ? $mau['ten'] : $ct['ct_mau'];
        $mauStr = !empty($ct['nhan_so_ngay']) ? "($tenMau × số ngày)" : $tenMau;
        return $ct['phep_tinh'] === 'TY_LE' ? "$tenTu ÷ $mauStr × 100" : "$tenTu ÷ $mauStr";
    }
    // Mã hệ thống cài sẵn
    return match ($ct['ma']) {
        'CSGB'    => 'Ngày điều trị nội trú ÷ (giường bệnh × số ngày) × 100',
        'MAU_CSGB'=> 'Giường bệnh × số ngày (mẫu số của công suất)',
        'NDT_TB'  => 'Ngày điều trị nội trú ÷ số bệnh nhân nội trú',
        default  => 'máy tự tính từ các chỉ tiêu khác',
    };
}

/**
 * Công thức TỰ CẤU HÌNH.
 *
 * Không dùng eval: người dùng không gõ biểu thức mà CHỌN từ menu — phép tính
 * (tỷ lệ / thương), chỉ tiêu tử, chỉ tiêu mẫu. Ở đây chỉ đọc lại các lựa chọn
 * đó (đã lưu trong bảng chi_tieu) và ráp số của đúng khoa vào.
 *
 *   ket_qua = tu / mau          (×100 nếu phep_tinh = 'TY_LE')
 *   mau     = Σ mẫu các tháng ĐÃ NHẬP tử  (× số ngày nếu nhan_so_ngay = 1)
 *
 * Mẫu chỉ cộng những tháng mà tử đã có số liệu — cùng nguyên tắc "chỉ tính
 * tháng đã nhập" như công suất giường bệnh, tránh giữa năm bị loãng.
 */
function tinh_cong_thuc_cau_hinh(array $ct, int $nam, array $cacThang, int $idKhoa): ?float
{
    $ctTu  = !empty($ct['ct_tu'])  ? chi_tieu_theo_ma($ct['ct_tu'])  : null;
    $ctMau = !empty($ct['ct_mau']) ? chi_tieu_theo_ma($ct['ct_mau']) : null;
    if (!$ctTu || !$ctMau) {
        return null;   // thiếu chỉ tiêu tham chiếu -> chưa tính được
    }

    $tu = gia_tri_luy_ke($nam, $cacThang, $idKhoa, $ctTu['id']);
    if ($tu === null) {
        return null;   // khoa chưa nhập tử -> chưa có công suất
    }

    // Mẫu là hằng số (vd số bàn mổ, số giường): lấy giá trị gần nhất cho mọi tháng
    $mauHangSo = $ctMau['loai_gia_tri'] === 'HANG_SO'
        ? gia_tri_luy_ke($nam, $cacThang, $idKhoa, $ctMau['id'])
        : null;

    $nhanNgay = !empty($ct['nhan_so_ngay']);
    $mau = 0.0;
    foreach ($cacThang as $t) {
        if (gia_tri_thang($nam, $t, $idKhoa, $ctTu['id']) === null) {
            continue;   // tháng chưa nhập tử thì không đưa vào mẫu
        }
        $vMau = $mauHangSo !== null ? $mauHangSo : gia_tri_thang($nam, $t, $idKhoa, $ctMau['id']);
        if ($vMau === null) {
            continue;
        }
        $mau += $nhanNgay ? $vMau * so_ngay_thang($nam, $t) : $vMau;
    }
    if ($mau <= 0) {
        return null;
    }
    return $ct['phep_tinh'] === 'TY_LE' ? $tu / $mau * 100 : $tu / $mau;
}

/**
 * Các chỉ tiêu dẫn xuất. Không dùng eval.
 *
 * Chỉ tiêu do người dùng tự tạo mang sẵn cấu hình (phep_tinh, ct_tu, ct_mau)
 * -> tính theo cấu hình. Còn các mã hệ thống cũ (CSGB, NDT_TB…) vẫn tính theo
 * nhánh gắn cứng bên dưới để không phá dữ liệu đang chạy.
 */
function tinh_cong_thuc(string $ma, int $nam, array $cacThang, int $idKhoa): ?float
{
    static $dangTinh = [];
    if (isset($dangTinh[$ma])) {
        return null;   // chặn tham chiếu vòng (A trỏ B, B trỏ A)
    }

    $ctNay = chi_tieu_theo_ma($ma);
    if ($ctNay && !empty($ctNay['phep_tinh']) && !empty($ctNay['ct_tu']) && !empty($ctNay['ct_mau'])) {
        $dangTinh[$ma] = true;
        try {
            return tinh_cong_thuc_cau_hinh($ctNay, $nam, $cacThang, $idKhoa);
        } finally {
            unset($dangTinh[$ma]);
        }
    }

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

        // Mẫu số của công suất giường bệnh = Σ (giường × số ngày) các tháng ĐÃ nhập
        // ngày điều trị — hiện ra để đối chiếu (giống cột "Mẫu của công suất" trong Excel).
        case 'MAU_CSGB':
            if (!$ctNDT || !$ctGB) {
                return null;
            }
            $mau = 0.0;
            foreach ($cacThang as $t) {
                if (gia_tri_thang($nam, $t, $idKhoa, $ctNDT['id']) === null) {
                    continue;   // tháng chưa nhập ngày điều trị thì không tính (giống công suất)
                }
                $gb = gia_tri_thang($nam, $t, $idKhoa, $ctGB['id']);
                if ($gb === null) {
                    $gb = (float)qVal('SELECT giuong_benh FROM khoa WHERE id = ?', [$idKhoa]);
                }
                $mau += $gb * so_ngay_thang($nam, $t);
            }
            return $mau > 0 ? $mau : null;
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
    // Loại "Ghi chú" chỉ để hiển thị — không chấm đạt/không đạt.
    if (($ct['loai_gia_tri'] ?? '') === 'GHI_CHU') {
        return ['phan_tram' => null, 'danh_gia' => 'na', 'mo_ta' => 'Ghi chú — không đánh giá'];
    }
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
 *   1. Lịch riêng admin đặt cho chính khoa này
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
 * Gia hạn riêng (ky.mo_den) đè lên lịch — dùng khi admin trả lại
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
 * sai vẫn sửa lại được, không phải xin admin trả lại.
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


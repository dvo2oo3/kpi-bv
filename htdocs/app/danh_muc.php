<?php
/**
 * Phần dùng chung của ba trang thư viện chỉ tiêu:
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
    'GHI_CHU'      => 'Ghi chú (chỉ hiển thị, không cộng dồn)',
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
function chuoi_thanh_ma(string $ten, string $macDinh = 'MA', int $toiDa = 26): string
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
        $s = $macDinh;
    }
    return substr($s, 0, $toiDa);
}

/**
 * Chuẩn hóa tên để so khớp: bỏ dấu, thường hóa, bỏ dấu câu và vài từ đệm
 * ("tổng", "số", "các"…). Trả về chuỗi token cách nhau một dấu cách.
 */
function chuan_hoa_khop(string $ten): string
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
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);            // dấu câu → cách
    $s = ' ' . trim(preg_replace('/\s+/', ' ', $s)) . ' ';
    // Mở các viết tắt hay gặp để khớp tên tốt hơn
    $vietTat = [
        ' gb ' => ' giuong benh ', ' tmh ' => ' tai mui hong ',
        ' hpq ' => ' hen phe quan ', ' pnmt ' => ' phu nu mang thai ',
        ' bn ' => ' benh nhan ', ' xn ' => ' xet nghiem ', ' nt ' => ' noi tru ',
    ];
    $s = strtr($s, $vietTat);
    $s = preg_replace('/ (tong|so|cac|trong|do|cua|lan) /', ' ', $s);   // bỏ từ đệm
    $s = preg_replace('/ (tong|so|cac|trong|do|cua|lan) /', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/**
 * Khớp một tên (từ file khoa) với chỉ tiêu đang dùng trong danh mục.
 * Trả về ['ct' => row, 'diem' => 0..100] hoặc null nếu không đủ tin cậy.
 */
function khop_thu_vien(string $ten): ?array
{
    $chuan = chuan_hoa_khop($ten);
    $tight = str_replace(' ', '', $chuan);
    if ($tight === '') {
        return null;
    }
    $tokA = array_filter(explode(' ', $chuan));
    $best = null; $bestDiem = 0;
    foreach (tat_ca_chi_tieu() as $ct) {
        $c2 = chuan_hoa_khop($ct['ten']);
        $t2 = str_replace(' ', '', $c2);
        if ($t2 === '') { continue; }
        $diem = 0;
        if ($tight === $t2) {
            $diem = 100;                                   // khít
        } elseif (strlen($tight) >= 5 && strlen($t2) >= 5
               && (str_contains($t2, $tight) || str_contains($tight, $t2))) {
            $diem = 85;                                     // chứa nhau
        } else {
            $tokB = array_filter(explode(' ', $c2));
            $chung = count(array_intersect($tokA, $tokB));
            $tong = max(count($tokA), count($tokB));
            if ($tong > 0) { $diem = (int)round($chung / $tong * 70); }  // trùng từ
        }
        if ($diem > $bestDiem) { $bestDiem = $diem; $best = $ct; }
    }
    return ($best && $bestDiem >= 60) ? ['ct' => $best, 'diem' => $bestDiem] : null;
}

function ma_tu_ten(string $ten): string
{
    $goc = chuoi_thanh_ma($ten, 'CT', 26);
    $s = $goc; $i = 1;
    while (qVal('SELECT 1 FROM chi_tieu WHERE ma = ?', [$s])) {
        $s = substr($goc, 0, 26) . '_' . (++$i);
    }
    return $s;
}

/** Sinh mã khoa duy nhất từ tên (dùng khi để trống ô mã khoa). */
function ma_khoa_tu_ten(string $ten): string
{
    $goc = chuoi_thanh_ma($ten, 'KHOA', 20);
    $s = $goc; $i = 1;
    while (qVal('SELECT 1 FROM khoa WHERE ma = ?', [$s])) {
        $s = substr($goc, 0, 17) . '_' . (++$i);
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

    // Chỉ vai trò được cấp quyền mới đặt chỉ tiêu dạng công thức.
    if ($nguon === 'CONG_THUC' && !$laDev) {
        $nguon = 'NHAP_TAY';
    }
    foreach ([[$loai,   ['DEM', 'TRUNG_BINH', 'TY_LE', 'HANG_SO', 'GHI_CHU']],
              [$nguon,  ['NHAP_TAY', 'TONG_CON', 'CONG_THUC']],
              [$huong,  ['CAO_TOT', 'THAP_TOT', 'DICH_CO_DINH']],
              [$phanBo, ['THEO_NGAY', 'KHONG_CHIA']]] as [$v, $hopLe]) {
        if (!in_array($v, $hopLe, true)) {
            return [];
        }
    }

    // Công thức tự cấu hình: chọn phép tính + tử + mẫu (không gõ tự do).
    $phepTinh = null; $ctTu = null; $ctMau = null; $nhanNgay = 0;
    if ($nguon === 'CONG_THUC') {
        $phepTinh = post('phep_tinh', 'TY_LE');
        $ctTu     = chu_hoa(post('ct_tu'));
        $ctMau    = chu_hoa(post('ct_mau'));
        $nhanNgay = post('nhan_so_ngay') ? 1 : 0;

        if (!in_array($phepTinh, ['TY_LE', 'THUONG'], true)) {
            return [];
        }
        if ($ctTu === '' || $ctMau === '' || $ctTu === $ctMau) {
            return [];   // phải chọn đủ tử, mẫu và khác nhau
        }
        if (!qVal('SELECT 1 FROM chi_tieu WHERE ma = ? AND hoat_dong = 1', [$ctTu])
         || !qVal('SELECT 1 FROM chi_tieu WHERE ma = ? AND hoat_dong = 1', [$ctMau])) {
            return [];   // tử/mẫu phải là chỉ tiêu có thật
        }
        // Kết quả công thức không cộng dồn và không chia theo ngày.
        $loai   = $phepTinh === 'TY_LE' ? 'TY_LE' : 'TRUNG_BINH';
        $phanBo = 'KHONG_CHIA';
    }

    return [
        'loai' => $loai, 'nguon' => $nguon, 'huong' => $huong, 'phan_bo' => $phanBo,
        'phep_tinh' => $phepTinh, 'ct_tu' => $ctTu, 'ct_mau' => $ctMau,
        'nhan_so_ngay' => $nhanNgay,
    ];
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
    // Gom con theo cha
    $conCua = [];
    foreach ($tatCa as $ct) {
        if ($ct['id_cha'] !== null) {
            $conCua[(int)$ct['id_cha']][] = $ct;
        }
    }
    $cay = [];
    $duyet = function (array $ct, int $cap) use (&$duyet, &$cay, &$conCua) {
        $ct['cap'] = $cap;
        $cay[] = $ct;
        foreach ($conCua[(int)$ct['id']] ?? [] as $con) {
            $duyet($con, $cap + 1);   // lồng không giới hạn cấp
        }
    };
    foreach ($tatCa as $ct) {
        if ($ct['id_cha'] === null) {
            $duyet($ct, 0);
        }
    }
    return $cay;
}

/**
 * Tìm các nhóm chỉ tiêu TRÙNG TÊN: tên rút gọn giống nhau nhưng khác mã →
 * dashboard/báo cáo gom theo mã nên đếm rời, sai tổng. Bỏ qua chỉ tiêu hệ thống.
 * Mỗi nhóm ≥ 2 thành viên, kèm cờ:
 *   loai = 'chac'  → mọi mã cùng một nội dung lớn (trùng thật, gộp an toàn)
 *   loai = 'nghi'  → các mã nằm ở nội dung lớn KHÁC nhau (BHYT theo khám/nội trú,
 *                    Loại I phẫu thuật/thủ thuật…) → thường khác nghĩa, phải xem kỹ.
 */
function nhom_trung_lap(): array
{
    $tatCa  = qAll('SELECT * FROM chi_tieu ORDER BY thu_tu, id');
    $dem    = bang_dem_du_lieu();
    $tenCT  = [];
    foreach ($tatCa as $c) { $tenCT[(int)$c['id']] = $c['ten']; }
    $demKhoa = [];
    foreach (qAll('SELECT id_chi_tieu, COUNT(*) n FROM chi_tieu_ap_dung GROUP BY id_chi_tieu') as $r) {
        $demKhoa[(int)$r['id_chi_tieu']] = (int)$r['n'];
    }

    // Đóng gói một hàng chỉ tiêu thành "thành viên" của nhóm.
    $tv = function (array $c) use ($dem, $demKhoa, $tenCT): array {
        $id = (int)$c['id']; $idCha = (int)($c['id_cha'] ?? 0);
        return [
            'row' => $c, 'id' => $id,
            'so_dl'   => $dem[$id] ?? 0,
            'so_khoa' => $demKhoa[$id] ?? 0,
            'ten_cha' => $idCha ? ($tenCT[$idCha] ?? '?') : '',
        ];
    };

    // Gom hai cấp: theo TÊN rút gọn, rồi trong mỗi tên gom tiếp theo NỘI DUNG LỚN.
    $theoTen = [];
    foreach ($tatCa as $c) {
        if (la_he_thong($c['ma'])) { continue; }
        $khop = chuan_hoa_khop($c['ten']);
        if ($khop === '') { continue; }
        $theoTen[$khop][(int)($c['id_cha'] ?? 0)][] = $c;
    }

    $kq = [];
    foreach ($theoTen as $theoCha) {
        $coTrungCungCha = false;
        // CHẮC CHẮN: mỗi nội dung lớn có ≥2 mã cùng tên → trùng thật, gộp an toàn.
        foreach ($theoCha as $ds) {
            if (count($ds) < 2) { continue; }
            $coTrungCungCha = true;
            $ms = array_map($tv, $ds);
            $kq[] = [
                'ten' => $ds[0]['ten'], 'loai' => 'chac', 'nhieu_cha' => false,
                'ten_cha' => $ms[0]['ten_cha'], 'members' => $ms,
            ];
        }
        // NGHI NGỜ: cùng tên nhưng nằm ở ≥2 nội dung lớn khác nhau, và mỗi nơi chỉ
        // còn 1 mã (đã sạch trùng cùng cha) → mỗi nội dung lớn một đại diện để xem.
        if (!$coTrungCungCha && count($theoCha) >= 2) {
            $reps = [];
            foreach ($theoCha as $ds) { $reps[] = $tv($ds[0]); }
            $kq[] = [
                'ten' => $reps[0]['row']['ten'], 'loai' => 'nghi', 'nhieu_cha' => true,
                'ten_cha' => '', 'members' => $reps,
            ];
        }
    }
    // Trùng chắc chắn lên trước; trong mỗi loại, nhóm nhiều thành viên trước
    usort($kq, function ($a, $b) {
        if ($a['loai'] !== $b['loai']) { return $a['loai'] === 'chac' ? -1 : 1; }
        return count($b['members']) <=> count($a['members']);
    });
    return $kq;
}

/** Số nhóm trùng CHẮC CHẮN (cùng nội dung lớn) — dùng cho badge nhắc việc. */
function so_nhom_trung_chac(): int
{
    return count(array_filter(nhom_trung_lap(), fn($g) => $g['loai'] === 'chac'));
}

/**
 * Gộp các chỉ tiêu trùng ($boIds) vào một chỉ tiêu giữ lại ($giuId).
 * Dời toàn bộ số liệu / kế hoạch / khoa áp dụng / bút toán / con cái sang bản giữ;
 * trùng khóa (cùng năm-tháng-khoa) thì CỘNG DỒN giá trị số; rồi xóa các bản trùng.
 * Làm trong một transaction. Trả về tóm tắt hoặc ['loi' => ...].
 */
function gop_chi_tieu(int $giuId, array $boIds): array
{
    $giu = q1('SELECT * FROM chi_tieu WHERE id = ?', [$giuId]);
    if (!$giu) { return ['loi' => 'Chỉ tiêu giữ lại không tồn tại.']; }
    $boIds = array_values(array_unique(array_filter(array_map('intval', $boIds),
        fn($x) => $x > 0 && $x !== $giuId)));
    if (!$boIds) { return ['loi' => 'Không có chỉ tiêu nào để gộp.']; }

    foreach (array_merge([$giuId], $boIds) as $x) {
        $c = q1('SELECT ma FROM chi_tieu WHERE id = ?', [$x]);
        if (!$c) { return ['loi' => "Chỉ tiêu #$x không tồn tại."]; }
        if (la_he_thong($c['ma'])) {
            return ['loi' => "\"{$c['ma']}\" là chỉ tiêu hệ thống — không gộp được."];
        }
    }

    $tt = ['di_chuyen' => 0, 'cong_gop' => 0, 'xoa' => 0, 'con' => 0, 'khoa' => 0];
    db()->beginTransaction();

    // Bảng số liệu có khóa (…, id_khoa, id_chi_tieu): dời, trùng khóa thì cộng dồn.
    $bangSo = [
        'so_lieu'        => [['nam', 'thang', 'id_khoa'], ['gia_tri']],
        'ke_hoach'       => [['nam', 'id_khoa'],          ['chi_tieu_giao', 'chi_tieu_nang_luc', 'th_nam_truoc']],
        'ke_hoach_thang' => [['nam', 'thang', 'id_khoa'], ['chi_tieu']],
    ];
    foreach ($bangSo as $bang => [$keys, $sums]) {
        $whKey = implode(' AND ', array_map(fn($k) => "$k = ?", $keys));
        foreach ($boIds as $dup) {
            foreach (qAll("SELECT * FROM $bang WHERE id_chi_tieu = ?", [$dup]) as $r) {
                $kv = array_map(fn($k) => $r[$k], $keys);
                $co = q1("SELECT * FROM $bang WHERE id_chi_tieu = ? AND $whKey",
                    array_merge([$giuId], $kv));
                if ($co) {
                    $set = []; $sp = [];
                    foreach ($sums as $col) {
                        $set[] = "$col = ?";
                        $sp[]  = (float)($co[$col] ?? 0) + (float)($r[$col] ?? 0);
                    }
                    q("UPDATE $bang SET " . implode(', ', $set) . " WHERE id_chi_tieu = ? AND $whKey",
                        array_merge($sp, [$giuId], $kv));
                    q("DELETE FROM $bang WHERE id_chi_tieu = ? AND $whKey",
                        array_merge([$dup], $kv));
                    $tt['cong_gop']++;
                } else {
                    q("UPDATE $bang SET id_chi_tieu = ? WHERE id_chi_tieu = ? AND $whKey",
                        array_merge([$giuId, $dup], $kv));
                    $tt['di_chuyen']++;
                }
            }
        }
    }

    // Khoa áp dụng (tập hợp): dời khoa chưa có ở bản giữ, đã có thì bỏ.
    foreach ($boIds as $dup) {
        foreach (qAll('SELECT id_khoa FROM chi_tieu_ap_dung WHERE id_chi_tieu = ?', [$dup]) as $r) {
            $idK = (int)$r['id_khoa'];
            if (qVal('SELECT 1 FROM chi_tieu_ap_dung WHERE id_chi_tieu = ? AND id_khoa = ?', [$giuId, $idK])) {
                q('DELETE FROM chi_tieu_ap_dung WHERE id_chi_tieu = ? AND id_khoa = ?', [$dup, $idK]);
            } else {
                q('UPDATE chi_tieu_ap_dung SET id_chi_tieu = ? WHERE id_chi_tieu = ? AND id_khoa = ?', [$giuId, $dup, $idK]);
                $tt['khoa']++;
            }
        }
    }

    $in = implode(',', array_fill(0, count($boIds), '?'));
    // Bút toán điều chỉnh: có id riêng nên chỉ cần trỏ lại.
    q("UPDATE dieu_chinh SET id_chi_tieu = ? WHERE id_chi_tieu IN ($in)", array_merge([$giuId], $boIds));
    // Con cái của bản trùng → làm con bản giữ.
    $tt['con'] = q("UPDATE chi_tieu SET id_cha = ? WHERE id_cha IN ($in)", array_merge([$giuId], $boIds))->rowCount();
    // gop_vao lưu theo MÃ: chỉ tiêu nào gộp vào mã bản trùng → trỏ sang mã bản giữ.
    foreach ($boIds as $dup) {
        $maDup = qVal('SELECT ma FROM chi_tieu WHERE id = ?', [$dup]);
        if ($maDup) {
            q('UPDATE chi_tieu SET gop_vao = ? WHERE gop_vao = ?', [$giu['ma'], $maDup]);
        }
    }
    $tt['xoa'] = q("DELETE FROM chi_tieu WHERE id IN ($in)", $boIds)->rowCount();
    danh_lai_thu_tu();   // dồn lại số sau khi gộp (đang trong transaction)

    db()->commit();
    return ['ok' => true, 'giu' => $giu] + $tt;
}

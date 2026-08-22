<?php
require_once __DIR__ . '/db.php';

/* ============================================================
 * MÔ HÌNH PHÂN QUYỀN
 *
 *   dev    — quyền cao nhất, thuộc về người phát triển
 *   admin  — quản trị: quản lý tài khoản, giao chỉ tiêu, duyệt/khóa kỳ
 *   bacsi  — người nhập số liệu tại khoa
 *
 * Nguyên tắc: quyền gắn với VAI TRÒ (không gán lẻ cho từng người),
 * riêng phạm vi dữ liệu thì gắn với KHOA mà người đó phụ trách.
 * ============================================================ */

const VAI_TRO = [
    'dev'   => 'Người phát triển',
    'admin' => 'Quản trị (admin)',
    'bacsi' => 'Bác sĩ / Người nhập',
];

/** Danh sách quyền của từng vai trò. Dấu '*' nghĩa là toàn quyền. */
const QUYEN_THEO_VAI_TRO = [
    'dev' => ['*'],

    'admin' => [
        // Tài khoản
        'nguoidung.xem', 'nguoidung.them', 'nguoidung.sua',
        'nguoidung.doi_mat_khau', 'nguoidung.khoa', 'nguoidung.xoa',
        // Danh mục — admin tự quản lý được nội dung
        'khoa.xem', 'khoa.them', 'khoa.sua', 'khoa.ngung',
        'chitieu.xem', 'chitieu.them', 'chitieu.sua', 'chitieu.ngung',
        // Nghiệp vụ — admin nhập thay được cho khoa nộp muộn
        'chitieu.giao', 'solieu.nhap', 'solieu.xem_tat_ca', 'solieu.nhap_excel',
        'ky.duyet', 'ky.khoa', 'ky.dat_lich', 'dieuchinh.duyet',
        'nguoidung.uy_quyen',
        // Báo cáo
        'baocao.toan_vien', 'baocao.xuat', 'baocao.giam_doc', 'sao_luu.tai_ve',
        'nhatky.xem',
        // Nhận diện — đổi logo/favicon ứng dụng
        'he_thong.logo',
        // Cấu hình các ô hiển thị trên trang chủ
        'he_thong.trang_chu',
    ],

    'bacsi' => [
        'solieu.nhap', 'solieu.xem_khoa_minh',
        'ky.nop',
        'dieuchinh.de_xuat',
        'baocao.khoa_minh',
        // KHÔNG có 'solieu.nhap_excel': nạp file Excel là việc của admin.
        // Khoa gõ thẳng trên web; muốn dùng Excel thì admin ủy quyền riêng.
        // Xem được định nghĩa chỉ tiêu để biết dòng nào nhập gì.
        // KHÔNG có 'khoa.xem': trang danh mục khoa hiển thị số liệu của mọi khoa.
        'chitieu.xem',
    ],
];

/**
 * Những quyền chỉ dev mới có — admin không thể chạm tới.
 *
 * Ranh giới: admin thêm/sửa được NỘI DUNG chỉ tiêu và danh mục khoa,
 * nhưng không đụng được vào phần CẤU TRÚC (chỉ tiêu tính theo công thức,
 * xóa vĩnh viễn dữ liệu, mở lại kỳ đã khóa).
 */
const QUYEN_RIENG_DEV = [
    'ky.mo_lai',            // mở lại kỳ đã khóa
    'dieuchinh.xoa',        // xóa toàn bộ bút toán điều chỉnh của một kỳ (dọn log)
    'chitieu.cong_thuc',    // đặt chỉ tiêu dạng công thức
    'chitieu.xoa',          // xóa vĩnh viễn chỉ tiêu
    'chitieu.nap_mac_dinh',
    'khoa.xoa',
    'nguoidung.tao_admin',
    'nhatky.xoa',
    'he_thong.cau_hinh',
    'he_thong.reset',       // xóa toàn bộ số liệu/kế hoạch để làm lại từ đầu
    'he_thong.bao_tri',     // bật/tắt chế độ bảo trì toàn hệ thống
];

/**
 * Mã chỉ tiêu mà bộ máy tính toán tham chiếu trực tiếp.
 * Không cho đổi mã hay xóa, vì đổi là hỏng công thức ngày điều trị
 * trung bình, công suất giường bệnh và phần chống đếm trùng toàn viện.
 */
const MA_CHI_TIEU_HE_THONG = [
    'GB', 'NT', 'NDT', 'NDT_TB', 'CSGB', 'MAU_CSGB',
    'XN', 'XN_HH', 'XN_HS', 'XN_VS', 'XN_NT', 'XN_HIV',
    'XQ', 'CT', 'MRI', 'SA', 'DT', 'NS', 'DEXA',
];

/* ------------------------------------------------------------
 * Phiên làm việc
 * ---------------------------------------------------------- */

function bat_dau_phien(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('QLBVSESS');
    session_start();

    // Hết phiên do không thao tác
    if (isset($_SESSION['thao_tac_cuoi'])
        && (time() - $_SESSION['thao_tac_cuoi']) > PHUT_HET_PHIEN * 60) {
        dang_xuat();
        header('Location: /dang-nhap.php?ly_do=het_phien');
        exit;
    }
    $_SESSION['thao_tac_cuoi'] = time();
}

/** Người đang đăng nhập, hoặc null. */
function nguoi_dung_hien_tai(): ?array
{
    bat_dau_phien();
    if (empty($_SESSION['id_nguoi_dung'])) {
        return null;
    }

    static $nd = null;
    if ($nd === null) {
        $nd = q1(
            'SELECT id, ten_dang_nhap, ho_ten, vai_tro, hoat_dong, doi_mat_khau
             FROM nguoi_dung WHERE id = ?',
            [$_SESSION['id_nguoi_dung']]
        );
        // Tài khoản bị vô hiệu hóa giữa chừng thì đá ra ngay
        if (!$nd || (int)$nd['hoat_dong'] !== 1) {
            dang_xuat();
            return null;
        }
        $nd['khoa'] = qAll(
            'SELECT k.id, k.ma, k.ten
             FROM nguoi_dung_khoa ndk
             JOIN khoa k ON k.id = ndk.id_khoa
             WHERE ndk.id_nguoi_dung = ? ORDER BY k.thu_tu, k.ten',
            [$nd['id']]
        );
    }
    return $nd;
}

function da_dang_nhap(): bool
{
    return nguoi_dung_hien_tai() !== null;
}

/* ------------------------------------------------------------
 * Kiểm tra quyền
 * ---------------------------------------------------------- */

/**
 * Những quyền admin được phép ủy cho một người cụ thể.
 *
 * Ví dụ giao cho một trưởng khoa quyền duyệt kỳ và đặt lịch mở kỳ,
 * mà không phải nâng người đó lên vai trò quản trị.
 * Danh sách này cố ý KHÔNG có quyền động tới tài khoản và danh mục gốc.
 */
const QUYEN_CO_THE_UY = [
    'ky.duyet'          => 'Duyệt và khóa kỳ',
    'ky.khoa'           => 'Khóa kỳ',
    'ky.dat_lich'       => 'Đặt lịch mở kỳ nhập liệu',
    'solieu.xem_tat_ca' => 'Xem số liệu mọi khoa',
    'solieu.nhap_excel' => 'Nhập số liệu từ file Excel',
    'solieu.sua_ky_khoa'=> 'Sửa số liệu kỳ đã chốt (qua bút toán điều chỉnh)',
    'chitieu.giao'      => 'Giao chỉ tiêu',
    'chitieu.them'      => 'Thêm chỉ tiêu',
    'chitieu.sua'       => 'Sửa chỉ tiêu',
    'baocao.toan_vien'  => 'Xem báo cáo toàn viện',
    'baocao.xuat'       => 'Xuất báo cáo Excel',
    'nhatky.xem'        => 'Xem nhật ký hệ thống',
    'sao_luu.tai_ve'    => 'Tải bản sao lưu',
    'he_thong.trang_chu'=> 'Cấu hình ô trang chủ',
];

/** Quyền được cấp riêng cho người đang đăng nhập. */
function quyen_cap_rieng(int $idNguoiDung): array
{
    static $bo_nho = [];
    if (isset($bo_nho[$idNguoiDung])) {
        return $bo_nho[$idNguoiDung];
    }
    $ds = [];
    try {
        foreach (qAll('SELECT quyen FROM quyen_nguoi_dung WHERE id_nguoi_dung = ?',
                [$idNguoiDung]) as $r) {
            $ds[] = $r['quyen'];
        }
    } catch (Throwable $e) {
        // Bảng chưa tạo (cơ sở dữ liệu cũ) — coi như không có quyền cấp riêng
    }
    return $bo_nho[$idNguoiDung] = $ds;
}

function co_quyen(string $quyen): bool
{
    $nd = nguoi_dung_hien_tai();
    if (!$nd) {
        return false;
    }
    $vt = $nd['vai_tro'];

    if ($vt === 'dev') {
        return true;
    }
    if (in_array($quyen, QUYEN_RIENG_DEV, true)) {
        return false;   // chỉ dev, chặn cứng kể cả admin
    }
    if (in_array($quyen, QUYEN_THEO_VAI_TRO[$vt] ?? [], true)) {
        return true;
    }
    // Quyền được ủy riêng — chỉ những quyền nằm trong danh sách cho phép ủy
    return isset(QUYEN_CO_THE_UY[$quyen])
        && in_array($quyen, quyen_cap_rieng((int)$nd['id']), true);
}

/** Người dùng có được xem/nhập số liệu của khoa này không? */
function co_quyen_voi_khoa(int $idKhoa): bool
{
    $nd = nguoi_dung_hien_tai();
    if (!$nd) {
        return false;
    }
    if (co_quyen('solieu.xem_tat_ca')) {
        return true;    // dev và admin thấy mọi khoa
    }
    foreach ($nd['khoa'] as $k) {
        if ((int)$k['id'] === $idKhoa) {
            return true;
        }
    }
    return false;
}

/** Danh sách id khoa mà người dùng được thao tác. null = tất cả. */
function cac_khoa_duoc_phep(): ?array
{
    $nd = nguoi_dung_hien_tai();
    if (!$nd) {
        return [];
    }
    if (co_quyen('solieu.xem_tat_ca')) {
        return null;
    }
    return array_map(fn($k) => (int)$k['id'], $nd['khoa']);
}

/* ------------------------------------------------------------
 * Chặn truy cập
 * ---------------------------------------------------------- */

function bat_buoc_dang_nhap(): array
{
    $nd = nguoi_dung_hien_tai();
    if (!$nd) {
        header('Location: /dang-nhap.php?tiep=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
        exit;
    }
    $trangHienTai = basename($_SERVER['SCRIPT_NAME'] ?? '');

    // Chế độ bảo trì: chặn theo mức. Mức 1 cho admin + whitelist vào; mức 2
    // (khóa cứng) chỉ dev vào. Vẫn cho vào trang đăng xuất để họ thoát ra được.
    if (function_exists('dang_bao_tri') && dang_bao_tri()
        && !bao_tri_duoc_vao($nd)
        && $trangHienTai !== 'dang-xuat.php') {
        trang_bao_tri();   // render + exit
    }

    // Buộc đổi mật khẩu lần đầu / sau khi được cấp lại
    if ((int)$nd['doi_mat_khau'] === 1
        && !in_array($trangHienTai, ['doi-mat-khau.php', 'dang-xuat.php'], true)) {
        header('Location: /doi-mat-khau.php?bat_buoc=1');
        exit;
    }
    return $nd;
}

function bat_buoc_quyen(string $quyen): array
{
    $nd = bat_buoc_dang_nhap();
    if (!co_quyen($quyen)) {
        ghi_nhat_ky('TU_CHOI_TRUY_CAP', $quyen, 'Truy cập ' . ($_SERVER['REQUEST_URI'] ?? ''));
        http_response_code(403);
        require __DIR__ . '/../_403.php';
        exit;
    }
    return $nd;
}

/* ------------------------------------------------------------
 * Đăng nhập / đăng xuất
 * ---------------------------------------------------------- */

/**
 * @return array{ok:bool, loi?:string, nguoi_dung?:array}
 */
function dang_nhap(string $tenDangNhap, string $matKhau): array
{
    bat_dau_phien();
    $tenDangNhap = trim($tenDangNhap);

    $nd = q1('SELECT * FROM nguoi_dung WHERE ten_dang_nhap = ?', [$tenDangNhap]);

    // Luôn tốn thời gian băm tương đương để không lộ tài khoản nào có thật
    if (!$nd) {
        password_verify($matKhau, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30M1p1DkzF0Ba');
        ghi_nhat_ky_tho(null, $tenDangNhap, 'DANG_NHAP_THAT_BAI', 'Sai tên đăng nhập');
        return ['ok' => false, 'loi' => 'Tên đăng nhập hoặc mật khẩu không đúng.'];
    }

    if ((int)$nd['hoat_dong'] !== 1) {
        ghi_nhat_ky_tho($nd['id'], $tenDangNhap, 'DANG_NHAP_THAT_BAI', 'Tài khoản bị vô hiệu hóa');
        return ['ok' => false, 'loi' => 'Tài khoản đã bị vô hiệu hóa. Liên hệ quản trị viên.'];
    }

    if ($nd['khoa_den'] !== null && strtotime($nd['khoa_den']) > time()) {
        $con = (int)ceil((strtotime($nd['khoa_den']) - time()) / 60);
        return ['ok' => false, 'loi' => "Tài khoản đang bị tạm khóa. Thử lại sau {$con} phút."];
    }

    if (!password_verify($matKhau, $nd['mat_khau_hash'])) {
        $soLanSai = (int)$nd['so_lan_sai'] + 1;
        $khoaDen  = null;
        if ($soLanSai >= SO_LAN_SAI_TOI_DA) {
            $khoaDen  = date('Y-m-d H:i:s', time() + PHUT_KHOA_TAM * 60);
            $soLanSai = 0;
        }
        q('UPDATE nguoi_dung SET so_lan_sai = ?, khoa_den = ? WHERE id = ?',
            [$soLanSai, $khoaDen, $nd['id']]);
        ghi_nhat_ky_tho($nd['id'], $tenDangNhap, 'DANG_NHAP_THAT_BAI', 'Sai mật khẩu');

        if ($khoaDen) {
            return ['ok' => false, 'loi' => 'Sai mật khẩu quá số lần cho phép. Tài khoản bị tạm khóa '
                . PHUT_KHOA_TAM . ' phút.'];
        }
        $conLai = SO_LAN_SAI_TOI_DA - $soLanSai;
        return ['ok' => false, 'loi' => "Tên đăng nhập hoặc mật khẩu không đúng. Còn {$conLai} lần thử."];
    }

    // Nâng cấp thuật toán băm nếu tham số mặc định của PHP đã thay đổi
    if (password_needs_rehash($nd['mat_khau_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE nguoi_dung SET mat_khau_hash = ? WHERE id = ?',
            [password_hash($matKhau, PASSWORD_DEFAULT), $nd['id']]);
    }

    session_regenerate_id(true);   // chống session fixation
    $_SESSION['id_nguoi_dung'] = (int)$nd['id'];
    $_SESSION['thao_tac_cuoi'] = time();

    q('UPDATE nguoi_dung SET so_lan_sai = 0, khoa_den = NULL, lan_dang_nhap_cuoi = CURRENT_TIMESTAMP
       WHERE id = ?', [$nd['id']]);
    ghi_nhat_ky_tho($nd['id'], $tenDangNhap, 'DANG_NHAP', 'Thành công');

    return ['ok' => true, 'nguoi_dung' => $nd];
}

function dang_xuat(): void
{
    bat_dau_phien();
    $nd = $_SESSION['id_nguoi_dung'] ?? null;
    if ($nd) {
        ghi_nhat_ky_tho((int)$nd, null, 'DANG_XUAT', null);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ------------------------------------------------------------
 * Mật khẩu
 * ---------------------------------------------------------- */

/**
 * Đếm ký tự và chuyển chữ thường có dấu tiếng Việt.
 * Không phụ thuộc phần mở rộng mbstring — một số máy chủ miễn phí không bật.
 */
function do_dai_chuoi(string $s): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($s, 'UTF-8')
        : count(preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: []);
}

/**
 * Hạ chữ thường.
 *
 * strtolower() chỉ xử lý được chữ ASCII: "Đã khóa" giữ nguyên chữ Đ hoa,
 * câu ghép lại thành "kỳ Đã khóa". Nên khi thiếu mbstring phải tự đối chiếu
 * bảng chữ hoa tiếng Việt.
 */
function chu_thuong(string $s): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($s, 'UTF-8');
    }
    static $hoa = 'ÀÁÂÃÈÉÊÌÍÒÓÔÕÙÚĂĐĨŨƠƯÝ'
        . 'ẠẢẤẦẨẪẬẮẰẲẴẶẸẺẼẾỀỂỄỆỈỊỌỎỐỒỔỖỘỚỜỞỠỢỤỦỨỪỬỮỰỲỴỶỸ';
    static $thuong = 'àáâãèéêìíòóôõùúăđĩũơưý'
        . 'ạảấầẩẫậắằẳẵặẹẻẽếềểễệỉịọỏốồổỗộớờởỡợụủứừửữựỳỵỷỹ';

    $a = preg_split('//u', $hoa, -1, PREG_SPLIT_NO_EMPTY);
    $b = preg_split('//u', $thuong, -1, PREG_SPLIT_NO_EMPTY);
    if (count($a) !== count($b)) {
        return strtolower($s);   // bảng lệch thì thà không đổi còn hơn đổi sai
    }
    return strtr(strtolower($s), array_combine($a, $b));
}

function chu_hoa(string $s): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
}

/** Trả về chuỗi lỗi, hoặc null nếu mật khẩu hợp lệ. */
function kiem_tra_mat_khau(string $mk): ?string
{
    if (do_dai_chuoi($mk) < 8) {
        return 'Mật khẩu phải có ít nhất 8 ký tự.';
    }
    if (!preg_match('/[A-Za-z]/', $mk) || !preg_match('/[0-9]/', $mk)) {
        return 'Mật khẩu phải có cả chữ và số.';
    }
    $de_doan = ['12345678', 'password', 'matkhau123', 'admin123', '123456789', 'qwerty123'];
    if (in_array(chu_thuong($mk), $de_doan, true)) {
        return 'Mật khẩu quá dễ đoán, vui lòng chọn mật khẩu khác.';
    }
    return null;
}

/** Sinh mật khẩu tạm để admin cấp cho người dùng mới. */
function sinh_mat_khau_tam(int $doDai = 10): string
{
    $bang = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $mk = '';
    for ($i = 0; $i < $doDai; $i++) {
        $mk .= $bang[random_int(0, strlen($bang) - 1)];
    }
    // Bảo đảm có cả chữ và số
    return $mk . random_int(10, 99);
}

/* ------------------------------------------------------------
 * Nhật ký
 * ---------------------------------------------------------- */

function ghi_nhat_ky_tho(?int $idNguoiDung, ?string $tenDangNhap,
                         string $hanhDong, ?string $chiTiet = null,
                         ?string $doiTuong = null): void
{
    try {
        q('INSERT INTO nhat_ky (id_nguoi_dung, ten_dang_nhap, hanh_dong, doi_tuong, chi_tiet, dia_chi_ip)
           VALUES (?,?,?,?,?,?)',
            [$idNguoiDung, $tenDangNhap, $hanhDong, $doiTuong, $chiTiet,
             $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        // Không để lỗi ghi nhật ký làm hỏng nghiệp vụ chính
    }

    // Tự dọn nhật ký cũ hơn NHAT_KY_GIU_NGAY — tối đa 1 lần/request và 1 lần/ngày
    // (không cần cron). Cờ tĩnh tránh chạy lặp vì cai_dat_lay cache trong 1 request.
    static $daThuDon = false;
    if (!$daThuDon && function_exists('cai_dat_lay')) {
        $daThuDon = true;
        try {
            $homNay = date('Y-m-d');
            if (cai_dat_lay('nhat_ky_don_ngay') !== $homNay) {
                cai_dat_dat('nhat_ky_don_ngay', $homNay);   // đặt cờ trước để không lặp
                don_nhat_ky_cu(defined('NHAT_KY_GIU_NGAY') ? NHAT_KY_GIU_NGAY : 30);
            }
        } catch (Throwable $e) {
            // dọn log là việc phụ, lỗi thì bỏ qua
        }
    }
}

/** Xóa nhật ký cũ hơn $ngay ngày. Trả về số dòng đã xóa. Portable MySQL + SQLite. */
function don_nhat_ky_cu(int $ngay = 30): int
{
    $moc = date('Y-m-d H:i:s', strtotime("-$ngay days"));
    try {
        return q('DELETE FROM nhat_ky WHERE thoi_diem < ?', [$moc])->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

function ghi_nhat_ky(string $hanhDong, ?string $doiTuong = null, ?string $chiTiet = null): void
{
    $nd = nguoi_dung_hien_tai();
    ghi_nhat_ky_tho($nd['id'] ?? null, $nd['ten_dang_nhap'] ?? null,
        $hanhDong, $chiTiet, $doiTuong);
}

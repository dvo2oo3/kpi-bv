<?php
/**
 * Sao lưu toàn bộ dữ liệu.
 *
 * InfinityFree không cam kết giữ dữ liệu và không có backup tự động,
 * nên đây là bản sao duy nhất. Nên tải về mỗi tháng một lần.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';

$toi = bat_buoc_quyen('sao_luu.tai_ve');

const BANG_SAO_LUU = ['khoa', 'nguoi_dung', 'nguoi_dung_khoa', 'chi_tieu',
    'chi_tieu_ap_dung', 'ke_hoach', 'ke_hoach_thang', 'ky', 'so_lieu',
    'dieu_chinh', 'nhat_ky'];

if (($_GET['tai'] ?? '') === 'sql') {
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="qlbv-'
        . date('Y-m-d-His') . '.sql"');

    echo "-- Sao lưu " . TEN_UNG_DUNG . "\n";
    echo "-- " . TEN_DON_VI . "\n";
    echo "-- Thời điểm: " . date('d/m/Y H:i:s') . "\n";
    echo "-- Người thực hiện: {$toi['ten_dang_nhap']}\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach (BANG_SAO_LUU as $bang) {
        try {
            $dong = qAll("SELECT * FROM $bang");
        } catch (Throwable $e) {
            continue;   // bảng chưa tồn tại thì bỏ qua
        }
        echo "-- ---------- $bang (" . count($dong) . " dòng) ----------\n";
        echo "DELETE FROM `$bang`;\n";
        foreach ($dong as $r) {
            $cot = array_map(fn($c) => "`$c`", array_keys($r));
            $val = array_map(function ($v) {
                if ($v === null) {
                    return 'NULL';
                }
                return db()->quote((string)$v);
            }, array_values($r));
            echo "INSERT INTO `$bang` (" . implode(',', $cot) . ") VALUES ("
               . implode(',', $val) . ");\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    ghi_nhat_ky('SAO_LUU', 'toan_bo', 'Tải file SQL');
    exit;
}

/* ---------- Xuất RIÊNG thư viện chỉ tiêu (JSON, để chép sang máy khác) ---------- */
if (($_GET['tai'] ?? '') === 'thu_vien') {
    $tenTheoId = [];
    foreach (qAll('SELECT id, ma FROM chi_tieu') as $r) { $tenTheoId[(int)$r['id']] = $r['ma']; }
    $apDung = [];
    foreach (qAll('SELECT ap.id_chi_tieu, k.ma FROM chi_tieu_ap_dung ap JOIN khoa k ON k.id = ap.id_khoa') as $r) {
        $apDung[(int)$r['id_chi_tieu']][] = $r['ma'];
    }
    $ds = [];
    foreach (qAll('SELECT * FROM chi_tieu ORDER BY thu_tu, id') as $r) {
        $id = (int)$r['id'];
        $ds[] = [
            'ma' => $r['ma'], 'ten' => $r['ten'], 'don_vi' => $r['don_vi'],
            'cha_ma' => $r['id_cha'] !== null ? ($tenTheoId[(int)$r['id_cha']] ?? null) : null,
            'thu_tu' => (int)$r['thu_tu'], 'loai_gia_tri' => $r['loai_gia_tri'],
            'nguon' => $r['nguon'], 'huong' => $r['huong'], 'phan_bo' => $r['phan_bo'],
            'phep_tinh' => $r['phep_tinh'], 'ct_tu' => $r['ct_tu'], 'ct_mau' => $r['ct_mau'],
            'nhan_so_ngay' => (int)$r['nhan_so_ngay'], 'la_chuan' => (int)$r['la_chuan'],
            'gop_vao' => $r['gop_vao'], 'mo_ta' => $r['mo_ta'] ?? null,
            'hoat_dong' => (int)$r['hoat_dong'], 'khoa' => $apDung[$id] ?? [],
        ];
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="thu-vien-chi-tieu-' . date('Y-m-d') . '.json"');
    echo json_encode(['loai' => 'thu_vien_chi_tieu', 'thoi_diem' => date('c'),
        'so_chi_tieu' => count($ds), 'chi_tieu' => $ds], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    ghi_nhat_ky('XUAT_THU_VIEN', 'json', count($ds) . ' chỉ tiêu');
    exit;
}

/* ---------- Xuất RIÊNG SỐ LIỆU ĐÃ NHẬP (thực hiện + kế hoạch) để ĐẨY LÊN host ----------
   Dùng INSERT ... ON DUPLICATE KEY UPDATE và định vị theo MÃ khoa + MÃ chỉ tiêu:
   chạy trên host chỉ MERGE (thêm mới / cập nhật) — KHÔNG xóa dữ liệu đang có. */
if (($_GET['tai'] ?? '') === 'so_lieu') {
    $nam = (int)($_GET['nam'] ?? 0);   // 0 = mọi năm
    $dkNam = $nam ? " AND s.nam = $nam" : '';
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="qlbv-solieu-'
        . ($nam ?: 'tatca') . '-' . date('Y-m-d-His') . '.sql"');
    echo "-- Số liệu đã nhập (thực hiện + kế hoạch)" . ($nam ? " năm $nam" : ' — mọi năm') . "\n";
    echo "-- ĐẨY LÊN host: chạy file này trong phpMyAdmin. Chỉ MERGE (thêm/cập nhật),\n";
    echo "-- KHÔNG xóa dữ liệu host. Định vị theo mã nên khớp dù id local/host khác nhau.\n";
    echo "SET NAMES utf8mb4;\n\n";
    $q = fn($v) => $v === null ? 'NULL' : (0 + $v);

    $sl = qAll("SELECT s.nam, s.thang, k.ma AS kma, c.ma AS cma, s.gia_tri, s.la_chien_dich, s.ghi_chu
                  FROM so_lieu s JOIN khoa k ON k.id = s.id_khoa JOIN chi_tieu c ON c.id = s.id_chi_tieu
                 WHERE 1=1$dkNam");
    echo "-- so_lieu (" . count($sl) . " dòng)\n";
    foreach ($sl as $r) {
        echo "INSERT INTO so_lieu (nam,thang,id_khoa,id_chi_tieu,gia_tri,la_chien_dich,ghi_chu) VALUES ("
           . (int)$r['nam'] . "," . (int)$r['thang']
           . ",(SELECT id FROM khoa WHERE ma=" . db()->quote($r['kma']) . ")"
           . ",(SELECT id FROM chi_tieu WHERE ma=" . db()->quote($r['cma']) . "),"
           . $q($r['gia_tri']) . "," . (int)$r['la_chien_dich'] . ","
           . ($r['ghi_chu'] === null ? 'NULL' : db()->quote($r['ghi_chu']))
           . ") ON DUPLICATE KEY UPDATE gia_tri=VALUES(gia_tri),la_chien_dich=VALUES(la_chien_dich),ghi_chu=VALUES(ghi_chu);\n";
    }

    $dkNamK = $nam ? " AND p.nam = $nam" : '';
    $kh = qAll("SELECT p.nam, k.ma AS kma, c.ma AS cma, p.chi_tieu_giao, p.chi_tieu_nang_luc, p.th_nam_truoc
                  FROM ke_hoach p JOIN khoa k ON k.id = p.id_khoa JOIN chi_tieu c ON c.id = p.id_chi_tieu
                 WHERE 1=1$dkNamK");
    echo "\n-- ke_hoach (" . count($kh) . " dòng)\n";
    foreach ($kh as $r) {
        echo "INSERT INTO ke_hoach (nam,id_khoa,id_chi_tieu,chi_tieu_giao,chi_tieu_nang_luc,th_nam_truoc) VALUES ("
           . (int)$r['nam']
           . ",(SELECT id FROM khoa WHERE ma=" . db()->quote($r['kma']) . ")"
           . ",(SELECT id FROM chi_tieu WHERE ma=" . db()->quote($r['cma']) . "),"
           . $q($r['chi_tieu_giao']) . "," . $q($r['chi_tieu_nang_luc']) . "," . $q($r['th_nam_truoc'])
           . ") ON DUPLICATE KEY UPDATE chi_tieu_giao=VALUES(chi_tieu_giao),chi_tieu_nang_luc=VALUES(chi_tieu_nang_luc),th_nam_truoc=VALUES(th_nam_truoc);\n";
    }
    ghi_nhat_ky('XUAT_SO_LIEU', $nam ? (string)$nam : 'tatca',
        count($sl) . ' số liệu + ' . count($kh) . ' kế hoạch (để đẩy lên host)');
    exit;
}

/* ---------- Xuất số liệu đã nhập dạng JSON (để NHẬP LẠI ngay trong app) ---------- */
if (($_GET['tai'] ?? '') === 'so_lieu_json') {
    $nam = (int)($_GET['nam'] ?? 0);
    $dk  = $nam ? " AND s.nam = $nam" : '';
    $dkk = $nam ? " AND p.nam = $nam" : '';
    $sl = qAll("SELECT s.nam, s.thang, k.ma AS kma, c.ma AS cma, s.gia_tri, s.la_chien_dich, s.ghi_chu
                  FROM so_lieu s JOIN khoa k ON k.id=s.id_khoa JOIN chi_tieu c ON c.id=s.id_chi_tieu WHERE 1=1$dk");
    $kh = qAll("SELECT p.nam, k.ma AS kma, c.ma AS cma, p.chi_tieu_giao, p.chi_tieu_nang_luc, p.th_nam_truoc
                  FROM ke_hoach p JOIN khoa k ON k.id=p.id_khoa JOIN chi_tieu c ON c.id=p.id_chi_tieu WHERE 1=1$dkk");
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="qlbv-solieu-'
        . ($nam ?: 'tatca') . '-' . date('Y-m-d') . '.json"');
    echo json_encode(['loai' => 'so_lieu_nhap', 'thoi_diem' => date('c'), 'nam' => $nam ?: null,
        'so_lieu' => $sl, 'ke_hoach' => $kh], JSON_UNESCAPED_UNICODE);
    ghi_nhat_ky('XUAT_SO_LIEU', 'json', count($sl) . ' số liệu + ' . count($kh) . ' kế hoạch');
    exit;
}

/* ---------- NHẬP số liệu từ file .json (MERGE an toàn — không xóa gì) ---------- */
if (la_post() && post('viec') === 'nhap_so_lieu') {
    kiem_tra_csrf();
    $tep = $_FILES['tep'] ?? null;
    if (!$tep || ($tep['error'] ?? 1) !== UPLOAD_ERR_OK) {
        nhan_tin('loi', 'Chưa chọn file .json hợp lệ (hoặc file quá lớn).');
        chuyen_huong('/sao-luu.php');
    }
    $data = json_decode((string)file_get_contents($tep['tmp_name']), true);
    if (!is_array($data) || ($data['loai'] ?? '') !== 'so_lieu_nhap') {
        nhan_tin('loi', 'File không đúng — cần file .json xuất từ chính tab "Số liệu đã nhập".');
        chuyen_huong('/sao-luu.php');
    }
    $khoaId = []; foreach (qAll('SELECT id, ma FROM khoa')     as $r) { $khoaId[$r['ma']] = (int)$r['id']; }
    $ctId   = []; foreach (qAll('SELECT id, ma FROM chi_tieu') as $r) { $ctId[$r['ma']]   = (int)$r['id']; }
    $themSL = 0; $suaSL = 0; $themKH = 0; $suaKH = 0; $bo = 0;
    db()->beginTransaction();
    foreach ($data['so_lieu'] ?? [] as $r) {
        $ik = $khoaId[$r['kma'] ?? ''] ?? null; $ic = $ctId[$r['cma'] ?? ''] ?? null;
        if (!$ik || !$ic) { $bo++; continue; }
        $nam = (int)$r['nam']; $th = (int)$r['thang'];
        $gt = $r['gia_tri'] ?? null; $lcd = (int)($r['la_chien_dich'] ?? 0); $gc = $r['ghi_chu'] ?? null;
        if (qVal('SELECT 1 FROM so_lieu WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?', [$nam,$th,$ik,$ic])) {
            q('UPDATE so_lieu SET gia_tri=?, la_chien_dich=?, ghi_chu=? WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
                [$gt,$lcd,$gc,$nam,$th,$ik,$ic]); $suaSL++;
        } else {
            q('INSERT INTO so_lieu (nam,thang,id_khoa,id_chi_tieu,gia_tri,la_chien_dich,ghi_chu) VALUES (?,?,?,?,?,?,?)',
                [$nam,$th,$ik,$ic,$gt,$lcd,$gc]); $themSL++;
        }
    }
    foreach ($data['ke_hoach'] ?? [] as $r) {
        $ik = $khoaId[$r['kma'] ?? ''] ?? null; $ic = $ctId[$r['cma'] ?? ''] ?? null;
        if (!$ik || !$ic) { $bo++; continue; }
        $nam = (int)$r['nam'];
        if (qVal('SELECT 1 FROM ke_hoach WHERE nam=? AND id_khoa=? AND id_chi_tieu=?', [$nam,$ik,$ic])) {
            q('UPDATE ke_hoach SET chi_tieu_giao=?, chi_tieu_nang_luc=?, th_nam_truoc=? WHERE nam=? AND id_khoa=? AND id_chi_tieu=?',
                [$r['chi_tieu_giao'] ?? null, $r['chi_tieu_nang_luc'] ?? null, $r['th_nam_truoc'] ?? null, $nam,$ik,$ic]); $suaKH++;
        } else {
            q('INSERT INTO ke_hoach (nam,id_khoa,id_chi_tieu,chi_tieu_giao,chi_tieu_nang_luc,th_nam_truoc) VALUES (?,?,?,?,?,?)',
                [$nam,$ik,$ic,$r['chi_tieu_giao'] ?? null, $r['chi_tieu_nang_luc'] ?? null, $r['th_nam_truoc'] ?? null]); $themKH++;
        }
    }
    db()->commit();
    ghi_nhat_ky('NHAP_SO_LIEU', 'json', "SL thêm $themSL/sửa $suaSL, KH thêm $themKH/sửa $suaKH, bỏ $bo");
    nhan_tin('ok', "Đã nhập số liệu: thực hiện (thêm $themSL, cập nhật $suaSL), "
        . "kế hoạch (thêm $themKH, cập nhật $suaKH)."
        . ($bo ? " Bỏ qua $bo dòng do mã khoa/chỉ tiêu không có trên máy này." : ''));
    chuyen_huong('/sao-luu.php');
}

/* Tách một chuỗi .sql thành từng câu lệnh, tôn trọng chuỗi trong nháy đơn
   (cả kiểu '' của SQLite lẫn \' của MySQL) và bỏ chú thích "-- …". */
function tach_lenh_sql(string $sql): array
{
    $lenh = []; $cur = ''; $n = strlen($sql); $trongChuoi = false;
    for ($i = 0; $i < $n; $i++) {
        $c = $sql[$i];
        if ($trongChuoi) {
            $cur .= $c;
            if ($c === '\\' && $i + 1 < $n) { $cur .= $sql[++$i]; continue; }   // \' \\ (MySQL)
            if ($c === "'") {
                if ($i + 1 < $n && $sql[$i + 1] === "'") { $cur .= $sql[++$i]; continue; }   // '' (SQLite)
                $trongChuoi = false;
            }
            continue;
        }
        if ($c === "'") { $trongChuoi = true; $cur .= $c; continue; }
        if ($c === '-' && $i + 1 < $n && $sql[$i + 1] === '-') {   // chú thích tới hết dòng
            while ($i < $n && $sql[$i] !== "\n") { $i++; }
            continue;
        }
        if ($c === ';') { $lenh[] = $cur; $cur = ''; continue; }
        $cur .= $c;
    }
    if (trim($cur) !== '') { $lenh[] = $cur; }
    return $lenh;
}

/* Tách danh sách giá trị trong VALUES(...) thành mảng, GỠ escape của cả hai
   kiểu: '' (SQLite) và \' \n \\ … (MySQL). Trả về giá trị thật (chuỗi/số/null). */
function tach_gia_tri_sql(string $s): array
{
    $vals = []; $cur = ''; $n = strlen($s); $trongChuoi = false; $laChuoi = false;
    $esc = ['n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", '\\' => '\\', "'" => "'", '"' => '"', 'b' => "\x08", 'Z' => "\x1a"];
    for ($i = 0; $i < $n; $i++) {
        $c = $s[$i];
        if ($trongChuoi) {
            if ($c === '\\' && $i + 1 < $n) { $nx = $s[$i + 1]; $cur .= $esc[$nx] ?? $nx; $i++; continue; }
            if ($c === "'") {
                if ($i + 1 < $n && $s[$i + 1] === "'") { $cur .= "'"; $i++; continue; }
                $trongChuoi = false; continue;
            }
            $cur .= $c; continue;
        }
        if ($c === "'") { $trongChuoi = true; $laChuoi = true; continue; }
        if ($c === ',') {
            $vals[] = $laChuoi ? $cur : _gt_thuong($cur); $cur = ''; $laChuoi = false; continue;
        }
        $cur .= $c;
    }
    $vals[] = $laChuoi ? $cur : _gt_thuong($cur);
    return $vals;
}
function _gt_thuong(string $raw)
{
    $t = trim($raw);
    if ($t === '' || strcasecmp($t, 'NULL') === 0) { return null; }
    return $t;   // số (chèn qua prepared, kiểu tự khớp)
}

/* Chạy MỘT câu lệnh của bản sao lưu, không phụ thuộc dialect:
   - INSERT → parse cột + giá trị rồi chèn lại bằng PREPARED (PDO tự escape cho DB đích).
   - Còn lại (DELETE…) → chạy thẳng.
   Nhờ vậy khôi phục được cả file MySQL (\' ) lẫn SQLite ('') trên bất kỳ DB nào. */
/** Danh sách cột thật của một bảng (cache theo bảng). Rỗng nếu không đọc được. */
function cot_cua_bang(PDO $db, string $bang): array
{
    static $cache = [];
    if (!array_key_exists($bang, $cache)) {
        $ds = [];
        try {
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                foreach ($db->query("PRAGMA table_info(`$bang`)") as $r) { $ds[] = $r['name']; }
            } else {
                foreach ($db->query("SHOW COLUMNS FROM `$bang`") as $r) { $ds[] = $r['Field']; }
            }
        } catch (\Throwable $e) {
            $ds = [];   // không đọc được → không lọc (giữ nguyên hành vi cũ)
        }
        $cache[$bang] = $ds;
    }
    return $cache[$bang];
}

function chay_lenh_backup(PDO $db, string $l): void
{
    $l = trim($l);
    if ($l === '') { return; }
    if (!preg_match('/^INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?\s*\(([^)]*)\)\s*VALUES\s*\((.*)\)\s*$/is', $l, $m)) {
        $db->exec($l);   // DELETE / lệnh khác
        return;
    }
    $bang = $m[1];
    $cols = array_map(fn($c) => trim($c, " `\t\r\n"), explode(',', $m[2]));
    $vals = tach_gia_tri_sql($m[3]);
    if (count($cols) !== count($vals)) { $db->exec($l); return; }   // dự phòng

    // Bỏ qua cột không tồn tại ở bảng đích (vd host cũ chưa có cột mo_ta).
    $tonTai = cot_cua_bang($db, $bang);
    if ($tonTai) {
        $gcol = []; $gval = [];
        foreach ($cols as $i => $c) {
            if (in_array($c, $tonTai, true)) { $gcol[] = $c; $gval[] = $vals[$i]; }
        }
        if (!$gcol) { return; }   // không còn cột nào khớp → bỏ dòng
        $cols = $gcol; $vals = $gval;
    }

    $colSql = implode(',', array_map(fn($c) => '`' . $c . '`', $cols));
    $ph = implode(',', array_fill(0, count($cols), '?'));
    $st = $db->prepare("INSERT INTO `$bang` ($colSql) VALUES ($ph)");
    $st->execute($vals);
}

$laSqlite = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

if (la_post()) {
    kiem_tra_csrf();
    $viec = post('viec');

    /* ---------- KHÔI PHỤC toàn bộ từ file .sql tải lên (chỉ DEV) ---------- */
    if ($viec === 'phuc_hoi' && co_quyen('he_thong.reset')) {
        $tep = $_FILES['file_sql'] ?? null;
        if (trim((string)post('xac_nhan')) !== 'KHOIPHUC') {
            nhan_tin('loi', 'Phải gõ đúng chữ KHOIPHUC (in hoa, không dấu cách) để xác nhận.');
        } elseif (!$tep || ($tep['error'] ?? 1) !== UPLOAD_ERR_OK) {
            nhan_tin('loi', 'Chưa chọn file .sql hợp lệ (hoặc file quá lớn so với giới hạn máy chủ).');
        } else {
            $sql = file_get_contents($tep['tmp_name']);
            if ($sql === false || trim($sql) === '') {
                nhan_tin('loi', 'File rỗng hoặc không đọc được.');
            } elseif (stripos($sql, 'INSERT INTO') === false) {
                nhan_tin('loi', 'File không giống bản sao lưu (không thấy câu INSERT nào).');
            } else {
                $ok = 0; $loi = null;
                // Giữ nguyên tài khoản đang dùng: bỏ qua các câu lệnh đụng bảng người dùng.
                $giuTaiKhoan = trim((string)post('giu_tai_khoan')) === '1';
                $bangBoQua = ['nguoi_dung', 'nguoi_dung_khoa'];
                // SQLite: PRAGMA foreign_keys chỉ đổi được NGOÀI transaction → đặt trước.
                if ($laSqlite) { db()->exec('PRAGMA foreign_keys = OFF'); }
                try {
                    db()->beginTransaction();
                    if (!$laSqlite) { db()->exec('SET FOREIGN_KEY_CHECKS = 0'); }
                    foreach (tach_lenh_sql($sql) as $l) {
                        $l = trim($l);
                        if ($l === '' || preg_match('/^SET\s/i', $l)) { continue; }
                        if ($giuTaiKhoan
                            && preg_match('/^(?:DELETE\s+FROM|INSERT\s+INTO)\s+`?([A-Za-z0-9_]+)`?/i', $l, $mb)
                            && in_array(strtolower($mb[1]), $bangBoQua, true)) {
                            continue; // giữ lại tài khoản hiện tại
                        }
                        chay_lenh_backup(db(), $l); $ok++;
                    }
                    if (!$laSqlite) { db()->exec('SET FOREIGN_KEY_CHECKS = 1'); }
                    db()->commit();
                } catch (Throwable $e) {
                    if (db()->inTransaction()) { db()->rollBack(); }
                    $loi = $e->getMessage();
                }
                if ($laSqlite) { db()->exec('PRAGMA foreign_keys = ON'); }
                if ($loi !== null) {
                    nhan_tin('loi', 'Khôi phục thất bại (đã hoàn tác, dữ liệu giữ nguyên): '
                        . substr($loi, 0, 300));
                } elseif ($giuTaiKhoan) {
                    ghi_nhat_ky('KHOI_PHUC', 'toan_bo', "$ok câu lệnh (giữ tài khoản)");
                    nhan_tin('ok', "Đã khôi phục dữ liệu ($ok câu lệnh). "
                        . 'Giữ nguyên tài khoản hiện tại — không cần đăng nhập lại.');
                    chuyen_huong('/sao-luu.php');
                } else {
                    ghi_nhat_ky('KHOI_PHUC', 'toan_bo', "$ok câu lệnh");
                    // Có thể tài khoản hiện tại đã đổi/không còn → đăng nhập lại cho chắc.
                    dang_xuat();
                    $_SESSION['tin_nhan'][] = ['loai' => 'ok',
                        'noi_dung' => "Đã khôi phục dữ liệu ($ok câu lệnh). Hãy đăng nhập lại."];
                    chuyen_huong('/dang-nhap.php');
                }
            }
        }
        chuyen_huong('/sao-luu.php');
    }

    /* ---------- NHẬP thư viện từ file JSON (chỉ thêm/cập nhật chỉ tiêu, KHÔNG đụng số liệu) ---------- */
    if ($viec === 'nhap_thu_vien' && co_quyen('chitieu.xoa')) {
        $tep = $_FILES['file_tv'] ?? null;
        if (!$tep || ($tep['error'] ?? 1) !== UPLOAD_ERR_OK) {
            nhan_tin('loi', 'Chưa chọn file .json hợp lệ.');
            chuyen_huong('/sao-luu.php');
        }
        $data = json_decode((string)file_get_contents($tep['tmp_name']), true);
        if (!is_array($data) || ($data['loai'] ?? '') !== 'thu_vien_chi_tieu' || empty($data['chi_tieu'])) {
            nhan_tin('loi', 'File không phải bản xuất thư viện hợp lệ.');
            chuyen_huong('/sao-luu.php');
        }
        $idKhoa = [];
        foreach (qAll('SELECT id, ma FROM khoa') as $r) { $idKhoa[$r['ma']] = (int)$r['id']; }
        $them = 0; $capNhat = 0; $khoaBoQua = 0;
        require_once __DIR__ . '/app/danh_muc.php';   // la_he_thong()
        db()->beginTransaction();
        // Vòng 1: thêm/cập nhật từng chỉ tiêu theo MÃ
        foreach ($data['chi_tieu'] as $c) {
            $ma = (string)($c['ma'] ?? ''); if ($ma === '') { continue; }
            $cu = q1('SELECT id, ma FROM chi_tieu WHERE ma = ?', [$ma]);
            if ($cu) {
                if (la_he_thong($ma)) {
                    // Chỉ tiêu hệ thống: chỉ cập nhật chữ hiển thị, giữ nguyên cách tính
                    q('UPDATE chi_tieu SET ten=?, don_vi=?, mo_ta=? WHERE id=?',
                        [$c['ten'], $c['don_vi'], $c['mo_ta'] ?? null, (int)$cu['id']]);
                } else {
                    q('UPDATE chi_tieu SET ten=?, don_vi=?, thu_tu=?, loai_gia_tri=?, nguon=?, huong=?,
                         phan_bo=?, phep_tinh=?, ct_tu=?, ct_mau=?, nhan_so_ngay=?, la_chuan=?, gop_vao=?,
                         mo_ta=?, hoat_dong=? WHERE id=?',
                        [$c['ten'], $c['don_vi'], (int)($c['thu_tu'] ?? 0), $c['loai_gia_tri'], $c['nguon'],
                         $c['huong'], $c['phan_bo'], $c['phep_tinh'] ?? null, $c['ct_tu'] ?? null,
                         $c['ct_mau'] ?? null, (int)($c['nhan_so_ngay'] ?? 0), (int)($c['la_chuan'] ?? 1),
                         $c['gop_vao'] ?? null, $c['mo_ta'] ?? null, (int)($c['hoat_dong'] ?? 1), (int)$cu['id']]);
                }
                $capNhat++;
            } else {
                q('INSERT INTO chi_tieu (ma, ten, don_vi, thu_tu, loai_gia_tri, nguon, huong, phan_bo,
                     phep_tinh, ct_tu, ct_mau, nhan_so_ngay, la_chuan, gop_vao, mo_ta, hoat_dong)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$ma, $c['ten'], $c['don_vi'], (int)($c['thu_tu'] ?? 0), $c['loai_gia_tri'], $c['nguon'],
                     $c['huong'], $c['phan_bo'], $c['phep_tinh'] ?? null, $c['ct_tu'] ?? null,
                     $c['ct_mau'] ?? null, (int)($c['nhan_so_ngay'] ?? 0), (int)($c['la_chuan'] ?? 1),
                     $c['gop_vao'] ?? null, $c['mo_ta'] ?? null, (int)($c['hoat_dong'] ?? 1)]);
                $them++;
            }
        }
        // Vòng 2: gán id_cha theo mã cha
        foreach ($data['chi_tieu'] as $c) {
            $idCha = !empty($c['cha_ma']) ? (int)qVal('SELECT id FROM chi_tieu WHERE ma = ?', [$c['cha_ma']]) : 0;
            q('UPDATE chi_tieu SET id_cha = ? WHERE ma = ?', [$idCha ?: null, $c['ma']]);
        }
        // Vòng 3: gán khoa áp dụng (chỉ THÊM khoa còn thiếu, không xóa gán cũ)
        foreach ($data['chi_tieu'] as $c) {
            $idCT = (int)qVal('SELECT id FROM chi_tieu WHERE ma = ?', [$c['ma']]);
            foreach ((array)($c['khoa'] ?? []) as $maK) {
                if (!isset($idKhoa[$maK])) { $khoaBoQua++; continue; }
                if (!qVal('SELECT 1 FROM chi_tieu_ap_dung WHERE id_chi_tieu=? AND id_khoa=?', [$idCT, $idKhoa[$maK]])) {
                    q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)', [$idCT, $idKhoa[$maK]]);
                }
            }
        }
        db()->commit();
        ghi_nhat_ky('NHAP_THU_VIEN', 'json', "Thêm $them · cập nhật $capNhat");
        nhan_tin('ok', "Đã nhập thư viện: thêm $them chỉ tiêu mới, cập nhật $capNhat chỉ tiêu."
            . ($khoaBoQua ? " ($khoaBoQua lượt gán khoa bỏ qua vì khoa không tồn tại bên này.)" : '')
            . ' Số liệu, kế hoạch, tài khoản KHÔNG bị đụng tới.');
        chuyen_huong('/sao-luu.php');
    }
}

$thongKe = [];
foreach (BANG_SAO_LUU as $bang) {
    try {
        $thongKe[$bang] = (int)qVal("SELECT COUNT(*) FROM $bang");
    } catch (Throwable $e) {
        $thongKe[$bang] = null;
    }
}
$lanCuoi = qVal("SELECT thoi_diem FROM nhat_ky WHERE hanh_dong = 'SAO_LUU'
                 ORDER BY thoi_diem DESC LIMIT 1");

// Tổng quan để hiện trên thẻ tải
$tongDong = 0; $soBang = 0;
foreach ($thongKe as $n) {
    if ($n !== null) { $tongDong += $n; $soBang++; }
}
$songNgay = $lanCuoi ? (int)floor((time() - strtotime($lanCuoi)) / 86400) : null;
$canSaoLuu = $songNgay === null || $songNgay >= 30;   // quá 30 ngày thì nhắc

// Đếm số liệu đã nhập (để đẩy lên host)
$slDong    = (int)qVal('SELECT COUNT(*) FROM so_lieu');
$khDong    = (int)qVal('SELECT COUNT(*) FROM ke_hoach');
$slTheoNam = qAll('SELECT nam, COUNT(*) n FROM so_lieu GROUP BY nam ORDER BY nam DESC');

mo_trang('Sao lưu dữ liệu');
?>
<div class="dau-muc">
  <div>
    <h1>Sao lưu dữ liệu</h1>
    <p class="phu">Tải toàn bộ dữ liệu về máy để giữ an toàn.</p>
  </div>
</div>

<div class="tab-phu" role="tablist">
  <button type="button" class="tab-phu-muc dang" data-tab="taive">⬇ Tải bản sao</button>
  <button type="button" class="tab-phu-muc" data-tab="solieu">⬆ Số liệu đã nhập</button>
  <?php if (co_quyen('chitieu.xoa')): ?>
    <button type="button" class="tab-phu-muc" data-tab="thuvien">Thư viện chỉ tiêu</button>
  <?php endif; ?>
  <?php if (co_quyen('he_thong.reset')): ?>
    <button type="button" class="tab-phu-muc" data-tab="khoiphuc">⚠ Khôi phục</button>
  <?php endif; ?>
</div>

<div class="tab-noi" data-tab="taive">
<!-- Thẻ tải nổi bật -->
<section class="sl-the">
  <div class="sl-icon">
    <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 3v12"/><path d="M7 11l5 4 5-4"/>
      <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
    </svg>
  </div>
  <div class="sl-the-noi">
    <h2>Tải bản sao toàn bộ hệ thống</h2>
    <p class="phu">Một file <code>.sql</code> chứa mọi bảng dữ liệu — khôi phục lại được đầy đủ khi cần.</p>
    <div class="sl-meta">
      <span><strong><?= $soBang ?></strong> bảng</span>
      <span class="sl-cham">·</span>
      <span><strong><?= so((float)$tongDong) ?></strong> dòng</span>
      <span class="sl-cham">·</span>
      <span>Gần nhất:
        <strong><?= $lanCuoi ? e(ngay_gio($lanCuoi)) : 'chưa từng' ?></strong>
        <?php if ($songNgay !== null): ?>
          <span class="phu">(<?= $songNgay === 0 ? 'hôm nay' : "cách đây $songNgay ngày" ?>)</span>
        <?php endif; ?>
      </span>
    </div>
    <?php if ($canSaoLuu): ?>
      <p class="sl-nhac">
        <?= $songNgay === null
            ? 'Chưa có bản sao nào — nên tải ngay một bản.'
            : "Đã $songNgay ngày chưa sao lưu — nên tải bản mới." ?>
      </p>
    <?php endif; ?>
  </div>
  <div class="sl-the-nut">
    <a class="nut nut-chinh sl-nut" href="?tai=sql">Tải bản sao (.sql)</a>
  </div>
</section>

<div class="tb tb-canh-bao">
  Hệ thống chạy trên hosting miễn phí — <strong>không có sao lưu tự động</strong> và
  không cam kết giữ dữ liệu. File tải về là bản sao duy nhất, nên tải mỗi tháng một lần
  sau khi khóa sổ.
</div>

<h2>Nội dung trong bản sao</h2>
<div class="sl-luoi">
  <?php foreach ($thongKe as $bang => $n): ?>
    <div class="sl-o<?= $n === null ? ' sl-o-trong' : '' ?>">
      <code><?= e($bang) ?></code>
      <span class="sl-o-so"><?= $n === null ? 'chưa có' : so((float)$n) ?></span>
    </div>
  <?php endforeach; ?>
</div>
</div><!-- /tab taive -->

<div class="tab-noi" data-tab="solieu" hidden>
<section class="the-hop">
  <h2>Số liệu đã nhập — sao lưu &amp; đẩy lên host <span class="the the-nho the-chuan">an toàn</span></h2>
  <p class="phu">
    Chỉ gồm <strong>số liệu thực hiện</strong> và <strong>chỉ tiêu giao (kế hoạch)</strong>.
    Định vị theo <em>mã khoa + mã chỉ tiêu</em> nên khớp dù id ở máy và host khác nhau.
    Khi nhập lại chỉ <strong>thêm mới / cập nhật (MERGE)</strong>, <strong>KHÔNG xóa</strong> gì.
  </p>

  <div class="sl-so">
    <div><strong><?= number_format($slDong) ?></strong> <span class="phu">dòng số liệu thực hiện</span></div>
    <div><strong><?= number_format($khDong) ?></strong> <span class="phu">dòng chỉ tiêu giao (kế hoạch)</span></div>
  </div>

  <h3 style="margin:1rem 0 .3rem">1. Tải về (sao lưu / để đẩy lên host)</h3>
  <p class="hang-nut" style="margin:.2rem 0">
    <a class="nut nut-chinh" href="?tai=so_lieu_json">⬇ Tải file (.json) — tất cả năm</a>
    <?php foreach ($slTheoNam as $r): ?>
      <a class="nut nut-phu" href="?tai=so_lieu_json&nam=<?= (int)$r['nam'] ?>">Năm <?= (int)$r['nam'] ?> (<?= number_format((int)$r['n']) ?> dòng)</a>
    <?php endforeach; ?>
  </p>
  <p class="phu" style="margin:.2rem 0">
    Muốn đẩy thẳng qua <strong>phpMyAdmin</strong> (không qua app)? Tải bản
    <a href="?tai=so_lieu">SQL — tất cả năm</a>
    <?php foreach ($slTheoNam as $r): ?>· <a href="?tai=so_lieu&nam=<?= (int)$r['nam'] ?>">SQL <?= (int)$r['nam'] ?></a><?php endforeach; ?>
    rồi Import trên phpMyAdmin.
  </p>

  <h3 style="margin:1.2rem 0 .3rem">2. Nhập lại từ file (.json)</h3>
  <p class="phu" style="margin:.2rem 0">
    Dùng khi đẩy lên host (mở app trên host, chọn file .json vừa tải) hay chép số liệu giữa các máy.
    <strong>An toàn</strong>: chỉ thêm/cập nhật, không xóa dữ liệu đang có.
  </p>
  <form method="post" enctype="multipart/form-data" class="hang-nut" style="align-items:center;gap:.6rem">
    <?= csrf_field() ?>
    <input type="hidden" name="viec" value="nhap_so_lieu">
    <input type="file" name="tep" accept=".json,application/json" required>
    <button class="nut nut-chinh" type="submit">⬆ Nhập số liệu</button>
  </form>
</section>
</div><!-- /tab solieu -->

<?php if (co_quyen('chitieu.xoa')): ?>
<div class="tab-noi" data-tab="thuvien" hidden>
<section class="the-hop">
  <h2>Chép riêng THƯ VIỆN chỉ tiêu <span class="the the-nho the-chuan">an toàn</span></h2>
  <p class="phu" style="margin-top:0">Mang bộ chỉ tiêu (kèm khoa áp dụng + mô tả) từ máy này sang máy khác.
    Khi nhập, hệ thống chỉ <strong>thêm mã mới / cập nhật mã cũ theo tên mã</strong> —
    <strong>KHÔNG</strong> đụng tới số liệu, kế hoạch, tài khoản hay kỳ.</p>
  <div class="hang-nut">
    <a class="nut nut-phu" href="?tai=thu_vien">⬇ Xuất thư viện (.json)</a>
  </div>
  <form method="post" enctype="multipart/form-data" style="margin-top:14px"
        data-xac-nhan="Nhập thư viện từ file này?&#10;Chỉ thêm/cập nhật chỉ tiêu, không xóa số liệu.">
    <?= csrf_field() ?>
    <input type="hidden" name="viec" value="nhap_thu_vien">
    <label class="o-tep">Chọn file thư viện (.json) để nhập
      <input type="file" name="file_tv" accept=".json,application/json" required>
    </label>
    <div class="form-chan">
      <button class="nut nut-chinh" type="submit">⬆ Nhập thư viện</button>
    </div>
  </form>
</section>
</div><!-- /tab thuvien -->
<?php endif; ?>

<?php if (co_quyen('he_thong.reset')): ?>
<div class="tab-noi" data-tab="khoiphuc" hidden>
<?php
// Cho người dùng THẤY rõ mức độ mất mát trước khi ghi đè.
$slCT = (int)(qVal('SELECT COUNT(*) FROM chi_tieu') ?: 0);
$slSL = (int)(qVal('SELECT COUNT(*) FROM so_lieu') ?: 0);
$slKH = (int)(qVal('SELECT COUNT(*) FROM ke_hoach') ?: 0);
?>
<section class="the-hop" style="border:2px solid var(--loi,#dc2626)">
  <h2 style="color:var(--loi,#dc2626)">⛔ Khôi phục TOÀN BỘ từ file .sql — XÓA SẠCH dữ liệu hiện tại</h2>
  <div class="tb tb-nguy">
    <p style="margin:0 0 10px"><strong>Thao tác này XÓA HẾT dữ liệu đang có rồi thay bằng nội dung file.</strong>
      Mọi <strong>chỉ tiêu người dùng đã thêm</strong> và <strong>thứ tự đã sắp xếp</strong> trên host sẽ
      <strong>biến mất</strong> nếu không có trong file. <strong>Không hoàn tác.</strong></p>
    <p style="margin:0 0 10px">Host đang có <strong><?= so((float)$slCT) ?></strong> chỉ tiêu ·
      <strong><?= so((float)$slSL) ?></strong> dòng số liệu ·
      <strong><?= so((float)$slKH) ?></strong> dòng kế hoạch — <strong>TẤT CẢ sẽ bị xóa</strong> và thay bằng file.</p>
    <p style="margin:0">💡 Chỉ muốn <strong>cập nhật danh mục chỉ tiêu</strong> mà KHÔNG mất số liệu và công sức
      sắp xếp của người dùng? → Dùng tab <strong>“Thư viện → Nhập thư viện (.json)”</strong> ở trên: nó chỉ
      <strong>gộp thêm/cập nhật</strong>, không xóa gì. Chỉ dùng “Khôi phục” khi thật sự muốn thay toàn bộ.</p>
    <p style="margin:10px 0 0">Trước khi khôi phục, bấm <strong>“Tải bản sao (.sql)”</strong> ở trên để có đường lùi.</p>
  </div>
  <form method="post" enctype="multipart/form-data" class="form-tai-khoan"
        data-xac-nhan="XÓA SẠCH dữ liệu host hiện tại (<?= so((float)$slCT) ?> chỉ tiêu, <?= so((float)$slSL) ?> số liệu, thứ tự đã sắp) và thay bằng file này?&#10;&#10;KHÔNG hoàn tác được. Chắc chắn tiếp tục?" data-xac-nhan-loai="nguy">
    <?= csrf_field() ?>
    <input type="hidden" name="viec" value="phuc_hoi">
    <label class="o-tep">Chọn file sao lưu (.sql)
      <input type="file" name="file_sql" accept=".sql,application/sql,text/plain" required>
    </label>
    <label class="o-chon" style="margin-top:10px;display:flex;gap:8px;align-items:flex-start;font-weight:600">
      <input type="checkbox" name="giu_tai_khoan" value="1" style="margin-top:3px;width:auto">
      <span>Giữ nguyên tài khoản hiện tại
        <span class="phu" style="display:block;font-weight:400">Không nạp lại danh sách người dùng/mật khẩu từ file — bạn vẫn đăng nhập bình thường sau khi khôi phục. Bỏ chọn nếu muốn ghi đè cả tài khoản.</span>
      </span>
    </label>
    <label style="margin-top:10px">Gõ <code>KHOIPHUC</code> để xác nhận
      <input type="text" name="xac_nhan" autocomplete="off" placeholder="KHOIPHUC" required
             style="max-width:210px;letter-spacing:2px;font-weight:700;text-align:center">
    </label>
    <div class="form-chan">
      <button class="nut nut-nguy" type="submit">Khôi phục (ghi đè toàn bộ)</button>
    </div>
  </form>
  <p class="phu" style="margin-top:10px">Nếu <strong>không</strong> tick “Giữ nguyên tài khoản”, xong sẽ tự đăng xuất — đăng nhập lại bằng tài khoản trong bản sao vừa khôi phục.</p>
</section>

<section class="the-hop sl-phuc-hoi">
  <h2>Cách khác: phục hồi bằng phpMyAdmin</h2>
  <ol class="huong-dan">
    <li>Vào phpMyAdmin, chạy lại <code>install/schema.sql</code> để tạo bảng (nếu DB trống).</li>
    <li>Tab <strong>Import</strong> → chọn file <code>.sql</code> đã tải → <strong>Go</strong>.</li>
    <li>Đăng nhập lại bằng tài khoản cũ, mật khẩu giữ nguyên.</li>
  </ol>
</section>
</div><!-- /tab khoiphuc -->
<?php endif; ?>

<script>
/* Tab con của trang Sao lưu — đổi tab không tải lại, nhớ tab qua URL #hash */
(function () {
  var thanh = document.querySelector('.tab-phu[role="tablist"]');
  if (!thanh) { return; }
  var noi = document.querySelectorAll('.tab-noi[data-tab]');
  function mo(t) {
    var coTab = false;
    thanh.querySelectorAll('[data-tab]').forEach(function (b) {
      var chon = b.dataset.tab === t; b.classList.toggle('dang', chon); if (chon) { coTab = true; }
    });
    if (!coTab) { t = 'taive'; thanh.querySelector('[data-tab="taive"]').classList.add('dang'); }
    noi.forEach(function (x) { x.hidden = x.dataset.tab !== t; });
  }
  thanh.addEventListener('click', function (e) {
    var b = e.target.closest('[data-tab]'); if (!b) { return; }
    mo(b.dataset.tab); history.replaceState(null, '', '#' + b.dataset.tab);
  });
  mo((location.hash || '#taive').slice(1));
})();
</script>
<?php dong_trang();

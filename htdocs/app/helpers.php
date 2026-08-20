<?php
require_once __DIR__ . '/auth.php';

/** Escape HTML — dùng cho mọi giá trị in ra trang. */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    bat_dau_phien();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function kiem_tra_csrf(): void
{
    bat_dau_phien();
    $gui = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $gui)) {
        http_response_code(419);
        trang_loi_phien();
    }
}

/** Trang báo phiên hết hạn / token sai — có nút tự xử lý, thay cho die() trơ. */
function trang_loi_phien(): void
{
    $login = da_dang_nhap() ? '/' : '/dang-nhap.php';
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Phiên đã hết hạn</title><style>'
        . ':root{color-scheme:light dark}'
        . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
        . 'font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:#f1f5f9;color:#0f172a;padding:20px}'
        . '@media(prefers-color-scheme:dark){body{background:#0f172a;color:#e2e8f0}.hop{background:#1e293b !important;border-color:#334155 !important}}'
        . '.hop{background:#fff;border:1px solid #e2e8f0;border-radius:16px;max-width:420px;width:100%;'
        . 'padding:28px 26px;box-shadow:0 10px 30px rgba(0,0,0,.12);text-align:center}'
        . '.bieu{font-size:40px;margin-bottom:8px}h1{font-size:19px;margin:6px 0 6px}'
        . 'p{margin:0 0 18px;color:#64748b;line-height:1.55;font-size:14.5px}'
        . '.nut{display:inline-block;padding:11px 18px;border-radius:10px;font-weight:600;'
        . 'text-decoration:none;font-size:14.5px;border:0;cursor:pointer;margin:4px}'
        . '.c{background:#2563eb;color:#fff}.p{background:transparent;color:#2563eb;border:1px solid #2563eb}'
        . '</style></head><body><div class="hop"><div class="bieu">⏱️</div>'
        . '<h1>Phiên làm việc đã hết hạn</h1>'
        . '<p>Yêu cầu không hợp lệ hoặc trang đã mở quá lâu. Hãy tải lại rồi thao tác lại giúp bạn nhé.</p>'
        . '<button class="nut c" onclick="location.href=location.pathname">Tải lại trang</button>'
        . '<a class="nut p" href="' . e($login) . '">' . ($login === '/' ? 'Về trang chủ' : 'Về đăng nhập') . '</a>'
        . '</div></body></html>';
    exit;
}

/* ---------- Thông báo giữa các trang ---------- */

function nhan_tin(string $loai, string $noi_dung): void
{
    bat_dau_phien();
    $_SESSION['thong_bao'][] = ['loai' => $loai, 'noi_dung' => $noi_dung];
}

function lay_thong_bao(): array
{
    bat_dau_phien();
    $tb = $_SESSION['thong_bao'] ?? [];
    unset($_SESSION['thong_bao']);
    return $tb;
}

/* ---------- Tiện ích ---------- */

function chuyen_huong(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function post(string $ten, string $mac_dinh = ''): string
{
    return trim((string)($_POST[$ten] ?? $mac_dinh));
}

function la_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function ten_vai_tro(string $ma): string
{
    return VAI_TRO[$ma] ?? $ma;
}

function ngay_gio(?string $s): string
{
    if (!$s) {
        return '—';
    }
    // SQLite (bản chạy thử ở máy cá nhân) lưu CURRENT_TIMESTAMP theo giờ UTC,
    // không có múi giờ phiên như MySQL → bù +7h để hiện đúng giờ Việt Nam.
    // MySQL trên máy chủ đã SET time_zone='+07:00' nên giữ nguyên.
    static $bu = null;
    if ($bu === null) {
        $bu = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 7 * 3600 : 0;
    }
    return date('d/m/Y H:i', strtotime($s) + $bu);
}

/** Số có dấu phân cách nghìn kiểu Việt Nam. Null hiện dấu gạch. */
function so(?float $v, int $le = 0): string
{
    return $v === null ? '—' : number_format($v, $le, ',', '.');
}

function phan_tram(?float $v): string
{
    return $v === null ? '—' : number_format($v, 1, ',', '.') . '%';
}

/**
 * Đưa một giá trị lấy từ CSDL vào ô nhập.
 *
 * KHÔNG dùng rtrim để bỏ số 0 thừa: tùy trình điều khiển CSDL mà giá trị
 * trả về là "90", "90.0" hay "90.00". rtrim($v, '0') sẽ biến 90 thành 9,
 * 100 thành 1 và 0 thành chuỗi rỗng — người dùng bấm lưu là mất số liệu.
 */
function so_o_nhap($v): string
{
    if ($v === null || $v === '') {
        return '';
    }
    $f = (float)$v;
    if (abs($f - round($f)) < 1e-9) {
        return (string)(int)round($f);      // số nguyên giữ nguyên
    }
    return rtrim(rtrim(number_format($f, 4, '.', ''), '0'), '.');
}

/**
 * Đọc một ô số từ biểu mẫu. Trả về null nếu để trống.
 *
 * Người dùng có thể gõ "1.234,5" (kiểu Việt Nam) hoặc "1234.5".
 * Có cả hai dấu thì dấu chấm là phân cách nghìn, dấu phẩy là thập phân.
 * Chỉ có một dấu thì coi là dấu thập phân — vì ô nhập của hệ thống
 * cũng hiển thị phần thập phân bằng dấu chấm.
 */
function so_tu_bieu_mau(?string $raw): ?float
{
    $raw = str_replace(' ', '', trim((string)$raw));
    if ($raw === '') {
        return null;
    }
    $coCham = str_contains($raw, '.');
    $coPhay = str_contains($raw, ',');
    if ($coCham && $coPhay) {
        $raw = str_replace(['.', ','], ['', '.'], $raw);
    } elseif ($coPhay) {
        $raw = str_replace(',', '.', $raw);
    }
    return (float)$raw;
}

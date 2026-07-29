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
        die('Phiên làm việc đã hết hạn hoặc yêu cầu không hợp lệ. Vui lòng tải lại trang.');
    }
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
    return $s ? date('d/m/Y H:i', strtotime($s)) : '—';
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

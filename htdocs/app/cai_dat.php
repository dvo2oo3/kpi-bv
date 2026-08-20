<?php
require_once __DIR__ . '/db.php';

/**
 * Cài đặt chung dạng khóa – giá trị (bảng cai_dat).
 * Dùng cho logo ứng dụng, và về sau có thể mở rộng cho tên hiển thị…
 *
 * Riêng logo là ảnh có thể nặng nên KHÔNG nạp cùng phần cài đặt chung mỗi
 * trang; nó được phục vụ qua /anh-logo.php (có cache trình duyệt).
 */

/** Đọc một giá trị cài đặt. Trả $macDinh nếu chưa có. */
function cai_dat_lay(string $khoa, ?string $macDinh = null): ?string
{
    // Blob logo nặng → đọc riêng, không đưa vào bộ nhớ chung.
    if ($khoa === 'logo') {
        try {
            $v = qVal('SELECT gia_tri FROM cai_dat WHERE khoa = ?', ['logo']);
            return ($v === false || $v === null) ? $macDinh : (string)$v;
        } catch (\Throwable $e) {
            return $macDinh;
        }
    }
    static $bo = null;              // các cài đặt nhẹ: nạp một lần cho cả trang
    if ($bo === null) {
        $bo = [];
        try {
            foreach (qAll("SELECT khoa, gia_tri FROM cai_dat WHERE khoa <> 'logo'") as $r) {
                $bo[$r['khoa']] = $r['gia_tri'];
            }
        } catch (\Throwable $e) {
            $bo = [];               // chưa có bảng (CSDL cũ) → coi như rỗng
        }
    }
    return array_key_exists($khoa, $bo) ? $bo[$khoa] : $macDinh;
}

/** Ghi (thêm hoặc cập nhật). $giaTri = null để xóa. Portable MySQL + SQLite. */
function cai_dat_dat(string $khoa, ?string $giaTri): void
{
    if ($giaTri === null) {
        q('DELETE FROM cai_dat WHERE khoa = ?', [$khoa]);
        return;
    }
    if (qVal('SELECT 1 FROM cai_dat WHERE khoa = ?', [$khoa])) {
        q('UPDATE cai_dat SET gia_tri = ? WHERE khoa = ?', [$giaTri, $khoa]);
    } else {
        q('INSERT INTO cai_dat (khoa, gia_tri) VALUES (?, ?)', [$khoa, $giaTri]);
    }
}

/**
 * Cấu hình các ô trên trang chủ (dashboard).
 * Trả ['tru_cot' => [id,...], 'khoi_luong' => [id,...]] hoặc null nếu chưa đặt.
 * Lưu theo id chỉ tiêu để đổi tên / đổi mã vẫn lấy đúng.
 */
function dashboard_o(): ?array
{
    $v = cai_dat_lay('dashboard_o');
    if ($v === null || $v === '') {
        return null;
    }
    $d = json_decode($v, true);
    return is_array($d) ? $d : null;
}

/** Lưu cấu hình ô dashboard. Truyền hai mảng id (0 = bỏ trống ô đó). */
function dashboard_o_luu(array $truCot, array $khoiLuong): void
{
    $loc = fn($a) => array_values(array_filter(array_map('intval', $a), fn($x) => $x > 0));
    cai_dat_dat('dashboard_o', json_encode(
        ['tru_cot' => $loc($truCot), 'khoi_luong' => $loc($khoiLuong)],
        JSON_UNESCAPED_UNICODE));
}

/** Data URI của logo — chỉ dùng khi cần chính ảnh (endpoint phục vụ ảnh). */
function logo_du_lieu(): ?string
{
    $v = cai_dat_lay('logo');
    return ($v !== null && str_starts_with($v, 'data:image/')) ? $v : null;
}

/** Dấu phiên bản logo: vừa để biết có logo hay không, vừa để phá cache. */
function logo_phien_ban(): ?string
{
    $v = cai_dat_lay('logo_v');
    return ($v !== null && $v !== '') ? $v : null;
}

/** URL nhẹ để nhúng logo (favicon + <img>). null = dùng biểu tượng mặc định. */
function logo_url(): ?string
{
    $v = logo_phien_ban();
    return $v !== null ? '/anh-logo.php?v=' . rawurlencode($v) : null;
}

/* ------------------------------------------------------------
 * Chế độ bảo trì — hai mức:
 *   1 = Bảo trì thường: chặn tất cả TRỪ dev, admin, và các tài khoản
 *       được chọn (whitelist).
 *   2 = Khóa cứng (dev sửa lỗi): CHỈ dev vào được, admin cũng bị chặn.
 * ---------------------------------------------------------- */

/** Mức bảo trì hiện tại: 0 = tắt, 1 = thường, 2 = khóa cứng. */
function bao_tri_muc(): int
{
    $v = (string)(cai_dat_lay('bao_tri') ?? '0');
    return $v === '2' ? 2 : ($v === '1' ? 1 : 0);
}

/** Có đang bật bảo trì không (mức bất kỳ). */
function dang_bao_tri(): bool
{
    return bao_tri_muc() > 0;
}

/** Danh sách id tài khoản được phép vào khi bảo trì THƯỜNG (ngoài admin/dev). */
function bao_tri_cho_phep(): array
{
    $v = cai_dat_lay('bao_tri_cho_phep');
    if ($v === null || $v === '') {
        return [];
    }
    $ds = json_decode($v, true);
    return is_array($ds) ? array_values(array_filter(array_map('intval', $ds))) : [];
}

/** Lưu danh sách id tài khoản được phép vào khi bảo trì thường. */
function bao_tri_cho_phep_dat(array $ids): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($x) => $x > 0)));
    cai_dat_dat('bao_tri_cho_phep', $ids ? json_encode($ids) : null);
}

/**
 * Tài khoản $nd có được vào hệ thống khi đang bảo trì không?
 *   - dev  : luôn vào (kể cả khóa cứng).
 *   - mức 2: chỉ dev.
 *   - mức 1: thêm admin và các tài khoản trong whitelist.
 */
function bao_tri_duoc_vao(array $nd): bool
{
    $vt = $nd['vai_tro'] ?? '';
    if ($vt === 'dev') {
        return true;
    }
    if (bao_tri_muc() === 2) {
        return false;                          // khóa cứng: admin cũng bị chặn
    }
    if ($vt === 'admin') {
        return true;
    }
    return in_array((int)($nd['id'] ?? 0), bao_tri_cho_phep(), true);
}

/** Lời nhắn hiển thị khi bảo trì (có mặc định). */
function bao_tri_loi_nhan(): string
{
    $v = cai_dat_lay('bao_tri_loi_nhan');
    return ($v !== null && trim($v) !== '')
        ? $v
        : 'Hệ thống đang được bảo trì, nâng cấp. Vui lòng quay lại sau ít phút. '
        . 'Rất xin lỗi vì sự bất tiện này.';
}

/** Trang chặn khi bảo trì. Render trang rồi thoát hẳn. */
function trang_bao_tri(): void
{
    http_response_code(503);
    header('Retry-After: 1800');
    header('Content-Type: text/html; charset=UTF-8');
    $logo  = logo_url();
    $tenDv = defined('TEN_DON_VI') ? TEN_DON_VI : '';
    $hieu  = $logo
        ? '<img src="' . e($logo) . '" alt="" style="width:64px;height:64px;object-fit:contain;border-radius:14px">'
        : '<div class="bt-cong">＋</div>';
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Đang bảo trì</title><style>'
        . ':root{color-scheme:light dark}'
        . '*{box-sizing:border-box}'
        . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
        . 'font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:#f1f5f9;color:#0f172a;padding:22px}'
        . '@media(prefers-color-scheme:dark){body{background:#0f172a;color:#e2e8f0}'
        . '.bt-hop{background:#1e293b !important;border-color:#334155 !important}}'
        . '.bt-hop{background:#fff;border:1px solid #e2e8f0;border-radius:18px;max-width:460px;width:100%;'
        . 'padding:34px 30px;box-shadow:0 12px 34px rgba(0,0,0,.12);text-align:center}'
        . '.bt-cong{width:64px;height:64px;border-radius:14px;background:#2563eb;color:#fff;'
        . 'font-size:38px;line-height:64px;margin:0 auto;font-weight:700}'
        . '.bt-dv{margin-top:14px;font-weight:700;font-size:15px;color:#2563eb}'
        . '.bt-icon{font-size:44px;margin:14px 0 4px}'
        . 'h1{font-size:21px;margin:6px 0 10px}'
        . 'p{margin:0 0 20px;color:#64748b;line-height:1.6;font-size:15px}'
        . '.bt-nut{display:inline-block;padding:11px 20px;border-radius:10px;font-weight:600;'
        . 'text-decoration:none;font-size:14.5px;background:transparent;color:#2563eb;border:1px solid #2563eb}'
        . '</style></head><body><div class="bt-hop">'
        . $hieu
        . ($tenDv !== '' ? '<div class="bt-dv">' . e($tenDv) . '</div>' : '')
        . '<div class="bt-icon">🛠️</div>'
        . '<h1>Hệ thống đang bảo trì</h1>'
        . '<p>' . nl2br(e(bao_tri_loi_nhan())) . '</p>'
        . '<a class="bt-nut" href="/dang-xuat.php">Đăng xuất</a>'
        . '</div></body></html>';
    exit;
}

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

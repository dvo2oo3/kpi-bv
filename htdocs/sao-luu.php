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

mo_trang('Sao lưu dữ liệu');
?>
<div class="dau-muc">
  <div>
    <h1>Sao lưu dữ liệu</h1>
    <p class="phu">Tải toàn bộ dữ liệu về máy để giữ an toàn.</p>
  </div>
</div>

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

<section class="the-hop sl-phuc-hoi">
  <h2>Cách phục hồi</h2>
  <ol class="huong-dan">
    <li>Vào phpMyAdmin, chạy lại <code>install/schema.sql</code> để tạo bảng.</li>
    <li>Tab <strong>Import</strong> → chọn file <code>.sql</code> đã tải → <strong>Go</strong>.</li>
    <li>Đăng nhập lại bằng tài khoản cũ, mật khẩu giữ nguyên.</li>
  </ol>
</section>
<?php dong_trang();

<?php
/**
 * Biểu đồ cho trang Tổng quan.
 *
 * HAI NGUYÊN TẮC:
 *
 * 1. SVG chỉ chứa HÌNH, không chứa CHỮ.
 *    SVG co giãn theo bề rộng thẻ, nên chữ đặt trong SVG cũng co theo:
 *    ở thẻ rộng 318px thì chữ 9px chỉ còn ~5px, không đọc được.
 *    Mọi nhãn đều dựng bằng HTML để giữ đúng cỡ chữ ở mọi khổ màn hình.
 *
 * 2. Cột và đường chỉ dùng MỘT màu.
 *    Giá trị mã hóa bằng độ dài cột và vị trí điểm, không bằng màu.
 *    Bảng màu trạng thái của hệ thống (đỏ #b3261e và cam #96601a) chỉ cách
 *    nhau ΔE 2,9 với người mù màu deutan — tô theo mức là hai nhóm nhìn như một.
 */

const MAU_NET  = '#1580bd';
const MAU_NEN  = '#dceaf4';
const MAU_LUOI = '#e7ecf1';

function svg_esc(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Đường xu hướng 12 tháng — chỉ hình, nhãn tháng do hàm nhan_thang() dựng.
 * Tháng chưa nhập làm đường đứt quãng, không nối bừa qua khoảng trống.
 *
 * @param array $giaTri [thang => giá trị|null]
 */
function bieu_do_duong(array $giaTri, string $donVi = '', int $le = 0): string
{
    $R = 600; $C = 110; $dem = 6;
    $rongVe = $R - $dem * 2;
    $caoVe  = $C - 14;

    $coSo = array_filter($giaTri, fn($v) => $v !== null);
    if (!$coSo) {
        return '<div class="bd-trong">Chưa có số liệu</div>';
    }
    $max = max($coSo);
    $tran = $max > 0 ? $max * 1.15 : 1;

    $x = fn($t) => $dem + ($t - 1) / 11 * $rongVe;
    $y = fn($v) => 7 + $caoVe - ($v / $tran) * $caoVe;

    $svg = '<svg class="bd" viewBox="0 0 ' . $R . ' ' . $C . '" role="img" '
         . 'aria-label="Xu hướng 12 tháng" preserveAspectRatio="none">';

    // Hai đường lưới mảnh, lùi hẳn về sau
    foreach ([0, 0.5] as $p) {
        $yy = round(7 + $caoVe - $p * $caoVe, 1);
        $svg .= '<line x1="0" y1="' . $yy . '" x2="' . $R . '" y2="' . $yy
              . '" stroke="' . MAU_LUOI . '" stroke-width="1" vector-effect="non-scaling-stroke"/>';
    }

    // Cắt đoạn ở tháng chưa nhập
    $doan = []; $cur = [];
    for ($t = 1; $t <= 12; $t++) {
        if (($giaTri[$t] ?? null) === null) {
            if (count($cur) > 1) { $doan[] = $cur; }
            $cur = [];
            continue;
        }
        $cur[] = [$x($t), $y($giaTri[$t])];
    }
    if (count($cur) > 1) { $doan[] = $cur; }

    foreach ($doan as $d) {
        $duong = implode(' ', array_map(fn($p) => round($p[0], 1) . ',' . round($p[1], 1), $d));
        $svg .= '<polygon points="' . round($d[0][0], 1) . ',' . ($C - 1) . ' '
              . $duong . ' ' . round(end($d)[0], 1) . ',' . ($C - 1)
              . '" fill="' . MAU_NEN . '"/>';
        $svg .= '<polyline points="' . $duong . '" fill="none" stroke="' . MAU_NET
              . '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"'
              . ' vector-effect="non-scaling-stroke"/>';
    }

    // Điểm + vùng bắt chuột. Chú giải bằng <title> nên không bị co theo khung.
    for ($t = 1; $t <= 12; $t++) {
        $v = $giaTri[$t] ?? null;
        if ($v === null) { continue; }
        $nhan = 'Tháng ' . $t . ': ' . so($v, $le) . ($donVi ? ' ' . $donVi : '');
        $svg .= '<rect x="' . round($x($t) - $rongVe / 24, 1) . '" y="0" width="'
              . round($rongVe / 12, 1) . '" height="' . $C . '" fill="transparent">'
              . '<title>' . svg_esc($nhan) . '</title></rect>';
        $svg .= '<circle cx="' . round($x($t), 1) . '" cy="' . round($y($v), 1)
              . '" r="3" fill="' . MAU_NET . '" stroke="#fff" stroke-width="2"'
              . ' vector-effect="non-scaling-stroke"><title>' . svg_esc($nhan)
              . '</title></circle>';
    }
    return $svg . '</svg>';
}

/** Hàng nhãn tháng bằng HTML, đặt ngay dưới biểu đồ đường. */
function nhan_thang(): string
{
    $h = '<div class="bd-truc">';
    for ($t = 1; $t <= 12; $t++) {
        $h .= '<span>' . $t . '</span>';
    }
    return $h . '</div>';
}

/**
 * Vòng tròn phần trăm (donut) — SVG thuần, không JS.
 * $pct 0..100. Trả về SVG để nhúng; số/chữ ở giữa do HTML phủ lên.
 */
function svg_donut(float $pct, string $mau = '#0f766e'): string
{
    $pct = max(0.0, min(100.0, $pct));
    $r = 15.915;   // chu vi ≈ 100 để dasharray tính theo %
    return '<svg class="donut" viewBox="0 0 36 36" aria-hidden="true">'
        . '<circle class="donut-nen" cx="18" cy="18" r="' . $r . '"></circle>'
        . '<circle class="donut-day" cx="18" cy="18" r="' . $r . '"'
        . ' stroke="' . $mau . '" stroke-dasharray="' . round($pct, 1) . ' 100"'
        . ' transform="rotate(-90 18 18)"></circle></svg>';
}

/**
 * Đồng hồ nửa cung (gauge) cho chỉ tiêu mức đích — ví dụ công suất giường.
 * $val giá trị, $max đỉnh thang đo (mặc định 120), $moc vạch mốc (100%).
 */
function svg_gauge(?float $val, float $max = 120, float $moc = 100): string
{
    $v = $val === null ? 0 : max(0.0, min($max, $val));
    $frac = $max > 0 ? $v / $max : 0;
    $cung = 125.66;                 // nửa chu vi cung, r=40
    $day  = $cung * $frac;
    // Kim: góc 180° (trái) → 0° (phải), 90° = thẳng đứng
    $goc  = deg2rad(180 - $frac * 180);
    $kx   = 50 + 34 * cos($goc);
    $ky   = 50 - 34 * sin($goc);
    $gocMoc = deg2rad(180 - ($max > 0 ? min(1, $moc / $max) : 0) * 180);
    $mx1 = 50 + 30 * cos($gocMoc); $my1 = 50 - 30 * sin($gocMoc);
    $mx2 = 50 + 44 * cos($gocMoc); $my2 = 50 - 44 * sin($gocMoc);
    return '<svg class="gauge" viewBox="0 0 100 58" aria-hidden="true">'
        . '<path class="gauge-nen" d="M8 50 A42 42 0 0 1 92 50"></path>'
        . '<path class="gauge-day" d="M8 50 A42 42 0 0 1 92 50"'
        . ' stroke-dasharray="' . round($day, 1) . ' 999"></path>'
        . '<line class="gauge-moc" x1="' . round($mx1, 1) . '" y1="' . round($my1, 1)
        . '" x2="' . round($mx2, 1) . '" y2="' . round($my2, 1) . '"></line>'
        . '<line class="gauge-kim" x1="50" y1="50" x2="' . round($kx, 1) . '" y2="' . round($ky, 1) . '"></line>'
        . '<circle class="gauge-tam" cx="50" cy="50" r="3.5"></circle></svg>';
}

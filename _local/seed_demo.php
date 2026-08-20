<?php
/**
 * Tạo dữ liệu mẫu để chạy thử ở máy cá nhân.
 * KHÔNG dùng trên máy chủ thật — mật khẩu ở đây là mật khẩu công khai.
 */
require_once 'D:/my code/quan_ly_bv/htdocs/app/auth.php';

$dsMau = [
    // ten_dang_nhap, ho_ten, vai_tro, chuc_vu, mat_khau, ma khoa phu trach
    ['dev',      'Dương Đình Võ',    'dev',   'Người phát triển',        'devbvns2026',   []],
    ['admin',    'Nguyễn Thị Lan',   'admin', 'Quản trị',                'adminbvns2026', []],
    ['bs.noi',   'Trần Văn Hùng',    'bacsi', 'Điều dưỡng hành chính',   'noibvns2026',   ['NOI']],
    ['bs.nhi',   'Phạm Thị Mai',     'bacsi', 'Điều dưỡng hành chính',   'nhibvns2026',   ['NHI']],
    ['bs.kiem',  'Lê Thị Hoa',       'bacsi', 'Điều dưỡng hành chính',   'kiembvns2026',  ['TN', 'YHCT']], // phụ trách 2 khoa
];

$idKhoa = [];
foreach (qAll('SELECT id, ma FROM khoa') as $k) {
    $idKhoa[$k['ma']] = (int)$k['id'];
}

foreach ($dsMau as [$ten, $hoTen, $vaiTro, $chucVu, $mk, $khoa]) {
    $cu = qVal('SELECT id FROM nguoi_dung WHERE ten_dang_nhap = ?', [$ten]);
    if ($cu) {
        echo "bo qua (da co): $ten\n";
        continue;
    }
    q('INSERT INTO nguoi_dung (ten_dang_nhap, mat_khau_hash, ho_ten, vai_tro, chuc_vu, doi_mat_khau)
       VALUES (?,?,?,?,?,0)',
        [$ten, password_hash($mk, PASSWORD_DEFAULT), $hoTen, $vaiTro, $chucVu]);
    $id = (int)db()->lastInsertId();
    foreach ($khoa as $maK) {
        q('INSERT INTO nguoi_dung_khoa (id_nguoi_dung, id_khoa) VALUES (?,?)',
            [$id, $idKhoa[$maK]]);
    }
    printf("tao: %-10s | %-6s | mat khau: %-15s | khoa: %s\n",
        $ten, $vaiTro, $mk, $khoa ? implode(',', $khoa) : '-');
}

echo "\nSo khoa trong danh muc: " . qVal('SELECT COUNT(*) FROM khoa') . "\n";
echo "So tai khoan: " . qVal('SELECT COUNT(*) FROM nguoi_dung') . "\n";

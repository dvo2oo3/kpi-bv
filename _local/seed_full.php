<?php
/**
 * Nạp dữ liệu thật năm 2026 của khoa Nhi và khoa Truyền nhiễm,
 * lấy nguyên từ hai file Excel, để đối chiếu kết quả tính toán.
 */
require_once 'D:/my code/quan_ly_bv/htdocs/app/chi_tieu.php';
require_once 'D:/my code/quan_ly_bv/htdocs/app/auth.php';

$NAM = 2026;

$idKhoa = [];
foreach (qAll('SELECT id, ma FROM khoa') as $k) { $idKhoa[$k['ma']] = (int)$k['id']; }
$idCT = [];
foreach (qAll('SELECT id, ma FROM chi_tieu') as $c) { $idCT[$c['ma']] = (int)$c['id']; }

if (!$idCT) { die("Chua nap danh muc chi tieu.\n"); }

/* ---------- Số liệu tháng 1..6/2026 ---------- */
// Khoa Nhi — sheet k.KNHI
$nhi = [
  'GB'      => [25,25,25,25,25,25],
  'KB_BH'   => [253,169,223,197,169,190],   // sheet chỉ ghi tổng, dồn hết vào BH
  'NT_BH'   => [87,75,97,90,70,82],
  'NT_ND'   => [1,0,0,1,0,0],
  'NDT_BH'  => [505,431,522,435,394,407],
  'NDT_ND'  => [2,0,0,1,0,0],
  'BN6T'    => [46,43,66,54,51,47],
  'TT_L1'   => [0,0,0,0,0,0],
  'TT_L2'   => [0,0,0,0,0,0],
  'TT_L3'   => [1351,660,1195,1093,1140,1011],
  'KQ_KHOI' => [36,25,8,30,34,20],
  'KQ_DO'   => [51,50,88,61,36,60],
  'KQ_KTD'  => [1,0,1,0,0,2],
  'KQ_NANG' => [0,0,0,0,0,0],
  'TV'      => [0,0,0,0,0,0],
  'CV_NT'   => [1,0,1,0,0,2],
];
// Khoa Truyền nhiễm — sheet Toan vien, mục V
$tn = [
  'GB'     => [20,20,20,20,20,20],
  'NT_BH'  => [56,38,60,63,45,66],
  'NT_ND'  => [2,0,4,1,1,2],
  'NDT_BH' => [347,183,351,361,223,360],
  'NDT_ND' => [6,0,7,1,2,2],
  'BN6T'   => [8,7,11,12,10,8],
  'BN60'   => [9,9,23,17,11,22],
  'TT_L1'  => [0,0,0,0,0,0],
  'TT_L2'  => [0,0,0,0,0,0],
  'TT_L3'  => [255,150,341,372,126,99],
];

/* ---------- Chỉ tiêu giao năm 2026 (file .xlsx) ---------- */
$khNhi = ['GB'=>25,'KB'=>3000,'NDT'=>9125,'NT'=>1825,'NDT_TB'=>5.0,'CSGB'=>100,
          'TT'=>15000,'XN'=>12600,'XN_HH'=>2650,'XN_HS'=>8780,'XN_VS'=>600,'XN_NT'=>570,
          'XQ'=>1200,'SA'=>1200,'NCKH'=>0];
$khTN  = ['GB'=>20,'KB'=>200,'NDT'=>7300,'NT'=>1352,'NDT_TB'=>5.4,'CSGB'=>100,
          'TT'=>1100,'XN'=>5150,'XN_HH'=>700,'XN_HS'=>4000,'XN_VS'=>400,'XN_NT'=>100,
          'XQ'=>400,'SA'=>350,'DEXA'=>10];

function nap_so_lieu(array $bang, int $idK, int $nam, array $idCT): int
{
    $n = 0;
    foreach ($bang as $ma => $giaTri) {
        if (!isset($idCT[$ma])) { echo "  bo qua chi tieu la: $ma\n"; continue; }
        foreach ($giaTri as $i => $v) {
            $thang = $i + 1;
            q('DELETE FROM so_lieu WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
                [$nam, $thang, $idK, $idCT[$ma]]);
            q('INSERT INTO so_lieu (nam,thang,id_khoa,id_chi_tieu,gia_tri) VALUES (?,?,?,?,?)',
                [$nam, $thang, $idK, $idCT[$ma], $v]);
            $n++;
        }
    }
    return $n;
}

function nap_ke_hoach(array $kh, int $idK, int $nam, array $idCT): int
{
    $n = 0;
    foreach ($kh as $ma => $v) {
        if (!isset($idCT[$ma])) { echo "  bo qua KH la: $ma\n"; continue; }
        q('DELETE FROM ke_hoach WHERE nam=? AND id_khoa=? AND id_chi_tieu=?',
            [$nam, $idK, $idCT[$ma]]);
        q('INSERT INTO ke_hoach (nam,id_khoa,id_chi_tieu,chi_tieu_giao) VALUES (?,?,?,?)',
            [$nam, $idK, $idCT[$ma], $v]);
        $n++;
    }
    return $n;
}

echo "Nap so lieu khoa Nhi: " . nap_so_lieu($nhi, $idKhoa['NHI'], $NAM, $idCT) . " o\n";
echo "Nap so lieu khoa TN : " . nap_so_lieu($tn,  $idKhoa['TN'],  $NAM, $idCT) . " o\n";
echo "Nap ke hoach Nhi    : " . nap_ke_hoach($khNhi, $idKhoa['NHI'], $NAM, $idCT) . " chi tieu\n";
echo "Nap ke hoach TN     : " . nap_ke_hoach($khTN,  $idKhoa['TN'],  $NAM, $idCT) . " chi tieu\n";

// Đánh dấu kỳ tháng 1-6 đã khóa
foreach (['NHI','TN'] as $ma) {
    for ($t = 1; $t <= 6; $t++) {
        q('DELETE FROM ky WHERE nam=? AND thang=? AND id_khoa=?', [$NAM, $t, $idKhoa[$ma]]);
        q('INSERT INTO ky (nam,thang,id_khoa,trang_thai) VALUES (?,?,?,?)',
            [$NAM, $t, $idKhoa[$ma], $t <= 5 ? 'DA_KHOA' : 'DA_NOP']);
    }
}
echo "Da danh dau ky T1-T5 khoa, T6 cho duyet.\n";

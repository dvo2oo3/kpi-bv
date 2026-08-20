<?php
/** Nap danh muc chi tieu mac dinh thang qua CLI (khong can server HTTP). */
$HT = 'D:/my code/quan_ly_bv/htdocs';
require_once "$HT/app/db.php";
require_once "$HT/app/danh_muc.php";
require_once "$HT/app/danh_muc_mac_dinh.php";

$dsKhoa = danh_sach_khoa_hoat_dong();
$idKhoaTheoMa = [];
foreach ($dsKhoa as $k) { $idKhoaTheoMa[$k['ma']] = (int)$k['id']; }

function ma_khoa_ap_dung(array $r, array $dsKhoa): array
{
    if ($r['apDung'] === '*') { return array_column($dsKhoa, 'ma'); }
    if ($r['apDung'] === 'NOI_TRU') {
        return array_column(array_filter($dsKhoa, fn($k) => $k['loai'] === 'NOI_TRU'), 'ma');
    }
    return (array)$r['apDung'];
}

$dm = danh_muc_chi_tieu_mac_dinh();

db()->beginTransaction();
$thuTu = 0; $idTheoMa = [];
foreach ($dm as $r) {
    $thuTu += 10;
    $cu = q1('SELECT id FROM chi_tieu WHERE ma = ?', [$r['ma']]);
    if ($cu) {
        q('UPDATE chi_tieu SET ten=?, don_vi=?, thu_tu=?, loai_gia_tri=?,
             nguon=?, huong=?, phan_bo=?, hoat_dong=1 WHERE id=?',
            [$r['ten'], $r['dv'], $thuTu, $r['loai'], $r['nguon'],
             $r['huong'], $r['phanBo'], $cu['id']]);
        $idTheoMa[$r['ma']] = (int)$cu['id'];
    } else {
        q('INSERT INTO chi_tieu (ma, ten, don_vi, thu_tu, loai_gia_tri, nguon, huong, phan_bo)
           VALUES (?,?,?,?,?,?,?,?)',
            [$r['ma'], $r['ten'], $r['dv'], $thuTu, $r['loai'],
             $r['nguon'], $r['huong'], $r['phanBo']]);
        $idTheoMa[$r['ma']] = (int)db()->lastInsertId();
    }
}
foreach ($dm as $r) {
    $idCha = $r['cha'] !== null ? ($idTheoMa[$r['cha']] ?? null) : null;
    q('UPDATE chi_tieu SET id_cha = ? WHERE id = ?', [$idCha, $idTheoMa[$r['ma']]]);
}
foreach ($dm as $r) {
    $idCT = $idTheoMa[$r['ma']];
    foreach (ma_khoa_ap_dung($r, $dsKhoa) as $maK) {
        if (!isset($idKhoaTheoMa[$maK])) { continue; }
        if (!qVal('SELECT 1 FROM chi_tieu_ap_dung WHERE id_chi_tieu=? AND id_khoa=?',
                [$idCT, $idKhoaTheoMa[$maK]])) {
            q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)',
                [$idCT, $idKhoaTheoMa[$maK]]);
        }
    }
}
db()->commit();
echo 'Da nap danh muc: ' . count($dm) . " chi tieu.\n";

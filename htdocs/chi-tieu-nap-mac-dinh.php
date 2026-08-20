<?php
/**
 * Nạp lại bộ thư viện chỉ tiêu mặc định (rút từ hai file Excel).
 *
 * Tách thành trang riêng và BẮT BUỘC xem trước: đây là thao tác chạm vào
 * cả 59 chỉ tiêu cùng lúc, không nên là một cái nút nằm cuối trang danh mục.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/danh_muc.php';
require_once __DIR__ . '/app/danh_muc_mac_dinh.php';

$toi = bat_buoc_quyen('chitieu.nap_mac_dinh');

$dsKhoa = danh_sach_khoa_hoat_dong();
$idKhoaTheoMa = [];
foreach ($dsKhoa as $k) {
    $idKhoaTheoMa[$k['ma']] = (int)$k['id'];
}
$dm = danh_muc_chi_tieu_mac_dinh();

/** Khoa nào sẽ được gán cho một mục trong bộ mặc định. */
function ma_khoa_ap_dung(array $r, array $dsKhoa): array
{
    if ($r['apDung'] === '*') {
        return array_column($dsKhoa, 'ma');
    }
    if ($r['apDung'] === 'NOI_TRU') {
        return array_column(array_filter($dsKhoa, fn($k) => $k['loai'] === 'NOI_TRU'), 'ma');
    }
    return (array)$r['apDung'];
}

/* ---------- So sánh bộ mặc định với danh mục hiện có ---------- */
$xemTruoc = [];
$dem = ['them' => 0, 'sua' => 0, 'giu' => 0];
foreach ($dm as $r) {
    $cu = q1('SELECT * FROM chi_tieu WHERE ma = ?', [$r['ma']]);
    if (!$cu) {
        $viec = 'them';
        $khac = [];
    } else {
        $khac = [];
        if ($cu['ten'] !== $r['ten']) {
            $khac[] = 'nội dung: "' . $cu['ten'] . '" → "' . $r['ten'] . '"';
        }
        if ($cu['don_vi'] !== $r['dv']) {
            $khac[] = 'đơn vị: "' . $cu['don_vi'] . '" → "' . $r['dv'] . '"';
        }
        if ($cu['loai_gia_tri'] !== $r['loai']) {
            $khac[] = 'loại: ' . (NHAN[$cu['loai_gia_tri']] ?? '?') . ' → ' . (NHAN[$r['loai']] ?? '?');
        }
        if ($cu['nguon'] !== $r['nguon']) {
            $khac[] = 'nguồn: ' . (NHAN[$cu['nguon']] ?? '?') . ' → ' . (NHAN[$r['nguon']] ?? '?');
        }
        if ($cu['huong'] !== $r['huong']) {
            $khac[] = 'đánh giá: ' . (NHAN[$cu['huong']] ?? '?') . ' → ' . (NHAN[$r['huong']] ?? '?');
        }
        if ($cu['phan_bo'] !== $r['phanBo']) {
            $khac[] = 'phân bổ: ' . (NHAN[$cu['phan_bo']] ?? '?') . ' → ' . (NHAN[$r['phanBo']] ?? '?');
        }
        if ((int)$cu['hoat_dong'] === 0) {
            $khac[] = 'đang ngừng → dùng lại';
        }
        $viec = $khac ? 'sua' : 'giu';
    }
    $dem[$viec]++;
    $xemTruoc[] = ['ma' => $r['ma'], 'ten' => $r['ten'], 'viec' => $viec, 'khac' => $khac,
                   'khoa' => ma_khoa_ap_dung($r, $dsKhoa)];
}

// Chỉ tiêu do người dùng tự thêm, không nằm trong bộ mặc định
$maMacDinh = array_column($dm, 'ma');
$tuThem = [];
foreach (qAll('SELECT ma, ten FROM chi_tieu ORDER BY thu_tu, id') as $c) {
    if (!in_array($c['ma'], $maMacDinh, true)) {
        $tuThem[] = $c;
    }
}

/* ---------- Thực hiện ---------- */
if (la_post()) {
    kiem_tra_csrf();
    if (post('xac_nhan') !== 'NAP') {
        nhan_tin('loi', 'Phải gõ đúng chữ NAP để xác nhận.');
        chuyen_huong('/chi-tieu-nap-mac-dinh.php');
    }

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
            if (!isset($idKhoaTheoMa[$maK])) {
                continue;
            }
            if (!qVal('SELECT 1 FROM chi_tieu_ap_dung WHERE id_chi_tieu=? AND id_khoa=?',
                    [$idCT, $idKhoaTheoMa[$maK]])) {
                q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)',
                    [$idCT, $idKhoaTheoMa[$maK]]);
            }
        }
    }
    db()->commit();

    ghi_nhat_ky('NAP_DANH_MUC', 'chi_tieu',
        "Thêm {$dem['them']}, cập nhật {$dem['sua']}, giữ nguyên {$dem['giu']}");
    nhan_tin('ok', "Đã nạp danh mục mặc định: thêm mới {$dem['them']}, "
        . "cập nhật {$dem['sua']}, giữ nguyên {$dem['giu']} chỉ tiêu.");
    chuyen_huong('/danh-muc-chi-tieu.php');
}

mo_trang('Nạp danh mục mặc định');
?>
<p class="duong-dan"><a href="/danh-muc-chi-tieu.php">Thư viện chỉ tiêu</a> › Nạp danh mục mặc định</p>
<h1>Nạp danh mục mặc định</h1>
<p class="phu">
  Bộ <?= count($dm) ?> chỉ tiêu rút từ hai file Excel đang dùng
  (<em>Theo dõi thực hiện chỉ tiêu kế hoạch khoa 2026</em> và
  <em>Chỉ tiêu các khoa đạt được</em>). Đối chiếu theo mã chỉ tiêu.
</p>

<div class="the-tom-tat">
  <div class="o-tom-tat o-dat"><strong><?= $dem['them'] ?></strong><span>Sẽ thêm mới</span></div>
  <div class="o-tom-tat o-canh-bao"><strong><?= $dem['sua'] ?></strong><span>Sẽ cập nhật</span></div>
  <div class="o-tom-tat o-na"><strong><?= $dem['giu'] ?></strong><span>Giữ nguyên</span></div>
  <div class="o-tom-tat o-vuot"><strong><?= count($tuThem) ?></strong><span>Bạn tự thêm, không đụng tới</span></div>
</div>

<div class="tb tb-canh-bao">
  <strong>Không mất số liệu.</strong> Thao tác này chỉ sửa danh mục, không đụng vào bảng số liệu
  hay kế hoạch đã nhập. Nhưng nếu bạn từng sửa tên hay cách tính của một chỉ tiêu mặc định,
  nó sẽ bị đưa về như bản gốc — xem cột <em>Thay đổi</em> bên dưới.
</div>

<?php if ($dem['sua'] > 0): ?>
<h2>Những chỉ tiêu sẽ bị thay đổi (<?= $dem['sua'] ?>)</h2>
<div class="cuon-ngang">
<table class="bang">
  <thead><tr><th>Mã</th><th>Nội dung</th><th>Thay đổi</th></tr></thead>
  <tbody>
  <?php foreach ($xemTruoc as $x): if ($x['viec'] !== 'sua') continue; ?>
    <tr>
      <td><code><?= e($x['ma']) ?></code></td>
      <td><?= e($x['ten']) ?></td>
      <td class="nho">
        <?php foreach ($x['khac'] as $k): ?>
          <div class="dong-thay-doi"><?= e($k) ?></div>
        <?php endforeach; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php if ($dem['them'] > 0): ?>
<h2>Những chỉ tiêu sẽ được thêm mới (<?= $dem['them'] ?>)</h2>
<div class="cuon-ngang">
<table class="bang">
  <thead><tr><th>Mã</th><th>Nội dung</th><th>Khoa áp dụng</th></tr></thead>
  <tbody>
  <?php foreach ($xemTruoc as $x): if ($x['viec'] !== 'them') continue; ?>
    <tr>
      <td><code><?= e($x['ma']) ?></code></td>
      <td><?= e($x['ten']) ?></td>
      <td class="nho"><?= e(implode(', ', $x['khoa'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php if ($tuThem): ?>
<h2>Chỉ tiêu bạn tự thêm — giữ nguyên, không đụng tới (<?= count($tuThem) ?>)</h2>
<p class="phu"><?php
  $ten = array_map(fn($c) => $c['ma'] . ' (' . $c['ten'] . ')', $tuThem);
  echo e(implode(' · ', array_slice($ten, 0, 20)));
  if (count($ten) > 20) { echo ' … và ' . (count($ten) - 20) . ' chỉ tiêu nữa'; }
?></p>
<?php endif; ?>

<h2>Xác nhận</h2>
<form method="post" class="bieu-mau-ngang">
  <?= csrf_field() ?>
  <label>Gõ chữ <code>NAP</code> để xác nhận
    <input type="text" name="xac_nhan" placeholder="NAP" required autocomplete="off">
  </label>
  <p class="hang-nut">
    <button class="nut nut-nguy" type="submit">Nạp danh mục mặc định</button>
    <a class="nut nut-phu" href="/danh-muc-chi-tieu.php">Hủy</a>
  </p>
</form>
<?php dong_trang();

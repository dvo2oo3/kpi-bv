<?php
/**
 * Duyệt và khóa kỳ. Mở lại kỳ đã khóa: chỉ người phát triển.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';

$toi = bat_buoc_quyen('ky.duyet');

$macDinhThang = (int)date('n') - 1;
$macDinhNam   = (int)date('Y');
if ($macDinhThang === 0) { $macDinhThang = 12; $macDinhNam--; }

$nam   = (int)($_GET['nam']   ?? $macDinhNam);
$thang = (int)($_GET['thang'] ?? $macDinhThang);
$thang = max(1, min(12, $thang));

$dsKhoa = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');

if (la_post()) {
    kiem_tra_csrf();
    $viec   = post('viec');
    $idKhoa = (int)post('id_khoa');
    $khoa   = q1('SELECT * FROM khoa WHERE id = ?', [$idKhoa]);

    if (!$khoa) {
        nhan_tin('loi', 'Không tìm thấy khoa.');
        chuyen_huong("/duyet-ky.php?nam=$nam&thang=$thang");
    }

    $bandau = trang_thai_ky($nam, $thang, $idKhoa);
    $bao_dam_co_ky = function () use ($nam, $thang, $idKhoa, $bandau) {
        if (!qVal('SELECT 1 FROM ky WHERE nam=? AND thang=? AND id_khoa=?',
                [$nam, $thang, $idKhoa])) {
            q('INSERT INTO ky (nam, thang, id_khoa, trang_thai) VALUES (?,?,?,?)',
                [$nam, $thang, $idKhoa, $bandau === 'CHUA_DEN' ? 'MO' : $bandau]);
        }
    };

    if ($viec === 'duyet') {
        $bao_dam_co_ky();
        q('UPDATE ky SET trang_thai=?, nguoi_duyet=?, thoi_diem_duyet=CURRENT_TIMESTAMP
            WHERE nam=? AND thang=? AND id_khoa=?',
            ['DA_DUYET', $toi['id'], $nam, $thang, $idKhoa]);
        ghi_nhat_ky('DUYET_KY', $khoa['ma'], "Tháng $thang/$nam");
        nhan_tin('ok', "Đã duyệt số liệu {$khoa['ten']} tháng $thang/$nam.");

    } elseif ($viec === 'tra_lai') {
        $bao_dam_co_ky();
        // Trả lại mà lịch đã đóng thì khoa vẫn không nhập được — phải gia hạn kèm theo
        $ngay = max(1, min(60, (int)post('so_ngay', '7')));
        $den  = date('Y-m-d', strtotime("+$ngay days"));
        q('UPDATE ky SET trang_thai=?, ghi_chu=?, mo_den=? WHERE nam=? AND thang=? AND id_khoa=?',
            ['MO', post('ly_do') ?: null, $den, $nam, $thang, $idKhoa]);
        ghi_nhat_ky('TRA_LAI_KY', $khoa['ma'],
            "Tháng $thang/$nam — gia hạn đến $den — " . post('ly_do'));
        nhan_tin('ok', "Đã trả lại {$khoa['ten']} để nhập bổ sung, "
            . "mở đến hết " . date('d/m/Y', strtotime($den)) . '.');

    } elseif ($viec === 'khoa') {
        $bao_dam_co_ky();
        q('UPDATE ky SET trang_thai=?, mo_den=NULL WHERE nam=? AND thang=? AND id_khoa=?',
            ['DA_KHOA', $nam, $thang, $idKhoa]);
        ghi_nhat_ky('KHOA_KY', $khoa['ma'], "Tháng $thang/$nam");
        nhan_tin('ok', "Đã khóa số liệu {$khoa['ten']} tháng $thang/$nam.");

    } elseif ($viec === 'mo_lai') {
        // Quyền riêng của người phát triển
        if (!co_quyen('ky.mo_lai')) {
            ghi_nhat_ky('TU_CHOI_MO_LAI_KY', $khoa['ma'], "Tháng $thang/$nam");
            nhan_tin('loi', 'Chỉ người phát triển mới mở lại được kỳ đã khóa. '
                . 'Yêu cầu của bạn đã được ghi vào nhật ký.');
        } else {
            $bao_dam_co_ky();
            $ngay = max(1, min(60, (int)post('so_ngay', '7')));
            $den  = date('Y-m-d', strtotime("+$ngay days"));
            q('UPDATE ky SET trang_thai=?, ghi_chu=?, mo_den=? WHERE nam=? AND thang=? AND id_khoa=?',
                ['MO', post('ly_do') ?: null, $den, $nam, $thang, $idKhoa]);
            ghi_nhat_ky('MO_LAI_KY', $khoa['ma'],
                "Tháng $thang/$nam — gia hạn đến $den — " . post('ly_do'));
            nhan_tin('ok', "Đã mở lại kỳ {$khoa['ten']} tháng $thang/$nam.");
        }
    }
    chuyen_huong("/duyet-ky.php?nam=$nam&thang=$thang");
}

mo_trang('Duyệt và khóa kỳ');
?>
<h1>Duyệt và khóa kỳ — tháng <?= $thang ?>/<?= $nam ?></h1>

<form method="get" class="thanh-loc">
  <label>Năm
    <select name="nam" onchange="this.form.submit()">
      <?php for ($n = NAM_MAC_DINH; $n >= NAM_MAC_DINH - 3; $n--): ?>
        <option value="<?= $n ?>" <?= $n === $nam ? 'selected' : '' ?>><?= $n ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <label>Tháng
    <select name="thang" onchange="this.form.submit()">
      <?php for ($t = 1; $t <= 12; $t++): ?>
        <option value="<?= $t ?>" <?= $t === $thang ? 'selected' : '' ?>>Tháng <?= $t ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <span class="phu">Hạn nộp: <?= date('d/m/Y', han_nop($nam, $thang)) ?></span>
</form>

<div class="cuon-ngang">
<table class="bang">
  <thead>
    <tr><th>Khoa</th><th>Trạng thái</th><th>Đã nhập</th>
        <th>Người nộp</th><th>Người duyệt</th><th>Thao tác</th></tr>
  </thead>
  <tbody>
  <?php foreach ($dsKhoa as $k):
      $idK = (int)$k['id'];
      $tt  = trang_thai_ky($nam, $thang, $idK);
      $ky  = q1('SELECT * FROM ky WHERE nam=? AND thang=? AND id_khoa=?', [$nam, $thang, $idK]);
      $soCT = count(array_filter(chi_tieu_cua_khoa($idK), fn($c) => $c['nguon'] === 'NHAP_TAY'));
      $daNhap = (int)qVal('SELECT COUNT(*) FROM so_lieu
                            WHERE nam=? AND thang=? AND id_khoa=? AND gia_tri IS NOT NULL',
          [$nam, $thang, $idK]);
      $nguoiNop   = $ky && $ky['nguoi_nop']   ? qVal('SELECT ho_ten FROM nguoi_dung WHERE id=?', [$ky['nguoi_nop']]) : null;
      $nguoiDuyet = $ky && $ky['nguoi_duyet'] ? qVal('SELECT ho_ten FROM nguoi_dung WHERE id=?', [$ky['nguoi_duyet']]) : null;
      ?>
    <tr>
      <td><strong><?= e($k['ma']) ?></strong><br><small class="phu"><?= e($k['ten']) ?></small></td>
      <td><span class="trang-thai-ky tt-<?= e(strtolower($tt)) ?>"><?= e(ten_trang_thai($tt)) ?></span></td>
      <td>
        <?= $daNhap ?>/<?= $soCT ?>
        <?php if ($soCT > 0): ?>
          <div class="thanh-tien-do"><span style="width:<?= min(100, round($daNhap / $soCT * 100)) ?>%"></span></div>
        <?php endif; ?>
      </td>
      <td class="nho"><?= $nguoiNop ? e($nguoiNop) . '<br>' . e(ngay_gio($ky['thoi_diem_nop'])) : '—' ?></td>
      <td class="nho"><?= $nguoiDuyet ? e($nguoiDuyet) . '<br>' . e(ngay_gio($ky['thoi_diem_duyet'])) : '—' ?></td>
      <td class="thao-tac">
        <a class="nut nut-nho nut-phu"
           href="/nhap-so-lieu.php?nam=<?= $nam ?>&thang=<?= $thang ?>&khoa=<?= $idK ?>">Xem</a>

        <?php if ($tt === 'DA_NOP'): ?>
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="viec" value="duyet">
            <input type="hidden" name="id_khoa" value="<?= $idK ?>">
            <button class="nut nut-nho" type="submit">Duyệt</button>
          </form>
          <details>
            <summary>Trả lại</summary>
            <form method="post"><?= csrf_field() ?>
              <input type="hidden" name="viec" value="tra_lai">
              <input type="hidden" name="id_khoa" value="<?= $idK ?>">
              <input type="text" name="ly_do" placeholder="Lý do trả lại" required>
              <label>Mở thêm bao nhiêu ngày
                <input type="text" inputmode="numeric" name="so_ngay" value="7">
              </label>
              <button class="nut nut-nho nut-phu" type="submit">Gửi</button>
            </form>
          </details>
        <?php endif; ?>

        <?php if ($tt === 'DA_DUYET'): ?>
          <form method="post"
                onsubmit="return confirm('Khóa số liệu khoa này? Sau khi khóa chỉ người phát triển mở lại được.')">
            <?= csrf_field() ?>
            <input type="hidden" name="viec" value="khoa">
            <input type="hidden" name="id_khoa" value="<?= $idK ?>">
            <button class="nut nut-nho nut-nguy" type="submit">Khóa</button>
          </form>
        <?php endif; ?>

        <?php if ($tt === 'DA_KHOA'): ?>
          <details>
            <summary><?= co_quyen('ky.mo_lai') ? 'Mở lại' : 'Đề nghị mở lại' ?></summary>
            <form method="post"><?= csrf_field() ?>
              <input type="hidden" name="viec" value="mo_lai">
              <input type="hidden" name="id_khoa" value="<?= $idK ?>">
              <input type="text" name="ly_do" placeholder="Lý do mở lại" required>
              <label>Mở thêm bao nhiêu ngày
                <input type="text" inputmode="numeric" name="so_ngay" value="7">
              </label>
              <button class="nut nut-nho nut-nguy" type="submit">Gửi</button>
            </form>
            <?php if (!co_quyen('ky.mo_lai')): ?>
              <small class="phu">Chỉ người phát triển thực hiện được. Yêu cầu sẽ vào nhật ký.</small>
            <?php endif; ?>
          </details>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php dong_trang();

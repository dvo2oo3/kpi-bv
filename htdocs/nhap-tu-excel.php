<?php
/**
 * Nhập số liệu từ Excel theo biểu mẫu.
 *
 * Quy trình: tải file mẫu → điền trong Excel → tải lên → xem đối chiếu → xác nhận.
 *
 * Bắt buộc có bước đối chiếu trước khi ghi: file Excel do người ngoài điền,
 * không thể tin là đúng. Bước này chỉ ra rõ ô nào đổi từ đâu sang đâu,
 * ô nào bị bỏ vì kỳ đã chốt, mã nào không nhận ra.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/xlsx.php';

$toi = bat_buoc_dang_nhap();
// Việc của Phòng KHTH. Khoa nào quen làm Excel thì admin ủy riêng quyền này —
// khi đó vẫn chỉ thấy khoa của mình, và vẫn không đụng được chỉ tiêu giao.
bat_buoc_quyen('solieu.nhap_excel');

$duocPhep = cac_khoa_duoc_phep();
$dsKhoa = $duocPhep === null
    ? qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten')
    : ($duocPhep ? qAll('SELECT * FROM khoa WHERE hoat_dong = 1 AND id IN ('
        . implode(',', array_fill(0, count($duocPhep), '?')) . ') ORDER BY thu_tu', $duocPhep)
      : []);

if (!$dsKhoa) {
    mo_trang('Nhập từ Excel');
    echo '<div class="tb tb-canh-bao">Bạn chưa được gán khoa nào.</div>';
    dong_trang();
    exit;
}

$nam    = (int)($_GET['nam'] ?? $_POST['nam'] ?? NAM_MAC_DINH);
$idKhoa = (int)($_GET['khoa'] ?? $_POST['khoa'] ?? $dsKhoa[0]['id']);
if (!co_quyen_voi_khoa($idKhoa)) {
    bat_buoc_quyen('solieu.xem_tat_ca');
}
$khoa = q1('SELECT * FROM khoa WHERE id = ?', [$idKhoa]);
$dsCT = array_values(array_filter(chi_tieu_cua_khoa($idKhoa),
    fn($c) => $c['nguon'] === 'NHAP_TAY'));

// Bác sĩ nhập số liệu nhưng không được đụng vào chỉ tiêu giao. Cột đó vẫn nằm
// trong file mẫu để đối chiếu — bỏ hẳn cột thì các cột tháng dồn sang trái,
// file của bác sĩ và file của Phòng KHTH không còn dùng lẫn cho nhau được.
$duocGiao = co_quyen('chitieu.giao');

/* ============================================================
 * Tải file mẫu
 * ========================================================== */
if (($_GET['tai'] ?? '') === 'mau') {
    if (!xlsx_kha_dung()) {
        nhan_tin('loi', 'Máy chủ thiếu phần mở rộng zlib nên chưa tạo được file Excel.');
        chuyen_huong("/nhap-tu-excel.php?nam=$nam&khoa=$idKhoa");
    }
    $kh = ke_hoach_nam($nam, $idKhoa);

    $dong = [];
    $dong[] = ['Mã chỉ tiêu', 'Nội dung', 'Đơn vị',
               'Chỉ tiêu giao ' . $nam . ($duocGiao ? '' : ' (chỉ để xem)'),
               'T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];
    foreach ($dsCT as $ct) {
        $d = [$ct['ma'], ($ct['cap'] ? '    ' : '') . $ct['ten'], $ct['don_vi']];
        $r = $kh[$ct['id']] ?? null;
        $d[] = $r && $r['chi_tieu_giao'] !== null ? (float)$r['chi_tieu_giao'] : '';
        for ($t = 1; $t <= 12; $t++) {
            $v = gia_tri_thang($nam, $t, $idKhoa, $ct['id']);
            $d[] = $v === null ? '' : $v;
        }
        $dong[] = $d;
    }

    $ten = 'mau-' . strtolower($khoa['ma']) . "-$nam.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $ten . '"');
    header('Cache-Control: max-age=0');
    echo xlsx_tao($dong, $khoa['ma'] . ' ' . $nam,
        [14, 42, 9, 15, 8,8,8,8,8,8,8,8,8,8,8,8], 1);
    ghi_nhat_ky('TAI_MAU_EXCEL', $khoa['ma'], "Năm $nam");
    exit;
}

/* ============================================================
 * Đọc file tải lên và dựng bảng đối chiếu
 * ========================================================== */
$doiChieu = null; $loiDoc = null; $maLa = [];

function doc_o($v): ?float
{
    $v = trim((string)$v);
    return $v === '' ? null : so_tu_bieu_mau($v);
}

if (la_post() && post('viec') === 'tai_len') {
    kiem_tra_csrf();
    $f = $_FILES['tep'] ?? null;

    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $loiDoc = 'Chưa chọn được file, hoặc file quá lớn so với giới hạn của máy chủ.';
    } elseif ($f['size'] > 3 * 1024 * 1024) {
        $loiDoc = 'File lớn hơn 3 MB. Biểu mẫu chuẩn chỉ vài chục KB — kiểm tra lại file.';
    } else {
        $noiDung = file_get_contents($f['tmp_name']);
        $bang = xlsx_doc($noiDung);
        if (!$bang) {
            $loiDoc = 'Không đọc được file. Phải là định dạng .xlsx (Excel 2007 trở lên). '
                . 'Nếu file của bạn là .xls đời cũ, mở bằng Excel rồi chọn '
                . 'Lưu thành → Excel Workbook (*.xlsx).';
        } else {
            $ctTheoMa = [];
            foreach ($dsCT as $ct) { $ctTheoMa[$ct['ma']] = $ct; }

            $khNam = ke_hoach_nam($nam, $idKhoa);
            $doiChieu = [];
            foreach ($bang as $i => $d) {
                if ($i === 0) { continue; }               // dòng tiêu đề
                $ma = trim((string)($d[0] ?? ''));
                if ($ma === '') { continue; }
                if (!isset($ctTheoMa[$ma])) { $maLa[] = $ma; continue; }
                $ct = $ctTheoMa[$ma];

                // Chỉ tiêu giao cả năm — cột D. Không có quyền giao thì cột này
                // coi như không tồn tại, không bày ra rồi lặng lẽ bỏ lúc ghi.
                if ($duocGiao) {
                    $moiKH = doc_o($d[3] ?? '');
                    $r = $khNam[$ct['id']] ?? null;
                    $cuKH = $r && $r['chi_tieu_giao'] !== null ? (float)$r['chi_tieu_giao'] : null;
                    if ($moiKH !== null && $cuKH !== $moiKH) {
                        $doiChieu[] = ['loai' => 'kh', 'ct' => $ct, 'thang' => null,
                                       'cu' => $cuKH, 'moi' => $moiKH, 'bo_qua' => null];
                    }
                }

                // Số liệu 12 tháng — cột E..P
                for ($t = 1; $t <= 12; $t++) {
                    $moi = doc_o($d[3 + $t] ?? '');
                    if ($moi === null) { continue; }
                    $cu = gia_tri_thang($nam, $t, $idKhoa, $ct['id']);
                    if ($cu !== null && abs($cu - $moi) < 1e-9) { continue; }

                    // Cùng một luật với trang Nhập số liệu: còn trong cửa sổ mở
                    // thì sửa được, kể cả kỳ đã bấm Nộp, kể cả kỳ được gia hạn.
                    $boQua = null;
                    if (!ky_cho_sua($nam, $t, $idKhoa)) {
                        $tt = trang_thai_ky($nam, $t, $idKhoa);
                        $boQua = $tt === 'CHUA_DEN'
                            ? 'chưa đến kỳ nhập'
                            : 'kỳ ' . chu_thuong(ten_trang_thai($tt));
                    }
                    $doiChieu[] = ['loai' => 'sl', 'ct' => $ct, 'thang' => $t,
                                   'cu' => $cu, 'moi' => $moi, 'bo_qua' => $boQua];
                }
            }
        }
    }
}

/* ============================================================
 * Ghi vào cơ sở dữ liệu sau khi người dùng xác nhận
 * ========================================================== */
if (la_post() && post('viec') === 'ghi') {
    kiem_tra_csrf();
    $chon = $_POST['ap'] ?? [];
    $soKH = 0; $soSL = 0;

    db()->beginTransaction();
    foreach ($chon as $khoaChon) {
        $p = explode('|', (string)$khoaChon);
        if (count($p) !== 3) { continue; }
        [$loai, $idCT, $thang] = [$p[0], (int)$p[1], $p[2]];

        $ct = null;
        foreach ($dsCT as $c) { if ($c['id'] === $idCT) { $ct = $c; break; } }
        if (!$ct) { continue; }

        $gt = doc_o($_POST['gt'][$khoaChon] ?? '');
        if ($gt === null) { continue; }

        if ($loai === 'kh') {
            if (!$duocGiao) { continue; }
            if (qVal('SELECT 1 FROM ke_hoach WHERE nam=? AND id_khoa=? AND id_chi_tieu=?',
                    [$nam, $idKhoa, $idCT])) {
                q('UPDATE ke_hoach SET chi_tieu_giao=? WHERE nam=? AND id_khoa=? AND id_chi_tieu=?',
                    [$gt, $nam, $idKhoa, $idCT]);
            } else {
                q('INSERT INTO ke_hoach (nam, id_khoa, id_chi_tieu, chi_tieu_giao) VALUES (?,?,?,?)',
                    [$nam, $idKhoa, $idCT, $gt]);
            }
            $soKH++;
        } else {
            $t = (int)$thang;
            // Chốt chặn ở máy chủ: kỳ đã chốt thì không ghi, dù biểu mẫu có gửi lên
            if (!ky_cho_sua($nam, $t, $idKhoa)) { continue; }
            if (qVal('SELECT 1 FROM so_lieu WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
                    [$nam, $t, $idKhoa, $idCT])) {
                q('UPDATE so_lieu SET gia_tri=?, nguoi_nhap=?, thoi_diem=CURRENT_TIMESTAMP
                    WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
                    [$gt, $toi['id'], $nam, $t, $idKhoa, $idCT]);
            } else {
                q('INSERT INTO so_lieu (nam, thang, id_khoa, id_chi_tieu, gia_tri, nguoi_nhap)
                   VALUES (?,?,?,?,?,?)', [$nam, $t, $idKhoa, $idCT, $gt, $toi['id']]);
            }
            if (!qVal('SELECT 1 FROM ky WHERE nam=? AND thang=? AND id_khoa=?', [$nam, $t, $idKhoa])) {
                q('INSERT INTO ky (nam, thang, id_khoa, trang_thai) VALUES (?,?,?,?)',
                    [$nam, $t, $idKhoa, 'MO']);
            }
            $soSL++;
        }
    }
    db()->commit();

    ghi_nhat_ky('NHAP_TU_EXCEL', $khoa['ma'], "Năm $nam — $soSL ô số liệu, $soKH chỉ tiêu giao");
    nhan_tin('ok', $duocGiao
        ? "Đã ghi $soSL ô số liệu và $soKH chỉ tiêu giao từ file Excel."
        : "Đã ghi $soSL ô số liệu từ file Excel.");
    chuyen_huong("/nhap-tu-excel.php?nam=$nam&khoa=$idKhoa");
}

mo_trang('Nhập từ Excel');
?>
<h1>Nhập số liệu từ Excel</h1>

<form method="get" class="thanh-loc">
  <label>Khoa
    <select name="khoa" onchange="this.form.submit()">
      <?php foreach ($dsKhoa as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $idKhoa ? 'selected' : '' ?>>
          <?= e($k['ten']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Năm
    <select name="nam" onchange="this.form.submit()">
      <?php for ($n = NAM_MAC_DINH; $n >= NAM_MAC_DINH - 3; $n--): ?>
        <option value="<?= $n ?>" <?= $n === $nam ? 'selected' : '' ?>><?= $n ?></option>
      <?php endfor; ?>
    </select>
  </label>
</form>

<?php if ($loiDoc): ?>
  <div class="tb tb-loi"><?= e($loiDoc) ?></div>
<?php endif; ?>

<?php if ($doiChieu === null): ?>
<div class="luoi-buoc">
  <section class="buoc">
    <div class="buoc-dau"><span class="so-buoc">1</span><div><h2>Tải biểu mẫu</h2></div></div>
    <p class="phu">
      File mẫu của <strong><?= e($khoa['ten']) ?></strong> năm <?= $nam ?>,
      gồm <?= count($dsCT) ?> dòng chỉ tiêu và 12 cột tháng.
      Số liệu đã nhập trong hệ thống được điền sẵn để đối chiếu.
    </p>
    <p class="hang-nut">
      <a class="nut" href="?tai=mau&nam=<?= $nam ?>&khoa=<?= $idKhoa ?>">Tải file mẫu (.xlsx)</a>
    </p>
  </section>

  <section class="buoc">
    <div class="buoc-dau"><span class="so-buoc">2</span><div><h2>Điền trong Excel</h2></div></div>
    <ul class="huong-dan">
      <li><strong>Không đổi cột A</strong> — hệ thống đối chiếu theo mã chỉ tiêu ở cột này.</li>
      <li>Thêm bớt dòng, đổi thứ tự đều được. Dòng có mã lạ sẽ bị bỏ qua và báo lại.</li>
      <li>Ô để trống nghĩa là <em>không đụng tới</em>, khác với số 0.</li>
      <?php if (!$duocGiao): ?>
        <li>Cột <strong>Chỉ tiêu giao</strong> chỉ để đối chiếu — sửa cột đó
            hệ thống không nhận. Chỉ tiêu giao do Phòng KHTH đặt.</li>
      <?php endif; ?>
      <li>Lưu lại ở định dạng <strong>.xlsx</strong>.</li>
    </ul>
  </section>

  <section class="buoc">
    <div class="buoc-dau"><span class="so-buoc">3</span><div><h2>Tải lên và đối chiếu</h2></div></div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="viec" value="tai_len">
      <input type="hidden" name="nam" value="<?= $nam ?>">
      <input type="hidden" name="khoa" value="<?= $idKhoa ?>">
      <label>Chọn file đã điền
        <input type="file" name="tep" accept=".xlsx" required>
      </label>
      <button class="nut nut-chinh" type="submit">Đối chiếu trước khi ghi</button>
      <p class="phu">Chưa ghi gì vào hệ thống ở bước này.</p>
    </form>
  </section>
</div>

<?php else: ?>
<?php
  $apDuoc = array_values(array_filter($doiChieu, fn($d) => $d['bo_qua'] === null));
  $boQua  = array_values(array_filter($doiChieu, fn($d) => $d['bo_qua'] !== null));
?>
<div class="the-tom-tat">
  <div class="o-tom-tat o-dat"><strong><?= count($apDuoc) ?></strong><span>Sẽ ghi vào</span></div>
  <div class="o-tom-tat o-canh-bao"><strong><?= count($boQua) ?></strong><span>Bỏ qua vì kỳ không mở</span></div>
  <div class="o-tom-tat o-na"><strong><?= count($maLa) ?></strong><span>Mã không nhận ra</span></div>
</div>

<?php if ($maLa): ?>
  <div class="tb tb-canh-bao">
    Không nhận ra <?= count($maLa) ?> mã chỉ tiêu, các dòng này bị bỏ qua:
    <code><?= e(implode('</code>, <code>', array_slice(array_unique($maLa), 0, 15))) ?></code>
  </div>
<?php endif; ?>

<?php if (!$apDuoc): ?>
  <div class="tb tb-canh-bao">Không có giá trị nào cần ghi. File giống hệt số liệu đang có,
    hoặc mọi kỳ liên quan đã chốt.</div>
  <p class="hang-nut"><a class="nut nut-phu"
     href="/nhap-tu-excel.php?nam=<?= $nam ?>&khoa=<?= $idKhoa ?>">Quay lại</a></p>
<?php else: ?>

<h2>Những giá trị sẽ được ghi</h2>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="ghi">
  <input type="hidden" name="nam" value="<?= $nam ?>">
  <input type="hidden" name="khoa" value="<?= $idKhoa ?>">
  <div class="cuon-ngang">
  <table class="bang">
    <thead>
      <tr><th style="width:34px"></th><th>Nội dung</th><th>Kỳ</th>
          <th class="phai">Đang có</th><th class="phai">Từ Excel</th><th class="phai">Chênh</th></tr>
    </thead>
    <tbody>
    <?php foreach ($apDuoc as $d):
        $khoaO = $d['loai'] . '|' . $d['ct']['id'] . '|' . ($d['thang'] ?? '');
        $chenh = ($d['cu'] !== null) ? $d['moi'] - $d['cu'] : null; ?>
      <tr>
        <td><input type="checkbox" name="ap[]" value="<?= e($khoaO) ?>" checked></td>
        <td><?= e($d['ct']['ten']) ?> <span class="phu"><?= e($d['ct']['don_vi']) ?></span></td>
        <td class="nho"><?= $d['loai'] === 'kh'
              ? '<strong>Chỉ tiêu giao năm</strong>' : 'Tháng ' . $d['thang'] ?></td>
        <td class="phai"><?= $d['cu'] === null
              ? '<span class="phu">chưa nhập</span>' : so($d['cu'], 2) ?></td>
        <td class="phai"><strong><?= so($d['moi'], 2) ?></strong></td>
        <td class="phai nho <?= $chenh !== null && $chenh < 0 ? 'chenh-am' : 'chenh-duong' ?>">
          <?= $chenh === null ? '—' : ($chenh > 0 ? '+' : '') . so($chenh, 2) ?>
        </td>
        <input type="hidden" name="gt[<?= e($khoaO) ?>]" value="<?= e(so_o_nhap($d['moi'])) ?>">
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p class="hang-nut">
    <button class="nut" type="submit"
            onclick="return confirm('Ghi <?= count($apDuoc) ?> giá trị vào hệ thống?')">
      Ghi <?= count($apDuoc) ?> giá trị đã chọn
    </button>
    <a class="nut nut-phu" href="/nhap-tu-excel.php?nam=<?= $nam ?>&khoa=<?= $idKhoa ?>">Hủy</a>
  </p>
</form>
<?php endif; ?>

<?php if ($boQua): ?>
<h2>Bỏ qua vì kỳ không mở <span class="phu">· <?= count($boQua) ?> giá trị</span></h2>
<p class="phu">
  Muốn sửa số liệu của kỳ đã chốt phải dùng bút toán điều chỉnh ở trang Nhập số liệu,
  để giữ lại giá trị cũ và lý do sửa.
</p>
<div class="cuon-ngang">
<table class="bang">
  <thead><tr><th>Nội dung</th><th>Kỳ</th><th class="phai">Đang có</th>
             <th class="phai">Từ Excel</th><th>Lý do bỏ qua</th></tr></thead>
  <tbody>
  <?php foreach (array_slice($boQua, 0, 40) as $d): ?>
    <tr class="dong-mo">
      <td><?= e($d['ct']['ten']) ?></td>
      <td class="nho">Tháng <?= $d['thang'] ?></td>
      <td class="phai"><?= $d['cu'] === null ? '—' : so($d['cu'], 2) ?></td>
      <td class="phai"><?= so($d['moi'], 2) ?></td>
      <td class="nho"><?= e($d['bo_qua']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php endif; ?>
<?php dong_trang();

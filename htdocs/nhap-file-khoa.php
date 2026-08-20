<?php
/**
 * Nhập số liệu từ FILE THẬT của khoa (tên tự đặt, không cột mã).
 * Hai bước: (1) upload → máy tự khớp tên về chỉ tiêu chuẩn;
 *           (2) admin xác nhận ánh xạ → ghi số. Không import mù.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/danh_muc.php';
require_once __DIR__ . '/app/xlsx.php';

$toi = bat_buoc_quyen('solieu.nhap_excel');
$dsKhoa = danh_sach_khoa_hoat_dong();

$nam   = (int)($_POST['nam'] ?? $_GET['nam'] ?? NAM_MAC_DINH);
$idKhoa = (int)($_POST['khoa'] ?? $_GET['khoa'] ?? ($dsKhoa[0]['id'] ?? 0));
$khoa = q1('SELECT * FROM khoa WHERE id = ?', [$idKhoa]);

// Tên (đã chuẩn hóa) bị bỏ qua khi dò dòng dữ liệu — tiêu đề / dòng phụ.
// Lưu ý: chuan_hoa_khop() bỏ vài từ đệm (trong, các…), nên viết dạng đã chuẩn hóa.
const BO_QUA_TEN = ['noi dung', 'mau cong suat', 'ngay thang',
    'tieu chi hoat dong', 'thuc hien', 'chi tieu', 'don vi tinh', 'don vi'];

/** Đọc file khoa → [['ten'=>, 'thang'=>[t=>v], 'giao'=>], ...] + cảnh báo. */
function doc_file_khoa(string $noiDung): array
{
    $bang = xlsx_doc($noiDung);
    if (!$bang) { return ['loi' => 'Không đọc được file (không phải .xlsx?).']; }

    // 1) Tìm cột "Nội dung" + hàng tiêu đề
    $colTen = null; $hangTD = null;
    foreach ($bang as $ri => $row) {
        foreach ($row as $ci => $v) {
            if (chuan_hoa_khop((string)$v) === 'noi dung') { $colTen = $ci; $hangTD = $ri; break 2; }
        }
    }
    if ($colTen === null) { $colTen = 1; $hangTD = 0; }   // mặc định cột B

    // 2) Dò cột Tháng 1..12 và cột "chỉ tiêu giao" trong vài hàng tiêu đề
    $colThang = []; $colGiao = null;
    for ($r = max(0, $hangTD - 1); $r <= $hangTD + 2; $r++) {
        foreach ($bang[$r] ?? [] as $ci => $v) {
            $s = chuan_hoa_khop((string)$v);
            if (preg_match('/^thang (\d{1,2})$/', $s, $m) && $m[1] >= 1 && $m[1] <= 12) {
                $colThang[(int)$m[1]] = $ci;
            } elseif ($colGiao === null && str_contains($s, 'giao')) {
                $colGiao = $ci;
            }
        }
    }

    // 3) Duyệt dòng dữ liệu
    $dong = [];
    foreach ($bang as $ri => $row) {
        if ($ri <= $hangTD + 2) { continue; }
        $ten = trim((string)($row[$colTen] ?? ''));
        if ($ten === '') { continue; }
        $chuan = chuan_hoa_khop($ten);
        if ($chuan === '' || strlen($ten) > 250) { continue; }   // strlen (byte) — né mbstring
        if (in_array($chuan, BO_QUA_TEN, true)) { continue; }   // dòng phụ / tiêu đề

        $thang = [];
        foreach ($colThang as $t => $ci) {
            $v = $row[$ci] ?? '';
            if (is_numeric($v) && trim((string)$v) !== '') { $thang[$t] = (float)$v; }
        }
        $giao = null;
        if ($colGiao !== null && is_numeric($row[$colGiao] ?? '')) { $giao = (float)$row[$colGiao]; }
        if (!$thang && $giao === null) { continue; }   // dòng không có số

        $dong[] = ['ten' => $ten, 'thang' => $thang, 'giao' => $giao];
    }
    return ['dong' => $dong, 'so_cot_thang' => count($colThang)];
}

$buoc = 'chon';            // chon | xacnhan
$dong = null;

if (la_post()) {
    kiem_tra_csrf();
    $viec = post('viec');

    /* -------- Bước 1: đọc & khớp -------- */
    if ($viec === 'doc' && $khoa) {
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            nhan_tin('loi', 'Chưa chọn file.');
        } else {
            $kq = doc_file_khoa(file_get_contents($_FILES['file']['tmp_name']));
            if (isset($kq['loi'])) {
                nhan_tin('loi', $kq['loi']);
            } elseif (!$kq['dong']) {
                nhan_tin('loi', 'Không tìm thấy dòng chỉ tiêu nào có số liệu trong file.');
            } else {
                $dong = [];
                foreach ($kq['dong'] as $d) {
                    $k = khop_thu_vien($d['ten']);
                    $d['goi_y'] = $k ? (int)$k['ct']['id'] : 0;
                    $d['diem']  = $k ? $k['diem'] : 0;
                    $dong[] = $d;
                }
                $buoc = 'xacnhan';
            }
        }
    }

    /* -------- Bước 2: xác nhận → ghi số -------- */
    if ($viec === 'nhap' && $khoa) {
        $tens = $_POST['ten']  ?? [];
        $gts  = $_POST['gt']   ?? [];   // JSON mỗi dòng
        $maps = $_POST['map']  ?? [];   // '' bỏ qua | 'moi' | id
        $soSL = 0; $soKH = 0; $soMoi = 0; $khoaMonth = [];
        db()->beginTransaction();
        foreach ($tens as $i => $ten) {
            $chon = (string)($maps[$i] ?? '');
            if ($chon === '') { continue; }
            $data = json_decode((string)($gts[$i] ?? '{}'), true) ?: [];

            if ($chon === 'moi') {
                $ma = ma_tu_ten($ten);
                $tt = (int)qVal('SELECT COALESCE(MAX(thu_tu),0) FROM chi_tieu') + 10;
                q('INSERT INTO chi_tieu (ma, ten, don_vi, thu_tu, loai_gia_tri, nguon, huong, phan_bo, la_chuan)
                   VALUES (?,?,?,?,?,?,?,?,1)', [$ma, $ten, '', $tt, 'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY']);
                $idCT = (int)db()->lastInsertId();
                $soMoi++;
            } else {
                $idCT = (int)$chon;
                $ct = q1('SELECT * FROM chi_tieu WHERE id = ?', [$idCT]);
                if (!$ct) { continue; }
                if ($ct['nguon'] !== 'NHAP_TAY') { continue; }   // tổng con / công thức: bỏ
            }
            // gán khoa nếu chưa có
            if (!qVal('SELECT 1 FROM chi_tieu_ap_dung WHERE id_chi_tieu=? AND id_khoa=?', [$idCT, $idKhoa])) {
                q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)', [$idCT, $idKhoa]);
            }

            // số liệu tháng (bỏ tháng thuộc kỳ đã khóa)
            foreach (($data['thang'] ?? []) as $t => $v) {
                $t = (int)$t;
                if ($t < 1 || $t > 12 || !is_numeric($v)) { continue; }
                // Bulk import: chỉ chặn kỳ đã CHỐT (khóa/đã duyệt) — sửa phải qua bút toán.
                if (in_array(trang_thai_ky($nam, $t, $idKhoa), ['DA_KHOA', 'DA_DUYET'], true)) {
                    $khoaMonth[$t] = true; continue;
                }
                // Portable (MySQL + SQLite): kiểm rồi UPDATE / INSERT, không dùng "INSERT OR"
                if (qVal('SELECT 1 FROM so_lieu WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
                        [$nam, $t, $idKhoa, $idCT])) {
                    q('UPDATE so_lieu SET gia_tri=?, nguoi_nhap=?, thoi_diem=CURRENT_TIMESTAMP
                        WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
                        [(float)$v, $toi['id'], $nam, $t, $idKhoa, $idCT]);
                } else {
                    q('INSERT INTO so_lieu (nam, thang, id_khoa, id_chi_tieu, gia_tri, nguoi_nhap)
                       VALUES (?,?,?,?,?,?)', [$nam, $t, $idKhoa, $idCT, (float)$v, $toi['id']]);
                }
                $soSL++;
            }
            // chỉ tiêu giao năm
            if (isset($data['giao']) && is_numeric($data['giao'])) {
                if (qVal('SELECT 1 FROM ke_hoach WHERE nam=? AND id_khoa=? AND id_chi_tieu=?',
                        [$nam, $idKhoa, $idCT])) {
                    q('UPDATE ke_hoach SET chi_tieu_giao=? WHERE nam=? AND id_khoa=? AND id_chi_tieu=?',
                        [(float)$data['giao'], $nam, $idKhoa, $idCT]);
                } else {
                    q('INSERT INTO ke_hoach (nam, id_khoa, id_chi_tieu, chi_tieu_giao) VALUES (?,?,?,?)',
                        [$nam, $idKhoa, $idCT, (float)$data['giao']]);
                }
                $soKH++;
            }
        }
        db()->commit();
        ghi_nhat_ky('NHAP_FILE_KHOA', $khoa['ma'],
            "Năm $nam — $soSL ô số liệu, $soKH chỉ tiêu giao, $soMoi chỉ tiêu mới");
        $tb = "Đã nhập $soSL ô số liệu, $soKH chỉ tiêu giao"
            . ($soMoi ? ", tạo $soMoi chỉ tiêu mới" : '') . " cho {$khoa['ten']}.";
        if ($khoaMonth) {
            $tb .= ' (Bỏ qua các tháng đã khóa: ' . implode(', ', array_keys($khoaMonth))
                . ' — muốn sửa phải qua bút toán điều chỉnh.)';
        }
        nhan_tin('ok', $tb);
        chuyen_huong("/nhap-file-khoa.php?nam=$nam&khoa=$idKhoa");
    }
}

// Danh sách chỉ tiêu nhập tay để chọn trong dropdown ánh xạ
$dsChon = qAll("SELECT id, ma, ten, id_cha FROM chi_tieu
                WHERE hoat_dong = 1 AND nguon = 'NHAP_TAY' ORDER BY thu_tu, id");

mo_trang('Nhập file khoa');
tab_nhap();
?>
<h1>Nhập số liệu từ file của khoa</h1>
<p class="phu">Tải lên đúng file Excel khoa đang dùng (tên tự đặt). Máy tự khớp về chỉ tiêu chuẩn,
  anh xác nhận rồi mới ghi số — không nhập mù.</p>

<?php if ($buoc === 'chon'): ?>
<form method="post" enctype="multipart/form-data" class="form-tai-khoan" style="max-width:640px">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="doc">
  <div class="luoi-truong">
    <label>Khoa
      <select name="khoa">
        <?php foreach ($dsKhoa as $k): ?>
          <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $idKhoa ? 'selected' : '' ?>><?= e($k['ten']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Năm
      <input type="text" inputmode="numeric" name="nam" value="<?= $nam ?>">
    </label>
    <label class="o-rong-2">File Excel của khoa (.xlsx — sheet đầu)
      <input type="file" name="file" accept=".xlsx" required>
    </label>
  </div>
  <div class="form-chan">
    <button class="nut nut-chinh" type="submit">Đọc &amp; khớp tên</button>
  </div>
</form>

<?php elseif ($buoc === 'xacnhan'): ?>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="nhap">
  <input type="hidden" name="nam" value="<?= $nam ?>">
  <input type="hidden" name="khoa" value="<?= $idKhoa ?>">
  <div class="tb tb-canh-bao">
    <strong>Soát ánh xạ trước khi ghi.</strong> Dòng nào khớp sẵn thì để nguyên; sai thì chọn lại;
    lạ thì <em>➕ Tạo chỉ tiêu mới</em> hoặc <em>— Bỏ qua —</em>. Khoa: <strong><?= e($khoa['ten']) ?></strong> · Năm <?= $nam ?>.
  </div>
  <div class="cuon-ngang">
  <table class="bang">
    <thead><tr><th>Tên trong file</th><th>Khớp với chỉ tiêu chuẩn</th><th>Số sẽ nhập</th></tr></thead>
    <tbody>
    <?php foreach ($dong as $i => $d):
        $tin = $d['diem'] >= 100 ? '' : ($d['goi_y'] ? 'can-soat' : 'chua-khop'); ?>
      <tr class="<?= $tin ?>">
        <td><?= e($d['ten']) ?>
          <?php if ($d['goi_y'] && $d['diem'] < 100): ?><span class="the the-nho the-rieng">kiểm lại (<?= $d['diem'] ?>%)</span><?php endif; ?>
        </td>
        <td>
          <input type="hidden" name="ten[<?= $i ?>]" value="<?= e($d['ten']) ?>">
          <input type="hidden" name="gt[<?= $i ?>]" value="<?= e(json_encode(['thang'=>$d['thang'],'giao'=>$d['giao']], JSON_UNESCAPED_UNICODE)) ?>">
          <select name="map[<?= $i ?>]">
            <option value="" <?= $d['goi_y'] ? '' : '' ?>>— Bỏ qua —</option>
            <option value="moi" <?= $d['goi_y'] ? '' : 'selected' ?>>➕ Tạo chỉ tiêu mới từ tên này</option>
            <?php foreach ($dsChon as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $d['goi_y'] ? 'selected' : '' ?>>
                <?= $c['id_cha'] ? '↳ ' : '' ?><?= e($c['ten']) ?> (<?= e($c['ma']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </td>
        <td class="nho">
          <?= count($d['thang']) ?> tháng<?= $d['giao'] !== null ? ' · giao ' . so($d['giao'], 0) : '' ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="form-chan" style="margin-top:1rem">
    <button class="nut nut-chinh" type="submit">Ghi số liệu vào <?= e($khoa['ten']) ?></button>
    <a class="nut nut-phu" href="/nhap-file-khoa.php?nam=<?= $nam ?>&khoa=<?= $idKhoa ?>">Hủy, chọn file khác</a>
  </div>
</form>
<?php endif; ?>
<?php dong_trang();

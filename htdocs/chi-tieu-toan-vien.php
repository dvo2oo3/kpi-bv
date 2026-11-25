<?php
/**
 * Chỉ tiêu TOÀN VIỆN — đặt số CHÍNH THỨC (theo bản SẾP/QĐ74) cho từng chỉ tiêu,
 * thay cho việc app tự cộng chỉ tiêu các khoa (nhiều khi lệch bản chính thức).
 *
 * Mỗi dòng cho thấy "Tự cộng khoa" (máy cộng, tham chiếu) cạnh ô "Chỉ tiêu toàn
 * viện (chính thức)". Để trống = tự cộng; nhập số = ĐÈ. Lưu vào so_toan_vien
 * (cột chi_tieu_giao) — báo cáo & xuất Excel đọc số này.
 *
 * NHẬP TỪ EXCEL SẾP: tải sheet "toàn viện" của bản SẾP → khớp dòng theo tên →
 * xem trước (có tự đoán THANG: ×1 hay ×1000 vì file SẾP ghi số không đồng nhất)
 * → duyệt rồi áp hàng loạt.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/danh_muc.php';
require_once __DIR__ . '/app/bao_cao.php';
require_once __DIR__ . '/app/xlsx.php';

bat_buoc_quyen('baocao.toan_vien');

$nam = nam_dang_lam();

// "120.000" → 120000 · "5,4" → 5.4 · "" → null  (giống $bcSoTay ở báo cáo)
$soTay = function ($v): ?float {
    $v = trim((string)$v);
    if ($v === '') { return null; }
    $v = str_replace(['.', ' '], '', $v);
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? (float)$v : null;
};

/* ---------- Lưu hàng loạt (sửa tay) ---------- */
if (la_post() && post('viec') === 'luu') {
    kiem_tra_csrf();
    $STV = so_toan_vien_cua_nam($nam);
    $soDoi = 0;
    foreach ((array)($_POST['kh'] ?? []) as $id => $raw) {
        $id  = (int)$id;
        $moi = $soTay($raw);
        $cu  = (isset($STV[$id]) && $STV[$id]['chi_tieu_giao'] !== null && $STV[$id]['chi_tieu_giao'] !== '')
             ? (float)$STV[$id]['chi_tieu_giao'] : null;
        if ($moi === $cu) { continue; }
        dat_so_toan_vien_o($nam, $id, 'kh', $moi);
        $soDoi++;
    }
    ghi_nhat_ky('CHI_TIEU_TOAN_VIEN', (string)$nam, "$soDoi dòng đổi");
    nhan_tin('ok', $soDoi > 0 ? "Đã lưu chỉ tiêu toàn viện ($soDoi dòng thay đổi)." : 'Không có dòng nào thay đổi.');
    chuyen_huong('/chi-tieu-toan-vien.php?nam=' . $nam);
}

/* ---------- Áp dụng kết quả import (sau khi xem trước) ---------- */
if (la_post() && post('viec') === 'nhap_ap') {
    kiem_tra_csrf();
    $ap   = $_POST['ap'] ?? [];      // id => giá trị (đã đoán thang, có thể sửa tay)
    $chon = array_map('intval', (array)($_POST['chon'] ?? []));
    $soAp = 0;
    foreach ($chon as $id) {
        $gt = $soTay($ap[$id] ?? '');
        if ($gt === null) { continue; }
        dat_so_toan_vien_o($nam, $id, 'kh', $gt);
        $soAp++;
    }
    ghi_nhat_ky('CHI_TIEU_TOAN_VIEN', (string)$nam, "nhập SẾP: $soAp dòng");
    nhan_tin('ok', "Đã nhập $soAp chỉ tiêu toàn viện từ file SẾP.");
    chuyen_huong('/chi-tieu-toan-vien.php?nam=' . $nam);
}

$bang = bang_toan_vien($nam, range(1, 12), 'giao');

/* ---------- Tải file & xem trước import ---------- */
$preview = null; $boQua = []; $loiNhap = null;
if (la_post() && post('viec') === 'nhap_file') {
    kiem_tra_csrf();
    $f = $_FILES['file'] ?? null;
    if (!$f || ($f['error'] ?? 1) !== 0 || !is_uploaded_file($f['tmp_name'])) {
        $loiNhap = 'Chưa chọn file hoặc tải lên lỗi.';
    } elseif (!xlsx_kha_dung()) {
        $loiNhap = 'Máy chủ thiếu zlib/XML để đọc .xlsx.';
    } else {
        $sheet = xlsx_doc_ten(file_get_contents($f['tmp_name']), 'toàn viện');
        if (!$sheet) {
            $loiNhap = 'Không tìm thấy (hoặc không đọc được) sheet "toàn viện" trong file.';
        } else {
            $mapTen = [];
            foreach ($bang as $d) { $mapTen[chuan_hoa_khop($d['ct']['ten'])] = $d; }
            $preview = [];
            foreach ($sheet as $row) {
                $ten  = trim($row[1] ?? '');
                $cell = trim((string)($row[3] ?? ''));          // ô xlsx: số dạng "78.475" = 78,475 (chấm = thập phân)
                $raw  = ($cell !== '' && is_numeric($cell)) ? (float)$cell : null;
                if ($ten === '' || $raw === null) { continue; }
                $k = chuan_hoa_khop($ten);
                if (!isset($mapTen[$k])) { $boQua[] = $ten; continue; }
                $d   = $mapTen[$k];
                $le  = in_array($d['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true) ? 1 : 0;
                $sum = $d['may_cong_kh'];
                // Đoán THANG (file SẾP ghi lẫn số thật & số nghìn):
                //  - có tổng khoa  → chọn raw hay raw×1000, cái nào gần tổng hơn.
                //  - không tổng khoa + là SỐ ĐẾM có phần lẻ (vd 180,8) → gần như chắc là nghìn.
                $goiY = $raw;
                if ($sum !== null && $sum != 0 && $raw != 0) {
                    $d1 = abs(log(abs($raw) / abs($sum)));
                    $d2 = abs(log(abs($raw * 1000) / abs($sum)));
                    if ($d2 < $d1) { $goiY = $raw * 1000; }
                } elseif ($sum === null && $le === 0 && $raw != (float)(int)$raw) {
                    $goiY = $raw * 1000;
                }
                $preview[] = ['id' => (int)$d['ct']['id'], 'tenApp' => $d['ct']['ten'], 'tenSep' => $ten,
                              'le' => $le, 'raw' => $raw, 'goiY' => $goiY, 'tuCong' => $sum, 'nhan2' => $goiY != $raw];
            }
        }
    }
}

$STV = so_toan_vien_cua_nam($nam);
$soRieng = count(array_filter($bang, fn($d) =>
    isset($STV[(int)$d['ct']['id']]) && $STV[(int)$d['ct']['id']]['chi_tieu_giao'] !== null
    && $STV[(int)$d['ct']['id']]['chi_tieu_giao'] !== ''));

mo_trang('Chỉ tiêu toàn viện');
?>
<div class="dau-muc">
  <div>
    <h1 style="margin:0">Chỉ tiêu toàn viện</h1>
    <p class="phu" style="margin:3px 0 0">Mặc định app <strong>tự cộng</strong> chỉ tiêu các khoa. Nếu bản chính thức
      (SẾP/QĐ74) khác tổng khoa, nhập <strong>số chính thức</strong> để đè — hoặc <strong>nhập từ file SẾP</strong> bên dưới.</p>
  </div>
  <div class="hang-nut">
    <a class="nut nut-phu" href="/doi-chieu-chi-tieu.php?nam=<?= (int)$nam ?>">Đối chiếu tổng khoa / viện →</a>
    <a class="nut nut-phu" href="/bao-cao.php?nam=<?= (int)$nam ?>&pv=toan_vien">Xem báo cáo toàn viện →</a>
  </div>
</div>

<?php if ($loiNhap): ?><div class="tb tb-loi"><?= e($loiNhap) ?></div><?php endif; ?>

<details class="the-hop" style="margin-bottom:14px"<?= $preview !== null ? ' open' : '' ?>>
  <summary style="cursor:pointer;font-weight:600">Nhập chỉ tiêu toàn viện từ Excel SẾP</summary>
  <p class="phu" style="margin:8px 0">Tải file SẾP (.xlsx) có sheet <strong>“toàn viện”</strong>. Hệ thống khớp dòng theo tên,
    tự đoán thang số (vì file SẾP ghi lẫn số thật và số nghìn), rồi cho anh <strong>xem trước &amp; duyệt</strong> trước khi áp.</p>
  <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="viec" value="nhap_file">
    <input type="file" name="file" accept=".xlsx" required>
    <button type="submit" class="nut">Tải lên &amp; xem trước</button>
  </form>

  <?php if ($preview !== null): ?>
    <?php if (!$preview): ?>
      <div class="tb tb-canh-bao" style="margin-top:12px">Không khớp được dòng nào (tên trong file khác tên chỉ tiêu). Kiểm tra lại sheet.</div>
    <?php else: ?>
    <form method="post" style="margin-top:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="viec" value="nhap_ap">
      <div class="phu" style="margin-bottom:8px">Khớp <strong><?= count($preview) ?></strong> dòng. Ô <span class="ctv-nhan ctv-nhan-lech">×1000</span>
        là đã tự nhân nghìn cho khớp số tự cộng — anh soát rồi sửa nếu cần, bỏ tích dòng không muốn áp.</div>
      <div class="cuon-ngang">
        <table class="bang bang-ctv">
          <thead><tr>
            <th style="width:36px"></th><th style="min-width:230px">Chỉ tiêu (khớp)</th>
            <th class="phai">Tự cộng khoa</th><th class="phai">Số trong file</th>
            <th class="phai" style="min-width:140px">Sẽ đặt</th>
          </tr></thead>
          <tbody>
            <?php foreach ($preview as $p): ?>
            <tr>
              <td class="giua"><input type="checkbox" name="chon[]" value="<?= $p['id'] ?>" checked></td>
              <td><?= e($p['tenApp']) ?><?php if (chuan_hoa_khop($p['tenApp']) !== chuan_hoa_khop($p['tenSep'])): ?>
                  <span class="phu nho">· SẾP: <?= e($p['tenSep']) ?></span><?php endif; ?></td>
              <td class="phai nho phu"><?= $p['tuCong'] === null ? '—' : so($p['tuCong'], $p['le']) ?></td>
              <td class="phai nho phu"><?= so($p['raw'], 2) ?></td>
              <td class="phai"><input type="text" name="ap[<?= $p['id'] ?>]" value="<?= e(so_o_nhap($p['goiY'])) ?>"
                     inputmode="decimal" style="width:120px;text-align:right">
                  <?php if ($p['nhan2']): ?><span class="ctv-nhan ctv-nhan-lech" title="Đã nhân nghìn">×1000</span><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($boQua): ?><p class="phu nho" style="margin-top:8px">Bỏ qua <?= count($boQua) ?> dòng không khớp tên:
        <?= e(implode(' · ', array_slice($boQua, 0, 12))) ?><?= count($boQua) > 12 ? '…' : '' ?></p><?php endif; ?>
      <div style="margin-top:12px"><button type="submit" class="nut nut-chinh">Áp dụng <?= count($preview) ?> dòng đã chọn</button></div>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</details>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="luu">
  <div class="the-hop" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px">
    <label>Năm
      <input type="number" name="nam" value="<?= (int)$nam ?>" onchange="location.href='/chi-tieu-toan-vien.php?nam='+this.value" style="width:100px">
    </label>
    <div style="flex:1" class="phu">
      <?= $soRieng > 0
        ? '<strong style="color:#b45309">' . (int)$soRieng . '</strong> chỉ tiêu đang đặt số riêng (khác tổng khoa).'
        : 'Chưa chỉ tiêu nào đặt số riêng — tất cả đang tự cộng khoa.' ?>
    </div>
    <button type="submit" class="nut nut-chinh">Lưu chỉ tiêu toàn viện</button>
  </div>

  <div class="cuon-ngang">
    <table class="bang bang-ctv">
      <thead>
        <tr>
          <th style="min-width:240px">Nội dung</th>
          <th>ĐVT</th>
          <th class="phai">Tự cộng khoa</th>
          <th class="phai" style="min-width:150px">Chỉ tiêu toàn viện <small class="nhan-phu">(chính thức)</small></th>
          <th>Trạng thái</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bang as $d):
            $id  = (int)$d['ct']['id'];
            $le  = in_array($d['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true) ? 1 : 0;
            $ov  = (isset($STV[$id]) && $STV[$id]['chi_tieu_giao'] !== null && $STV[$id]['chi_tieu_giao'] !== '')
                 ? (float)$STV[$id]['chi_tieu_giao'] : null;
            $rieng = $ov !== null;
            $lech  = $rieng && $d['may_cong_kh'] !== null && abs($ov - (float)$d['may_cong_kh']) > 0.001;
        ?>
          <tr class="<?= $d['cap'] ? 'dong-con' : '' ?><?= $rieng ? ' ctv-rieng' : '' ?>">
            <td><?= $d['cap'] ? '<span class="thut">↳</span> ' : '' ?><?= e($d['ct']['ten']) ?></td>
            <td class="nho"><?= e($d['ct']['don_vi']) ?></td>
            <td class="phai nho phu"><?= $d['may_cong_kh'] === null ? '—' : so($d['may_cong_kh'], $le) ?></td>
            <td class="phai">
              <input type="text" name="kh[<?= $id ?>]" value="<?= $ov === null ? '' : e(so_o_nhap($ov)) ?>"
                     placeholder="<?= $d['may_cong_kh'] === null ? '' : e(so($d['may_cong_kh'], $le)) ?>"
                     class="o-ctv" inputmode="decimal" style="width:120px;text-align:right">
            </td>
            <td class="nho">
              <?php if ($lech): ?><span class="ctv-nhan ctv-nhan-lech" title="Số chính thức khác tổng khoa">riêng · ≠ tổng</span>
              <?php elseif ($rieng): ?><span class="ctv-nhan">riêng</span>
              <?php else: ?><span class="phu">tự cộng</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:14px"><button type="submit" class="nut nut-chinh">Lưu chỉ tiêu toàn viện</button></div>
</form>
<?php dong_trang();

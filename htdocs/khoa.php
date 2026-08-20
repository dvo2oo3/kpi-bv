<?php
/**
 * Quản lý danh mục khoa.
 *
 * Không xóa cứng khoa đã có số liệu — chỉ cho "ngừng hoạt động",
 * để báo cáo các năm trước vẫn dựng lại được nguyên vẹn.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/danh_muc.php';

$toi = bat_buoc_quyen('khoa.xem');
$duocSua = co_quyen('khoa.sua');

const LOAI_KHOA = [
    'NOI_TRU'      => 'Nội trú (có giường bệnh)',
    'NGOAI_TRU'    => 'Ngoại trú',
    'CAN_LAM_SANG' => 'Cận lâm sàng',
];

/** Khoa đã phát sinh dữ liệu thì không được xóa. */
function khoa_co_du_lieu(int $id): int
{
    return (int)qVal('SELECT COUNT(*) FROM so_lieu WHERE id_khoa = ?', [$id])
         + (int)qVal('SELECT COUNT(*) FROM ke_hoach WHERE id_khoa = ?', [$id]);
}

if (la_post()) {
    kiem_tra_csrf();
    $viec = post('viec');

    // ---------- Thêm khoa ----------
    if ($viec === 'them' && co_quyen('khoa.them')) {
        $ma  = chu_hoa(post('ma'));
        $ten = post('ten');
        $loai = post('loai', 'NOI_TRU');
        $gb  = (int)post('giuong_benh', '0');

        if ($ten === '') {
            nhan_tin('loi', 'Vui lòng nhập tên khoa.');
        } elseif ($ma !== '' && !preg_match('/^[A-Z0-9_]{2,20}$/', $ma)) {
            nhan_tin('loi', 'Mã khoa chỉ gồm chữ in hoa, số và gạch dưới, dài 2–20 ký tự.');
        } elseif (!isset(LOAI_KHOA[$loai])) {
            nhan_tin('loi', 'Loại khoa không hợp lệ.');
        } elseif ($ma !== '' && qVal('SELECT 1 FROM khoa WHERE ma = ?', [$ma])) {
            nhan_tin('loi', "Mã khoa \"$ma\" đã tồn tại.");
        } else {
            if ($ma === '') { $ma = ma_khoa_tu_ten($ten); }
            $thuTu = (int)qVal('SELECT COALESCE(MAX(thu_tu),0) FROM khoa') + 1;
            db()->beginTransaction();
            q('INSERT INTO khoa (ma, ten, loai, giuong_benh, thu_tu) VALUES (?,?,?,?,?)',
                [$ma, $ten, $loai, $loai === 'NOI_TRU' ? $gb : 0, $thuTu]);
            $idMoi = (int)db()->lastInsertId();

            // Gán sẵn bộ chỉ tiêu giống một khoa cùng loại để khỏi phải tick tay 59 dòng
            $mau = q1('SELECT id FROM khoa WHERE loai = ? AND id <> ? AND hoat_dong = 1
                       ORDER BY thu_tu LIMIT 1', [$loai, $idMoi]);
            $soCT = 0;
            if ($mau) {
                foreach (qAll('SELECT id_chi_tieu FROM chi_tieu_ap_dung WHERE id_khoa = ?',
                        [$mau['id']]) as $r) {
                    q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)',
                        [$r['id_chi_tieu'], $idMoi]);
                    $soCT++;
                }
            }
            db()->commit();
            ghi_nhat_ky('THEM_KHOA', $ma, $ten);
            nhan_tin('ok', "Đã thêm khoa \"$ten\"."
                . ($soCT ? " Đã gán sẵn $soCT chỉ tiêu theo mẫu khoa cùng loại — "
                         . 'vào Thư viện chỉ tiêu để chỉnh lại cho đúng.'
                         : ' Cần vào Thư viện chỉ tiêu để gán chỉ tiêu cho khoa này.'));
        }
        chuyen_huong('/khoa.php');
    }

    // ---------- Sửa khoa ----------
    if ($viec === 'sua' && $duocSua) {
        $id  = (int)post('id');
        $cu  = q1('SELECT * FROM khoa WHERE id = ?', [$id]);
        $ten = post('ten');
        $loai = post('loai');
        $gb  = (int)post('giuong_benh', '0');
        $thuTu = (int)post('thu_tu', '0');

        if (!$cu) {
            nhan_tin('loi', 'Không tìm thấy khoa.');
        } elseif ($ten === '') {
            nhan_tin('loi', 'Tên khoa không được để trống.');
        } elseif (!isset(LOAI_KHOA[$loai])) {
            nhan_tin('loi', 'Loại khoa không hợp lệ.');
        } else {
            q('UPDATE khoa SET ten=?, loai=?, giuong_benh=?, thu_tu=? WHERE id=?',
                [$ten, $loai, $loai === 'NOI_TRU' ? $gb : 0, $thuTu, $id]);
            ghi_nhat_ky('SUA_KHOA', $cu['ma'],
                "Giường bệnh: {$cu['giuong_benh']} → $gb");
            nhan_tin('ok', "Đã cập nhật khoa \"$ten\".");
            if ((int)$cu['giuong_benh'] !== $gb) {
                nhan_tin('canh-bao', 'Số giường bệnh đã đổi. Công suất sử dụng giường bệnh '
                    . 'của các kỳ trước sẽ được tính lại theo số mới — kiểm tra lại báo cáo đã nộp.');
            }
        }
        chuyen_huong('/khoa.php');
    }

    // ---------- Ngừng / mở lại ----------
    if ($viec === 'doi_trang_thai' && co_quyen('khoa.ngung')) {
        $id = (int)post('id');
        $cu = q1('SELECT * FROM khoa WHERE id = ?', [$id]);
        if (!$cu) {
            nhan_tin('loi', 'Không tìm thấy khoa.');
        } else {
            $moi = (int)$cu['hoat_dong'] === 1 ? 0 : 1;
            q('UPDATE khoa SET hoat_dong = ? WHERE id = ?', [$moi, $id]);
            ghi_nhat_ky($moi ? 'MO_LAI_KHOA' : 'NGUNG_KHOA', $cu['ma']);
            nhan_tin('ok', ($moi ? 'Đã mở lại khoa "' : 'Đã ngừng hoạt động khoa "')
                . $cu['ten'] . '". Số liệu cũ vẫn được giữ nguyên.');
        }
        chuyen_huong('/khoa.php');
    }

    // ---------- Xóa vĩnh viễn (chỉ dev, chỉ khi chưa có dữ liệu) ----------
    if ($viec === 'xoa') {
        $id = (int)post('id');
        $cu = q1('SELECT * FROM khoa WHERE id = ?', [$id]);
        if (!co_quyen('khoa.xoa')) {
            ghi_nhat_ky('TU_CHOI_XOA_KHOA', $cu['ma'] ?? (string)$id);
            nhan_tin('loi', 'Chỉ người phát triển mới xóa vĩnh viễn được khoa.');
        } elseif (!$cu) {
            nhan_tin('loi', 'Không tìm thấy khoa.');
        } elseif (($n = khoa_co_du_lieu($id)) > 0) {
            nhan_tin('loi', "Khoa \"{$cu['ten']}\" đã có $n dòng số liệu/kế hoạch nên không xóa được. "
                . 'Dùng nút "Ngừng hoạt động" thay thế.');
        } else {
            q('DELETE FROM khoa WHERE id = ?', [$id]);
            ghi_nhat_ky('XOA_KHOA', $cu['ma'], $cu['ten']);
            nhan_tin('ok', "Đã xóa khoa \"{$cu['ten']}\".");
        }
        chuyen_huong('/khoa.php');
    }
}

$dsKhoa = qAll('SELECT * FROM khoa ORDER BY thu_tu, ten');
$tongGB = (int)qVal('SELECT COALESCE(SUM(giuong_benh),0) FROM khoa WHERE hoat_dong = 1');

mo_trang('Danh mục khoa');
?>
<h1>Danh mục khoa</h1>
<p class="phu">
  <?= count($dsKhoa) ?> khoa · tổng <strong><?= $tongGB ?></strong> giường bệnh kế hoạch.
  Số giường bệnh ở đây là mẫu số khi tính công suất sử dụng giường bệnh.
</p>

<p class="hang-nut" style="margin:.25rem 0 1rem">
  <input type="search" class="o-tim" data-tim="#bang-khoa" data-dem="#khoa-dem"
         placeholder="Tìm mã / tên khoa…" autocomplete="off">
  <span id="khoa-dem" class="phu"></span>
</p>

<div class="cuon-ngang">
<table class="bang" id="bang-khoa">
  <thead>
    <tr>
      <th>TT</th><th>Mã</th><th>Tên khoa</th><th>Loại</th>
      <th class="phai">Giường</th><th class="phai">Chỉ tiêu</th>
      <th class="phai">Số liệu</th><th>Trạng thái</th>
      <?php if ($duocSua): ?><th>Thao tác</th><?php endif; ?>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($dsKhoa as $k):
      $id = (int)$k['id'];
      $soCT = (int)qVal('SELECT COUNT(*) FROM chi_tieu_ap_dung WHERE id_khoa = ?', [$id]);
      $soSL = (int)qVal('SELECT COUNT(*) FROM so_lieu WHERE id_khoa = ? AND gia_tri IS NOT NULL', [$id]); ?>
    <tr class="<?= (int)$k['hoat_dong'] === 1 ? '' : 'dong-mo' ?>">
      <td class="phai nho"><?= (int)$k['thu_tu'] ?></td>
      <td><code><?= e($k['ma']) ?></code></td>
      <td><strong><?= e($k['ten']) ?></strong></td>
      <td class="nho"><?= e(LOAI_KHOA[$k['loai']] ?? $k['loai']) ?></td>
      <td class="phai"><?= $k['loai'] === 'NOI_TRU' ? (int)$k['giuong_benh'] : '—' ?></td>
      <td class="phai nho"><?= $soCT ?></td>
      <td class="phai nho"><?= so((float)$soSL) ?></td>
      <td><?= (int)$k['hoat_dong'] === 1
              ? '<span class="trang-thai bat">Hoạt động</span>'
              : '<span class="trang-thai tat">Ngừng</span>' ?></td>
      <?php if ($duocSua): ?>
      <td class="thao-tac">
        <button type="button" class="nut nut-nho nut-phu" data-mo="sua-khoa-<?= $id ?>">Sửa</button>

        <div class="lop-phu" id="sua-khoa-<?= $id ?>" hidden>
         <div class="hop-modal" role="dialog" aria-modal="true" aria-label="Sửa khoa">
          <div class="modal-dau">
            <h2>Sửa khoa <code><?= e($k['ma']) ?></code></h2>
            <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
          </div>
          <div class="modal-than">
            <form method="post" class="form-tai-khoan">
              <?= csrf_field() ?>
              <input type="hidden" name="viec" value="sua">
              <input type="hidden" name="id" value="<?= $id ?>">
              <div class="luoi-truong">
                <label class="o-rong-2">Tên khoa
                  <input type="text" name="ten" value="<?= e($k['ten']) ?>" required></label>
                <label>Loại
                  <select name="loai">
                    <?php foreach (LOAI_KHOA as $ma => $ten): ?>
                      <option value="<?= $ma ?>" <?= $k['loai'] === $ma ? 'selected' : '' ?>>
                        <?= e($ten) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label>Giường bệnh kế hoạch
                  <input type="text" inputmode="numeric" name="giuong_benh"
                         value="<?= (int)$k['giuong_benh'] ?>"></label>
                <label>Thứ tự hiển thị
                  <input type="text" inputmode="numeric" name="thu_tu" value="<?= (int)$k['thu_tu'] ?>"></label>
              </div>
              <div class="form-chan">
                <button class="nut nut-chinh" type="submit">Lưu thay đổi</button>
                <button type="button" class="nut nut-phu" data-dong>Hủy</button>
              </div>
            </form>
          </div>
         </div>
        </div>

        <?php if (co_quyen('khoa.ngung')): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="viec" value="doi_trang_thai">
          <input type="hidden" name="id" value="<?= $id ?>">
          <button class="nut nut-nho <?= (int)$k['hoat_dong'] === 1 ? 'nut-canh' : 'nut-phu' ?>"
                  type="submit"><?= (int)$k['hoat_dong'] === 1 ? 'Ngừng' : 'Mở lại' ?></button>
        </form>
        <?php endif; ?>

        <?php if (co_quyen('khoa.xoa') && $soSL === 0 && khoa_co_du_lieu($id) === 0): ?>
        <form method="post"
              data-xac-nhan="Xóa vĩnh viễn khoa <?= e($k['ma']) ?>? Không khôi phục được." data-xac-nhan-loai="nguy">
          <?= csrf_field() ?>
          <input type="hidden" name="viec" value="xoa">
          <input type="hidden" name="id" value="<?= $id ?>">
          <button class="nut nut-nho nut-nguy" type="submit">Xóa</button>
        </form>
        <?php endif; ?>
      </td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if (co_quyen('khoa.them')): ?>
<!-- Nút nổi góc màn hình: bấm mở popup thêm khoa -->
<button type="button" class="nut-noi" data-mo="modal-them-khoa" title="Thêm khoa">
  <span class="nut-noi-cong" aria-hidden="true">+</span><span class="nut-noi-chu">Thêm khoa</span>
</button>
<div class="lop-phu" id="modal-them-khoa" hidden>
 <div class="hop-modal" role="dialog" aria-modal="true" aria-label="Thêm khoa">
  <div class="modal-dau">
    <h2>Thêm khoa</h2>
    <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
  </div>
  <div class="modal-than">
    <form method="post" class="form-tai-khoan">
      <?= csrf_field() ?>
      <input type="hidden" name="viec" value="them">
      <div class="luoi-truong">
        <label>Mã khoa
          <input type="text" name="ma" placeholder="Để trống — tự tạo từ tên">
          <small class="nhan-phu">Để trống sẽ tự sinh từ tên</small></label>
        <label>Loại
          <select name="loai">
            <?php foreach (LOAI_KHOA as $ma => $ten): ?>
              <option value="<?= $ma ?>"><?= e($ten) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="o-rong-2">Tên khoa
          <input type="text" name="ten" placeholder="Khoa Răng Hàm Mặt" required></label>
        <label>Giường bệnh kế hoạch
          <input type="text" inputmode="numeric" name="giuong_benh" value="0"></label>
      </div>
      <p class="phu" style="margin:8px 0 0">
        Mã: chữ in hoa, số, gạch dưới — sau này không nên đổi. Giường bệnh chỉ dùng cho khoa nội trú.
        Khoa mới được gán sẵn bộ chỉ tiêu giống một khoa cùng loại; sau đó vào
        <a href="/danh-muc-chi-tieu.php">Thư viện chỉ tiêu</a> chỉnh lại cho đúng.
      </p>
      <div class="form-chan">
        <button class="nut nut-chinh" type="submit">Thêm khoa</button>
        <button type="button" class="nut nut-phu" data-dong>Đóng</button>
      </div>
    </form>
  </div>
 </div>
</div>
<?php endif; ?>
<?php dong_trang();

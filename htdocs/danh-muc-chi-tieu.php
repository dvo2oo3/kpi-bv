<?php
/**
 * Danh mục chỉ tiêu — xem và sửa.
 *
 * Thêm mới và nạp lại bộ mặc định nằm ở hai trang riêng:
 *   chi-tieu-them.php · chi-tieu-nap-mac-dinh.php
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/danh_muc.php';

$toi      = bat_buoc_quyen('chitieu.xem');
$duocThem = co_quyen('chitieu.them');
$duocSua  = co_quyen('chitieu.sua');
$laDev    = co_quyen('chitieu.cong_thuc');
$dsKhoa   = danh_sach_khoa_hoat_dong();

if (la_post()) {
    kiem_tra_csrf();
    $viec = post('viec');

    /* ---------- Sửa chỉ tiêu ---------- */
    if ($viec === 'sua' && $duocSua) {
        $id  = (int)post('id');
        $cu  = q1('SELECT * FROM chi_tieu WHERE id = ?', [$id]);
        $ten = post('ten');
        $donVi = post('don_vi');
        $thuTu = (int)post('thu_tu', '0');
        $f = doc_bieu_mau($laDev);

        if (!$cu) {
            nhan_tin('loi', 'Không tìm thấy chỉ tiêu.');
        } elseif ($ten === '') {
            nhan_tin('loi', 'Nội dung không được để trống.');
        } elseif (!$f) {
            nhan_tin('loi', 'Thông số chỉ tiêu không hợp lệ.');
        } elseif (la_he_thong($cu['ma'])) {
            // Chỉ tiêu hệ thống: giữ nguyên cách tính, chỉ cho sửa chữ hiển thị
            q('UPDATE chi_tieu SET ten=?, don_vi=?, thu_tu=? WHERE id=?',
                [$ten, $donVi, $thuTu, $id]);
            ghi_nhat_ky('SUA_CHI_TIEU', $cu['ma'], $ten);
            nhan_tin('ok', "Đã cập nhật \"$ten\". Đây là chỉ tiêu hệ thống nên cách tính giữ nguyên.");
        } else {
            q('UPDATE chi_tieu SET ten=?, don_vi=?, thu_tu=?, loai_gia_tri=?,
                 nguon=?, huong=?, phan_bo=? WHERE id=?',
                [$ten, $donVi, $thuTu, $f['loai'], $f['nguon'], $f['huong'], $f['phan_bo'], $id]);
            ghi_nhat_ky('SUA_CHI_TIEU', $cu['ma'], $ten);
            nhan_tin('ok', "Đã cập nhật chỉ tiêu \"$ten\".");
        }
        chuyen_huong('/danh-muc-chi-tieu.php');
    }

    /* ---------- Gán khoa áp dụng ---------- */
    if ($viec === 'ap_dung' && $duocSua) {
        $idCT = (int)post('id_chi_tieu');
        $chon = array_map('intval', $_POST['khoa'] ?? []);
        $ct = q1('SELECT * FROM chi_tieu WHERE id = ?', [$idCT]);
        if (!$ct) {
            nhan_tin('loi', 'Không tìm thấy chỉ tiêu.');
        } else {
            // Bỏ khoa đã có số liệu thì mất dòng đó khỏi báo cáo — cảnh báo trước
            $boMatDL = [];
            foreach (qAll('SELECT id_khoa FROM chi_tieu_ap_dung WHERE id_chi_tieu = ?', [$idCT]) as $r) {
                $idK = (int)$r['id_khoa'];
                if (!in_array($idK, $chon, true)) {
                    $n = (int)qVal('SELECT COUNT(*) FROM so_lieu
                                     WHERE id_chi_tieu=? AND id_khoa=? AND gia_tri IS NOT NULL',
                        [$idCT, $idK]);
                    if ($n > 0) {
                        $boMatDL[] = qVal('SELECT ma FROM khoa WHERE id=?', [$idK]) . " ($n)";
                    }
                }
            }
            db()->beginTransaction();
            q('DELETE FROM chi_tieu_ap_dung WHERE id_chi_tieu = ?', [$idCT]);
            foreach (array_unique($chon) as $idK) {
                q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)', [$idCT, $idK]);
            }
            $keoTheo = dong_bo_cha_con($idCT);
            db()->commit();

            ghi_nhat_ky('SUA_AP_DUNG_CHI_TIEU', $ct['ma'], 'Số khoa: ' . count($chon));
            nhan_tin('ok', 'Đã cập nhật phạm vi áp dụng.');
            foreach ($keoTheo as $g) {
                nhan_tin('canh-bao', $g);
            }
            if ($boMatDL) {
                nhan_tin('canh-bao', 'Các khoa sau đã có số liệu cho chỉ tiêu này nhưng vừa bị bỏ '
                    . 'khỏi phạm vi áp dụng: ' . implode(', ', $boMatDL)
                    . '. Số liệu vẫn còn trong CSDL nhưng không hiện trên báo cáo nữa.');
            }
        }
        chuyen_huong('/danh-muc-chi-tieu.php');
    }

    /* ---------- Ngừng sử dụng / dùng lại ---------- */
    if ($viec === 'doi_trang_thai' && co_quyen('chitieu.ngung')) {
        $id = (int)post('id');
        $cu = q1('SELECT * FROM chi_tieu WHERE id = ?', [$id]);
        if (!$cu) {
            nhan_tin('loi', 'Không tìm thấy chỉ tiêu.');
        } elseif (la_he_thong($cu['ma']) && (int)$cu['hoat_dong'] === 1) {
            nhan_tin('loi', "\"{$cu['ten']}\" là chỉ tiêu hệ thống, bộ máy tính toán đang "
                . 'tham chiếu tới nên không ngừng được.');
        } else {
            $moi = (int)$cu['hoat_dong'] === 1 ? 0 : 1;
            q('UPDATE chi_tieu SET hoat_dong = ? WHERE id = ?', [$moi, $id]);
            if (!$moi) {
                q('UPDATE chi_tieu SET hoat_dong = 0 WHERE id_cha = ?', [$id]);
            }
            ghi_nhat_ky($moi ? 'DUNG_LAI_CHI_TIEU' : 'NGUNG_CHI_TIEU', $cu['ma']);
            nhan_tin('ok', ($moi ? 'Đã dùng lại "' : 'Đã ngừng sử dụng "') . $cu['ten']
                . '". Số liệu cũ vẫn được giữ.');
        }
        chuyen_huong('/danh-muc-chi-tieu.php');
    }

    /* ---------- Xóa vĩnh viễn ---------- */
    if ($viec === 'xoa') {
        $id = (int)post('id');
        $cu = q1('SELECT * FROM chi_tieu WHERE id = ?', [$id]);
        if (!co_quyen('chitieu.xoa')) {
            ghi_nhat_ky('TU_CHOI_XOA_CHI_TIEU', $cu['ma'] ?? (string)$id);
            nhan_tin('loi', 'Chỉ người phát triển mới xóa vĩnh viễn được chỉ tiêu. '
                . 'Dùng nút "Ngừng dùng" thay thế.');
        } elseif (!$cu) {
            nhan_tin('loi', 'Không tìm thấy chỉ tiêu.');
        } elseif (la_he_thong($cu['ma'])) {
            nhan_tin('loi', "\"{$cu['ma']}\" là chỉ tiêu hệ thống, xóa sẽ hỏng công thức "
                . 'ngày điều trị trung bình và công suất giường bệnh.');
        } elseif (($n = chi_tieu_co_du_lieu($id)) > 0) {
            nhan_tin('loi', "Chỉ tiêu này đã có $n dòng số liệu/kế hoạch nên không xóa được. "
                . 'Dùng nút "Ngừng dùng".');
        } elseif (qVal('SELECT 1 FROM chi_tieu WHERE id_cha = ?', [$id])) {
            nhan_tin('loi', 'Còn nội dung nhỏ bên trong. Xóa các nội dung nhỏ trước.');
        } else {
            q('DELETE FROM chi_tieu WHERE id = ?', [$id]);
            ghi_nhat_ky('XOA_CHI_TIEU', $cu['ma'], $cu['ten']);
            nhan_tin('ok', "Đã xóa chỉ tiêu \"{$cu['ten']}\".");
        }
        chuyen_huong('/danh-muc-chi-tieu.php');
    }
}

/* ---------------- Hiển thị ---------------- */
$cay = cay_chi_tieu_day_du();
$soGoc = count(array_filter($cay, fn($c) => $c['cap'] === 0));

$apDung = [];
foreach (qAll('SELECT * FROM chi_tieu_ap_dung') as $r) {
    $apDung[(int)$r['id_chi_tieu']][] = (int)$r['id_khoa'];
}
$maKhoa = [];
foreach ($dsKhoa as $k) {
    $maKhoa[(int)$k['id']] = $k['ma'];
}

mo_trang('Danh mục chỉ tiêu');
?>
<div class="dau-muc">
  <div>
    <h1>Danh mục chỉ tiêu</h1>
    <p class="phu">
      <?= count($cay) ?> chỉ tiêu · <?= $soGoc ?> nội dung lớn.
      Chỉ tiêu <em>bằng tổng các nội dung nhỏ</em> và <em>tính theo công thức</em>
      không nhập tay, hệ thống tự tính.
    </p>
  </div>
  <div class="hang-nut">
    <?php if ($duocThem): ?>
      <a class="nut" href="/chi-tieu-them.php">+ Thêm chỉ tiêu</a>
    <?php endif; ?>
    <?php if (co_quyen('chitieu.nap_mac_dinh')): ?>
      <a class="nut nut-phu" href="/chi-tieu-nap-mac-dinh.php">Nạp danh mục mặc định</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$cay): ?>
  <div class="tb tb-canh-bao">
    Danh mục đang trống.
    <?php if (co_quyen('chitieu.nap_mac_dinh')): ?>
      <a href="/chi-tieu-nap-mac-dinh.php">Nạp bộ mặc định</a> rút từ file Excel,
    <?php endif; ?>
    <?php if ($duocThem): ?>
      hoặc <a href="/chi-tieu-them.php">tự thêm từng chỉ tiêu</a>.
    <?php endif; ?>
  </div>
<?php else: ?>

<div class="cuon-ngang">
<table class="bang bang-mot-dong">
  <thead>
    <tr>
      <th>Mã</th><th>Nội dung</th><th>Đơn vị</th><th>Loại</th>
      <th>Nguồn</th><th>Đánh giá</th><th>Khoa áp dụng</th><th>Trạng thái</th>
      <?php if ($duocSua): ?><th>Thao tác</th><?php endif; ?>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($cay as $ct):
      $id = (int)$ct['id'];
      $ds = $apDung[$id] ?? [];
      $heThong = la_he_thong($ct['ma']);
      $soDL = chi_tieu_co_du_lieu($id); ?>
    <tr class="<?= $ct['cap'] ? 'dong-con' : '' ?> <?= (int)$ct['hoat_dong'] ? '' : 'dong-mo' ?>">
      <td><code><?= e($ct['ma']) ?></code></td>
      <td>
        <?= $ct['cap'] ? '<span class="thut">↳</span> ' : '' ?><?= e($ct['ten']) ?>
        <?php if ($heThong): ?>
          <span class="the the-nho" title="Bộ máy tính toán tham chiếu tới mã này">hệ thống</span>
        <?php endif; ?>
        <?php if ($ct['cap'] === 0 && $duocThem): ?>
          <a class="them-con" href="/chi-tieu-them.php?cha=<?= $id ?>"
             title="Thêm nội dung nhỏ vào đây">+ nội dung nhỏ</a>
        <?php endif; ?>
      </td>
      <td class="nho"><?= e($ct['don_vi']) ?></td>
      <td class="nho"><?= e(NHAN[$ct['loai_gia_tri']] ?? $ct['loai_gia_tri']) ?></td>
      <td class="nho"><?= $ct['nguon'] === 'NHAP_TAY'
              ? '<span class="phu">Khoa nhập</span>'
              : '<strong>' . e(NHAN[$ct['nguon']]) . '</strong>' ?></td>
      <td class="nho"><?= e(NHAN[$ct['huong']] ?? $ct['huong']) ?></td>
      <td class="nho">
        <?php if ($ds):
            // Khoa áp dụng có thể tới 11 mã. Gói lại một dòng, trỏ chuột xem
            // đủ — nếu để xuống dòng thì dòng bảng cao gấp rưỡi dòng bên cạnh.
            $dsMa = implode(', ', array_map(fn($i) => $maKhoa[$i] ?? '?', $ds)); ?>
          <span class="ds-gon" title="<?= e($dsMa) ?>"><?= e($dsMa) ?></span>
        <?php else: ?>
          <span class="canh-bao-nho">chưa gán khoa</span>
        <?php endif; ?>
      </td>
      <td><?= (int)$ct['hoat_dong']
              ? '<span class="trang-thai bat">Đang dùng</span>'
              : '<span class="trang-thai tat">Ngừng</span>' ?></td>
      <?php if ($duocSua): ?>
      <td class="thao-tac">
        <button type="button" class="nut nut-nho nut-phu" data-mo="sua-<?= $id ?>">Sửa</button>
        <button type="button" class="nut nut-nho nut-phu" data-mo="khoa-<?= $id ?>">Khoa</button>

        <?php if (co_quyen('chitieu.ngung') && !($heThong && (int)$ct['hoat_dong'])): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="viec" value="doi_trang_thai">
          <input type="hidden" name="id" value="<?= $id ?>">
          <button class="nut nut-nho <?= (int)$ct['hoat_dong'] ? 'nut-nguy' : 'nut-phu' ?>"
                  type="submit"><?= (int)$ct['hoat_dong'] ? 'Ngừng dùng' : 'Dùng lại' ?></button>
        </form>
        <?php endif; ?>

        <?php if (co_quyen('chitieu.xoa') && !$heThong && $soDL === 0): ?>
        <form method="post"
              onsubmit="return confirm('Xóa vĩnh viễn chỉ tiêu <?= e($ct['ma']) ?>?')">
          <?= csrf_field() ?>
          <input type="hidden" name="viec" value="xoa">
          <input type="hidden" name="id" value="<?= $id ?>">
          <button class="nut nut-nho nut-nguy" type="submit">Xóa</button>
        </form>
        <?php endif; ?>

        <!-- Popup Sửa chỉ tiêu -->
        <div class="lop-phu" id="sua-<?= $id ?>" hidden>
         <div class="hop-modal" role="dialog" aria-modal="true" aria-label="Sửa chỉ tiêu">
          <div class="modal-dau">
            <h2>Sửa chỉ tiêu <code><?= e($ct['ma']) ?></code></h2>
            <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
          </div>
          <div class="modal-than">
            <form method="post" class="form-tai-khoan">
              <?= csrf_field() ?>
              <input type="hidden" name="viec" value="sua">
              <input type="hidden" name="id" value="<?= $id ?>">
              <div class="luoi-truong">
                <label class="o-rong-2">Nội dung
                  <input type="text" name="ten" value="<?= e($ct['ten']) ?>" required></label>
                <label>Đơn vị <input type="text" name="don_vi" value="<?= e($ct['don_vi']) ?>"></label>
                <label>Thứ tự
                  <input type="text" inputmode="numeric" name="thu_tu" value="<?= (int)$ct['thu_tu'] ?>"></label>
                <?php if (!$heThong): ?>
                  <label>Loại giá trị
                    <select name="loai_gia_tri">
                      <?php foreach (['DEM','TRUNG_BINH','TY_LE','HANG_SO'] as $v): ?>
                        <option value="<?= $v ?>" <?= $ct['loai_gia_tri'] === $v ? 'selected' : '' ?>>
                          <?= e(NHAN[$v]) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>Nguồn số liệu
                    <select name="nguon">
                      <?php foreach (['NHAP_TAY','TONG_CON'] as $v): ?>
                        <option value="<?= $v ?>" <?= $ct['nguon'] === $v ? 'selected' : '' ?>>
                          <?= e(NHAN[$v]) ?></option>
                      <?php endforeach; ?>
                      <?php if ($laDev): ?>
                        <option value="CONG_THUC" <?= $ct['nguon'] === 'CONG_THUC' ? 'selected' : '' ?>>
                          <?= e(NHAN['CONG_THUC']) ?></option>
                      <?php endif; ?>
                    </select>
                  </label>
                  <label>Cách đánh giá
                    <select name="huong">
                      <?php foreach (['CAO_TOT','THAP_TOT','DICH_CO_DINH'] as $v): ?>
                        <option value="<?= $v ?>" <?= $ct['huong'] === $v ? 'selected' : '' ?>>
                          <?= e(NHAN[$v]) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>Phân bổ ra tháng
                    <select name="phan_bo">
                      <?php foreach (['THEO_NGAY','KHONG_CHIA'] as $v): ?>
                        <option value="<?= $v ?>" <?= $ct['phan_bo'] === $v ? 'selected' : '' ?>>
                          <?= e(NHAN[$v]) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                <?php endif; ?>
              </div>
              <?php if ($heThong): ?>
                <p class="phu">Chỉ tiêu hệ thống: cách tính giữ nguyên, chỉ sửa được chữ hiển thị.</p>
              <?php endif; ?>
              <div class="form-chan">
                <button class="nut nut-chinh" type="submit">Lưu thay đổi</button>
                <button type="button" class="nut nut-phu" data-dong>Hủy</button>
              </div>
            </form>
          </div>
         </div>
        </div>

        <!-- Popup gán Khoa -->
        <div class="lop-phu" id="khoa-<?= $id ?>" hidden>
         <div class="hop-modal" role="dialog" aria-modal="true" aria-label="Khoa áp dụng">
          <div class="modal-dau">
            <h2>Khoa áp dụng — <code><?= e($ct['ma']) ?></code></h2>
            <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
          </div>
          <div class="modal-than">
            <form method="post" class="form-tai-khoan">
              <?= csrf_field() ?>
              <input type="hidden" name="viec" value="ap_dung">
              <input type="hidden" name="id_chi_tieu" value="<?= $id ?>">
              <fieldset class="nhom-khoa">
                <legend>Chọn khoa áp dụng chỉ tiêu này</legend>
                <div class="luoi-o-chon">
                  <?php foreach ($dsKhoa as $k): ?>
                    <label class="o-chon">
                      <input type="checkbox" name="khoa[]" value="<?= (int)$k['id'] ?>"
                        <?= in_array((int)$k['id'], $ds, true) ? 'checked' : '' ?>>
                      <span><strong><?= e($k['ma']) ?></strong> — <?= e($k['ten']) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </fieldset>
              <div class="form-chan">
                <button class="nut nut-chinh" type="submit">Lưu khoa áp dụng</button>
                <button type="button" class="nut nut-phu" data-dong>Hủy</button>
              </div>
            </form>
          </div>
         </div>
        </div>
      </td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php dong_trang();

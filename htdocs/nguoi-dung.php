<?php
/**
 * Quản lý người dùng.
 *
 * Ranh giới quan trọng:
 *   - Admin  : tạo/sửa được tài khoản vai trò "bacsi".
 *   - Dev    : ngoài ra còn tạo/sửa được "admin" và "dev".
 *   Admin KHÔNG tự nâng mình lên dev, cũng không đụng được tài khoản dev.
 */
require_once __DIR__ . '/app/layout.php';

$toi = bat_buoc_quyen('nguoidung.xem');
$laDev = $toi['vai_tro'] === 'dev';

/** Người đang đăng nhập có được thao tác lên tài khoản này không? */
function duoc_thao_tac(array $muc_tieu, bool $laDev): bool
{
    // Chỉ dev mới đụng được tài khoản dev hoặc admin
    if (in_array($muc_tieu['vai_tro'], ['dev', 'admin'], true)) {
        return $laDev;
    }
    return true;
}

$dsKhoa = qAll('SELECT id, ma, ten FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');

/* ---------------- Xử lý biểu mẫu ---------------- */
if (la_post()) {
    kiem_tra_csrf();
    $viec = post('viec');

    // ----- Thêm tài khoản -----
    if ($viec === 'them' && co_quyen('nguoidung.them')) {
        $taoOK  = false;   // sai thì mở lại popup để nhập tiếp
        $ten    = post('ten_dang_nhap');
        $hoTen  = post('ho_ten');
        $vaiTro = post('vai_tro', 'bacsi');
        $chucVu = post('chuc_vu');
        $dienThoai = post('dien_thoai');
        $khoaChon  = array_map('intval', $_POST['khoa'] ?? []);

        if (!$laDev && $vaiTro !== 'bacsi') {
            nhan_tin('loi', 'Chỉ người phát triển mới tạo được tài khoản quản trị.');
        } elseif (!preg_match('/^[a-zA-Z0-9._]{3,50}$/', $ten)) {
            nhan_tin('loi', 'Tên đăng nhập chỉ gồm chữ, số, dấu chấm và gạch dưới, dài 3–50 ký tự.');
        } elseif ($hoTen === '') {
            nhan_tin('loi', 'Vui lòng nhập họ tên.');
        } elseif (qVal('SELECT 1 FROM nguoi_dung WHERE ten_dang_nhap = ?', [$ten])) {
            nhan_tin('loi', 'Tên đăng nhập "' . $ten . '" đã tồn tại.');
        } elseif ($vaiTro === 'bacsi' && !$khoaChon) {
            nhan_tin('loi', 'Tài khoản bác sĩ phải được gán ít nhất một khoa.');
        } else {
            $mkTam = sinh_mat_khau_tam();
            db()->beginTransaction();
            q('INSERT INTO nguoi_dung
                 (ten_dang_nhap, mat_khau_hash, ho_ten, vai_tro, chuc_vu, dien_thoai, doi_mat_khau, nguoi_tao)
               VALUES (?,?,?,?,?,?,1,?)',
                [$ten, password_hash($mkTam, PASSWORD_DEFAULT), $hoTen, $vaiTro,
                 $chucVu ?: null, $dienThoai ?: null, $toi['id']]);
            $idMoi = (int)db()->lastInsertId();
            foreach (array_unique($khoaChon) as $idK) {
                q('INSERT INTO nguoi_dung_khoa (id_nguoi_dung, id_khoa) VALUES (?,?)',
                    [$idMoi, $idK]);
            }
            db()->commit();

            ghi_nhat_ky('TAO_NGUOI_DUNG', $ten, 'Vai trò: ' . $vaiTro);
            // Không gửi được email → hiển thị mật khẩu tạm một lần duy nhất
            nhan_tin('ok', "Đã tạo tài khoản \"{$ten}\". Mật khẩu tạm: {$mkTam} "
                . '— hãy chép lại và giao tận tay, hệ thống sẽ không hiển thị lại. '
                . 'Người dùng bắt buộc đổi mật khẩu ở lần đăng nhập đầu.');
            $taoOK = true;
        }
        chuyen_huong('/nguoi-dung.php' . ($taoOK ? '' : '?mo_them=1'));
    }

    // ----- Cấp lại mật khẩu -----
    if ($viec === 'cap_lai_mk' && co_quyen('nguoidung.doi_mat_khau')) {
        $id = (int)post('id');
        $mt = q1('SELECT * FROM nguoi_dung WHERE id = ?', [$id]);
        if (!$mt) {
            nhan_tin('loi', 'Không tìm thấy tài khoản.');
        } elseif (!duoc_thao_tac($mt, $laDev)) {
            nhan_tin('loi', 'Bạn không có quyền với tài khoản này.');
        } else {
            $mkTam = sinh_mat_khau_tam();
            q('UPDATE nguoi_dung SET mat_khau_hash = ?, doi_mat_khau = 1,
                 so_lan_sai = 0, khoa_den = NULL WHERE id = ?',
                [password_hash($mkTam, PASSWORD_DEFAULT), $id]);
            ghi_nhat_ky('CAP_LAI_MAT_KHAU', $mt['ten_dang_nhap']);
            nhan_tin('ok', "Mật khẩu mới của \"{$mt['ten_dang_nhap']}\": {$mkTam} "
                . '— chép lại ngay, không hiển thị lại lần nữa.');
        }
        chuyen_huong('/nguoi-dung.php');
    }

    // ----- Mở khóa tạm (do gõ sai mật khẩu quá nhiều), giữ nguyên mật khẩu -----
    if ($viec === 'mo_khoa_tam' && co_quyen('nguoidung.doi_mat_khau')) {
        $id = (int)post('id');
        $mt = q1('SELECT * FROM nguoi_dung WHERE id = ?', [$id]);
        if (!$mt) {
            nhan_tin('loi', 'Không tìm thấy tài khoản.');
        } elseif (!duoc_thao_tac($mt, $laDev)) {
            nhan_tin('loi', 'Bạn không có quyền với tài khoản này.');
        } else {
            q('UPDATE nguoi_dung SET so_lan_sai = 0, khoa_den = NULL WHERE id = ?', [$id]);
            ghi_nhat_ky('MO_KHOA_TAM', $mt['ten_dang_nhap']);
            nhan_tin('ok', "Đã mở khóa tạm cho \"{$mt['ten_dang_nhap']}\" — "
                . 'đăng nhập lại được ngay, mật khẩu giữ nguyên.');
        }
        chuyen_huong('/nguoi-dung.php');
    }

    // ----- Bật / tắt tài khoản -----
    if ($viec === 'doi_trang_thai' && co_quyen('nguoidung.khoa')) {
        $id = (int)post('id');
        $mt = q1('SELECT * FROM nguoi_dung WHERE id = ?', [$id]);
        if (!$mt) {
            nhan_tin('loi', 'Không tìm thấy tài khoản.');
        } elseif ($id === (int)$toi['id']) {
            nhan_tin('loi', 'Không thể tự vô hiệu hóa tài khoản của chính mình.');
        } elseif (!duoc_thao_tac($mt, $laDev)) {
            nhan_tin('loi', 'Bạn không có quyền với tài khoản này.');
        } elseif ($mt['vai_tro'] === 'dev'
            && (int)qVal("SELECT COUNT(*) FROM nguoi_dung WHERE vai_tro='dev' AND hoat_dong=1") <= 1
            && (int)$mt['hoat_dong'] === 1) {
            nhan_tin('loi', 'Không thể vô hiệu hóa tài khoản phát triển cuối cùng.');
        } else {
            $moi = (int)$mt['hoat_dong'] === 1 ? 0 : 1;
            q('UPDATE nguoi_dung SET hoat_dong = ? WHERE id = ?', [$moi, $id]);
            ghi_nhat_ky($moi ? 'MO_TAI_KHOAN' : 'KHOA_TAI_KHOAN', $mt['ten_dang_nhap']);
            nhan_tin('ok', ($moi ? 'Đã mở lại ' : 'Đã vô hiệu hóa ') . 'tài khoản "'
                . $mt['ten_dang_nhap'] . '".');
        }
        chuyen_huong('/nguoi-dung.php');
    }

    // ----- Ủy quyền riêng cho một người -----
    if ($viec === 'uy_quyen' && co_quyen('nguoidung.uy_quyen')) {
        $id = (int)post('id');
        $mt = q1('SELECT * FROM nguoi_dung WHERE id = ?', [$id]);
        $chon = array_values(array_intersect(
            (array)($_POST['quyen'] ?? []), array_keys(QUYEN_CO_THE_UY)));

        if (!$mt) {
            nhan_tin('loi', 'Không tìm thấy tài khoản.');
        } elseif (!duoc_thao_tac($mt, $laDev)) {
            nhan_tin('loi', 'Bạn không có quyền với tài khoản này.');
        } else {
            db()->beginTransaction();
            q('DELETE FROM quyen_nguoi_dung WHERE id_nguoi_dung = ?', [$id]);
            foreach (array_unique($chon) as $qn) {
                q('INSERT INTO quyen_nguoi_dung (id_nguoi_dung, quyen, nguoi_cap)
                   VALUES (?,?,?)', [$id, $qn, $toi['id']]);
            }
            db()->commit();
            ghi_nhat_ky('UY_QUYEN', $mt['ten_dang_nhap'],
                $chon ? implode(', ', $chon) : 'thu hồi hết');
            nhan_tin('ok', $chon
                ? 'Đã ủy ' . count($chon) . ' quyền cho "' . $mt['ho_ten'] . '".'
                : 'Đã thu hồi toàn bộ quyền ủy riêng của "' . $mt['ho_ten'] . '".');
        }
        chuyen_huong('/nguoi-dung.php');
    }

    // ----- Gán khoa -----
    if ($viec === 'gan_khoa' && co_quyen('nguoidung.sua')) {
        $id = (int)post('id');
        $mt = q1('SELECT * FROM nguoi_dung WHERE id = ?', [$id]);
        $khoaChon = array_map('intval', $_POST['khoa'] ?? []);
        if (!$mt) {
            nhan_tin('loi', 'Không tìm thấy tài khoản.');
        } elseif (!duoc_thao_tac($mt, $laDev)) {
            nhan_tin('loi', 'Bạn không có quyền với tài khoản này.');
        } elseif ($mt['vai_tro'] === 'bacsi' && !$khoaChon) {
            nhan_tin('loi', 'Tài khoản bác sĩ phải được gán ít nhất một khoa.');
        } else {
            db()->beginTransaction();
            q('DELETE FROM nguoi_dung_khoa WHERE id_nguoi_dung = ?', [$id]);
            foreach (array_unique($khoaChon) as $idK) {
                q('INSERT INTO nguoi_dung_khoa (id_nguoi_dung, id_khoa) VALUES (?,?)',
                    [$id, $idK]);
            }
            db()->commit();
            ghi_nhat_ky('GAN_KHOA', $mt['ten_dang_nhap'], 'Số khoa: ' . count($khoaChon));
            nhan_tin('ok', 'Đã cập nhật khoa phụ trách của "' . $mt['ten_dang_nhap'] . '".');
        }
        chuyen_huong('/nguoi-dung.php');
    }
}

/* ---------------- Hiển thị ---------------- */

// Admin không nhìn thấy tài khoản dev
$dieuKien = $laDev ? '1=1' : "vai_tro <> 'dev'";
$dsNguoiDung = qAll(
    "SELECT nd.*
       FROM nguoi_dung nd
      WHERE {$dieuKien}
      ORDER BY CASE nd.vai_tro WHEN 'dev' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END, nd.ho_ten"
);

// Khoa phụ trách: gom trong PHP thay vì GROUP_CONCAT để câu lệnh chạy được
// trên mọi hệ quản trị CSDL (MySQL, MariaDB, SQLite).
$khoaCuaNguoi = [];   // id người dùng => [id khoa]
$maKhoaCuaNguoi = []; // id người dùng => [mã khoa]
foreach (qAll(
    'SELECT ndk.id_nguoi_dung, ndk.id_khoa, k.ma
       FROM nguoi_dung_khoa ndk JOIN khoa k ON k.id = ndk.id_khoa
      ORDER BY k.thu_tu') as $r) {
    $khoaCuaNguoi[(int)$r['id_nguoi_dung']][]   = (int)$r['id_khoa'];
    $maKhoaCuaNguoi[(int)$r['id_nguoi_dung']][] = $r['ma'];
}

mo_trang('Quản lý người dùng');
?>
<div class="dau-muc">
  <div>
    <h1>Quản lý người dùng</h1>
    <p class="phu">
      <?php if ($laDev): ?>
        Bạn đang ở vai trò <strong>Người phát triển</strong> — thấy và thao tác được mọi tài khoản.
      <?php else: ?>
        Bạn quản lý được tài khoản <strong>Bác sĩ / Người nhập</strong>.
        Tài khoản phát triển không hiển thị ở đây.
      <?php endif; ?>
    </p>
  </div>
  <?php if (co_quyen('nguoidung.them')): ?>
    <div class="hang-nut">
      <button type="button" class="nut" data-mo="modal-them">+ Thêm tài khoản</button>
    </div>
  <?php endif; ?>
</div>

<div class="cuon-ngang">
<table class="bang">
  <thead>
    <tr>
      <th>Họ tên</th><th>Tên đăng nhập</th><th>Vai trò</th>
      <th>Khoa phụ trách</th><th>Quyền ủy thêm</th><th>Đăng nhập cuối</th>
      <th>Trạng thái</th><th>Thao tác</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($dsNguoiDung as $u):
      $sua = duoc_thao_tac($u, $laDev);
      $laToi = (int)$u['id'] === (int)$toi['id']; ?>
    <tr class="<?= (int)$u['hoat_dong'] === 1 ? '' : 'dong-mo' ?>">
      <td>
        <!-- Dòng chức vụ luôn hiện, để trống thì gạch ngang: người này có
             chức vụ người kia không thì dòng bảng cao thấp so le. -->
        <div class="o-ten-nd">
          <?= e($u['ho_ten']) ?>
          <?php if ($laToi): ?><span class="the the-nho">bạn</span><?php endif; ?>
          <?php if ((int)$u['doi_mat_khau'] === 1): ?>
            <span class="the the-nho the-cho">chờ đổi MK</span>
          <?php endif; ?>
        </div>
        <small class="phu"><?= $u['chuc_vu'] ? e($u['chuc_vu']) : '—' ?></small>
      </td>
      <td><code><?= e($u['ten_dang_nhap']) ?></code></td>
      <td><span class="the the-<?= e($u['vai_tro']) ?>"><?= e(ten_vai_tro($u['vai_tro'])) ?></span></td>
      <?php $maKhoa = $maKhoaCuaNguoi[(int)$u['id']] ?? []; ?>
      <td><?= $maKhoa ? e(implode(', ', $maKhoa)) : '<span class="phu">—</span>' ?></td>
      <?php $qUy = $quyenCuaNguoi[(int)$u['id']] ?? []; ?>
      <td class="nho">
        <?php if ($u['vai_tro'] === 'dev'): ?>
          <span class="phu">toàn quyền</span>
        <?php elseif ($qUy): ?>
          <span class="the the-nho" title="<?= e(implode(', ',
              array_map(fn($x) => QUYEN_CO_THE_UY[$x] ?? $x, $qUy))) ?>">
            +<?= count($qUy) ?> quyền</span>
        <?php else: ?><span class="phu">—</span><?php endif; ?>
      </td>
      <td><?= e(ngay_gio($u['lan_dang_nhap_cuoi'])) ?></td>
      <?php $tamKhoa = !empty($u['khoa_den']) && strtotime($u['khoa_den']) > time(); ?>
      <td><?= (int)$u['hoat_dong'] === 1
              ? '<span class="trang-thai bat">Hoạt động</span>'
              : '<span class="trang-thai tat">Vô hiệu hóa</span>' ?>
        <?php if ($tamKhoa): ?>
          <br><span class="the the-nho the-cho" title="Do gõ sai mật khẩu quá nhiều lần">
            tạm khóa đến <?= date('H:i', strtotime($u['khoa_den'])) ?></span>
        <?php endif; ?>
      </td>
      <td class="thao-tac">
        <?php if (!$sua): ?>
          <span class="phu">Không có quyền</span>
        <?php else: ?>
          <?php if ($u['vai_tro'] === 'bacsi'): ?>
            <button type="button" class="nut nut-nho nut-phu" data-mo="gankhoa-<?= (int)$u['id'] ?>">Gán khoa</button>
            <div class="lop-phu" id="gankhoa-<?= (int)$u['id'] ?>" hidden>
             <div class="hop-modal" role="dialog" aria-modal="true" aria-label="Gán khoa">
              <div class="modal-dau">
                <h2>Gán khoa cho <?= e($u['ho_ten']) ?></h2>
                <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
              </div>
              <div class="modal-than">
                <form method="post" class="form-tai-khoan">
                  <?= csrf_field() ?>
                  <input type="hidden" name="viec" value="gan_khoa">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <fieldset class="nhom-khoa">
                    <legend>Khoa phụ trách</legend>
                    <div class="luoi-o-chon">
                      <?php foreach ($dsKhoa as $k): ?>
                        <label class="o-chon">
                          <input type="checkbox" name="khoa[]" value="<?= (int)$k['id'] ?>"
                            <?= in_array((int)$k['id'], $khoaCuaNguoi[(int)$u['id']] ?? [], true) ? 'checked' : '' ?>>
                          <span><strong><?= e($k['ma']) ?></strong> — <?= e($k['ten']) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </fieldset>
                  <div class="form-chan">
                    <button class="nut nut-chinh" type="submit">Lưu khoa</button>
                    <button type="button" class="nut nut-phu" data-dong>Hủy</button>
                  </div>
                </form>
              </div>
             </div>
            </div>
          <?php endif; ?>

          <?php if (co_quyen('nguoidung.uy_quyen') && $u['vai_tro'] !== 'dev'): ?>
          <button type="button" class="nut nut-nho nut-phu" data-mo="uyquyen-<?= (int)$u['id'] ?>">Ủy quyền</button>
          <div class="lop-phu" id="uyquyen-<?= (int)$u['id'] ?>" hidden>
           <div class="hop-modal" role="dialog" aria-modal="true" aria-label="Ủy quyền">
            <div class="modal-dau">
              <h2>Ủy quyền cho <?= e($u['ho_ten']) ?></h2>
              <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
            </div>
            <div class="modal-than">
              <form method="post" class="form-tai-khoan">
                <?= csrf_field() ?>
                <input type="hidden" name="viec" value="uy_quyen">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <fieldset class="nhom-khoa">
                  <legend>Quyền ủy thêm cho người này</legend>
                  <div class="luoi-o-chon">
                    <?php foreach (QUYEN_CO_THE_UY as $ma => $tenQ): ?>
                      <label class="o-chon">
                        <input type="checkbox" name="quyen[]" value="<?= e($ma) ?>"
                          <?= in_array($ma, $qUy, true) ? 'checked' : '' ?>>
                        <span><?= e($tenQ) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>
                <div class="form-chan">
                  <button class="nut nut-chinh" type="submit">Lưu quyền</button>
                  <button type="button" class="nut nut-phu" data-dong>Hủy</button>
                  <small class="phu" style="flex:1 1 100%">Quyền dành riêng cho người phát triển không ủy được.</small>
                </div>
              </form>
            </div>
           </div>
          </div>
          <?php endif; ?>

          <?php if ($tamKhoa): ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="viec" value="mo_khoa_tam">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="nut nut-nho nut-nhan" type="submit"
                    title="Xóa khóa tạm do gõ sai mật khẩu, giữ nguyên mật khẩu">Mở khóa</button>
          </form>
          <?php endif; ?>

          <form method="post" onsubmit="return confirm('Cấp lại mật khẩu cho <?= e($u['ten_dang_nhap']) ?>?')">
            <?= csrf_field() ?>
            <input type="hidden" name="viec" value="cap_lai_mk">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="nut nut-nho nut-phu" type="submit">Cấp lại MK</button>
          </form>

          <?php if (!$laToi): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="viec" value="doi_trang_thai">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="nut nut-nho <?= (int)$u['hoat_dong'] === 1 ? 'nut-nguy' : 'nut-phu' ?>"
                      type="submit"><?= (int)$u['hoat_dong'] === 1 ? 'Vô hiệu hóa' : 'Mở lại' ?></button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if (co_quyen('nguoidung.them')): ?>
<div class="lop-phu" id="modal-them" <?= isset($_GET['mo_them']) ? '' : 'hidden' ?>>
 <div class="hop-modal" role="dialog" aria-modal="true" aria-label="Thêm tài khoản">
  <div class="modal-dau">
    <h2>Thêm tài khoản</h2>
    <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
  </div>
  <div class="modal-than">
<form method="post" class="form-tai-khoan" autocomplete="off" id="form-them">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="them">
  <div class="luoi-truong">
    <label>Tên đăng nhập <input name="ten_dang_nhap" required></label>
    <label>Họ và tên <input name="ho_ten" required></label>
    <label>Chức vụ <input name="chuc_vu" placeholder="Điều dưỡng hành chính"></label>
    <label>Điện thoại <input name="dien_thoai"></label>
    <label>Vai trò
      <select name="vai_tro" id="chon-vai-tro">
        <option value="bacsi">Bác sĩ / Người nhập</option>
        <?php if ($laDev): ?>
          <option value="admin">Quản trị (Phòng KHTH)</option>
          <option value="dev">Người phát triển</option>
        <?php endif; ?>
      </select>
    </label>
  </div>

  <fieldset class="nhom-khoa" id="nhom-khoa">
    <legend>Khoa phụ trách <small class="phu">(bắt buộc với vai trò Bác sĩ, có thể chọn nhiều khoa)</small></legend>
    <div class="luoi-o-chon">
      <?php foreach ($dsKhoa as $k): ?>
        <label class="o-chon">
          <input type="checkbox" name="khoa[]" value="<?= (int)$k['id'] ?>">
          <span><strong><?= e($k['ma']) ?></strong> — <?= e($k['ten']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="phu chi-bac-si" hidden>
      Quản trị và người phát triển thấy mọi khoa nên không cần chọn.
    </p>
  </fieldset>

  <div class="form-chan">
    <button class="nut nut-chinh" type="submit">Tạo tài khoản</button>
    <button type="button" class="nut nut-phu" data-dong>Hủy</button>
    <p class="phu">
      Hệ thống sinh mật khẩu tạm và hiển thị <strong>một lần duy nhất</strong> sau khi tạo.
      Chép lại và giao tận tay người dùng.
    </p>
  </div>
</form>
  </div>
 </div>
</div>
<script>
/* Khoa phụ trách chỉ có nghĩa với vai trò Bác sĩ. Vai trò khác thì ẩn danh
   sách khoa đi cho gọn, và bỏ tick để không lưu nhầm. */
(function () {
  var vt = document.getElementById('chon-vai-tro');
  var nhom = document.getElementById('nhom-khoa');
  if (!vt || !nhom) { return; }
  var ds = nhom.querySelector('.luoi-o-chon');
  var nhac = nhom.querySelector('.chi-bac-si');
  function capNhat() {
    var laBS = vt.value === 'bacsi';
    ds.hidden = !laBS;
    nhac.hidden = laBS;
    if (!laBS) {
      nhom.querySelectorAll('input[type=checkbox]').forEach(function (o) { o.checked = false; });
    }
  }
  vt.addEventListener('change', capNhat);
  capNhat();
})();
</script>
<?php endif; ?>
<?php dong_trang();

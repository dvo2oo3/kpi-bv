<?php
/**
 * Cấu hình các ô hiển thị trên Trang chủ.
 *
 * Chọn chỉ tiêu nào nạp vào từng ô — lưu theo id nên đổi tên / đổi mã chỉ tiêu
 * vẫn lấy đúng số. Chưa cấu hình thì trang chủ tự dùng mã mặc định.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/cai_dat.php';

$toi = bat_buoc_quyen('he_thong.trang_chu');

$dsCT = qAll('SELECT id, ma, ten, id_cha FROM chi_tieu WHERE hoat_dong = 1 ORDER BY thu_tu, id');
$idHopLe = array_column($dsCT, null, 'id');   // để kiểm id gửi lên

if (la_post()) {
    kiem_tra_csrf();
    if (post('viec') === 'mac_dinh') {
        cai_dat_dat('dashboard_o', null);
        ghi_nhat_ky('CAU_HINH_TRANG_CHU', '', 'Khôi phục mặc định');
        nhan_tin('ok', 'Đã khôi phục các ô trang chủ về mặc định.');
        chuyen_huong('/cau-hinh-trang-chu.php');
    }

    $tc = array_map('intval', $_POST['tru_cot'] ?? []);
    $kl = array_map('intval', $_POST['khoi_luong'] ?? []);
    $sai = false;
    foreach (array_merge($tc, $kl) as $id) {
        if ($id !== 0 && !isset($idHopLe[$id])) { $sai = true; }
    }
    if ($sai) {
        nhan_tin('loi', 'Có ô chọn chỉ tiêu không hợp lệ.');
    } else {
        dashboard_o_luu($tc, $kl);
        ghi_nhat_ky('CAU_HINH_TRANG_CHU', '', 'Cập nhật ô hiển thị');
        nhan_tin('ok', 'Đã lưu cấu hình trang chủ.');
    }
    chuyen_huong('/cau-hinh-trang-chu.php');
}

// Lựa chọn hiện tại: theo cấu hình, hoặc mã mặc định nếu chưa đặt
$idTuMa = function (string $ma): int { $c = chi_tieu_theo_ma($ma); return $c ? (int)$c['id'] : 0; };
$cfg = dashboard_o();
$curTC = $cfg['tru_cot']    ?? array_map($idTuMa, ['KB','NT','NDT','CSGB']);
$curKL = $cfg['khoi_luong'] ?? array_map($idTuMa, ['TT','PT','XN','XQ','SA','NS']);
$curTC = array_pad(array_slice($curTC, 0, 4), 4, 0);
$curKL = array_pad(array_slice($curKL, 0, 6), 6, 0);

$nhanTC = ['Lượt khám bệnh','Bệnh nhân nội trú','Ngày điều trị nội trú','Công suất giường bệnh'];
$nhanKL = ['Thủ thuật','Phẫu thuật','Xét nghiệm','X-quang','Siêu âm','Nội soi'];

/** In các <option> cho một ô, đánh dấu id đang chọn. */
function o_chon(array $dsCT, int $chon): string
{
    $h = '<option value="0">— Không hiện —</option>';
    foreach ($dsCT as $c) {
        $thut = $c['id_cha'] !== null ? '↳ ' : '';
        $h .= '<option value="' . (int)$c['id'] . '"'
            . ((int)$c['id'] === $chon ? ' selected' : '') . '>'
            . $thut . e($c['ten']) . ' (' . e($c['ma']) . ')</option>';
    }
    return $h;
}

mo_trang('Cấu hình trang chủ');
?>
<p class="duong-dan"><a href="/">Trang chủ</a> › Cấu hình ô hiển thị</p>
<h1>Cấu hình ô hiển thị trên trang chủ</h1>
<p class="phu">
  Chọn chỉ tiêu nạp vào từng ô. Lưu theo chỉ tiêu (không theo mã) nên
  <strong>đổi tên hay đổi mã chỉ tiêu vẫn lấy đúng số</strong>. Tên trên thẻ lấy theo
  chính chỉ tiêu được chọn. Để <em>“— Không hiện —”</em> nếu muốn bỏ trống một ô.
</p>

<form method="post">
  <?= csrf_field() ?>

  <section class="buoc">
    <div class="buoc-dau"><span class="so-buoc">1</span>
      <div><h2>Bốn ô lớn (trụ cột)</h2></div></div>
    <div class="luoi-truong">
      <?php foreach ($nhanTC as $i => $nhan): ?>
        <label>Ô lớn <?= $i + 1 ?> <small class="nhan-phu">mặc định: <?= e($nhan) ?></small>
          <select name="tru_cot[]"><?= o_chon($dsCT, (int)$curTC[$i]) ?></select>
        </label>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="buoc">
    <div class="buoc-dau"><span class="so-buoc">2</span>
      <div><h2>Sáu ô khối lượng chuyên môn</h2></div></div>
    <div class="luoi-truong">
      <?php foreach ($nhanKL as $i => $nhan): ?>
        <label>Ô khối lượng <?= $i + 1 ?> <small class="nhan-phu">mặc định: <?= e($nhan) ?></small>
          <select name="khoi_luong[]"><?= o_chon($dsCT, (int)$curKL[$i]) ?></select>
        </label>
      <?php endforeach; ?>
    </div>
  </section>

  <p class="hang-nut">
    <button class="nut nut-chinh" type="submit">Lưu cấu hình</button>
    <a class="nut nut-phu" href="/">Hủy</a>
  </p>
</form>

<form method="post" data-xac-nhan="Đưa tất cả ô trang chủ về mặc định?"
      style="margin-top:1rem">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="mac_dinh">
  <button class="nut nut-nho nut-phu" type="submit">Khôi phục mặc định</button>
</form>

<?php dong_trang();

<?php
/**
 * Thêm chỉ tiêu mới — theo ba bước.
 *
 * Chọn khoa TRƯỚC, vì danh sách nội dung lớn phụ thuộc vào khoa:
 * mỗi khoa có bộ chỉ tiêu riêng, chọn nhầm một nội dung lớn của khoa khác
 * sẽ kéo cả nội dung lớn đó sang khoa đang thêm.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/danh_muc.php';

$toi = bat_buoc_quyen('chitieu.them');
$laDev = co_quyen('chitieu.cong_thuc');
$dsKhoa = danh_sach_khoa_hoat_dong();

// Mở sẵn ở dạng "thêm nội dung nhỏ cho chỉ tiêu này"
$chaMacDinh = (int)($_GET['cha'] ?? 0) ?: null;
$khoaMacDinh = array_map('intval', (array)($_GET['khoa'] ?? []));
if ($chaMacDinh && !$khoaMacDinh) {
    // Vào từ liên kết "+ nội dung nhỏ": chọn sẵn đúng các khoa của chỉ tiêu cha
    $khoaMacDinh = array_map('intval', array_column(
        qAll('SELECT id_khoa FROM chi_tieu_ap_dung WHERE id_chi_tieu = ?', [$chaMacDinh]),
        'id_khoa'));
}

// Nội dung lớn kèm danh sách khoa áp dụng, để lọc phía trình duyệt
$noiDungLon = qAll('SELECT id, ma, ten FROM chi_tieu
                     WHERE id_cha IS NULL AND hoat_dong = 1 ORDER BY thu_tu, id');
$khoaCuaCha = [];
foreach (qAll('SELECT id_chi_tieu, id_khoa FROM chi_tieu_ap_dung') as $r) {
    $khoaCuaCha[(int)$r['id_chi_tieu']][] = (int)$r['id_khoa'];
}

if (la_post()) {
    kiem_tra_csrf();

    /* ---------- Thêm nhanh một khoa ngay tại bước 1 ---------- */
    if (post('viec') === 'them_khoa') {
        if (!co_quyen('khoa.them')) {
            nhan_tin('loi', 'Bạn không có quyền thêm khoa.');
            chuyen_huong('/chi-tieu-them.php');
        }
        $maK  = chu_hoa(post('khoa_ma'));
        $tenK = post('khoa_ten');
        $loaiK = post('khoa_loai', 'NOI_TRU');
        $gbK  = (int)post('khoa_giuong', '0');
        $dangChon = array_map('intval', $_POST['dang_chon'] ?? []);

        if (!preg_match('/^[A-Z0-9_]{2,20}$/', $maK)) {
            nhan_tin('loi', 'Mã khoa chỉ gồm chữ in hoa, số và gạch dưới, dài 2–20 ký tự.');
        } elseif ($tenK === '') {
            nhan_tin('loi', 'Vui lòng nhập tên khoa.');
        } elseif (!in_array($loaiK, ['NOI_TRU', 'NGOAI_TRU', 'CAN_LAM_SANG'], true)) {
            nhan_tin('loi', 'Loại khoa không hợp lệ.');
        } elseif (qVal('SELECT 1 FROM khoa WHERE ma = ?', [$maK])) {
            nhan_tin('loi', "Mã khoa \"$maK\" đã tồn tại.");
        } else {
            $thuTu = (int)qVal('SELECT COALESCE(MAX(thu_tu),0) FROM khoa') + 1;
            q('INSERT INTO khoa (ma, ten, loai, giuong_benh, thu_tu) VALUES (?,?,?,?,?)',
                [$maK, $tenK, $loaiK, $loaiK === 'NOI_TRU' ? $gbK : 0, $thuTu]);
            $idMoiK = (int)db()->lastInsertId();
            ghi_nhat_ky('THEM_KHOA', $maK, $tenK . ' (thêm nhanh từ trang Thêm chỉ tiêu)');
            nhan_tin('ok', "Đã thêm khoa \"$tenK\" và chọn sẵn cho chỉ tiêu đang tạo. "
                . 'Khoa mới chưa được gán chỉ tiêu nào khác — vào Danh mục khoa để bổ sung.');
            $dangChon[] = $idMoiK;
        }
        $qs = http_build_query(array_filter(['khoa' => array_unique($dangChon)]));
        chuyen_huong('/chi-tieu-them.php' . ($qs ? "?$qs" : ''));
    }

    $ma    = chu_hoa(post('ma'));
    $ten   = post('ten');
    $donVi = post('don_vi');
    $idCha = post('id_cha') !== '' ? (int)post('id_cha') : null;
    $f     = doc_bieu_mau($laDev);
    $chon  = array_map('intval', $_POST['khoa'] ?? []);
    $themTiep = post('viec') === 'them_tiep';

    $loi = null;
    if (!$chon) {
        $loi = 'Bước 1: phải chọn ít nhất một khoa, nếu không chỉ tiêu sẽ không hiện ra để nhập.';
    } elseif ($ten === '') {
        $loi = 'Vui lòng nhập nội dung chỉ tiêu.';
    } elseif (!$f) {
        $loi = 'Thông số chỉ tiêu không hợp lệ.';
    } elseif ($ma !== '' && !preg_match('/^[A-Z0-9_]{2,30}$/', $ma)) {
        $loi = 'Mã chỉ tiêu chỉ gồm chữ in hoa, số và gạch dưới, dài 2–30 ký tự.';
    } elseif ($ma !== '' && qVal('SELECT 1 FROM chi_tieu WHERE ma = ?', [$ma])) {
        $loi = "Mã chỉ tiêu \"$ma\" đã tồn tại.";
    } elseif ($idCha !== null) {
        $cha = q1('SELECT * FROM chi_tieu WHERE id = ?', [$idCha]);
        if (!$cha) {
            $idCha = null;
        } elseif ($cha['id_cha'] !== null) {
            $loi = 'Chỉ lồng được hai tầng: nội dung lớn và nội dung nhỏ.';
        }
    }

    if ($loi !== null) {
        nhan_tin('loi', $loi);
    } else {
        // Bỏ trống mã thì tự sinh từ nội dung
        if ($ma === '') {
            $ma = ma_tu_ten($ten);
        }
        $thuTu = $idCha !== null
            ? (int)qVal('SELECT COALESCE(MAX(thu_tu),0) FROM chi_tieu WHERE id = ? OR id_cha = ?',
                [$idCha, $idCha]) + 1
            : (int)qVal('SELECT COALESCE(MAX(thu_tu),0) FROM chi_tieu') + 10;

        db()->beginTransaction();
        q('INSERT INTO chi_tieu (ma, ten, don_vi, id_cha, thu_tu, loai_gia_tri, nguon, huong, phan_bo)
           VALUES (?,?,?,?,?,?,?,?,?)',
            [$ma, $ten, $donVi, $idCha, $thuTu,
             $f['loai'], $f['nguon'], $f['huong'], $f['phan_bo']]);
        $idMoi = (int)db()->lastInsertId();
        foreach (array_unique($chon) as $idK) {
            q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)', [$idMoi, $idK]);
        }
        $keoTheo = [];
        if ($idCha !== null) {
            q('UPDATE chi_tieu SET nguon = ? WHERE id = ? AND nguon = ?',
                ['TONG_CON', $idCha, 'NHAP_TAY']);
            $keoTheo = dong_bo_cha_con($idMoi);
        }
        db()->commit();

        ghi_nhat_ky('THEM_CHI_TIEU', $ma, $ten);
        nhan_tin('ok', "Đã thêm chỉ tiêu \"$ten\" cho " . count($chon) . ' khoa.'
            . ($idCha !== null ? ' Chỉ tiêu cha đã chuyển sang tự cộng từ các nội dung nhỏ.' : ''));
        foreach ($keoTheo as $g) {
            nhan_tin('canh-bao', $g);
        }
        if ($themTiep) {
            $qs = http_build_query(array_filter([
                'cha'  => $idCha,
                'khoa' => $chon,
            ]));
            chuyen_huong('/chi-tieu-them.php' . ($qs ? "?$qs" : ''));
        }
        chuyen_huong('/danh-muc-chi-tieu.php');
    }
}

mo_trang('Thêm chỉ tiêu');
?>
<p class="duong-dan"><a href="/danh-muc-chi-tieu.php">Danh mục chỉ tiêu</a> › Thêm chỉ tiêu</p>
<h1>Thêm chỉ tiêu</h1>

<form method="post" id="bieu-mau-them">
  <?= csrf_field() ?>

  <!-- ============ BƯỚC 1 ============ -->
  <section class="buoc">
    <div class="buoc-dau">
      <span class="so-buoc">1</span>
      <div><h2>Chọn khoa áp dụng</h2></div>
    </div>

    <div class="luoi-o-chon" id="nhom-khoa">
      <?php foreach ($dsKhoa as $k): ?>
        <label class="o-chon">
          <input type="checkbox" name="khoa[]" value="<?= (int)$k['id'] ?>"
            <?= in_array((int)$k['id'], $khoaMacDinh, true) ? 'checked' : '' ?>>
          <span><strong><?= e($k['ma']) ?></strong> — <?= e($k['ten']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="hang-nut">
      <button type="button" class="nut nut-nho nut-phu" onclick="chonKhoa(true)">Chọn tất cả</button>
      <button type="button" class="nut nut-nho nut-phu" onclick="chonKhoa(false)">Bỏ chọn</button>
      <span class="phu" id="tom-tat-khoa"></span>
    </p>

    <?php if (co_quyen('khoa.them')): ?>
    <details class="them-khoa-nhanh" id="khung-them-khoa">
      <summary>Khoa cần dùng chưa có trong danh sách? Thêm ngay tại đây</summary>
      <!-- Các ô dưới đây thuộc biểu mẫu "them-khoa-nhanh" đặt ở cuối trang,
           vì HTML không cho lồng biểu mẫu trong biểu mẫu. -->
      <div class="luoi-truong">
        <label>Mã khoa
          <input type="text" name="khoa_ma" form="them-khoa-nhanh" placeholder="RHM">
        </label>
        <label>Tên khoa
          <input type="text" name="khoa_ten" form="them-khoa-nhanh" placeholder="Khoa Răng Hàm Mặt">
        </label>
        <label>Loại
          <select name="khoa_loai" form="them-khoa-nhanh">
            <option value="NOI_TRU">Nội trú (có giường bệnh)</option>
            <option value="NGOAI_TRU">Ngoại trú</option>
            <option value="CAN_LAM_SANG">Cận lâm sàng</option>
          </select>
        </label>
        <label>Giường bệnh kế hoạch
          <input type="text" inputmode="numeric" name="khoa_giuong" form="them-khoa-nhanh" value="0">
        </label>
      </div>
      <p class="hang-nut">
        <button class="nut nut-nho" type="submit" form="them-khoa-nhanh">Thêm khoa</button>
        <span class="phu">Trang sẽ tải lại, nên thêm khoa trước khi nhập bước 3.</span>
      </p>
    </details>
    <?php endif; ?>
  </section>

  <!-- ============ BƯỚC 2 ============ -->
  <section class="buoc" id="buoc-2">
    <div class="buoc-dau">
      <span class="so-buoc">2</span>
      <div><h2>Đặt vào đâu trong danh mục</h2></div>
    </div>

    <div class="chua-chon-khoa" id="nhac-chon-khoa">
      Chưa chọn khoa nào. Quay lại bước 1 để chọn khoa trước.
    </div>

    <div id="vung-buoc-2">
      <details class="muc-lon-hien-co">
        <summary>Khoa đã chọn đang có <span id="dem-muc-lon">0</span> nội dung lớn</summary>
        <div id="ds-muc-lon"></div>
      </details>

      <label class="o-rong">Thuộc nội dung lớn
        <select name="id_cha" id="chon-cha">
          <option value="">— Đây là một nội dung lớn mới —</option>
          <?php foreach ($noiDungLon as $c):
              $dsK = $khoaCuaCha[(int)$c['id']] ?? []; ?>
            <option value="<?= (int)$c['id'] ?>"
                    data-khoa="<?= e(implode(',', $dsK)) ?>"
                    <?= $chaMacDinh === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['ten']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div id="canh-bao-cha"></div>
    </div>
  </section>

  <!-- ============ BƯỚC 3 ============ -->
  <section class="buoc">
    <div class="buoc-dau">
      <span class="so-buoc">3</span>
      <div><h2>Thông tin chỉ tiêu</h2></div>
      <?php mo_tro_giup('tg-thong-tin', 'Giải thích các ô ở bước 3'); ?>
        <dl class="giai-nghia">
          <dt>Mã chỉ tiêu</dt>
          <dd><strong>Để trống là được</strong> — hệ thống tự tạo mã từ nội dung
              (VD "Tổng số lượt tiêm chủng" → <code>TONG_SO_LUOT_TIEM_CHUNG</code>).
              Chỉ tự đặt khi cần mã riêng; mã tham chiếu trong tính toán nên sau không đổi được.</dd>
          <dt>Loại giá trị</dt>
          <dd><strong>Đếm</strong> — cộng dồn được qua các tháng (lượt khám, ca thủ thuật).<br>
              <strong>Trung bình</strong> và <strong>Tỷ lệ</strong> — không cộng dồn,
              hệ thống tính lại từ tử số và mẫu số của cả kỳ.<br>
              <strong>Hằng số</strong> — không đổi theo tháng, như số giường bệnh.</dd>
          <dt>Cách đánh giá</dt>
          <dd><strong>Càng cao càng tốt</strong> — hầu hết chỉ tiêu.<br>
              <strong>Càng thấp càng tốt</strong> — tử vong, chuyển viện, ngày điều trị trung bình.<br>
              <strong>Đích đúng 100%</strong> — công suất giường bệnh: thiếu là lãng phí,
              vượt là quá tải.</dd>
          <dt>Phân bổ chỉ tiêu ra tháng</dt>
          <dd><strong>Chia theo số ngày</strong> — tháng 2 có 28 ngày nên chỉ tiêu thấp hơn
              tháng 1 có 31 ngày.<br>
              <strong>Giữ nguyên mức năm</strong> — bắt buộc với tỷ lệ và trung bình.</dd>
        </dl>
      <?php dong_tro_giup(); ?>
    </div>

    <div class="luoi-truong">
      <label>Mã chỉ tiêu
        <input type="text" name="ma" value="<?= e(post('ma')) ?>"
               placeholder="Để trống — tự tạo từ nội dung">
        <small class="nhan-phu">Để trống sẽ tự sinh từ nội dung</small>
      </label>
      <label>Nội dung
        <input type="text" name="ten" value="<?= e(post('ten')) ?>"
               placeholder="Tổng số lượt tiêm chủng" required>
      </label>
      <label>Đơn vị tính
        <input type="text" name="don_vi" value="<?= e(post('don_vi')) ?>" placeholder="Lượt">
      </label>
      <label>Loại giá trị
        <select name="loai_gia_tri">
          <?php foreach (['DEM', 'TRUNG_BINH', 'TY_LE', 'HANG_SO'] as $v): ?>
            <option value="<?= $v ?>"><?= e(NHAN[$v]) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Cách đánh giá
        <select name="huong">
          <?php foreach (['CAO_TOT', 'THAP_TOT', 'DICH_CO_DINH'] as $v): ?>
            <option value="<?= $v ?>"><?= e(NHAN[$v]) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Phân bổ chỉ tiêu ra tháng
        <select name="phan_bo">
          <option value="THEO_NGAY"><?= e(NHAN['THEO_NGAY']) ?></option>
          <option value="KHONG_CHIA"><?= e(NHAN['KHONG_CHIA']) ?></option>
        </select>
      </label>
    </div>
    <input type="hidden" name="nguon" value="NHAP_TAY">
  </section>

  <p class="hang-nut">
    <button class="nut nut-chinh" type="submit" name="viec" value="them">Thêm chỉ tiêu</button>
    <button class="nut nut-phu" type="submit" name="viec" value="them_tiep">Thêm rồi nhập tiếp</button>
    <a class="nut nut-phu" href="/danh-muc-chi-tieu.php">Hủy</a>
  </p>
</form>

<?php if (co_quyen('khoa.them')): ?>
<!-- Biểu mẫu đích của phần "thêm khoa nhanh" ở bước 1 -->
<form method="post" id="them-khoa-nhanh" class="an">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="them_khoa">
</form>
<?php endif; ?>

<script>
(function () {
  var oKhoa   = document.querySelectorAll('#nhom-khoa input[type=checkbox]');
  var chonCha = document.getElementById('chon-cha');
  var dsMucLon = document.getElementById('ds-muc-lon');
  var nhac    = document.getElementById('nhac-chon-khoa');
  var vung    = document.getElementById('vung-buoc-2');
  var tomTat  = document.getElementById('tom-tat-khoa');
  var canhBao = document.getElementById('canh-bao-cha');

  function khoaDangChon() {
    var r = [];
    oKhoa.forEach(function (o) { if (o.checked) r.push(o.value); });
    return r;
  }
  function tenKhoa(o) { return o.parentNode.querySelector('strong').textContent; }

  function veLai() {
    var chon = khoaDangChon();
    var coKhoa = chon.length > 0;

    nhac.style.display = coKhoa ? 'none' : 'block';
    vung.style.display = coKhoa ? 'block' : 'none';

    var ten = [];
    oKhoa.forEach(function (o) { if (o.checked) ten.push(tenKhoa(o)); });
    tomTat.textContent = coKhoa
      ? 'Đã chọn ' + chon.length + ' khoa: ' + ten.join(', ')
      : '';

    // Nội dung lớn có sẵn ở TẤT CẢ khoa đã chọn
    var coSan = [];
    Array.prototype.forEach.call(chonCha.options, function (op) {
      if (!op.value) { return; }
      var cua = (op.dataset.khoa || '').split(',').filter(Boolean);
      var duU = chon.every(function (k) { return cua.indexOf(k) !== -1; });
      op.dataset.coSan = duU ? '1' : '0';
      if (duU) { coSan.push(op.textContent.trim()); }
    });

    var dem = document.getElementById('dem-muc-lon');
    if (dem) { dem.textContent = coSan.length; }
    dsMucLon.innerHTML = coSan.length
      ? coSan.map(function (t) { return '<span class="the-muc-lon">' + t + '</span>'; }).join('')
      : '<span class="phu">Khoa đã chọn chưa có nội dung lớn nào dùng chung.</span>';

    // Cảnh báo nếu nội dung lớn đang chọn chưa thuộc các khoa đó
    var op = chonCha.options[chonCha.selectedIndex];
    if (op && op.value && op.dataset.coSan === '0') {
      canhBao.innerHTML = '<div class="tb tb-canh-bao">Nội dung lớn '
        + '<strong>' + op.textContent.trim() + '</strong> chưa có ở tất cả khoa bạn chọn. '
        + 'Nếu vẫn thêm, hệ thống sẽ gán nội dung lớn này vào các khoa đó để dòng con hiện ra được.'
        + '</div>';
    } else {
      canhBao.innerHTML = '';
    }
  }

  oKhoa.forEach(function (o) { o.addEventListener('change', veLai); });
  chonCha.addEventListener('change', veLai);

  // Thêm khoa nhanh: mang theo các khoa đang tích để không mất lựa chọn
  var fThemKhoa = document.getElementById('them-khoa-nhanh');
  if (fThemKhoa) {
    fThemKhoa.addEventListener('submit', function () {
      fThemKhoa.querySelectorAll('input[name="dang_chon[]"]')
               .forEach(function (o) { o.remove(); });
      khoaDangChon().forEach(function (id) {
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = 'dang_chon[]'; h.value = id;
        fThemKhoa.appendChild(h);
      });
    });
  }
  window.chonKhoa = function (bat) {
    oKhoa.forEach(function (o) { o.checked = bat; });
    veLai();
  };
  veLai();
})();
</script>
<?php dong_trang();

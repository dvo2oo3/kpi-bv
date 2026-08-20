<?php
/**
 * Duyệt và khóa kỳ. Mở lại kỳ đã khóa: chỉ người phát triển.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';

$toi = bat_buoc_quyen('ky.duyet');

/** Chuỗi confirm cảnh báo khi DUYỆT một kỳ mà cửa nhập CÒN MỞ (rỗng nếu đã đóng).
 *  Duyệt sớm sẽ khóa số liệu đang cho nhập → nhắc admin xác nhận. */
function js_canh_bao_duyet(int $nam, int $thang, int $idKhoa): string
{
    $cs = cua_so_ky($nam, $thang, $idKhoa);
    if (time() < $cs['mo_tu'] || time() > $cs['dong_sau']) {
        return '';   // cửa đã đóng/chưa tới → duyệt bình thường, không cảnh báo
    }
    $msg = "Tháng $thang vẫn ĐANG MỞ cho nhập (đến " . date('d/m/Y', $cs['dong_sau'])
         . "). Duyệt bây giờ sẽ KHÓA SỚM — khoa hết nhập/sửa được. Chắc chắn duyệt?";
    return htmlspecialchars($msg, ENT_QUOTES);   // dùng cho thuộc tính data-xac-nhan
}

/**
 * Nút + modal cho một thao tác mở kỳ (Trả lại / Bỏ duyệt / Mở lại).
 * Dùng modal (.lop-phu) thay cho <details> để không làm phình chiều cao hàng
 * và không bị .cuon-ngang cắt mất.
 */
function o_mo_ky(array $o): void
{
    $id = $o['id'];
    ?>
    <button type="button" class="nut nut-nho <?= $o['nut_class'] ?? 'nut-canh' ?>"
            data-mo="<?= e($id) ?>"><?= e($o['nut']) ?></button>
    <div class="lop-phu" id="<?= e($id) ?>" hidden>
     <div class="hop-modal" role="dialog" aria-modal="true" aria-label="<?= e($o['tieu_de']) ?>">
      <div class="modal-dau">
        <h2><?= e($o['tieu_de']) ?></h2>
        <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
      </div>
      <div class="modal-than">
        <?php if (!empty($o['ghi_chu'])): ?><p class="phu" style="margin-top:0"><?= e($o['ghi_chu']) ?></p><?php endif; ?>
        <form method="post" class="form-tai-khoan">
          <?= csrf_field() ?>
          <input type="hidden" name="viec" value="<?= e($o['viec']) ?>">
          <input type="hidden" name="id_khoa" value="<?= (int)$o['id_khoa'] ?>">
          <label>Lý do
            <input type="text" name="ly_do" required
                   value="<?= e($o['ly_do'] ?? '') ?>" placeholder="<?= e($o['placeholder']) ?>">
          </label>
          <label>Mở thêm bao nhiêu ngày
            <input type="text" inputmode="numeric" name="so_ngay" value="7">
          </label>
          <div class="form-chan">
            <button class="nut nut-chinh" type="submit"><?= e($o['nut_gui']) ?></button>
            <button type="button" class="nut nut-phu" data-dong>Hủy</button>
          </div>
        </form>
      </div>
     </div>
    </div>
    <?php
}

$macDinhThang = (int)date('n') - 1;   // mặc định = tháng trước (kỳ báo cáo gần nhất)
$macDinhNam   = (int)date('Y');
if ($macDinhThang === 0) { $macDinhThang = 12; $macDinhNam--; }

$nam   = (int)($_GET['nam']   ?? $macDinhNam);
$thang = (int)($_GET['thang'] ?? $macDinhThang);
$thang = max(1, min(12, $thang));

$dsKhoa = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');

if (la_post()) {
    kiem_tra_csrf();
    // Duyệt/Trả lại từ danh sách "chờ duyệt mọi tháng" mang theo nam/thang riêng,
    // để duyệt đúng kỳ dù trang đang mở ở tháng khác.
    if (post('nam'))   { $nam   = (int)post('nam'); }
    if (post('thang')) { $thang = max(1, min(12, (int)post('thang'))); }
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

    } elseif ($viec === 'bo_duyet') {
        // Bỏ duyệt: đưa kỳ ĐÃ DUYỆT (chưa khóa) về "đang mở" để sửa lại.
        // Admin tự sửa được vì chính admin đã duyệt; khác với kỳ ĐÃ KHÓA (dev mới mở).
        $bao_dam_co_ky();
        $ngay = max(1, min(60, (int)post('so_ngay', '7')));
        $den  = date('Y-m-d', strtotime("+$ngay days"));
        q('UPDATE ky SET trang_thai=?, nguoi_duyet=NULL, thoi_diem_duyet=NULL,
             ghi_chu=?, mo_den=? WHERE nam=? AND thang=? AND id_khoa=?',
            ['MO', post('ly_do') ?: null, $den, $nam, $thang, $idKhoa]);
        ghi_nhat_ky('BO_DUYET_KY', $khoa['ma'],
            "Tháng $thang/$nam — mở lại đến $den — " . post('ly_do'));
        nhan_tin('ok', "Đã bỏ duyệt & mở lại {$khoa['ten']} tháng $thang/$nam để sửa.");

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
    // Thao tác từ popup "chờ duyệt" gửi bằng AJAX → trả JSON, không tải lại cả trang.
    if (post('ajax') === '1') {
        $ok = true; $msg = '';
        foreach (lay_thong_bao() as $t) { if ($t['loai'] === 'loi') { $ok = false; } $msg = $t['noi_dung']; }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
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

<?php
// Tất cả kỳ đang chờ duyệt (MỌI tháng) — để không bỏ sót khi khoa nộp cho
// tháng khác với tháng đang xem.
$choDuyet = qAll("SELECT k.nam, k.thang, k.id_khoa, kh.ma, kh.ten, k.thoi_diem_nop,
                         nd.ho_ten AS nguoi_nop
                  FROM ky k JOIN khoa kh ON kh.id = k.id_khoa
                  LEFT JOIN nguoi_dung nd ON nd.id = k.nguoi_nop
                  WHERE k.trang_thai = 'DA_NOP'
                  ORDER BY k.nam DESC, k.thang DESC, kh.thu_tu, kh.ten");

// Khoa YÊU CẦU MỞ LẠI kỳ đã chốt (ghi_chu tiền tố 'YC:') — do bác sĩ gửi từ
// trang Nhập số liệu. Admin thấy ở đây để mở lại.
$choMoLai = qAll("SELECT k.nam, k.thang, k.id_khoa, kh.ma, kh.ten, k.ghi_chu
                  FROM ky k JOIN khoa kh ON kh.id = k.id_khoa
                  WHERE k.ghi_chu LIKE 'YC:%'
                    AND k.trang_thai IN ('DA_DUYET','DA_KHOA')
                  ORDER BY k.nam DESC, k.thang DESC, kh.thu_tu, kh.ten");
if ($choDuyet): ?>
<p class="hang-nut" style="margin:0 0 16px">
  <button type="button" class="nut nut-canh nut-cho-duyet" data-mo="modal-cho-duyet">
    ⏳ <span class="so-cho-duyet"><?= count($choDuyet) ?></span> kỳ đang chờ duyệt — bấm để duyệt
  </button>
</p>
<div class="lop-phu" id="modal-cho-duyet" hidden>
 <div class="hop-modal" role="dialog" aria-modal="true" aria-label="Kỳ đang chờ duyệt">
  <div class="modal-dau">
    <h2><span class="so-cho-duyet"><?= count($choDuyet) ?></span> kỳ đang chờ duyệt <small class="phu">(mọi tháng)</small></h2>
    <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
  </div>
  <div class="modal-than">
    <table class="bang-cho-duyet">
      <?php foreach ($choDuyet as $c): ?>
      <tr>
        <td>
          <strong><?= e($c['ma']) ?></strong> · tháng <?= (int)$c['thang'] ?>/<?= (int)$c['nam'] ?>
          <?php if ($c['nguoi_nop']): ?>
            <small class="phu">— <?= e($c['nguoi_nop']) ?><?= $c['thoi_diem_nop'] ? ', ' . e(ngay_gio($c['thoi_diem_nop'])) : '' ?></small>
          <?php endif; ?>
        </td>
        <td class="phai">
          <?php $cbPop = js_canh_bao_duyet((int)$c['nam'], (int)$c['thang'], (int)$c['id_khoa']); ?>
          <form method="post" class="form-duyet-ajax" style="display:inline"<?= $cbPop ? ' data-xac-nhan="' . $cbPop . '"' : '' ?>><?= csrf_field() ?>
            <input type="hidden" name="viec" value="duyet">
            <input type="hidden" name="id_khoa" value="<?= (int)$c['id_khoa'] ?>">
            <input type="hidden" name="nam" value="<?= (int)$c['nam'] ?>">
            <input type="hidden" name="thang" value="<?= (int)$c['thang'] ?>">
            <button class="nut nut-nho" type="submit">Duyệt</button>
          </form>
          <a class="nut nut-nho nut-phu" target="_blank" rel="noopener"
             href="/nhap-so-lieu.php?nam=<?= (int)$c['nam'] ?>&thang=<?= (int)$c['thang'] ?>&khoa=<?= (int)$c['id_khoa'] ?>"
             title="Mở số liệu đã nộp ở tab mới để xem trước">Xem số liệu ↗</a>
          <details class="tra-lai-nhanh">
            <summary class="nut nut-nho nut-phu">Trả lại</summary>
            <form method="post" class="form-tra-lai"><?= csrf_field() ?>
              <input type="hidden" name="viec" value="tra_lai">
              <input type="hidden" name="id_khoa" value="<?= (int)$c['id_khoa'] ?>">
              <input type="hidden" name="nam" value="<?= (int)$c['nam'] ?>">
              <input type="hidden" name="thang" value="<?= (int)$c['thang'] ?>">
              <input type="text" name="ly_do" required placeholder="Lý do trả lại để bác sĩ sửa">
              <label>Mở thêm <input type="text" inputmode="numeric" name="so_ngay" value="7"> ngày</label>
              <button class="nut nut-nho nut-canh" type="submit">Gửi trả lại</button>
            </form>
          </details>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
 </div>
</div>
<?php endif; ?>

<?php if ($choMoLai): ?>
<div class="tb tb-canh-bao">
  <strong>⚠ <?= count($choMoLai) ?> khoa yêu cầu mở lại để sửa</strong> — bấm để tới đúng tháng rồi bấm “Mở lại”:
  <div class="ds-cho-nop">
    <?php foreach ($choMoLai as $c):
        $chon = (int)$c['nam'] === $nam && (int)$c['thang'] === $thang; ?>
      <a class="the-cho-nop<?= $chon ? ' dang-xem' : '' ?>"
         href="/duyet-ky.php?nam=<?= (int)$c['nam'] ?>&thang=<?= (int)$c['thang'] ?>"
         title="<?= e(trim(substr((string)$c['ghi_chu'], 3))) ?>">
        <strong><?= e($c['ma']) ?></strong>
        <span>tháng <?= (int)$c['thang'] ?>/<?= (int)$c['nam'] ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

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
      $ycRow  = $ky && str_starts_with((string)($ky['ghi_chu'] ?? ''), 'YC:');
      $ycLyDo = $ycRow ? trim(substr((string)$ky['ghi_chu'], 3)) : '';
      ?>
    <tr>
      <td><strong class="ten-khoa"><?= e($k['ten']) ?></strong><br><small class="ma-khoa"><?= e($k['ma']) ?></small></td>
      <td><span class="trang-thai-ky tt-<?= e(strtolower($tt)) ?>"><?= e(ten_trang_thai($tt)) ?></span>
        <?php if ($ycRow): ?><div class="the the-nho the-yeu-cau" title="<?= e($ycLyDo) ?>">⚠ khoa xin mở lại</div><?php endif; ?>
      </td>
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

        <?php if ($tt === 'DA_NOP'): $cbDuyet = js_canh_bao_duyet($nam, $thang, $idK); ?>
          <form method="post"<?= $cbDuyet ? ' data-xac-nhan="' . $cbDuyet . '"' : '' ?>><?= csrf_field() ?>
            <input type="hidden" name="viec" value="duyet">
            <input type="hidden" name="id_khoa" value="<?= $idK ?>">
            <button class="nut nut-nho" type="submit">Duyệt</button>
          </form>
          <?php o_mo_ky([
              'id' => "moky-tra-$idK", 'nut' => 'Trả lại', 'nut_class' => 'nut-phu',
              'viec' => 'tra_lai', 'id_khoa' => $idK,
              'tieu_de' => 'Trả lại kỳ để khoa sửa — ' . $k['ten'],
              'placeholder' => 'Lý do trả lại', 'nut_gui' => 'Trả lại cho khoa',
          ]); ?>
        <?php endif; ?>

        <?php if ($tt === 'DA_DUYET'): ?>
          <?php o_mo_ky([
              'id' => "moky-boduyet-$idK", 'nut' => $ycRow ? 'Mở lại ⚠' : 'Mở lại',
              'viec' => 'bo_duyet', 'id_khoa' => $idK,
              'tieu_de' => 'Bỏ duyệt & mở lại — ' . $k['ten'],
              'placeholder' => 'Lý do bỏ duyệt / mở lại', 'nut_gui' => 'Bỏ duyệt & mở lại',
              'ly_do' => $ycRow ? 'Mở theo yêu cầu: ' . $ycLyDo : '',
          ]); ?>
          <form method="post"
                data-xac-nhan="Khóa số liệu khoa này? Sau khi khóa chỉ người phát triển mở lại được." data-xac-nhan-loai="nguy">
            <?= csrf_field() ?>
            <input type="hidden" name="viec" value="khoa">
            <input type="hidden" name="id_khoa" value="<?= $idK ?>">
            <button class="nut nut-nho nut-nguy" type="submit">Khóa</button>
          </form>
        <?php endif; ?>

        <?php if ($tt === 'DA_KHOA'): ?>
          <?php o_mo_ky([
              'id' => "moky-molai-$idK",
              'nut' => (co_quyen('ky.mo_lai') ? 'Mở lại' : 'Đề nghị mở lại') . ($ycRow ? ' ⚠' : ''),
              'viec' => 'mo_lai', 'id_khoa' => $idK,
              'tieu_de' => (co_quyen('ky.mo_lai') ? 'Mở lại kỳ đã khóa' : 'Đề nghị mở lại kỳ') . ' — ' . $k['ten'],
              'placeholder' => 'Lý do mở lại', 'nut_gui' => co_quyen('ky.mo_lai') ? 'Mở lại kỳ' : 'Gửi đề nghị',
              'ly_do' => $ycRow ? 'Mở theo yêu cầu: ' . $ycLyDo : '',
              'ghi_chu' => co_quyen('ky.mo_lai') ? '' : 'Chỉ người phát triển thực hiện được. Đề nghị của bạn sẽ được ghi vào nhật ký.',
          ]); ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<script>
/* Nút "Duyệt" trong popup chờ duyệt: gửi AJAX, xóa dòng ngay, KHÔNG tải lại cả trang.
   (Cảnh báo "duyệt sớm" data-xac-nhan vẫn chạy trước như thường.) */
(function () {
  function capNhatSo() {
    var bang = document.querySelector('.bang-cho-duyet');
    var con  = bang ? bang.querySelectorAll('tr').length : 0;
    Array.prototype.forEach.call(document.querySelectorAll('.so-cho-duyet'), function (s) { s.textContent = con; });
    if (con === 0) {
      var modal = document.getElementById('modal-cho-duyet'); if (modal) { modal.hidden = true; }
      var nut = document.querySelector('.nut-cho-duyet'); if (nut && nut.parentNode) { nut.parentNode.remove(); }
    }
  }
  document.addEventListener('submit', function (e) {
    var f = e.target.closest ? e.target.closest('.form-duyet-ajax') : null;
    if (!f) { return; }
    // Còn chờ xác nhận (duyệt sớm) thì để popup xác nhận xử lý trước, lần gửi lại mới AJAX.
    if (f.dataset.xacNhan && !f.dataset.xnOk) { return; }
    e.preventDefault();
    var nut = f.querySelector('button[type="submit"]');
    if (nut) { nut.disabled = true; nut.textContent = 'Đang duyệt…'; }
    var fd = new FormData(f); fd.append('ajax', '1');
    fetch(location.pathname + location.search, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) {
          if (window.toast) { toast(d.msg || 'Không duyệt được.', 'canh-bao'); }
          if (nut) { nut.disabled = false; nut.textContent = 'Duyệt'; }
          return;
        }
        if (window.toast) { toast(d.msg || 'Đã duyệt.', 'ok'); }
        var tr = f.closest('tr'); if (tr) { tr.remove(); }
        capNhatSo();
      })
      .catch(function () { location.reload(); });
  });
})();
</script>
<?php dong_trang();

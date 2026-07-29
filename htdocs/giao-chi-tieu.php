<?php
/**
 * Giao chỉ tiêu năm cho từng khoa.
 * Lưu song song hai mốc: chỉ tiêu giao (quyết định) và chỉ tiêu năng lực (tính toán).
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/danh_muc.php';

$toi = bat_buoc_quyen('chitieu.giao');
$duocThemCT = co_quyen('chitieu.them');
$duocSuaCT  = co_quyen('chitieu.sua');

$nam    = (int)($_GET['nam'] ?? NAM_MAC_DINH);
$dsKhoa = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');
$idKhoa = (int)($_GET['khoa'] ?? ($dsKhoa[0]['id'] ?? 0));
$khoa   = q1('SELECT * FROM khoa WHERE id = ?', [$idKhoa]);

if (!$khoa) {
    nhan_tin('loi', 'Không tìm thấy khoa.');
    chuyen_huong('/');
}

if (la_post()) {
    kiem_tra_csrf();

    /* ---------- Thêm một dòng chỉ tiêu ngay trong bảng ---------- */
    if (post('viec') === 'them_chi_tieu') {
        if (!$duocThemCT) {
            nhan_tin('loi', 'Bạn không có quyền thêm chỉ tiêu.');
            chuyen_huong("/giao-chi-tieu.php?nam=$nam&khoa=$idKhoa");
        }
        $ten   = post('ct_ten');
        $donVi = post('ct_don_vi');
        $ma    = chu_hoa(post('ct_ma'));
        $idCha = post('ct_cha') !== '' ? (int)post('ct_cha') : null;

        $loai   = post('ct_loai', 'DEM');
        $huong  = post('ct_huong', 'CAO_TOT');
        $phanBo = post('ct_phan_bo', 'THEO_NGAY');
        $hopLe = in_array($loai, ['DEM','TRUNG_BINH','TY_LE','HANG_SO'], true)
              && in_array($huong, ['CAO_TOT','THAP_TOT','DICH_CO_DINH'], true)
              && in_array($phanBo, ['THEO_NGAY','KHONG_CHIA'], true);

        if ($ten === '') {
            nhan_tin('loi', 'Chưa nhập nội dung chỉ tiêu.');
        } elseif (!$hopLe) {
            nhan_tin('loi', 'Thông số chỉ tiêu không hợp lệ.');
        } elseif ($ma !== '' && !preg_match('/^[A-Z0-9_]{2,30}$/', $ma)) {
            nhan_tin('loi', 'Mã chỉ tiêu chỉ gồm chữ in hoa, số và gạch dưới, dài 2–30 ký tự.');
        } elseif ($ma !== '' && qVal('SELECT 1 FROM chi_tieu WHERE ma = ?', [$ma])) {
            nhan_tin('loi', "Mã chỉ tiêu \"$ma\" đã tồn tại.");
        } else {
            // Bỏ trống mã thì tự sinh từ nội dung
            if ($ma === '') {
                $ma = ma_tu_ten($ten);
            }
            // Nội dung nhỏ phải nằm dưới một nội dung lớn của chính khoa này
            if ($idCha !== null) {
                $cha = q1('SELECT c.* FROM chi_tieu c
                            JOIN chi_tieu_ap_dung a ON a.id_chi_tieu = c.id
                           WHERE c.id = ? AND a.id_khoa = ? AND c.id_cha IS NULL',
                    [$idCha, $idKhoa]);
                if (!$cha) {
                    $idCha = null;
                }
            }
            $thuTu = $idCha !== null
                ? (int)qVal('SELECT COALESCE(MAX(thu_tu),0) FROM chi_tieu WHERE id=? OR id_cha=?',
                    [$idCha, $idCha]) + 1
                : (int)qVal('SELECT COALESCE(MAX(thu_tu),0) FROM chi_tieu') + 10;

            db()->beginTransaction();
            q('INSERT INTO chi_tieu (ma, ten, don_vi, id_cha, thu_tu, loai_gia_tri, nguon, huong, phan_bo)
               VALUES (?,?,?,?,?,?,?,?,?)',
                [$ma, $ten, $donVi, $idCha, $thuTu, $loai, 'NHAP_TAY', $huong, $phanBo]);
            $idMoi = (int)db()->lastInsertId();
            q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)', [$idMoi, $idKhoa]);
            $keoTheo = [];
            if ($idCha !== null) {
                q('UPDATE chi_tieu SET nguon=? WHERE id=? AND nguon=?', ['TONG_CON', $idCha, 'NHAP_TAY']);
                $keoTheo = dong_bo_cha_con($idMoi);
            }
            db()->commit();

            ghi_nhat_ky('THEM_CHI_TIEU', $ma, "$ten (thêm từ bảng Giao chỉ tiêu, khoa {$khoa['ma']})");
            nhan_tin('ok', "Đã thêm dòng \"$ten\" (mã $ma) cho {$khoa['ten']}."
                . ($idCha !== null ? ' Nội dung lớn đã chuyển sang tự cộng từ các dòng con.' : ''));
            foreach ($keoTheo as $g) {
                nhan_tin('canh-bao', $g);
            }
            $idMoiCT = $idMoi;   // để đẩy dòng vừa thêm lên đầu bảng
        }
        // Vừa thêm thì đưa dòng mới lên đầu; tải lại trang thường (không có
        // ?moi) nó tự về đúng thứ tự.
        chuyen_huong("/giao-chi-tieu.php?nam=$nam&khoa=$idKhoa"
            . (isset($idMoiCT) ? "&moi=$idMoiCT" : '#ct-moi'));
    }

    /* ---------- Tính cột năng lực từ số giường bệnh ---------- */
    if (post('viec') === 'tinh_nang_luc') {
        db()->beginTransaction();
        $daTinh = []; $chuaTinh = [];
        foreach (chi_tieu_cua_khoa($idKhoa) as $ct) {
            if (!in_array($ct['ma'], MA_SUY_TU_GIUONG_BENH, true)) {
                continue;
            }
            $v = nang_luc_theo_giuong_benh($nam, $idKhoa, $ct['ma']);
            if ($v === null) {
                $chuaTinh[] = $ct['ten'];
                continue;
            }
            if (qVal('SELECT 1 FROM ke_hoach WHERE nam=? AND id_khoa=? AND id_chi_tieu=?',
                    [$nam, $idKhoa, $ct['id']])) {
                q('UPDATE ke_hoach SET chi_tieu_nang_luc=?
                    WHERE nam=? AND id_khoa=? AND id_chi_tieu=?',
                    [$v, $nam, $idKhoa, $ct['id']]);
            } else {
                q('INSERT INTO ke_hoach (nam, id_khoa, id_chi_tieu, chi_tieu_nang_luc)
                   VALUES (?,?,?,?)', [$nam, $idKhoa, $ct['id'], $v]);
            }
            $daTinh[] = $ct['ten'];
        }
        db()->commit();

        ghi_nhat_ky('TINH_NANG_LUC', $khoa['ma'], "Năm $nam, " . count($daTinh) . ' chỉ tiêu');
        if ($daTinh) {
            nhan_tin('ok', 'Đã tính cột năng lực cho ' . count($daTinh) . ' chỉ tiêu: '
                . implode(', ', $daTinh) . '.');
        }
        if ($chuaTinh) {
            nhan_tin('canh-bao', 'Chưa tính được: ' . implode(', ', $chuaTinh)
                . '. Cần giao trước số giường bệnh và ngày điều trị trung bình.');
        }
        chuyen_huong("/giao-chi-tieu.php?nam=$nam&khoa=$idKhoa");
    }

    $giao    = $_POST['giao']     ?? [];
    $nangLuc = $_POST['nang_luc'] ?? [];
    $truoc   = $_POST['nam_truoc'] ?? [];

    $doc = fn($m, $k) => so_tu_bieu_mau($m[$k] ?? null);

    // Sửa nội dung và đơn vị ngay trong bảng, lưu cùng lúc với chỉ tiêu
    $suaTen   = $_POST['ct_ten']    ?? [];
    $suaDonVi = $_POST['ct_don_vi'] ?? [];
    $daDoiTen = [];

    db()->beginTransaction();
    $soDong = 0;
    foreach (chi_tieu_cua_khoa($idKhoa) as $ct) {
        $id = $ct['id'];

        if ($duocSuaCT && array_key_exists($id, $suaTen)) {
            $tenMoi   = trim((string)$suaTen[$id]);
            $donViMoi = trim((string)($suaDonVi[$id] ?? $ct['don_vi']));
            // Tên rỗng thì bỏ qua, không xóa mất tên đang có
            if ($tenMoi !== '' && ($tenMoi !== $ct['ten'] || $donViMoi !== $ct['don_vi'])) {
                q('UPDATE chi_tieu SET ten = ?, don_vi = ? WHERE id = ?',
                    [$tenMoi, $donViMoi, $id]);
                $daDoiTen[] = $ct['ten'] === $tenMoi
                    ? "$tenMoi (đơn vị: {$ct['don_vi']} → $donViMoi)"
                    : "\"{$ct['ten']}\" → \"$tenMoi\"";
            }
        }

        // Chỉ tiêu không có trong biểu mẫu gửi lên thì giữ nguyên.
        // Nếu không, một lần gửi thiếu ô là xóa sạch kế hoạch cả khoa.
        if (!array_key_exists($id, $giao)
            && !array_key_exists($id, $nangLuc)
            && !array_key_exists($id, $truoc)) {
            continue;
        }

        $g = $doc($giao, $id);
        $n = $doc($nangLuc, $id);
        $t = $doc($truoc, $id);

        if ($g === null && $n === null && $t === null) {
            q('DELETE FROM ke_hoach WHERE nam=? AND id_khoa=? AND id_chi_tieu=?',
                [$nam, $idKhoa, $id]);
            continue;
        }
        $co = qVal('SELECT 1 FROM ke_hoach WHERE nam=? AND id_khoa=? AND id_chi_tieu=?',
            [$nam, $idKhoa, $id]);
        if ($co) {
            q('UPDATE ke_hoach SET chi_tieu_giao=?, chi_tieu_nang_luc=?, th_nam_truoc=?
                WHERE nam=? AND id_khoa=? AND id_chi_tieu=?',
                [$g, $n, $t, $nam, $idKhoa, $id]);
        } else {
            q('INSERT INTO ke_hoach (nam, id_khoa, id_chi_tieu, chi_tieu_giao,
                 chi_tieu_nang_luc, th_nam_truoc) VALUES (?,?,?,?,?,?)',
                [$nam, $idKhoa, $id, $g, $n, $t]);
        }
        $soDong++;
    }
    db()->commit();
    ghi_nhat_ky('GIAO_CHI_TIEU', $khoa['ma'], "Năm $nam, $soDong chỉ tiêu");
    nhan_tin('ok', "Đã lưu chỉ tiêu năm $nam cho {$khoa['ten']}.");
    if ($daDoiTen) {
        ghi_nhat_ky('SUA_CHI_TIEU', $khoa['ma'], implode(' · ', $daDoiTen));
        nhan_tin('canh-bao', 'Đã sửa nội dung ' . count($daDoiTen) . ' chỉ tiêu: '
            . implode(' · ', array_slice($daDoiTen, 0, 5))
            . '. Nội dung là danh mục dùng chung, các khoa khác cũng thấy thay đổi này.');
    }
    chuyen_huong("/giao-chi-tieu.php?nam=$nam&khoa=$idKhoa");
}

$dsCT = chi_tieu_cua_khoa($idKhoa);
$kh   = ke_hoach_nam($nam, $idKhoa);

// Vừa thêm dòng nào thì đẩy dòng đó lên đầu bảng để thấy ngay. Chỉ áp dụng khi
// URL còn ?moi=… — tải lại trang thường thì danh sách về đúng thứ tự.
$moiId = (int)($_GET['moi'] ?? 0);
if ($moiId) {
    foreach ($dsCT as $i => $c) {
        if ((int)$c['id'] === $moiId) {
            $ctMoi = $c;
            unset($dsCT[$i]);
            array_unshift($dsCT, $ctMoi);
            break;
        }
    }
    $dsCT = array_values($dsCT);
}

mo_trang('Giao chỉ tiêu');
?>
<h1>Giao chỉ tiêu năm <?= $nam ?></h1>

<form method="get" class="thanh-loc">
  <label>Năm
    <select name="nam" onchange="this.form.submit()">
      <?php for ($n = NAM_MAC_DINH + 1; $n >= NAM_MAC_DINH - 3; $n--): ?>
        <option value="<?= $n ?>" <?= $n === $nam ? 'selected' : '' ?>><?= $n ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <label>Khoa
    <select name="khoa" onchange="this.form.submit()">
      <?php foreach ($dsKhoa as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $idKhoa ? 'selected' : '' ?>>
          <?= e($k['ten']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
</form>

<?php if (!$dsCT): ?>
  <div class="tb tb-canh-bao">
    Khoa này chưa được gán chỉ tiêu nào. Vào <a href="/danh-muc-chi-tieu.php">Danh mục chỉ tiêu</a>
    để nạp danh mục mặc định trước.
  </div>
<?php else: ?>

<form method="post" class="hang-nut">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="tinh_nang_luc">
  <button class="nut nut-phu" type="submit">Tính cột năng lực theo giường bệnh</button>
  <?php mo_tro_giup('tg-cot-chi-tieu', 'Hai cột chỉ tiêu khác nhau thế nào'); ?>
    <p>
      <strong>Cột "Chỉ tiêu giao <?= $nam ?>"</strong> — điền đúng con số ở cột
      <em>Chỉ tiêu giao năm <?= $nam ?></em> trong file Excel. Đây là mẫu số của cột
      <em>SO KH(%)</em> trong báo cáo cũ, nên điền sai là phần trăm lệch hẳn so với
      báo cáo đã gửi Sở.
    </p>
    <p>
      <strong>Cột "Theo năng lực giường bệnh"</strong> — trần lý thuyết khi giường chạy hết
      công suất cả năm. Bấm nút <em>Tính cột năng lực theo giường bệnh</em>, không cần gõ tay:
    </p>
    <ul class="cong-thuc">
      <li>Tổng số ngày điều trị = Giường bệnh × <?= so_ngay_trong_nam($nam) ?> ngày</li>
      <li>Tổng số BN nội trú = Giường bệnh × <?= so_ngay_trong_nam($nam) ?> ÷ Ngày điều trị TB</li>
      <li>Công suất giường bệnh = 100%</li>
    </ul>
    <p>
      Đây đúng là cách Sở tính: QĐ 74/QĐ-SYT giao toàn viện 13.079 bệnh nhân nội trú,
      bằng 215 giường × 365 ÷ 6 ngày.
    </p>
    <p class="phu">
      Cột <em>Giao/Năng lực</em> cho biết chỉ tiêu giao đã sát năng lực chưa.
      Dưới 80% nghĩa là còn dư giường.
    </p>
  <?php dong_tro_giup(); ?>
</form>

<?php if ($duocThemCT):
    $ndLon = array_values(array_filter($dsCT, fn($c) => $c['cap'] === 0)); ?>
<div class="the-them-ct">
  <div class="the-them-ct-dau">+ Thêm chỉ tiêu cho khoa này</div>
  <div class="them-ct-hang">
    <input type="text" name="ct_ten" form="them-ct" id="o-ct-ten" class="o-ten-moi"
           placeholder="Nhập nội dung chỉ tiêu mới…" autocomplete="off">
    <input type="text" name="ct_don_vi" form="them-ct" class="o-dv-moi"
           placeholder="Đơn vị" autocomplete="off">
    <select name="ct_cha" form="them-ct" class="o-cha-moi">
      <option value="">— Là nội dung lớn —</option>
      <?php foreach ($ndLon as $c): ?>
        <option value="<?= (int)$c['id'] ?>">↳ nằm trong: <?= e($c['ten']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="nut nut-nho nut-chinh" type="submit" form="them-ct">+ Thêm dòng</button>
    <button type="button" class="nut-nang-cao" onclick="hienNangCao()">Tùy chọn khác</button>
  </div>
  <div class="luoi-nang-cao" id="dong-nang-cao" hidden>
    <label>Mã chỉ tiêu
      <input type="text" name="ct_ma" form="them-ct" placeholder="tự sinh từ nội dung">
    </label>
    <label>Loại giá trị
      <select name="ct_loai" form="them-ct">
        <?php foreach (['DEM','TRUNG_BINH','TY_LE','HANG_SO'] as $v): ?>
          <option value="<?= $v ?>"><?= e(NHAN[$v]) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Cách đánh giá
      <select name="ct_huong" form="them-ct">
        <?php foreach (['CAO_TOT','THAP_TOT','DICH_CO_DINH'] as $v): ?>
          <option value="<?= $v ?>"><?= e(NHAN[$v]) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Phân bổ ra tháng
      <select name="ct_phan_bo" form="them-ct">
        <option value="THEO_NGAY"><?= e(NHAN['THEO_NGAY']) ?></option>
        <option value="KHONG_CHIA"><?= e(NHAN['KHONG_CHIA']) ?></option>
      </select>
    </label>
  </div>
</div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="luu">
  <div class="cuon-ngang">
  <table class="bang bang-nhap">
    <thead>
      <tr>
        <th>Nội dung</th>
        <th style="width:74px">Đơn vị</th>
        <th style="width:130px">Chỉ tiêu giao <?= $nam ?></th>
        <th style="width:238px">Theo năng lực giường bệnh</th>
        <th class="phai" style="width:104px">Giao/Năng lực</th>
        <th style="width:130px">Thực hiện <?= $nam - 1 ?></th>
        <th style="width:158px">Phân bổ tháng</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($dsCT as $ct):
        $r = $kh[$ct['id']] ?? null;
        $tuTinh = $ct['nguon'] !== 'NHAP_TAY'; ?>
      <?php $laMoi = (int)$ct['id'] === $moiId; ?>
      <tr class="<?= $ct['cap'] ? 'dong-con' : '' ?><?= $laMoi ? ' vua-them' : '' ?>"
          <?= $laMoi ? 'id="dong-moi"' : '' ?>>
        <td>
          <div class="o-ten-ct">
            <?= $ct['cap'] ? '<span class="thut">↳</span>' : '' ?>
            <?php if ($duocSuaCT): ?>
              <input type="text" name="ct_ten[<?= $ct['id'] ?>]" class="o-sua-ten"
                     value="<?= e($ct['ten']) ?>" title="Sửa trực tiếp rồi bấm Lưu chỉ tiêu">
            <?php else: ?>
              <span><?= e($ct['ten']) ?></span>
            <?php endif; ?>
            <?php if ($laMoi): ?><span class="the the-nho the-moi">vừa thêm</span><?php endif; ?>
            <?php if ($ct['huong'] === 'THAP_TOT'): ?>
              <span class="the the-nho">thấp là tốt</span>
            <?php elseif ($ct['huong'] === 'DICH_CO_DINH'): ?>
              <span class="the the-nho">đích 100%</span>
            <?php endif; ?>
            <?php if ($ct['cap'] === 0 && $duocThemCT): ?>
              <button type="button" class="them-con-nut" data-cha="<?= $ct['id'] ?>"
                      title="Thêm nội dung con vào mục này">＋ con</button>
            <?php endif; ?>
          </div>
        </td>
        <td class="nho">
          <?php if ($duocSuaCT): ?>
            <input type="text" name="ct_don_vi[<?= $ct['id'] ?>]" class="o-sua-don-vi"
                   value="<?= e($ct['don_vi']) ?>">
          <?php else: ?>
            <?= e($ct['don_vi']) ?>
          <?php endif; ?>
        </td>
        <td><input type="text" inputmode="decimal" name="giao[<?= $ct['id'] ?>]"
                   value="<?= $r && $r['chi_tieu_giao'] !== null ? e(so_o_nhap($r['chi_tieu_giao'])) : '' ?>"
                   class="o-so"></td>
        <td>
          <!-- Gợi ý nằm CẠNH ô nhập, không nằm dưới: xuống dòng thì dòng bảng
               cao hơn các dòng khác và cả bảng nhấp nhô. -->
          <div class="o-kem-goi-y">
            <input type="text" inputmode="decimal" name="nang_luc[<?= $ct['id'] ?>]"
                   value="<?= $r && $r['chi_tieu_nang_luc'] !== null ? e(so_o_nhap($r['chi_tieu_nang_luc'])) : '' ?>"
                   class="o-so o-phu">
            <?php $goiY = nang_luc_theo_giuong_benh($nam, $idKhoa, $ct['ma']);
            if ($goiY !== null): ?>
              <span class="goi-y">tính được: <?= so($goiY, 2) ?></span>
            <?php endif; ?>
          </div>
        </td>
        <td class="phai nho">
          <?php
          $giaoV = $r && $r['chi_tieu_giao'] !== null ? (float)$r['chi_tieu_giao'] : null;
          $nlV   = $r && $r['chi_tieu_nang_luc'] !== null ? (float)$r['chi_tieu_nang_luc'] : null;
          if ($giaoV !== null && $nlV !== null && $nlV > 0):
              $ty = $giaoV / $nlV * 100;
              // Dưới 80% năng lực nghĩa là chỉ tiêu giao còn rộng so với số giường
              $lop = $ty < 80 ? 'ty-thap' : ($ty > 105 ? 'ty-cao' : 'ty-vua'); ?>
            <span class="<?= $lop ?>"><?= phan_tram($ty) ?></span>
          <?php else: ?>
            <span class="phu">—</span>
          <?php endif; ?>
        </td>
        <td><input type="text" inputmode="decimal" name="nam_truoc[<?= $ct['id'] ?>]"
                   value="<?= $r && $r['th_nam_truoc'] !== null ? e(so_o_nhap($r['th_nam_truoc'])) : '' ?>"
                   class="o-so o-phu"></td>
        <td class="nho mot-dong">
          <?= $ct['phan_bo'] === 'THEO_NGAY'
              ? 'chia theo số ngày'
              : '<strong>giữ nguyên mọi tháng</strong>' ?>
        </td>
      </tr>
      <?php if ($ct['cap'] === 0 && $duocThemCT): ?>
      <tr class="dong-them-con" id="them-con-<?= $ct['id'] ?>" hidden>
        <td colspan="7">
          <div class="hang-them-con">
            <span class="thut">↳</span>
            <input type="text" name="ct_ten" form="fcon-<?= $ct['id'] ?>"
                   class="o-them-con-ten" placeholder="Tên nội dung con…" autocomplete="off">
            <input type="text" name="ct_don_vi" form="fcon-<?= $ct['id'] ?>"
                   class="o-them-con-dv" placeholder="Đơn vị" autocomplete="off">
            <button class="nut nut-nho" type="submit" form="fcon-<?= $ct['id'] ?>">+ Thêm con</button>
            <button type="button" class="nut-nang-cao" data-huy-con="<?= $ct['id'] ?>">Hủy</button>
          </div>
        </td>
      </tr>
      <?php endif; ?>
    <?php endforeach; ?>

    </tbody>
  </table>
  </div>
  <p><button class="nut nut-chinh" type="submit">Lưu chỉ tiêu</button></p>
</form>

<?php if ($duocThemCT): ?>
  <!-- Mỗi nội dung lớn một biểu mẫu ẩn để nút "+ con" gửi thẳng, không lồng
       biểu mẫu vào biểu mẫu "luu" của bảng. -->
  <?php foreach ($dsCT as $c): if ($c['cap'] !== 0) continue; ?>
    <form method="post" id="fcon-<?= $c['id'] ?>" class="an">
      <?= csrf_field() ?>
      <input type="hidden" name="viec" value="them_chi_tieu">
      <input type="hidden" name="ct_cha" value="<?= $c['id'] ?>">
    </form>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($duocThemCT): ?>
<form method="post" id="them-ct" class="an">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="them_chi_tieu">
</form>
<script>
function hienNangCao() {
  var d = document.getElementById('dong-nang-cao');
  d.hidden = !d.hidden;
  if (!d.hidden) { d.querySelector('input').focus(); }
}

/* Nút "+ con" ở mỗi dòng cha: mở dòng nhập nội dung con ngay dưới nó. */
document.addEventListener('click', function (e) {
  var mo = e.target.closest('.them-con-nut');
  if (mo) {
    var r = document.getElementById('them-con-' + mo.dataset.cha);
    if (r) { r.hidden = false; var o = r.querySelector('input'); if (o) { o.focus(); } }
    return;
  }
  var huy = e.target.closest('[data-huy-con]');
  if (huy) {
    var r2 = document.getElementById('them-con-' + huy.dataset.huyCon);
    if (r2) { r2.hidden = true; }
  }
});

/* Dòng vừa thêm đang ở đầu bảng — cuộn tới cho thấy ngay. */
(function () {
  var m = document.getElementById('dong-moi');
  if (m) { m.scrollIntoView({ block: 'center' }); }
})();
</script>
<?php endif; ?>

<h2>Xem trước phân bổ 12 tháng</h2>
<p class="phu">
  Chỉ tiêu đếm được chia theo số ngày của từng tháng, không chia đều 1/12 —
  tháng 2 có <?= so_ngay_thang($nam, 2) ?> ngày nên chỉ tiêu thấp hơn tháng 1 có 31 ngày.
</p>
<div class="cuon-ngang">
<table class="bang nho">
  <thead>
    <tr><th>Nội dung</th>
      <?php for ($t = 1; $t <= 12; $t++): ?><th>T<?= $t ?></th><?php endfor; ?>
      <th>Cả năm</th></tr>
  </thead>
  <tbody>
  <?php foreach ($dsCT as $ct):
      if ($ct['cap'] || $ct['nguon'] !== 'NHAP_TAY') continue;
      $r = $kh[$ct['id']] ?? null;
      if (!$r || $r['chi_tieu_giao'] === null) continue; ?>
    <tr>
      <td><?= e($ct['ten']) ?></td>
      <?php for ($t = 1; $t <= 12; $t++): ?>
        <td class="phai"><?= so(chi_tieu_cua_ky($nam, [$t], $idKhoa, $ct['id']), 0) ?></td>
      <?php endfor; ?>
      <td class="phai"><strong><?= so((float)$r['chi_tieu_giao'], 0) ?></strong></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php dong_trang();

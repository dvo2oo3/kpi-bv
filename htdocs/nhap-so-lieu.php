<?php
/**
 * Nhập số liệu thực hiện theo tháng.
 * Khoa chỉ nhập được khoa mình và chỉ khi kỳ đang MỞ.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';

$toi = bat_buoc_dang_nhap();
if (!co_quyen('solieu.nhap') && !co_quyen('solieu.xem_tat_ca')) {
    bat_buoc_quyen('solieu.nhap');
}

// --- Chọn khoa ---
$duocPhep = cac_khoa_duoc_phep();   // null = tất cả
$dsKhoa = $duocPhep === null
    ? qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten')
    : ($duocPhep
        ? qAll('SELECT * FROM khoa WHERE hoat_dong = 1 AND id IN ('
             . implode(',', array_fill(0, count($duocPhep), '?'))
             . ') ORDER BY thu_tu, ten', $duocPhep)
        : []);

if (!$dsKhoa) {
    mo_trang('Nhập số liệu');
    echo '<div class="tb tb-canh-bao">Bạn chưa được gán khoa nào. '
       . 'Liên hệ admin để được phân công.</div>';
    dong_trang();
    exit;
}

$idKhoa = (int)($_GET['khoa'] ?? $dsKhoa[0]['id']);
if (!co_quyen_voi_khoa($idKhoa)) {
    bat_buoc_quyen('solieu.xem_tat_ca');
}
$khoa = q1('SELECT * FROM khoa WHERE id = ?', [$idKhoa]);

// --- Chọn kỳ mặc định ---
// Nhảy thẳng vào THÁNG ĐANG MỞ cho khoa này (tháng sớm nhất còn cửa nhập), để
// bác sĩ mở trang là làm việc được ngay — không rơi vào tháng đã đóng rồi tưởng
// "mở lịch rồi mà vẫn khóa". Không tháng nào mở thì lấy tháng trước như cũ.
$macDinhNam   = (int)date('Y');
$thangDangMo  = null;   // tháng năm nay còn cửa nhập cho khoa này
for ($m = 1; $m <= 12; $m++) {
    if (ky_cho_sua($macDinhNam, $m, $idKhoa)) { $thangDangMo = $m; break; }
}
$macDinhThang = $thangDangMo;
if ($macDinhThang === null) {
    $macDinhThang = (int)date('n') - 1;
    if ($macDinhThang === 0) { $macDinhThang = 12; $macDinhNam--; }
}

$nam   = (int)($_GET['nam']   ?? $macDinhNam);
$thang = (int)($_GET['thang'] ?? $macDinhThang);
$thang = max(1, min(12, $thang));

$trangThai = trang_thai_ky($nam, $thang, $idKhoa);

/*
 * Ba trạng thái thao tác:
 *   $choSua       — kỳ đang mở, nhập bình thường
 *   $duocDieuChinh— kỳ đã chốt nhưng người dùng có quyền sửa qua bút toán
 *   $cheDoDieuChinh— đang bật chế độ đó
 *
 * Kỳ đã chốt vẫn sửa được, nhưng mọi thay đổi phải để lại bút toán
 * ghi rõ giá trị cũ, giá trị mới, lý do và người thực hiện.
 */
$choSua = ky_cho_sua($nam, $thang, $idKhoa) && co_quyen('solieu.nhap');
$duocDieuChinh = !$choSua
    && $trangThai !== 'CHUA_DEN'
    && co_quyen('solieu.sua_ky_khoa');
$cheDoDieuChinh = $duocDieuChinh && ($_GET['dieu_chinh'] ?? '') === '1';
$choNhap = $choSua || $cheDoDieuChinh;

$dsCT = chi_tieu_cua_khoa($idKhoa);

// Cột chỉ tiêu xem theo tháng (phần chia của tháng) hay theo năm (chỉ tiêu giao
// cả năm). Mặc định NĂM để khỏi nhầm con số tháng nhỏ với chỉ tiêu năm.
$xemCT = ($_GET['xemct'] ?? 'nam') === 'thang' ? 'thang' : 'nam';
$khNam = ke_hoach_nam($nam, $idKhoa);

/* ---------------- Lưu ---------------- */
if (la_post()) {
    kiem_tra_csrf();
    $viec = post('viec');

    /* ---------- Bác sĩ yêu cầu admin mở lại kỳ đã chốt để sửa ---------- */
    if ($viec === 'yeu_cau_mo_lai') {
        if (!in_array($trangThai, ['DA_DUYET', 'DA_KHOA'], true)) {
            nhan_tin('loi', 'Kỳ này chưa chốt nên không cần yêu cầu mở lại.');
            chuyen_huong("/nhap-so-lieu.php?nam=$nam&thang=$thang&khoa=$idKhoa");
        }
        $lyDo = trim((string)post('ly_do'));
        if ($lyDo === '') {
            nhan_tin('loi', 'Vui lòng ghi rõ lý do cần sửa để admin xem xét.');
            chuyen_huong("/nhap-so-lieu.php?nam=$nam&thang=$thang&khoa=$idKhoa");
        }
        if (!qVal('SELECT 1 FROM ky WHERE nam=? AND thang=? AND id_khoa=?', [$nam, $thang, $idKhoa])) {
            q('INSERT INTO ky (nam, thang, id_khoa, trang_thai) VALUES (?,?,?,?)',
                [$nam, $thang, $idKhoa, $trangThai]);
        }
        // Ghi yêu cầu vào ghi_chu (tiền tố "YC:") — admin thấy trên trang Duyệt kỳ.
        $nguoi = $toi['ho_ten'] ?? ($toi['ten_dang_nhap'] ?? '');
        q('UPDATE ky SET ghi_chu=? WHERE nam=? AND thang=? AND id_khoa=?',
            ['YC: ' . $lyDo . ' — ' . $nguoi . ', ' . date('d/m/Y H:i'),
             $nam, $thang, $idKhoa]);
        ghi_nhat_ky('YEU_CAU_MO_LAI', $khoa['ma'], "Tháng $thang/$nam — $lyDo");
        nhan_tin('ok', 'Đã gửi yêu cầu mở lại cho admin. Khi admin mở lại, bạn sẽ sửa được.');
        chuyen_huong("/nhap-so-lieu.php?nam=$nam&thang=$thang&khoa=$idKhoa");
    }

    /* ---------- Sửa kỳ đã chốt bằng bút toán điều chỉnh ---------- */
    if ($viec === 'dieu_chinh') {
        if (!$duocDieuChinh) {
            ghi_nhat_ky('TU_CHOI_DIEU_CHINH', $khoa['ma'], "Tháng $thang/$nam");
            nhan_tin('loi', 'Bạn không có quyền sửa số liệu của kỳ đã chốt. '
                . 'Đề nghị admin hoặc người phát triển lập bút toán điều chỉnh.');
            chuyen_huong("/nhap-so-lieu.php?nam=$nam&thang=$thang&khoa=$idKhoa");
        }
        $lyDo = post('ly_do');
        if ($lyDo === '') {
            nhan_tin('loi', 'Phải ghi lý do điều chỉnh. Số liệu đã chốt không được sửa suông.');
            chuyen_huong("/nhap-so-lieu.php?nam=$nam&thang=$thang&khoa=$idKhoa&dieu_chinh=1");
        }

        $gt = $_POST['gt'] ?? [];
        $soDoi = 0;
        db()->beginTransaction();
        foreach ($dsCT as $ct) {
            if (!nhap_tay_duoc($ct, $idKhoa)) {
                continue;
            }
            $id = $ct['id'];
            // Không có trong biểu mẫu gửi lên thì để nguyên, KHÔNG coi là xóa
            if (!array_key_exists($id, $gt)) {
                continue;
            }
            $moi = so_tu_bieu_mau((string)$gt[$id]);
            $cuRaw = qVal('SELECT gia_tri FROM so_lieu
                            WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
                [$nam, $thang, $idKhoa, $id]);
            $cu = $cuRaw === null ? null : (float)$cuRaw;

            if ($cu === $moi || ($cu !== null && $moi !== null && abs($cu - $moi) < 1e-9)) {
                continue;   // không đổi thì không ghi bút toán
            }

            q('INSERT INTO dieu_chinh (nam, thang, id_khoa, id_chi_tieu, gia_tri_cu,
                 gia_tri_moi, ly_do, trang_thai, nguoi_de_xuat, nguoi_duyet)
               VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$nam, $thang, $idKhoa, $id, $cu, $moi, $lyDo,
                 'DA_DUYET', $toi['id'], $toi['id']]);

            if ($cuRaw === null && !qVal('SELECT 1 FROM so_lieu
                    WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
                    [$nam, $thang, $idKhoa, $id])) {
                q('INSERT INTO so_lieu (nam, thang, id_khoa, id_chi_tieu, gia_tri, nguoi_nhap)
                   VALUES (?,?,?,?,?,?)', [$nam, $thang, $idKhoa, $id, $moi, $toi['id']]);
            } else {
                q('UPDATE so_lieu SET gia_tri=?, nguoi_nhap=?, thoi_diem=CURRENT_TIMESTAMP
                    WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
                    [$moi, $toi['id'], $nam, $thang, $idKhoa, $id]);
            }
            $soDoi++;
        }
        db()->commit();

        if ($soDoi === 0) {
            nhan_tin('canh-bao', 'Không có giá trị nào thay đổi nên chưa ghi bút toán nào.');
        } else {
            ghi_nhat_ky('DIEU_CHINH_SO_LIEU', $khoa['ma'],
                "Tháng $thang/$nam — $soDoi chỉ tiêu — $lyDo");
            nhan_tin('ok', "Đã điều chỉnh $soDoi chỉ tiêu và ghi bút toán. "
                . 'Giá trị cũ vẫn được lưu lại để đối chiếu.');
        }
        chuyen_huong("/nhap-so-lieu.php?nam=$nam&thang=$thang&khoa=$idKhoa");
    }

    /* ---------- Dev: xóa toàn bộ bút toán điều chỉnh của kỳ (dọn log) ----------
     * Chỉ xóa bản ghi lưu vết trong bảng dieu_chinh; KHÔNG đụng số liệu hiện tại. */
    if ($viec === 'xoa_dieu_chinh') {
        if (!co_quyen('dieuchinh.xoa')) {
            ghi_nhat_ky('TU_CHOI_XOA_DIEU_CHINH', $khoa['ma'], "Tháng $thang/$nam");
            nhan_tin('loi', 'Bạn không có quyền xóa bút toán điều chỉnh.');
            chuyen_huong("/nhap-so-lieu.php?nam=$nam&thang=$thang&khoa=$idKhoa");
        }
        $soXoa = q('DELETE FROM dieu_chinh WHERE nam=? AND thang=? AND id_khoa=?',
                   [$nam, $thang, $idKhoa])->rowCount();
        ghi_nhat_ky('XOA_DIEU_CHINH', $khoa['ma'], "Tháng $thang/$nam — xóa $soXoa bút toán");
        nhan_tin('ok', $soXoa > 0
            ? "Đã xóa $soXoa bút toán điều chỉnh của kỳ này. Số liệu hiện tại giữ nguyên."
            : 'Kỳ này không có bút toán nào để xóa.');
        chuyen_huong("/nhap-so-lieu.php?nam=$nam&thang=$thang&khoa=$idKhoa");
    }

    if ($viec === 'luu' || $viec === 'nop') {
        if (!$choSua) {
            nhan_tin('loi', 'Kỳ này không còn cho sửa (' . ten_trang_thai($trangThai) . ').');
            chuyen_huong("/nhap-so-lieu.php?nam=$nam&thang=$thang&khoa=$idKhoa");
        }

        $gt = $_POST['gt'] ?? [];
        $cd = $_POST['chien_dich'] ?? [];
        $gc = $_POST['ghi_chu'] ?? [];

        db()->beginTransaction();
        foreach ($dsCT as $ct) {
            if (!nhap_tay_duoc($ct, $idKhoa)) {
                continue;   // dòng cha (có con) và dòng công thức không lưu
            }
            $id = $ct['id'];
            if (!array_key_exists($id, $gt)) {
                continue;   // ô không có trong biểu mẫu thì giữ nguyên giá trị cũ
            }
            $val  = so_tu_bieu_mau((string)$gt[$id]);
            $laCD = !empty($cd[$id]) ? 1 : 0;
            $ghi  = trim((string)($gc[$id] ?? '')) ?: null;

            $co = qVal('SELECT 1 FROM so_lieu WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
                [$nam, $thang, $idKhoa, $id]);
            if ($co) {
                q('UPDATE so_lieu SET gia_tri=?, la_chien_dich=?, ghi_chu=?, nguoi_nhap=?,
                     thoi_diem=CURRENT_TIMESTAMP
                    WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
                    [$val, $laCD, $ghi, $toi['id'], $nam, $thang, $idKhoa, $id]);
            } else {
                q('INSERT INTO so_lieu (nam, thang, id_khoa, id_chi_tieu, gia_tri,
                     la_chien_dich, ghi_chu, nguoi_nhap) VALUES (?,?,?,?,?,?,?,?)',
                    [$nam, $thang, $idKhoa, $id, $val, $laCD, $ghi, $toi['id']]);
            }
        }

        // Bảo đảm có bản ghi kỳ
        if (!qVal('SELECT 1 FROM ky WHERE nam=? AND thang=? AND id_khoa=?',
                [$nam, $thang, $idKhoa])) {
            q('INSERT INTO ky (nam, thang, id_khoa, trang_thai) VALUES (?,?,?,?)',
                [$nam, $thang, $idKhoa, 'MO']);
        }

        if ($viec === 'nop') {
            q('UPDATE ky SET trang_thai=?, nguoi_nop=?, thoi_diem_nop=CURRENT_TIMESTAMP
                WHERE nam=? AND thang=? AND id_khoa=?',
                ['DA_NOP', $toi['id'], $nam, $thang, $idKhoa]);
            ghi_nhat_ky('NOP_KY', $khoa['ma'], "Tháng $thang/$nam");
            nhan_tin('ok', "Đã nộp số liệu tháng $thang/$nam. Chờ admin duyệt.");
        } else {
            ghi_nhat_ky('LUU_SO_LIEU', $khoa['ma'], "Tháng $thang/$nam");
            nhan_tin('ok', 'Đã lưu số liệu.');
        }
        db()->commit();
        chuyen_huong("/nhap-so-lieu.php?nam=$nam&thang=$thang&khoa=$idKhoa");
    }
}

/* ---------------- Đọc để hiển thị ---------------- */
$hienTai = [];
foreach (qAll('SELECT * FROM so_lieu WHERE nam=? AND thang=? AND id_khoa=?',
        [$nam, $thang, $idKhoa]) as $r) {
    $hienTai[(int)$r['id_chi_tieu']] = $r;
}

// Bút toán điều chỉnh mới nhất của từng chỉ tiêu (để hiện dấu "cũ → mới" ngay trên dòng)
$dieuChinh = [];
foreach (qAll('SELECT * FROM dieu_chinh WHERE nam=? AND thang=? AND id_khoa=? ORDER BY thoi_diem, id',
        [$nam, $thang, $idKhoa]) as $r) {
    $dieuChinh[(int)$r['id_chi_tieu']] = $r;   // vòng tăng dần → giữ cái mới nhất
}

// Mốc tháng trước để so tăng/giảm (giúp admin soi số bất thường khi duyệt)
$thangTr = $thang - 1; $namTr = $nam;
if ($thangTr === 0) { $thangTr = 12; $namTr--; }

/* Kiểm tra chéo */
$canhBao = [];
foreach ($dsCT as $ct) {
    if ($ct['nguon'] !== 'TONG_CON' || $ct['cap'] > 0) {
        continue;
    }
    $tongCon = null;
    foreach (con_cua($ct['id'], $idKhoa) as $con) {
        $v = $hienTai[$con['id']]['gia_tri'] ?? null;
        if ($v !== null) {
            $tongCon = ($tongCon ?? 0) + (float)$v;
        }
    }
    if ($tongCon !== null && $ct['ma'] === 'KQDT') {
        $ctNT = chi_tieu_theo_ma('NT');
        $bnNT = $ctNT ? gia_tri_thang($nam, $thang, $idKhoa, $ctNT['id']) : null;
        if ($bnNT !== null && abs($tongCon - $bnNT) > 0.01) {
            $canhBao[] = sprintf(
                'Kết quả điều trị cộng lại là %s nhưng tổng bệnh nhân nội trú là %s — lệch %s.',
                so($tongCon), so($bnNT), so(abs($tongCon - $bnNT)));
        }
    }
}
// Công suất giường bệnh bất thường
$ctCS = chi_tieu_theo_ma('CSGB');
if ($ctCS) {
    $cs = gia_tri_thang($nam, $thang, $idKhoa, $ctCS['id']);
    if ($cs !== null && ($cs > 120 || $cs < 30)) {
        $canhBao[] = sprintf('Công suất giường bệnh tháng này là %s — nằm ngoài khoảng 30%%–120%%, '
            . 'kiểm tra lại số ngày điều trị và số giường.', phan_tram($cs));
    }
}
// Lệch nhiều so với 3 tháng gần nhất
foreach ($dsCT as $ct) {
    if ($ct['nguon'] !== 'NHAP_TAY' || $ct['loai_gia_tri'] !== 'DEM') {
        continue;
    }
    $v = $hienTai[$ct['id']]['gia_tri'] ?? null;
    if ($v === null) {
        continue;
    }
    $truoc = [];
    for ($i = 1; $i <= 3; $i++) {
        $t = $thang - $i; $n = $nam;
        if ($t < 1) { $t += 12; $n--; }
        $x = gia_tri_thang($n, $t, $idKhoa, $ct['id']);
        if ($x !== null) {
            $truoc[] = $x;
        }
    }
    if (count($truoc) >= 2) {
        $tb = array_sum($truoc) / count($truoc);
        if ($tb > 5 && abs((float)$v - $tb) / $tb > 0.5) {
            $canhBao[] = sprintf('%s: tháng này %s, trung bình 3 tháng gần nhất %s — chênh trên 50%%.',
                $ct['ten'], so((float)$v), so($tb));
        }
    }
}

mo_trang('Nhập số liệu');
?>
<h1>Nhập số liệu — <?= e($khoa['ten']) ?></h1>

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
  <label>Tháng
    <select name="thang" onchange="this.form.submit()">
      <?php for ($t = 1; $t <= 12; $t++): ?>
        <option value="<?= $t ?>" <?= $t === $thang ? 'selected' : '' ?>>Tháng <?= $t ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <span class="trang-thai-ky tt-<?= e(strtolower($trangThai)) ?>">
    <?= e(ten_trang_thai($trangThai)) ?>
  </span>
  <div class="chon-xem" role="group" aria-label="Xem chỉ tiêu theo">
    <span class="chon-xem-nhan">Cột chỉ tiêu:</span>
    <a href="?khoa=<?= $idKhoa ?>&nam=<?= $nam ?>&thang=<?= $thang ?>&xemct=nam"
       class="<?= $xemCT === 'nam' ? 'chon' : '' ?>">Cả năm</a>
    <a href="?khoa=<?= $idKhoa ?>&nam=<?= $nam ?>&thang=<?= $thang ?>&xemct=thang"
       class="<?= $xemCT === 'thang' ? 'chon' : '' ?>">Theo tháng</a>
  </div>
</form>

<?php
// Gợi ý tháng đang mở khi đang xem một tháng không nhập được
$goiYMo = ($thangDangMo !== null && !($nam === $macDinhNam && $thang === $thangDangMo))
    ? '<a href="?nam=' . $macDinhNam . '&thang=' . $thangDangMo . '&khoa=' . $idKhoa . '">'
      . '<strong>Tháng ' . $thangDangMo . '/' . $macDinhNam . ' đang mở — vào nhập</strong></a>'
    : '';
?>
<?php if ($trangThai === 'CHUA_DEN'): ?>
  <div class="tb tb-canh-bao">Tháng <?= $thang ?>/<?= $nam ?> chưa kết thúc, chưa nhập được.
    <?= $goiYMo ?></div>

<?php elseif ($cheDoDieuChinh): ?>
  <div class="tb tb-loi">
    <strong>Đang ở chế độ điều chỉnh.</strong>
    Kỳ này <?= e(chu_thuong(ten_trang_thai($trangThai))) ?> — mọi giá trị bạn sửa sẽ được ghi
    thành bút toán kèm giá trị cũ, lý do và tên bạn. Không sửa suông được.
    <a href="?nam=<?= $nam ?>&thang=<?= $thang ?>&khoa=<?= $idKhoa ?>">Thoát chế độ điều chỉnh</a>
  </div>

<?php elseif (!$choSua): ?>
  <div class="tb tb-canh-bao">
    Kỳ này <strong><?= e(ten_trang_thai($trangThai)) ?></strong> nên chỉ xem, không sửa được.
    <?php if ($duocDieuChinh): ?>
      Bạn có quyền sửa kỳ đã chốt, nhưng phải qua bút toán điều chỉnh —
      <a href="?nam=<?= $nam ?>&thang=<?= $thang ?>&khoa=<?= $idKhoa ?>&dieu_chinh=1">
        <strong>bật chế độ điều chỉnh</strong></a>.
    <?php endif; ?>
    <?= $goiYMo ?>
  </div>
  <?php // Bác sĩ xin admin MỞ LẠI kỳ đã duyệt/khóa để sửa
  if (in_array($trangThai, ['DA_DUYET', 'DA_KHOA'], true)):
      $kyTt = ban_ghi_ky($nam, $thang, $idKhoa);
      $daGuiYC = $kyTt && str_starts_with((string)($kyTt['ghi_chu'] ?? ''), 'YC:'); ?>
    <?php if ($daGuiYC): ?>
      <div class="tb tb-ok">
        ✔ <strong>Đã gửi yêu cầu mở lại</strong> — đang chờ admin duyệt.
        <small class="phu">(<?= e(trim(substr($kyTt['ghi_chu'], 3))) ?>)</small>
      </div>
    <?php else: ?>
      <div class="tb">
        <form method="post" class="hang-yeu-cau">
          <?= csrf_field() ?>
          <input type="hidden" name="viec" value="yeu_cau_mo_lai">
          <strong>Cần sửa lại số liệu?</strong>
          <input type="text" name="ly_do" required
                 placeholder="Ghi lý do (VD: nhập nhầm số lượt khám) để xin admin mở lại">
          <button class="nut nut-nho nut-canh" type="submit">Yêu cầu mở lại</button>
        </form>
      </div>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<?php foreach ($canhBao as $cb): ?>
  <div class="tb tb-canh-bao"><?= e($cb) ?></div>
<?php endforeach; ?>

<?php if (!$dsCT): ?>
  <div class="tb tb-loi">Khoa này chưa được gán chỉ tiêu nào.</div>
<?php else: ?>
<form method="post">
  <?= csrf_field() ?>
  <div class="cuon-ngang">
  <table class="bang bang-nhap">
    <thead>
      <tr>
        <th class="giua" style="width:42px">STT</th>
        <th style="width:34%">Nội dung</th>
        <th>Đơn vị</th>
        <th>Chỉ tiêu <?= $xemCT === 'nam' ? 'cả năm' : 'tháng ' . $thang ?></th>
        <th>Thực hiện</th>
        <th class="giua">So tháng trước</th>
        <th>Đợt/chiến dịch</th>
        <th>Ghi chú</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($dsCT as $ct):
        $id = $ct['id'];
        $r  = $hienTai[$id] ?? null;
        $tuTinh = !nhap_tay_duoc($ct, $idKhoa);
        $ctThang = chi_tieu_cua_ky($nam, [$thang], $idKhoa, $id);
        // Giá trị cột chỉ tiêu: theo năm dùng chỉ tiêu giao (chỉ tiêu tỷ lệ/hằng
        // số giữ nguyên, chỉ tiêu đếm là tổng cả năm); theo tháng dùng phần chia.
        $giaNam = $khNam[$id]['chi_tieu_giao'] ?? null;
        $ctHien = $xemCT === 'nam'
            ? ($giaNam !== null ? (float)$giaNam : chi_tieu_cua_ky($nam, range(1, 12), $idKhoa, $id))
            : $ctThang;
        $tinh = $tuTinh ? gia_tri_thang($nam, $thang, $idKhoa, $id) : null;
        $leSo = in_array($ct['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true) ? 1 : 0;
        // So với tháng trước để soi số bất thường khi duyệt
        $curV   = $tuTinh ? $tinh : ($r['gia_tri'] ?? null);
        $truocV = gia_tri_thang($namTr, $thangTr, $idKhoa, $id);
        $daNhapV = $curV !== null;
        $doiTruoc = $daNhapV && $truocV !== null && abs((float)$curV - (float)$truocV) > 1e-9; ?>
      <tr class="<?= $ct['cap'] ? 'dong-con' : '' ?> <?= $tuTinh ? 'dong-tinh' : '' ?>"
          data-id="<?= (int)$id ?>" data-cha="<?= $ct['id_cha'] !== null ? (int)$ct['id_cha'] : '' ?>"
          data-nguon="<?= e($ct['nguon']) ?>" data-le="<?= (int)$leSo ?>">
        <td class="giua nho"><?= $ct['cap'] ? '' : (int)($ct['vi_tri'] ?? 0) ?></td>
        <td>
          <?= $ct['cap'] ? '<span class="thut">↳</span> ' : '' ?><?= e($ct['ten']) ?>
          <?php if ($tuTinh && $ct['nguon'] === 'TONG_CON'): ?>
            <span class="the the-nho">tổng của con</span>
          <?php elseif ($tuTinh && $ct['nguon'] === 'CONG_THUC'): ?>
            <span class="the the-nho">tự tính</span>
          <?php endif; ?>
        </td>
        <td class="nho"><?= e($ct['don_vi']) ?></td>
        <td class="phai nho"><?= so($ctHien, $leSo) ?></td>
        <td>
          <?php if ($tuTinh): ?>
            <span class="gia-tri-tinh" data-tinh="<?= (int)$id ?>"><?= $leSo ? so($tinh, 1) : so($tinh) ?></span>
          <?php elseif (!$choNhap):
            // Kỳ đã chốt: hiện thẳng con số, không dựng ô nhập rỗng màu xám
            $daNhap = $r && $r['gia_tri'] !== null; ?>
            <span class="gia-tri-khoa <?= $daNhap ? 'gia-tri-moi' : 'trong' ?><?= $doiTruoc ? ' co-doi' : '' ?>">
              <?= $daNhap ? so((float)$r['gia_tri'], $leSo) : 'chưa nhập' ?>
            </span>
          <?php else: ?>
            <input type="text" inputmode="decimal" name="gt[<?= $id ?>]"
                   class="o-so<?= $doiTruoc ? ' co-doi' : '' ?>"
                   value="<?= e(so_o_nhap($r['gia_tri'] ?? null)) ?>">
          <?php endif; ?>
          <?php if (isset($dieuChinh[$id])):
              $dc = $dieuChinh[$id];
              $dcCu  = $dc['gia_tri_cu']  === null ? 'chưa nhập' : so((float)$dc['gia_tri_cu'], $leSo);
              $dcMoi = $dc['gia_tri_moi'] === null ? 'xóa'       : so((float)$dc['gia_tri_moi'], $leSo); ?>
            <div class="dau-dieu-chinh" title="Đã điều chỉnh — lý do: <?= e($dc['ly_do']) ?>">
              ✎ đã sửa: <span class="dc-cu"><?= $dcCu ?></span> → <span class="dc-moi"><?= $dcMoi ?></span>
            </div>
          <?php endif; ?>
        </td>
        <td class="giua nho o-thang-truoc">
          <?php if (!$daNhapV): ?>
            <span class="phu">—</span>
          <?php elseif ($truocV === null): ?>
            <span class="delta delta-moi" title="Tháng <?= $thangTr ?> chưa có số">mới</span>
          <?php elseif (!$doiTruoc): ?>
            <span class="delta delta-bang" title="Bằng tháng <?= $thangTr ?>: <?= so($truocV, $leSo) ?>">＝</span>
          <?php elseif (abs((float)$truocV) < 1e-9): // tháng trước = 0 → không chia được, chỉ báo tăng ?>
            <span class="delta delta-len" title="Tháng <?= $thangTr ?>: 0 → nay: <?= so($curV, $leSo) ?>">▲ mới</span>
          <?php else:
              $tang = (float)$curV >= (float)$truocV;
              $ty   = abs(((float)$curV - (float)$truocV) / (float)$truocV) * 100;
              // Thay đổi vừa phải → hiện %; quá lớn (>10 lần) → hiện "gấp N lần" cho dễ hiểu
              if ($ty < 1000) {
                  $txtDelta = ($ty < 10 ? number_format($ty, 1, '.', '') : (string)(int)round($ty)) . '%';
              } else {
                  $txtDelta = 'gấp ' . (string)(int)round((float)$curV / abs((float)$truocV)) . ' lần';
              } ?>
            <span class="delta delta-<?= $tang ? 'len' : 'giam' ?>"
                  title="Tháng <?= $thangTr ?>: <?= so($truocV, $leSo) ?> → nay: <?= so($curV, $leSo) ?>">
              <?= $tang ? '▲' : '▼' ?> <?= $txtDelta ?>
            </span>
          <?php endif; ?>
        </td>
        <td class="giua">
          <?php if ($tuTinh): ?>
          <?php elseif (!$choNhap): ?>
            <?= $r && (int)$r['la_chien_dich'] === 1
                ? '<span class="the the-nho">đợt</span>' : '' ?>
          <?php else: ?>
            <input type="checkbox" name="chien_dich[<?= $id ?>]" value="1"
                   <?= $r && (int)$r['la_chien_dich'] === 1 ? 'checked' : '' ?>>
          <?php endif; ?>
        </td>
        <td class="nho">
          <?php if ($tuTinh): ?>
          <?php elseif (!$choNhap): ?>
            <?= $r && $r['ghi_chu'] ? e($r['ghi_chu']) : '' ?>
          <?php else: ?>
            <input type="text" name="ghi_chu[<?= $id ?>]" class="o-ghi-chu"
                   value="<?= $r ? e($r['ghi_chu'] ?? '') : '' ?>">
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <p class="phu">
    Ô để trống nghĩa là <strong>chưa nhập</strong>, khác hẳn số 0. Tháng chưa nhập sẽ không bị
    đưa vào lũy kế và không làm sai công suất giường bệnh.
    Đánh dấu <strong>Đợt/chiến dịch</strong> cho số liệu của các đợt khám tập trung để không
    làm méo xu hướng các tháng.
  </p>

  <?php if ($choSua): ?>
    <p class="hang-nut">
      <button class="nut" type="submit" name="viec" value="luu">Lưu tạm</button>
      <button class="nut nut-nhan" type="submit" name="viec" value="nop"
              data-xac-nhan="Nộp số liệu tháng <?= $thang ?>/<?= $nam ?>? Sau khi nộp sẽ không sửa được nữa.">
        Nộp cho admin
      </button>
    </p>
  <?php elseif ($cheDoDieuChinh): ?>
    <div class="khung-dieu-chinh">
      <label>Lý do điều chỉnh <span class="bat-buoc">bắt buộc</span>
        <input type="text" name="ly_do" required
               placeholder="VD: Khoa báo sót 5 ca phẫu thuật, đối chiếu lại sổ mổ ngày 28/6">
      </label>
      <button class="nut nut-nguy" type="submit" name="viec" value="dieu_chinh"
              data-xac-nhan="Ghi bút toán điều chỉnh cho tháng <?= $thang ?>/<?= $nam ?>?">
        Ghi bút toán điều chỉnh
      </button>
      <small class="dc-ghichu">Lý do được lưu vĩnh viễn cùng giá trị cũ và tên người sửa.</small>
    </div>
  <?php endif; ?>
</form>

<?php
/* Lịch sử điều chỉnh của kỳ này */
$dsDC = qAll(
    'SELECT dc.*, ct.ten AS ten_chi_tieu, ct.don_vi, nd.ho_ten
       FROM dieu_chinh dc
       JOIN chi_tieu ct ON ct.id = dc.id_chi_tieu
  LEFT JOIN nguoi_dung nd ON nd.id = dc.nguoi_de_xuat
      WHERE dc.nam = ? AND dc.thang = ? AND dc.id_khoa = ?
   ORDER BY dc.thoi_diem DESC, dc.id DESC',
    [$nam, $thang, $idKhoa]);
if ($dsDC): ?>
<h2>Bút toán điều chỉnh của kỳ này</h2>
<p class="phu">
  Số liệu đã chốt nhưng được sửa sau đó. Giá trị cũ được giữ lại để đối chiếu với
  báo cáo đã gửi đi trước khi sửa.
</p>
<div class="cuon-ngang">
<table class="bang">
  <thead>
    <tr><th>Thời điểm</th><th>Nội dung</th><th class="phai">Giá trị cũ</th>
        <th class="phai">Giá trị mới</th><th class="phai">Chênh</th>
        <th>Lý do</th><th>Người thực hiện</th></tr>
  </thead>
  <tbody>
  <?php foreach ($dsDC as $d):
      $cu  = $d['gia_tri_cu']  === null ? null : (float)$d['gia_tri_cu'];
      $moi = $d['gia_tri_moi'] === null ? null : (float)$d['gia_tri_moi'];
      $chenh = ($cu !== null && $moi !== null) ? $moi - $cu : null; ?>
    <tr>
      <td class="nho"><?= e(ngay_gio($d['thoi_diem'])) ?></td>
      <td><?= e($d['ten_chi_tieu']) ?> <span class="phu"><?= e($d['don_vi']) ?></span></td>
      <td class="phai"><?= $cu === null ? '<span class="phu">chưa nhập</span>' : so($cu) ?></td>
      <td class="phai"><strong><?= $moi === null ? '<span class="phu">xóa</span>' : so($moi) ?></strong></td>
      <td class="phai <?= $chenh !== null && $chenh < 0 ? 'chenh-am' : 'chenh-duong' ?>">
        <?= $chenh === null ? '—' : ($chenh > 0 ? '+' : '') . so($chenh) ?>
      </td>
      <td class="nho"><?= e($d['ly_do']) ?></td>
      <td class="nho"><?= e($d['ho_ten'] ?? '—') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php if (co_quyen('dieuchinh.xoa')): ?>
  <form method="post" style="margin-top:12px"
        data-xac-nhan="Xóa TOÀN BỘ <?= count($dsDC) ?> bút toán điều chỉnh của tháng <?= $thang ?>/<?= $nam ?>? Số liệu hiện tại GIỮ NGUYÊN, chỉ mất phần lịch sử lưu vết. Không hoàn tác được."
        data-xac-nhan-loai="nguy">
    <?= csrf_field() ?>
    <input type="hidden" name="viec" value="xoa_dieu_chinh">
    <button class="nut nut-nho nut-nguy" type="submit">Xóa toàn bộ bút toán của kỳ này</button>
  </form>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<script>
/* Preview trực tiếp: gõ số con → dòng "tổng của con" (TONG_CON) tự cộng và hiện ngay,
   chưa cần Lưu. Cộng từ dưới lên nên tổng lồng nhau (con của con) cũng đúng. */
(function () {
  var tbody = document.querySelector('.bang-nhap tbody');
  if (!tbody) { return; }
  var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-id]'));

  function dinhDang(n, le) {
    return n.toLocaleString('vi-VN', { minimumFractionDigits: le ? 1 : 0, maximumFractionDigits: le ? 1 : 0 });
  }
  function giaTri(tr) {
    var inp = tr.querySelector('input[name^="gt"]');
    if (inp) {                                   // ô nhập: số thô, chấm = thập phân
      var v = inp.value.trim().replace(',', '.');
      if (v === '') { return null; }
      var f = parseFloat(v); return isNaN(f) ? null : f;
    }
    var sp = tr.querySelector('.gia-tri-tinh, .gia-tri-khoa');
    if (sp) {                                    // ô tự tính / kỳ đã khóa: 28.500 (chấm = nghìn, phẩy = thập phân)
      var t = sp.textContent.trim().replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
      if (t === '') { return null; }
      var g = parseFloat(t); return isNaN(g) ? null : g;
    }
    return null;
  }
  function tinhLai() {
    for (var i = rows.length - 1; i >= 0; i--) {   // dưới lên: con tính trước
      var tr = rows[i];
      if (tr.dataset.nguon !== 'TONG_CON') { continue; }
      var span = tr.querySelector('.gia-tri-tinh');
      if (!span) { continue; }                    // TONG_CON không có con trong khoa → là ô nhập
      var pid = tr.dataset.id, tong = null, co = false, coNhap = false;
      for (var j = 0; j < rows.length; j++) {
        if (rows[j].dataset.cha === pid) {
          var v = giaTri(rows[j]);
          if (v !== null) { tong = (tong || 0) + v; co = true; }
          if (rows[j].querySelector('input[name^="gt"]')) { coNhap = true; }
        }
      }
      span.textContent = co ? dinhDang(tong, tr.dataset.le === '1') : '—';
      span.classList.toggle('gia-tri-preview', co && coNhap);   // chỉ tô xanh khi đang gõ (kỳ mở)
    }
  }
  tbody.addEventListener('input', function (e) {
    if (e.target.matches && e.target.matches('input[name^="gt"]')) { tinhLai(); }
  });
  tinhLai();
})();
</script>
<style>
  .gia-tri-tinh.gia-tri-preview { color: var(--xanh-500, #2563eb); font-weight: 600; }
</style>
<?php dong_trang();

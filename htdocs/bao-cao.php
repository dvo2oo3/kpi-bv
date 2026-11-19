<?php
/**
 * Báo cáo và KPI: theo khoa hoặc toàn viện, mọi kỳ.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/bao_cao.php';
require_once __DIR__ . '/app/danh_muc.php';   // la_he_thong, gop_mem_chi_tieu (nút gộp trên báo cáo)

$toi = bat_buoc_dang_nhap();
if (!co_quyen('baocao.toan_vien') && !co_quyen('baocao.khoa_minh')) {
    bat_buoc_quyen('baocao.khoa_minh');
}

$nam = (int)($_GET['nam'] ?? NAM_MAC_DINH);
// Mặc định "đến nay": lũy kế tới tháng gần nhất đã xong (năm cũ = cả 12 tháng).
$namNay = (int)date('Y');
$thangDenNay = ($nam === $namNay) ? min(12, max(1, (int)date('n') - 1)) : 12;
$kyDenNay = $thangDenNay . 'T';
$ky = (string)($_GET['ky'] ?? $kyDenNay);
if (!in_array($ky, CAC_KY, true) && !preg_match('/^\d{1,2}T$/', $ky)) {
    $ky = $kyDenNay;
}
$goc = ($_GET['goc'] ?? 'giao') === 'nang_luc' ? 'nang_luc' : 'giao';

$duocPhep = cac_khoa_duoc_phep();
$dsKhoa = $duocPhep === null
    ? qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten')
    : ($duocPhep ? qAll('SELECT * FROM khoa WHERE hoat_dong = 1 AND id IN ('
        . implode(',', array_fill(0, count($duocPhep), '?')) . ') ORDER BY thu_tu, ten', $duocPhep)
      : []);

$xemToanVien = co_quyen('baocao.toan_vien');
$pham_vi = $_GET['pv'] ?? ($xemToanVien ? 'toan_vien' : 'khoa');
if ($pham_vi === 'toan_vien' && !$xemToanVien) {
    $pham_vi = 'khoa';
}
$idKhoa = (int)($_GET['khoa'] ?? ($dsKhoa[0]['id'] ?? 0));
if ($pham_vi === 'khoa' && $idKhoa && !co_quyen_voi_khoa($idKhoa)) {
    bat_buoc_quyen('baocao.toan_vien');
}

$cacThang = cac_thang_cua_ky($ky);

// Cho kéo-thả đổi vị trí NGAY trong báo cáo — chỉ ở kiểu "Theo khoa", người có
// quyền sửa chỉ tiêu và có quyền với khoa đó. Ghi vào thứ tự RIÊNG của khoa
// (chi_tieu_ap_dung.thu_tu) — giống hệt trang Giao chỉ tiêu.
$duocSapXep = $pham_vi === 'khoa' && $idKhoa
    && co_quyen('chitieu.sua') && co_quyen_voi_khoa($idKhoa);

if (la_post() && post('viec') === 'sap_xep' && $duocSapXep) {
    kiem_tra_csrf();
    $ids = array_values(array_filter(array_map('intval', explode(',', post('ids')))));
    $thuoc = [];
    foreach (qAll('SELECT id_chi_tieu FROM chi_tieu_ap_dung WHERE id_khoa = ?', [$idKhoa]) as $r) {
        $thuoc[(int)$r['id_chi_tieu']] = true;
    }
    $ids = array_values(array_filter($ids, fn($id) => isset($thuoc[$id])));
    if ($ids) {
        db()->beginTransaction();
        $tt = 10;
        foreach ($ids as $id) {
            q('UPDATE chi_tieu_ap_dung SET thu_tu = ? WHERE id_chi_tieu = ? AND id_khoa = ?',
                [$tt, $id, $idKhoa]);
            $tt += 10;
        }
        db()->commit();
        ghi_nhat_ky('SAP_XEP_CHI_TIEU', (string)$idKhoa, count($ids) . ' dòng (từ báo cáo)');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ----- Số TOÀN VIỆN nhập tay: lưu / xoá ngay từ báo cáo (admin xem toàn viện) -----
$duocSuaTV = $pham_vi === 'toan_vien' && $xemToanVien;
$bcSoTay = function ($v) {              // "120.000" → 120000 ; "5,4" → 5.4 ; "" → null
    $v = trim((string)$v);
    if ($v === '') { return null; }
    $v = str_replace(['.', ' '], '', $v);
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? (float)$v : null;
};
if (la_post() && post('viec') === 'luu_tv' && $duocSuaTV) {
    kiem_tra_csrf();
    $idCt = (int)post('id');
    luu_so_toan_vien($nam, $idCt, $bcSoTay(post('kh')), $bcSoTay(post('th')),
        (trim((string)post('ghi_chu')) !== '' ? trim((string)post('ghi_chu')) : null));
    ghi_nhat_ky('SO_TOAN_VIEN_TAY', (string)$idCt,
        "năm $nam: KH=" . (post('kh') !== '' ? post('kh') : '—') . " TH=" . (post('th') !== '' ? post('th') : '—'));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}
if (la_post() && post('viec') === 'xoa_tv' && $duocSuaTV) {
    kiem_tra_csrf();
    xoa_so_toan_vien($nam, (int)post('id'));
    ghi_nhat_ky('SO_TOAN_VIEN_TAY', (string)post('id'), "năm $nam: bỏ nhập tay");
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}
// Sửa THẲNG một ô trên báo cáo (truong = kh | th). Rỗng = bỏ nhập tay ô đó.
if (la_post() && post('viec') === 'luu_tv_o' && $duocSuaTV) {
    kiem_tra_csrf();
    $idCt   = (int)post('id');
    $truong = post('truong') === 'kh' ? 'kh' : 'th';
    $gt     = $bcSoTay(post('gia_tri'));
    dat_so_toan_vien_o($nam, $idCt, $truong, $gt);
    ghi_nhat_ky('SO_TOAN_VIEN_TAY', (string)$idCt,
        "năm $nam: sửa ô " . ($truong === 'kh' ? 'KH' : 'TH') . " = " . (post('gia_tri') !== '' ? post('gia_tri') : '—'));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}
// Sửa THẲNG một ô THÁNG của toàn viện (đè phép cộng khoa của tháng đó). Rỗng = bỏ đè.
if (la_post() && post('viec') === 'luu_tv_thang' && $duocSuaTV) {
    kiem_tra_csrf();
    $idCt  = (int)post('id');
    $thang = (int)post('thang');
    if ($thang >= 1 && $thang <= 12) {
        dat_so_toan_vien_thang($nam, $thang, $idCt, $bcSoTay(post('gia_tri')));
        ghi_nhat_ky('SO_TOAN_VIEN_TAY', (string)$idCt,
            "năm $nam T$thang = " . (post('gia_tri') !== '' ? post('gia_tri') : '—'));
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ----- Gộp MỀM từ báo cáo: tích NHIỀU dòng → gộp về 1 bản giữ, CHỌN cách tính -----
$duocGopBC = $pham_vi === 'toan_vien' && co_quyen('chitieu.gop');
if (la_post() && post('viec') === 'gop_bc' && $duocGopBC) {
    kiem_tra_csrf();
    $traJson = function (array $x) { header('Content-Type: application/json; charset=utf-8');
        echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; };
    $giuId = (int)post('giu');
    $tatCa = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)post('ids'))), fn($x) => $x > 0)));
    $boIds = array_values(array_filter($tatCa, fn($x) => $x !== $giuId));
    // Cách tính khi gộp: cộng dồn (tổng) hay trung bình.
    $kieu  = post('kieu') === 'trung_binh' ? 'TRUNG_BINH' : 'DEM';
    $giu = q1('SELECT * FROM chi_tieu WHERE id = ?', [$giuId]);
    if (!$giu)      { $traJson(['ok' => false, 'loi' => 'Chưa chọn dòng giữ lại.']); }
    if (!$boIds)    { $traJson(['ok' => false, 'loi' => 'Cần tích ít nhất 2 dòng để gộp.']); }
    $kq = gop_mem_chi_tieu($giuId, $boIds);
    if (!empty($kq['loi'])) { $traJson(['ok' => false, 'loi' => $kq['loi']]); }
    // Đặt cách tính cho kết quả gộp (cộng dồn = DEM, trung bình = TRUNG_BINH).
    q('UPDATE chi_tieu SET loai_gia_tri = ? WHERE id = ?', [$kieu, $giuId]);
    ghi_nhat_ky('GOP_MEM_CHI_TIEU', $giu['ma'],
        'Gộp ' . $kq['gop'] . ' dòng vào "' . $giu['ten'] . '" (' . ($kieu === 'TRUNG_BINH' ? 'trung bình' : 'cộng dồn') . ')');
    $traJson(['ok' => true, 'msg' => 'Đã gộp ' . $kq['gop'] . ' dòng vào "' . $giu['ten'] . '".']);
}

// ----- BÚT TOÁN: sửa thẳng 1 ô tháng của KHOA trên báo cáo → ghi dieu_chinh + cập nhật so_lieu -----
$duocButToan = $pham_vi === 'khoa' && $idKhoa && co_quyen('dieuchinh.duyet') && co_quyen_voi_khoa($idKhoa);
if (la_post() && post('viec') === 'but_toan' && $duocButToan) {
    kiem_tra_csrf();
    $traJson = function (array $x) { header('Content-Type: application/json; charset=utf-8');
        echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; };
    $idCt  = (int)post('id');
    $lyDo  = trim((string)post('ly_do'));
    $cheDo = post('che_do') === 'luyke' ? 'luyke' : 'thang';   // luyke = gõ tổng (bản thường); thang = số tháng
    $ct = q1('SELECT * FROM chi_tieu WHERE id = ?', [$idCt]);
    if (!$ct)                         { $traJson(['ok' => false, 'loi' => 'Không tìm thấy chỉ tiêu.']); }
    if (!nhap_tay_duoc($ct, $idKhoa)) { $traJson(['ok' => false, 'loi' => 'Chỉ tiêu này do hệ thống tự tính, không sửa tay được.']); }
    if ($lyDo === '')                 { $traJson(['ok' => false, 'loi' => 'Cần ghi lý do điều chỉnh.']); }
    $muc = so_tu_bieu_mau(post('gia_tri'));   // giá trị người dùng gõ

    if ($cheDo === 'luyke') {
        // Bản thường gõ MỤC TIÊU lũy kế → quy về bút toán ở THÁNG CUỐI KỲ.
        $thang  = max($cacThang);
        $loaiHL = qVal('SELECT loai_gia_tri FROM chi_tieu_ap_dung WHERE id_chi_tieu=? AND id_khoa=?',
            [$idCt, $idKhoa]) ?: $ct['loai_gia_tri'];
        $cuRaw  = qVal('SELECT gia_tri FROM so_lieu WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
            [$nam, $thang, $idKhoa, $idCt]);
        $cu = $cuRaw === null ? null : (float)$cuRaw;
        if ($loaiHL === 'HANG_SO') {
            $moi = $muc;   // số tồn: lũy kế = tháng cuối → đặt thẳng
        } else {
            $luyKe = gia_tri_luy_ke($nam, $cacThang, $idKhoa, $idCt);   // lũy kế hiện tại
            $moi = ($cu ?? 0) + (($muc ?? 0) - ($luyKe ?? 0));          // dồn chênh lệch vào tháng cuối
        }
    } else {
        $thang = (int)post('thang');
        if ($thang < 1 || $thang > 12) { $traJson(['ok' => false, 'loi' => 'Tháng không hợp lệ.']); }
        $moi   = $muc;
        $cuRaw = qVal('SELECT gia_tri FROM so_lieu WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
            [$nam, $thang, $idKhoa, $idCt]);
        $cu = $cuRaw === null ? null : (float)$cuRaw;
    }
    if ($cu === $moi || ($cu !== null && $moi !== null && abs($cu - $moi) < 1e-9)) {
        $traJson(['ok' => true, 'msg' => 'Không đổi.']);
    }
    db()->beginTransaction();
    q('INSERT INTO dieu_chinh (nam, thang, id_khoa, id_chi_tieu, gia_tri_cu, gia_tri_moi, ly_do,
         trang_thai, nguoi_de_xuat, nguoi_duyet)
       VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$nam, $thang, $idKhoa, $idCt, $cu, $moi, $lyDo, 'DA_DUYET', $toi['id'], $toi['id']]);
    if (qVal('SELECT 1 FROM so_lieu WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
            [$nam, $thang, $idKhoa, $idCt])) {
        q('UPDATE so_lieu SET gia_tri=?, nguoi_nhap=?, thoi_diem=CURRENT_TIMESTAMP
            WHERE nam=? AND thang=? AND id_khoa=? AND id_chi_tieu=?',
            [$moi, $toi['id'], $nam, $thang, $idKhoa, $idCt]);
    } else {
        q('INSERT INTO so_lieu (nam, thang, id_khoa, id_chi_tieu, gia_tri, nguoi_nhap)
           VALUES (?,?,?,?,?,?)', [$nam, $thang, $idKhoa, $idCt, $moi, $toi['id']]);
    }
    db()->commit();
    ghi_nhat_ky('DIEU_CHINH_SO_LIEU', $ct['ma'],
        "T$thang/$nam khoa#$idKhoa: " . ($cu ?? '—') . ' → ' . ($moi ?? '—') . " · $lyDo");
    $traJson(['ok' => true, 'msg' => 'Đã ghi bút toán điều chỉnh T' . $thang . '.']);
}


// Xuất Excel nhanh cho view đang xem (sheet khoa theo QĐ33b + sheet toàn viện QĐ74).
if (!empty($_GET['xuat']) && (co_quyen('baocao.xuat') || co_quyen('baocao.giam_doc'))) {
    require_once __DIR__ . '/app/bao_cao_gd.php';
    $cfg = bao_cao_gd_chuan([
        'ky'             => 'nam',
        'toan_vien'      => $pham_vi === 'toan_vien' ? 1 : 0,
        'khoa'           => ($pham_vi === 'khoa' && $idKhoa) ? [$idKhoa] : 'all',
        'chi_tiet_thang' => !empty($_GET['thang']) ? 1 : 0,
        'mau'            => 'chi_tiet',
    ]);
    xuat_bao_cao_giam_doc($nam, $cfg);
    exit;
}

mo_trang('Báo cáo & KPI');
?>
<h1>Báo cáo thực hiện chỉ tiêu — <?= e(ten_ky($ky)) ?> năm <?= $nam ?></h1>

<form method="get" class="thanh-loc">
  <label>Năm
    <select name="nam" onchange="this.form.submit()">
      <?php for ($n = NAM_MAC_DINH; $n >= NAM_MAC_DINH - 3; $n--): ?>
        <option value="<?= $n ?>" <?= $n === $nam ? 'selected' : '' ?>><?= $n ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <label>Kỳ
    <select name="ky" onchange="this.form.submit()">
      <?php if (!in_array($kyDenNay, CAC_KY, true)): ?>
        <option value="<?= $kyDenNay ?>" <?= $ky === $kyDenNay ? 'selected' : '' ?>><?= $thangDenNay ?> tháng (đến nay)</option>
      <?php endif; ?>
      <?php foreach (CAC_KY as $k): ?>
        <option value="<?= $k ?>" <?= $k === $ky ? 'selected' : '' ?>><?= e(ten_ky($k)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Phạm vi
    <select name="pv" onchange="this.form.submit()">
      <?php if ($xemToanVien): ?>
        <option value="toan_vien" <?= $pham_vi === 'toan_vien' ? 'selected' : '' ?>>Toàn viện</option>
      <?php endif; ?>
      <option value="khoa" <?= $pham_vi === 'khoa' ? 'selected' : '' ?>>Theo khoa</option>
    </select>
  </label>
  <?php if ($pham_vi === 'khoa'): ?>
  <label>Khoa
    <select name="khoa" onchange="this.form.submit()">
      <?php foreach ($dsKhoa as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $idKhoa ? 'selected' : '' ?>>
          <?= e($k['ten']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <label>Mốc chỉ tiêu
    <select name="goc" onchange="this.form.submit()">
      <option value="giao"     <?= $goc === 'giao' ? 'selected' : '' ?>>Chỉ tiêu giao (QĐ)</option>
      <option value="nang_luc" <?= $goc === 'nang_luc' ? 'selected' : '' ?>>Chỉ tiêu năng lực</option>
    </select>
  </label>
  <label class="o-chon" style="align-self:flex-end;display:inline-flex;gap:6px;align-items:center;font-weight:600;white-space:nowrap">
    <input type="checkbox" name="thang" value="1" <?= !empty($_GET['thang']) ? 'checked' : '' ?> onchange="this.form.submit()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex:0 0 auto;opacity:.7"><rect x="3" y="4.5" width="18" height="17" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/></svg>Số liệu 12 tháng
  </label>
  <?php if (co_quyen('baocao.xuat') || co_quyen('baocao.giam_doc')): ?>
  <a class="nut nut-nho nut-phu" style="align-self:flex-end;white-space:nowrap"
     href="/bao-cao.php?nam=<?= $nam ?>&ky=<?= e($ky) ?>&pv=<?= e($pham_vi) ?><?= $idKhoa ? '&khoa=' . (int)$idKhoa : '' ?>&goc=<?= e($goc) ?><?= !empty($_GET['thang']) ? '&thang=1' : '' ?>&xuat=1"
     title="Tải Excel đúng view đang xem (khoa QĐ33b + toàn viện QĐ74)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px"><path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M5 21h14"/></svg> Xuất Excel</a>
  <?php endif; ?>
</form>

<?php
$bang = $pham_vi === 'toan_vien'
    ? bang_toan_vien($nam, $cacThang, $goc)
    : bang_theo_khoa($nam, $cacThang, $idKhoa, $goc);

// Xem chi tiết 12 tháng (đối chiếu bảng Excel): T1..T12 + lũy kế Quý/6T/9T/năm.
$xem12  = !empty($_GET['thang']);
$idCh   = $pham_vi === 'khoa' ? $idKhoa : null;
$lkRanges = ['q1' => [1,2,3], 'h6' => [1,2,3,4,5,6], 'n9' => range(1,9), 'yr' => range(1,12)];
$tvLK = null;                               // toàn viện: lũy kế từng kỳ, map theo mã
if ($xem12 && $pham_vi === 'toan_vien') {
    $tvLK = [];
    foreach ($lkRanges as $k => $rng) {
        $tvLK[$k] = [];
        foreach (bang_toan_vien($nam, $rng, $goc) as $r) { $tvLK[$k][$r['ct']['ma']] = $r['thuc_hien']; }
    }
}
/** Lũy kế của 1 dòng theo kỳ (khoa dùng gia_tri_luy_ke, toàn viện dùng map đã tính). */
$bcLuyKe = function (array $d, string $key, array $rng) use ($pham_vi, $idKhoa, $nam, $tvLK) {
    return $pham_vi === 'khoa'
        ? gia_tri_luy_ke($nam, $rng, $idKhoa, (int)$d['ct']['id'])
        : ($tvLK[$key][$d['ct']['ma']] ?? null);
};

// Tổng hợp mức độ đạt
$dem = ['dat' => 0, 'canh_bao' => 0, 'khong_dat' => 0, 'vuot' => 0, 'na' => 0];
foreach ($bang as $d) {
    if ($d['cap'] === 0) {
        $dem[$d['kpi']['danh_gia']]++;
    }
}
$tienDoKyVong = count($cacThang) === 12 ? 100 : round(max($cacThang) / 12 * 100);
?>

<div class="the-tom-tat">
  <div class="o-tom-tat o-dat"><strong><?= $dem['dat'] ?></strong><span>Đạt</span></div>
  <div class="o-tom-tat o-canh-bao"><strong><?= $dem['canh_bao'] ?></strong><span>Gần đạt</span></div>
  <div class="o-tom-tat o-khong-dat"><strong><?= $dem['khong_dat'] ?></strong><span>Chưa đạt</span></div>
  <div class="o-tom-tat o-vuot"><strong><?= $dem['vuot'] ?></strong><span>Vượt/quá tải</span></div>
  <div class="o-tom-tat o-na"><strong><?= $dem['na'] ?></strong><span>Chưa đủ dữ liệu</span></div>
</div>

<p class="phu">
  Hết <?= e(ten_ky($ky)) ?>, tiến độ kỳ vọng là <strong><?= $tienDoKyVong ?>%</strong> kế hoạch năm.
</p>
<p class="phu">
  <strong>So KH kỳ</strong> so với chỉ tiêu đã chia cho riêng kỳ này — dùng để chấm đạt/chưa đạt,
  công bằng khi năm chưa hết. <strong>So KH năm</strong> so với chỉ tiêu cả năm — đúng cách tính
  trong bảng Excel đang dùng, là con số in ra báo cáo gửi Sở.
  Chỉ tiêu <em>thấp là tốt</em> và <em>đích 100%</em> được chấm theo cách riêng, không so thẳng phần trăm.
</p>

<?php if ($duocSapXep): ?>
  <p class="phu" style="margin:.25rem 0"><span class="the the-nho">Kéo <span aria-hidden="true">⠿</span> hoặc bấm ▲▼ để đổi vị trí — chỉ đổi thứ tự của khoa này.</span></p>
<?php endif; ?>
<?php if ($xem12): ?>
<style>
  .nhan-ton,.nhan-tyle{display:inline-block;font-size:10.5px;font-weight:700;line-height:1.3;padding:0 6px;border-radius:999px;margin-left:5px;vertical-align:middle;white-space:nowrap}
  .nhan-ton{background:#e0f2fe;color:#075985;border:1px solid #7dd3fc}
  .nhan-tyle{background:#f3e8ff;color:#6b21a8;border:1px solid #d8b4fe}
  tr.dong-ton>td:first-child{box-shadow:inset 3px 0 0 #38bdf8}
</style>
<p class="phu" style="margin:.25rem 0">Số liệu từng tháng + lũy kế 3T / 6T / 9T / 12T (hết Quý I / 6 tháng / 9 tháng / cả năm) — để đối chiếu bảng Excel.
  Dòng gắn <span class="nhan-ton">số tồn</span> (giường, quản lý bệnh) và <span class="nhan-tyle">tỷ lệ/TB</span> (công suất, ngày ĐT TB) <strong>KHÔNG cộng dồn</strong> — lũy kế lấy số cuối kỳ / tính lại; các dòng <em>lượt</em> còn lại thì cộng dồn.</p>
<div class="cuon-ngang">
<table class="bang bang-12t" style="font-size:12.5px">
  <thead>
    <tr>
      <th class="giua" style="width:42px">STT</th>
      <th style="min-width:210px">Nội dung</th>
      <th>ĐVT</th>
      <th class="phai">KH năm</th>
      <th class="phai">T1</th><th class="phai">T2</th><th class="phai">T3</th>
      <th class="phai" style="background:#fef3c7" title="Lũy kế 3 tháng (hết Quý I)">3T</th>
      <th class="phai">T4</th><th class="phai">T5</th><th class="phai">T6</th>
      <th class="phai" style="background:#fef3c7" title="Lũy kế 6 tháng">6T</th>
      <th class="phai">T7</th><th class="phai">T8</th><th class="phai">T9</th>
      <th class="phai" style="background:#fef3c7" title="Lũy kế 9 tháng">9T</th>
      <th class="phai">T10</th><th class="phai">T11</th><th class="phai">T12</th>
      <th class="phai" style="background:#fde68a" title="Lũy kế cả năm (12 tháng)">12T</th>
    </tr>
  </thead>
  <tbody>
  <?php $stt12 = 0; foreach ($bang as $d):
      $loai2  = $d['ct']['loai_gia_tri'];
      $le2    = in_array($loai2, ['TY_LE', 'TRUNG_BINH'], true) ? 1 : 0;
      $laTon  = $loai2 === 'HANG_SO';
      $laTyLe = $le2 === 1;
      if (!$d['cap']) { $stt12++; }
      $m = chuoi_12_thang($nam, $idCh, $d['ct']['ma']);
      $idRow = (int)$d['ct']['id'];
      $btOk = $duocButToan && nhap_tay_duoc($d['ct'], $idKhoa);   // view khoa: sửa ô tháng = bút toán
      $tvSuaThang = $duocSuaTV;                                   // view toàn viện: sửa ô tháng = đè theo tháng
      $coDeThang  = $tvSuaThang && count(so_toan_vien_thang_cua_nam($nam, $idRow)) > 0;
      $oT = function ($t) use ($m, $le2, $btOk, $tvSuaThang, $idRow) {
          $val = $m[$t] ?? null; $s = so($val, $le2);
          if ($btOk) {
              return '<td class="phai"><span class="bc-bt-sua" data-id="' . $idRow
                   . '" data-thang="' . $t . '" data-raw="' . e(so_o_nhap($val))
                   . '" title="Bấm để sửa (ghi bút toán điều chỉnh)">' . $s . '</span></td>';
          }
          if ($tvSuaThang) {
              return '<td class="phai"><span class="bc-o-sua" data-id="' . $idRow
                   . '" data-truong="thang" data-thang="' . $t . '" data-raw="' . e(so_o_nhap($val))
                   . '" title="Bấm để sửa số tháng (toàn viện) — đè phép cộng khoa">' . $s . '</span></td>';
          }
          return '<td class="phai">' . $s . '</td>';
      };
      // Lũy kế: dòng ĐÃ đè tháng thì tính lại từ chuỗi cho khớp; còn lại dùng số precompute.
      $lk = function (string $key, array $rng) use ($coDeThang, $m, $loai2, $bcLuyKe, $d) {
          if ($coDeThang) { $v = luy_ke_tu_chuoi($m, $loai2, $rng); if ($v !== null) { return $v; } }
          return $bcLuyKe($d, $key, $rng);
      }; ?>
    <tr class="<?= $d['cap'] ? 'dong-con' : '' ?><?= $laTon ? ' dong-ton' : '' ?>">
      <td class="giua nho bc-stt"><?php if ($duocGopBC): ?><input type="checkbox" class="bc-gop-chk" data-id="<?= (int)$d['ct']['id'] ?>" data-ten="<?= e($d['ct']['ten']) ?>" title="Tích để gộp với dòng khác"><?php endif; ?><span class="bc-num"><?= $d['cap'] ? '' : $stt12 ?></span></td>
      <td><?= $d['cap'] ? '<span class="thut">↳</span> ' : '' ?><?= e($d['ct']['ten']) ?><?php if ($laTon): ?> <span class="nhan-ton" title="Số tồn — lũy kế lấy số CUỐI KỲ, không cộng dồn các tháng">số tồn</span><?php elseif ($laTyLe): ?> <span class="nhan-tyle" title="Tỷ lệ / trung bình — tính lại theo công thức, không cộng dồn">tỷ lệ/TB</span><?php endif; ?></td>
      <td class="nho"><?= e($d['ct']['don_vi']) ?></td>
      <td class="phai nho"><?php if ($duocSuaTV): ?><span class="bc-o-sua" data-id="<?= (int)$d['ct']['id'] ?>" data-truong="kh" data-raw="<?= e(so_o_nhap($d['chi_tieu_nam'])) ?>" title="Bấm để sửa KH năm (toàn viện)"><?= so($d['chi_tieu_nam'], $le2) ?></span><?php else: ?><?= so($d['chi_tieu_nam'], $le2) ?><?php endif; ?></td>
      <?= $oT(1) ?><?= $oT(2) ?><?= $oT(3) ?>
      <td class="phai" style="background:#fef3c7"><strong><?= so($lk('q1', [1,2,3]), $le2) ?></strong></td>
      <?= $oT(4) ?><?= $oT(5) ?><?= $oT(6) ?>
      <td class="phai" style="background:#fef3c7"><strong><?= so($lk('h6', [1,2,3,4,5,6]), $le2) ?></strong></td>
      <?= $oT(7) ?><?= $oT(8) ?><?= $oT(9) ?>
      <td class="phai" style="background:#fef3c7"><strong><?= so($lk('n9', range(1,9)), $le2) ?></strong></td>
      <?= $oT(10) ?><?= $oT(11) ?><?= $oT(12) ?>
      <td class="phai" style="background:#fde68a"><strong><?= so($lk('yr', range(1,12)), $le2) ?></strong></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<div class="cuon-ngang">
<table class="bang<?= $duocSapXep ? ' bang-sapxep' : '' ?>" id="bang-bc"<?= $duocSapXep ? ' data-sapxep="1"' : '' ?>>
  <thead>
    <tr>
      <th class="giua" style="width:42px">STT</th>
      <th style="width:28%">Nội dung</th>
      <th>Đơn vị</th>
      <th class="phai">Chỉ tiêu kỳ</th>
      <th class="phai">Chỉ tiêu năm</th>
      <th class="phai">Thực hiện</th>
      <th class="phai">So KH kỳ</th>
      <th class="phai">So KH năm</th>
      <th>Đánh giá</th>
      <th class="phai">Cùng kỳ <?= $nam - 1 ?></th>
    </tr>
  </thead>
  <tbody>
  <?php $sttDem = 0; $thangCuoiKy = $cacThang ? max($cacThang) : 12;
    foreach ($bang as $d):
      $le = in_array($d['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true) ? 1 : 0;
      $btOkTh = $duocButToan && nhap_tay_duoc($d['ct'], $idKhoa);   // view khoa: sửa Thực hiện = bút toán
      if (!$d['cap']) { $sttDem++; }   // đánh số chạy cho dòng lớn (đúng cả 2 kiểu xem) ?>
    <tr class="<?= $d['cap'] ? 'dong-con' : '' ?>"<?php if ($duocSapXep): ?> data-id="<?= (int)$d['ct']['id'] ?>" data-cha="<?= $d['ct']['id_cha'] !== null ? (int)$d['ct']['id_cha'] : '' ?>"<?php endif; ?>>
      <td class="giua nho bc-stt"><?php if ($duocGopBC): ?><input type="checkbox" class="bc-gop-chk" data-id="<?= (int)$d['ct']['id'] ?>" data-ten="<?= e($d['ct']['ten']) ?>" title="Tích để gộp với dòng khác"><?php endif; ?><span class="bc-num"><?= $d['cap'] ? '' : $sttDem ?></span><?php if ($duocSapXep && !$d['cap']): ?><span class="bc-dk"><span class="bc-keo" draggable="true" title="Kéo để đổi vị trí">⠿</span><button type="button" class="bc-mui" title="Chuyển lên" onclick="bcDoiCho(<?= (int)$d['ct']['id'] ?>,-1)">▲</button><button type="button" class="bc-mui" title="Chuyển xuống" onclick="bcDoiCho(<?= (int)$d['ct']['id'] ?>,1)">▼</button></span><?php endif; ?></td>
      <td><?= $d['cap'] ? '<span class="thut">↳</span> ' : '' ?><?= e($d['ct']['ten']) ?></td>
      <td class="nho"><?= e($d['ct']['don_vi']) ?></td>
      <td class="phai"><?= so($d['chi_tieu'], $le) ?></td>
      <td class="phai nho"><?php if ($duocSuaTV): ?><span class="bc-o-sua" data-id="<?= (int)$d['ct']['id'] ?>" data-truong="kh" data-raw="<?= e(so_o_nhap($d['chi_tieu_nam'])) ?>" title="Bấm để sửa KH năm (toàn viện)"><?= so($d['chi_tieu_nam'], $le) ?></span><?php else: ?><?= so($d['chi_tieu_nam'], $le) ?><?php endif; ?></td>
      <td class="phai">
        <?php if ($duocSuaTV): ?><strong class="bc-o-sua" data-id="<?= (int)$d['ct']['id'] ?>" data-truong="th" data-raw="<?= e(so_o_nhap($d['thuc_hien'])) ?>" title="Bấm để sửa số thực hiện (toàn viện)"><?= so($d['thuc_hien'], $le) ?></strong><?php elseif ($btOkTh): ?><strong class="bc-bt-sua" data-id="<?= (int)$d['ct']['id'] ?>" data-thang="<?= $thangCuoiKy ?>" data-che-do="luyke" data-raw="<?= e(so_o_nhap($d['thuc_hien'])) ?>" title="Bấm để sửa số thực hiện — ghi bút toán vào tháng cuối kỳ"><?= so($d['thuc_hien'], $le) ?></strong><?php else: ?><strong><?= so($d['thuc_hien'], $le) ?></strong><?php endif; ?>
        <?php if (!empty($d['tay'])): ?>
          <span class="nhan-tay" title="Số toàn viện nhập tay (máy cộng khoa: <?= so($d['may_cong_th'], $le) ?>)">nhập tay</span>
        <?php endif; ?>
        <?php // Thay đổi so với cùng kỳ năm trước — tô màu theo hướng tốt/xấu
        $th = $d['thuc_hien']; $tr = $d['nam_truoc'];
        if ($th !== null && $tr !== null && (float)$tr != 0.0):
            $tang  = (float)$th >= (float)$tr;
            $delta = ((float)$th - (float)$tr) / abs((float)$tr) * 100;
            $huong = $d['ct']['huong'] ?? 'CAO_TOT';
            $lop   = $huong === 'DICH_CO_DINH' ? 'trung'
                   : (($huong === 'THAP_TOT' ? !$tang : $tang) ? 'tot' : 'xau'); ?>
          <span class="delta delta-<?= $lop ?>" title="So với cùng kỳ <?= $nam - 1 ?>: <?= so($tr, $le) ?>">
            <?= $tang ? '▲' : '▼' ?> <?= so(abs($delta), 1) ?>%
          </span>
        <?php endif; ?>
      </td>
      <td class="phai"><?= phan_tram($d['kpi']['phan_tram']) ?></td>
      <td class="phai nho"><?= phan_tram($d['pt_nam']) ?></td>
      <td><span class="dg dg-<?= e($d['kpi']['danh_gia']) ?>"><?= e($d['kpi']['mo_ta']) ?></span></td>
      <td class="phai nho"><?= so($d['nam_truoc'], $le) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; /* xem12 */ ?>

<?php if (!$bang): ?>
  <div class="tb tb-canh-bao">
    Chưa có dữ liệu. Cần nạp <a href="/danh-muc-chi-tieu.php">thư viện chỉ tiêu</a>,
    <a href="/giao-chi-tieu.php">giao chỉ tiêu</a> rồi nhập số liệu.
  </div>
<?php endif; ?>

<?php if ($duocSuaTV): ?>
<input type="hidden" id="bc-tv-csrf" value="<?= e(csrf_token()) ?>">
<style>
  .nhan-tay { display:inline-block; font-size:10.5px; font-weight:700; color:#6d28d9;
    background:#f3e8ff; border-radius:999px; padding:0 7px; margin-left:4px; vertical-align:middle; }
  .bc-o-sua { cursor:pointer; }
  .bc-o-sua:hover { background:#f3e8ff; color:#6d28d9; border-radius:3px; box-shadow:inset 0 -1.5px 0 #c4b5fd; }
  .bc-o-input { width:100%; min-width:64px; box-sizing:border-box; padding:2px 6px;
    border:1px solid #7c3aed; border-radius:5px; text-align:right; font:inherit; }
</style>
<script>
/* Nhập tay = bấm thẳng vào ô KH/TH để sửa; Enter/rời ô lưu, Esc huỷ. Xoá rỗng = bỏ nhập tay. */
(function () {
  var csrf = document.getElementById('bc-tv-csrf'); if (!csrf) { return; }
  var dangSua = null;
  function luu(span, val) {
    var fd = new FormData();
    if (span.dataset.truong === 'thang') {
      fd.append('viec', 'luu_tv_thang'); fd.append('id', span.dataset.id);
      fd.append('thang', span.dataset.thang); fd.append('gia_tri', val);
    } else {
      fd.append('viec', 'luu_tv_o'); fd.append('id', span.dataset.id);
      fd.append('truong', span.dataset.truong); fd.append('gia_tri', val);
    }
    fd.append('_csrf', csrf.value);
    fetch(location.pathname + location.search, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok) { location.reload(); } else { alert(d.loi || 'Không lưu được'); } })
      .catch(function () { location.reload(); });
  }
  function moO(span) {
    dangSua = span;
    var raw = span.dataset.raw || '';
    var inp = document.createElement('input');
    inp.type = 'text'; inp.className = 'bc-o-input'; inp.value = raw; inp.setAttribute('inputmode', 'decimal');
    span.replaceWith(inp); inp.focus(); inp.select();
    var xong = false;
    function ket(luuLai) {
      if (xong) { return; } xong = true;
      if (luuLai && inp.value.trim() !== raw.trim()) { luu(span, inp.value.trim()); return; }
      inp.replaceWith(span); dangSua = null;
    }
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { ket(true); } else if (e.key === 'Escape') { ket(false); }
    });
    inp.addEventListener('blur', function () { ket(true); });
  }
  document.addEventListener('click', function (e) {
    var span = e.target.closest('.bc-o-sua');
    if (span && !dangSua) { moO(span); }
  });
})();
</script>
<?php endif; ?>

<?php if ($duocButToan): ?>
<input type="hidden" id="bc-bt-csrf" value="<?= e(csrf_token()) ?>">
<style>
  .bc-bt-sua { cursor:pointer; }
  .bc-bt-sua:hover { background:#fee2e2; color:#991b1b; border-radius:3px; box-shadow:inset 0 -1.5px 0 #fca5a5; }
  .bc-o-input { width:100%; min-width:58px; box-sizing:border-box; padding:2px 5px;
    border:1px solid #dc2626; border-radius:5px; text-align:right; font:inherit; }
</style>
<p class="phu" style="margin:.35rem 0">✎ Bấm vào ô <strong>Thực hiện</strong> (bản thường) hoặc <strong>ô tháng</strong> (xem 12 tháng) để sửa số của khoa ngay tại đây —
  hệ thống tự ghi <strong>bút toán điều chỉnh</strong> (lưu số cũ + lý do để đối chiếu), khỏi vào trang nhập liệu.
  Bản thường: số gõ là <em>mục tiêu lũy kế</em>, chênh lệch dồn vào tháng cuối kỳ.</p>
<script>
/* Bút toán: bấm ô tháng → sửa → hỏi lý do 1 lần/phiên → ghi dieu_chinh + cập nhật so_lieu. */
(function () {
  var csrf = document.getElementById('bc-bt-csrf'); if (!csrf) { return; }
  var lyDo = '', dangSua = null;
  function luuNgay(span, val) {
    var fd = new FormData();
    fd.append('viec', 'but_toan'); fd.append('id', span.dataset.id); fd.append('thang', span.dataset.thang || '');
    fd.append('che_do', span.dataset.cheDo || 'thang');
    fd.append('gia_tri', val); fd.append('ly_do', lyDo); fd.append('_csrf', csrf.value);
    fetch(location.pathname + location.search, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok) { location.reload(); } else { alert(d.loi || 'Không ghi được'); } })
      .catch(function () { location.reload(); });
  }
  function moO(span) {
    dangSua = span; var raw = span.dataset.raw || '';
    var inp = document.createElement('input');
    inp.type = 'text'; inp.className = 'bc-o-input'; inp.value = raw; inp.setAttribute('inputmode', 'decimal');
    span.replaceWith(inp); inp.focus(); inp.select();
    var xong = false;
    function ket(ok) {
      if (xong) { return; }
      if (ok && inp.value.trim() !== raw.trim()) {
        if (!lyDo) {
          var r = (prompt('Lý do điều chỉnh (ghi vào bút toán — dùng chung cho các lần sửa sau trong phiên này):', '') || '').trim();
          if (!r) { return; }   // huỷ → giữ ô nhập để sửa tiếp hoặc Esc
          lyDo = r;
        }
        xong = true; luuNgay(span, inp.value.trim()); return;
      }
      xong = true; inp.replaceWith(span); dangSua = null;
    }
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { ket(true); } else if (e.key === 'Escape') { ket(false); } });
    inp.addEventListener('blur', function () { ket(true); });
  }
  document.addEventListener('click', function (e) { var s = e.target.closest('.bc-bt-sua'); if (s && !dangSua) { moO(s); } });
})();
</script>
<?php endif; ?>

<?php if ($duocGopBC): ?>
<input type="hidden" id="bc-gop-csrf" value="<?= e(csrf_token()) ?>">
<div id="bc-gop-bar" hidden>
  <span id="bc-gop-dem">Đã chọn 0 dòng</span>
  <button type="button" class="nut nut-nho" id="bc-gop-mo">Gộp</button>
  <button type="button" class="nut nut-nho" id="bc-an">Ẩn</button>
  <button type="button" class="nut nut-nho" id="bc-xuat">Xuất CSV</button>
  <button type="button" class="nut nut-nho nut-phu" id="bc-gop-clear">Bỏ chọn</button>
</div>
<div id="bc-an-undo" hidden>
  <span id="bc-an-dem">Đã ẩn 0 dòng</span>
  <button type="button" class="nut nut-nho nut-phu" id="bc-an-hien">Hiện lại</button>
</div>
<div id="bc-gop-modal" hidden>
  <div class="bc-gop-pop">
    <div class="bc-gop-tit">Gộp các dòng đã chọn về một</div>
    <div class="bc-gop-lb">Giữ lại tên dòng nào:</div>
    <div id="bc-gop-ds"></div>
    <div class="bc-gop-lb" style="margin-top:12px">Cách tính khi gộp:</div>
    <label class="bc-gop-kieu"><input type="radio" name="bc-kieu" value="cong_don" checked> Cộng dồn (A + B)</label>
    <label class="bc-gop-kieu"><input type="radio" name="bc-kieu" value="trung_binh"> Trung bình</label>
    <div class="bc-gop-nut">
      <button type="button" class="nut nut-nho" id="bc-gop-ok">Gộp</button>
      <button type="button" class="nut nut-nho nut-phu" id="bc-gop-huy">Huỷ</button>
    </div>
    <div class="phu bc-gop-note">Gộp mềm, không xoá — đảo lại ở Thư viện → “Gộp đối tượng” → “Đang gộp”.</div>
  </div>
</div>
<style>
  td .bc-gop-chk { accent-color:#7c3aed; cursor:pointer; margin-right:5px; vertical-align:middle; }
  #bc-gop-bar { position:fixed; left:50%; transform:translateX(-50%); bottom:18px; z-index:55;
    display:flex; align-items:center; gap:12px; background:#1e1b4b; color:#fff;
    padding:10px 16px; border-radius:999px; box-shadow:0 8px 30px rgba(0,0,0,.3); }
  #bc-gop-dem { font-weight:600; font-size:13px; }
  #bc-gop-modal { position:fixed; inset:0; background:rgba(15,23,42,.35); z-index:60;
    display:flex; align-items:center; justify-content:center; }
  .bc-gop-pop { background:#fff; border:1px solid var(--vien,#e5eaf0); border-radius:12px;
    box-shadow:0 10px 40px rgba(0,0,0,.2); padding:18px 20px; max-width:480px; width:92%;
    max-height:80vh; overflow:auto; }
  .bc-gop-tit { font-weight:700; color:var(--xanh-900,#0f2e2b); margin-bottom:10px; font-size:15px; }
  .bc-gop-lb { font-size:12.5px; font-weight:600; color:#475569; margin-bottom:6px; }
  #bc-gop-ds label { display:flex; align-items:center; gap:8px; padding:5px 2px; font-size:13.5px; }
  #bc-gop-ds input { accent-color:#7c3aed; }
  .bc-gop-kieu { display:flex; align-items:center; gap:8px; padding:4px 2px; font-size:13.5px; }
  .bc-gop-nut { display:flex; gap:8px; margin-top:14px; }
  .bc-gop-note { margin-top:8px; }
  #bc-gop-bar .nut[disabled] { opacity:.45; pointer-events:none; }
  tr.bc-hidden { display:none !important; }
  #bc-an-undo { position:fixed; left:50%; transform:translateX(-50%); bottom:70px; z-index:54;
    display:flex; align-items:center; gap:12px; background:#334155; color:#fff;
    padding:9px 16px; border-radius:999px; box-shadow:0 8px 30px rgba(0,0,0,.3); }
  #bc-an-dem { font-weight:600; font-size:13px; }
</style>
<script>
(function () {
  var bar = document.getElementById('bc-gop-bar'); if (!bar) { return; }
  var modal = document.getElementById('bc-gop-modal');
  var token = document.getElementById('bc-gop-csrf').value;
  function daChon() { return Array.prototype.slice.call(document.querySelectorAll('.bc-gop-chk:checked')); }
  function soAn() { return document.querySelectorAll('tr.bc-hidden').length; }
  function capNhatUndo() {
    var u = document.getElementById('bc-an-undo'); var n = soAn();
    document.getElementById('bc-an-dem').textContent = 'Đã ẩn ' + n + ' dòng'; u.hidden = n < 1;
  }
  function capNhatBar() {
    var n = daChon().length;
    document.getElementById('bc-gop-dem').textContent = 'Đã chọn ' + n + ' dòng';
    document.getElementById('bc-gop-mo').disabled = n < 2;
    bar.hidden = n < 1;
  }
  function cellText(el) { return (el.textContent || '').replace(/\s+/g, ' ').trim(); }
  function csvCell(s) { return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; }
  function xuatCsv() {
    var ds = daChon(); if (!ds.length) { return; }
    var table = ds[0].closest('table'); if (!table) { return; }
    var head = Array.prototype.slice.call(table.querySelectorAll('thead th')).slice(1).map(cellText);
    var rows = ds.map(function (c) {
      return Array.prototype.slice.call(c.closest('tr').children).slice(1).map(cellText);
    });
    var csv = [head].concat(rows).map(function (r) { return r.map(csvCell).join(','); }).join('\r\n');
    var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = 'bao-cao-da-chon.csv';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }
  document.addEventListener('change', function (e) { if (e.target.classList.contains('bc-gop-chk')) { capNhatBar(); } });
  document.addEventListener('click', function (e) {
    if (e.target.id === 'bc-gop-clear') {
      document.querySelectorAll('.bc-gop-chk:checked').forEach(function (c) { c.checked = false; }); capNhatBar(); return;
    }
    if (e.target.id === 'bc-an') {
      var dsAn = daChon(); if (!dsAn.length) { return; }
      dsAn.forEach(function (c) { var tr = c.closest('tr'); if (tr) { tr.classList.add('bc-hidden'); } c.checked = false; });
      capNhatBar(); capNhatUndo(); return;
    }
    if (e.target.id === 'bc-an-hien') {
      document.querySelectorAll('tr.bc-hidden').forEach(function (tr) { tr.classList.remove('bc-hidden'); });
      capNhatUndo(); return;
    }
    if (e.target.id === 'bc-xuat') { xuatCsv(); return; }
    if (e.target.id === 'bc-gop-mo') {
      var ds = daChon(); if (ds.length < 2) { return; }
      var html = '';
      ds.forEach(function (c, i) {
        html += '<label><input type="radio" name="bc-giu" value="' + c.dataset.id + '"' + (i === 0 ? ' checked' : '') + '> '
              + (c.dataset.ten || '').replace(/</g, '&lt;') + '</label>';
      });
      document.getElementById('bc-gop-ds').innerHTML = html;
      modal.hidden = false; return;
    }
    if (e.target === modal || e.target.id === 'bc-gop-huy') { modal.hidden = true; return; }
    if (e.target.id === 'bc-gop-ok') {
      var ds = daChon();
      var giu = document.querySelector('input[name="bc-giu"]:checked');
      var kieu = document.querySelector('input[name="bc-kieu"]:checked');
      if (!giu) { alert('Chọn dòng giữ lại.'); return; }
      var ids = ds.map(function (c) { return c.dataset.id; }).join(',');
      var fd = new FormData();
      fd.append('viec', 'gop_bc'); fd.append('giu', giu.value); fd.append('ids', ids);
      fd.append('kieu', kieu ? kieu.value : 'cong_don'); fd.append('_csrf', token);
      fetch(location.pathname + location.search, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) { location.reload(); } else { alert(d.loi || 'Không gộp được.'); } })
        .catch(function () { location.reload(); });
    }
  });
})();
</script>
<?php endif; ?>

<?php if ($duocSapXep): ?>
<input type="hidden" id="bc-csrf" value="<?= e(csrf_token()) ?>">
<style>
  /* Cột STT: rộng cố định, để đủ chỗ cho nút → hover không làm bảng co giãn. */
  .bang-sapxep .bc-stt { position: relative; white-space: nowrap; }
  .bang-sapxep .bc-num { display: inline-block; min-width: 54px; text-align: center; }
  .bang-sapxep .bc-keo { cursor: grab; color: #94a3b8; user-select: none; }
  .bang-sapxep .bc-mui { border: 0; background: transparent; color: #64748b; cursor: pointer; font-size: 11px; padding: 0 1px; line-height: 1; }
  .bang-sapxep .bc-mui:hover { color: var(--xanh-500, #2563eb); }
  .bang-sapxep tr.dang-keo { opacity: .45; }
  /* Nút = lớp PHỦ tuyệt đối (không chiếm chỗ) → không đẩy cột; chỉ hiện khi hover. */
  .bang-sapxep .bc-dk {
    position: absolute; inset: 0; display: flex; align-items: center;
    justify-content: center; gap: 1px; visibility: hidden;
  }
  .bang-sapxep tr:hover .bc-dk { visibility: visible; }
  .bang-sapxep tr:hover .bc-num { visibility: hidden; }
  @media (hover: none) {
    .bang-sapxep .bc-dk { visibility: visible; }
    .bang-sapxep tr[data-id]:not(.dong-con) .bc-num { visibility: hidden; }
  }
</style>
<script>
(function () {
  var bang = document.getElementById('bang-bc');
  if (!bang || !bang.dataset.sapxep) { return; }
  var tbody = bang.querySelector('tbody');
  function laCon(tr) { return tr.classList.contains('dong-con'); }
  function khoiCua(tr) {
    var a = [tr];
    if (!laCon(tr)) { var n = tr.nextElementSibling; while (n && laCon(n)) { a.push(n); n = n.nextElementSibling; } }
    return a;
  }
  function luu() {
    var ids = Array.prototype.map.call(tbody.querySelectorAll('tr[data-id]'), function (r) { return r.dataset.id; });
    var fd = new FormData();
    fd.append('viec', 'sap_xep'); fd.append('ids', ids.join(','));
    fd.append('_csrf', document.getElementById('bc-csrf').value);
    fetch(location.pathname + location.search, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (!d.ok) { location.reload(); } })
      .catch(function () { location.reload(); });
  }
  /* Kéo-thả (chỉ kéo dòng lớn — cả khối cha + con di theo) */
  var keo = null, khoi = [];
  tbody.addEventListener('dragstart', function (e) {
    var h = e.target.closest('.bc-keo'); if (!h) { e.preventDefault(); return; }
    keo = h.closest('tr'); khoi = khoiCua(keo);
    e.dataTransfer.effectAllowed = 'move';
    setTimeout(function () { khoi.forEach(function (r) { r.classList.add('dang-keo'); }); }, 0);
  });
  tbody.addEventListener('dragend', function () { khoi.forEach(function (r) { r.classList.remove('dang-keo'); }); keo = null; khoi = []; });
  tbody.addEventListener('dragover', function (e) {
    if (!keo) { return; }
    var tr = e.target.closest('tr[data-id]');
    if (!tr || laCon(tr) || khoi.indexOf(tr) >= 0) { return; }
    e.preventDefault();
  });
  tbody.addEventListener('drop', function (e) {
    if (!keo) { return; }
    var tr = e.target.closest('tr[data-id]');
    if (!tr || laCon(tr) || khoi.indexOf(tr) >= 0) { return; }
    e.preventDefault();
    var tren = e.clientY < tr.getBoundingClientRect().top + tr.offsetHeight / 2;
    var moc = tren ? tr : (function () { var k = khoiCua(tr); return k[k.length - 1].nextElementSibling; })();
    khoi.forEach(function (r) { tbody.insertBefore(r, moc); });
    luu();
  });
  /* Nút ▲ ▼ (chạy cả trên điện thoại) */
  window.bcDoiCho = function (id, dir) {
    var tr = tbody.querySelector('tr[data-id="' + id + '"]'); if (!tr) { return; }
    var block = khoiCua(tr);
    var ke = dir > 0 ? block[block.length - 1].nextElementSibling : tr.previousElementSibling;
    while (ke && laCon(ke)) { ke = dir > 0 ? ke.nextElementSibling : ke.previousElementSibling; }
    if (!ke || !ke.dataset || !ke.dataset.id) {
      if (window.toast) { toast('Đã ở ' + (dir > 0 ? 'cuối' : 'đầu') + ' rồi.', 'canh-bao'); }
      return;
    }
    var blockKe = khoiCua(ke);
    var moc = dir > 0 ? blockKe[blockKe.length - 1].nextElementSibling : blockKe[0];
    block.forEach(function (r) { tbody.insertBefore(r, moc); });
    luu();
  };
})();
</script>
<?php endif; ?>
<?php dong_trang();

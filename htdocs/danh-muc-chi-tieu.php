<?php
/**
 * Thư viện chỉ tiêu — xem và sửa.
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

    // Thao tác nhanh (ngừng / riêng-chuẩn / xóa) gọi bằng AJAX → trả JSON, không tải lại.
    $laAjax = post('ajax') === '1';
    $tra = function (bool $ok, string $msg, array $extra = []) use ($laAjax) {
        if ($laAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => $ok, ($ok ? 'msg' : 'loi') => $msg] + $extra, JSON_UNESCAPED_UNICODE);
            exit;
        }
        nhan_tin($ok ? 'ok' : 'loi', $msg);
        chuyen_huong('/danh-muc-chi-tieu.php');
    };

    /* ---------- Sửa chỉ tiêu ---------- */
    if ($viec === 'sua' && $duocSua) {
        $id  = (int)post('id');
        $cu  = q1('SELECT * FROM chi_tieu WHERE id = ?', [$id]);
        $ten = post('ten');
        $donVi = post('don_vi');
        $viTriMoi = (int)post('thu_tu', '0');   // ô "Thứ tự chung" giờ là VỊ TRÍ (1,2,3…)
        $moTa  = trim(post('mo_ta')) !== '' ? trim(post('mo_ta')) : null;   // ghi chú quản lý
        $f = doc_bieu_mau($laDev);

        if (!$cu) {
            nhan_tin('loi', 'Không tìm thấy chỉ tiêu.');
        } elseif ($ten === '') {
            nhan_tin('loi', 'Nội dung không được để trống.');
        } elseif (!$f) {
            nhan_tin('loi', 'Thông số chỉ tiêu không hợp lệ.');
        } else {
            if (la_he_thong($cu['ma'])) {
                // Chỉ tiêu hệ thống: giữ nguyên cách tính, chỉ cho sửa chữ hiển thị + mô tả
                q('UPDATE chi_tieu SET ten=?, don_vi=?, mo_ta=? WHERE id=?',
                    [$ten, $donVi, $moTa, $id]);
                nhan_tin('ok', "Đã cập nhật \"$ten\". Đây là chỉ tiêu hệ thống nên cách tính giữ nguyên.");
            } else {
                q('UPDATE chi_tieu SET ten=?, don_vi=?, loai_gia_tri=?,
                     nguon=?, huong=?, phan_bo=?, mo_ta=? WHERE id=?',
                    [$ten, $donVi, $f['loai'], $f['nguon'], $f['huong'], $f['phan_bo'], $moTa, $id]);
                nhan_tin('ok', "Đã cập nhật chỉ tiêu \"$ten\".");
            }
            // Ô "Thứ tự chung" là VỊ TRÍ trong nhóm — chuyển tới đó nếu khác chỗ hiện tại.
            if ($viTriMoi > 0 && $viTriMoi !== vi_tri_thu_vien($id)) {
                dat_vi_tri_thu_vien($id, $viTriMoi);
            }
            ghi_nhat_ky('SUA_CHI_TIEU', $cu['ma'], $ten);
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
            $tra(false, 'Không tìm thấy chỉ tiêu.');
        } elseif (la_he_thong($cu['ma']) && (int)$cu['hoat_dong'] === 1) {
            $tra(false, "\"{$cu['ten']}\" là chỉ tiêu hệ thống, bộ máy tính toán đang "
                . 'tham chiếu tới nên không ngừng được.');
        } else {
            $moi = (int)$cu['hoat_dong'] === 1 ? 0 : 1;
            q('UPDATE chi_tieu SET hoat_dong = ? WHERE id = ?', [$moi, $id]);
            $ids = [$id];
            if (!$moi) {
                foreach (hau_due_ids($id) as $cid) {   // ngừng cả con/cháu bên trong
                    q('UPDATE chi_tieu SET hoat_dong = 0 WHERE id = ?', [$cid]);
                    $ids[] = (int)$cid;
                }
            }
            ghi_nhat_ky($moi ? 'DUNG_LAI_CHI_TIEU' : 'NGUNG_CHI_TIEU', $cu['ma']);
            $tra(true, ($moi ? 'Đã dùng lại "' : 'Đã ngừng sử dụng "') . $cu['ten']
                . '". Số liệu cũ vẫn được giữ.', ['moi' => $moi, 'ids' => $ids]);
        }
    }

    /* ---------- Dọn: ngừng dùng tất cả mã "mồ côi" (chưa gán khoa nào) ---------- */
    if ($viec === 'ngung_mo_coi' && co_quyen('chitieu.ngung')) {
        $moCoi = qAll('SELECT id, ma FROM chi_tieu c
                        WHERE hoat_dong = 1
                          AND NOT EXISTS (SELECT 1 FROM chi_tieu_ap_dung a
                                          WHERE a.id_chi_tieu = c.id)');
        $dem = 0;
        db()->beginTransaction();
        foreach ($moCoi as $c) {
            if (la_he_thong($c['ma'])) { continue; }   // không đụng chỉ tiêu hệ thống
            q('UPDATE chi_tieu SET hoat_dong = 0 WHERE id = ?', [(int)$c['id']]);
            $dem++;
        }
        db()->commit();
        ghi_nhat_ky('NGUNG_MO_COI', '', $dem . ' mã');
        nhan_tin($dem ? 'ok' : 'canh-bao', $dem
            ? "Đã ngừng dùng $dem mã chưa gán khoa. Chúng ẩn khỏi gợi ý; số liệu cũ (nếu có) "
              . 'vẫn giữ — bấm "Dùng lại" nếu cần.'
            : 'Không có mã mồ côi nào để dọn.');
        chuyen_huong('/danh-muc-chi-tieu.php?loc=mo_coi');
    }

    /* ---------- Đổi chuẩn (thư viện) ↔ riêng ---------- */
    if ($viec === 'doi_thu_vien' && $duocSua) {
        $id = (int)post('id');
        $cu = q1('SELECT * FROM chi_tieu WHERE id = ?', [$id]);
        if (!$cu) {
            $tra(false, 'Không tìm thấy chỉ tiêu.');
        } elseif (la_he_thong($cu['ma'])) {
            $tra(false, "\"{$cu['ma']}\" là chỉ tiêu hệ thống — luôn là chuẩn, không đổi được.");
        } else {
            $moi = (int)$cu['la_chuan'] === 1 ? 0 : 1;
            if ($moi === 1) {
                // Thành chuẩn → bỏ "gộp vào" (chuẩn thì đứng độc lập, lên tổng riêng)
                q('UPDATE chi_tieu SET la_chuan = 1, gop_vao = NULL WHERE id = ?', [$id]);
            } else {
                q('UPDATE chi_tieu SET la_chuan = 0 WHERE id = ?', [$id]);
            }
            ghi_nhat_ky('DOI_THU_VIEN_CHI_TIEU', $cu['ma'], $moi ? 'chuẩn' : 'riêng');
            $tra(true, "Đã đổi \"{$cu['ten']}\" thành "
                . ($moi ? 'chỉ tiêu CHUẨN (vào thư viện, lên tổng toàn viện).'
                        : 'chỉ tiêu RIÊNG (chỉ ở khoa, không lên tổng).'), ['moi' => $moi]);
        }
    }

    /* ---------- Xóa vĩnh viễn ---------- */
    if ($viec === 'xoa') {
        $id = (int)post('id');
        $cu = q1('SELECT * FROM chi_tieu WHERE id = ?', [$id]);
        if (!co_quyen('chitieu.xoa')) {
            ghi_nhat_ky('TU_CHOI_XOA_CHI_TIEU', $cu['ma'] ?? (string)$id);
            $tra(false, 'Chỉ người phát triển mới xóa vĩnh viễn được chỉ tiêu. '
                . 'Dùng nút "Ngừng dùng" thay thế.');
        } elseif (!$cu) {
            $tra(false, 'Không tìm thấy chỉ tiêu.');
        } elseif (la_he_thong($cu['ma'])) {
            $tra(false, "\"{$cu['ma']}\" là chỉ tiêu hệ thống, xóa sẽ hỏng công thức "
                . 'ngày điều trị trung bình và công suất giường bệnh.');
        } elseif (($n = chi_tieu_co_du_lieu($id)) > 0) {
            $tra(false, "Chỉ tiêu này đã có $n dòng số liệu/kế hoạch nên không xóa được. "
                . 'Dùng nút "Ngừng dùng".');
        } elseif (qVal('SELECT 1 FROM chi_tieu WHERE id_cha = ?', [$id])) {
            $tra(false, 'Còn nội dung nhỏ bên trong. Xóa các nội dung nhỏ trước.');
        } else {
            q('DELETE FROM chi_tieu WHERE id = ?', [$id]);
            danh_lai_thu_tu();   // dồn lại số sau khi xóa
            ghi_nhat_ky('XOA_CHI_TIEU', $cu['ma'], $cu['ten']);
            $tra(true, "Đã xóa chỉ tiêu \"{$cu['ten']}\".", ['xoa' => $id]);
        }
    }
}

/* ---------------- Hiển thị ---------------- */
$cayDayDu = cay_chi_tieu_day_du();
$soGoc = count(array_filter($cayDayDu, fn($c) => $c['cap'] === 0));
$soRieng = count(array_filter($cayDayDu,
    fn($c) => !la_he_thong($c['ma']) && (int)($c['la_chuan'] ?? 1) === 0));

// Khoa áp dụng mỗi chỉ tiêu — để lọc "chưa gán khoa" và hiện cột Khoa áp dụng.
$apDung = [];
foreach (qAll('SELECT * FROM chi_tieu_ap_dung') as $r) {
    $apDung[(int)$r['id_chi_tieu']][] = (int)$r['id_khoa'];
}
$maKhoa = [];
foreach ($dsKhoa as $k) {
    $maKhoa[(int)$k['id']] = $k['ma'];
}
// Mã "mồ côi" = chưa gán khoa nào. Dọn được = mồ côi + đang dùng + không phải hệ thống.
$soMoCoi    = count(array_filter($cayDayDu, fn($c) => !isset($apDung[(int)$c['id']])));
$soMoCoiDon = count(array_filter($cayDayDu,
    fn($c) => !isset($apDung[(int)$c['id']]) && (int)$c['hoat_dong'] === 1 && !la_he_thong($c['ma'])));

// Lọc theo thư viện: chuẩn / riêng / chưa gán khoa (hệ thống luôn tính là chuẩn)
$loc = in_array($_GET['loc'] ?? '', ['chuan', 'rieng', 'mo_coi'], true) ? $_GET['loc'] : '';
$cay = $cayDayDu;
if ($loc === 'chuan') {
    $cay = array_values(array_filter($cay,
        fn($c) => la_he_thong($c['ma']) || (int)($c['la_chuan'] ?? 1) === 1));
} elseif ($loc === 'rieng') {
    $cay = array_values(array_filter($cay,
        fn($c) => !la_he_thong($c['ma']) && (int)($c['la_chuan'] ?? 1) === 0));
} elseif ($loc === 'mo_coi') {
    $cay = array_values(array_filter($cay, fn($c) => !isset($apDung[(int)$c['id']])));
}

mo_trang('Thư viện chỉ tiêu');
?>
<div class="dau-muc">
  <div>
    <h1>Thư viện chỉ tiêu</h1>
    <p class="phu">
      <?= count($cayDayDu) ?> chỉ tiêu · <?= $soGoc ?> nội dung lớn · <?= $soRieng ?> riêng.
      Chỉ tiêu <em>bằng tổng các nội dung nhỏ</em> và <em>tính theo công thức</em>
      không nhập tay, hệ thống tự tính.
    </p>
  </div>
  <div class="hang-nut">
    <?php if ($duocThem): ?>
      <a class="nut" href="/chi-tieu-them.php">+ Thêm chỉ tiêu</a>
    <?php endif; ?>
    <?php if (co_quyen('chitieu.xoa')): $soNhomTrung = so_nhom_trung_chac(); ?>
      <a class="nut <?= $soNhomTrung ? 'nut-canh' : 'nut-phu' ?>" href="/gop-trung-lap.php"
         title="Tìm các chỉ tiêu cùng tên bị tách thành nhiều mã và gộp lại">
        Kiểm tra trùng lặp<?= $soNhomTrung ? ' <span class="menu-badge">' . $soNhomTrung . '</span>' : '' ?>
      </a>
    <?php endif; ?>
    <?php if (co_quyen('chitieu.nap_mac_dinh')): ?>
      <a class="nut nut-phu" href="/chi-tieu-nap-mac-dinh.php">Nạp danh mục mặc định</a>
    <?php endif; ?>
  </div>
</div>

<p class="hang-nut" style="margin:.25rem 0 1rem">
  <span class="phu">Lọc thư viện:</span>
  <a class="nut nut-nho <?= $loc === '' ? '' : 'nut-phu' ?>" href="?">Tất cả</a>
  <a class="nut nut-nho <?= $loc === 'chuan' ? '' : 'nut-phu' ?>" href="?loc=chuan">Chuẩn (thư viện)</a>
  <a class="nut nut-nho <?= $loc === 'rieng' ? '' : 'nut-phu' ?>" href="?loc=rieng">Riêng (<?= $soRieng ?>)</a>
  <a class="nut nut-nho <?= $loc === 'mo_coi' ? '' : 'nut-phu' ?>" href="?loc=mo_coi"
     title="Chỉ tiêu chưa khoa nào áp dụng — thường là mã dư/mồ côi">Chưa gán khoa (<?= $soMoCoi ?>)</a>
  <input type="search" class="o-tim" data-tim="#bang-dm" data-dem="#dm-dem"
         placeholder="Tìm mã / nội dung chỉ tiêu…" autocomplete="off">
  <span id="dm-dem" class="phu"></span>
</p>

<?php if ($loc === 'mo_coi' && co_quyen('chitieu.ngung') && $soMoCoiDon > 0): ?>
  <p class="hang-nut" style="margin:-.5rem 0 1rem">
    <form method="post" style="display:inline">
      <?= csrf_field() ?>
      <input type="hidden" name="viec" value="ngung_mo_coi">
      <button class="nut nut-nho nut-canh" type="submit"
              data-xac-nhan="Ngừng dùng <?= $soMoCoiDon ?> mã chưa gán khoa nào? Chúng sẽ ẩn khỏi gợi ý mã và danh mục dùng. Số liệu cũ (nếu có) vẫn được giữ — có thể bấm &quot;Dùng lại&quot; sau.">
        🧹 Dọn: ngừng dùng <?= $soMoCoiDon ?> mã mồ côi
      </button>
    </form>
    <span class="phu">Chỉ ẩn khỏi danh sách dùng, không xóa — Dùng lại được bất cứ lúc nào.</span>
  </p>
<?php endif; ?>

<?php if (!$cay && $loc): ?>
  <div class="tb tb-canh-bao">Không có chỉ tiêu nào trong nhóm “<?= $loc === 'rieng' ? 'Riêng' : ($loc === 'mo_coi' ? 'Chưa gán khoa' : 'Chuẩn') ?>”.
    <a href="?">Xem tất cả</a>.</div>
<?php elseif (!$cay): ?>
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
<table class="bang bang-mot-dong" id="bang-dm">
  <thead>
    <tr>
      <th>Mã</th><th>Nội dung</th><th>Đơn vị</th><th>Loại</th>
      <th>Nguồn</th><th>Đánh giá</th><th>Khoa áp dụng</th><th>Thư viện</th><th>Trạng thái</th>
      <?php if ($duocSua): ?><th>Thao tác</th><?php endif; ?>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($cay as $ct):
      $id = (int)$ct['id'];
      $ds = $apDung[$id] ?? [];
      $heThong = la_he_thong($ct['ma']);
      $soDL = chi_tieu_co_du_lieu($id); ?>
    <tr data-id="<?= $id ?>" class="<?= $ct['cap'] ? 'dong-con' : '' ?> <?= (int)$ct['hoat_dong'] ? '' : 'dong-mo' ?>">
      <td><code><?= e($ct['ma']) ?></code></td>
      <td<?= $ct['cap'] ? ' style="padding-left:' . (10 + (int)$ct['cap'] * 20) . 'px"' : '' ?>>
        <?= $ct['cap'] ? '<span class="thut">↳</span> ' : '' ?><?= e($ct['ten']) ?>
        <?php if ($heThong): ?>
          <span class="the the-nho" title="Bộ máy tính toán tham chiếu tới mã này">hệ thống</span>
        <?php endif; ?>
        <?php if (!empty($ct['mo_ta'])): ?>
          <div class="mo-ta-ct phu"><?= e($ct['mo_ta']) ?></div>
        <?php endif; ?>
        <?php if ($duocThem): ?>
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
            // Mỗi mã là LINK: bấm → tới trang Giao chỉ tiêu của khoa đó, cuộn
            // tới đúng dòng chỉ tiêu này (mỏ neo #ct-<id>).
            $idCT = (int)$ct['id'];
            $dsMa = implode(', ', array_map(fn($i) => $maKhoa[$i] ?? '?', $ds));
            $link = array_map(function ($i) use ($maKhoa, $idCT) {
                return '<a class="khoa-lien-ket" href="/giao-chi-tieu.php?khoa=' . (int)$i
                     . '#ct-' . $idCT . '" title="Tới nơi áp dụng ở khoa này">'
                     . e($maKhoa[$i] ?? '?') . '</a>';
            }, $ds); ?>
          <span class="ds-gon" title="Bấm mã khoa để tới nơi áp dụng — <?= e($dsMa) ?>"><?= implode(', ', $link) ?></span>
        <?php else: ?>
          <span class="canh-bao-nho">chưa gán khoa</span>
        <?php endif; ?>
      </td>
      <td class="nho o-thuvien">
        <?php if ($heThong || (int)($ct['la_chuan'] ?? 1) === 1): ?>
          <span class="the the-nho the-chuan">Chuẩn</span>
        <?php else: ?>
          <span class="the the-nho the-rieng">Riêng</span>
          <?php if (!empty($ct['gop_vao'])): $gv = chi_tieu_theo_ma($ct['gop_vao']); ?>
            <div class="phu" style="font-size:11px;margin-top:2px">↗ <?= e($gv ? $gv['ten'] : $ct['gop_vao']) ?></div>
          <?php endif; ?>
        <?php endif; ?>
      </td>
      <td class="o-trangthai"><?= (int)$ct['hoat_dong']
              ? '<span class="trang-thai bat">Đang dùng</span>'
              : '<span class="trang-thai tat">Ngừng</span>' ?></td>
      <?php if ($duocSua): ?>
      <td class="thao-tac"><div class="nhom-tt">
        <button type="button" class="nut nut-nho nut-phu" data-mo="sua-<?= $id ?>">Sửa</button>
        <button type="button" class="nut nut-nho nut-phu" data-mo="khoa-<?= $id ?>">Khoa</button>

        <?php if (!$heThong): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="viec" value="doi_thu_vien">
          <input type="hidden" name="id" value="<?= $id ?>">
          <button class="nut nut-nho nut-phu js-tv" type="submit"
                  title="Chuyển giữa chuẩn (thư viện, lên tổng) và riêng của khoa">
            <?= (int)($ct['la_chuan'] ?? 1) === 1 ? '→ Riêng' : '→ Chuẩn' ?>
          </button>
        </form>
        <?php endif; ?>

        <?php if (co_quyen('chitieu.ngung') && !($heThong && (int)$ct['hoat_dong'])): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="viec" value="doi_trang_thai">
          <input type="hidden" name="id" value="<?= $id ?>">
          <button class="nut nut-nho js-tt <?= (int)$ct['hoat_dong'] ? 'nut-canh' : 'nut-phu' ?>"
                  type="submit"><?= (int)$ct['hoat_dong'] ? 'Ngừng dùng' : 'Dùng lại' ?></button>
        </form>
        <?php endif; ?>

        <?php if (co_quyen('chitieu.xoa') && !$heThong && $soDL === 0): ?>
        <form method="post"
              data-xac-nhan="Xóa vĩnh viễn chỉ tiêu <?= e($ct['ma']) ?>?" data-xac-nhan-loai="nguy">
          <?= csrf_field() ?>
          <input type="hidden" name="viec" value="xoa">
          <input type="hidden" name="id" value="<?= $id ?>">
          <button class="nut nut-nho nut-nguy" type="submit">Xóa</button>
        </form>
        <?php endif; ?>
        </div><!-- /.nhom-tt -->

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
                <label class="o-rong-2">Mô tả / Ghi chú <small class="nhan-phu">(chữ nhạt hiện dưới tên — để người quản lý hiểu chỉ tiêu này dùng làm gì)</small>
                  <input type="text" name="mo_ta" value="<?= e($ct['mo_ta'] ?? '') ?>"
                         placeholder="VD: BHYT của bệnh nhân điều trị ngoại trú"></label>
                <label>Đơn vị <input type="text" name="don_vi" value="<?= e($ct['don_vi']) ?>"></label>
                <label>Thứ tự chung <small class="nhan-phu">(vị trí 1, 2, 3… trong nhóm — chung cho mọi khoa)</small>
                  <input type="text" inputmode="numeric" name="thu_tu" value="<?= vi_tri_thu_vien((int)$ct['id']) ?>"></label>
                <?php if (!$heThong): ?>
                  <label>Loại giá trị
                    <select name="loai_gia_tri">
                      <?php foreach (['DEM','TRUNG_BINH','TY_LE','HANG_SO','GHI_CHU'] as $v): ?>
                        <option value="<?= $v ?>" <?= $ct['loai_gia_tri'] === $v ? 'selected' : '' ?>>
                          <?= e(NHAN[$v]) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>Nguồn số liệu
                    <select name="nguon">
                      <?php foreach (['NHAP_TAY','TONG_CON','TONG_CON_TAY'] as $v): ?>
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

<script>
/* Thao tác nhanh (Ngừng/Dùng lại · →Riêng/→Chuẩn · Xóa) chạy AJAX — KHÔNG tải lại trang. */
(function () {
  function dong(id) { return document.querySelector('tr[data-id="' + id + '"]'); }

  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.tagName !== 'FORM') { return; }
    var vi = f.querySelector('input[name="viec"]');
    if (!vi) { return; }
    var viec = vi.value;
    if (['doi_trang_thai', 'doi_thu_vien', 'xoa'].indexOf(viec) === -1) { return; }
    e.preventDefault();
    var fd = new FormData(f); fd.append('ajax', '1');
    var id = fd.get('id');
    fetch(location.pathname, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { toast(d.loi || 'Không thực hiện được.', 'canh-bao'); return; }
        toast(d.msg || 'Xong.', 'ok');
        if (viec === 'xoa') {
          var tr = dong(d.xoa); if (tr) { tr.remove(); }
        } else if (viec === 'doi_trang_thai') {
          capNhatTrangThai(d.ids || [id], d.moi);
        } else if (viec === 'doi_thu_vien') {
          capNhatThuVien(id, d.moi);
        }
      })
      .catch(function () { toast('Lỗi kết nối, thử lại.', 'canh-bao'); });
  });

  function capNhatTrangThai(ids, moi) {
    ids.forEach(function (x) {
      var tr = dong(x); if (!tr) { return; }
      tr.classList.toggle('dong-mo', !moi);
      var oTT = tr.querySelector('.o-trangthai');
      if (oTT) {
        oTT.innerHTML = moi
          ? '<span class="trang-thai bat">Đang dùng</span>'
          : '<span class="trang-thai tat">Ngừng</span>';
      }
      var nut = tr.querySelector('.js-tt');
      if (nut) {
        nut.textContent = moi ? 'Ngừng dùng' : 'Dùng lại';
        nut.classList.toggle('nut-canh', !!moi);
        nut.classList.toggle('nut-phu', !moi);
      }
    });
  }

  function capNhatThuVien(id, moi) {
    var tr = dong(id); if (!tr) { return; }
    var oTV = tr.querySelector('.o-thuvien');
    if (oTV) {
      oTV.innerHTML = moi
        ? '<span class="the the-nho the-chuan">Chuẩn</span>'
        : '<span class="the the-nho the-rieng">Riêng</span>';
    }
    var nut = tr.querySelector('.js-tv');
    if (nut) { nut.textContent = moi ? '→ Riêng' : '→ Chuẩn'; }
  }
})();
</script>
<?php endif; ?>
<?php dong_trang();

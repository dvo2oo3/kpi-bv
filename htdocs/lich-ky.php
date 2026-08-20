<?php
/**
 * Lịch mở kỳ nhập liệu.
 *
 * admin quyết định từ ngày nào tới ngày nào các khoa được nhập.
 * Đặt cho cả 12 tháng một lần, hoặc riêng từng tháng, hoặc riêng từng khoa.
 * Không đặt gì thì chạy theo quy tắc mặc định.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';

$toi = bat_buoc_quyen('ky.dat_lich');

$nam = (int)($_GET['nam'] ?? $_POST['nam'] ?? NAM_MAC_DINH);
$dsKhoa = qAll('SELECT * FROM khoa WHERE hoat_dong = 1 ORDER BY thu_tu, ten');

/** Ngày dạng yyyy-mm-dd, hoặc null nếu không hợp lệ. */
function doc_ngay(string $s): ?string
{
    $s = trim($s);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return null;
    }
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $s : null;
}

if (la_post()) {
    kiem_tra_csrf();
    $viec = post('viec');

    /* ---------- Đặt lịch cho cả năm bằng một quy tắc ---------- */
    if ($viec === 'dat_ca_nam') {
        $ngayMo   = max(1, min(31, (int)post('ngay_mo', '1')));
        $ngayDong = max(1, min(31, (int)post('ngay_dong', '5')));
        // Cửa nhập nằm TRONG chính tháng đó, hay dời sang tháng liền sau.
        // Cả ngày mở lẫn ngày đóng đều thuộc tháng này — không còn chuyện
        // "mở chính tháng đó nhưng đóng sang tháng sau".
        $sangThangSau = post('thang_mo') === 'sau';
        $idKhoa   = (int)post('id_khoa', '0');

        db()->beginTransaction();
        for ($t = 1; $t <= 12; $t++) {
            $thangCua = $t + ($sangThangSau ? 1 : 0);  $namCua = $nam;
            if ($thangCua > 12) { $thangCua -= 12; $namCua++; }

            // Ngày vượt số ngày của tháng thì kẹp về ngày cuối tháng
            $moD   = min($ngayMo,   so_ngay_thang($namCua, $thangCua));
            $dongD = min($ngayDong, so_ngay_thang($namCua, $thangCua));
            $mo   = sprintf('%04d-%02d-%02d', $namCua, $thangCua, $moD);
            $dong = sprintf('%04d-%02d-%02d', $namCua, $thangCua, $dongD);
            if (strtotime($dong) < strtotime($mo)) {
                continue;   // ngày đóng trước ngày mở thì bỏ tháng đó
            }
            q('DELETE FROM lich_ky WHERE nam=? AND thang=? AND id_khoa=?', [$nam, $t, $idKhoa]);
            q('INSERT INTO lich_ky (nam, thang, id_khoa, mo_tu, dong_sau, nguoi_dat)
               VALUES (?,?,?,?,?,?)', [$nam, $t, $idKhoa, $mo, $dong, $toi['id']]);
        }
        db()->commit();

        $tenKhoa = $idKhoa === 0 ? 'mọi khoa'
            : (qVal('SELECT ten FROM khoa WHERE id=?', [$idKhoa]) ?? '?');
        ghi_nhat_ky('DAT_LICH_KY', "nam $nam", "Cả năm, $tenKhoa");
        nhan_tin('ok', "Đã đặt lịch cả 12 tháng năm $nam cho $tenKhoa.");
        chuyen_huong("/lich-ky.php?nam=$nam");
    }

    /* ---------- Mở CẢ 12 tháng để nhập bổ sung (nhập số liệu lịch sử) ----------
       Khác "Đặt cả năm" ở chỗ: cửa nhập kéo từ đầu mỗi tháng tới MỘT NGÀY CHUNG
       trong tương lai, nên các tháng đã qua cũng mở lại để nhập được. */
    if ($viec === 'mo_ca_nam_nhap') {
        $den    = doc_ngay(post('mo_den'));
        $idKhoa = (int)post('id_khoa', '0');
        if (!$den || strtotime($den) < strtotime(date('Y-m-d'))) {
            nhan_tin('loi', 'Chọn "mở đến ngày" là một ngày từ hôm nay trở đi.');
            chuyen_huong("/lich-ky.php?nam=$nam");
        }
        db()->beginTransaction();
        for ($t = 1; $t <= 12; $t++) {
            $mo = sprintf('%04d-%02d-01', $nam, $t);
            q('DELETE FROM lich_ky WHERE nam=? AND thang=? AND id_khoa=?', [$nam, $t, $idKhoa]);
            q('INSERT INTO lich_ky (nam, thang, id_khoa, mo_tu, dong_sau, ghi_chu, nguoi_dat)
               VALUES (?,?,?,?,?,?,?)', [$nam, $t, $idKhoa, $mo, $den, 'Mở nhập bổ sung', $toi['id']]);
        }
        db()->commit();
        $tenKhoa = $idKhoa === 0 ? 'mọi khoa'
            : (qVal('SELECT ten FROM khoa WHERE id=?', [$idKhoa]) ?? '?');
        ghi_nhat_ky('MO_CA_NAM_NHAP', "nam $nam", "12 tháng, đến $den, $tenKhoa");
        nhan_tin('ok', "Đã mở cả 12 tháng năm $nam cho $tenKhoa để nhập bổ sung — "
            . 'mở đến hết ' . date('d/m/Y', strtotime($den)) . '. '
            . '(Kỳ đã DUYỆT/KHÓA thì cần “Mở lại” riêng.)');
        chuyen_huong("/lich-ky.php?nam=$nam");
    }

    /* ---------- Đặt lịch riêng một tháng ---------- */
    if ($viec === 'dat_thang') {
        $thang  = max(1, min(12, (int)post('thang')));
        $idKhoa = (int)post('id_khoa', '0');
        $mo     = doc_ngay(post('mo_tu'));
        $dong   = doc_ngay(post('dong_sau'));
        $ghiChu = post('ghi_chu');

        if ($mo === null || $dong === null) {
            nhan_tin('loi', 'Ngày không hợp lệ. Định dạng yyyy-mm-dd.');
        } elseif (strtotime($dong) < strtotime($mo)) {
            nhan_tin('loi', 'Ngày đóng phải sau ngày mở.');
        } else {
            q('DELETE FROM lich_ky WHERE nam=? AND thang=? AND id_khoa=?', [$nam, $thang, $idKhoa]);
            q('INSERT INTO lich_ky (nam, thang, id_khoa, mo_tu, dong_sau, ghi_chu, nguoi_dat)
               VALUES (?,?,?,?,?,?,?)',
                [$nam, $thang, $idKhoa, $mo, $dong, $ghiChu ?: null, $toi['id']]);
            $tenKhoa = $idKhoa === 0 ? 'mọi khoa'
                : (qVal('SELECT ma FROM khoa WHERE id=?', [$idKhoa]) ?? '?');
            ghi_nhat_ky('DAT_LICH_KY', "T$thang/$nam", "$tenKhoa: $mo → $dong");
            nhan_tin('ok', "Đã đặt lịch tháng $thang/$nam cho $tenKhoa: "
                . date('d/m/Y', strtotime($mo)) . ' → ' . date('d/m/Y', strtotime($dong)) . '.');
        }
        chuyen_huong("/lich-ky.php?nam=$nam");
    }

    /* ---------- Bỏ lịch, quay về quy tắc mặc định ---------- */
    if ($viec === 'bo_lich') {
        $thang  = max(1, min(12, (int)post('thang')));
        $idKhoa = (int)post('id_khoa', '0');
        q('DELETE FROM lich_ky WHERE nam=? AND thang=? AND id_khoa=?', [$nam, $thang, $idKhoa]);
        ghi_nhat_ky('BO_LICH_KY', "T$thang/$nam", "id_khoa=$idKhoa");
        nhan_tin('ok', "Đã bỏ lịch tháng $thang/$nam. Kỳ này quay về quy tắc mặc định.");
        chuyen_huong("/lich-ky.php?nam=$nam");
    }

    /* ---------- Khóa lại ngay: đóng cửa nhập tức thì cho mọi khoa ---------- */
    if ($viec === 'khoa_ngay') {
        $thang  = max(1, min(12, (int)post('thang')));
        $cs     = cua_so_ky($nam, $thang, 0);
        $moTu   = date('Y-m-d', $cs['mo_tu']);
        $homQua = date('Y-m-d', strtotime('-1 day'));

        db()->beginTransaction();
        // Xóa mọi lịch của tháng (cả chung lẫn riêng khoa) rồi đặt lịch chung
        // đóng từ hôm qua; gỡ mọi gia hạn — để không khoa nào còn cửa nhập.
        q('DELETE FROM lich_ky WHERE nam=? AND thang=?', [$nam, $thang]);
        q('INSERT INTO lich_ky (nam, thang, id_khoa, mo_tu, dong_sau, ghi_chu, nguoi_dat)
           VALUES (?,?,0,?,?,?,?)',
            [$nam, $thang, $moTu, $homQua, 'Khóa lại ngay', $toi['id']]);
        q('UPDATE ky SET mo_den = NULL WHERE nam=? AND thang=?', [$nam, $thang]);
        db()->commit();

        ghi_nhat_ky('KHOA_KY_NGAY', "T$thang/$nam", 'Đóng cửa nhập tức thì, mọi khoa');
        nhan_tin('ok', "Đã khóa tháng $thang/$nam ngay — mọi khoa không nhập/sửa được nữa. "
            . 'Khoa đã nộp thì chờ admin duyệt.');
        chuyen_huong("/lich-ky.php?nam=$nam");
    }

    /* ---------- Gia hạn cho MỘT hoặc NHIỀU khoa cùng lúc ---------- */
    if ($viec === 'gia_han') {
        $thang = max(1, min(12, (int)post('thang')));
        $den   = doc_ngay(post('mo_den'));
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array)($_POST['id_khoa'] ?? [])), fn($x) => $x > 0)));

        if ($den === null) {
            nhan_tin('loi', 'Ngày gia hạn không hợp lệ.');
        } elseif (!$ids) {
            nhan_tin('loi', 'Chưa chọn khoa nào.');
        } else {
            $ok = 0; $boQua = [];
            db()->beginTransaction();
            foreach ($ids as $idKhoa) {
                $khoa = q1('SELECT ma FROM khoa WHERE id=?', [$idKhoa]);
                if (!$khoa) { continue; }
                // Bỏ qua kỳ ĐÃ CHỐT (đã duyệt/đã khóa) — không mở lại được.
                $kyCu = ban_ghi_ky($nam, $thang, $idKhoa);
                if ($kyCu && in_array($kyCu['trang_thai'], ['DA_DUYET', 'DA_KHOA'], true)) {
                    $boQua[] = $khoa['ma']; continue;
                }
                if (!qVal('SELECT 1 FROM ky WHERE nam=? AND thang=? AND id_khoa=?',
                        [$nam, $thang, $idKhoa])) {
                    q('INSERT INTO ky (nam, thang, id_khoa, trang_thai, mo_den) VALUES (?,?,?,?,?)',
                        [$nam, $thang, $idKhoa, 'MO', $den]);
                } else {
                    q('UPDATE ky SET mo_den=? WHERE nam=? AND thang=? AND id_khoa=?',
                        [$den, $nam, $thang, $idKhoa]);
                }
                $ok++;
            }
            db()->commit();
            ghi_nhat_ky('GIA_HAN_KY', 'nhiều', "T$thang/$nam: $ok khoa đến $den");
            $msg = "Đã gia hạn $ok khoa tháng $thang/$nam đến hết "
                 . date('d/m/Y', strtotime($den)) . '.';
            if ($boQua) { $msg .= ' Bỏ qua (đã chốt): ' . implode(', ', $boQua) . '.'; }
            nhan_tin($ok > 0 ? 'ok' : 'loi', $ok > 0 ? $msg : 'Không gia hạn được khoa nào (đã chốt hết).');
        }
        chuyen_huong("/lich-ky.php?nam=$nam");
    }

    /* ---------- Thu hồi gia hạn MỘT hoặc NHIỀU khoa cùng lúc ---------- */
    if ($viec === 'thu_hoi') {
        // Mỗi mục dạng "thang:id_khoa". Nút 1 dòng gửi 1 mục; nút hàng loạt gửi nhiều.
        $muc = (array)($_POST['muc'] ?? []);
        if (!$muc && post('id_khoa') !== '') {
            $muc = [(int)post('thang') . ':' . (int)post('id_khoa')];
        }
        $ok = 0; $boQua = [];
        db()->beginTransaction();
        foreach ($muc as $m) {
            [$thg, $idK] = array_pad(explode(':', (string)$m), 2, 0);
            $thg = max(1, min(12, (int)$thg)); $idK = (int)$idK;
            if ($idK <= 0) { continue; }
            $khoa = q1('SELECT ma FROM khoa WHERE id=?', [$idK]);
            if (!$khoa) { continue; }
            $kyCu = ban_ghi_ky($nam, $thg, $idK);
            if ($kyCu && in_array($kyCu['trang_thai'], ['DA_DUYET', 'DA_KHOA'], true)) {
                $boQua[] = $khoa['ma']; continue;
            }
            q('UPDATE ky SET mo_den = NULL WHERE nam=? AND thang=? AND id_khoa=?',
                [$nam, $thg, $idK]);
            $ok++;
        }
        db()->commit();
        ghi_nhat_ky('THU_HOI_GIA_HAN', 'nhiều', "$ok khoa");
        $msg = "Đã thu hồi $ok khoa — đóng cửa nhập ngay.";
        if ($boQua) { $msg .= ' Bỏ qua (đã chốt): ' . implode(', ', $boQua) . '.'; }
        nhan_tin($ok > 0 ? 'ok' : 'loi', $ok > 0 ? $msg : 'Chưa chọn khoa nào để thu hồi.');
        chuyen_huong("/lich-ky.php?nam=$nam");
    }
}

/* ---------------- Hiển thị ---------------- */
$lichChung = [];
foreach (qAll('SELECT * FROM lich_ky WHERE nam = ? AND id_khoa = 0', [$nam]) as $r) {
    $lichChung[(int)$r['thang']] = $r;
}
$lichKhoa = [];
foreach (qAll('SELECT * FROM lich_ky WHERE nam = ? AND id_khoa > 0', [$nam]) as $r) {
    $lichKhoa[(int)$r['thang']][(int)$r['id_khoa']] = $r;
}
// Chỉ liệt kê gia hạn CÒN Ý NGHĨA — bỏ kỳ đã chốt (đã duyệt/đã khóa) vì đã khóa
// rồi thì gia hạn vô nghĩa, hiện "còn hiệu lực" gây hiểu nhầm.
$giaHan = qAll("SELECT k.*, kh.ma, kh.ten FROM ky k JOIN khoa kh ON kh.id = k.id_khoa
                 WHERE k.nam = ? AND k.mo_den IS NOT NULL
                   AND k.trang_thai NOT IN ('DA_DUYET', 'DA_KHOA')
                 ORDER BY k.thang, kh.thu_tu", [$nam]);
$maKhoa = [];
foreach ($dsKhoa as $k) { $maKhoa[(int)$k['id']] = $k['ma']; }

// Mặc định form "Đặt nhanh" = lịch chung đã lưu (nếu có) → nhớ số đã đặt,
// tải lại không bị nhảy về 1/5.
$dfNgayMo = 1; $dfNgayDong = 5; $dfThangMo = 'chinh';
foreach ($lichChung as $tKey => $lc) {
    $dfNgayMo   = (int)date('j', strtotime($lc['mo_tu']));
    $dfNgayDong = (int)date('j', strtotime($lc['dong_sau']));
    $dfThangMo  = ((int)date('n', strtotime($lc['mo_tu'])) !== (int)$tKey) ? 'sau' : 'chinh';
    break;   // lấy tháng đầu có lịch làm mẫu
}

mo_trang('Lịch mở kỳ');
?>
<div class="dau-muc">
  <div>
    <h1>Lịch mở kỳ nhập liệu — năm <?= $nam ?></h1>
    <p class="phu">
      Quyết định từ ngày nào tới ngày nào các khoa được nhập số liệu.
      Trong khoảng đó khoa nhập và sửa thoải mái, kể cả sau khi đã bấm Nộp.
    </p>
  </div>
  <form method="get" class="hang-nut">
    <label class="an-nhan">Năm
      <select name="nam" onchange="this.form.submit()">
        <?php for ($n = NAM_MAC_DINH + 1; $n >= NAM_MAC_DINH - 2; $n--): ?>
          <option value="<?= $n ?>" <?= $n === $nam ? 'selected' : '' ?>><?= $n ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <?php mo_tro_giup('tg-lich', 'Lịch mở kỳ hoạt động thế nào'); ?>
      <p>Khi một khoa mở trang Nhập số liệu, hệ thống xét theo thứ tự:</p>
      <ol class="huong-dan">
        <li><strong>Gia hạn riêng</strong> cho chính khoa đó — nếu còn hạn thì mở, bỏ qua các bước sau.</li>
        <li><strong>Lịch riêng của khoa</strong> ở tháng đó.</li>
        <li><strong>Lịch chung</strong> đặt cho mọi khoa.</li>
        <li><strong>Quy tắc mặc định</strong> khi chưa đặt lịch nào: mở từ ngày 1 của tháng
            tới hết ngày 5 tháng sau.</li>
      </ol>
      <p>Đã duyệt hoặc đã khóa là trạng thái chốt — lịch không mở lại được,
         phải dùng bút toán điều chỉnh.</p>
    <?php dong_tro_giup(); ?>
  </form>
</div>

<div class="tab-phu" role="tablist">
  <button type="button" class="tab-phu-muc dang" data-tab="lich">📅 Lịch mở kỳ</button>
  <button type="button" class="tab-phu-muc" data-tab="giahan">⏳ Gia hạn &amp; thu hồi</button>
</div>

<div class="tab-noi" data-tab="lich">
<section class="the-hop" style="margin-bottom:1rem">
  <h2>Mở tất cả tháng để nhập bổ sung <span class="the the-nho the-chuan">nhập số liệu lịch sử</span></h2>
  <p class="phu" style="margin-top:.2rem">
    Mở cửa nhập của <strong>cả 12 tháng</strong> năm <?= $nam ?> — <strong>kể cả tháng đã qua</strong> —
    tới một ngày trong tương lai, để nhập/sửa số liệu cũ. (Kỳ đã <em>DUYỆT/KHÓA</em> thì phải bấm “Mở lại” riêng ở tab Duyệt kỳ.)
  </p>
  <form method="post" class="bieu-mau-ngang">
    <?= csrf_field() ?>
    <input type="hidden" name="viec" value="mo_ca_nam_nhap">
    <input type="hidden" name="nam" value="<?= $nam ?>">
    <label>Áp dụng cho
      <select name="id_khoa">
        <option value="0">Mọi khoa</option>
        <?php foreach ($dsKhoa as $k): ?><option value="<?= (int)$k['id'] ?>">Riêng <?= e($k['ten']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Mở đến hết ngày
      <input type="date" name="mo_den" value="<?= $nam ?>-12-31" required>
    </label>
    <button class="nut nut-chinh" type="submit">Mở 12 tháng để nhập</button>
  </form>
</section>

<h2>Đặt nhanh cho cả 12 tháng</h2>
<form method="post" class="bieu-mau-ngang">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="dat_ca_nam">
  <input type="hidden" name="nam" value="<?= $nam ?>">
  <label>Áp dụng cho
    <select name="id_khoa">
      <option value="0">Mọi khoa</option>
      <?php foreach ($dsKhoa as $k): ?>
        <option value="<?= (int)$k['id'] ?>">Riêng <?= e($k['ten']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Cửa nhập nằm ở
    <select name="thang_mo">
      <option value="chinh" <?= $dfThangMo === 'chinh' ? 'selected' : '' ?>>Trong chính tháng đó</option>
      <option value="sau" <?= $dfThangMo === 'sau' ? 'selected' : '' ?>>Tháng kế tiếp</option>
    </select>
  </label>
  <label>Mở từ ngày
    <input type="text" inputmode="numeric" name="ngay_mo" value="<?= $dfNgayMo ?>">
  </label>
  <label>Đóng vào ngày
    <input type="text" inputmode="numeric" name="ngay_dong" value="<?= $dfNgayDong ?>">
    <small>Cùng tháng với ngày mở. Điền 31 = cuối tháng.</small>
  </label>
  <button class="nut nut-chinh" type="submit">Đặt cho cả năm</button>
  <p class="phu">
    Hai kiểu tách bạch, cả ngày mở lẫn ngày đóng đều nằm trong tháng đã chọn:
    <br><strong>Trong chính tháng đó</strong> — tháng 6 mở 01/06 → 05/06, khoa nhập
    ngay trong tháng (điền đóng ngày 31 để mở suốt cả tháng).
    <br><strong>Tháng kế tiếp</strong> — tháng 6 mở 01/07 → 05/07, khoa chốt số liệu
    tháng 6 vào đầu tháng 7.
  </p>
</form>

<h2>Lịch từng tháng</h2>
<div class="cuon-ngang">
<table class="bang">
  <thead>
    <tr><th>Tháng</th><th>Mở từ</th><th>Đóng sau</th><th>Nguồn</th>
        <th>Trạng thái hôm nay</th><th>Lịch riêng khoa</th><th>Đặt lại</th></tr>
  </thead>
  <tbody>
  <?php for ($t = 1; $t <= 12; $t++):
      $cs = cua_so_ky($nam, $t, 0);
      $l  = $lichChung[$t] ?? null;
      // Trang Lịch mở kỳ hiện TRẠNG THÁI CỬA NHẬP của tháng (chưa đến / đang mở /
      // đã đóng) — KHÔNG phải trạng thái duyệt của riêng một khoa. Trạng thái
      // duyệt là việc của trang Duyệt kỳ, theo từng khoa.
      $tt = time() < $cs['mo_tu'] ? 'CHUA_DEN'
          : (time() <= $cs['dong_sau'] ? 'MO' : 'DA_KHOA'); ?>
    <tr>
      <td><strong>Tháng <?= $t ?></strong></td>
      <td class="nho"><?= date('d/m/Y', $cs['mo_tu']) ?></td>
      <td class="nho"><?= date('d/m/Y', $cs['dong_sau']) ?></td>
      <td class="nho">
        <?= $cs['nguon'] === 'mac_dinh'
            ? '<span class="phu">mặc định</span>'
            : '<span class="the the-nho">đã đặt</span>' ?>
      </td>
      <td><span class="trang-thai-ky tt-<?= e(chu_thuong($tt)) ?>"><?= e(ten_trang_thai($tt)) ?></span></td>
      <td class="nho">
        <?php $rieng = $lichKhoa[$t] ?? [];
        if ($rieng): ?>
          <?php foreach ($rieng as $idK => $r): ?>
            <div><strong><?= e($maKhoa[$idK] ?? '?') ?></strong>
              <?= date('d/m', strtotime($r['mo_tu'])) ?>–<?= date('d/m', strtotime($r['dong_sau'])) ?></div>
          <?php endforeach; ?>
        <?php else: ?><span class="phu">—</span><?php endif; ?>
      </td>
      <td class="thao-tac">
        <details>
          <summary>Sửa</summary>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="viec" value="dat_thang">
            <input type="hidden" name="nam" value="<?= $nam ?>">
            <input type="hidden" name="thang" value="<?= $t ?>">
            <label>Áp dụng cho
              <select name="id_khoa">
                <option value="0">Mọi khoa</option>
                <?php foreach ($dsKhoa as $k): ?>
                  <option value="<?= (int)$k['id'] ?>">Riêng <?= e($k['ma']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Mở từ
              <input type="date" name="mo_tu"
                     value="<?= $l ? e($l['mo_tu']) : date('Y-m-d', $cs['mo_tu']) ?>">
            </label>
            <label>Đóng sau
              <input type="date" name="dong_sau"
                     value="<?= $l ? e($l['dong_sau']) : date('Y-m-d', $cs['dong_sau']) ?>">
            </label>
            <label>Ghi chú
              <input type="text" name="ghi_chu" value="<?= $l ? e($l['ghi_chu'] ?? '') : '' ?>">
            </label>
            <button class="nut nut-nho" type="submit">Lưu</button>
          </form>
        </details>
        <?php if ($l): ?>
        <form method="post" data-xac-nhan="Bỏ lịch tháng <?= $t ?>, quay về quy tắc mặc định?">
          <?= csrf_field() ?>
          <input type="hidden" name="viec" value="bo_lich">
          <input type="hidden" name="nam" value="<?= $nam ?>">
          <input type="hidden" name="thang" value="<?= $t ?>">
          <input type="hidden" name="id_khoa" value="0">
          <button class="nut nut-nho nut-phu" type="submit">Bỏ lịch</button>
        </form>
        <?php endif; ?>
        <?php // Chỉ hiện khi tháng đang mở (còn trong cửa nhập)
        if (time() >= $cs['mo_tu'] && time() <= $cs['dong_sau']): ?>
        <form method="post"
              data-xac-nhan="Khóa tháng <?= $t ?>/<?= $nam ?> NGAY cho mọi khoa? Không ai nhập/sửa được nữa." data-xac-nhan-loai="nguy">
          <?= csrf_field() ?>
          <input type="hidden" name="viec" value="khoa_ngay">
          <input type="hidden" name="nam" value="<?= $nam ?>">
          <input type="hidden" name="thang" value="<?= $t ?>">
          <button class="nut nut-nho nut-nguy" type="submit">Khóa lại ngay</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endfor; ?>
  </tbody>
</table>
</div>

</div><!-- /tab lich -->

<div class="tab-noi" data-tab="giahan" hidden>
<h2>Gia hạn cho khoa</h2>
<p class="phu">
  Dùng khi khoa nộp muộn hoặc cần nhập bổ sung sau khi lịch chung đã đóng.
  Chọn <strong>một hoặc nhiều khoa</strong>. Gia hạn đè lên mọi lịch, nhưng không mở lại được kỳ đã duyệt/đã khóa.
</p>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="gia_han">
  <input type="hidden" name="nam" value="<?= $nam ?>">
  <fieldset style="border:1px solid var(--vien,#e2e8f0);border-radius:10px;padding:10px 12px;margin-bottom:10px">
    <legend style="font-weight:600;padding:0 6px">Chọn khoa</legend>
    <label class="o-chon" style="display:inline-flex;gap:6px;margin:0 16px 6px 0;font-weight:600">
      <input type="checkbox" onclick="ghChonHet(this)"> Tất cả
    </label>
    <?php foreach ($dsKhoa as $k): ?>
      <label class="o-chon" style="display:inline-flex;gap:6px;margin:0 16px 6px 0">
        <input type="checkbox" class="gh-khoa" name="id_khoa[]" value="<?= (int)$k['id'] ?>">
        <strong><?= e($k['ma']) ?></strong> <span class="phu"><?= e($k['ten']) ?></span>
      </label>
    <?php endforeach; ?>
  </fieldset>
  <div class="bieu-mau-ngang">
    <label>Tháng
      <select name="thang">
        <?php for ($t = 1; $t <= 12; $t++): ?>
          <option value="<?= $t ?>">Tháng <?= $t ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <label>Mở đến hết ngày
      <input type="date" name="mo_den" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
    </label>
    <button class="nut nut-chinh" type="submit">Gia hạn khoa đã chọn</button>
  </div>
</form>
<script>
function ghChonHet(o){ document.querySelectorAll('.gh-khoa').forEach(c => c.checked = o.checked); }
</script>

<?php if ($giaHan): ?>
<h3>Đang gia hạn</h3>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="thu_hoi">
  <input type="hidden" name="nam" value="<?= $nam ?>">
  <div class="cuon-ngang">
  <table class="bang">
    <thead><tr>
      <th style="width:32px"><input type="checkbox" onclick="thChonHet(this)" title="Chọn tất cả"></th>
      <th>Khoa</th><th>Tháng</th><th>Mở đến hết</th><th>Còn hiệu lực</th>
    </tr></thead>
    <tbody>
    <?php foreach ($giaHan as $g):
        $con = strtotime($g['mo_den'] . ' 23:59:59') >= time(); ?>
      <tr class="<?= $con ? '' : 'dong-mo' ?>">
        <td><input type="checkbox" class="th-muc" name="muc[]"
                   value="<?= (int)$g['thang'] ?>:<?= (int)$g['id_khoa'] ?>"></td>
        <td><strong><?= e($g['ma']) ?></strong> <span class="phu"><?= e($g['ten']) ?></span></td>
        <td>Tháng <?= (int)$g['thang'] ?></td>
        <td class="nho"><?= date('d/m/Y', strtotime($g['mo_den'])) ?></td>
        <td><?= $con ? '<span class="trang-thai bat">Còn hạn</span>'
                     : '<span class="trang-thai tat">Hết hạn</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div style="margin-top:10px">
    <button class="nut nut-nguy" type="submit"
            data-xac-nhan="Thu hồi các khoa đã chọn? Khoa sẽ không nhập/sửa được nữa ngay." data-xac-nhan-loai="nguy">
      Thu hồi khoa đã chọn
    </button>
  </div>
</form>
<script>
function thChonHet(o){ document.querySelectorAll('.th-muc').forEach(c => c.checked = o.checked); }
</script>
<?php endif; ?>
</div><!-- /tab giahan -->

<script>
/* Tab con trang Lịch mở kỳ — đổi tab không tải lại, nhớ qua #hash */
(function () {
  var thanh = document.querySelector('.tab-phu[role="tablist"]');
  if (!thanh) { return; }
  var noi = document.querySelectorAll('.tab-noi[data-tab]');
  function mo(t) {
    var co = false;
    thanh.querySelectorAll('[data-tab]').forEach(function (b) {
      var chon = b.dataset.tab === t; b.classList.toggle('dang', chon); if (chon) { co = true; }
    });
    if (!co) { t = 'lich'; thanh.querySelector('[data-tab="lich"]').classList.add('dang'); }
    noi.forEach(function (x) { x.hidden = x.dataset.tab !== t; });
  }
  thanh.addEventListener('click', function (e) {
    var b = e.target.closest('[data-tab]'); if (!b) { return; }
    mo(b.dataset.tab); history.replaceState(null, '', '#' + b.dataset.tab);
  });
  mo((location.hash || '#lich').slice(1));
})();
</script>
<?php dong_trang();

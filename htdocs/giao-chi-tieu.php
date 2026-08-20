<?php
/**
 * Giao chỉ tiêu năm cho từng khoa.
 * Lưu song song hai mốc: chỉ tiêu giao (quyết định) và chỉ tiêu năng lực (tính toán).
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/chi_tieu.php';
require_once __DIR__ . '/app/danh_muc.php';

function dong_giao(array $ct, ?array $r, int $nam, int $idKhoa,
    bool $duocSuaCT, bool $duocThemCT, bool $laMoi = false): string
{
    ob_start(); ?>
      <tr class="<?= $ct['cap'] ? 'dong-con' : '' ?><?= $laMoi ? ' vua-them' : '' ?>"
          data-id="<?= (int)$ct['id'] ?>" data-cha="<?= $ct['id_cha'] !== null ? (int)$ct['id_cha'] : '' ?>"
          data-loai="<?= e($ct['loai_gia_tri']) ?>" data-nguon="<?= e($ct['nguon']) ?>"
          data-huong="<?= e($ct['huong']) ?>" data-phanbo="<?= e($ct['phan_bo']) ?>"
          data-pheptinh="<?= e((string)($ct['phep_tinh'] ?? '')) ?>"
          data-cttu="<?= e((string)($ct['ct_tu'] ?? '')) ?>" data-ctmau="<?= e((string)($ct['ct_mau'] ?? '')) ?>"
          data-nhanngay="<?= !empty($ct['nhan_so_ngay']) ? 1 : 0 ?>"
          data-gopvao="<?= e((string)($ct['gop_vao'] ?? '')) ?>"
          data-thutu="<?= (int)($ct['vi_tri'] ?? $ct['thu_tu']) ?>" data-ma="<?= e($ct['ma']) ?>"
          data-thutuchung="<?= vi_tri_thu_vien((int)$ct['id']) ?>"
          data-mota="<?= e((string)($ct['mo_ta'] ?? '')) ?>"
          data-hethong="<?= la_he_thong($ct['ma']) ? 1 : 0 ?>"
          <?= $laMoi ? 'id="dong-moi"' : '' ?>>
        <td<?= $ct['cap'] ? ' style="padding-left:' . (6 + (int)$ct['cap'] * 20) . 'px"' : '' ?>>
          <div class="o-ten-ct">
            <?= $ct['cap'] ? '<span class="thut">↳</span>' : '' ?>
            <?php if ($duocSuaCT): ?>
              <input type="text" name="ct_ten[<?= $ct['id'] ?>]" class="o-sua-ten"
                     value="<?= e($ct['ten']) ?>" title="Sửa trực tiếp rồi bấm Lưu chỉ tiêu">
            <?php else: ?>
              <span class="ten-ct-chu"><?= e($ct['ten']) ?></span>
            <?php endif; ?>
            <?php if ($laMoi): ?><span class="the the-nho the-moi">vừa thêm</span><?php endif; ?>
            <?php if ($ct['huong'] === 'THAP_TOT'): ?>
              <span class="the the-nho">thấp là tốt</span>
            <?php elseif ($ct['huong'] === 'DICH_CO_DINH'): ?>
              <span class="the the-nho">đích 100%</span>
            <?php endif; ?>
            <?php if (!empty($ct['gop_vao'])):
                $ctChuan = chi_tieu_theo_ma($ct['gop_vao']); ?>
              <span class="the the-nho the-gop" title="Số liệu cộng vào chỉ tiêu chuẩn khi lên dashboard/báo cáo">↗ gộp vào <?= e($ctChuan ? $ctChuan['ten'] : $ct['gop_vao']) ?></span>
            <?php endif; ?>
            <?php $coThemCon = $duocThemCT;   // lồng con không giới hạn cấp
                  if ($duocSuaCT || $duocThemCT): ?>
              <span class="ct-dieu-khien">
                <?php if ($duocSuaCT): ?>
                  <span class="ct-keo ct-nut" draggable="true" title="Kéo để đổi vị trí">⠿</span>
                  <button type="button" class="ct-nut ct-len" title="Chuyển lên"
                          onclick="doiCho(<?= $ct['id'] ?>, -1)">▲</button>
                  <button type="button" class="ct-nut ct-xuong" title="Chuyển xuống"
                          onclick="doiCho(<?= $ct['id'] ?>, 1)">▼</button>
                  <button type="button" class="ct-nut" title="Sửa chỉ tiêu (loại, cách đánh giá…)"
                          onclick="suaCT(<?= $ct['id'] ?>)">✎</button>
                <?php endif; ?>
                <?php if ($coThemCon): ?>
                  <button type="button" class="them-con-nut ct-nut" data-cha="<?= $ct['id'] ?>"
                          title="Thêm nội dung con">＋</button>
                <?php endif; ?>
                <?php if ($duocThemCT): ?>
                  <button type="button" class="ct-nut ct-nut-xoa" title="Gỡ chỉ tiêu này khỏi khoa"
                          onclick="goKhoiKhoa(<?= $ct['id'] ?>)">✕</button>
                <?php endif; ?>
              </span>
            <?php endif; ?>
            <?php if (!empty($ct['mo_ta'])): ?>
              <div class="mo-ta-ct phu"><?= e($ct['mo_ta']) ?></div>
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
      <?php if ($duocThemCT): ?>
      <tr class="dong-them-con" id="them-con-<?= $ct['id'] ?>" hidden>
        <td colspan="7" style="padding-left:<?= 6 + ((int)$ct['cap'] + 1) * 20 ?>px">
          <div class="hang-them-con">
            <span class="thut">↳</span>
            <input type="text" name="ct_ten" form="fcon-<?= $ct['id'] ?>"
                   class="o-them-con-ten o-combo-ct" placeholder="Gõ để tìm hoặc tạo mới…" autocomplete="off">
            <input type="text" name="ct_don_vi" form="fcon-<?= $ct['id'] ?>"
                   class="o-them-con-dv" placeholder="Đơn vị" autocomplete="off">
            <button class="nut nut-nho" type="submit" form="fcon-<?= $ct['id'] ?>">+ Thêm con</button>
            <button type="button" class="nut-nang-cao" data-huy-con="<?= $ct['id'] ?>">Hủy</button>
          </div>
        </td>
      </tr>
      <?php endif; ?>
    <?php return ob_get_clean();
}

function form_con(array $ct): string
{
    ob_start(); ?>
    <form method="post" id="fcon-<?= (int)$ct['id'] ?>" class="an">
      <?= csrf_field() ?>
      <input type="hidden" name="viec" value="them_chi_tieu">
      <input type="hidden" name="ct_cha" value="<?= (int)$ct['id'] ?>">
      <input type="hidden" name="ct_dung_lai" value="">
    </form>
    <?php return ob_get_clean();
}

$toi = bat_buoc_quyen('chitieu.giao');
$duocThemCT = co_quyen('chitieu.them');
$duocSuaCT  = co_quyen('chitieu.sua');

// Kiểm tra nhanh trùng lặp cho RIÊNG một chỉ tiêu (dùng trong popup Sửa) — trả JSON,
// phạm vi nhỏ: chỉ liệt kê các mã TRÙNG TÊN với chỉ tiêu này, không quét cả thư viện.
if (isset($_GET['ajax_trung']) && $duocSuaCT) {
    header('Content-Type: application/json; charset=utf-8');
    $idKt = (int)($_GET['id'] ?? 0);
    $ctKt = q1('SELECT * FROM chi_tieu WHERE id = ?', [$idKt]);
    if (!$ctKt) { echo json_encode(['ok' => false, 'loi' => 'Không tìm thấy chỉ tiêu.']); exit; }
    $khopKt = chuan_hoa_khop($ctKt['ten']);
    $chaKt  = (int)($ctKt['id_cha'] ?? 0);
    $tenChaKt = fn($idc) => $idc ? (qVal('SELECT ten FROM chi_tieu WHERE id = ?', [$idc]) ?: '') : '';
    $dsKt = [];
    if ($khopKt !== '') {
        foreach (tat_ca_chi_tieu() as $c) {
            if ((int)$c['id'] === $idKt || la_he_thong($c['ma'])) { continue; }
            if (chuan_hoa_khop($c['ten']) !== $khopKt) { continue; }
            $dsKt[] = [
                'ma'  => $c['ma'], 'ten' => $c['ten'],
                'cha' => $tenChaKt((int)($c['id_cha'] ?? 0)),
                'cung_cha' => (int)($c['id_cha'] ?? 0) === $chaKt,
            ];
        }
    }
    echo json_encode(['ok' => true, 'items' => $dsKt], JSON_UNESCAPED_UNICODE);
    exit;
}

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
        $dungLaiId = (int)post('ct_dung_lai');   // id chỉ tiêu người dùng CHỌN từ danh sách

        $loai   = post('ct_loai', 'DEM');
        $huong  = post('ct_huong', 'CAO_TOT');
        $phanBo = post('ct_phan_bo', 'THEO_NGAY');
        $hopLe = in_array($loai, ['DEM','TRUNG_BINH','TY_LE','HANG_SO','GHI_CHU'], true)
              && in_array($huong, ['CAO_TOT','THAP_TOT','DICH_CO_DINH'], true)
              && in_array($phanBo, ['THEO_NGAY','KHONG_CHIA'], true);

        // Nguồn + cấu hình công thức (chỉ khi có quyền đặt công thức)
        $nguon = 'NHAP_TAY';
        $phepTinh = null; $ctTu = null; $ctMau = null; $nhanNgay = 0;
        if (post('ct_nguon') === 'CONG_THUC' && co_quyen('chitieu.cong_thuc')) {
            $nguon    = 'CONG_THUC';
            $phepTinh = post('ct_phep_tinh', 'TY_LE');
            $ctTu     = chu_hoa(post('ct_tu'));
            $ctMau    = chu_hoa(post('ct_mau'));
            $nhanNgay = post('ct_nhan_ngay') ? 1 : 0;
            $idCha    = null;   // công thức luôn là chỉ tiêu đứng riêng, không lồng
            if (!in_array($phepTinh, ['TY_LE','THUONG'], true)
             || $ctTu === '' || $ctMau === '' || $ctTu === $ctMau
             || !qVal('SELECT 1 FROM chi_tieu WHERE ma=? AND hoat_dong=1', [$ctTu])
             || !qVal('SELECT 1 FROM chi_tieu WHERE ma=? AND hoat_dong=1', [$ctMau])) {
                $hopLe = false;   // cấu hình công thức thiếu/sai
            } else {
                $loai   = $phepTinh === 'TY_LE' ? 'TY_LE' : 'TRUNG_BINH';
                $phanBo = 'KHONG_CHIA';
            }
        }

        if ($ten === '') {
            nhan_tin('loi', 'Chưa nhập nội dung chỉ tiêu.');
        } elseif (!$hopLe) {
            nhan_tin('loi', 'Thông số chỉ tiêu không hợp lệ.');
        } elseif ($ma !== '' && !preg_match('/^[A-Z0-9_]{2,30}$/', $ma)) {
            nhan_tin('loi', 'Mã chỉ tiêu chỉ gồm chữ in hoa, số và gạch dưới, dài 2–30 ký tự.');
        } else {
            // Thêm CON (bỏ trống mã): nếu TÊN trùng một chỉ tiêu CHUẨN đã có →
            // DÙNG LẠI nó (gán vào khoa), KHÔNG đẻ mã mới. Đẻ mã trùng lặp làm
            // dashboard/báo cáo cộng THIẾU số của khoa (gom theo mã chuẩn).
            // Không khớp chuẩn nào thì mới tạo mới (mã tự thêm hậu tố).
            if ($dungLaiId > 0) {
                // Người dùng CHỌN từ danh sách gợi ý → dùng lại đúng chỉ tiêu đó.
                // NHƯNG chỉ khi ĐÚNG cha đang thêm: trùng tên mà khác cha là chỉ tiêu
                // KHÁC (vd "Loại I" dưới thủ thuật ≠ dưới phẫu thuật) → tạo mới dưới cha này.
                $daCo = q1('SELECT * FROM chi_tieu WHERE id = ? AND hoat_dong = 1', [$dungLaiId]);
                if ($daCo && (int)($daCo['id_cha'] ?? 0) !== (int)($idCha ?? 0)) {
                    $daCo = null;
                }
                $maKhop = $daCo ? $daCo['ma'] : ma_tu_ten($ten);
            } elseif ($idCha !== null && $ma === '') {
                // Tự khớp theo tên với chỉ tiêu CHUẨN — chỉ dùng lại khi ĐÚNG cha,
                // khác cha thì là chỉ tiêu khác → tạo mới.
                $daCo = null;
                $tenKhop = chuan_hoa_khop($ten);
                if ($tenKhop !== '') {
                    foreach (tat_ca_chi_tieu() as $c) {
                        if (!empty($c['la_chuan']) && (int)($c['hoat_dong'] ?? 1) === 1
                                && (int)($c['id_cha'] ?? 0) === $idCha
                                && chuan_hoa_khop($c['ten']) === $tenKhop) {
                            $daCo = $c; break;
                        }
                    }
                }
                $maKhop = $daCo ? $daCo['ma'] : ma_tu_ten($ten);
            } else {
                // Đầu mục lớn (hoặc gõ mã tay): giữ tự-khớp — trùng mã thì dùng lại.
                $maKhop = $ma !== '' ? $ma : chuoi_thanh_ma($ten, 'CT', 30);
                $daCo = q1('SELECT * FROM chi_tieu WHERE ma = ?', [$maKhop]);
            }

            if ($daCo) {
                // DÙNG LẠI chỉ tiêu có sẵn (trùng tên chuẩn hoặc trùng mã) → gán vào
                // khoa: kèm TỔ TIÊN (để cha hiện đúng) + chính nó + HẬU DUỆ. Không
                // tạo bản trùng → số liệu cộng đúng vào chỉ tiêu chuẩn.
                $toTien = [];
                $cur = tat_ca_chi_tieu()[(int)$daCo['id']] ?? null;
                while ($cur && $cur['id_cha'] !== null) {
                    $toTien[] = (int)$cur['id_cha'];
                    $cur = tat_ca_chi_tieu()[(int)$cur['id_cha']] ?? null;
                }
                $dsGan = array_merge($toTien, [(int)$daCo['id']], hau_due_ids((int)$daCo['id']));
                $soGan = 0;
                foreach ($dsGan as $cid) {
                    if (!qVal('SELECT 1 FROM chi_tieu_ap_dung WHERE id_chi_tieu=? AND id_khoa=?',
                            [$cid, $idKhoa])) {
                        q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)',
                            [$cid, $idKhoa]);
                        $soGan++;
                    }
                }
                if ($soGan > 0) {
                    ghi_nhat_ky('GAN_CHI_TIEU', $daCo['ma'], "Dùng lại cho khoa {$khoa['ma']} ($soGan dòng)");
                    nhan_tin('ok', "Đã dùng lại chỉ tiêu chuẩn có sẵn \"{$daCo['ten']}\" "
                        . "(mã {$daCo['ma']}) cho {$khoa['ten']} — số liệu sẽ cộng đúng vào chỉ tiêu này, "
                        . 'không tạo mã trùng.');
                    $taiLaiSauThem = true;   // có thể thêm cả con → tải lại cho gọn
                } else {
                    nhan_tin('canh-bao', "Chỉ tiêu \"{$daCo['ten']}\" đã có sẵn trong khoa này rồi.");
                }
            } else {
                // Nội dung con phải nằm dưới một chỉ tiêu của CHÍNH khoa này
                // (cha có thể là gốc HAY đã là con — lồng không giới hạn cấp).
                if ($idCha !== null) {
                    $cha = q1('SELECT c.* FROM chi_tieu c
                                JOIN chi_tieu_ap_dung a ON a.id_chi_tieu = c.id
                               WHERE c.id = ? AND a.id_khoa = ?',
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
                q('INSERT INTO chi_tieu
                     (ma, ten, don_vi, id_cha, thu_tu, loai_gia_tri, nguon, huong, phan_bo,
                      phep_tinh, ct_tu, ct_mau, nhan_so_ngay)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$maKhop, $ten, $donVi, $idCha, $thuTu, $loai, $nguon, $huong, $phanBo,
                     $phepTinh, $ctTu, $ctMau, $nhanNgay]);
                $idMoi = (int)db()->lastInsertId();
                q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)', [$idMoi, $idKhoa]);
                $keoTheo = [];
                if ($idCha !== null) {
                    q('UPDATE chi_tieu SET nguon=? WHERE id=? AND nguon=?', ['TONG_CON', $idCha, 'NHAP_TAY']);
                    $keoTheo = dong_bo_cha_con($idMoi);
                }
                db()->commit();

                ghi_nhat_ky('THEM_CHI_TIEU', $maKhop, "$ten (thêm từ bảng Giao chỉ tiêu, khoa {$khoa['ma']})");
                nhan_tin('ok', "Đã thêm dòng \"$ten\" (mã $maKhop) cho {$khoa['ten']}."
                    . ($idCha !== null ? ' Nội dung lớn đã chuyển sang tự cộng từ các dòng con.' : ''));
                foreach ($keoTheo as $g) {
                    nhan_tin('canh-bao', $g);
                }
                $idMoiCT = $idMoi;
            }
        }
        // Vừa thêm thì đưa dòng mới lên đầu; tải lại trang thường (không có
        // ?moi) nó tự về đúng thứ tự.
        // Gửi bằng AJAX thì trả về HTML dòng mới, không tải lại cả trang.
        if (post('ajax') === '1') {
            $tbs = lay_thong_bao();   // rút thông báo khỏi phiên để không đọng lại
            header('Content-Type: application/json; charset=utf-8');
            if (!empty($taiLaiSauThem)) {
                echo json_encode(['ok' => true, 'reload' => true], JSON_UNESCAPED_UNICODE);
            } elseif (isset($idMoiCT)) {
                $ctMoi = q1('SELECT * FROM chi_tieu WHERE id = ?', [$idMoiCT]);
                $ctMoi['cap'] = cap_cua_ct((int)$ctMoi['id']);   // độ sâu thật trong cây
                echo json_encode([
                    'ok'    => true,
                    'row'   => dong_giao($ctMoi, null, $nam, $idKhoa, $duocSuaCT, $duocThemCT, true),
                    'fcon'  => form_con($ctMoi),   // mọi dòng đều có form để thêm con tiếp
                    'chaId' => (int)($ctMoi['id_cha'] ?? 0),
                    'id'    => (int)$idMoiCT,
                    'ten'   => $ctMoi['ten'],
                ], JSON_UNESCAPED_UNICODE);
            } else {
                $loiTb = '';
                foreach ($tbs as $t) {
                    if (in_array($t['loai'], ['loi', 'canh-bao'], true)) { $loiTb = $t['noi_dung']; }
                }
                echo json_encode(['ok' => false, 'loi' => $loiTb ?: 'Không thêm được dòng.'],
                    JSON_UNESCAPED_UNICODE);
            }
            exit;
        }
        chuyen_huong("/giao-chi-tieu.php?nam=$nam&khoa=$idKhoa"
            . (isset($idMoiCT) ? "&moi=$idMoiCT" : '#ct-moi'));
    }

    /* ---------- Gán chỉ tiêu chuẩn có sẵn từ thư viện (dùng chung) ---------- */
    if (post('viec') === 'gan_thu_vien' && $duocThemCT) {
        $ids = array_map('intval', $_POST['tv'] ?? []);
        $soGan = 0; $tenGan = [];
        db()->beginTransaction();
        foreach ($ids as $pid) {
            $ct = q1('SELECT * FROM chi_tieu WHERE id = ? AND la_chuan = 1 AND hoat_dong = 1', [$pid]);
            if (!$ct) { continue; }
            $nhom = array_merge([$pid], hau_due_ids($pid));   // gán cả nội dung con/cháu kèm theo
            foreach ($nhom as $cid) {
                if (!qVal('SELECT 1 FROM chi_tieu_ap_dung WHERE id_chi_tieu=? AND id_khoa=?', [$cid, $idKhoa])) {
                    q('INSERT INTO chi_tieu_ap_dung (id_chi_tieu, id_khoa) VALUES (?,?)', [$cid, $idKhoa]);
                    $soGan++;
                }
            }
            $tenGan[] = $ct['ten'];
        }
        db()->commit();
        if ($soGan > 0) {
            ghi_nhat_ky('GAN_CHI_TIEU_THU_VIEN', $khoa['ma'], implode(', ', $tenGan) . " ($soGan dòng)");
            nhan_tin('ok', 'Đã gán ' . count($tenGan) . " chỉ tiêu chuẩn cho {$khoa['ten']}.");
        } else {
            nhan_tin('canh-bao', 'Chưa chọn chỉ tiêu nào để gán.');
        }
        if (post('ajax') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'reload' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        chuyen_huong("/giao-chi-tieu.php?nam=$nam&khoa=$idKhoa");
    }

    /* ---------- Đổi vị trí lên/xuống (trong nhóm anh em cùng cha) ---------- */
    if (post('viec') === 'chuyen' && $duocSuaCT) {
        $id  = (int)post('id');
        $len = post('huong') === 'len';
        $ct  = q1('SELECT * FROM chi_tieu WHERE id = ?', [$id]);
        if ($ct) {
            $anhEm = array_values(array_filter(
                chi_tieu_cua_khoa($idKhoa),
                fn($c) => (string)$c['id_cha'] === (string)$ct['id_cha']));
            $vt = null;
            foreach ($anhEm as $k => $c) {
                if ((int)$c['id'] === $id) { $vt = $k; break; }
            }
            $ke = $len ? ($vt ?? -1) - 1 : ($vt ?? -1) + 1;
            if ($vt !== null && isset($anhEm[$ke])) {
                $a = $anhEm[$vt]; $b = $anhEm[$ke];
                // thu_tu ở đây đã là thứ tự RIÊNG của khoa; đổi chỗ ngay trong khoa này.
                $ta = (int)$a['thu_tu']; $tb = (int)$b['thu_tu'];
                if ($ta === $tb) { $tb = $ta + ($len ? -1 : 1); }
                q('UPDATE chi_tieu_ap_dung SET thu_tu = ? WHERE id_chi_tieu = ? AND id_khoa = ?', [$tb, $a['id'], $idKhoa]);
                q('UPDATE chi_tieu_ap_dung SET thu_tu = ? WHERE id_chi_tieu = ? AND id_khoa = ?', [$ta, $b['id'], $idKhoa]);
                ghi_nhat_ky('CHUYEN_CHI_TIEU', $a['ma'], $len ? 'lên' : 'xuống');
            }
        }
        chuyen_huong("/giao-chi-tieu.php?nam=$nam&khoa=$idKhoa");
    }

    /* ---------- Sắp xếp lại bằng kéo-thả (nhận cả danh sách id theo thứ tự mới) ----------
       Ghi vào thu_tu RIÊNG của khoa (chi_tieu_ap_dung.thu_tu) nên chỉ đổi thứ tự
       của khoa này, KHÔNG ảnh hưởng khoa khác. */
    if (post('viec') === 'sap_xep' && $duocSuaCT) {
        $ids = array_values(array_filter(array_map('intval', explode(',', post('ids')))));
        $thuoc = [];   // chỉ tiêu thực sự thuộc khoa này
        foreach (qAll('SELECT id_chi_tieu FROM chi_tieu_ap_dung WHERE id_khoa = ?', [$idKhoa]) as $r) {
            $thuoc[(int)$r['id_chi_tieu']] = true;
        }
        $ids = array_values(array_filter($ids, fn($id) => isset($thuoc[$id])));
        if ($ids) {
            db()->beginTransaction();
            $tt = 10;   // đánh lại đều bước 10 theo đúng thứ tự mới, riêng cho khoa
            foreach ($ids as $id) {
                q('UPDATE chi_tieu_ap_dung SET thu_tu = ? WHERE id_chi_tieu = ? AND id_khoa = ?',
                    [$tt, $id, $idKhoa]);
                $tt += 10;
            }
            db()->commit();
            ghi_nhat_ky('SAP_XEP_CHI_TIEU', $khoa['ma'], count($ids) . ' dòng (kéo-thả, riêng khoa)');
        }
        if (post('ajax') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        chuyen_huong("/giao-chi-tieu.php?nam=$nam&khoa=$idKhoa");
    }

    /* ---------- Xóa vĩnh viễn một chỉ tiêu (chỉ dev) ---------- */
    if (post('viec') === 'xoa_ct') {
        $id = (int)post('id');
        $cu = q1('SELECT * FROM chi_tieu WHERE id = ?', [$id]);
        $loiXoa = null;
        if (!co_quyen('chitieu.xoa')) {
            $loiXoa = 'Chỉ người phát triển mới xóa vĩnh viễn được chỉ tiêu. '
                . 'Vào Thư viện chỉ tiêu dùng "Ngừng dùng" thay thế.';
        } elseif (!$cu) {
            $loiXoa = 'Không tìm thấy chỉ tiêu.';
        } elseif (la_he_thong($cu['ma'])) {
            $loiXoa = "\"{$cu['ma']}\" là chỉ tiêu hệ thống, không xóa được.";
        } elseif (($n = chi_tieu_co_du_lieu($id)) > 0) {
            $loiXoa = "Chỉ tiêu này đã có $n dòng số liệu/kế hoạch nên không xóa được. "
                . 'Dùng "Ngừng dùng" ở Thư viện chỉ tiêu.';
        } elseif (qVal('SELECT 1 FROM chi_tieu WHERE id_cha = ?', [$id])) {
            $loiXoa = 'Còn nội dung con bên trong. Xóa các nội dung con trước.';
        } else {
            q('DELETE FROM chi_tieu WHERE id = ?', [$id]);
            danh_lai_thu_tu();   // dồn lại số sau khi xóa (không để lỗ hổng)
            ghi_nhat_ky('XOA_CHI_TIEU', $cu['ma'], "{$cu['ten']} (từ bảng Giao chỉ tiêu)");
        }
        if (post('ajax') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($loiXoa === null ? ['ok' => true] : ['ok' => false, 'loi' => $loiXoa],
                JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($loiXoa !== null) { nhan_tin('loi', $loiXoa); }
        else { nhan_tin('ok', "Đã xóa chỉ tiêu \"{$cu['ten']}\"."); }
        chuyen_huong("/giao-chi-tieu.php?nam=$nam&khoa=$idKhoa");
    }

    /* ---------- Gỡ chỉ tiêu (kèm con) khỏi khoa này — KHÔNG xóa khỏi danh mục ---------- */
    if (post('viec') === 'go_khoi_khoa' && $duocThemCT) {
        $id = (int)post('id');
        $ct = q1('SELECT * FROM chi_tieu WHERE id = ?', [$id]);
        if ($ct) {
            $ids = array_merge([$id], hau_due_ids($id));   // gỡ cả con/cháu khỏi khoa
            foreach ($ids as $cid) {
                q('DELETE FROM chi_tieu_ap_dung WHERE id_chi_tieu = ? AND id_khoa = ?', [$cid, $idKhoa]);
            }
            ghi_nhat_ky('GO_CHI_TIEU_KHOI_KHOA', $ct['ma'], "Khoa {$khoa['ma']}: {$ct['ten']}");
            nhan_tin('ok', "Đã gỡ \"{$ct['ten']}\" khỏi {$khoa['ten']}.");
        }
        if (post('ajax') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        chuyen_huong("/giao-chi-tieu.php?nam=$nam&khoa=$idKhoa");
    }

    /* ---------- Sửa cấu hình chỉ tiêu ngay trong bảng Giao ---------- */
    if (post('viec') === 'sua' && $duocSuaCT) {
        $id    = (int)post('id');
        $cu    = q1('SELECT * FROM chi_tieu WHERE id = ?', [$id]);
        $tenS  = post('ten');
        $dvS   = post('don_vi');
        $ttS   = (int)post('thu_tu', '0');
        $f     = doc_bieu_mau(co_quyen('chitieu.cong_thuc'));
        $gopVao = chu_hoa(post('gop_vao'));   // '' = không gộp
        $moTaS = trim(post('mo_ta')) !== '' ? trim(post('mo_ta')) : null;   // ghi chú quản lý
        // Sửa nhanh mã: chuẩn hóa về CHỮ HOA + A-Z0-9_; rỗng = giữ mã cũ.
        $maMoi = preg_replace('/[^A-Z0-9_]/', '', chu_hoa((string)post('ct_ma')));
        $loiSua = null;
        if (!$cu) {
            $loiSua = 'Không tìm thấy chỉ tiêu.';
        } elseif ($tenS === '') {
            $loiSua = 'Nội dung không được để trống.';
        } elseif (!$f) {
            $loiSua = 'Thông số chỉ tiêu không hợp lệ.';
        } elseif ($gopVao !== '' && $gopVao === $cu['ma']) {
            $loiSua = 'Không thể gộp chỉ tiêu vào chính nó.';
        } elseif ($gopVao !== ''
              && !qVal('SELECT 1 FROM chi_tieu WHERE ma=? AND la_chuan=1 AND hoat_dong=1', [$gopVao])) {
            $loiSua = 'Chỉ tiêu chuẩn để gộp vào không hợp lệ.';
        } elseif (!la_he_thong($cu['ma']) && $maMoi !== '' && $maMoi !== $cu['ma']
              && qVal('SELECT 1 FROM chi_tieu WHERE ma = ? AND id <> ?', [$maMoi, $id])) {
            $loiSua = "Mã \"$maMoi\" đã có ở chỉ tiêu khác. Muốn gộp hai mã làm một, "
                . 'dùng Thư viện → Kiểm tra trùng lặp.';
        } elseif (la_he_thong($cu['ma'])) {
            // Chỉ tiêu hệ thống: giữ nguyên cách tính, chỉ sửa chữ hiển thị + mô tả.
            // (Vị trí trong bảng do phía client xử lý bằng cơ chế kéo-thả — xem datViTri.)
            q('UPDATE chi_tieu SET ten=?, don_vi=?, mo_ta=? WHERE id=?', [$tenS, $dvS, $moTaS, $id]);
            ghi_nhat_ky('SUA_CHI_TIEU', $cu['ma'], $tenS);
        } elseif ($f['nguon'] === 'CONG_THUC' && ($cu['ma'] === $f['ct_tu'] || $cu['ma'] === $f['ct_mau'])) {
            $loiSua = 'Công thức không thể lấy chính chỉ tiêu này làm tử số hoặc mẫu số.';
        } else {
            // Gộp vào chỉ tiêu chuẩn → thành biến thể riêng (la_chuan=0); bỏ gộp thì giữ nguyên.
            $gvLuu = $gopVao !== '' ? $gopVao : null;
            $chuan = $gopVao !== '' ? 0 : (int)$cu['la_chuan'];
            q('UPDATE chi_tieu SET ten=?, don_vi=?, loai_gia_tri=?, nguon=?, huong=?, phan_bo=?,
                 phep_tinh=?, ct_tu=?, ct_mau=?, nhan_so_ngay=?, gop_vao=?, la_chuan=?, mo_ta=?
               WHERE id=?',
                [$tenS, $dvS, $f['loai'], $f['nguon'], $f['huong'], $f['phan_bo'],
                 $f['phep_tinh'], $f['ct_tu'], $f['ct_mau'], $f['nhan_so_ngay'], $gvLuu, $chuan, $moTaS, $id]);
            // (Vị trí trong bảng do phía client xử lý bằng cơ chế kéo-thả — xem datViTri.)
            // Đổi mã (nếu có): cập nhật luôn các tham chiếu theo mã (gộp vào / công thức).
            if ($maMoi !== '' && $maMoi !== $cu['ma']) {
                q('UPDATE chi_tieu SET ma = ? WHERE id = ?', [$maMoi, $id]);
                q('UPDATE chi_tieu SET gop_vao = ? WHERE gop_vao = ?', [$maMoi, $cu['ma']]);
                q('UPDATE chi_tieu SET ct_tu = ? WHERE ct_tu = ?', [$maMoi, $cu['ma']]);
                q('UPDATE chi_tieu SET ct_mau = ? WHERE ct_mau = ?', [$maMoi, $cu['ma']]);
                ghi_nhat_ky('DOI_MA_CHI_TIEU', $cu['ma'], '→ ' . $maMoi);
            }
            ghi_nhat_ky('SUA_CHI_TIEU', $maMoi !== '' ? $maMoi : $cu['ma'], $tenS);
        }
        if (post('ajax') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            if ($loiSua !== null) {
                echo json_encode(['ok' => false, 'loi' => $loiSua], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $ct2 = q1('SELECT * FROM chi_tieu WHERE id = ?', [$id]);
            $ct2['cap']    = $ct2['id_cha'] !== null ? 1 : 0;
            $ct2['vi_tri'] = 1;
            // Lấy độ sâu + số thứ hạng thật trong bảng của khoa để hiển thị đúng.
            foreach (chi_tieu_cua_khoa($idKhoa) as $c) {
                if ((int)$c['id'] === $id) { $ct2['cap'] = (int)$c['cap']; $ct2['vi_tri'] = (int)$c['vi_tri']; break; }
            }
            $r2 = ke_hoach_nam($nam, $idKhoa)[$id] ?? null;
            echo json_encode([
                'ok'  => true, 'id' => $id,
                'msg' => 'Đã lưu "' . $tenS . '".',
                'row' => dong_giao($ct2, $r2, $nam, $idKhoa, $duocSuaCT, $duocThemCT, false),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($loiSua !== null) { nhan_tin('loi', $loiSua); }
        else { nhan_tin('ok', "Đã cập nhật \"$tenS\"."); }
        chuyen_huong("/giao-chi-tieu.php?nam=$nam&khoa=$idKhoa");
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
            // Chỉ đẩy ĐẦU MỤC lên đầu cho dễ thấy. Nội dung con giữ nguyên dưới
            // cha — đẩy lên đầu sẽ tách con khỏi cha, sai thứ bậc.
            if ((int)($c['cap'] ?? 0) === 0) {
                unset($dsCT[$i]);
                array_unshift($dsCT, $c);
                $dsCT = array_values($dsCT);
            }
            break;
        }
    }
}

mo_trang('Giao chỉ tiêu');
?>
<h1>Giao chỉ tiêu năm <?= $nam ?></h1>

<form method="get" class="thanh-loc thanh-loc-gon">
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

<?php if ($duocThemCT):
    $ndLon = array_values(array_filter($dsCT, fn($c) => $c['cap'] === 0));
    $themDevCT = co_quyen('chitieu.cong_thuc');
    $dsCTThem  = $themDevCT ? qAll('SELECT ma, ten FROM chi_tieu WHERE hoat_dong = 1 ORDER BY thu_tu, id') : [];
    // Thư viện chuẩn (nội dung lớn) chưa có trong khoa này — gán lại để dùng chung
    $apKhoaHienCo = [];
    foreach ($dsCT as $c) { $apKhoaHienCo[$c['id']] = true; }
    $thuVienGan = [];
    foreach (tat_ca_chi_tieu() as $c) {
        if ($c['id_cha'] === null && !empty($c['la_chuan']) && !isset($apKhoaHienCo[$c['id']])) {
            $soCon = 0;
            foreach (tat_ca_chi_tieu() as $cc) { if ($cc['id_cha'] === $c['id']) { $soCon++; } }
            $thuVienGan[] = $c + ['so_con' => $soCon];
        }
    } ?>
<!-- Nút nổi ở góc màn hình: bấm mở popup thêm chỉ tiêu, khỏi cuộn lên đầu -->
<button type="button" class="nut-noi" data-mo="modal-them-ct" title="Thêm chỉ tiêu">
  <span class="nut-noi-cong" aria-hidden="true">+</span><span class="nut-noi-chu">Thêm chỉ tiêu</span>
</button>
<div class="lop-phu" id="modal-them-ct" hidden>
 <div class="hop-modal" role="dialog" aria-modal="true" aria-label="Thêm chỉ tiêu">
  <div class="modal-dau">
    <h2>Thêm chỉ tiêu cho khoa</h2>
    <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
  </div>
  <div class="modal-than">
    <?php if ($thuVienGan): ?>
    <fieldset class="nhom-khoa" style="margin:0 0 .75rem">
      <legend>Chọn từ thư viện chuẩn <small class="phu">(khuyến nghị — dùng chung, số liệu tự gộp đúng toàn viện)</small></legend>
      <div class="luoi-o-chon" style="max-height:190px;overflow:auto">
        <?php foreach ($thuVienGan as $c): ?>
          <label class="o-chon">
            <input type="checkbox" name="tv[]" value="<?= (int)$c['id'] ?>" form="gan-thu-vien">
            <span><strong><?= e($c['ten']) ?></strong> <em class="phu">(<?= e($c['ma']) ?><?= $c['so_con'] ? ' · +'.$c['so_con'].' mục nhỏ' : '' ?>)</em></span>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="hang-nut" style="margin:.5rem 0 0">
        <button class="nut nut-chinh nut-nho" type="submit" form="gan-thu-vien">＋ Gán mục đã chọn cho khoa</button>
      </p>
    </fieldset>
    <p class="phu" style="border-top:1px solid var(--vien-nhat);margin:0 0 .6rem;padding-top:.6rem">
      Hoặc <strong>tạo chỉ tiêu mới</strong> (riêng cho khoa này):
    </p>
    <?php endif; ?>
    <div class="luoi-truong">
      <label class="o-rong-2">Nội dung chỉ tiêu
        <input type="text" name="ct_ten" form="them-ct" id="o-ct-ten" class="o-combo-ct"
               placeholder="Gõ để tìm chỉ tiêu đã có, hoặc gõ tên mới…" autocomplete="off">
        <input type="hidden" name="ct_dung_lai" form="them-ct">
      </label>
      <label>Đơn vị
        <input type="text" name="ct_don_vi" form="them-ct" placeholder="Lượt / Ca / %…" autocomplete="off">
      </label>
      <label>Thuộc nhóm
        <select name="ct_cha" form="them-ct">
          <option value="">— Là nội dung lớn —</option>
          <?php foreach ($ndLon as $c): ?>
            <option value="<?= (int)$c['id'] ?>">↳ nằm trong: <?= e($c['ten']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <p class="hang-nang-cao">
      <button type="button" class="nut-nang-cao" onclick="hienNangCao()">Tùy chọn khác ▾</button>
    </p>
    <div class="luoi-truong" id="dong-nang-cao" hidden>
      <label>Mã chỉ tiêu
        <input type="text" name="ct_ma" form="them-ct" placeholder="tự sinh từ nội dung">
      </label>
      <label>Loại giá trị
        <select name="ct_loai" form="them-ct">
          <?php foreach (['DEM','TRUNG_BINH','TY_LE','HANG_SO','GHI_CHU'] as $v): ?>
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
      <?php if ($themDevCT): ?>
      <label>Nguồn số liệu
        <select name="ct_nguon" form="them-ct" id="them-nguon">
          <option value="NHAP_TAY">Khoa nhập tay</option>
          <option value="CONG_THUC">Tính theo công thức</option>
        </select>
      </label>
      <?php endif; ?>
    </div>

    <?php if ($themDevCT): ?>
    <fieldset class="nhom-khoa" id="them-khoi-ct" hidden style="margin:.25rem 0">
      <legend>Cấu hình công thức <small class="phu">(Tử ÷ Mẫu, ×100 nếu tỷ lệ %)</small></legend>
      <div class="luoi-truong">
        <label>Phép tính
          <select name="ct_phep_tinh" form="them-ct">
            <option value="TY_LE">Tỷ lệ % (A ÷ B × 100)</option>
            <option value="THUONG">Thương (A ÷ B)</option>
          </select></label>
        <label>Tử số (A)
          <select name="ct_tu" form="them-ct">
            <option value="">— chọn —</option>
            <?php foreach ($dsCTThem as $c): ?>
              <option value="<?= e($c['ma']) ?>"><?= e($c['ten']) ?> (<?= e($c['ma']) ?>)</option>
            <?php endforeach; ?>
          </select></label>
        <label>Mẫu số (B)
          <select name="ct_mau" form="them-ct">
            <option value="">— chọn —</option>
            <?php foreach ($dsCTThem as $c): ?>
              <option value="<?= e($c['ma']) ?>"><?= e($c['ten']) ?> (<?= e($c['ma']) ?>)</option>
            <?php endforeach; ?>
          </select></label>
        <label class="o-rong" style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="ct_nhan_ngay" value="1" form="them-ct" style="width:auto">
          <span>Nhân mẫu với số ngày trong tháng <small class="phu">(công suất)</small></span>
        </label>
      </div>
    </fieldset>
    <?php endif; ?>
    <p class="them-ct-ket" id="them-ct-ket" hidden></p>
    <div class="form-chan">
      <button class="nut nut-chinh" type="submit" form="them-ct">+ Thêm dòng</button>
      <button type="button" class="nut nut-phu" data-dong>Đóng</button>
    </div>
  </div>
 </div>
</div>
<?php endif; ?>

<?php if (!$dsCT): ?>
  <div class="tb tb-canh-bao">
    Khoa này chưa có chỉ tiêu. Bấm nút <strong>➕ Thêm chỉ tiêu</strong> ở góc phải để thêm từng dòng,
    hoặc vào <a href="/danh-muc-chi-tieu.php">Thư viện chỉ tiêu</a> nạp bộ mặc định.
  </div>
<?php else: ?>

<form method="post" class="hang-nut">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="tinh_nang_luc">
  <button class="nut nut-phu" type="submit">Tính cột năng lực theo giường bệnh</button>
  <?php if ($duocSuaCT): ?>
    <button type="button" class="nut nut-phu" id="nut-hoan-tac" hidden
            title="Hoàn tác lần sắp xếp gần nhất (Ctrl+Z)">↩ Hoàn tác sắp xếp (Ctrl+Z)</button>
  <?php endif; ?>
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
        echo dong_giao($ct, $kh[$ct['id']] ?? null, $nam, $idKhoa,
            $duocSuaCT, $duocThemCT, (int)$ct['id'] === $moiId);
    endforeach; ?>

    </tbody>
  </table>
  </div>
  <p><button class="nut nut-chinh" type="submit">Lưu chỉ tiêu</button></p>
</form>

<?php endif; ?>

<?php if ($duocThemCT): ?>
  <!-- Mỗi nội dung lớn một biểu mẫu ẩn để nút "+ con" gửi thẳng, không lồng
       biểu mẫu vào biểu mẫu "luu" của bảng. -->
  <div id="fcon-forms">
  <?php foreach ($dsCT as $c): echo form_con($c); endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($duocThemCT): ?>
<form method="post" id="them-ct" class="an">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="them_chi_tieu">
</form>
<form method="post" id="gan-thu-vien" class="an">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="gan_thu_vien">
</form>
<form method="post" id="f-chuyen" class="an">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="chuyen">
  <input type="hidden" name="id" id="chuyen-id">
  <input type="hidden" name="huong" id="chuyen-huong">
</form>
<form method="post" id="f-xoa-ct" class="an">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="xoa_ct">
  <input type="hidden" name="id" id="xoa-ct-id">
</form>
<form method="post" id="f-sapxep" class="an">
  <?= csrf_field() ?>
  <input type="hidden" name="viec" value="sap_xep">
  <input type="hidden" name="ids" id="sapxep-ids">
</form>

<?php if ($duocSuaCT):
    $laDevCT = co_quyen('chitieu.cong_thuc');
    $dsCTChon = $laDevCT ? qAll('SELECT ma, ten FROM chi_tieu WHERE hoat_dong = 1 ORDER BY thu_tu, id') : [];
    $dsChuan = qAll('SELECT ma, ten FROM chi_tieu WHERE la_chuan = 1 AND hoat_dong = 1 ORDER BY thu_tu, id');
?>
<div class="lop-phu" id="modal-sua-ct" hidden>
 <div class="hop-modal" role="dialog" aria-modal="true" aria-label="Sửa chỉ tiêu">
  <div class="modal-dau">
    <h2>Sửa chỉ tiêu</h2>
    <button type="button" class="dong-tro-giup" aria-label="Đóng">&times;</button>
  </div>
  <div class="modal-than">
    <div class="tb tb-canh-bao" id="sua-ht" hidden>
      Chỉ tiêu hệ thống — chỉ đổi được tên / đơn vị / thứ tự, cách tính giữ nguyên.
    </div>
    <form method="post" id="f-sua-ct" class="form-tai-khoan">
      <?= csrf_field() ?>
      <input type="hidden" name="viec" value="sua">
      <input type="hidden" name="id" id="sua-id">
      <div class="luoi-truong">
        <label class="o-rong-2">Nội dung
          <input type="text" name="ten" id="sua-ten" required></label>
        <label class="o-rong-2">Mô tả / Ghi chú <small class="nhan-phu">(chữ nhạt hiện dưới tên, để quản lý hiểu chỉ tiêu này dùng làm gì)</small>
          <input type="text" name="mo_ta" id="sua-mota" autocomplete="off"
                 placeholder="VD: BHYT của bệnh nhân điều trị ngoại trú"></label>
        <label class="o-rong-2">Đơn vị
          <input type="text" name="don_vi" id="sua-donvi"></label>
        <label>Thứ tự bảng <small class="nhan-phu">(vị trí trong khoa này: dòng đầu = 1; gõ số để chuyển tới vị trí đó — chỉ đổi khoa này)</small>
          <input type="text" inputmode="numeric" name="thu_tu" id="sua-thutu"></label>
        <label>Thứ tự chung <small class="nhan-phu">(vị trí trong thư viện dùng chung — chỉ xem; sửa ở trang Thư viện chỉ tiêu)</small>
          <input type="text" id="sua-thutuchung" readonly disabled
                 style="background:var(--nen-mo,#f1f5f9);color:var(--chu-phu,#64748b)"></label>
        <label class="o-rong-2">Mã chỉ tiêu
          <input type="text" name="ct_ma" id="sua-ma" autocomplete="off"
                 spellcheck="false" style="text-transform:uppercase">
          <small class="nhan-phu">Sửa nhanh cho gọn/đúng quy ước (VD: <code>KB_BH</code>). Gõ để xem mã đang có.</small>
          <small id="sua-ma-tt" class="ma-tt"></small>
          <div class="trung-hang">
            <button type="button" class="nut nut-nho nut-phu" id="btn-kt-trung">🔍 Chỉ tiêu này có bị trùng?</button>
          </div>
          <div id="sua-trung-kq" class="trung-kq"></div>
        </label>
        <label>Loại giá trị
          <select name="loai_gia_tri" id="sua-loai" data-tinh>
            <?php foreach (['DEM','TRUNG_BINH','TY_LE','HANG_SO','GHI_CHU'] as $v): ?>
              <option value="<?= $v ?>"><?= e(NHAN[$v]) ?></option><?php endforeach; ?>
          </select></label>
        <label>Nguồn số liệu
          <select name="nguon" id="sua-nguon" data-tinh>
            <option value="NHAP_TAY"><?= e(NHAN['NHAP_TAY']) ?></option>
            <option value="TONG_CON"><?= e(NHAN['TONG_CON']) ?></option>
            <?php if ($laDevCT): ?><option value="CONG_THUC"><?= e(NHAN['CONG_THUC']) ?></option><?php endif; ?>
          </select></label>
        <label>Cách đánh giá
          <select name="huong" id="sua-huong" data-tinh>
            <?php foreach (['CAO_TOT','THAP_TOT','DICH_CO_DINH'] as $v): ?>
              <option value="<?= $v ?>"><?= e(NHAN[$v]) ?></option><?php endforeach; ?>
          </select></label>
        <label>Phân bổ ra tháng
          <select name="phan_bo" id="sua-phanbo" data-tinh>
            <option value="THEO_NGAY"><?= e(NHAN['THEO_NGAY']) ?></option>
            <option value="KHONG_CHIA"><?= e(NHAN['KHONG_CHIA']) ?></option>
          </select></label>
        <label class="o-rong-2">Gộp số liệu vào chỉ tiêu chuẩn
          <select name="gop_vao" id="sua-gopvao" data-tinh>
            <option value="">— Không gộp (đứng riêng) —</option>
            <?php foreach ($dsChuan as $c): ?>
              <option value="<?= e($c['ma']) ?>"><?= e($c['ten']) ?> (<?= e($c['ma']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <small class="nhan-phu">Khoa đặt tên khác cho cùng khái niệm → chọn để số liệu cộng vào chỉ tiêu chuẩn trên dashboard/báo cáo</small>
        </label>
      </div>

      <?php if ($laDevCT): ?>
      <fieldset class="nhom-khoa" id="sua-khoi-ct" hidden style="margin-top:.75rem">
        <legend>Cấu hình công thức <small class="phu">(Tử ÷ Mẫu, ×100 nếu tỷ lệ %)</small></legend>
        <div class="luoi-truong">
          <label>Phép tính
            <select name="phep_tinh" id="sua-phep">
              <option value="TY_LE">Tỷ lệ % (A ÷ B × 100)</option>
              <option value="THUONG">Thương (A ÷ B)</option>
            </select></label>
          <label>Tử số (A)
            <select name="ct_tu" id="sua-cttu">
              <option value="">— chọn —</option>
              <?php foreach ($dsCTChon as $c): ?>
                <option value="<?= e($c['ma']) ?>"><?= e($c['ten']) ?> (<?= e($c['ma']) ?>)</option>
              <?php endforeach; ?>
            </select></label>
          <label>Mẫu số (B)
            <select name="ct_mau" id="sua-ctmau">
              <option value="">— chọn —</option>
              <?php foreach ($dsCTChon as $c): ?>
                <option value="<?= e($c['ma']) ?>"><?= e($c['ten']) ?> (<?= e($c['ma']) ?>)</option>
              <?php endforeach; ?>
            </select></label>
          <label class="o-rong" style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="nhan_so_ngay" id="sua-nhanngay" value="1" style="width:auto">
            <span>Nhân mẫu với số ngày trong tháng <small class="phu">(công suất)</small></span>
          </label>
        </div>
      </fieldset>
      <?php endif; ?>
      <div class="form-chan">
        <button class="nut nut-chinh" type="submit">Lưu thay đổi</button>
        <button type="button" class="nut nut-phu" data-dong>Đóng</button>
      </div>
    </form>
  </div>
 </div>
</div>
<?php endif; ?>

<script>
/* Thêm chỉ tiêu bằng AJAX: chèn thẳng dòng mới, KHÔNG tải lại cả trang
   (giữ nguyên các ô đang gõ dở, không giật, không mất vị trí cuộn). */
(function () {
  function chenDong(d) {
    var tbody = document.querySelector('table.bang-nhap tbody');
    if (!tbody) { location.reload(); return; }   // khoa đang trống, chưa có bảng → tải lại để hiện
    tbody.querySelectorAll('.vua-them').forEach(function (x) { x.classList.remove('vua-them'); });
    var cu = document.getElementById('dong-moi'); if (cu) { cu.removeAttribute('id'); }
    var tmp = document.createElement('tbody'); tmp.innerHTML = d.row.trim();
    var moi = Array.prototype.slice.call(tmp.children);
    var neo = d.chaId ? document.getElementById('them-con-' + d.chaId) : null;
    moi.forEach(function (r) { tbody.insertBefore(r, neo); });
    if (d.fcon) { document.getElementById('fcon-forms').insertAdjacentHTML('beforeend', d.fcon); }
    if (!d.chaId) {
      document.querySelectorAll('select[name="ct_cha"]').forEach(function (s) {
        var o = document.createElement('option'); o.value = d.id;
        o.textContent = '↳ nằm trong: ' + d.ten; s.appendChild(o);
      });
    }
    var dm = tbody.querySelector('.vua-them');
    if (dm) { dm.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
  }
  document.addEventListener('submit', function (e) {
    var f = e.target;
    // Dùng getAttribute vì form có <input name="id"> che mất thuộc tính f.id
    var fid = f.getAttribute('id') || '';
    if (fid === 'f-sua-ct') { luuSuaCT(e); return; }
    if (fid === 'gan-thu-vien') { ganThuVien(e); return; }
    if (fid !== 'them-ct' && fid.indexOf('fcon-') !== 0) { return; }
    e.preventDefault();
    var fd = new FormData(f); fd.append('ajax', '1');
    var cha = fid.indexOf('fcon-') === 0 ? fid.slice(5) : null;
    fetch(location.pathname + location.search, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { toast(d.loi || 'Không thêm được dòng.', 'canh-bao'); return; }
        if (d.reload) { location.reload(); return; }   // trùng mã → dùng lại (có thể thêm cả con)
        chenDong(d);
        if (cha) {
          var row = document.getElementById('them-con-' + cha);
          if (row) {
            row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
            var o1 = row.querySelector('input'); if (o1) { o1.focus(); }
          }
        } else {
          // Giữ popup mở để thêm tiếp; xóa ô, báo đã thêm, đưa con trỏ về ô nội dung
          var box = document.getElementById('modal-them-ct');
          if (box) {
            box.querySelectorAll('input[type=text]').forEach(function (i) { i.value = ''; });
            var sel = box.querySelector('select[name="ct_cha"]'); if (sel) { sel.value = ''; }
            var note = document.getElementById('them-ct-ket');
            if (note) { note.textContent = '✓ Đã thêm: ' + d.ten; note.hidden = false; }
            var o2 = document.getElementById('o-ct-ten'); if (o2) { o2.focus(); }
          }
        }
      })
      .catch(function () { f.submit(); });   // lỗi mạng → quay về cách cũ (tải lại)
  });
  // Mở popup thêm: đưa con trỏ vào ô nội dung, xóa dòng "đã thêm" cũ
  var fab = document.querySelector('.nut-noi');
  if (fab) {
    fab.addEventListener('click', function () {
      var note = document.getElementById('them-ct-ket'); if (note) { note.hidden = true; }
      setTimeout(function () { var o = document.getElementById('o-ct-ten'); if (o) { o.focus(); } }, 60);
    });
  }
})();

/* Kéo-thả đổi vị trí chỉ tiêu (như kéo thư mục). Kéo cha thì kéo theo cả cụm
   con; kéo con chỉ trong nhóm anh em cùng cha. */
(function () {
  var tbody = document.querySelector('table.bang-nhap tbody');
  var form = document.getElementById('f-sapxep');
  if (!tbody || !form) { return; }
  var keo = null, khoi = [];

  /* ---- Hoàn tác sắp xếp: lưu thứ tự TRƯỚC mỗi thao tác (nhiều cấp, sống qua reload) ---- */
  var KHOA_HT = 'sapxep_ht_' + location.pathname + location.search;
  function chupThuTu() {
    return Array.prototype.map.call(tbody.querySelectorAll('tr[data-id]'), function (r) { return r.dataset.id; });
  }
  function docHT() { try { return JSON.parse(sessionStorage.getItem(KHOA_HT) || '[]'); } catch (e) { return []; } }
  function luuHT(a) { try { sessionStorage.setItem(KHOA_HT, JSON.stringify(a)); } catch (e) {} }
  function capNhatNutHT() {
    var b = document.getElementById('nut-hoan-tac');
    if (b) { b.hidden = docHT().length === 0; }
  }
  function ghiHT() {                       // gọi TRƯỚC khi đổi chỗ
    var a = docHT(); a.push(chupThuTu()); if (a.length > 40) { a.shift(); }
    luuHT(a); capNhatNutHT();
  }
  window.hoanTacSapXep = function () {
    var a = docHT();
    if (!a.length) { if (window.toast) { toast('Không còn gì để hoàn tác.', 'canh-bao'); } return; }
    var truoc = a.pop(); luuHT(a);
    guiAjaxGiao({ viec: 'sap_xep', ids: truoc.join(',') })
      .then(function () { location.reload(); })
      .catch(function () { location.reload(); });
  };
  (function () {
    var b = document.getElementById('nut-hoan-tac');
    if (b) { b.addEventListener('click', window.hoanTacSapXep); }
    capNhatNutHT();
  })();

  /* Phím tắt Ctrl+Z (⌘Z trên Mac) = hoàn tác lần sắp xếp gần nhất.
     Bỏ qua khi đang gõ trong ô nhập hoặc đang mở popup (để Ctrl+Z gõ chữ như thường). */
  document.addEventListener('keydown', function (e) {
    var laZ = (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey &&
              (e.key === 'z' || e.key === 'Z');
    if (!laZ) { return; }
    var el = document.activeElement;
    var dangGo = el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);
    var modal = document.getElementById('modal-sua-ct');
    var modalMo = modal && !modal.hidden;
    if (dangGo || modalMo) { return; }
    if (docHT().length) { e.preventDefault(); window.hoanTacSapXep(); }
  });

  function laCon(tr) { return tr.classList.contains('dong-con'); }
  function khoiCua(tr) {
    var a = [tr];
    if (!laCon(tr)) {
      var n = tr.nextElementSibling;
      while (n && (laCon(n) || n.classList.contains('dong-them-con'))) {
        a.push(n); n = n.nextElementSibling;
      }
    }
    return a;
  }

  /* Hiệu ứng FLIP: chụp vị trí trước → đổi chỗ → trượt mượt về chỗ mới.
     Kèm nháy sáng khối vừa chuyển. Tôn trọng "giảm chuyển động". */
  var giamCD = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function flipChuyen(doiCho, khoiNhay) {
    if (giamCD) { doiCho(); nhayKhoi(khoiNhay); return; }
    var hang = Array.prototype.slice.call(tbody.children);
    var truoc = hang.map(function (r) { return r.getBoundingClientRect().top; });
    doiCho();
    hang.forEach(function (r, i) {
      var dy = truoc[i] - r.getBoundingClientRect().top;
      if (Math.abs(dy) < 1) { return; }
      r.style.transform = 'translateY(' + dy + 'px)';
      r.style.transition = 'transform 0s';
      r.getBoundingClientRect();               // ép reflow để mốc bắt đầu
      requestAnimationFrame(function () {
        r.style.transition = 'transform .32s cubic-bezier(.34,1.28,.5,1)';   // nảy nhẹ
        r.style.transform = '';
      });
      var xong = function () { r.style.transition = ''; r.style.transform = ''; r.removeEventListener('transitionend', xong); };
      r.addEventListener('transitionend', xong);
    });
    nhayKhoi(khoiNhay);
  }
  function nhayKhoi(khoi) {
    if (!khoi) { return; }
    khoi.forEach(function (r) {
      r.classList.remove('vua-chuyen'); void r.offsetWidth;   // reset để chạy lại animation
      r.classList.add('vua-chuyen');
      setTimeout(function () { r.classList.remove('vua-chuyen'); }, 820);
    });
  }
  function xoaVach() {
    tbody.querySelectorAll('.keo-tren,.keo-duoi').forEach(function (r) {
      r.classList.remove('keo-tren', 'keo-duoi');
    });
  }
  function hopLeTha(tr) {
    return laCon(keo) ? (laCon(tr) && tr.dataset.cha === keo.dataset.cha) : !laCon(tr);
  }

  /* ---- Tự cuộn trang khi kéo tới mép trên/dưới màn hình ---- */
  var autoY = 0, autoRAF = null;
  var MEP = 130, TOCDO = 42;   // vùng kích hoạt (px) và tốc độ cuộn tối đa (px/khung)
  function batAutoCuon() {
    if (autoRAF) { return; }
    (function lap() {
      if (!keo) { autoRAF = null; return; }
      var vh = window.innerHeight, d = 0, t;
      // Phân vùng tốc độ: càng sát mép (t→1) cuộn càng nhanh (đường cong bình phương).
      if (autoY < MEP)             { t = (MEP - autoY) / MEP; d = -TOCDO * t * t; }
      else if (autoY > vh - MEP)   { t = (autoY - (vh - MEP)) / MEP; d = TOCDO * t * t; }
      if (d) { window.scrollBy(0, Math.round(d)); }
      autoRAF = requestAnimationFrame(lap);
    })();
  }
  // Bắt vị trí con trỏ trên toàn trang (kể cả khi ra ngoài bảng) để cuộn mép.
  document.addEventListener('dragover', function (e) { if (keo) { autoY = e.clientY; } });

  tbody.addEventListener('dragstart', function (e) {
    var h = e.target.closest('.ct-keo');
    if (!h) { e.preventDefault(); return; }
    keo = h.closest('tr'); khoi = khoiCua(keo);
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', keo.dataset.id); } catch (x) {}
    setTimeout(function () { khoi.forEach(function (r) { r.classList.add('dang-keo'); }); }, 0);
    autoY = e.clientY; batAutoCuon();   // khởi động tự cuộn
  });
  tbody.addEventListener('dragend', function () {
    khoi.forEach(function (r) { r.classList.remove('dang-keo'); });
    xoaVach(); keo = null; khoi = [];   // keo=null -> vòng lặp tự dừng
  });
  tbody.addEventListener('dragover', function (e) {
    if (!keo) { return; }
    var tr = e.target.closest('tr[data-id]');
    if (!tr || khoi.indexOf(tr) >= 0 || !hopLeTha(tr)) { return; }
    e.preventDefault();
    var tren = e.clientY < tr.getBoundingClientRect().top + tr.offsetHeight / 2;
    xoaVach(); tr.classList.add(tren ? 'keo-tren' : 'keo-duoi');
  });
  tbody.addEventListener('drop', function (e) {
    if (!keo) { return; }
    var tr = e.target.closest('tr[data-id]');
    if (!tr || khoi.indexOf(tr) >= 0 || !hopLeTha(tr)) { return; }
    e.preventDefault();
    var tren = e.clientY < tr.getBoundingClientRect().top + tr.offsetHeight / 2;
    var moc = tren ? tr : (function () { var k = khoiCua(tr); return k[k.length - 1].nextElementSibling; })();
    ghiHT();                       // lưu thứ tự cũ để hoàn tác được
    xoaVach();
    flipChuyen(function () { khoi.forEach(function (r) { tbody.insertBefore(r, moc); }); }, khoi);
    luuThuTu();
  });

  /* Đánh lại số VỊ TRÍ (1,2,3…) trên data-thutu của từng dòng theo đúng thứ tự
     đang hiển thị — gốc đếm theo gốc, con đếm lại từ 1 trong mỗi cha. Gọi sau mỗi
     lần sắp lại để popup Sửa luôn hiện đúng vị trí hiện tại (không cần tải lại). */
  function capNhatViTri() {
    var demGoc = 0, demCon = {};
    Array.prototype.forEach.call(tbody.querySelectorAll('tr[data-id]'), function (r) {
      var cha = r.dataset.cha;
      if (!cha) { r.dataset.thutu = (++demGoc); }
      else { demCon[cha] = (demCon[cha] || 0) + 1; r.dataset.thutu = demCon[cha]; }
    });
  }
  capNhatViTri();   // đồng bộ ngay khi tải trang

  /* Lưu thứ tự mới bằng AJAX — KHÔNG tải lại (DOM đã sắp xếp sẵn) */
  function luuThuTu() {
    capNhatViTri();   // cập nhật số vị trí trên các dòng trước khi lưu
    var ids = Array.prototype.map.call(tbody.querySelectorAll('tr[data-id]'),
      function (r) { return r.dataset.id; });
    guiAjaxGiao({ viec: 'sap_xep', ids: ids.join(',') })
      .then(function (d) { if (!d.ok) { location.reload(); } })
      .catch(function () { location.reload(); });
  }

  /* Cùng cấp với dòng tham chiếu? (cha ↔ cha, con ↔ con cùng một cha) */
  function hopCap(a, ref) {
    return !!(a && a.dataset && a.dataset.id) &&
      (laCon(ref) ? (laCon(a) && a.dataset.cha === ref.dataset.cha) : !laCon(a));
  }
  /* Dòng cùng cấp gần nhất theo hướng dir (bỏ qua con/cháu và hàng "+ thêm con") */
  function anhEmKe(tuRow, ref, dir) {
    var n = tuRow;
    do { n = dir > 0 ? n.nextElementSibling : n.previousElementSibling; }
    while (n && !hopCap(n, ref));
    return n;
  }
  /* Nút ▲ ▼: đổi chỗ nguyên KHỐI (cha + toàn bộ con/cháu) với anh em kế bên.
     Chạy trên cả điện thoại (kéo-thả HTML5 không hoạt động khi cảm ứng). */
  window.doiCho = function (id, dir) {
    var tr = tbody.querySelector('tr[data-id="' + id + '"]');
    if (!tr) { return; }
    var block = khoiCua(tr);
    var anhEm = anhEmKe(dir > 0 ? block[block.length - 1] : block[0], tr, dir);
    if (!anhEm) { if (window.toast) { toast('Đã ở ' + (dir > 0 ? 'cuối' : 'đầu') + ' rồi.', 'canh-bao'); } return; }
    var blockAE = khoiCua(anhEm);
    var moc = dir > 0 ? blockAE[blockAE.length - 1].nextElementSibling : blockAE[0];
    ghiHT();                       // lưu thứ tự cũ để hoàn tác được
    flipChuyen(function () { block.forEach(function (r) { tbody.insertBefore(r, moc); }); }, block);
    luuThuTu();
  };

  /* Chuyển một dòng tới VỊ TRÍ cụ thể trong nhóm anh em (dùng khi gõ số ở popup Sửa).
     pos tính từ 1 (dòng đầu nhóm = 1). Đổi nguyên KHỐI như kéo-thả nên không mất
     số liệu đang gõ và không tải lại trang. Trả về true nếu có đổi chỗ. */
  window.datViTri = function (id, pos) {
    var tr = tbody.querySelector('tr[data-id="' + id + '"]');
    if (!tr) { return false; }
    // Các dòng đại diện của anh em cùng cấp (gốc↔gốc, con↔con cùng cha).
    var heads = Array.prototype.filter.call(
      tbody.querySelectorAll('tr[data-id]'),
      function (r) { return hopCap(r, tr); });
    var n = heads.length;
    pos = Math.max(1, Math.min(pos | 0, n));
    var cur = heads.indexOf(tr);
    if (cur < 0 || cur === pos - 1) { return false; }   // đã đúng chỗ, khỏi đổi
    var conLai = heads.slice(); conLai.splice(cur, 1);   // bỏ chính nó ra
    var block = khoiCua(tr);
    var moc;
    if (pos - 1 < conLai.length) {
      moc = khoiCua(conLai[pos - 1])[0];                 // chèn trước anh em ở vị trí đích
    } else {
      var kl = khoiCua(conLai[conLai.length - 1]);
      moc = kl[kl.length - 1].nextElementSibling;        // đưa xuống cuối nhóm
    }
    ghiHT();
    flipChuyen(function () { block.forEach(function (r) { tbody.insertBefore(r, moc); }); }, block);
    luuThuTu();
    return true;
  };
})();

/* Gửi thao tác (xóa / sắp xếp) bằng AJAX, không tải lại trang */
function guiAjaxGiao(data) {
  var fd = new FormData();
  Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
  fd.append('ajax', '1');
  var tok = document.querySelector('input[name="_csrf"]');
  if (tok) { fd.append('_csrf', tok.value); }
  return fetch(location.pathname + location.search, { method: 'POST', body: fd })
    .then(function (r) { return r.json(); });
}
/* Sửa cấu hình chỉ tiêu ngay trong bảng (popup) — không tải lại trang */
function suaCT(id) {
  var tr = document.querySelector('tr[data-id="' + id + '"]');
  if (!tr) { return; }
  document.getElementById('sua-id').value = id;
  var oTen = tr.querySelector('.o-sua-ten');
  var oChu = tr.querySelector('.ten-ct-chu');
  document.getElementById('sua-ten').value = oTen ? oTen.value : (oChu ? oChu.textContent.trim() : '');
  var oMoTa = document.getElementById('sua-mota');
  if (oMoTa) { oMoTa.value = tr.dataset.mota || ''; }
  var oDv = tr.querySelector('.o-sua-don-vi');
  document.getElementById('sua-donvi').value = oDv ? oDv.value : '';
  document.getElementById('sua-thutu').value = tr.dataset.thutu || '';
  var oChung = document.getElementById('sua-thutuchung');
  if (oChung) { oChung.value = tr.dataset.thutuchung || ''; }
  document.getElementById('sua-loai').value = tr.dataset.loai || 'DEM';
  document.getElementById('sua-nguon').value = tr.dataset.nguon || 'NHAP_TAY';
  document.getElementById('sua-huong').value = tr.dataset.huong || 'CAO_TOT';
  document.getElementById('sua-phanbo').value = tr.dataset.phanbo || 'THEO_NGAY';
  var gv = document.getElementById('sua-gopvao');
  if (gv) { gv.value = tr.dataset.gopvao || ''; }
  var phep = document.getElementById('sua-phep');
  if (phep) {
    phep.value = tr.dataset.pheptinh || 'TY_LE';
    document.getElementById('sua-cttu').value  = tr.dataset.cttu || '';
    document.getElementById('sua-ctmau').value = tr.dataset.ctmau || '';
    document.getElementById('sua-nhanngay').checked = tr.dataset.nhanngay === '1';
  }
  var ht = tr.dataset.hethong === '1';
  var oMa = document.getElementById('sua-ma');
  if (oMa) {
    oMa.value = tr.dataset.ma || ''; oMa.disabled = ht;   // mã hệ thống không đổi được
    if (window.kiemTraMaSua) { window.kiemTraMaSua(); }   // xóa/đặt lại dòng báo trạng thái
  }
  var kqTrung = document.getElementById('sua-trung-kq');
  if (kqTrung) { kqTrung.innerHTML = ''; kqTrung.className = 'trung-kq'; }   // dọn kết quả cũ
  document.getElementById('sua-ht').hidden = !ht;
  document.querySelectorAll('#f-sua-ct [data-tinh]').forEach(function (s) { s.disabled = ht; });
  veKhoiSuaCT();
  document.getElementById('modal-sua-ct').hidden = false;
}
/* Hiện khối cấu hình công thức trong popup khi nguồn = Tính theo công thức */
function veKhoiSuaCT() {
  var nguon = document.getElementById('sua-nguon');
  var khoi  = document.getElementById('sua-khoi-ct');
  if (!nguon || !khoi) { return; }
  var ct = nguon.value === 'CONG_THUC';
  khoi.hidden = !ct;
  // Loại giá trị & phân bổ do công thức tự quyết; khóa lại nếu không phải hệ thống.
  var ht = nguon.disabled;   // hệ thống đã khóa sẵn, đừng đụng vào
  if (!ht) {
    document.getElementById('sua-loai').disabled   = ct;
    document.getElementById('sua-phanbo').disabled = ct;
  }
}
(function () {
  var n = document.getElementById('sua-nguon');
  if (n) { n.addEventListener('change', veKhoiSuaCT); }
})();
/* Gán chỉ tiêu chuẩn từ thư viện cho khoa (nhiều mục) → tải lại bảng */
function ganThuVien(e) {
  e.preventDefault();
  var fd = new FormData(e.target); fd.append('ajax', '1');
  if (fd.getAll('tv[]').length === 0) { toast('Chưa chọn chỉ tiêu chuẩn nào.', 'canh-bao'); return; }
  fetch(location.pathname + location.search, { method: 'POST', body: fd })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok) { toast(d.loi || 'Không gán được.', 'canh-bao'); return; }
      location.reload();
    })
    .catch(function () { location.reload(); });
}
function luuSuaCT(e) {
  e.preventDefault();
  var id = document.getElementById('sua-id').value;
  var fd = new FormData(e.target); fd.append('ajax', '1');
  ['loai_gia_tri', 'nguon', 'huong', 'phan_bo'].forEach(function (n) {
    if (!fd.has(n)) { var el = document.querySelector('#f-sua-ct [name="' + n + '"]');
      if (el) { fd.append(n, el.value); } }
  });
  fetch(location.pathname + location.search, { method: 'POST', body: fd })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.ok) { toast(d.loi || 'Không lưu được.', 'canh-bao'); return; }
      var posMoi = parseInt(document.getElementById('sua-thutu').value, 10);
      var cu = document.querySelector('tr[data-id="' + id + '"]');
      var posCu = cu ? parseInt(cu.dataset.thutu, 10) : NaN;
      if (cu) {
        var snap = {};
        cu.querySelectorAll('input[name^="giao["],input[name^="nang_luc["],input[name^="nam_truoc["]')
          .forEach(function (i) { snap[i.name] = i.value; });
        var tmp = document.createElement('tbody'); tmp.innerHTML = d.row.trim();
        var moi = tmp.querySelector('tr');
        cu.replaceWith(moi);
        Object.keys(snap).forEach(function (n) {
          var el = moi.querySelector('[name="' + n + '"]'); if (el) { el.value = snap[n]; }
        });
        // Gõ số thứ tự khác vị trí cũ → chuyển dòng tới vị trí đó (giữ số liệu đang gõ).
        if (!isNaN(posMoi) && posMoi !== posCu && typeof window.datViTri === 'function') {
          window.datViTri(id, posMoi);   // tự cập nhật lại data-thutu các dòng
        }
      }
      document.getElementById('modal-sua-ct').hidden = true;
      if (window.toast) { toast(d.msg || 'Đã lưu chỉ tiêu.', 'ok'); }
    })
    .catch(function () { location.reload(); });
}
function goKhoiKhoa(id) {
  var tr = document.querySelector('tr[data-id="' + id + '"]');
  var oTen = tr && tr.querySelector('.o-sua-ten');
  var ten = oTen ? oTen.value
    : (tr && tr.querySelector('.ten-ct-chu') ? tr.querySelector('.ten-ct-chu').textContent.trim() : 'chỉ tiêu này');
  xacNhan('Gỡ "' + ten + '" (và nội dung con nếu có) khỏi khoa này?\n\n'
      + 'Chỉ tiêu vẫn còn trong Thư viện, các khoa khác không bị ảnh hưởng.',
      { ok: 'Gỡ khỏi khoa', loai: 'nguy' }).then(function (dongY) {
    if (!dongY) { return; }
    guiAjaxGiao({ viec: 'go_khoi_khoa', id: id }).then(function (d) {
      if (!d.ok) { toast(d.loi || 'Không gỡ được.', 'canh-bao'); return; }
      goBoDongVaCon(id);   // xóa dòng này + toàn bộ con/cháu trên giao diện
    }).catch(function () { location.reload(); });
  });
}
/* Xóa 1 dòng chỉ tiêu và mọi hậu duệ của nó khỏi bảng (đệ quy theo data-cha). */
function goBoDongVaCon(id) {
  document.querySelectorAll('tr[data-cha="' + id + '"]').forEach(function (x) {
    var cid = x.getAttribute('data-id');
    if (cid) { goBoDongVaCon(cid); }
  });
  var tc = document.getElementById('them-con-' + id);
  if (tc) { tc.remove(); }
  var tr = document.querySelector('tr[data-id="' + id + '"]');
  if (tr) { tr.remove(); }
}
function xoaCT(id) {
  xacNhan('Xóa chỉ tiêu này? Thao tác không hoàn tác được.',
      { ok: 'Xóa', loai: 'nguy' }).then(function (dongY) {
    if (!dongY) { return; }
    guiAjaxGiao({ viec: 'xoa_ct', id: id }).then(function (d) {
      if (!d.ok) { toast(d.loi || 'Không xóa được.', 'canh-bao'); return; }
      var tr = document.querySelector('tr[data-id="' + id + '"]');
      var tc = document.getElementById('them-con-' + id);
      if (tr) { tr.remove(); }
      if (tc) { tc.remove(); }
    }).catch(function () { location.reload(); });
  });
}
function hienNangCao() {
  var d = document.getElementById('dong-nang-cao');
  d.hidden = !d.hidden;
  if (!d.hidden) { d.querySelector('input').focus(); }
}
/* Hiện khối cấu hình công thức trong popup THÊM khi chọn nguồn "Tính theo công thức" */
(function () {
  var nguon = document.getElementById('them-nguon');
  var khoi  = document.getElementById('them-khoi-ct');
  if (!nguon || !khoi) { return; }
  var loai   = document.querySelector('[name="ct_loai"]');
  var phanBo = document.querySelector('[name="ct_phan_bo"]');
  nguon.addEventListener('change', function () {
    var ct = nguon.value === 'CONG_THUC';
    khoi.hidden = !ct;
    if (ct) { document.getElementById('dong-nang-cao').hidden = false; }
    if (loai)   { loai.disabled = ct; }
    if (phanBo) { phanBo.disabled = ct; }
  });
})();

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

<?php if ($duocThemCT || $duocSuaCT):
    // Dữ liệu cho combobox "gõ để tìm chỉ tiêu đã có / gõ mới" và cho ô Mã ở popup Sửa
    $tcCombo = tat_ca_chi_tieu();
    $dsCombo = [];
    foreach ($tcCombo as $c) {
        $chaTen = ($c['id_cha'] && isset($tcCombo[(int)$c['id_cha']]))
            ? $tcCombo[(int)$c['id_cha']]['ten'] : '';
        $dsCombo[] = ['i' => (int)$c['id'], 'm' => $c['ma'], 't' => $c['ten'],
                      'c' => $chaTen, 'd' => $c['don_vi']];
    } ?>
<script>
window.DS_CT = <?= json_encode($dsCombo, JSON_UNESCAPED_UNICODE) ?>;
(function () {
  function boDau(s){ return (s||'').normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/đ/g,'d').replace(/Đ/g,'D').toLowerCase(); }
  var listEl = null, kqHienTai = [], iChon = -1;
  function dongList(){ if (listEl){ listEl.remove(); listEl = null; } kqHienTai = []; iChon = -1; }
  function toSang(){
    if (!listEl) { return; }
    var mucs = listEl.querySelectorAll('.combo-muc');
    for (var i = 0; i < mucs.length; i++){ mucs[i].classList.toggle('sang', i === iChon); }
    if (iChon >= 0 && mucs[iChon]) { mucs[iChon].scrollIntoView({ block: 'nearest' }); }
  }
  function hiddenCua(o){
    var fId = o.getAttribute('form');
    if (!fId) { return null; }
    return document.querySelector('[name="ct_dung_lai"][form="' + fId + '"]')
        || (document.getElementById(fId) && document.getElementById(fId).querySelector('[name="ct_dung_lai"]'));
  }
  function datHidden(o, id){ var h = hiddenCua(o); if (h) { h.value = id || ''; } }
  function oDonVi(o){
    var fId = o.getAttribute('form');
    return fId ? document.querySelector('[name="ct_don_vi"][form="' + fId + '"]') : null;
  }

  function chon(o, c){
    o.value = c.t; o.dataset.dungLai = c.i; o.dataset.boquaTrung = ''; datHidden(o, c.i);
    var dv = oDonVi(o); if (dv) { dv.value = c.d || ''; }   // tự điền đơn vị
    dongList();
  }

  function hienList(o){
    dongList();
    var tu = boDau(o.value.trim());
    if (tu.length < 1) { return; }
    for (var i = 0; i < window.DS_CT.length && kqHienTai.length < 12; i++){
      var c = window.DS_CT[i];
      if (boDau(c.t).indexOf(tu) !== -1 || boDau(c.m).indexOf(tu) !== -1) { kqHienTai.push(c); }
    }
    if (!kqHienTai.length) { return; }
    listEl = document.createElement('div');
    listEl.className = 'combo-list';
    kqHienTai.forEach(function (c, idx){
      var it = document.createElement('div');
      it.className = 'combo-muc';
      var st = document.createElement('strong'); st.textContent = c.t;
      var em = document.createElement('em'); em.className = 'phu';
      em.textContent = ' (' + c.m + (c.c ? ' · dưới ' + c.c : '') + ')';
      it.appendChild(st); it.appendChild(em);
      it.addEventListener('mousedown', function (e){ e.preventDefault(); chon(o, c); });
      it.addEventListener('mousemove', function (){ iChon = idx; toSang(); });
      listEl.appendChild(it);
    });
    var r = o.getBoundingClientRect();
    listEl.style.left = (window.scrollX + r.left) + 'px';
    listEl.style.top  = (window.scrollY + r.bottom + 2) + 'px';
    listEl.style.width = r.width + 'px';
    document.body.appendChild(listEl);
  }

  document.addEventListener('input', function (e){
    var o = e.target.closest('.o-combo-ct'); if (!o) { return; }
    o.dataset.dungLai = ''; o.dataset.boquaTrung = ''; datHidden(o, '');   // gõ lại → bỏ chọn cũ
    hienList(o);
  });
  document.addEventListener('focusin', function (e){
    var o = e.target.closest('.o-combo-ct'); if (o && o.value.trim()) { hienList(o); }
  });
  /* Phím ↑ ↓ để chọn, Enter để dùng, Esc để đóng */
  document.addEventListener('keydown', function (e){
    var o = e.target.closest('.o-combo-ct'); if (!o) { return; }
    if (e.key === 'ArrowDown' && (!listEl || !kqHienTai.length)) { hienList(o); return; }
    if (!listEl || !kqHienTai.length) { return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); iChon = (iChon + 1) % kqHienTai.length; toSang(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); iChon = (iChon - 1 + kqHienTai.length) % kqHienTai.length; toSang(); }
    else if (e.key === 'Enter' && iChon >= 0) { e.preventDefault(); chon(o, kqHienTai[iChon]); }
    else if (e.key === 'Escape') { dongList(); }
  });
  document.addEventListener('click', function (e){
    if (!e.target.closest('.combo-list') && !e.target.closest('.o-combo-ct')) { dongList(); }
  });

  /* Cảnh báo khi TẠO MỚI mà tên trùng chỉ tiêu đã có (chưa chọn từ danh sách) */
  document.addEventListener('submit', function (e){
    var f = e.target;
    var fid = f.getAttribute('id') || '';   // <input name="id"> che mất f.id
    if (fid !== 'them-ct' && fid.indexOf('fcon-') !== 0) { return; }
    var o = document.querySelector('.o-combo-ct[form="' + fid + '"]');
    if (!o || o.dataset.dungLai || o.dataset.boquaTrung) { return; }
    var tu = boDau(o.value.trim()); if (!tu) { return; }
    var trung = window.DS_CT.find(function (c){ return boDau(c.t) === tu; });
    if (!trung) { return; }
    e.preventDefault();
    if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
    xacNhan('Đã có chỉ tiêu "' + trung.t + '" (mã ' + trung.m + ').\n\n'
        + 'Vẫn tạo mới riêng (sẽ có mã khác)? Hoặc Hủy rồi chọn từ danh sách gợi ý để dùng lại mã có sẵn.',
        { ok: 'Tạo mới riêng', huy: 'Để tôi chọn lại' }).then(function (dongY){
      if (dongY) { o.dataset.boquaTrung = '1'; (f.requestSubmit ? f.requestSubmit() : f.submit()); }
    });
  }, true);
})();

/* Ô "Mã chỉ tiêu" trong popup Sửa: gõ ra GỢI Ý mã đang có + báo ngay TRỐNG / TRÙNG. */
(function () {
  var oMa = document.getElementById('sua-ma');
  if (!oMa) { return; }
  function boDau(s){ return (s||'').normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/đ/g,'d').replace(/Đ/g,'D').toLowerCase(); }
  function chuanMa(s){ return (s||'').toUpperCase().replace(/[^A-Z0-9_]/g,''); }
  var tt = document.getElementById('sua-ma-tt');
  var list = null;
  function dongList(){ if (list){ list.remove(); list = null; } }
  function ctHienTai(){
    var id = parseInt((document.getElementById('sua-id')||{}).value, 10);
    return (window.DS_CT || []).find(function (c){ return c.i === id; }) || null;
  }
  /* Báo trạng thái: giữ nguyên / trống dùng được / trùng (là gộp) */
  window.kiemTraMaSua = function (){
    if (!tt) { return; }
    var v = chuanMa(oMa.value), cur = ctHienTai(), maCu = cur ? cur.m : '';
    if (v === '' || v === maCu) {
      tt.className = 'ma-tt'; tt.textContent = v === '' ? '' : 'Giữ nguyên mã hiện tại.'; return;
    }
    var idCur = cur ? cur.i : -1;
    var trung = (window.DS_CT || []).find(function (c){ return c.m === v && c.i !== idCur; });
    if (trung) {
      tt.className = 'ma-tt ma-tt-trung';
      tt.textContent = '⚠ Mã "' + v + '" đang thuộc "' + trung.t + '"'
        + (trung.c ? ' (dưới ' + trung.c + ')' : '') + '. Đây là GỘP — nên dùng Kiểm tra trùng lặp.';
    } else {
      tt.className = 'ma-tt ma-tt-trong';
      tt.textContent = '✓ Mã "' + v + '" chưa ai dùng — lưu được.';
    }
  };
  function hienGoiY(){
    dongList();
    var raw = (oMa.value || '').trim(); if (raw.length < 1 || !window.DS_CT) { return; }
    var tuMa = raw.toUpperCase(), tuTen = boDau(raw), idCur = (ctHienTai() || {}).i, kq = [];
    for (var i = 0; i < window.DS_CT.length && kq.length < 10; i++){
      var c = window.DS_CT[i];
      if (c.i === idCur) { continue; }
      if (c.m.toUpperCase().indexOf(tuMa) !== -1 || boDau(c.t).indexOf(tuTen) !== -1) { kq.push(c); }
    }
    if (!kq.length) { return; }
    list = document.createElement('div'); list.className = 'combo-list';
    kq.forEach(function (c){
      var it = document.createElement('div'); it.className = 'combo-muc';
      var st = document.createElement('strong'); st.textContent = c.m;
      var em = document.createElement('em'); em.className = 'phu';
      em.textContent = ' — ' + c.t + (c.c ? ' (dưới ' + c.c + ')' : '');
      it.appendChild(st); it.appendChild(em);
      it.addEventListener('mousedown', function (e){ e.preventDefault(); oMa.value = c.m; dongList(); window.kiemTraMaSua(); });
      list.appendChild(it);
    });
    var r = oMa.getBoundingClientRect();
    list.style.left = (window.scrollX + r.left) + 'px';
    list.style.top  = (window.scrollY + r.bottom + 2) + 'px';
    list.style.width = r.width + 'px';
    document.body.appendChild(list);
  }
  oMa.addEventListener('input', function (){ window.kiemTraMaSua(); hienGoiY(); });
  oMa.addEventListener('focus', function (){ if (oMa.value) { hienGoiY(); } });
  oMa.addEventListener('blur',  function (){ setTimeout(dongList, 150); });
})();

/* Nút "Chỉ tiêu này có bị trùng?" — kiểm tra NGAY trong popup (phạm vi 1 chỉ tiêu),
   không nhảy sang trang toàn bộ. */
(function () {
  var btn = document.getElementById('btn-kt-trung');
  var box = document.getElementById('sua-trung-kq');
  if (!btn || !box) { return; }
  function esc(s){ return (s || '').replace(/[&<>"]/g, function (c){
    return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' }[c]; }); }
  btn.addEventListener('click', function () {
    var id = (document.getElementById('sua-id') || {}).value;
    if (!id) { return; }
    box.className = 'trung-kq'; box.innerHTML = '<span class="phu">Đang kiểm tra…</span>';
    fetch('/giao-chi-tieu.php?ajax_trung=1&id=' + encodeURIComponent(id))
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { box.innerHTML = '<span class="phu">' + esc(d.loi || 'Lỗi') + '</span>'; return; }
        if (!d.items.length) {
          box.className = 'trung-kq trung-kq-ok';
          box.textContent = '✓ Không có chỉ tiêu nào trùng tên — mã này đứng riêng, ổn.';
          return;
        }
        var chac = d.items.filter(function (x) { return x.cung_cha; });
        var nghi = d.items.filter(function (x) { return !x.cung_cha; });
        var h = '';
        if (chac.length) {
          h += '<div class="trung-kq-tieu trung-kq-canh">⚠ Trùng thật (cùng nội dung lớn) — nên gộp lại:</div><ul>';
          chac.forEach(function (x) { h += '<li><code>' + esc(x.ma) + '</code> — ' + esc(x.ten) + '</li>'; });
          h += '</ul>';
        }
        if (nghi.length) {
          h += '<div class="trung-kq-tieu">Cùng tên nhưng khác nội dung lớn (thường khác nghĩa, cân nhắc):</div><ul>';
          nghi.forEach(function (x) { h += '<li><code>' + esc(x.ma) + '</code> — ' + esc(x.ten)
            + ' <span class="phu">(dưới ' + esc(x.cha || 'gốc') + ')</span></li>'; });
          h += '</ul>';
        }
        h += '<a href="/gop-trung-lap.php" target="_blank" class="lien-ktl">Sang trang gộp để xử lý ↗</a>';
        box.className = 'trung-kq'; box.innerHTML = h;
      })
      .catch(function () { box.innerHTML = '<span class="phu">Lỗi kết nối, thử lại.</span>'; });
  });
})();
</script>
<?php endif; ?>
<?php dong_trang();

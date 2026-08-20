<?php
/**
 * Kiểm tra & gộp chỉ tiêu trùng lặp.
 *
 * Cùng một nội dung nhưng khi giao ở nhiều khoa lại sinh ra nhiều mã khác nhau
 * → dashboard/báo cáo gom theo mã nên đếm rời, sai tổng. Trang này tìm các nhóm
 * trùng và gộp chúng về một mã duy nhất (dời hết số liệu, cộng dồn nếu trùng).
 * Chỉ người phát triển (được xóa chỉ tiêu) mới dùng được.
 */
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/danh_muc.php';

$toi = bat_buoc_quyen('chitieu.xoa');   // gộp có xóa chỉ tiêu → chỉ DEV

if (la_post()) {
    kiem_tra_csrf();
    if (post('viec') === 'gop') {
        $laAjax = post('ajax') === '1';
        // Gộp xong trả JSON (không tải lại trang) khi gọi bằng AJAX; nếu không thì
        // báo flash + chuyển hướng như thường (dự phòng khi tắt JS).
        $traLoi = function (bool $ok, string $tin) use ($laAjax) {
            if ($laAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => $ok, ($ok ? 'msg' : 'loi') => $tin], JSON_UNESCAPED_UNICODE);
                exit;
            }
            nhan_tin($ok ? 'ok' : 'loi', $tin);
            chuyen_huong('/gop-trung-lap.php');
        };

        $giuId  = (int)post('giu');
        $thanhVien = array_map('intval', $_POST['thanh_vien'] ?? []);

        // An toàn: mọi thành viên phải TRÙNG TÊN (rút gọn) với bản giữ, tránh gộp
        // nhầm hai chỉ tiêu không liên quan. Cho phép khác nội dung lớn (khu nghi ngờ).
        $giu = q1('SELECT * FROM chi_tieu WHERE id = ?', [$giuId]);
        if (!$giu) {
            $traLoi(false, 'Không tìm thấy chỉ tiêu giữ lại.');
        }
        $khopGiu = chuan_hoa_khop($giu['ten']);
        foreach ($thanhVien as $tv) {
            $c = q1('SELECT * FROM chi_tieu WHERE id = ?', [$tv]);
            if (!$c || chuan_hoa_khop($c['ten']) !== $khopGiu) {
                $traLoi(false, 'Danh sách gộp không hợp lệ (khác tên chỉ tiêu). Vui lòng tải lại trang.');
            }
        }

        // Mã đích (tùy chọn): gộp xong đổi luôn mã giữ về mã cho gọn/đúng quy ước.
        $maMoi = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string)post('ma_moi'))));
        $doiMa = $maMoi !== '' && $maMoi !== $giu['ma'];
        if ($doiMa) {
            if (la_he_thong($giu['ma'])) {
                $traLoi(false, 'Mã giữ lại là chỉ tiêu hệ thống — không đổi mã được.');
            }
            // Các mã trong nhóm sắp bị gộp/xóa nên không tính là trùng
            $inTV = implode(',', array_fill(0, count($thanhVien), '?'));
            if (qVal("SELECT 1 FROM chi_tieu WHERE ma = ? AND id NOT IN ($inTV)",
                    array_merge([$maMoi], $thanhVien))) {
                $traLoi(false, "Mã \"$maMoi\" đã thuộc một chỉ tiêu khác ngoài nhóm này. Chọn mã khác.");
            }
        }

        $boIds = array_values(array_filter($thanhVien, fn($x) => $x !== $giuId));
        $kq = gop_chi_tieu($giuId, $boIds);
        if (!empty($kq['loi'])) {
            $traLoi(false, $kq['loi']);
        }

        // Đổi mã giữ (nếu có) + dời tham chiếu theo mã (gộp vào / công thức)
        $maCuoi = $giu['ma'];
        if ($doiMa) {
            q('UPDATE chi_tieu SET ma = ? WHERE id = ?', [$maMoi, $giuId]);
            q('UPDATE chi_tieu SET gop_vao = ? WHERE gop_vao = ?', [$maMoi, $giu['ma']]);
            q('UPDATE chi_tieu SET ct_tu = ? WHERE ct_tu = ?', [$maMoi, $giu['ma']]);
            q('UPDATE chi_tieu SET ct_mau = ? WHERE ct_mau = ?', [$maMoi, $giu['ma']]);
            ghi_nhat_ky('DOI_MA_CHI_TIEU', $giu['ma'], '→ ' . $maMoi);
            $maCuoi = $maMoi;
        }

        // Đổi nội dung (tên hiển thị) của mã giữ, nếu có sửa
        $tenMoi = trim((string)post('ten_moi'));
        $doiTen = $tenMoi !== '' && $tenMoi !== $giu['ten'];
        if ($doiTen) {
            q('UPDATE chi_tieu SET ten = ? WHERE id = ?', [$tenMoi, $giuId]);
            ghi_nhat_ky('SUA_CHI_TIEU', $maCuoi, $tenMoi);
        }

        ghi_nhat_ky('GOP_CHI_TIEU', $maCuoi,
            'Gộp ' . $kq['xoa'] . ' mã · dời ' . $kq['di_chuyen']
            . ' · cộng dồn ' . $kq['cong_gop']);
        $tenCuoi = $doiTen ? $tenMoi : $giu['ten'];
        $traLoi(true, "Đã gộp {$kq['xoa']} chỉ tiêu trùng vào \"{$tenCuoi}\" (mã {$maCuoi}). "
            . ($doiMa ? "Đổi mã thành {$maMoi}. " : '')
            . ($doiTen ? "Đổi nội dung thành \"{$tenMoi}\". " : '')
            . "Dời {$kq['di_chuyen']} dòng số liệu, cộng dồn {$kq['cong_gop']} dòng trùng, "
            . "gộp {$kq['khoa']} khoa áp dụng"
            . ($kq['con'] ? ", chuyển {$kq['con']} nội dung con" : '') . '.');
    }
}

/* ---------------- Hiển thị ---------------- */
$nhom = nhom_trung_lap();
$chac = array_values(array_filter($nhom, fn($g) => $g['loai'] === 'chac'));
$nghi = array_values(array_filter($nhom, fn($g) => $g['loai'] === 'nghi'));

/** Thứ tự ưu tiên chọn làm bản GIỮ: là chuẩn → nhiều dữ liệu → nhiều khoa → mã cũ (id nhỏ). */
function _uu_tien_giu(array $a, array $b): int
{
    $ca = (int)($a['row']['la_chuan'] ?? 1); $cb = (int)($b['row']['la_chuan'] ?? 1);
    if ($ca !== $cb)                 { return $cb <=> $ca; }
    if ($a['so_dl'] !== $b['so_dl']) { return $b['so_dl'] <=> $a['so_dl']; }
    if ($a['so_khoa'] !== $b['so_khoa']) { return $b['so_khoa'] <=> $a['so_khoa']; }
    return $a['id'] <=> $b['id'];
}

/** Xuất một thẻ nhóm trùng (form gộp). $loai = 'chac' | 'nghi'. */
function xuat_nhom_trung(array $g, int $stt): void
{
    $nghiNgo = $g['loai'] === 'nghi';
    $ms = $g['members'];
    usort($ms, '_uu_tien_giu');
    $giuMacDinh = $ms[0]['id'];
    $xn = 'Gộp ' . (count($ms) - 1) . ' mã trùng vào mã đã chọn?&#10;'
        . ($nghiNgo
            ? '⚠ Các mã này nằm ở NỘI DUNG LỚN KHÁC NHAU — chỉ gộp nếu chắc chắn là cùng một chỉ tiêu.&#10;'
            : '')
        . 'Số liệu sẽ dồn về một mã, không tự hoàn tác được.';
    ?>
    <form method="post" class="the-trung <?= $nghiNgo ? 'the-nghi' : '' ?>" data-hoi="<?= $xn ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="viec" value="gop">
      <div class="trung-dau">
        <strong>Nhóm <?= $stt ?>:</strong>
        “<?= e($ms[0]['row']['ten']) ?>”
        <?php if (!$nghiNgo && $g['ten_cha']): ?>
          <span class="phu">— trong <em><?= e($g['ten_cha']) ?></em></span>
        <?php endif; ?>
        <span class="the the-nho <?= $nghiNgo ? 'the-do' : 'the-canh' ?>"><?= count($ms) ?> mã trùng</span>
      </div>
      <?php if ($nghiNgo): ?>
        <p class="canh-nghi">⚠ Các mã dưới đây <strong>trùng tên nhưng nằm ở nội dung lớn khác nhau</strong>
          (xem cột <em>Nội dung lớn</em>). Thường đây là <strong>khác nghĩa</strong> — ví dụ “Bảo hiểm y tế”
          trong <em>lượt khám</em> khác trong <em>nội trú</em>. Chỉ gộp khi thật sự là một.</p>
      <?php endif; ?>
      <div class="cuon-ngang">
      <table class="bang bang-mot-dong bang-trung">
        <thead><tr>
          <th>Giữ</th><th>Mã</th><th>Nội dung (nguyên văn)</th><th>Nội dung lớn</th><th>Đơn vị</th><th>Loại</th>
          <th>Thư viện</th><th>Khoa</th><th>Dòng dữ liệu</th><th>Trạng thái</th>
        </tr></thead>
        <tbody>
        <?php foreach ($ms as $m): $r = $m['row']; $id = $m['id']; ?>
          <tr>
            <td style="text-align:center">
              <input type="radio" name="giu" value="<?= $id ?>"
                     data-ma="<?= e($r['ma']) ?>" data-ten="<?= e($r['ten']) ?>"
                     <?= $id === $giuMacDinh ? 'checked' : '' ?> required>
              <input type="hidden" name="thanh_vien[]" value="<?= $id ?>">
            </td>
            <td><code><?= e($r['ma']) ?></code>
              <?= $id === $giuMacDinh ? '<span class="the the-nho" title="Gợi ý giữ lại">💡</span>' : '' ?>
            </td>
            <td><?= e($r['ten']) ?></td>
            <td class="nho"><?= $m['ten_cha'] !== ''
                    ? e($m['ten_cha']) : '<span class="phu">(gốc)</span>' ?></td>
            <td class="nho"><?= e($r['don_vi']) ?></td>
            <td class="nho"><?= e(NHAN[$r['loai_gia_tri']] ?? $r['loai_gia_tri']) ?></td>
            <td class="nho"><?= (int)($r['la_chuan'] ?? 1) === 1
                    ? '<span class="the the-nho the-chuan">Chuẩn</span>'
                    : '<span class="the the-nho the-rieng">Riêng</span>' ?></td>
            <td class="nho"><?= $m['so_khoa'] ?: '<span class="phu">0</span>' ?></td>
            <td class="nho"><?= $m['so_dl']
                    ? '<strong>' . $m['so_dl'] . '</strong>'
                    : '<span class="phu">0</span>' ?></td>
            <td class="nho"><?= (int)$r['hoat_dong']
                    ? '<span class="trang-thai bat">Đang dùng</span>'
                    : '<span class="trang-thai tat">Ngừng</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <div class="trung-chan">
        <label class="o-ten-cuoi">Nội dung sau khi gộp
          <input type="text" name="ten_moi" class="sua-ten-gop" autocomplete="off"
                 value="<?= e($ms[0]['row']['ten']) ?>">
        </label>
        <label class="o-ma-cuoi">Mã sau khi gộp
          <input type="text" name="ma_moi" class="sua-ma-gop" autocomplete="off"
                 spellcheck="false" style="text-transform:uppercase"
                 value="<?= e($ms[0]['row']['ma']) ?>">
        </label>
        <button type="submit" class="nut <?= $nghiNgo ? 'nut-nguy' : 'nut-canh' ?>">Gộp về mã này</button>
        <span class="phu">Chọn ô “Giữ”, sửa <em>Nội dung / Mã</em> nếu muốn, rồi bấm gộp.</span>
      </div>
    </form>
    <?php
}

mo_trang('Kiểm tra trùng lặp');
?>
<p class="duong-dan"><a href="/danh-muc-chi-tieu.php">Thư viện chỉ tiêu</a> › Kiểm tra trùng lặp</p>
<h1>Kiểm tra & gộp chỉ tiêu trùng lặp</h1>

<div class="tb tb-tin">
  Những chỉ tiêu <strong>cùng nội dung nhưng bị tách thành nhiều mã</strong> sẽ làm dashboard
  và báo cáo cộng thiếu (mỗi mã một tổng riêng). Chọn <strong>một mã để giữ</strong> rồi gộp —
  toàn bộ số liệu, kế hoạch, khoa áp dụng của các mã còn lại sẽ dồn về mã giữ, trùng năm-tháng-khoa
  thì <strong>cộng dồn</strong>. Số liệu không mất; thao tác không tự hoàn tác nên nên
  <a href="/sao-luu.php?tai=sql">tải bản sao lưu</a> trước cho chắc.
</div>

<div class="tb tb-ok" id="trong-sach" style="margin-top:1rem<?= $nhom ? ';display:none' : '' ?>">
  ✔ Không phát hiện chỉ tiêu trùng lặp nào. Thư viện đang gọn gàng.
</div>

<?php if ($chac): ?>
<section class="khu-trung" data-loai="chac">
  <h2 class="tieu-de-khu">Trùng chắc chắn — cùng nội dung lớn
    <span class="the the-nho the-canh"><span class="dem-nhom"><?= count($chac) ?></span> nhóm</span></h2>
  <p class="phu">Các mã cùng tên và cùng chỗ, chỉ khác mã — gộp an toàn.
     Mã <span class="the the-nho">💡</span> là gợi ý nên giữ (nhiều dữ liệu / là chuẩn).</p>
  <?php foreach ($chac as $i => $g) { xuat_nhom_trung($g, $i + 1); } ?>
</section>
<?php endif; ?>

<?php if ($nghi): ?>
<section class="khu-trung" data-loai="nghi">
  <h2 class="tieu-de-khu">Nghi ngờ trùng — khác nội dung lớn
    <span class="the the-nho the-do"><span class="dem-nhom"><?= count($nghi) ?></span> nhóm</span></h2>
  <div class="tb tb-canh">
    ⚠ Các nhóm dưới đây <strong>trùng tên nhưng nằm ở những nội dung lớn khác nhau</strong>.
    Phần lớn là <strong>khác nghĩa</strong> (BHYT theo lượt khám ≠ theo nội trú; Loại I phẫu thuật ≠ thủ thuật)
    và <strong>KHÔNG nên gộp</strong>. Chỉ gộp nhóm nào anh chắc chắn là cùng một chỉ tiêu bị đặt nhầm chỗ.
  </div>
  <?php foreach ($nghi as $i => $g) { xuat_nhom_trung($g, $i + 1); } ?>
</section>
<?php endif; ?>

<script>
<?php
  $tcMa = tat_ca_chi_tieu(); $dsMa = [];
  foreach ($tcMa as $c) {
      $chaTen = ($c['id_cha'] && isset($tcMa[(int)$c['id_cha']])) ? $tcMa[(int)$c['id_cha']]['ten'] : '';
      $dsMa[] = ['m' => $c['ma'], 't' => $c['ten'], 'c' => $chaTen];
  }
?>
window.DS_MA = <?= json_encode($dsMa, JSON_UNESCAPED_UNICODE) ?>;

/* Gõ ở ô "Mã sau khi gộp" hoặc "Nội dung sau khi gộp" → gợi ý các mã / nội dung ĐANG CÓ. */
(function () {
  function boDau(s){ return (s||'').normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/đ/g,'d').replace(/Đ/g,'D').toLowerCase(); }
  var list = null;
  function dongList(){ if (list){ list.remove(); list = null; } }

  document.addEventListener('input', function (e) {
    var oMa = e.target.closest('.sua-ma-gop');
    var oTen = e.target.closest('.sua-ten-gop');
    var o = oMa || oTen; if (!o) { return; }
    dongList();
    var raw = (o.value || '').trim(); if (raw.length < 1) { return; }
    var tuMa = raw.toUpperCase(), tuTen = boDau(raw), kq = [];
    for (var i = 0; i < window.DS_MA.length && kq.length < 12; i++) {
      var c = window.DS_MA[i];
      var khop = oMa ? c.m.toUpperCase().indexOf(tuMa) !== -1 : boDau(c.t).indexOf(tuTen) !== -1;
      if (khop) { kq.push(c); }
    }
    if (!kq.length) { return; }
    list = document.createElement('div'); list.className = 'combo-list';
    kq.forEach(function (c) {
      var it = document.createElement('div'); it.className = 'combo-muc';
      var st = document.createElement('strong'); st.textContent = oMa ? c.m : c.t;
      var em = document.createElement('em'); em.className = 'phu';
      em.textContent = oMa ? (' — ' + c.t + (c.c ? ' (dưới ' + c.c + ')' : ''))
                           : ('  [' + c.m + ']' + (c.c ? ' · dưới ' + c.c : ''));
      it.appendChild(st); it.appendChild(em);
      it.addEventListener('mousedown', function (ev) {
        ev.preventDefault(); o.value = oMa ? c.m : c.t; o.dataset.suaTay = '1'; dongList();
      });
      list.appendChild(it);
    });
    var r = o.getBoundingClientRect();
    list.style.left = (window.scrollX + r.left) + 'px';
    list.style.top  = (window.scrollY + r.bottom + 2) + 'px';
    list.style.width = Math.max(r.width, 260) + 'px';
    document.body.appendChild(list);
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.combo-list') && !e.target.closest('.sua-ma-gop') && !e.target.closest('.sua-ten-gop')) { dongList(); }
  });
})();

/* Gộp bằng AJAX: hỏi xác nhận → gửi ngầm → xóa thẻ nhóm + toast, KHÔNG tải lại trang. */
(function () {
  /* Đổi ô "Giữ" thì ô Nội dung + Mã "sau khi gộp" nhảy theo — trừ khi người dùng đã tự sửa. */
  document.addEventListener('change', function (e) {
    var rad = e.target.closest('input[name="giu"]');
    if (!rad || !rad.checked) { return; }
    var f = rad.closest('form.the-trung'); if (!f) { return; }
    var oMa = f.querySelector('.sua-ma-gop');
    if (oMa && !oMa.dataset.suaTay) { oMa.value = rad.dataset.ma || ''; }
    var oTen = f.querySelector('.sua-ten-gop');
    if (oTen && !oTen.dataset.suaTay) { oTen.value = rad.dataset.ten || ''; }
  });
  /* Đánh dấu khi người dùng tự gõ → thôi không nhảy theo ô Giữ nữa */
  document.addEventListener('input', function (e) {
    var o = e.target.closest('.sua-ma-gop, .sua-ten-gop');
    if (o) { o.dataset.suaTay = '1'; }
  });

  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form.the-trung');
    if (!f) { return; }
    e.preventDefault();
    var nghi = f.classList.contains('the-nghi');
    window.xacNhan(f.dataset.hoi, { ok: 'Gộp', huy: 'Hủy', loai: 'nguy' }).then(function (dongY) {
      if (!dongY) { return; }
      var nut = f.querySelector('button[type="submit"]');
      if (nut) { nut.disabled = true; nut.textContent = 'Đang gộp…'; }
      var fd = new FormData(f);
      fd.append('ajax', '1');
      fetch(location.pathname, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) {
            toast(d.loi || 'Không gộp được.', 'canh-bao');
            if (nut) { nut.disabled = false; nut.textContent = 'Gộp nhóm này về mã đã chọn'; }
            return;
          }
          toast(d.msg || 'Đã gộp xong.', 'ok');
          xoaThe(f);
        })
        .catch(function () {
          toast('Lỗi kết nối, thử lại.', 'canh-bao');
          if (nut) { nut.disabled = false; nut.textContent = 'Gộp nhóm này về mã đã chọn'; }
        });
    });
  });

  /* Bỏ thẻ vừa gộp, cập nhật số đếm; hết nhóm trong khu thì ẩn khu; hết sạch thì báo gọn gàng. */
  function xoaThe(f) {
    var khu = f.closest('.khu-trung');
    f.remove();
    if (khu) {
      var conLai = khu.querySelectorAll('form.the-trung').length;
      var dem = khu.querySelector('.dem-nhom');
      if (dem) { dem.textContent = conLai; }
      if (conLai === 0) { khu.remove(); }
    }
    if (!document.querySelector('form.the-trung')) {
      var ts = document.getElementById('trong-sach');
      if (ts) { ts.style.display = ''; }
    }
  }
})();
</script>
<?php dong_trang();

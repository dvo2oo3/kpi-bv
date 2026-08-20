<?php
/**
 * Xuất báo cáo cả viện thành một workbook nhiều sheet:
 *   - mỗi khoa một sheet + một sheet TOÀN VIỆN
 *   - $loai = 'tong_quat' (chỉ nội dung lớn) | 'day_du' (có nội dung nhỏ + 12 cột tháng)
 *
 * Định dạng SpreadsheetML (.xls mở bằng Excel), tô màu cột So KH(%).
 */
function xuat_bao_cao_bo(int $nam, string $ky, string $loai, string $goc, array $dsKhoa): void
{
    $dayDu    = ($loai === 'day_du');
    $cacThang = cac_thang_cua_ky($ky);
    $tenFile  = 'bao-cao-' . ($dayDu ? 'day-du' : 'tong-quat') . "-{$ky}-{$nam}.xls";

    // Gom các sheet: từng khoa rồi tới toàn viện
    $sheets = [];
    foreach ($dsKhoa as $k) {
        $sheets[] = ['ten' => $k['ma'], 'tenDay' => chu_hoa($k['ten']),
                     'id' => (int)$k['id'],
                     'bang' => bang_theo_khoa($nam, $cacThang, (int)$k['id'], $goc)];
    }
    $sheets[] = ['ten' => 'TOAN VIEN', 'tenDay' => 'TOÀN VIỆN', 'id' => null,
                 'bang' => bang_toan_vien($nam, $cacThang, $goc)];

    $soCot = $dayDu ? 19 : 7;   // TT,ND,ĐVT,KHnăm,TH,%KH (+12 tháng) + Đánh giá

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $tenFile . '"');
    header('Cache-Control: max-age=0');

    $x = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    // Viền mảnh cho mọi ô dữ liệu
    $vien = '<Borders>'
        . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>'
        . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>'
        . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>'
        . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>'
        . '</Borders>';

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    ?>
<Styles>
  <Style ss:ID="tieude"><Font ss:Bold="1" ss:Size="14" ss:Color="#0F766E"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>
  <Style ss:ID="tieude2"><Font ss:Bold="1" ss:Size="11"/>
    <Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="phu"><Font ss:Italic="1" ss:Size="9" ss:Color="#64748B"/>
    <Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="dau"><Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="10"/>
    <Interior ss:Color="#0F766E" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><?= $vien ?></Style>
  <Style ss:ID="tt"><Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
  <Style ss:ID="cha"><Font ss:Bold="1"/><?= $vien ?></Style>
  <Style ss:ID="con"><Alignment ss:Indent="2"/><Font ss:Color="#475569"/><?= $vien ?></Style>
  <Style ss:ID="con1"><Alignment ss:Indent="2"/><Font ss:Color="#475569"/><?= $vien ?></Style>
  <Style ss:ID="con2"><Alignment ss:Indent="4"/><Font ss:Color="#475569"/><?= $vien ?></Style>
  <Style ss:ID="con3"><Alignment ss:Indent="6"/><Font ss:Color="#475569"/><?= $vien ?></Style>
  <Style ss:ID="con4"><Alignment ss:Indent="8"/><Font ss:Color="#475569"/><?= $vien ?></Style>
  <Style ss:ID="dv"><Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
  <Style ss:ID="so"><NumberFormat ss:Format="#,##0"/>
    <Alignment ss:Horizontal="Right"/><?= $vien ?></Style>
  <Style ss:ID="so1"><NumberFormat ss:Format="#,##0.0"/>
    <Alignment ss:Horizontal="Right"/><?= $vien ?></Style>
  <Style ss:ID="ptN"><NumberFormat ss:Format="0.0&quot;%&quot;"/>
    <Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
  <Style ss:ID="ptDat"><NumberFormat ss:Format="0.0&quot;%&quot;"/>
    <Font ss:Bold="1" ss:Color="#166534"/><Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
  <Style ss:ID="ptCanh"><NumberFormat ss:Format="0.0&quot;%&quot;"/>
    <Font ss:Bold="1" ss:Color="#92400E"/><Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
  <Style ss:ID="ptKem"><NumberFormat ss:Format="0.0&quot;%&quot;"/>
    <Font ss:Bold="1" ss:Color="#991B1B"/><Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
  <Style ss:ID="ptVuot"><NumberFormat ss:Format="0.0&quot;%&quot;"/>
    <Font ss:Bold="1" ss:Color="#3730A3"/><Interior ss:Color="#EEF2FF" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center"/><?= $vien ?></Style>
  <Style ss:ID="dgia"><Font ss:Size="9" ss:Color="#475569"/><?= $vien ?></Style>
</Styles>
<?php
    $ptStyle = ['dat' => 'ptDat', 'canh_bao' => 'ptCanh', 'khong_dat' => 'ptKem',
                'vuot' => 'ptVuot', 'na' => 'ptN'];

    foreach ($sheets as $sh):
        // Bản tổng quát chỉ giữ nội dung lớn
        $rows = $dayDu ? $sh['bang']
                       : array_values(array_filter($sh['bang'], fn($d) => $d['cap'] === 0));
?>
<Worksheet ss:Name="<?= $x($sh['ten']) ?>">
<Table>
  <Column ss:Width="34"/><Column ss:Width="300"/><Column ss:Width="50"/>
  <Column ss:Width="72"/><Column ss:Width="72"/><Column ss:Width="60"/>
  <?php if ($dayDu): for ($i = 0; $i < 12; $i++): ?><Column ss:Width="46"/><?php endfor; endif; ?>
  <Column ss:Width="150"/>
  <Row ss:Height="20"><Cell ss:StyleID="tieude" ss:MergeAcross="<?= $soCot - 1 ?>">
    <Data ss:Type="String"><?= $x(TEN_DON_VI) ?></Data></Cell></Row>
  <Row><Cell ss:StyleID="tieude2" ss:MergeAcross="<?= $soCot - 1 ?>">
    <Data ss:Type="String">BÁO CÁO THỰC HIỆN CHỈ TIÊU — <?= $x($sh['tenDay']) ?></Data></Cell></Row>
  <Row><Cell ss:StyleID="phu" ss:MergeAcross="<?= $soCot - 1 ?>">
    <Data ss:Type="String"><?= $x(ten_ky($ky)) ?> năm <?= $nam ?> · mốc <?= $goc === 'giao' ? 'chỉ tiêu giao' : 'năng lực' ?> · <?= $dayDu ? 'bản đầy đủ' : 'bản tổng quát' ?> · kết xuất <?= date('d/m/Y H:i') ?></Data></Cell></Row>
  <Row/>
  <Row ss:Height="30">
    <Cell ss:StyleID="dau"><Data ss:Type="String">TT</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Nội dung</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">ĐVT</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">KH năm</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Thực hiện</Data></Cell>
    <Cell ss:StyleID="dau"><Data ss:Type="String">So KH</Data></Cell>
    <?php if ($dayDu): for ($t = 1; $t <= 12; $t++): ?>
      <Cell ss:StyleID="dau"><Data ss:Type="String">T<?= $t ?></Data></Cell>
    <?php endfor; endif; ?>
    <Cell ss:StyleID="dau"><Data ss:Type="String">Đánh giá</Data></Cell>
  </Row>
<?php
        $stt = 0;
        foreach ($rows as $d):
            $le  = in_array($d['ct']['loai_gia_tri'], ['TY_LE', 'TRUNG_BINH'], true);
            $kSo = $le ? 'so1' : 'so';
            $laCon = $d['cap'] !== 0;
            if (!$laCon) { $stt++; }
            $psid = $ptStyle[$d['kpi']['danh_gia']] ?? 'ptN';
?>
  <Row>
    <Cell ss:StyleID="tt"><Data ss:Type="String"><?= $laCon ? '' : $stt ?></Data></Cell>
    <Cell ss:StyleID="<?= $laCon ? 'con' . min(4, (int)$d['cap']) : 'cha' ?>">
      <Data ss:Type="String"><?= $x($d['ct']['ten']) ?></Data></Cell>
    <Cell ss:StyleID="dv"><Data ss:Type="String"><?= $x($d['ct']['don_vi']) ?></Data></Cell>
    <?php if ($d['chi_tieu_nam'] !== null): ?>
      <Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($d['chi_tieu_nam'], 2) ?></Data></Cell>
    <?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?>
    <?php if ($d['thuc_hien'] !== null): ?>
      <Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($d['thuc_hien'], 2) ?></Data></Cell>
    <?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?>
    <?php if ($d['pt_nam'] !== null): ?>
      <Cell ss:StyleID="<?= $psid ?>"><Data ss:Type="Number"><?= round($d['pt_nam'], 2) ?></Data></Cell>
    <?php else: ?><Cell ss:StyleID="ptN"/><?php endif; ?>
    <?php if ($dayDu):
        $chuoi = chuoi_12_thang($nam, $sh['id'], $d['ct']['ma']);
        for ($t = 1; $t <= 12; $t++):
            $v = $chuoi[$t] ?? null; ?>
      <?php if ($v !== null): ?>
        <Cell ss:StyleID="<?= $kSo ?>"><Data ss:Type="Number"><?= round($v, 2) ?></Data></Cell>
      <?php else: ?><Cell ss:StyleID="<?= $kSo ?>"/><?php endif; ?>
    <?php endfor; endif; ?>
    <Cell ss:StyleID="dgia"><Data ss:Type="String"><?= $x($d['kpi']['mo_ta']) ?></Data></Cell>
  </Row>
<?php endforeach; ?>
</Table>
</Worksheet>
<?php endforeach; ?>
</Workbook>
<?php
    ghi_nhat_ky('XUAT_BAO_CAO', $loai, "$ky/$nam · " . count($sheets) . ' sheet');
}

<?php
/**
 * Thư viện chỉ tiêu mặc định, rút từ hai file Excel đang dùng:
 *   - THEO DOI THUC HIEN CHI TIEU KE HOACH KHOA 2026.xls
 *   - CHỈ TIÊU CÁC KHOA ĐẠT ĐƯỢC 6 THG ĐẦU NĂM 2026.xlsx
 *
 * Đây chỉ là bộ khởi tạo. Sau khi nạp, người phát triển sửa trực tiếp
 * trong màn hình "Thư viện chỉ tiêu"; file này không đọc lại nữa.
 *
 * ap_dung:  '*'         mọi khoa
 *           'NOI_TRU'   các khoa có giường bệnh
 *           ['PK','NOI'] danh sách mã khoa cụ thể
 */

const KHOA_LAM_SANG = ['PK','HSCC','NOI','NGOAI','NHI','SAN','TN','YHCT','LCK'];

function danh_muc_chi_tieu_mac_dinh(): array
{
    // [ma, ten, don_vi, cha, loai_gia_tri, nguon, huong, phan_bo, ap_dung]
    $d = [];
    $them = function (string $ma, string $ten, string $dv, ?string $cha,
                      string $loai, string $nguon, string $huong,
                      string $phanBo, $apDung) use (&$d) {
        $d[] = compact('ma', 'ten', 'dv', 'cha', 'loai', 'nguon', 'huong', 'phanBo', 'apDung');
    };

    // --- Giường bệnh ---
    $them('GB', 'Giường bệnh nội trú kế hoạch', 'Giường', null,
        'HANG_SO', 'NHAP_TAY', 'CAO_TOT', 'KHONG_CHIA', 'NOI_TRU');

    // --- Khám bệnh ---
    $them('KB', 'Tổng số lượt khám bệnh', 'Lượt', null,
        'DEM', 'TONG_CON', 'CAO_TOT', 'THEO_NGAY', KHOA_LAM_SANG);
    $them('KB_BH', 'Trong đó: Bảo hiểm y tế', 'Lượt', 'KB',
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', KHOA_LAM_SANG);
    $them('KB_ND', 'Trong đó: Người dân (viện phí)', 'Lượt', 'KB',
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', KHOA_LAM_SANG);
    $them('KB_SK', 'Trong đó: Khám sức khỏe', 'Lượt', 'KB',
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', KHOA_LAM_SANG);

    // --- Nội trú ---
    $them('NT', 'Tổng số bệnh nhân điều trị nội trú', 'Lượt', null,
        'DEM', 'TONG_CON', 'CAO_TOT', 'THEO_NGAY', 'NOI_TRU');
    $them('NT_BH', 'Trong đó: Bảo hiểm y tế', 'Lượt', 'NT',
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', 'NOI_TRU');
    $them('NT_ND', 'Trong đó: Người dân (viện phí)', 'Lượt', 'NT',
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', 'NOI_TRU');

    $them('NDT', 'Tổng số ngày điều trị của bệnh nhân nội trú', 'Ngày', null,
        'DEM', 'TONG_CON', 'CAO_TOT', 'THEO_NGAY', 'NOI_TRU');
    $them('NDT_BH', 'Trong đó: Bảo hiểm y tế', 'Ngày', 'NDT',
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', 'NOI_TRU');
    $them('NDT_ND', 'Trong đó: Người dân (viện phí)', 'Ngày', 'NDT',
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', 'NOI_TRU');

    $them('BN60', 'Bệnh nhân trên 60 tuổi', 'Lượt', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', 'NOI_TRU');
    $them('BN6T', 'Trẻ em dưới 6 tuổi', 'Lượt', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', 'NOI_TRU');

    // --- Chỉ tiêu dẫn xuất: KHÔNG nhập tay, KHÔNG cộng dồn ---
    $them('NDT_TB', 'Số ngày điều trị nội trú trung bình', 'Ngày', null,
        'TRUNG_BINH', 'CONG_THUC', 'THAP_TOT', 'KHONG_CHIA', 'NOI_TRU');
    $them('CSGB', 'Công suất sử dụng giường bệnh', '%', null,
        'TY_LE', 'CONG_THUC', 'DICH_CO_DINH', 'KHONG_CHIA', 'NOI_TRU');

    // --- Thủ thuật / phẫu thuật ---
    $them('TT', 'Tổng số thủ thuật', 'Ca', null,
        'DEM', 'TONG_CON', 'CAO_TOT', 'THEO_NGAY', KHOA_LAM_SANG);
    foreach ([['TT_L1', 'Loại I'], ['TT_L2', 'Loại II'], ['TT_L3', 'Loại III']] as [$m, $t]) {
        $them($m, $t, 'Ca', 'TT', 'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', KHOA_LAM_SANG);
    }

    $them('PT', 'Tổng số phẫu thuật', 'Ca', null,
        'DEM', 'TONG_CON', 'CAO_TOT', 'THEO_NGAY', ['NGOAI', 'SAN', 'LCK']);
    foreach ([['PT_DB', 'Đặc biệt'], ['PT_L1', 'Loại I'],
              ['PT_L2', 'Loại II'], ['PT_L3', 'Loại III']] as [$m, $t]) {
        $them($m, $t, 'Ca', 'PT', 'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY',
            ['NGOAI', 'SAN', 'LCK']);
    }

    // --- Xét nghiệm (khoa lâm sàng ghi theo chỉ định, khoa XN ghi theo thực hiện) ---
    $apXN = array_merge(KHOA_LAM_SANG, ['XN']);
    $them('XN', 'Tổng số các xét nghiệm', 'Lần', null,
        'DEM', 'TONG_CON', 'CAO_TOT', 'THEO_NGAY', $apXN);
    foreach ([['XN_HH', 'Huyết học'], ['XN_HS', 'Hóa sinh'],
              ['XN_VS', 'Vi sinh vật'], ['XN_MD', 'Miễn dịch'], ['XN_NT', 'Nước tiểu']] as [$m, $t]) {
        $them($m, $t, 'Lần', 'XN', 'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', $apXN);
    }
    $them('XN_HIV', 'Tổng số xét nghiệm HIV', 'Lần', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['XN']);

    // --- Chẩn đoán hình ảnh ---
    $apCDHA = array_merge(KHOA_LAM_SANG, ['CDHA']);
    $them('XQ',   'Tổng số chụp X-quang', 'Lần', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', $apCDHA);
    $them('CT',   'Tổng số chụp CT-Scanner', 'Lần', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', $apCDHA);
    $them('MRI',  'Tổng số chụp MRI', 'Lần', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['CDHA']);
    $them('SA',   'Tổng số siêu âm chẩn đoán và điều trị', 'Lần', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', $apCDHA);
    $them('DT',   'Tổng số điện tim', 'Lần', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', $apCDHA);
    $them('NS',   'Tổng số nội soi chẩn đoán và can thiệp', 'Lần', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', $apCDHA);
    $them('DEXA', 'Số lần chỉ định đo mật độ xương (DEXA)', 'Lần', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', $apCDHA);

    // --- Kết quả điều trị ---
    $them('KQDT', 'Kết quả điều trị nội trú', 'Lượt', null,
        'DEM', 'TONG_CON', 'CAO_TOT', 'THEO_NGAY', 'NOI_TRU');
    foreach ([['KQ_KHOI', 'Khỏi'], ['KQ_DO', 'Đỡ'],
              ['KQ_KTD', 'Không thay đổi'], ['KQ_NANG', 'Nặng hơn']] as [$m, $t]) {
        $them($m, $t, 'Lượt', 'KQDT', 'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', 'NOI_TRU');
    }

    // --- Chỉ tiêu càng thấp càng tốt ---
    $them('TV', 'Bệnh nhân tử vong', 'BN', null,
        'DEM', 'NHAP_TAY', 'THAP_TOT', 'THEO_NGAY', 'NOI_TRU');
    $them('CV_NT', 'Chuyển viện nội trú', 'Lượt', null,
        'DEM', 'NHAP_TAY', 'THAP_TOT', 'THEO_NGAY', 'NOI_TRU');
    $them('CV_NGT', 'Chuyển viện ngoại trú', 'Lượt', null,
        'DEM', 'NHAP_TAY', 'THAP_TOT', 'THEO_NGAY', KHOA_LAM_SANG);

    // --- Chỉ tiêu riêng từng khoa ---
    $them('QL_THA', 'Bệnh nhân quản lý ngoại trú Tăng huyết áp', 'BN', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'KHONG_CHIA', ['PK']);
    $them('QL_DTD', 'Bệnh nhân quản lý ngoại trú Đái tháo đường', 'BN', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'KHONG_CHIA', ['PK']);
    $them('TV_HIV', 'Người nguy cơ cao được tư vấn và lấy mẫu XN HIV', 'Mẫu', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['PK']);
    $them('COPD', 'Bệnh nhân điều trị ngoại trú COPD - Hen phế quản', 'BN', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'KHONG_CHIA', ['NOI']);
    $them('KHAM_PK', 'Tổng số lần khám phụ khoa', 'Lượt', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['SAN']);
    $them('KHAM_THAI', 'Tổng số lần khám thai', 'Lượt', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['SAN']);
    $them('DE', 'Tổng số sản phụ đẻ', 'Ca', null,
        'DEM', 'TONG_CON', 'CAO_TOT', 'THEO_NGAY', ['SAN']);
    $them('DE_THUONG', 'Đẻ thường', 'Ca', 'DE',
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['SAN']);
    $them('DE_MO', 'Mổ lấy thai', 'Ca', 'DE',
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['SAN']);
    $them('PNMT_HIV', 'Phụ nữ mang thai được tư vấn và lấy mẫu XN HIV', 'Mẫu', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['SAN']);
    $them('NS_TMH', 'Chỉ định nội soi Tai Mũi Họng', 'Lần', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['LCK']);
    $them('NS_DD', 'Nội soi dạ dày', 'Ca', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['LCK']);
    $them('DT_NGOAI_TRU', 'Tổng số lượt bệnh nhân điều trị ngoại trú', 'Lượt', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['YHCT', 'PK']);
    $them('NGT_BH', 'Trong đó: Bảo hiểm y tế', 'Lượt', 'DT_NGOAI_TRU',
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['YHCT', 'PK']);
    $them('NGT_ND', 'Trong đó: Người dân (viện phí)', 'Lượt', 'DT_NGOAI_TRU',
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'THEO_NGAY', ['YHCT', 'PK']);

    // --- Nghiên cứu khoa học ---
    $them('NCKH', 'Đề tài nghiên cứu khoa học', 'Đề tài', null,
        'DEM', 'NHAP_TAY', 'CAO_TOT', 'KHONG_CHIA', '*');

    return $d;
}

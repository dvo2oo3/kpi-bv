-- ================================================================
-- DONG BO DANH MUC LEN HOST — chay MOT LAN (phpMyAdmin > tab SQL).
-- Chi sua DANH MUC. KHONG dung so lieu / ke hoach / tai khoan.
-- An toan: sua theo MA cu the, KHONG phai khoi phuc toan bo.
-- >> Nen bam "Sao luu > tai SQL" tren host truoc khi chay cho chac. <<
-- ================================================================
SET FOREIGN_KEY_CHECKS=0;

-- 1) So ton -> KHONG cong don (giuong thuc ke, quan ly TD/THA, COPD)
UPDATE chi_tieu SET loai_gia_tri='HANG_SO' WHERE ma IN ('GIUONG_THUC_KE','BENH_NHAN_QUAN_LY_TIEU_DUO','BENH_NHAN_QUAN_LY_TANG_HUY','COPD');

-- 2) Doi huong 7 chi tieu bien chung -> cang THAP cang TOT
UPDATE chi_tieu SET huong='THAP_TOT' WHERE ma IN ('KQ_NANG','TS_BN_DOA_SAY_2','TS_BN_SAY_THAI_2','THAI_LUU_2','SO_BANG_HUYET_SAU_DE_2','SO_SAN_GIAT_2','TONG_SO_CHUYEN_VIEN_NGOAI_TRU');

-- 3) Go lien ket cha cho con (neu co) truoc khi xoa chi tieu chet
UPDATE chi_tieu SET id_cha=NULL
 WHERE id_cha IN (SELECT id FROM (SELECT id FROM chi_tieu WHERE ma IN ('BENH_VIEN_DA_KHOA_TINH','BENH_VIEN_NHI','BN_CAP_CUU','BN_DT_NGOAI_TRU','BN_TIEN_LUONG_TV_XIN_VE','CHUYEN_VIEN','CV_CHUYEN_KHOA_2','DO_MAT_DO_LOANG_XUONG','KHAM_PK','KHAM_THAI','MAU_CUA_CONG_SUAT','NGT_BH','NGT_ND','SO_BANG_HUYET_SAU_DE','SO_SAN_GIAT','SUC_KHOE','THAI_LUU','TONG_SO_NGAY','TONG_SO_NOI_SOI','TONG_SO_TE_BAO_XET_NGHIEM_2','TONG_SO_XN_VINH_SNH_VA_MIEN_DI','TRE_DE_RA_2500G','TRONG_DO_NHAN_DAN_VIEN_PHI_11','TR_DO_CVBV_TINH','TR_DO_CVBV_TINH_2','TS_BN_DAT_VONG','TS_BN_DOA_SAY','TS_BN_SAY_THAI','TS_DT_NOI_TRU_PHU_KHOA','TS_TRE_DE_RA_TV','T_DO_BO_BOT')) t);

-- 4) Xoa gan khoa cua cac chi tieu chet
DELETE FROM chi_tieu_ap_dung
 WHERE id_chi_tieu IN (SELECT id FROM (SELECT id FROM chi_tieu WHERE ma IN ('BENH_VIEN_DA_KHOA_TINH','BENH_VIEN_NHI','BN_CAP_CUU','BN_DT_NGOAI_TRU','BN_TIEN_LUONG_TV_XIN_VE','CHUYEN_VIEN','CV_CHUYEN_KHOA_2','DO_MAT_DO_LOANG_XUONG','KHAM_PK','KHAM_THAI','MAU_CUA_CONG_SUAT','NGT_BH','NGT_ND','SO_BANG_HUYET_SAU_DE','SO_SAN_GIAT','SUC_KHOE','THAI_LUU','TONG_SO_NGAY','TONG_SO_NOI_SOI','TONG_SO_TE_BAO_XET_NGHIEM_2','TONG_SO_XN_VINH_SNH_VA_MIEN_DI','TRE_DE_RA_2500G','TRONG_DO_NHAN_DAN_VIEN_PHI_11','TR_DO_CVBV_TINH','TR_DO_CVBV_TINH_2','TS_BN_DAT_VONG','TS_BN_DOA_SAY','TS_BN_SAY_THAI','TS_DT_NOI_TRU_PHU_KHOA','TS_TRE_DE_RA_TV','T_DO_BO_BOT')) t);

-- 5) Xoa 31 chi tieu chet / rac
DELETE FROM chi_tieu WHERE ma IN ('BENH_VIEN_DA_KHOA_TINH','BENH_VIEN_NHI','BN_CAP_CUU','BN_DT_NGOAI_TRU','BN_TIEN_LUONG_TV_XIN_VE','CHUYEN_VIEN','CV_CHUYEN_KHOA_2','DO_MAT_DO_LOANG_XUONG','KHAM_PK','KHAM_THAI','MAU_CUA_CONG_SUAT','NGT_BH','NGT_ND','SO_BANG_HUYET_SAU_DE','SO_SAN_GIAT','SUC_KHOE','THAI_LUU','TONG_SO_NGAY','TONG_SO_NOI_SOI','TONG_SO_TE_BAO_XET_NGHIEM_2','TONG_SO_XN_VINH_SNH_VA_MIEN_DI','TRE_DE_RA_2500G','TRONG_DO_NHAN_DAN_VIEN_PHI_11','TR_DO_CVBV_TINH','TR_DO_CVBV_TINH_2','TS_BN_DAT_VONG','TS_BN_DOA_SAY','TS_BN_SAY_THAI','TS_DT_NOI_TRU_PHU_KHOA','TS_TRE_DE_RA_TV','T_DO_BO_BOT');

SET FOREIGN_KEY_CHECKS=1;

-- LUU Y: rieng viec dua "So ngay DT trung binh" len TREN "Cong suat giuong"
-- (doi thu tu) thi lam bang giao dien: Giao chi tieu -> keo 2 dong, cho de/an toan.

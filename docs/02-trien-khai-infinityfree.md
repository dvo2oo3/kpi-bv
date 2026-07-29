# Triển khai lên InfinityFree

## Giới hạn gói miễn phí và cách hệ thống thích ứng

| Giới hạn | Cách xử lý trong thiết kế |
|---|---|
| Không có cron job | Trạng thái khóa kỳ **tính từ ngày hệ thống lúc đọc**, không cần tác vụ nền |
| Không SSH, không Composer | PHP thuần + PDO, không framework, không thư mục `vendor/` |
| Trần ~30.000 inode | Toàn bộ dự án dưới 30 file |
| `mail()` bị chặn | Không có "Quên mật khẩu" qua email. Admin cấp mật khẩu tạm, hệ thống buộc đổi ở lần đăng nhập đầu |
| 50 MB mỗi CSDL | Ước tính 11 khoa × 60 chỉ tiêu × 12 tháng ≈ 8.000 dòng/năm ≈ 1 MB. Dùng được vài chục năm |
| ~30.000 lượt truy cập/ngày | Một file CSS duy nhất, không JavaScript ngoài, không ảnh nền |
| Không có mbstring trên vài máy chủ | Hàm `do_dai_chuoi()` và `chu_thuong()` có phương án dự phòng |
| Không backup, không SLA | **Bắt buộc** tự tải sao lưu định kỳ (xem mục Vận hành) |

## Các bước cài đặt

1. **Tạo cơ sở dữ liệu**
   Control Panel → *MySQL Databases* → tạo mới. Ghi lại 4 thông tin: host, tên CSDL, tên đăng nhập, mật khẩu.

2. **Tạo bảng**
   Control Panel → *phpMyAdmin* → chọn CSDL vừa tạo → tab **SQL** → dán toàn bộ nội dung `htdocs/install/schema.sql` → **Go**.

3. **Sửa cấu hình**
   Mở `htdocs/app/config.php`, điền `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.

4. **Tải mã nguồn lên**
   Bằng FTP (FileZilla) hoặc File Manager, chép **toàn bộ nội dung thư mục `htdocs/`** vào thư mục `htdocs` trên máy chủ.
   Lưu ý chép cả các file `.htaccess` (FileZilla mặc định ẩn file bắt đầu bằng dấu chấm — bật *Server → Force showing hidden files*).

5. **Bật SSL**
   Control Panel → *Free SSL Certificates* → cấp và cài cho tên miền. File `.htaccess` sẽ tự chuyển mọi truy cập sang HTTPS.

6. **Tạo tài khoản đầu tiên**
   Mở `https://<tên-miền>/install/` → tạo tài khoản **Người phát triển**.

7. **⚠️ Xóa thư mục `/install` trên máy chủ ngay sau đó.**
   Trang này tự vô hiệu hóa khi đã có tài khoản, nhưng vẫn nên xóa hẳn.

8. Đăng nhập bằng tài khoản dev → tạo tài khoản **Quản trị** cho Phòng KHTH → phòng KHTH tự tạo tài khoản cho các khoa.

## Vận hành

- **Sao lưu:** mỗi tháng vào phpMyAdmin → tab *Export* → *Go* → lưu file `.sql` về máy. Đây là bản sao duy nhất anh có; InfinityFree không cam kết giữ dữ liệu.
- **Chống treo tài khoản:** đăng nhập trang quản trị InfinityFree ít nhất mỗi tháng một lần.
- **Cấp lại mật khẩu:** người dùng quên mật khẩu thì liên hệ Phòng KHTH → trang *Người dùng* → nút *Cấp lại MK* → chép mật khẩu tạm giao tận tay. Mật khẩu chỉ hiện **một lần**.

## Chuyển sang VPS khi chạy thật

Mã nguồn không phụ thuộc InfinityFree. Để chuyển:
1. Cài PHP 8.3 + MySQL/MariaDB + Nginx hoặc Apache trên VPS.
2. Nạp file `.sql` đã export.
3. Chép mã nguồn, sửa `app/config.php`.
4. Bật cron nếu muốn tự động hóa thêm (không bắt buộc).

Khi đó có thêm: backup tự động, SSL Let's Encrypt, không giới hạn lượt truy cập, dữ liệu đặt tại Việt Nam.

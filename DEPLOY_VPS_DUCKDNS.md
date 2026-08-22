# QLBV — Deploy lên VPS Hetzner với tên miền DuckDNS

> **Bối cảnh**: InfinityFree sập → chuyển QLBV về chính VPS Hetzner (`49.13.210.250`)
> đang chạy EduVenture + KNSApp. Dùng tên miền free DuckDNS.
>
> **Stack**: Nginx → PHP-FPM (cài thêm) → MySQL (dùng chung server sẵn có, DB riêng `qlbv`).
> Code KHÔNG cần sửa — chỉ đổi thông tin CSDL qua `config.php`.
>
> **Dữ liệu**: import từ `_local/qlbv_data_mysql.sql` (xuất từ demo.sqlite, 3973 dòng:
> 11 khoa, 153 chỉ tiêu, 1709 số liệu, 5 người dùng…). Số liệu thật hiện chỉ có khoa NHI + TN.

---

## 📋 Checklist

- [ ] **B0.** Token DuckDNS (`3fcba16f-…`) chỉ gõ khi chạy — KHÔNG ghi vào file này
- [ ] **B1.** DuckDNS trỏ về `49.13.210.250`
- [ ] **B2.** VPS: cài PHP-FPM + tạo DB MySQL `qlbv`
- [ ] **B3.** Tạo bảng (schema.sql) + import dữ liệu
- [ ] **B4.** Upload code + tạo `config.php`
- [ ] **B5.** Nginx site + PHP-FPM
- [ ] **B6.** SSL Let's Encrypt
- [ ] **B7.** Kiểm tra + đổi mật khẩu

> **Tên miền**: `ttytnamsach.duckdns.org` (tài khoản dvo2oo3@github).
> **Token** (bắt đầu `3fcba16f-…`) lấy trên trang DuckDNS; thay `TOKEN` trong các lệnh
> khi gõ. Đừng lưu token đầy đủ vào file kẻo commit lên GitHub là mất tên miền.

---

## B1. DuckDNS trỏ về VPS

VPS có IP tĩnh nên chỉ cần đặt 1 lần.

1. Vào https://www.duckdns.org → đăng nhập → dòng `ttytnamsach`.
2. Ô **địa chỉ IP hiện tại** đang là `118.70.85.228` (IP nhà bác) → sửa thành
   `49.13.210.250` → bấm **cập nhật địa chỉ IP**.

Hoặc chạy 1 lệnh từ VPS (thay `TOKEN` bằng token thật):

```bash
curl "https://www.duckdns.org/update?domains=ttytnamsach&token=TOKEN&ip=49.13.210.250"
# → in ra: OK
```

> **Giữ tên miền khỏi hết hạn** (DuckDNS xóa domain không cập nhật sau ~30 ngày).
> IP tĩnh nên hiếm khi đổi, nhưng cứ đặt cron 1 lần/tháng cho chắc:
> ```bash
> ( crontab -l 2>/dev/null; echo '@monthly curl -s "https://www.duckdns.org/update?domains=ttytnamsach&token=TOKEN&ip=49.13.210.250" >/dev/null' ) | crontab -
> ```

**Verify** (đợi 1-2 phút cho DNS lan):
```bash
ping ttytnamsach.duckdns.org    # phải ra 49.13.210.250
```

---

## B2. VPS: cài PHP-FPM + tạo DB MySQL

```bash
ssh root@49.13.210.250

# --- PHP-FPM + extension QLBV cần ---
apt update
apt install -y php-fpm php-mysql php-mbstring php-curl php-xml php-zip
# Kiểm tra phiên bản socket (Debian 12 = php8.2)
ls /run/php/          # → php8.2-fpm.sock  (nhớ con số này cho B5)
systemctl enable --now php8.2-fpm
```

MySQL đã chạy sẵn (EduVenture dùng). Tạo DB + user riêng cho QLBV:

```bash
mysql -u root -p
```
```sql
CREATE DATABASE qlbv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'qlbv_user'@'localhost' IDENTIFIED BY 'ĐỔI_MẬT_KHẨU_MẠNH';
GRANT ALL PRIVILEGES ON qlbv.* TO 'qlbv_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> **Lưu mật khẩu** `qlbv_user` — dùng ở B4.

---

## B3. Tạo bảng + import dữ liệu

Từ máy Windows, upload 2 file SQL lên VPS (thư mục tạm):

```powershell
# Trong D:\my code\quan_ly_bv
scp htdocs/install/schema.sql   root@49.13.210.250:/tmp/qlbv_schema.sql
scp _local/qlbv_data_mysql.sql  root@49.13.210.250:/tmp/qlbv_data.sql
```

Trên VPS, nạp schema (tạo bảng) rồi nạp dữ liệu:

```bash
mysql -u qlbv_user -p qlbv < /tmp/qlbv_schema.sql   # tạo 14 bảng + seed 11 khoa
mysql -u qlbv_user -p qlbv < /tmp/qlbv_data.sql     # xóa seed, nạp dữ liệu thật
rm /tmp/qlbv_schema.sql /tmp/qlbv_data.sql          # dọn file có dữ liệu

# Kiểm tra nhanh
mysql -u qlbv_user -p qlbv -e "SELECT COUNT(*) so_lieu FROM so_lieu; SELECT COUNT(*) chi_tieu FROM chi_tieu; SELECT ten_dang_nhap,vai_tro FROM nguoi_dung;"
```

> File data tự `DELETE FROM` từng bảng trước khi INSERT nên nạp lại nhiều lần vẫn sạch.

---

## B4. Upload code + tạo config.php

```powershell
# Từ Windows — đẩy toàn bộ htdocs lên VPS
scp -r htdocs root@49.13.210.250:/var/www/qlbv/
```
(Hoặc WinSCP: tạo `/var/www/qlbv/` rồi kéo thư mục `htdocs` vào.)

Trên VPS tạo `config.php` với thông tin MySQL VPS (file này bị .gitignore, không có sẵn):

```bash
ssh root@49.13.210.250
cp /var/www/qlbv/htdocs/app/config.example.php /var/www/qlbv/htdocs/app/config.php
nano /var/www/qlbv/htdocs/app/config.php
```

Sửa 4 dòng DB đầu:
```php
define('DB_HOST', getenv('QLBV_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('QLBV_DB_NAME') ?: 'qlbv');
define('DB_USER', getenv('QLBV_DB_USER') ?: 'qlbv_user');
define('DB_PASS', getenv('QLBV_DB_PASS') ?: 'MẬT_KHẨU_QLBV_USER_Ở_B2');
```

Phân quyền cho web đọc:
```bash
chown -R www-data:www-data /var/www/qlbv
chmod 640 /var/www/qlbv/htdocs/app/config.php
```

---

## B5. Nginx site + PHP-FPM

Toàn bộ rule đã dịch từ `.htaccess` (ép HTTPS, chặn file nhạy cảm, security header):

```bash
cat > /etc/nginx/sites-available/qlbv << 'EOF'
server {
    listen 80;
    server_name ttytnamsach.duckdns.org;

    root /var/www/qlbv/htdocs;
    index index.php;

    client_max_body_size 50M;   # cho nhập Excel / sao lưu

    # Security headers (thay cho mod_headers trong .htaccess)
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "same-origin" always;

    # Chặn truy cập file nhạy cảm (thay FilesMatch .sql/.md/.log/.ini)
    location ~* \.(sql|md|log|ini)$ { deny all; }

    # Chặn gọi trực tiếp trang lỗi nội bộ
    location = /_403.php { deny all; }

    # Không liệt kê thư mục
    autoindex off;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;   # đổi nếu B2 ra số khác
    }

    # Chặn mọi file/thư mục ẩn (.git, .htaccess…)
    location ~ /\. { deny all; }
}
EOF

ln -sf /etc/nginx/sites-available/qlbv /etc/nginx/sites-enabled/
nginx -t          # test cú pháp
systemctl reload nginx
```

**Test HTTP** (chưa SSL):
```bash
curl -I http://ttytnamsach.duckdns.org/dang-nhap.php   # → 200 OK
```

---

## B6. SSL Let's Encrypt

DuckDNS trỏ thẳng IP (không qua Cloudflare) nên HTTP-01 challenge chạy ngon:

```bash
apt install -y certbot python3-certbot-nginx    # nếu chưa có
certbot --nginx -d ttytnamsach.duckdns.org
# Chọn "2" khi hỏi redirect HTTP→HTTPS (tương đương rule ép HTTPS trong .htaccess)
```

Certbot tự thêm block 443 + redirect 80→443 + auto-renew.

**Verify**:
```bash
curl -I https://ttytnamsach.duckdns.org/dang-nhap.php   # → 200 OK
```
Mở trình duyệt → `https://ttytnamsach.duckdns.org` → trang đăng nhập QLBV.

---

## B7. Kiểm tra + bảo mật

Đăng nhập bằng tài khoản admin có sẵn (mật khẩu cũ trên InfinityFree vẫn dùng được vì hash đã import):

| Tài khoản | Vai trò | Ghi chú |
|---|---|---|
| `dvo2oo3` | dev | Dương Đình Võ |
| `ddhoa`   | admin | Phan Thị Hoa |
| `dung`    | admin | Vũ Thị Dung |

**Nên làm ngay sau khi lên:**
- [ ] Đăng nhập → **Đổi mật khẩu** (nhất là tài khoản dev/admin).
- [ ] Vào **Nhập số liệu** thử 1 khoa xem đọc/ghi DB OK.
- [ ] Xóa tài khoản test `abc`, `aaa` nếu không cần.

---

## 🔧 Troubleshooting

| Lỗi | Check | Fix |
|---|---|---|
| 502 Bad Gateway | `systemctl status php8.2-fpm` | Sai đường dẫn socket trong Nginx (B5) → sửa lại số phiên bản |
| "Không kết nối được CSDL" | `mysql -u qlbv_user -p qlbv` | Sai mật khẩu trong `config.php`, hoặc chưa GRANT |
| Trang trắng / lỗi 500 | tạm bật `QLBV_GO_LOI=1` hoặc xem `journalctl -u php8.2-fpm` | Thường thiếu extension → `apt install php-mbstring php-mysql` |
| Chữ tiếng Việt lỗi | | DB phải `utf8mb4` (đã set ở B2); config đã `charset=utf8mb4` |
| CSS/ảnh không lên | `ls /var/www/qlbv/htdocs/assets` | Sai `root`, hoặc quyền `www-data` |

## 💾 Backup định kỳ

```bash
cat > /root/backup-qlbv.sh << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p /backup/qlbv
mysqldump -u qlbv_user -pMẬT_KHẨU qlbv | gzip > /backup/qlbv/qlbv_$DATE.sql.gz
ls -t /backup/qlbv/qlbv_*.sql.gz | tail -n +15 | xargs -r rm   # giữ 14 bản
EOF
chmod +x /root/backup-qlbv.sh
( crontab -l 2>/dev/null; echo "0 3 * * * /root/backup-qlbv.sh" ) | crontab -   # 3h sáng
```

---

## 📌 Cập nhật code sau này

Sửa PHP ở máy → chỉ cần đẩy lại file thay đổi, KHÔNG đụng DB:
```powershell
scp htdocs/nhap-so-lieu.php root@49.13.210.250:/var/www/qlbv/htdocs/
```
PHP không cần restart (mỗi request nạp file mới). Nếu sửa `config.php` cũng vậy.

> ⚠️ TUYỆT ĐỐI không import đè file .sql cũ hay đẩy `demo.sqlite` lên host —
> sẽ ghi đè dữ liệu sống. Data thật giờ nằm ở MySQL trên VPS.

# Hướng dẫn Thiết lập Web Server (192.168.168.20)

## Bước 1: Cài đặt Môi trường (Nginx & PHP 8.2)
```bash
# Cập nhật hệ thống
sudo apt update && sudo apt upgrade -y

# Cài đặt Nginx và các module PHP cần thiết cho MariaDB
sudo apt install nginx git php-fpm php-mysql php-curl php-gd php-mbstring php-xml php-zip -y

# Đảm bảo dịch vụ khởi động cùng hệ thống
sudo systemctl enable nginx php8.2-fpm
sudo systemctl start nginx php8.2-fpm
```

## Bước 2: Tải Mã nguồn & Phân quyền
```bash
cd /var/www/html

# Xóa dữ liệu cũ
sudo rm -rf *

# Clone chính xác branch 'webserver'
sudo git clone -b webserver https://github.com/tkmh04/Build-a-Web-server.git .

# Xóa file database.sql để bảo mật
sudo rm -f database.sql
sudo rm -f webserver.sql

# Phân quyền cho Web Server
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
```

## Bước 3: Cấu hình Kết nối Database
Vì mã nguồn của bạn sử dụng file `db_config.php` trả về mảng, hãy cấu hình như sau:

```bash
sudo nano /var/www/html/db_config.php
```

**Nội dung file:**
```php
<?php
declare(strict_types=1);

return [
    'dbHost' => '192.168.169.30', // IP máy Database (Vùng LAN)
    'dbName' => 'webserver',
    'dbUser' => 'mh',
    'dbPass' => '123',
    'dbPort' => 3306,
    'schemaVersion' => 3,
    'isLocalDebug' => true,
];
```

## Bước 4: Cấu hình Nginx Virtual Host
Cấu hình này hỗ trợ URL thân thiện (`url=$uri`) và bảo mật các file config.

```bash
sudo nano /etc/nginx/sites-available/default
```

**Nội dung chuẩn:**
```nginx
server {
    listen 80;
    server_name 192.168.168.20;
    root /var/www/html;
    index index.php index.html;

    location / {
        # Định tuyến cho MVC/Index.php
        try_files $uri $uri/ /index.php?url=$uri&$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        
        # Tăng timeout cho các tác vụ nặng
        fastcgi_read_timeout 300;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Bảo mật: Chặn truy cập file .git và các file config
    location ~ /\.(?!well-known) {
        deny all;
    }

    location ~* (db_config|config)\.php$ {
        deny all;
    }
}
```
*Kiểm tra và Restart:* `sudo nginx -t && sudo systemctl restart nginx`

## Bước 5: Tùy chỉnh Tên hiển thị & Tìm kiếm
Để phân biệt với máy Backup, ta đổi tất cả dòng chữ "Web server Primary" thành "Web server 2" trong toàn bộ mã nguồn:

```bash
cd /var/www/html

# Đổi tên hiển thị cho webserver 2 và trong tất cả các tệp PHP (bao gồm admin.php, dashboard.php,...)
sudo find . -type f -name "*.php" -exec sed -i 's/Web server Primary/Web server 2/g' {} +

# Kiểm tra lại xem đã đổi thành công chưa
grep -r "Web server 2" .
```

## Bước 6: Kiểm tra kết nối cuối cùng
Đảm bảo máy Web có thể gọi được máy Database qua Firewall:

```bash
# kiểm tra kết nối máy lan .30
nc -zv 192.168.169.30 3306
Kết quả:
192.168.169.30: inverse host lookup failed: Host name lookup failure
(UNKNOWN) [192.168.169.30] 3306 (mysql) open
```



---

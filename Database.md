# Hướng dẫn Cấu hình MariaDB Server cho Mô hình

## 1. Cài đặt và Bảo mật ban đầu
Cập nhật hệ thống và cài đặt các gói cần thiết:

```bash
# Cập nhật và cài đặt MariaDB cùng công cụ wget
sudo apt update && sudo apt install mariadb-server wget -y

# Chạy trình thiết lập bảo mật
# Lưu ý: Đặt mật khẩu root và chọn 'Y' cho tất cả các câu hỏi
sudo mysql_secure_installation
```

## 2. Cấu hình Kết nối từ xa (Remote Access)
Để các máy Web (.10 và .20) có thể kết nối tới Database, ta cần cấu hình MariaDB lắng nghe trên tất cả các interface mạng.

```bash
# Sửa file cấu hình bind-address
sudo sed -i 's/bind-address\s*=\s*127.0.0.1/bind-address = 0.0.0.0/' /etc/mysql/mariadb.conf.d/50-server.cnf

# Khởi động lại dịch vụ để áp dụng thay đổi
sudo systemctl restart mariadb
```

## 3. Khởi tạo Database và Cấp quyền User
Đăng nhập vào MariaDB bằng quyền root: `sudo mysql -u root -p`. Sau đó thực thi các lệnh SQL sau:

```sql
-- 1. Tạo Database cho dự án
CREATE DATABASE IF NOT EXISTS webserver;

-- 2. Tạo User 'mh' và cấp quyền cho máy Web Primary (.10)
CREATE USER 'mh'@'192.168.168.10' IDENTIFIED BY '123';
GRANT ALL PRIVILEGES ON webserver.* TO 'mh'@'192.168.168.10';

-- 3. Tạo User 'mh' và cấp quyền cho máy Web Backup (.20)
CREATE USER 'mh'@'192.168.168.20' IDENTIFIED BY '123';
GRANT ALL PRIVILEGES ON webserver.* TO 'mh'@'192.168.168.20';

-- 4. Áp dụng thay đổi và thoát
FLUSH PRIVILEGES;
EXIT;
```

## 4. Import Dữ liệu từ GitHub
Tải file sơ đồ database và đổ dữ liệu vào hệ thống:

```bash
cd ~
# Tải file SQL từ kho lưu trữ GitHub
wget https://raw.githubusercontent.com/tkmh04/Build-a-Web-server/webserver/webserver.sql -O setup.sql

# Import dữ liệu vào database 'webserver'
sudo mysql -u root -p webserver < ~/setup.sql
```

## 5. Cấu hình Tường lửa (UFW)
Thiết lập tường lửa để chỉ cho phép các IP chỉ định được phép truy cập vào cổng 3306 (MySQL/MariaDB).

```bash
# Cho phép máy Primary (.10)
sudo ufw allow from 192.168.168.10 to any port 3306

# Cho phép máy Backup (.20)
sudo ufw allow from 192.168.168.20 to any port 3306

# Kích hoạt và kiểm tra trạng thái
sudo ufw enable
sudo ufw status
-- Kết quả
Status: active

To                         Action      From
--                         ------      ----
22                         ALLOW       Anywhere
3306                       ALLOW       192.168.168.20
3306                       ALLOW       192.168.168.10
22/tcp                     ALLOW       Anywhere
22                         ALLOW       192.168.169.0/24
22 (v6)                    ALLOW       Anywhere (v6)
22/tcp (v6)                ALLOW       Anywhere (v6)
```

## 6. Kiểm tra thiết lập thành công
Sau khi hoàn tất, sử dụng các lệnh sau để xác nhận trạng thái hoạt động:

### Kiểm tra Database và Bảng
```bash
sudo mysql -u root -p -e "SHOW DATABASES; USE webserver; SHOW TABLES;"
hoặc mysql -u root -p
SHOW DATABASES;
SHOW TABLES;
```

### Kiểm tra Danh sách User và Host
```sql
-- Đăng nhập mysql -u root -p :
SELECT User, Host FROM mysql.user;
```

**Kết quả mong đợi:**
| User | Host |
| :--- | :--- |
| mh | 192.168.168.10 |
| mh | 192.168.168.20 |
| root | localhost |
| mariadb.sys | localhost |

---

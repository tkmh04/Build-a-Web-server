# Triển khai Nginx WAF với ModSecurity v3 & OWASP CRS

## 📋 Mục lục
* [Tính năng chính](#-tính-năng-chính)
* [Yêu cầu hệ thống](#-yêu-cầu-hệ-thống)
* [Hướng dẫn cài đặt](#-hướng-dẫn-cài-đặt)
    * [Bước 1: Cài đặt Module](#bước-1-cài-đặt-module-modsecurity)
    * [Bước 2: Cấu hình Engine](#bước-2-cấu-hình-nền-tảng-engine)
    * [Bước 3: Tải bộ luật OWASP CRS](#bước-3-tải-bộ-luật-owasp-crs)
    * [Bước 4: Cấu hình gộp main.conf](#bước-4-tạo-file-cấu-hình-tổng-mainconf)
    * [Bước 5: Cấu hình Nginx Failover](#bước-5-cấu-hình-nginx-failover)
* [Kiểm tra & Chạy thử](#-kiểm-tra--chạy-thử)
* [Lưu ý quan trọng](#-lưu-ý-quan-trọng)

---

## 🚀 Tính năng chính
* **Phát hiện & Chặn**: Tự động chặn các truy vấn độc hại.
* **Cơ chế Failover**: Chế độ Active-Passive đảm bảo dịch vụ luôn sẵn sàng.
* **Tối ưu hóa**: Cấu hình loại bỏ các luật gây lỗi khi truy cập bằng IP (False Positive).
* **Bộ luật OWASP**: Cập nhật hơn 900 quy tắc bảo mật từ cộng đồng.

## 💻 Yêu cầu hệ thống
* Hệ điều hành: Ubuntu 20.04/22.04 LTS hoặc Debian.
* Quyền hạn: `sudo` hoặc `root`.
* Nginx đã được cài đặt.

---

## 🛠 Hướng dẫn cài đặt

### Bước 1: Cài đặt Module ModSecurity
Cài đặt gói hỗ trợ kết nối ModSecurity với Nginx.
```bash
sudo apt update
sudo apt install libnginx-mod-security2 -y
```

### Bước 2: Cấu hình nền tảng (Engine)
Thiết lập các thông số cơ bản và kích hoạt chế độ chặn (`SecRuleEngine On`).
```bash
# 1. Tạo thư mục lưu trữ
sudo mkdir -p /etc/nginx/modsec/

# 2. Tải cấu hình mẫu
sudo wget https://raw.githubusercontent.com/SpiderLabs/ModSecurity/v3/master/modsecurity.conf-recommended -O /etc/nginx/modsec/modsecurity.conf

# 3. Chuyển từ 'Chỉ cảnh báo' sang 'Chặn trực tiếp'
sudo sed -i 's/SecRuleEngine DetectionOnly/SecRuleEngine On/' /etc/nginx/modsec/modsecurity.conf
```

### Bước 3: Tải bộ luật OWASP CRS
Sử dụng Git để tải về phiên bản mới nhất của Core Rule Set.
```bash
cd /etc/nginx/modsec/
sudo git clone https://github.com/coreruleset/coreruleset.git coreruleset-3.x
sudo cp coreruleset-3.x/crs-setup.conf.example coreruleset-3.x/crs-setup.conf
```

### Bước 4: Tạo file cấu hình tổng (main.conf)
File này đóng vai trò điều phối, nạp tất cả các luật theo thứ tự chuẩn.
```bash
sudo nano /etc/nginx/modsec/main.conf
```
*Nội dung file:*
```apache
# Nạp Engine
Include /etc/nginx/modsec/modsecurity.conf

# Quy tắc Test thủ công
SecRule ARGS:kickme "yes" "id:999,phase:1,deny,status:403,msg:'Manual Block Test'"

# Nạp thiết lập CRS
Include /etc/nginx/modsec/coreruleset-3.x/crs-setup.conf

# Nạp toàn bộ các luật tấn công (923 luật)
Include /etc/nginx/modsec/coreruleset-3.x/rules/*.conf

# Loại bỏ luật 920350 để cho phép truy cập bằng IP thuần
SecRuleRemoveById 920350
```

### Bước 5: Cấu hình Nginx Failover
Tạo file cấu hình Virtual Host làm Gateway.
```bash
sudo nano /etc/nginx/sites-available/gateway-waf
```
*Nội dung cấu hình:*
```nginx
upstream backend_servers {
    server 192.168.168.10:80 max_fails=3 fail_timeout=30s;
    server 192.168.168.20:80 backup;
}

server {
    listen 80;
    server_name _;

    modsecurity on;
    modsecurity_rules_file /etc/nginx/modsec/main.conf;

    location / {
        proxy_pass http://backend_servers;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```
*Kích hoạt và khởi động lại:*
```bash
sudo ln -s /etc/nginx/sites-available/gateway-waf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## 🧪 Kiểm tra & Chạy thử

Theo dõi log để xem WAF hoạt động:
```bash
sudo tail -f /var/log/modsec_audit.log
```

### Danh sách các bài Test nhanh:

| STT | Loại tấn công | Câu lệnh kiểm tra (curl) | Kết quả kỳ vọng |
|:---:|:---|:---|:---:|
| 1 | **Truy cập IP** | `curl -I "http://<IP_WAF>/"` | `200 OK` |
| 2 | **Test Rule riêng** | `curl -I "http://<IP_WAF>/?kickme=yes"` | `403 Forbidden` |
| 3 | **SQL Injection** | `curl -I "http://<IP_WAF>/?id=1%20OR%201=1"` | `403 Forbidden` |
| 4 | **XSS Attack** | `curl -I "http://<IP_WAF>/?q=<script>alert(1)</script>"` | `403 Forbidden` |
| 5 | **Path Traversal** | `curl -I "http://<IP_WAF>/?file=../../etc/passwd"` | `403 Forbidden` |

---

## ⚠️ Lưu ý quan trọng
* **Thứ tự nạp luật**: File `crs-setup.conf` phải luôn được nạp **TRƯỚC** thư mục `rules/*.conf`.
* **Cấu trúc thư mục**: Đảm bảo thư mục `/etc/nginx/modsec/coreruleset-3.x/rules/` chứa đầy đủ các file `.conf`. Bạn có thể kiểm tra bằng lệnh: `ls -l /etc/nginx/modsec/coreruleset-3.x/rules/`.
* **Log File**: Nếu không tìm thấy log trong `modsec_audit.log`, hãy kiểm tra file log mặc định của Nginx tại `/var/log/nginx/error.log`.

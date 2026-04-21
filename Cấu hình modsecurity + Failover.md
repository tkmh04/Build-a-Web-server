# Nginx WAF (ModSecurity v3 + OWASP CRS) - Production Ready

## Overview

Triển khai **Web Application Firewall (WAF)** sử dụng:

* Nginx (Reverse Proxy + Load Balancer)
* ModSecurity v3 (WAF Engine)
* OWASP Core Rule Set (CRS)

## Features

* ✅ Chặn SQL Injection, XSS, LFI, RCE
* ✅ Reverse Proxy + Load Balancing
* ✅ Failover (Active-Passive)
* ✅ Logging chi tiết (Audit + Error)
* ✅ Giảm False Positive (tối ưu CRS)
* ✅ Production tuning (timeout, body size, header)

---

## Requirements

* OS: Ubuntu 20.04 / 22.04 hoặc Debian
* Nginx >= 1.18
* Quyền sudo

---

## Installation

### 1. Cài đặt Nginx + ModSecurity v3

```bash
sudo apt update
sudo apt install nginx libnginx-mod-http-modsecurity git -y
```

---

### 2. Cấu hình ModSecurity Engine

```bash
sudo mkdir -p /etc/nginx/modsec
cd /etc/nginx/modsec

# Tải config mẫu
sudo wget https://raw.githubusercontent.com/SpiderLabs/ModSecurity/v3/master/modsecurity.conf-recommended -O modsecurity.conf

# Enable blocking mode
sudo sed -i 's/SecRuleEngine DetectionOnly/SecRuleEngine On/' modsecurity.conf
```

👉 Sửa thêm trong `modsecurity.conf`:

```apache
# Bật log
SecAuditEngine RelevantOnly
SecAuditLog /var/log/modsec_audit.log

# Unicode mapping
SecUnicodeMapFile unicode.mapping 20127

# Giới hạn request
SecRequestBodyLimit 10485760
SecRequestBodyNoFilesLimit 131072
```

---

### 3. Cài OWASP CRS

```bash
cd /etc/nginx/modsec
sudo git clone https://github.com/coreruleset/coreruleset.git
cd coreruleset

sudo cp crs-setup.conf.example crs-setup.conf
```

---

### 4. Tạo file tổng `main.conf`

```bash
sudo nano /etc/nginx/modsec/main.conf
```

**Nội dung:**

```apache
# Load ModSecurity Engine
Include /etc/nginx/modsec/modsecurity.conf

# Custom test rule
SecRule ARGS:kickme "yes" "id:999,phase:1,deny,status:403,log,msg:'Manual Block Test'"

# Load CRS
Include /etc/nginx/modsec/coreruleset/crs-setup.conf
Include /etc/nginx/modsec/coreruleset/rules/*.conf

# Reduce false positive (allow IP access)
SecRuleRemoveById 920350
```

---

## Nginx Production Config Failover

### File: `/etc/nginx/sites-available/waf-gateway`

```nginx
upstream backend_servers {
    server 192.168.168.10:80 max_fails=3 fail_timeout=10s;
    server 192.168.168.20:80 backup;
}

server {
    listen 80;
    server_name _;

    # ===== WAF =====
    modsecurity on;
    modsecurity_rules_file /etc/nginx/modsec/main.conf;

    # ===== Security Headers =====
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";

    # ===== Limits =====
    client_max_body_size 10M;

    location / {
        proxy_pass http://backend_servers;

        # Forward headers
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;

        # Timeout tuning
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;

        # Failover nâng cao
        proxy_next_upstream error timeout http_500 http_502 http_503 http_504;

        proxy_redirect off;
    }
}
```

---

### Enable site

```bash
sudo ln -s /etc/nginx/sites-available/waf-gateway /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## Testing

### Theo dõi log

```bash
sudo tail -f /var/log/modsec_audit.log
```

---

### Test nhanh

| Attack      | Command                                                   | Expected |
| ----------- | ---------------------------                               | -------- |
| Normal      | curl -I "http://<IP_WAF>/"                                | 200      |
| Custom rule | curl -I "http://<IP_WAF>/?kickme=yes"                     | 403      |
| SQLi        | curl -I "http://<IP_WAF>/?id=1%20OR%201=1"                | 403      |
| XSS         | curl -I "http://<IP_WAF>/?q=<script>alert(1)</script>"    | 403      |
| LFI         | curl -I "http://<IP_WAF>/?file=../../etc/passwd"          | 403      |

---

## ⚠️ Important Notes

### 1. Thứ tự load rule

```text
crs-setup.conf → rules/*.conf
```

---

### 2. Kiểm tra rule tồn tại

```bash
ls /etc/nginx/modsec/coreruleset/rules/
```

---

### 3. Debug lỗi

```bash
sudo tail -f /var/log/nginx/error.log
```

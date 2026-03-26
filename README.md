# Build-a-Web-Server: Multi-Layer Defense System

Dự án triển khai hệ thống máy chủ web bảo mật cao dựa trên mô hình **Phòng thủ chiều sâu (Defense in Depth)**. Hệ thống kết hợp khả năng chống tấn công mạng (DDoS), bảo mật tầng ứng dụng (WAF), điều phối tải (Load Balancing) và giám sát thời gian thực với cảnh báo qua Telegram.

---

## Kiến trúc Hạ tầng (Infrastructure)

* **Hệ điều hành:** Debian 12 (Bookworm)
* **Nền tảng:** VirtualBox 7.x
* **Mô hình mạng:**
    * **VM1 (Gateway/DMZ - 192.168.167.10):** Tường lửa `nftables`, Nginx Reverse Proxy, ModSecurity WAF.
    * **VM2 (Primary Server - 192.168.168.10):** Máy chủ xử lý chính trong mạng LAN.
    * **VM3 (Scale/Backup Server - 192.168.168.20):** Máy chủ dự phòng và mở rộng tải.
    * **Database:** Kết nối Cloud (Supabase) hoặc Database Server riêng biệt.

---

## Các Kịch Bản Phòng Thủ (Defense Scenarios)

### **KB 1: Lớp Cổng (Network Defense)**
* **Công cụ:** `nftables`, `SYN Cookies`, `Dynamic Whitelist`.
* **Nhiệm vụ:** Chặn đứng các đợt tấn công "lấy thịt đè người" ở tầng mạng (Layer 3-4).
* **Tấn công tương ứng:** SYN Flood, UDP Flood, ICMP Flood.
* **Cơ chế:** Giới hạn **Rate Limiting** để ngăn chặn Botnet và tự động kích hoạt **SYN Cookies** khi phát hiện Flood.

### **KB 2: Lớp Điều phối (System Availability)**
* **Công cụ:** `Nginx Reverse Proxy`, `Load Balancing`, `Failover Check`.
* **Nhiệm vụ:** Đảm bảo hệ thống luôn sẵn sàng (**High Availability**).
* **Thuật toán:** `Weight` (Trọng số) + `max_fails` (Ngưỡng lỗi) + `fail_timeout`.
* **Cơ chế:** Tự động chuyển hướng traffic sang VM3 trong mili giây nếu VM2 gặp sự cố.

### **KB 3: Lớp Kiểm soát nội dung (Application Security)**
* **Công cụ:** `ModSecurity WAF`, `OWASP Core Rule Set (CRS)`.
* **Nhiệm vụ:** Phân tích sâu gói tin HTTP để tìm mã độc ẩn giấu trong dữ liệu người dùng.
* **Tấn công tương ứng:** SQL Injection (SQLi), Cross-Site Scripting (XSS), Local File Inclusion (LFI).
* **Bảo mật bổ sung:** Cấu hình **SSL/TLS (HTTPS)** để mã hóa toàn bộ đường truyền.

### **KB 4: Dự phòng mã nguồn & Dữ liệu (Data Resilience)**
* **Công cụ:** `Borg Backup`, `VM Mirror`, `USB ảo`.
* **Nhiệm vụ:** Hồi sinh dữ liệu khi tất cả các lớp phòng thủ trên đã thất thủ.
* **Kịch bản:** Chống Ransomware (mã hóa đòi tiền chuộc) và Data Destruction (xóa sạch dữ liệu).
* **Cơ chế bảo mật:** * Sử dụng **Borg** để nén và mã hóa bản sao lưu với **Passphrase**. 
    * Triển khai **VM Mirror** để duy trì bản sao máy ảo tức thời.
    * Lưu trữ ngoại vi qua **USB ảo** (cô lập về mặt vật lý với môi trường mạng) để ngăn chặn Hacker truy cập trái phép vào dữ liệu dự phòng.

---

## Giám sát & Cảnh báo (Monitoring)

Hệ thống tích hợp **Monitoring Script** (Bash/Python)/công cụ:
* **Theo dõi tài nguyên:** CPU, RAM, Disk trên toàn bộ các node VM.
* **Cảnh báo Telegram:** Gửi thông báo tức thời tới quản trị viên khi:
    * Phát hiện tấn công DDoS hoặc WAF bị kích hoạt.
    * Một máy chủ thành viên bị Down (Failover).
    * Tiến trình sao lưu dữ liệu hoàn tất hoặc thất bại.

---

## Hướng dẫn Triển khai Nhanh

## 1. Cài đặt và Chuẩn bị hệ thống

Cập nhật hệ thống và cài đặt gói `nftables`:

```bash
sudo apt update && sudo apt install nftables -y
sudo systemctl enable nftables
sudo systemctl start nftables
```

## 2. Tối ưu hóa Kernel (sysctl)

Cấu hình các tham số mạng để chống tấn công SYN Flood và cho phép chuyển tiếp gói tin (IP Forwarding).

Mở file cấu hình:
```bash
sudo nano /etc/sysctl.conf
```

Thêm hoặc chỉnh sửa các dòng sau:
```ini
# Chống tấn công SYN Flood
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_max_syn_backlog = 4096
net.ipv4.tcp_synack_retries = 2
net.ipv4.tcp_syn_retries = 3

# Cho phép chuyển tiếp gói tin (Bắt buộc cho Gateway)
net.ipv4.ip_forward = 1
```

Áp dụng cấu hình ngay lập tức:
```bash
sudo sysctl -p
# Kiểm tra lại (kết quả phải bằng 1)
cat /proc/sys/net/ipv4/ip_forward
```

## 3. Cấu hình Luật nftables

Đây là nội dung cấu hình chính để kiểm soát luồng dữ liệu.

Mở file cấu hình nftables:
```bash
sudo nano /etc/nftables.conf
```

Dán nội dung sau vào file:

```nftables
flush ruleset
# FILTER TABLE

table inet filter {

    # INPUT (Firewall)
    chain input {
        type filter hook input priority 0; policy drop;

        # Loopback
        iif lo accept

        # Established / Related
        ct state established,related accept

        # SSH chỉ từ máy admin
        ip saddr 192.168.167.0/24 tcp dport 22 accept

        # Ping giới hạn từ WAN
        iif "enp0s8" icmp type echo-request limit rate 5/second accept
    }


    # FORWARD

    chain forward {
        type filter hook forward priority 0; policy drop;

        # Cho phép kết nối đã thiết lập
        ct state established,related accept

        # CHO PHÉP TRAFFIC ĐÃ DNAT (WAN -> DMZ)

        iif "enp0s8" oif "enp0s9" tcp dport {80,443} ct status dnat accept
        #  CHẶN TRUY CẬP TRỰC TIẾP BACKEND (ANTI-BYPASS)

        ip daddr 192.168.168.40 tcp dport {80,443} drop

        # (OPTIONAL) CHẶN DMZ TỰ TRUY CẬP CHÍNH NÓ

        iif "enp0s9" ip daddr 192.168.168.40 tcp dport {80,443} drop
        # DMZ -> LAN (Web -> Database)
        iif "enp0s9" oif "enp0s10" tcp dport 3306 accept

        # LAN + DMZ -> Internet

        iif { "enp0s9", "enp0s10" } oif "enp0s8" accept
    }


    # OUTPUT

    chain output {
        type filter hook output priority 0; policy accept;
    }
}


# NAT TABLE

table ip nat {


    # PREROUTING (DNAT)

    chain prerouting {
        type nat hook prerouting priority dstnat;

        # HTTP/HTTPS → Reverse Proxy
        iif "enp0s8" ip daddr 192.168.167.10 tcp dport {80,443} dnat to 192.168.168.40
    }


    # POSTROUTING (SNAT)

    chain postrouting {
        type nat hook postrouting priority srcnat;

        # NAT cho LAN + DMZ ra Internet
        oif "enp0s8" masquerade
    }
}
```

## 4. Kiểm tra và Kích hoạt Ruleset

Thực hiện theo thứ tự để đảm bảo không bị mất kết nối hoặc lỗi cú pháp:

```bash
# Bước 1: Kiểm tra lỗi cú pháp file cấu hình
sudo nft -c -f /etc/nftables.conf

# Bước 2: Nếu không có lỗi, tiến hành nạp cấu hình
sudo systemctl restart nftables

# Bước 3: Xem danh sách rules đang hoạt động
sudo nft list ruleset
```

## 5. Duy trì cấu hình (Persistence)

Để đảm bảo các quy tắc được lưu lại sau khi khởi động lại máy, hãy chắc chắn dịch vụ nftables đã được enable:

```bash
sudo systemctl enable nftables
```

*Lưu ý: Nếu bạn thực hiện các thay đổi trực tiếp bằng lệnh `sudo nft add rule...`, hãy xuất cấu hình hiện tại ra file để lưu giữ:*
```bash
sudo sh -c 'nft list ruleset > /etc/nftables.conf'
```

---

### Giải thích các quy tắc quan trọng:
*   **`policy drop`**: Mọi gói tin không nằm trong danh sách cho phép sẽ bị chặn (Zero-trust).
*   **`ct state established,related accept`**: Cho phép các gói tin thuộc về một kết nối đã được thiết lập trước đó (giúp mạng hoạt động ổn định).
*   **`ct status dnat accept`**: Một cách viết ngắn gọn để cho phép các gói tin đã được xử lý bởi tầng NAT đi qua firewall mà không cần mở port thủ công lần 2 ở chain forward.
*   **`masquerade`**: Ẩn địa chỉ IP nội bộ đằng sau địa chỉ IP của Firewall khi đi ra internet.

---

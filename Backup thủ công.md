thêm cấu hình nfttables
---
table inet filter {
        chain input {
                type filter hook input priority filter; policy drop;
                iif "lo" accept
                ct state established,related accept
                ip saddr 192.168.167.0/24 tcp dport 22 accept
                iif "enp0s8" icmp type echo-request limit rate 5/second accept
        }

        chain forward {
                type filter hook forward priority filter; policy drop;
                ct state established,related accept
                iif "enp0s8" oif "enp0s9" tcp dport { 80, 443 } ct status dnat accept
                ip daddr 192.168.168.40 tcp dport { 80, 443 } drop
                iif "enp0s9" ip daddr 192.168.168.40 tcp dport { 80, 443 } drop
                iif "enp0s9" oif "enp0s10" tcp dport 3306 accept
                iif { "enp0s9", "enp0s10" } oif "enp0s8" accept
                ip saddr 192.168.168.10 ip daddr 192.168.169.40 tcp dport 22 accept
                ip saddr 192.168.168.10 ip daddr 192.168.169.40 icmp type echo-request accept
        }

        chain output {
                type filter hook output priority filter; policy accept;
        }
}
table ip nat {
        chain prerouting {
                type nat hook prerouting priority dstnat; policy accept;
                iif "enp0s8" ip daddr 192.168.167.10 tcp dport { 80, 443 } dnat to 192.168.168.40
        }

        chain postrouting {
                type nat hook postrouting priority srcnat; policy accept;
                oif "enp0s8" masquerade
        }
}
---
sudo apt update
sudo apt install borgbackup -y
borg --version
.10 Webserver
export BORG_PASSPHRASE='123'
sudo -E borg create --stats --progress mh@192.168.169.40:/backup/borg::src-$(date +%F-%H%M) /var/www/html
.30 Database
export BORG_PASSPHRASE='123'
ls /home/mh.webserver.sql
borg create --stats --progress mh@192.168.169.40:/backup/borg::db-$(date +%F-%H%M) /home/mh/webserver.sql
.40 Kho lưu
borg list mh@192.168.169.40:/backup/borg

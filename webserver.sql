-- phpMyAdmin SQL Dump
-- version 5.2.1
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th4 16, 2026 lúc 05:29 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `webserver`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `login_history`
--

CREATE TABLE `login_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `username_at_login` varchar(30) NOT NULL,
  `role_at_login` varchar(20) NOT NULL DEFAULT 'user',
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `ua_device_type` varchar(20) NOT NULL DEFAULT 'Desktop',
  `ua_device_name` varchar(80) NOT NULL DEFAULT 'Unknown',
  `ua_os_name` varchar(60) NOT NULL DEFAULT 'Unknown',
  `ua_os_version` varchar(40) NOT NULL DEFAULT '-',
  `ua_browser_name` varchar(80) NOT NULL DEFAULT 'Unknown',
  `ua_browser_version` varchar(40) NOT NULL DEFAULT '-'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `login_history`
--

INSERT INTO `login_history` (`id`, `user_id`, `username_at_login`, `role_at_login`, `login_at`, `ip_address`, `user_agent`, `ua_device_type`, `ua_device_name`, `ua_os_name`, `ua_os_version`, `ua_browser_name`, `ua_browser_version`) VALUES
(1, 1, 'hang', 'user', '2026-04-16 13:58:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'PC', 'Windows', '10/11', 'Google Chrome', '147.0.0.0'),
(2, 1, 'hang', 'user', '2026-04-16 15:19:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'PC', 'Windows', '10/11', 'Google Chrome', '147.0.0.0'),
(3, 1, 'hang', 'user', '2026-04-16 15:21:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'PC', 'Windows', '10/11', 'Google Chrome', '147.0.0.0');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(30) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `app_meta`
--

CREATE TABLE `app_meta` (
  `meta_key` varchar(64) NOT NULL,
  `meta_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `created_at`) VALUES
(1, 'hang', '$2y$10$C6nN.HjPFLPwkvud76xVvuSFfCMfQMghqjMLrlb9ZFGryLT8xpLaq', 'user', '2026-04-16 13:44:49'),
(2, 'admin', '$2y$12$c8SXMf4RwqnlLPzk67u/je.n8zlPR8agkmtLsR17JH6aUB.7hQ1kq', 'admin', '2026-04-16 17:30:00');

--
-- Đang đổ dữ liệu cho bảng `app_meta`
--

INSERT INTO `app_meta` (`meta_key`, `meta_value`, `updated_at`) VALUES
('schema_version', '3', '2026-04-17 00:00:00');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_login_at` (`login_at`),
  ADD KEY `idx_user_login_at` (`user_id`,`login_at`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Chỉ mục cho bảng `app_meta`
--
ALTER TABLE `app_meta`
  ADD PRIMARY KEY (`meta_key`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

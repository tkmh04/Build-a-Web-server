CREATE DATABASE IF NOT EXISTS webserver
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE webserver;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  username_at_login VARCHAR(30) NOT NULL,
  role_at_login VARCHAR(20) NOT NULL DEFAULT 'user',
  login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NOT NULL,
  ua_device_type VARCHAR(20) NOT NULL DEFAULT 'Desktop',
  ua_device_name VARCHAR(80) NOT NULL DEFAULT 'Unknown',
  ua_os_name VARCHAR(60) NOT NULL DEFAULT 'Unknown',
  ua_os_version VARCHAR(40) NOT NULL DEFAULT '-',
  ua_browser_name VARCHAR(80) NOT NULL DEFAULT 'Unknown',
  ua_browser_version VARCHAR(40) NOT NULL DEFAULT '-',
  INDEX idx_user_id (user_id),
  INDEX idx_login_at (login_at),
  INDEX idx_user_login_at (user_id, login_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS app_meta (
  meta_key VARCHAR(64) PRIMARY KEY,
  meta_value VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO users (username, password_hash, role)
VALUES ('admin', '$2y$12$c8SXMf4RwqnlLPzk67u/je.n8zlPR8agkmtLsR17JH6aUB.7hQ1kq', 'admin')
ON DUPLICATE KEY UPDATE role = 'admin';

INSERT INTO app_meta (meta_key, meta_value)
VALUES ('schema_version', '3')
ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value);

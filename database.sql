-- Create database
CREATE DATABASE IF NOT EXISTS webserver_db;
USE webserver_db;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert demo users (password: pass)
INSERT INTO users (username, password) VALUES 
('user', 'pass'),
('admin', 'admin123'),
('test', 'test123');

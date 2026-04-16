<?php
// SQLite Database Configuration
$db_path = __DIR__ . '/webserver.db';

try {
    $conn = new PDO('sqlite:' . $db_path);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert demo users
    $check = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($check == 0) {
        $conn->exec("INSERT INTO users (username, password) VALUES 
            ('user', 'pass'),
            ('admin', 'admin123'),
            ('test', 'test123')");
    }
    
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
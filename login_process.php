<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $password === $user['password']) {
            $_SESSION['username'] = $username;
            header('Location: welcome.php');
            exit;
        } else {
            echo '<script>alert("Invalid credentials!"); window.location.href = "login.php";</script>';
        }
    } catch (PDOException $e) {
        echo '<script>alert("Error: ' . $e->getMessage() . '"); window.location.href = "login.php";</script>';
    }
}
?>
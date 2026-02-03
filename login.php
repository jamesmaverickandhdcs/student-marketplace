<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("login.html", "error", "CSRF validation failed");
    }

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        redirectWithMessage("profile.php", "success", "Login successful! Welcome, " . $user['username']);
    } else {
        redirectWithMessage("login.html", "error", "Invalid email or password");
    }
}
?>
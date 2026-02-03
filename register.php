<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("register.html", "error", "CSRF validation failed");
    }

    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (strlen($username) < 3) {
        redirectWithMessage("register.html", "error", "Username must be at least 3 characters");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWithMessage("register.html", "error", "Invalid email format");
    }
    if (strlen($password) < 6) {
        redirectWithMessage("register.html", "error", "Password must be at least 6 characters");
    }

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $check->close();
        redirectWithMessage("register.html", "error", "Email already registered. Please login.");
    }
    $check->close();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $username, $email, $hashedPassword);

    if ($stmt->execute()) {
        redirectWithMessage("login.html", "success", "Registration successful! Please login.");
    } else {
        redirectWithMessage("register.html", "error", "Error: " . $stmt->error);
    }

    $stmt->close();
}
?>
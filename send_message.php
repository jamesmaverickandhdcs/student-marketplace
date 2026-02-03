<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php';

if (!isLoggedIn()) {
    redirectWithMessage("login.html", "error", "You must be logged in to send a message");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("profile.php", "error", "CSRF validation failed");
    }

    $sender_id   = $_SESSION['user_id'];
    $receiver_id = intval($_POST['receiver_id']);
    $content     = trim($_POST['content']);

    if (strlen($content) < 2) {
        redirectWithMessage("profile.php", "error", "Message must be at least 2 characters");
    }

    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $sender_id, $receiver_id, $content);

    if ($stmt->execute()) {
        redirectWithMessage("profile.php", "success", "Message sent successfully");
    } else {
        redirectWithMessage("profile.php", "error", "Failed to send message");
    }
    $stmt->close();
}
?>
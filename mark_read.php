<?php
session_start();
require_once 'db_connect.php';

if (isset($_POST['id']) && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $_POST['id'], $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

header("Location: profile.php");
exit();
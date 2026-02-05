<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$response = ["unreadNotifications" => 0, "unreadMessages" => 0];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Notifications
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($response['unreadNotifications']);
    $stmt->fetch();
    $stmt->close();

    // Messages
    $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($response['unreadMessages']);
    $stmt->fetch();
    $stmt->close();
}

echo json_encode($response);
$conn->close();
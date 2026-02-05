<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// ✅ Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit();
}

$sender_id   = $_SESSION['user_id'];
$receiver_id = intval($_POST['receiver_id'] ?? 0);
$content     = trim($_POST['content'] ?? "");

if ($receiver_id <= 0 || empty($content)) {
    echo json_encode(["success" => false, "message" => "Invalid message"]);
    exit();
}

// ✅ Insert message
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content, created_at, is_read) 
                        VALUES (?, ?, ?, NOW(), 0)");
$stmt->bind_param("iis", $sender_id, $receiver_id, $content);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Message sent successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to send message"]);
}

$stmt->close();
$conn->close();
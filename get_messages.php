<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$response = [];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT m.id, m.content, m.created_at, u.username AS sender
                            FROM messages m
                            JOIN users u ON m.sender_id = u.id
                            WHERE m.receiver_id = ? AND m.is_read = 0
                            ORDER BY m.created_at DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $response[] = $row;
    }
    $stmt->close();
}

echo json_encode($response);
$conn->close();
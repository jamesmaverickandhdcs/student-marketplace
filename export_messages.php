<?php
session_start();
include 'db.php';
include 'functions.php';

requireAdmin();

$result = $conn->query("SELECT m.id, u1.username AS sender, u2.username AS receiver, m.content, m.created_at
                        FROM messages m
                        JOIN users u1 ON m.sender_id = u1.id
                        JOIN users u2 ON m.receiver_id = u2.id
                        ORDER BY m.created_at DESC");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=messages_export.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Sender', 'Receiver', 'Content', 'Timestamp']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
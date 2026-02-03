<?php
session_start();
include 'db.php';
include 'functions.php';

requireAdmin();

$result = $conn->query("SELECT id, username, email, role FROM users ORDER BY id ASC");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=users_export.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Username', 'Email', 'Role']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
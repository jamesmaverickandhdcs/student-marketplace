<?php
session_start();
include 'db.php';
include 'functions.php';

requireAdmin();

$result = $conn->query("SELECT id, title, description, price, user_id FROM listings ORDER BY id DESC");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=listings_export.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Title', 'Description', 'Price', 'User ID']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
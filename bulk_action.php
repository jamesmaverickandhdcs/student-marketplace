<?php
session_start();
require_once 'db_connect.php';
require_once 'csrf.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// ✅ CSRF check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
}

if (!empty($_POST['listing_ids']) && !empty($_POST['bulk_action'])) {
    $ids = array_map('intval', $_POST['listing_ids']);
    $action = $_POST['bulk_action'];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    if ($action === "approve" || $action === "reject") {
        $status = $action === "approve" ? "Approved" : "Rejected";
        $stmt = $conn->prepare("UPDATE listings SET status = ? WHERE id IN ($placeholders)");
        $stmt->bind_param("s" . $types, $status, ...$ids);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === "delete") {
        $stmt = $conn->prepare("DELETE FROM listings WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: admin_listings.php");
exit();
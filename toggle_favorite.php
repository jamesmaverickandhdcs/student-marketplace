<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// ✅ Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$listing_id = intval($_POST['listing_id'] ?? 0);

if ($listing_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid listing"]);
    exit();
}

// ✅ Check if already favorited
$stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND listing_id = ?");
$stmt->bind_param("ii", $user_id, $listing_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Remove favorite
    $stmt->close();
    $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND listing_id = ?");
    $stmt->bind_param("ii", $user_id, $listing_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => true, "message" => "Removed from favorites"]);
} else {
    // Add favorite
    $stmt->close();
    $stmt = $conn->prepare("INSERT INTO favorites (user_id, listing_id, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $user_id, $listing_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => true, "message" => "Added to favorites"]);
}

$conn->close();
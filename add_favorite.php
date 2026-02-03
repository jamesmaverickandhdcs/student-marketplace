<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php';

if (!isLoggedIn()) {
    redirectWithMessage("login.html", "error", "You must be logged in to save favorites");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("listings.php", "error", "CSRF validation failed");
    }

    $user_id    = $_SESSION['user_id'];
    $listing_id = intval($_POST['listing_id']);

    $stmt = $conn->prepare("INSERT INTO favorites (user_id, listing_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $listing_id);

    if ($stmt->execute()) {
        redirectWithMessage("listings.php", "success", "Listing added to favorites");
    } else {
        redirectWithMessage("listings.php", "error", "Already in favorites or failed to add");
    }
    $stmt->close();
}
?>
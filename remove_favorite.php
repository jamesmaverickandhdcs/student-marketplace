<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php';

if (!isLoggedIn()) {
    redirectWithMessage("login.html", "error", "You must be logged in to remove favorites");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("profile.php", "error", "CSRF validation failed");
    }

    $user_id    = $_SESSION['user_id'];
    $listing_id = intval($_POST['listing_id']);

    $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND listing_id = ?");
    $stmt->bind_param("ii", $user_id, $listing_id);

    if ($stmt->execute()) {
        redirectWithMessage("profile.php", "success", "Listing removed from favorites");
    } else {
        redirectWithMessage("profile.php", "error", "Failed to remove favorite");
    }
    $stmt->close();
}
?>
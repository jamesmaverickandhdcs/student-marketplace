<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php'; // redirectWithMessage()

if (!isset($_SESSION['user_id'])) {
    redirectWithMessage("login.html", "error", "You must be logged in to delete a listing");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("profile.php", "error", "CSRF validation failed");
    }

    $listing_id = intval($_POST['id']);
    $user_id    = $_SESSION['user_id'];

    // Fetch image path
    $stmt = $conn->prepare("SELECT image FROM listings WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $listing_id, $user_id);
    $stmt->execute();
    $result  = $stmt->get_result();
    $listing = $result->fetch_assoc();
    $stmt->close();

    if ($listing) {
        $imagePath = $listing['image'];

        // Delete listing
        $stmt = $conn->prepare("DELETE FROM listings WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $listing_id, $user_id);

        if ($stmt->execute()) {
            if (!empty($imagePath) && file_exists($imagePath)) {
                unlink($imagePath);
            }
            redirectWithMessage("profile.php", "success", "Listing deleted successfully");
        } else {
            redirectWithMessage("profile.php", "error", "Error deleting listing: " . $stmt->error);
        }
        $stmt->close();
    } else {
        redirectWithMessage("profile.php", "error", "Listing not found or you don’t have permission");
    }
} else {
    redirectWithMessage("profile.php", "error", "Invalid request method");
}
?>
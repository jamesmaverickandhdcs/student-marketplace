<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php'; // redirectWithMessage()

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    redirectWithMessage("login.html", "error", "You must be logged in to post a listing");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("add_listing.php", "error", "CSRF validation failed");
    }

    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price       = floatval($_POST['price']);
    $category    = trim($_POST['category']);
    $user_id     = $_SESSION['user_id'];

    // Validation
    $errors = [];
    if (strlen($title) < 3) $errors[] = "Title must be at least 3 characters.";
    if ($price <= 0) $errors[] = "Price must be greater than 0.";
    $allowed_categories = ["Books", "Electronics", "Services", "Other"];
    if (!in_array($category, $allowed_categories)) $errors[] = "Invalid category selected.";

    if (!empty($errors)) {
        redirectWithMessage("add_listing.php", "error", implode(" ", $errors));
    }

    // Secure image upload
    $imagePath = "";
    if (!empty($_FILES['image']['name'])) {
        $mime = mime_content_type($_FILES['image']['tmp_name']);
        $allowed = ["image/jpeg", "image/png", "image/gif"];
        if (!in_array($mime, $allowed) || !getimagesize($_FILES['image']['tmp_name'])) {
            redirectWithMessage("add_listing.php", "error", "Invalid image file");
        }

        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $fileName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($_FILES['image']['name']));
        $targetFile = $targetDir . time() . "_" . $fileName;

        $img = imagecreatefromstring(file_get_contents($_FILES['image']['tmp_name']));
        if ($img !== false) {
            imagejpeg($img, $targetFile, 90);
            imagedestroy($img);
            $imagePath = $targetFile;
        } else {
            redirectWithMessage("add_listing.php", "error", "Failed to process image");
        }
    }

    // Insert listing
    $stmt = $conn->prepare("INSERT INTO listings 
        (title, description, price, image, user_id, category, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssdsss", $title, $description, $price, $imagePath, $user_id, $category);

    if ($stmt->execute()) {
        redirectWithMessage("listings.php", "success", "Listing posted successfully");
    } else {
        redirectWithMessage("add_listing.php", "error", "Error posting listing: " . $stmt->error);
    }
    $stmt->close();
}
?>
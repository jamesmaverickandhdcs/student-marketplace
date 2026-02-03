<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php'; // redirectWithMessage()

if (!isset($_SESSION['user_id'])) {
    redirectWithMessage("login.html", "error", "You must be logged in to edit your profile");
}

$user_id = $_SESSION['user_id'];

// Fetch current user info
$stmt = $conn->prepare("SELECT username, email, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("edit_profile.php", "error", "CSRF validation failed");
    }

    $newEmail    = trim($_POST['email']);
    $newPassword = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

    // Handle avatar upload securely
    $avatarPath = null;
    if (!empty($_FILES['avatar']['name'])) {
        $mime = mime_content_type($_FILES['avatar']['tmp_name']);
        $allowed = ["image/jpeg", "image/png", "image/gif"];
        if (!in_array($mime, $allowed) || !getimagesize($_FILES['avatar']['tmp_name'])) {
            redirectWithMessage("edit_profile.php", "error", "Invalid image file");
        }

        $targetDir = "uploads/avatars/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $fileName   = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($_FILES['avatar']['name']));
        $targetFile = $targetDir . time() . "_" . $fileName;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
            $avatarPath = $targetFile;
        }
    }

    // Build update query dynamically
    if ($newPassword && $avatarPath) {
        $updateStmt = $conn->prepare("UPDATE users SET email = ?, password = ?, avatar = ? WHERE id = ?");
        $updateStmt->bind_param("sssi", $newEmail, $newPassword, $avatarPath, $user_id);
    } elseif ($newPassword) {
        $updateStmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
        $updateStmt->bind_param("ssi", $newEmail, $newPassword, $user_id);
    } elseif ($avatarPath) {
        $updateStmt = $conn->prepare("UPDATE users SET email = ?, avatar = ? WHERE id = ?");
        $updateStmt->bind_param("ssi", $newEmail, $avatarPath, $user_id);
    } else {
        $updateStmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newEmail, $user_id);
    }

    if ($updateStmt->execute()) {
        redirectWithMessage("profile.php", "success", "Profile updated successfully");
    } else {
        redirectWithMessage("edit_profile.php", "error", "Failed to update profile");
    }
    $updateStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Profile</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>

<main>
  <h2>Edit Profile</h2>
  <form method="POST" action="edit_profile.php" class="edit-profile-form" enctype="multipart/form-data">
    <label for="username">Username:</label>
    <input type="text" id="username" value="<?= htmlspecialchars($user['username']) ?>" disabled>

    <label for="email">Email:</label>
    <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" required>

    <label for="password">New Password (optional):</label>
    <input type="password" name="password" id="password">

    <label for="avatar">Profile Picture:</label>
    <input type="file" name="avatar" id="avatar" accept="image/*">

    <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">

    <button type="submit" class="btn">Save Changes</button>
  </form>
</main>
</body>
</html>
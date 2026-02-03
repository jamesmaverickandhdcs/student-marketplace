<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isAdmin()) {
    redirectWithMessage("profile.php", "error", "Access denied");
}

// Quick stats
$userCount    = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$listingCount = $conn->query("SELECT COUNT(*) AS c FROM listings")->fetch_assoc()['c'];
$messageCount = $conn->query("SELECT COUNT(*) AS c FROM messages")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<main>
  <h2>Admin Dashboard</h2>
  <p>Total Users: <?= $userCount; ?></p>
  <p>Total Listings: <?= $listingCount; ?></p>
  <p>Total Messages: <?= $messageCount; ?></p>

  <h3>Moderation Tools</h3>
  <ul>
    <li><a href="manage_users.php">Manage Users</a></li>
    <li><a href="manage_listings.php">Manage Listings</a></li>
    <li><a href="manage_messages.php">Manage Messages</a></li>
  </ul>
</main>
</body>
</html>
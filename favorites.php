<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isLoggedIn()) {
    redirectWithMessage("login.html", "error", "You must be logged in to view favorites");
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT l.* 
                        FROM favorites f 
                        JOIN listings l ON f.listing_id = l.id 
                        WHERE f.user_id = ? 
                        ORDER BY f.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>My Favorites</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<main>
  <h2>My Favorites</h2>
  <?php while ($listing = $result->fetch_assoc()): ?>
    <div class="listing">
      <h3><?= sanitizeInput($listing['title']); ?></h3>
      <p><?= sanitizeInput($listing['description']); ?></p>
      <p>Price: <?= $listing['price']; ?></p>
      <form method="POST" action="remove_favorite.php">
        <input type="hidden" name="listing_id" value="<?= $listing['id']; ?>">
        <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">
        <button type="submit">Remove from Favorites</button>
      </form>
    </div>
  <?php endwhile; ?>
</main>
</body>
</html>
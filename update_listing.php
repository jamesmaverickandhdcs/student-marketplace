<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php';

requireAdmin();

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("manage_listings.php", "error", "CSRF validation failed");
    }
    $listing_id = intval($_POST['listing_id']);
    $stmt = $conn->prepare("DELETE FROM listings WHERE id = ?");
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    redirectWithMessage("manage_listings.php", "success", "Listing deleted");
}

// Filters
$title     = isset($_GET['title']) ? "%" . $_GET['title'] . "%" : "%";
$min_price = isset($_GET['min_price']) ? intval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? intval($_GET['max_price']) : 999999;

$stmt = $conn->prepare("SELECT id, title, description, price, user_id 
                        FROM listings 
                        WHERE title LIKE ? AND price BETWEEN ? AND ? 
                        ORDER BY id DESC");
$stmt->bind_param("sii", $title, $min_price, $max_price);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Manage Listings</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<main>
  <h2>Manage Listings</h2>
  <form method="GET" action="manage_listings.php">
    <input type="text" name="title" placeholder="Search by title">
    <input type="number" name="min_price" placeholder="Min Price">
    <input type="number" name="max_price" placeholder="Max Price">
    <button type="submit">Filter</button>
  </form>
  <table>
    <tr><th>ID</th><th>Title</th><th>Description</th><th>Price</th><th>User ID</th><th>Actions</th></tr>
    <?php while ($listing = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $listing['id']; ?></td>
        <td><?= sanitizeInput($listing['title']); ?></td>
        <td><?= sanitizeInput($listing['description']); ?></td>
        <td><?= $listing['price']; ?></td>
        <td><?= $listing['user_id']; ?></td>
        <td>
          <form method="POST" action="manage_listings.php" class="inline-form">
            <input type="hidden" name="listing_id" value="<?= $listing['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">
            <button type="submit" name="action" value="delete">Delete</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
</main>
</body>
</html>
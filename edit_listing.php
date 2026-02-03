<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php';

if (!isset($_SESSION['user_id'])) {
    redirectWithMessage("login.html", "error", "You must be logged in to edit a listing");
}

if (isset($_GET['id'])) {
    $listing_id = intval($_GET['id']);
    $user_id    = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT * FROM listings WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $listing_id, $user_id);
    $stmt->execute();
    $result  = $stmt->get_result();
    $listing = $result->fetch_assoc();
    $stmt->close();

    if (!$listing) {
        redirectWithMessage("listings.php", "error", "Listing not found or you don’t have permission");
    }
} else {
    redirectWithMessage("listings.php", "error", "No listing ID provided");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Listing</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>

<main>
  <h2>Edit Listing</h2>
  <form action="update_listing.php" method="POST" enctype="multipart/form-data" class="edit-listing-form">
    <input type="hidden" name="id" value="<?= htmlspecialchars($listing['id']); ?>">
    <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">

    <label for="title">Title:</label>
    <input type="text" id="title" name="title" value="<?= htmlspecialchars($listing['title']); ?>" required>

    <label for="description">Description:</label>
    <textarea id="description" name="description" required><?= htmlspecialchars($listing['description']); ?></textarea>

    <label for="price">Price:</label>
    <input type="number" id="price" name="price" step="0.01" value="<?= htmlspecialchars($listing['price']); ?>" required>

    <label>Current Image:</label><br>
    <img src="<?= !empty($listing['image']) ? htmlspecialchars($listing['image']) : 'images/placeholder.png'; ?>" width="150" alt="Current Listing Image">

    <label for="image">Upload New Image:</label>
    <input type="file" id="image" name="image" accept="image/*">

    <label for="category">Category:</label>
    <select id="category" name="category" required>
      <option value="Books" <?= $listing['category']=="Books"?"selected":""; ?>>Books</option>
      <option value="Electronics" <?= $listing['category']=="Electronics"?"selected":""; ?>>Electronics</option>
      <option value="Services" <?= $listing['category']=="Services"?"selected":""; ?>>Services</option>
      <option value="Other" <?= $listing['category']=="Other"?"selected":""; ?>>Other</option>
    </select>

    <button type="submit" class="btn">Save Changes</button>
  </form>
</main>
</body>
</html>
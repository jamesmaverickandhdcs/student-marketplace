<?php
session_start();
include 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Listings - Student Marketplace</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="print.css" media="print">
</head>
<body>
<?php include 'header.php'; ?>

<main>
  <h2>Available Listings</h2>

  <!-- Search + Filter Form -->
  <form id="searchForm" method="GET" action="listings.php" class="filter-form">
    <input type="text" name="search" placeholder="Search listings..." 
           value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
    <input type="number" name="min_price" placeholder="Min $" step="0.01"
           value="<?= isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : '' ?>">
    <input type="number" name="max_price" placeholder="Max $" step="0.01"
           value="<?= isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : '' ?>">

    <select name="category">
      <option value="">All Categories</option>
      <option value="Books" <?= (isset($_GET['category']) && $_GET['category']=="Books")?"selected":""; ?>>Books</option>
      <option value="Electronics" <?= (isset($_GET['category']) && $_GET['category']=="Electronics")?"selected":""; ?>>Electronics</option>
      <option value="Services" <?= (isset($_GET['category']) && $_GET['category']=="Services")?"selected":""; ?>>Services</option>
      <option value="Other" <?= (isset($_GET['category']) && $_GET['category']=="Other")?"selected":""; ?>>Other</option>
    </select>

    <select name="sort">
      <option value="newest" <?= (isset($_GET['sort']) && $_GET['sort']=="newest")?"selected":""; ?>>Newest First</option>
      <option value="oldest" <?= (isset($_GET['sort']) && $_GET['sort']=="oldest")?"selected":""; ?>>Oldest First</option>
      <option value="low_price" <?= (isset($_GET['sort']) && $_GET['sort']=="low_price")?"selected":""; ?>>Price: Low to High</option>
      <option value="high_price" <?= (isset($_GET['sort']) && $_GET['sort']=="high_price")?"selected":""; ?>>Price: High to Low</option>
    </select>

    <button type="submit">Search</button>
    <button type="button" onclick="window.print()">🖨️ Print Listings</button>
  </form>

  <!-- ✅ Floating Notification System -->
  <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="notification success">✅ Your listing was posted successfully!</div>
  <?php elseif (isset($_GET['error'])): ?>
    <div class="notification error">❌ <?= htmlspecialchars($_GET['error']); ?></div>
  <?php endif; ?>

  <!-- Listings container -->
  <div id="listings-container" class="listings-container"></div>
  <div id="loading-spinner" style="display:none; text-align:center;">
    <div class="loader"></div>
  </div>
</main>

<!-- Back to Top Button -->
<button id="backToTop" class="btn-top">⬆️ Back to Top</button>

<script src="listings.js"></script>
</body>
</html>
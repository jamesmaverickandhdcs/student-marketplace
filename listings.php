<?php
session_start();
include 'db.php';

// Pagination setup
$limit  = 6;
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

/* ---------------------------
   Build dynamic query for count
---------------------------- */
$countQuery  = "SELECT COUNT(*) AS total FROM listings WHERE 1=1";
$countParams = [];
$countTypes  = "";

// Filters
if (!empty($_GET['category'])) {
    $countQuery .= " AND category = ?";
    $countParams[] = $_GET['category'];
    $countTypes   .= "s";
}
if (!empty($_GET['search'])) {
    $countQuery .= " AND (title LIKE ? OR description LIKE ?)";
    $searchTerm   = "%" . $_GET['search'] . "%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes   .= "ss";
}
if (!empty($_GET['min_price'])) {
    $countQuery .= " AND price >= ?";
    $countParams[] = $_GET['min_price'];
    $countTypes   .= "d";
}
if (!empty($_GET['max_price'])) {
    $countQuery .= " AND price <= ?";
    $countParams[] = $_GET['max_price'];
    $countTypes   .= "d";
}

$countStmt = $conn->prepare($countQuery);
if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$totalListings = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($totalListings / $limit);

/* ---------------------------
   Build dynamic query for listings
---------------------------- */
$query  = "SELECT * FROM listings WHERE 1=1";
$params = [];
$types  = "";

// Apply filters
if (!empty($_GET['category'])) {
    $query .= " AND category = ?";
    $params[] = $_GET['category'];
    $types   .= "s";
}
if (!empty($_GET['search'])) {
    $query .= " AND (title LIKE ? OR description LIKE ?)";
    $searchTerm = "%" . $_GET['search'] . "%";
    $params[]   = $searchTerm;
    $params[]   = $searchTerm;
    $types     .= "ss";
}
if (!empty($_GET['min_price'])) {
    $query .= " AND price >= ?";
    $params[] = $_GET['min_price'];
    $types   .= "d";
}
if (!empty($_GET['max_price'])) {
    $query .= " AND price <= ?";
    $params[] = $_GET['max_price'];
    $types   .= "d";
}

// Sorting
if (isset($_GET['sort'])) {
    if ($_GET['sort'] === "low_price") {
        $query .= " ORDER BY price ASC";
    } elseif ($_GET['sort'] === "high_price") {
        $query .= " ORDER BY price DESC";
    } elseif ($_GET['sort'] === "oldest") {
        $query .= " ORDER BY created_at ASC";
    } else {
        $query .= " ORDER BY created_at DESC";
    }
} else {
    $query .= " ORDER BY created_at DESC";
}

// Pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types   .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

/* ---------------------------
   Build filter query string for pagination
---------------------------- */
$filterQuery = '';
foreach (['category','search','min_price','max_price','sort'] as $param) {
    if (!empty($_GET[$param])) {
        $filterQuery .= '&' . $param . '=' . urlencode($_GET[$param]);
    }
}
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

  <!-- Floating Notification System -->
  <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="notification success">✅ Your listing was posted successfully!</div>
  <?php elseif (isset($_GET['error'])): ?>
    <div class="notification error">❌ <?= htmlspecialchars($_GET['error']); ?></div>
  <?php endif; ?>

  <!-- Listings Container -->
  <div class="listings-container">
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="card">
          <img src="<?= !empty($row['image']) ? htmlspecialchars($row['image']) : 'images/placeholder.png' ?>"
               alt="<?= !empty($row['image']) ? htmlspecialchars($row['title']).' image' : 'No image available' ?>">
          <h3><?= htmlspecialchars($row['title']); ?></h3>
          <p><?= htmlspecialchars($row['description']); ?></p>
          <p class="price">Price: $<?= number_format($row['price'], 2); ?></p>
          <p class="category">Category: <?= htmlspecialchars($row['category']); ?></p>
          <p class="posted">Posted on <?= date("F j, Y", strtotime($row['created_at'])); ?></p>
        </div>
      <?php endwhile; ?>

      <!-- Pagination -->
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="listings.php?page=1<?= $filterQuery ?>" class="page-link" aria-label="First page">« First</a>
          <a href="listings.php?page=<?= $page - 1 ?><?= $filterQuery ?>" class="page-link" aria-label="Previous page">‹ Previous</a>
        <?php else: ?>
          <span class="page-link disabled" aria-disabled="true">« First</span>
          <span class="page-link disabled" aria-disabled="true">‹ Previous</span>
        <?php endif; ?>

        <span class="current-page">Page <?= $page ?> of <?= $totalPages ?></span>

        <?php if ($page < $totalPages): ?>
          <a href="listings.php?page=<?= $page + 1 ?><?= $filterQuery ?>" class="page-link" aria-label="Next page">Next ›</a>
          <a href="listings.php?page=<?= $page + 1 ?><?= $filterQuery ?>" class="page-link" aria-label="Next page">Next ›</a>
          <a href="listings.php?page=<?= $totalPages ?><?= $filterQuery ?>" class="page-link" aria-label="Last page">Last »</a>
        <?php else: ?>
          <span class="page-link disabled" aria-disabled="true">Next ›</span>
          <span class="page-link disabled" aria-disabled="true">Last »</span>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <p>No listings found. Try adjusting your filters or search terms.</p>
    <?php endif; ?>
  </div>
</main>

<!-- Back to Top Button -->
<button id="backToTop" class="btn-top">⬆️ Back to Top</button>

<script src="listings.js"></script>
</body>
</html>
<?php
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
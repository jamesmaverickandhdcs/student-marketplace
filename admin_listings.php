<?php
session_start();
include 'db.php';
include 'csrf.php';

// Protect page: only admins
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.html");
    exit;
}

/* ---------------------------
   Handle moderation actions
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        header("Location: admin_listings.php?error=csrf");
        exit;
    }

    $listing_id = intval($_POST['listing_id']);
    $action     = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE listings SET status='active' WHERE id=?");
    } elseif ($action === 'remove') {
        $stmt = $conn->prepare("UPDATE listings SET status='removed', removed_at=NOW() WHERE id=?");
    }

    if (isset($stmt)) {
        $stmt->bind_param("i", $listing_id);
        $stmt->execute();
    }

    header("Location: admin_listings.php?success=1");
    exit;
}

// Pagination setup
$limit  = 10;
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
if (!empty($_GET['status'])) {
    $countQuery .= " AND status = ?";
    $countParams[] = $_GET['status'];
    $countTypes   .= "s";
}
if (!empty($_GET['user'])) {
    $countQuery .= " AND user_id = ?";
    $countParams[] = $_GET['user'];
    $countTypes   .= "i";
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
$query  = "SELECT l.*, u.username FROM listings l JOIN users u ON l.user_id = u.id WHERE 1=1";
$params = [];
$types  = "";

// Apply filters
if (!empty($_GET['category'])) {
    $query .= " AND l.category = ?";
    $params[] = $_GET['category'];
    $types   .= "s";
}
if (!empty($_GET['search'])) {
    $query .= " AND (l.title LIKE ? OR l.description LIKE ?)";
    $searchTerm = "%" . $_GET['search'] . "%";
    $params[]   = $searchTerm;
    $params[]   = $searchTerm;
    $types     .= "ss";
}
if (!empty($_GET['status'])) {
    $query .= " AND l.status = ?";
    $params[] = $_GET['status'];
    $types   .= "s";
}
if (!empty($_GET['user'])) {
    $query .= " AND l.user_id = ?";
    $params[] = $_GET['user'];
    $types   .= "i";
}

// Sorting
$query .= " ORDER BY l.created_at DESC";

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
foreach (['category','search','status','user'] as $param) {
    if (!empty($_GET[$param])) {
        $filterQuery .= '&' . $param . '=' . urlencode($_GET[$param]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Listings - Student Marketplace</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>

<main>
  <h2>Admin: Manage Listings</h2>

  <!-- Filter Form -->
  <form method="GET" action="admin_listings.php" class="filter-form">
    <input type="text" name="search" placeholder="Search listings..."
           value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
    <select name="category">
      <option value="">All Categories</option>
      <option value="Books" <?= (isset($_GET['category']) && $_GET['category']=="Books")?"selected":""; ?>>Books</option>
      <option value="Electronics" <?= (isset($_GET['category']) && $_GET['category']=="Electronics")?"selected":""; ?>>Electronics</option>
      <option value="Services" <?= (isset($_GET['category']) && $_GET['category']=="Services")?"selected":""; ?>>Services</option>
      <option value="Other" <?= (isset($_GET['category']) && $_GET['category']=="Other")?"selected":""; ?>>Other</option>
    </select>
    <select name="status">
      <option value="">All Statuses</option>
      <option value="active" <?= (isset($_GET['status']) && $_GET['status']=="active")?"selected":""; ?>>Active</option>
      <option value="postponed" <?= (isset($_GET['status']) && $_GET['status']=="postponed")?"selected":""; ?>>Postponed</option>
      <option value="traded" <?= (isset($_GET['status']) && $_GET['status']=="traded")?"selected":""; ?>>Traded</option>
      <option value="removed" <?= (isset($_GET['status']) && $_GET['status']=="removed")?"selected":""; ?>>Removed</option>
    </select>
    <input type="number" name="user" placeholder="User ID"
           value="<?= isset($_GET['user']) ? htmlspecialchars($_GET['user']) : '' ?>">
    <button type="submit">Apply Filters</button>
    <a href="admin_listings.php" class="reset-link">Reset</a>
  </form>

  <!-- Notifications -->
  <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="notification success">✅ Action completed successfully!</div>
  <?php elseif (isset($_GET['error'])): ?>
    <div class="notification error">❌ <?= htmlspecialchars($_GET['error']); ?></div>
  <?php endif; ?>

  <!-- Listings -->
  <div class="listings-container">
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="card">
          <h3><?= htmlspecialchars($row['title']); ?></h3>
          <p><?= htmlspecialchars($row['description']); ?></p>
          <p class="price">Price: $<?= number_format($row['price'], 2); ?></p>
          <p class="category">Category: <?= htmlspecialchars($row['category']); ?></p>
          <p class="status">Status: <span class="badge <?= $row['status']; ?>"><?= ucfirst($row['status']); ?></span></p>
          <p class="user">Posted by: <?= htmlspecialchars($row['username']); ?> (User ID: <?= $row['user_id']; ?>)</p>
          <p class="posted">Posted on <?= date("F j, Y", strtotime($row['created_at'])); ?></p>

          <!-- Moderation Actions -->
          <form method="POST" action="admin_listings.php" class="inline-form" onsubmit="return confirm('Confirm action?');">
            <input type="hidden" name="listing_id" value="<?= $row['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">
            <?php if ($row['status'] !== 'active'): ?>
              <button type="submit" name="action" value="approve">Approve</button>
            <?php endif; ?>
            <?php if ($row['status'] !== 'removed'): ?>
              <button type="submit" name="action" value="remove">Remove</button>
            <?php endif; ?>
          </form>
        </div>
      <?php endwhile; ?>

      <!-- Pagination -->
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="admin_listings.php?page=1<?= $filterQuery ?>" class="page-link" aria-label="First page">« First</a>
          <a href="admin_listings.php?page=<?= $page - 1 ?><?= $filterQuery ?>" class="page-link" aria-label="Previous page">‹ Previous</a>
        <?php else: ?>
          <span class="page-link disabled" aria-disabled="true">« First</span>
          <span class="page-link disabled" aria-disabled="true">‹ Previous</span>
        <?php endif; ?>

        <span class="current-page">Page <?= $page ?> of <?= $totalPages ?></span>

        <?php if ($page < $totalPages): ?>
          <a href="admin_listings.php?page=<?= $page + 1 ?><?= $filterQuery ?>" class="page-link" aria-label="Next page">Next ›</a>
          <a href="admin_listings.php?page=<?= $totalPages ?><?= $filterQuery ?>" class="page-link" aria-label="Last page">Last »</a>
        <?php else: ?>
          <span class="page-link disabled" aria-disabled="true">Next ›</span>
          <span class="page-link disabled" aria-disabled="true">Last »</span>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <p>No listings found.</p>
    <?php endif; ?>
  </div>
</main>

<!-- Back to Top Button -->
<button id="backToTop" class="btn-top">⬆️ Back to Top</button>

</body>
</html>
<?php
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
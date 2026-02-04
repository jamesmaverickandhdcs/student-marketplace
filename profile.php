<?php
session_start();
include 'db.php';
include 'csrf.php';

// Protect page: only logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

/* ---------------------------
   Handle lifecycle actions
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        header("Location: profile.php?error=csrf");
        exit;
    }

    $listing_id = intval($_POST['listing_id']);
    $action     = $_POST['action'];

    if ($action === 'postpone') {
        $stmt = $conn->prepare(
            "UPDATE listings SET status='postponed', postponed_at=NOW() WHERE id=? AND user_id=?"
        );
    } elseif ($action === 'traded') {
        $stmt = $conn->prepare(
            "UPDATE listings SET status='traded', traded_at=NOW() WHERE id=? AND user_id=?"
        );
    } elseif ($action === 'reactivate') {
        $stmt = $conn->prepare(
            "UPDATE listings SET status='active', postponed_at=NULL, traded_at=NULL WHERE id=? AND user_id=?"
        );
    }

    if (isset($stmt)) {
        $stmt->bind_param("ii", $listing_id, $user_id);
        $stmt->execute();
    }

    header("Location: profile.php?success=1");
    exit;
}

// Pagination setup
$limit  = 6;
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

/* ---------------------------
   Build dynamic query for count
---------------------------- */
$countQuery  = "SELECT COUNT(*) AS total FROM listings WHERE user_id = ?";
$countParams = [$user_id];
$countTypes  = "i";

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

$status_filter = '';
if (!empty($_GET['status']) && in_array($_GET['status'], ['active','postponed','traded'])) {
    $countQuery .= " AND status = ?";
    $countParams[] = $_GET['status'];
    $countTypes   .= "s";
    $status_filter = $_GET['status'];
}

$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$totalListings = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($totalListings / $limit);

/* ---------------------------
   Build dynamic query for listings
---------------------------- */
$query  = "SELECT * FROM listings WHERE user_id = ?";
$params = [$user_id];
$types  = "i";

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
if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types   .= "s";
}

// Sorting
if (isset($_GET['sort'])) {
    if ($_GET['sort'] === "price_asc") {
        $query .= " ORDER BY price ASC";
    } elseif ($_GET['sort'] === "price_desc") {
        $query .= " ORDER BY price DESC";
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
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* ---------------------------
   Build filter query string for pagination
---------------------------- */
$filterQuery = '';
foreach (['category','search','min_price','max_price','status','sort'] as $param) {
    if (!empty($_GET[$param])) {
        $filterQuery .= '&' . $param . '=' . urlencode($_GET[$param]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($username) ?>'s Profile - Student Marketplace</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>

<main>
  <h2>My Listings</h2>

  <!-- Filter Form -->
  <form method="GET" action="profile.php" class="filter-form">
    <!-- existing filters ... -->
    <label for="status">Status:</label>
    <select name="status" id="status">
      <option value="">All</option>
      <option value="active" <?= ($status_filter=="active")?"selected":""; ?>>Active</option>
      <option value="postponed" <?= ($status_filter=="postponed")?"selected":""; ?>>Postponed</option>
      <option value="traded" <?= ($status_filter=="traded")?"selected":""; ?>>Traded</option>
    </select>
    <button type="submit">Apply</button>
    <a href="profile.php" class="reset-link">Reset Filters</a>
  </form>

  <!-- Listings Container -->
  <div class="listings-container">
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="card">
          <img src="<?= !empty($row['image']) ? htmlspecialchars($row['image']) : 'images/placeholder.png' ?>" 
            alt="<?= !empty($row['image']) ? 'Item image' : 'No image available' ?>">
          <h3><?= htmlspecialchars($row['title']); ?></h3>
          <p><?= htmlspecialchars($row['description']); ?></p>
          <p class="price">Price: $<?= number_format($row['price'], 2); ?></p>
          <p class="category">Category: <?= htmlspecialchars($row['category']); ?></p>
          <p class="posted">Posted on <?= date("F j, Y", strtotime($row['created_at'])); ?></p>
          <p class="status">
            <span class="badge <?= $row['status']; ?>"><?= ucfirst($row['status']); ?></span>
            <?php if ($row['status']=='postponed') echo " since ".$row['postponed_at']; ?>
            <?php if ($row['status']=='traded') echo " on ".$row['traded_at']; ?>
          </p>

          <!-- Lifecycle Actions -->
          <div class="card-actions">
            <a href="edit_listing.php?id=<?= $row['id']; ?>" class="btn-edit">✏️ Edit</a>
            <form method="POST" action="profile.php" class="inline-form" onsubmit="return confirm('Change listing status?');">
              <input type="hidden" name="listing_id" value="<?= $row['id']; ?>">
              <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">

              <?php if ($row['status'] === 'active'): ?>
                <div class="tooltip">
                  <button type="submit" name="action" value="postpone">Postpone</button>
                  <span class="tooltiptext">Mark listing as postponed</span>
                </div>
                <div class="tooltip">
                  <button type="submit" name="action" value="traded">Mark as Traded</button>
                  <span class="tooltiptext">Mark listing as traded</span>
                </div>

              <?php elseif ($row['status'] === 'postponed'): ?>
                <div class="tooltip">
                  <button type="submit" name="action" value="reactivate">Reactivate</button>
                  <span class="tooltiptext">Reactivate listing to active</span>
                </div>

              <?php elseif ($row['status'] === 'traded'): ?>
                <div class="tooltip">
                  <button type="button" disabled>Already Traded</button>
                  <span class="tooltiptext">This listing is completed</span>
                </div>
              <?php endif; ?>
            </form>
          </div>
        </div>
      <?php endwhile; ?>

      <!-- Pagination -->
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="profile.php?page=1<?= $filterQuery ?>" class="page-link" aria-label="First page">« First</a>
            <a href="profile.php?page=<?= $page - 1 ?><?= $filterQuery ?>" class="page-link" aria-label="Previous page">‹ Previous</a>
          <?php else: ?>
            <span class="page-link disabled" aria-disabled="true">« First</span>
            <span class="page-link disabled" aria-disabled="true">‹ Previous</span>
          <?php endif; ?>

          <span class="current-page">Page <?= $page ?> of <?= $totalPages ?></span>

          <?php if ($page < $totalPages): ?>
            <a href="profile.php?page=<?= $page + 1 ?><?= $filterQuery ?>" class="page-link" aria-label="Next page">Next ›</a>
            <a href="profile.php?page=<?= $totalPages ?><?= $filterQuery ?>" class="page-link" aria-label="Last page">Last »</a>
          <?php else: ?>
            <span class="page-link disabled" aria-disabled="true">Next ›</span>
            <span class="page-link disabled" aria-disabled="true">Last »</span>
          <?php endif; ?>
        </div>
    <?php else: ?>
      <p>You haven’t posted any listings yet.
        <a href="add_listing.php" class="btn">➕ Add your first listing</a>
      </p>
    <?php endif; ?>
  </div>
</main>

<!-- Scripts: AJAX, Slider, Infinite Scroll, Dark Mode, Back to Top -->
<script>
// (your existing JS for filters, infinite scroll, dark mode, back-to-top remains unchanged)
</script>

<button id="backToTop" class="btn-top">⬆️ Back to Top</button>
</body>
</html>
<?php
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
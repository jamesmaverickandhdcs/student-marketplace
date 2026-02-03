<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php';

requireAdmin();

// Handle lifecycle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("manage_listings.php", "error", "CSRF validation failed");
    }

    $listing_id = intval($_POST['listing_id']);
    $action = $_POST['action'];

    if ($action === 'postpone') {
        $stmt = $conn->prepare("UPDATE listings SET status='postponed', postponed_at=NOW() WHERE id=?");
        $stmt->bind_param("i", $listing_id);
        $stmt->execute();
        redirectWithMessage("manage_listings.php", "success", "Listing postponed");
    } elseif ($action === 'traded') {
        $stmt = $conn->prepare("UPDATE listings SET status='traded', traded_at=NOW() WHERE id=?");
        $stmt->bind_param("i", $listing_id);
        $stmt->execute();
        redirectWithMessage("manage_listings.php", "success", "Listing marked as traded");
    } elseif ($action === 'reactivate') {
        $stmt = $conn->prepare("UPDATE listings SET status='active', postponed_at=NULL WHERE id=?");
        $stmt->bind_param("i", $listing_id);
        $stmt->execute();
        redirectWithMessage("manage_listings.php", "success", "Listing reactivated");
    }
}

// Pagination + filters
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$title = isset($_GET['title']) ? "%" . $_GET['title'] . "%" : "%";
$status = isset($_GET['status']) ? $_GET['status'] : '';
$status_filter = in_array($status, ['active','postponed','traded']) ? $status : '';

$min_price = isset($_GET['min_price']) ? intval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? intval($_GET['max_price']) : 999999;

$where = "title LIKE ? AND price BETWEEN ? AND ?";
$params = [$title, $min_price, $max_price];
$types = "sii";

if ($status_filter) {
    $where .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM listings WHERE $where");
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$stmt = $conn->prepare("SELECT id, title, description, price, user_id, status, postponed_at, traded_at 
                        FROM listings 
                        WHERE $where 
                        ORDER BY id DESC 
                        LIMIT ? OFFSET ?");
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Manage Listings</title>
  <link rel="stylesheet" href="style.css">
  <script>function confirmAction(msg){return confirm(msg);}</script>
</head>
<body>
<?php include 'header.php'; ?>
<main>
  <h2>Manage Listings</h2>
  <a href="export_listings.php" class="btn">Export Listings (CSV)</a>
  <form method="GET" action="manage_listings.php">
    <input type="text" name="title" placeholder="Search by title" value="<?= htmlspecialchars($_GET['title'] ?? '') ?>">
    <input type="number" name="min_price" placeholder="Min Price" value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>">
    <input type="number" name="max_price" placeholder="Max Price" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>">
    <select name="status">
      <option value="">All Statuses</option>
      <option value="active" <?= $status=='active'?'selected':'' ?>>Active</option>
      <option value="postponed" <?= $status=='postponed'?'selected':'' ?>>Postponed</option>
      <option value="traded" <?= $status=='traded'?'selected':'' ?>>Traded</option>
    </select>
    <button type="submit">Filter</button>
  </form>
  <table>
    <tr><th>ID</th><th>Title</th><th>Description</th><th>Price</th><th>User ID</th><th>Status</th><th>Timestamp</th><th>Actions</th></tr>
    <?php while ($listing = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $listing['id']; ?></td>
        <td><?= sanitizeInput($listing['title']); ?></td>
        <td><?= sanitizeInput($listing['description']); ?></td>
        <td><?= $listing['price']; ?></td>
        <td><?= $listing['user_id']; ?></td>
        <td><?= $listing['status']; ?></td>
        <td>
          <?php
            if ($listing['status'] === 'postponed') echo "Postponed: " . $listing['postponed_at'];
            elseif ($listing['status'] === 'traded') echo "Traded: " . $listing['traded_at'];
            else echo "-";
          ?>
        </td>
        <td>
          <form method="POST" action="manage_listings.php" class="inline-form" onsubmit="return confirmAction('Change listing status?');">
            <input type="hidden" name="listing_id" value="<?= $listing['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">
            <?php if ($listing['status'] === 'active'): ?>
              <button type="submit" name="action" value="postpone">Postpone</button>
              <button type="submit" name="action" value="traded">Mark as Traded</button>
            <?php elseif ($listing['status'] === 'postponed'): ?>
              <button type="submit" name="action" value="reactivate">Reactivate</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">Previous</a>
    <?php endif; ?>
    <?php for ($i=1;$i<=$totalPages;$i++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">Next</a>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
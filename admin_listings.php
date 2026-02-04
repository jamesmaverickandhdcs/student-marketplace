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

    // Bulk actions
    if (isset($_POST['bulk_action']) && !empty($_POST['selected_listings'])) {
        $action = $_POST['bulk_action'];
        foreach ($_POST['selected_listings'] as $listing_id) {
            $listing_id = intval($listing_id);
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE listings SET status='active' WHERE id=?");
            } elseif ($action === 'remove') {
                $stmt = $conn->prepare("UPDATE listings SET status='removed', removed_at=NOW() WHERE id=?");
            }
            if (isset($stmt)) {
                $stmt->bind_param("i", $listing_id);
                $stmt->execute();
                // Log action
                $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, listing_id, action, timestamp) VALUES (?, ?, ?, NOW())");
                $logStmt->bind_param("iis", $_SESSION['admin_id'], $listing_id, $action);
                $logStmt->execute();
                $logStmt->close();
            }
        }
        header("Location: admin_listings.php?success=1");
        exit;
    }

    // Single listing actions
    if (isset($_POST['listing_id'])) {
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
            // Log action
            $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, listing_id, action, timestamp) VALUES (?, ?, ?, NOW())");
            $logStmt->bind_param("iis", $_SESSION['admin_id'], $listing_id, $action);
            $logStmt->execute();
            $logStmt->close();
        }

        header("Location: admin_listings.php?success=1");
        exit;
    }
}

// Pagination setup
$limit  = 10;
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

/* ---------------------------
   Build base query for listings
---------------------------- */
$baseQuery = "SELECT l.*, u.username FROM listings l JOIN users u ON l.user_id = u.id WHERE 1=1";
$params = [];
$types  = "";

// Apply filters
if (!empty($_GET['category'])) {
    $baseQuery .= " AND l.category = ?";
    $params[] = $_GET['category'];
    $types   .= "s";
}
if (!empty($_GET['search'])) {
    $baseQuery .= " AND (l.title LIKE ? OR l.description LIKE ?)";
    $searchTerm = "%" . $_GET['search'] . "%";
    $params[]   = $searchTerm;
    $params[]   = $searchTerm;
    $types     .= "ss";
}
if (!empty($_GET['status'])) {
    $baseQuery .= " AND l.status = ?";
    $params[] = $_GET['status'];
    $types   .= "s";
}
if (!empty($_GET['user'])) {
    $baseQuery .= " AND l.user_id = ?";
    $params[] = $_GET['user'];
    $types   .= "i";
}
if (!empty($_GET['start_date'])) {
    $baseQuery .= " AND l.created_at >= ?";
    $params[] = $_GET['start_date'];
    $types   .= "s";
}
if (!empty($_GET['end_date'])) {
    $baseQuery .= " AND l.created_at <= ?";
    $params[] = $_GET['end_date'];
    $types   .= "s";
}

/* ---------------------------
   Count query
---------------------------- */
$countQuery = str_replace("SELECT l.*, u.username", "SELECT COUNT(*) AS total", $baseQuery);
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalListings = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($totalListings / $limit);

/* ---------------------------
   Display query (with pagination)
---------------------------- */
$query = $baseQuery . " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
$displayParams = array_merge($params, [$limit, $offset]);
$displayTypes  = $types . "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($displayTypes, ...$displayParams);
$stmt->execute();
$result = $stmt->get_result();

/* ---------------------------
   Export query (multi-format)
---------------------------- */
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    $exportQuery = $baseQuery . " ORDER BY l.created_at DESC";
    $exportStmt = $conn->prepare($exportQuery);
    if (!empty($params)) {
        $exportStmt->bind_param($types, ...$params);
    }
    $exportStmt->execute();
    $exportResult = $exportStmt->get_result();

    if ($exportType === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=listings_export.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID','Title','Description','Category','Price','Status','User','Created At']);
        while ($row = $exportResult->fetch_assoc()) {
            fputcsv($output, [$row['id'],$row['title'],$row['description'],$row['category'],$row['price'],$row['status'],$row['username'],$row['created_at']]);
        }
        fclose($output);
        exit;
    }

    if ($exportType === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        $rows = [];
        while ($row = $exportResult->fetch_assoc()) {
            $rows[] = $row;
        }
        echo json_encode($rows, JSON_PRETTY_PRINT);
        exit;
    }

    if ($exportType === 'xlsx') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename=listings_export.xlsx');
        echo '<?xml version="1.0"?>
        <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet">
          <Worksheet ss:Name="Listings">
            <Table>';
        echo '<Row><Cell><Data ss:Type="String">ID</Data></Cell><Cell><Data ss:Type="String">Title</Data></Cell><Cell><Data ss:Type="String">Description</Data></Cell><Cell><Data ss:Type="String">Category</Data></Cell><Cell><Data ss:Type="String">Price</Data></Cell><Cell><Data ss:Type="String">Status</Data></Cell><Cell><Data ss:Type="String">User</Data></Cell><Cell><Data ss:Type="String">Created At</Data></Cell></Row>';
        while ($row = $exportResult->fetch_assoc()) {
            echo '<Row>';
            foreach (['id','title','description','category','price','status','username','created_at'] as $col) {
                echo '<Cell><Data ss:Type="String">'.htmlspecialchars($row[$col]).'</Data></Cell>';
            }
            echo '</Row>';
        }
        echo '</Table></Worksheet></Workbook>';
        exit;
    }
}

/* ---------------------------
   Build filter query string for pagination
---------------------------- */
$filterQuery = '';
foreach (['category','search','status','user','start_date','end_date'] as $param) {
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

  <!-- Filter Form (Sticky) -->
  <form method="GET" action="admin_listings.php" class="filter-form">
    <!-- Search -->
    <input type="text" name="search" placeholder="Search listings..."
           value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">

    <!-- Category -->
    <select name="category">
      <option value="">All Categories</option>
      <option value="Books" <?= (isset($_GET['category']) && $_GET['category']=="Books")?"selected":""; ?>>Books</option>
      <option value="Electronics" <?= (isset($_GET['category']) && $_GET['category']=="Electronics")?"selected":""; ?>>Electronics</option>
      <option value="Services" <?= (isset($_GET['category']) && $_GET['category']=="Services")?"selected":""; ?>>Services</option>
      <option value="Other" <?= (isset($_GET['category']) && $_GET['category']=="Other")?"selected":""; ?>>Other</option>
    </select>

    <!-- Status -->
    <select name="status">
      <option value="">All Statuses</option>
      <option value="active" <?= (isset($_GET['status']) && $_GET['status']=="active")?"selected":""; ?>>Active</option>
      <option value="postponed" <?= (isset($_GET['status']) && $_GET['status']=="postponed")?"selected":""; ?>>Postponed</option>
      <option value="traded" <?= (isset($_GET['status']) && $_GET['status']=="traded")?"selected":""; ?>>Traded</option>
      <option value="removed" <?= (isset($_GET['status']) && $_GET['status']=="removed")?"selected":""; ?>>Removed</option>
    </select>

    <!-- Date Range -->
    <input type="date" name="start_date"
           value="<?= isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '' ?>">
    <input type="date" name="end_date"
           value="<?= isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '' ?>">

    <!-- User ID -->
    <input type="number" name="user" placeholder="User ID"
           value="<?= isset($_GET['user']) ? htmlspecialchars($_GET['user']) : '' ?>">

    <!-- Action Buttons -->
    <button type="submit">Apply Filters</button>
    <a href="admin_listings.php" class="reset-link">Reset</a>
    <!-- Export Buttons -->
    <button type="submit" name="export" value="csv">⬇️ Export CSV</button>
    <button type="submit" name="export" value="json">⬇️ Export JSON</button>
    <button type="submit" name="export" value="xlsx">⬇️ Export Excel</button>
  </form>

  <!-- Notifications -->
  <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="notification success" role="alert">✅ Action completed successfully!</div>
  <?php elseif (isset($_GET['error'])): ?>
    <div class="notification error" role="alert">❌ <?= htmlspecialchars($_GET['error']); ?></div>
  <?php endif; ?>

  <!-- Bulk Action Form -->
  <form method="POST" action="admin_listings.php" onsubmit="return confirm('Apply bulk action?');">
    <select name="bulk_action" required>
      <option value="">Bulk Action</option>
      <option value="approve">Approve Selected</option>
      <option value="remove">Remove Selected</option>
    </select>
    <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">
    <button type="submit">Apply</button>

    <!-- Select All Checkbox -->
    <label>
      <input type="checkbox" id="selectAll" aria-label="Select all listings"> Select All Listings
    </label>

    <!-- Listings -->
    <div class="listings-container">
      <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <div class="card">
            <input type="checkbox" name="selected_listings[]" value="<?= $row['id']; ?>">
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
                <button type="submit" name="action" value="approve" aria-label="Approve listing">Approve</button>
              <?php endif; ?>
              <?php if ($row['status'] !== 'removed'): ?>
                <button type="submit" name="action" value="remove" aria-label="Remove listing">Remove</button>
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
  </form>

  <!-- Collapsible Audit Logs -->
  <button id="toggleLogs" aria-expanded="false">Show Audit Logs</button>
  <div id="auditLogs" style="display:none;">
    <h3>Recent Admin Actions</h3>
    <table>
      <tr><th>Admin</th><th>Listing</th><th>Action</th><th>Timestamp</th></tr>
      <?php
      $logResult = $conn->query("SELECT a.username, l.title, log.action, log.timestamp 
                                 FROM admin_logs log 
                                 JOIN users a ON log.admin_id = a.id 
                                 JOIN listings l ON log.listing_id = l.id 
                                 ORDER BY log.timestamp DESC LIMIT 20");
      while ($log = $logResult->fetch_assoc()) {
          echo "<tr>
                  <td>".htmlspecialchars($log['username'])."</td>
                  <td>".htmlspecialchars($log['title'])."</td>
                  <td>".htmlspecialchars($log['action'])."</td>
                  <td>".$log['timestamp']."</td>
                </tr>";
      }
      ?>
    </table>
  </div>
</main>

<!-- Back to Top Button -->
<button id="backToTop" class="btn-top">⬆️ Back to Top</button>

<script>
// Select All functionality
document.getElementById('selectAll').addEventListener('change', function() {
  const checkboxes = document.querySelectorAll('input[name="selected_listings[]"]');
  checkboxes.forEach(cb => cb.checked = this.checked);
});

// Collapsible Audit Logs
document.getElementById('toggleLogs').addEventListener('click', function() {
  const logs = document.getElementById('auditLogs');
  const expanded = logs.style.display === 'block';
  logs.style.display = expanded ? 'none' : 'block';
  this.textContent = expanded ? 'Show Audit Logs' : 'Hide Audit Logs';
  this.setAttribute('aria-expanded', !expanded);
});
</script>

</body>
</html>
<?php
if (isset($stmt)) { $stmt->close(); }
if (isset($exportStmt)) { $exportStmt->close(); }
$conn->close();
?>
<?php
session_start();
include 'db.php';
include 'csrf.php';

// Protect page: only admins
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.html");
    exit;
}

// Session hardening: timeout after 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.html?error=session_timeout");
    exit;
}
$_SESSION['last_activity'] = time();

// Role check (superadmin or moderator)
$role = $_SESSION['role'] ?? 'moderator';

/* ---------------------------
   Handle moderation actions
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        header("Location: admin_listings.php?error=csrf");
        exit;
    }

    // Bulk actions (superadmin only)
    if ($role === 'superadmin' && isset($_POST['bulk_action']) && !empty($_POST['selected_listings'])) {
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
                // Audit log
                $ip = $_SERVER['REMOTE_ADDR'];
                $ua = $_SERVER['HTTP_USER_AGENT'];
                $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, listing_id, action, ip_address, user_agent, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
                $logStmt->bind_param("iisss", $_SESSION['admin_id'], $listing_id, $action, $ip, $ua);
                $logStmt->execute();
                $logStmt->close();
            }
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
if (!empty($_GET['category'])) { $baseQuery .= " AND l.category = ?"; $params[] = $_GET['category']; $types .= "s"; }
if (!empty($_GET['search'])) { $baseQuery .= " AND (l.title LIKE ? OR l.description LIKE ?)"; $searchTerm = "%".$_GET['search']."%"; $params[] = $searchTerm; $params[] = $searchTerm; $types .= "ss"; }
if (!empty($_GET['status'])) { $baseQuery .= " AND l.status = ?"; $params[] = $_GET['status']; $types .= "s"; }
if (!empty($_GET['user'])) { $baseQuery .= " AND l.user_id = ?"; $params[] = $_GET['user']; $types .= "i"; }
if (!empty($_GET['start_date'])) { $baseQuery .= " AND l.created_at >= ?"; $params[] = $_GET['start_date']; $types .= "s"; }
if (!empty($_GET['end_date'])) { $baseQuery .= " AND l.created_at <= ?"; $params[] = $_GET['end_date']; $types .= "s"; }

/* ---------------------------
   Count query
---------------------------- */
$countQuery = str_replace("SELECT l.*, u.username", "SELECT COUNT(*) AS total", $baseQuery);
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) { $countStmt->bind_param($types, ...$params); }
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
    <input type="text" name="search" placeholder="Search listings..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
    <select name="category"><option value="">All Categories</option></select>
    <select name="status"><option value="">All Statuses</option><option value="active">Active</option><option value="removed">Removed</option></select>
    <div class="date-range">
      <label for="start_date">From:</label>
      <input type="date" id="start_date" name="start_date" value="<?= isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '' ?>">
      <label for="end_date">To:</label>
      <input type="date" id="end_date" name="end_date" value="<?= isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '' ?>">
    </div>
    <input type="number" name="user" placeholder="User ID" value="<?= isset($_GET['user']) ? htmlspecialchars($_GET['user']) : '' ?>">
    <button type="submit">Apply Filters</button>
    <a href="admin_listings.php" class="reset-link">Reset</a>
    <?php if ($role === 'superadmin'): ?>
      <button type="submit" name="export" value="csv">⬇️ Export CSV</button>
      <button type="submit" name="export" value="json">⬇️ Export JSON</button>
      <button type="submit" name="export" value="xlsx">⬇️ Export Excel</button>
    <?php endif; ?>
  </form>

  <!-- Notifications -->
  <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="notification success" role="alert">✅ Action completed successfully!</div>
  <?php elseif (isset($_GET['error'])): ?>
    <div class="notification error" role="alert">❌ <?= htmlspecialchars($_GET['error']); ?></div>
  <?php endif; ?>

  <!-- Listings with Bulk Actions -->
  <?php if ($role === 'superadmin'): ?>
    <form method="POST" action="admin_listings.php" class="bulk-actions" onsubmit="return confirm('Apply bulk action to selected listings?');">
      <select name="bulk_action" required>
        <option value="">-- Select Action --</option>
        <option value="approve">Approve</option>
        <option value="remove">Remove</option>
      </select>
      <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">

      <div class="listings-container">
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
          </div>
        <?php endwhile; ?>
      </div>

      <button type="submit">Apply to Selected</button>
    </form>
  <?php else: ?>
    <div class="listings-container">
      <?php while ($row = $result->fetch_assoc()  <!-- Listings with Bulk Actions -->
  <?php if ($role === 'superadmin'): ?>
    <!-- Bulk actions form START -->
    <form method="POST" action="admin_listings.php" class="bulk-actions" 
          onsubmit="return confirm('Apply bulk action to selected listings?');">

      <select name="bulk_action" required>
        <option value="">-- Select Action --</option>
        <option value="approve">Approve</option>
        <option value="remove">Remove</option>
      </select>
      <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">

      <!-- Listings inside the same form -->
      <div class="listings-container">
        <?php while ($row = $result->fetch_assoc()): ?>
          <div class="card">
            <input type="checkbox" name="selected_listings[]" value="<?= $row['id']; ?>">
            <h3><?= htmlspecialchars($row['title']); ?></h3>
            <p><?= htmlspecialchars($row['description']); ?></p>
            <p class="price">Price: $<?= number_format($row['price'], 2); ?></p>
            <p class="category">Category: <?= htmlspecialchars($row['category']); ?></p>
            <p class="status">Status: 
              <span class="badge <?= $row['status']; ?>"><?= ucfirst($row['status']); ?></span>
            </p>
            <p class="user">Posted by: <?= htmlspecialchars($row['username']); ?> 
              (User ID: <?= $row['user_id']; ?>)</p>
            <p class="posted">Posted on <?= date("F j, Y", strtotime($row['created_at'])); ?></p>
          </div>
        <?php endwhile; ?>
      </div>

      <!-- Bulk actions form END -->
      <button type="submit">Apply to Selected</button>
    </form>

  <?php else: ?>
    <!-- Listings for moderators (no bulk form, no checkboxes) -->
    <div class="listings-container">
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="card">
          <h3><?= htmlspecialchars($row['title']); ?></h3>
          <p><?= htmlspecialchars($row['description']); ?></p>
          <p class="price">Price: $<?= number_format($row['price'], 2); ?></p>
          <p class="category">Category: <?= htmlspecialchars($row['category']); ?></p>
          <p class="status">Status: 
            <span class="badge <?= $row['status']; ?>"><?= ucfirst($row['status']); ?></span>
          </p>
          <p class="user">Posted by: <?= htmlspecialchars($row['username']); ?> 
            (User ID: <?= $row['user_id']; ?>)</p>
          <p class="posted">Posted on <?= date("F j, Y", strtotime($row['created_at'])); ?></p>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

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

  <!-- Audit Logs -->
  <?php if ($role === 'superadmin'): ?>
    <button id="toggleLogs" aria-expanded="false">Show Audit Logs</button>
    <div id="auditLogs" style="display:none;">
      <h3>Recent Admin Actions</h3>

      <!-- Audit Log Filters -->
      <form method="GET" action="admin_listings.php" class="filter-form">
        <input type="text" name="log_admin" placeholder="Admin username" value="<?= isset($_GET['log_admin']) ? htmlspecialchars($_GET['log_admin']) : '' ?>">
        <select name="log_action">
          <option value="">All Actions</option>
          <option value="approve">Approve</option>
          <option value="remove">Remove</option>
        </select>
        <input type="date" name="log_start" value="<?= isset($_GET['log_start']) ? htmlspecialchars($_GET['log_start']) : '' ?>">
        <input type="date" name="log_end" value="<?= isset($_GET['log_end']) ? htmlspecialchars($_GET['log_end']) : '' ?>">
        <button type="submit">Filter Logs</button>
      </form>

      <table>
        <tr><th>Admin</th><th>Listing</th><th>Action</th><th>IP</th><th>User Agent</th><th>Timestamp</th></tr>
        <?php
        $logQuery = "SELECT a.username, l.title, log.action, log.ip_address, log.user_agent, log.timestamp 
                     FROM admin_logs log 
                     JOIN users a ON log.admin_id = a.id 
                     JOIN listings l ON log.listing_id = l.id 
                     WHERE 1=1";

        $logParams = [];
        $logTypes  = "";

        if (!empty($_GET['log_admin'])) { $logQuery .= " AND a.username LIKE ?"; $logParams[] = "%".$_GET['log_admin']."%"; $logTypes .= "s"; }
        if (!empty($_GET['log_action'])) { $logQuery .= " AND log.action = ?"; $logParams[] = $_GET['log_action']; $logTypes .= "s"; }
        if (!empty($_GET['log_start'])) { $logQuery .= " AND log.timestamp >= ?"; $logParams[] = $_GET['log_start']; $logTypes .= "s"; }
        if (!empty($_GET['log_end'])) { $logQuery .= " AND log.timestamp <= ?"; $logParams[] = $_GET['log_end']; $logTypes .= "s"; }

        $logPage   = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
        $logLimit  = 20;
        $logOffset = ($logPage - 1) * $logLimit;

        $logQuery .= " ORDER BY log.timestamp DESC LIMIT ? OFFSET ?";
        $logParams[] = $logLimit;
        $logParams[] = $logOffset;
        $logTypes   .= "ii";

        $logStmt = $conn->prepare($logQuery);
        if (!empty($logParams)) { $logStmt->bind_param($logTypes, ...$logParams); }
        $logStmt->execute();
        $logResult = $logStmt->get_result();

        while ($log = $logResult->fetch_assoc()) {
            echo "<tr>
                    <td>".htmlspecialchars($log['username'])."</td>
                    <td>".htmlspecialchars($log['title'])."</td>
                    <td>".htmlspecialchars($log['action'])."</td>
                    <td>".htmlspecialchars($log['ip_address'])."</td>
                    <td>".htmlspecialchars($log['user_agent'])."</td>
                    <td>".$log['timestamp']."</td>
                  </tr>";
        }
        ?>
      </table>

      <!-- Log Pagination -->
      <div class="pagination">
        <?php if ($logPage > 1): ?>
          <a href="admin_listings.php?log_page=<?= $logPage - 1 ?>" class="page-link">‹ Previous</a>
        <?php endif; ?>
        <span class="current-page">Page <?= $logPage ?></span>
        <a href="admin_listings.php?log_page=<?= $logPage + 1 ?>" class="page-link">Next ›</a>
      </div>
    </div>
  <?php endif; ?>
</main>

<!-- Back to Top Button -->
<button id="backToTop" class="btn-top">⬆️ Back to Top</button>

<script>
// Collapsible Audit Logs
document.getElementById('toggleLogs')?.addEventListener('click', function() {
  const logs = document.getElementById('auditLogs');
  const expanded = logs.style.display === 'block';
  logs.style.display = expanded ? 'none' : 'block';
  this.textContent = expanded ? 'Show Audit Logs' : 'Hide Audit Logs';
  this.setAttribute('aria-expanded', !expanded);
});

// Back to Top
document.getElementById('backToTop').addEventListener('click', () => {
  window.scrollTo({ top: 0, behavior: 'smooth' });
});

// AJAX Live Filtering
document.querySelector('.filter-form').addEventListener('change', function(e) {
  e.preventDefault();
  const params = new URLSearchParams(new FormData(this));
  params.append('ajax', '1');
  fetch('admin_listings.php?' + params.toString())
    .then(res => res.text())
    .then(html => {
      document.querySelector('.listings-container').innerHTML = html;
    });
});

// Infinite Scroll
let page = 1;
window.addEventListener('scroll', () => {
  if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 200) {
    page++;
    const params = new URLSearchParams(new FormData(document.querySelector('.filter-form')));
    params.append('ajax', '1');
    params.append('page', page);
    fetch('admin_listings.php?' + params.toString())
      .then(res => res.text())
      .then(html => {
        if (html.trim() !== '') {
          document.querySelector('.listings-container').insertAdjacentHTML('beforeend', html);
        }
      });
  }
});
</script>

</body>
</html>
<?php
if (isset($stmt)) { $stmt->close(); }
if (isset($exportStmt)) { $exportStmt->close(); }
if (isset($logStmt)) { $logStmt->close(); }
$conn->close();
?>
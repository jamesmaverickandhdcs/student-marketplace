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
                // Enhanced audit log
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

    // Single listing actions (moderators allowed)
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
            // Enhanced audit log
            $ip = $_SERVER['REMOTE_ADDR'];
            $ua = $_SERVER['HTTP_USER_AGENT'];
            $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, listing_id, action, ip_address, user_agent, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
            $logStmt->bind_param("iisss", $_SESSION['admin_id'], $listing_id, $action, $ip, $ua);
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
   Export query (superadmin only)
---------------------------- */
if ($role === 'superadmin' && isset($_GET['export'])) {
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
   Export Audit Logs (superadmin only)
---------------------------- */
if ($role === 'superadmin' && isset($_GET['export_logs'])) {
    $logResult = $conn->query("SELECT * FROM admin_logs ORDER BY timestamp DESC");
    if ($_GET['export_logs'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=audit_logs.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Admin ID','Listing ID','Action','IP','User Agent','Timestamp']);
        while ($log = $logResult->fetch_assoc()) {
            fputcsv($output, [$log['admin_id'],$log['listing_id'],$log['action'],$log['ip_address'],$log['user_agent'],$log['timestamp']]);
        }
        fclose($output);
        exit;
    }
    if ($_GET['export_logs'] === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        $rows = [];
        while ($log = $logResult->fetch_assoc()) {
            $rows[] = $log;
        }
        echo json_encode($rows, JSON_PRETTY_PRINT);
        exit;
    }
}

/* ---------------------------
   AJAX response for infinite scroll
---------------------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    while ($row = $result->fetch_assoc()) {
        echo '<div class="card">';
        echo '<h3>'.htmlspecialchars($row['title']).'</h3>';
        echo '<p>'.htmlspecialchars($row['description']).'</p>';
        echo '<p class="price">Price: $'.number_format($row['price'], 2).'</p>';
        echo '<p class="category">Category: '.htmlspecialchars($row['category']).'</p>';
        echo '<p class="status">Status: <span class="badge '.$row['status'].'">'.ucfirst($row['status']).'</span></p>';
        echo '<p class="user">Posted by: '.htmlspecialchars($row['username']).'</p>';
        echo '<p class="posted">Posted on '.date("F j, Y", strtotime($row['created_at'])).'</p>';
        echo '</div>';
    }
    exit;
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
    <input type="date" name="start_date" value="<?= isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '' ?>">
    <input type="date" name="end_date" value="<?= isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '' ?>">
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

  <!-- Listings -->
  <div class="listings-container">
    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="card">
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

  <!-- Audit Logs -->
  <?php if ($role === 'superadmin'): ?>
    <button id="toggleLogs" aria-expanded="false">Show Audit Logs</button>
    <div id="auditLogs" style="display:none;">
      <h3>Recent Admin Actions</h3>
      <form method="GET" action="admin_listings.php">
        <button type="submit" name="export_logs" value="csv">⬇️ Export Logs CSV</button>
        <button type="submit" name="export_logs" value="json">⬇️ Export Logs JSON</button>
      </form>
      <table>
        <tr><th>Admin</th><th>Listing</th><th>Action</th><th>IP</th><th>User Agent</th><th>Timestamp</th></tr>
        <?php
        $logResult = $conn->query("SELECT a.username, l.title, log.action, log.ip_address, log.user_agent, log.timestamp 
                                   FROM admin_logs log 
                                   JOIN users a ON log.admin_id = a.id 
                                   JOIN listings l ON log.listing_id = l.id 
                                   ORDER BY log.timestamp DESC LIMIT 20");
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
$conn->close();
?>
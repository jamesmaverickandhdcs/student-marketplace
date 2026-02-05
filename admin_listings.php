<?php
session_start();
require_once 'db_connect.php';
require_once 'header.php';

// ✅ Secure session check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// ✅ CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ Pagination setup
$limit = 20; // listings per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// ✅ Date filter setup
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;

// ✅ Build query with filters
$query = "SELECT l.id, l.title, l.status, l.created_at, u.username 
          FROM listings l 
          JOIN users u ON l.user_id = u.id 
          WHERE 1=1";

$params = [];
$types  = "";

if ($start_date && $end_date) {
    $query .= " AND DATE(l.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

$query .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ✅ Count total listings for pagination
$countQuery = "SELECT COUNT(*) FROM listings";
$countResult = $conn->query($countQuery);
$totalListings = $countResult->fetch_row()[0];
$totalPages = ceil($totalListings / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Listings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Manage Listings</h1>

    <!-- ✅ Date Filter Form -->
    <form method="get" class="date-range">
        <label for="start_date">Start:</label>
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
        <label for="end_date">End:</label>
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
        <button type="submit">Filter</button>
    </form>

    <!-- ✅ Listings Table -->
    <?php if ($result && $result->num_rows > 0): ?>
        <form method="post" class="bulk-form">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <table class="admin-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>ID</th>
                        <th>Title</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><input type="checkbox" name="listing_ids[]" value="<?php echo $row['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><span class="badge <?php echo $row['status']; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            <td><?php echo $row['created_at']; ?></td>
                            <td>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="listing_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="action" value="approve">Approve</button>
                                    <button type="submit" name="action" value="postpone">Postpone</button>
                                    <button type="submit" name="action" value="traded">Traded</button>
                                    <button type="submit" name="action" value="delete" onclick="return confirm('Delete this listing?');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- ✅ Bulk Actions -->
            <div class="bulk-actions">
                <select name="bulk_action" required>
                    <option value="">-- Select Action --</option>
                    <option value="approve">Approve</option>
                    <option value="postpone">Postpone</option>
                    <option value="traded">Mark as Traded</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="submit">Apply</button>
            </div>
        </form>

        <!-- ✅ Pagination -->
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="page-link <?php echo $i == $page ? 'active' : ''; ?>" href="?page=<?php echo $i; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php else: ?>
        <p>No listings found.</p>
    <?php endif; ?>

    <!-- ✅ Collapsible Audit Logs -->
    <button id="toggleLogs">Show Audit Logs</button>
    <div id="auditLogs" style="display:none;">
        <?php
        $logs = $conn->query("SELECT a.action, a.listing_id, a.admin_id, a.timestamp 
                              FROM audit_logs a 
                              ORDER BY a.timestamp DESC LIMIT 50");
        if ($logs && $logs->num_rows > 0) {
            echo "<ul>";
            while ($log = $logs->fetch_assoc()) {
                echo "<li>[{$log['timestamp']}] Admin {$log['admin_id']} performed '{$log['action']}' on Listing {$log['listing_id']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No audit logs available.</p>";
        }
        ?>
    </div>

    <?php $conn->close(); ?>

    <script>
    // ✅ Select All functionality
    document.addEventListener("DOMContentLoaded", function() {
        const selectAll = document.getElementById("select-all");
        const checkboxes = document.querySelectorAll("input[name='listing_ids[]']");
        const toggleLogs = document.getElementById("toggleLogs");
        const auditLogs = document.getElementById("auditLogs");

        if (selectAll) {
            selectAll.addEventListener("change", function() {
                checkboxes.forEach(cb => cb.checked = selectAll.checked);
            });
        }

        if (toggleLogs && auditLogs) {
            toggleLogs.addEventListener("click", function() {
                auditLogs.style.display = auditLogs.style.display === "none" ? "block" : "none";
            });
        }
    });
    </script>
</body>
</html>
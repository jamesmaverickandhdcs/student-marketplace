<?php
session_start();
require_once 'db_connect.php';

// ✅ Ensure admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// ✅ Export Listings to CSV
if (isset($_POST['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=listings_export.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Title', 'Seller', 'Status', 'Created At', 'Updated At']);

    $result = $conn->query("SELECT l.id, l.title, u.username, l.status, l.created_at, l.updated_at 
                            FROM listings l 
                            JOIN users u ON l.user_id = u.id");

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// ✅ Fetch listings
$result = $conn->query("SELECT l.id, l.title, u.username, l.status, l.created_at 
                        FROM listings l 
                        JOIN users u ON l.user_id = u.id 
                        ORDER BY l.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Listings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Admin Listings</h1>

    <!-- Export Button -->
    <form method="post">
        <button type="submit" name="export_csv" class="btn-export">Export Listings</button>
    </form>

    <!-- Bulk Actions -->
    <form id="bulkForm" method="post" action="bulk_action.php">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Seller</th>
                    <th>Status</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><input type="checkbox" name="listing_ids[]" value="<?php echo $row['id']; ?>"></td>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <select name="bulk_action" required>
            <option value="">Select Action</option>
            <option value="approve">Approve</option>
            <option value="reject">Reject</option>
            <option value="delete">Delete</option>
        </select>
        <button type="submit" class="btn-bulk">Apply Bulk Action</button>
    </form>

    <script>
    // ✅ Select All toggle
    document.getElementById("selectAll").addEventListener("change", function() {
        document.querySelectorAll("input[name='listing_ids[]']").forEach(cb => cb.checked = this.checked);
    });

    // ✅ Confirmation dialog
    document.getElementById("bulkForm").addEventListener("submit", function(e) {
        e.preventDefault();
        if (confirm("Are you sure you want to apply this bulk action?")) {
            this.submit();
        }
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>
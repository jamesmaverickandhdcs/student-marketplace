<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php';

requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("manage_users.php", "error", "CSRF validation failed");
    }
    $action  = $_POST['action'];
    $user_id = intval($_POST['user_id']);

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        redirectWithMessage("manage_users.php", "success", "User deleted");
    } elseif ($action === 'promote') {
        $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        redirectWithMessage("manage_users.php", "success", "User promoted");
    } elseif ($action === 'demote') {
        $stmt = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        redirectWithMessage("manage_users.php", "success", "Admin demoted");
    }
}

// Pagination + search
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['q']) ? "%" . $_GET['q'] . "%" : "%";

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE username LIKE ? OR email LIKE ?");
$countStmt->bind_param("ss", $search, $search);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$stmt = $conn->prepare("SELECT id, username, email, role 
                        FROM users 
                        WHERE username LIKE ? OR email LIKE ? 
                        ORDER BY id ASC 
                        LIMIT ? OFFSET ?");
$stmt->bind_param("ssii", $search, $search, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Manage Users</title>
  <link rel="stylesheet" href="style.css">
  <script>function confirmAction(msg){return confirm(msg);}</script>
</head>
<body>
<?php include 'header.php'; ?>
<main>
  <h2>Manage Users</h2>
  <a href="export_users.php" class="btn">Export Users (CSV)</a>
  <form method="GET" action="manage_users.php">
    <input type="text" name="q" placeholder="Search by username or email" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    <button type="submit">Search</button>
  </form>
  <table>
    <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Actions</th></tr>
    <?php while ($user = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $user['id']; ?></td>
        <td><?= sanitizeInput($user['username']); ?></td>
        <td><?= sanitizeInput($user['email']); ?></td>
        <td><?= $user['role']; ?></td>
        <td>
          <form method="POST" action="manage_users.php" class="inline-form" onsubmit="return confirmAction('Delete this user?');">
            <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">
            <button type="submit" name="action" value="delete">Delete</button>
          </form>
          <?php if ($user['role'] === 'user'): ?>
            <form method="POST" action="manage_users.php" class="inline-form" onsubmit="return confirmAction('Promote this user to admin?');">
              <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
              <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">
              <button type="submit" name="action" value="promote">Promote</button>
            </form>
          <?php else: ?>
            <form method="POST" action="manage_users.php" class="inline-form" onsubmit="return confirmAction('Demote this admin to user?');">
              <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
              <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">
              <button type="submit" name="action" value="demote">Demote</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
  <div class="pagination">
    <?php if ($page > 1): ?><a href="?q=<?= urlencode($_GET['q'] ?? '') ?>&page=<?= $page-1 ?>">Previous</a><?php endif; ?>
    <?php for ($i=1;$i<=$totalPages;$i++): ?>
      <a href="?q=<?= urlencode($_GET['q'] ?? '') ?>&page=<?= $i ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="?q=<?= urlencode($_GET['q'] ?? '') ?>&page=<?= $page+1 ?>">Next</a><?php endif; ?>
  </div>
</main>
</body>
</html>
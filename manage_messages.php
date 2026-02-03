<?php
session_start();
include 'db.php';
include 'csrf.php';
include 'functions.php';

requireAdmin();

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("manage_messages.php", "error", "CSRF validation failed");
    }
    $message_id = intval($_POST['message_id']);
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    redirectWithMessage("manage_messages.php", "success", "Message deleted");
}

// Pagination + search
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['q']) ? "%" . $_GET['q'] . "%" : "%";

// Count total
$countStmt = $conn->prepare("SELECT COUNT(*) AS total 
                             FROM messages m 
                             JOIN users u1 ON m.sender_id = u1.id 
                             JOIN users u2 ON m.receiver_id = u2.id 
                             WHERE u1.username LIKE ? OR u2.username LIKE ?");
$countStmt->bind_param("ss", $search, $search);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Fetch paginated results
$stmt = $conn->prepare("SELECT m.id, m.content, m.created_at, 
                               u1.username AS sender_name, 
                               u2.username AS receiver_name
                        FROM messages m
                        JOIN users u1 ON m.sender_id = u1.id
                        JOIN users u2 ON m.receiver_id = u2.id
                        WHERE u1.username LIKE ? OR u2.username LIKE ?
                        ORDER BY m.created_at DESC
                        LIMIT ? OFFSET ?");
$stmt->bind_param("ssii", $search, $search, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Manage Messages</title>
  <link rel="stylesheet" href="style.css">
  <script>function confirmAction(msg){return confirm(msg);}</script>
</head>
<body>
<?php include 'header.php'; ?>
<main>
  <h2>Manage Messages</h2>
  <a href="export_messages.php" class="btn">Export Messages (CSV)</a>
  <form method="GET" action="manage_messages.php">
    <input type="text" name="q" placeholder="Search by sender or receiver" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    <button type="submit">Search</button>
  </form>
  <table>
    <tr><th>ID</th><th>Sender</th><th>Receiver</th><th>Content</th><th>Timestamp</th><th>Actions</th></tr>
    <?php while ($msg = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $msg['id']; ?></td>
        <td><?= sanitizeInput($msg['sender_name']); ?></td>
        <td><?= sanitizeInput($msg['receiver_name']); ?></td>
        <td><?= sanitizeInput($msg['content']); ?></td>
        <td><?= $msg['created_at']; ?></td>
        <td>
          <form method="POST" action="manage_messages.php" class="inline-form" onsubmit="return confirmAction('Delete this message?');">
            <input type="hidden" name="message_id" value="<?= $msg['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">
            <button type="submit" name="action" value="delete">Delete</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>

  <!-- Pagination -->
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?q=<?= urlencode($_GET['q'] ?? '') ?>&page=<?= $page-1 ?>">Previous</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?q=<?= urlencode($_GET['q'] ?? '') ?>&page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?q=<?= urlencode($_GET['q'] ?? '') ?>&page=<?= $page+1 ?>">Next</a>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
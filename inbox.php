<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isLoggedIn()) {
    redirectWithMessage("login.html", "error", "You must be logged in to view messages");
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT m.*, u.username AS sender_name 
                        FROM messages m 
                        JOIN users u ON m.sender_id = u.id 
                        WHERE m.receiver_id = ? 
                        ORDER BY m.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Inbox</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<main>
  <h2>Inbox</h2>
  <?php while ($msg = $result->fetch_assoc()): ?>
    <div class="message">
      <strong><?= sanitizeInput($msg['sender_name']); ?>:</strong>
      <?= sanitizeInput($msg['content']); ?>
      <em>(<?= $msg['created_at']; ?>)</em>
    </div>
  <?php endwhile; ?>
</main>
</body>
</html>
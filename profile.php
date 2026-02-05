<?php
session_start();
require_once 'db_connect.php';
require_once 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Notifications
$notifStmt = $conn->prepare("SELECT id, message, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notifStmt->bind_param("i", $user_id);
$notifStmt->execute();
$notifications = $notifStmt->get_result();

// Favorites
$favStmt = $conn->prepare("SELECT l.id, l.title, l.status, u.username 
                           FROM favorites f
                           JOIN listings l ON f.listing_id = l.id
                           JOIN users u ON l.user_id = u.id
                           WHERE f.user_id = ?
                           ORDER BY f.created_at DESC");
$favStmt->bind_param("i", $user_id);
$favStmt->execute();
$favorites = $favStmt->get_result();

// Messages
$msgStmt = $conn->prepare("SELECT m.id, m.content, m.created_at, u.username AS sender
                           FROM messages m
                           JOIN users u ON m.sender_id = u.id
                           WHERE m.receiver_id = ?
                           ORDER BY m.created_at DESC");
$msgStmt->bind_param("i", $user_id);
$msgStmt->execute();
$inbox = $msgStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main role="main">
        <h1>Welcome to Your Dashboard</h1>

        <!-- Notifications -->
        <section class="notifications" aria-labelledby="notif-heading">
            <h2 id="notif-heading">Notifications</h2>
            <?php if ($notifications->num_rows > 0): ?>
                <?php while ($n = $notifications->fetch_assoc()): ?>
                    <div class="notification <?php echo $n['is_read'] ? 'read' : 'unread'; ?>" role="status" aria-live="polite">
                        <?php echo htmlspecialchars($n['message']); ?>
                        <span class="time"><?php echo $n['created_at']; ?></span>
                        <?php if (!$n['is_read']): ?>
                            <form method="post" action="mark_read.php" class="inline-form">
                                <input type="hidden" name="notif_id" value="<?php echo $n['id']; ?>">
                                <button type="submit" aria-label="Mark notification as read">Mark Read</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No notifications yet.</p>
            <?php endif; ?>
        </section>

        <!-- Favorites -->
        <section class="favorites" aria-labelledby="fav-heading">
            <h2 id="fav-heading">My Favorites</h2>
            <?php if ($favorites->num_rows > 0): ?>
                <ul>
                    <?php while ($fav = $favorites->fetch_assoc()): ?>
                        <li>
                            <?php echo htmlspecialchars($fav['title']); ?> 
                            (<?php echo htmlspecialchars($fav['status']); ?> by <?php echo htmlspecialchars($fav['username']); ?>)
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p>No favorites yet.</p>
            <?php endif; ?>
        </section>

        <!-- Messages -->
        <section class="messages" aria-labelledby="msg-heading">
            <h2 id="msg-heading">Inbox</h2>
            <?php if ($inbox->num_rows > 0): ?>
                <?php while ($msg = $inbox->fetch_assoc()): ?>
                    <div class="message" role="article">
                        <p><strong><?php echo htmlspecialchars($msg['sender']); ?>:</strong> 
                           <?php echo htmlspecialchars($msg['content']); ?></p>
                        <span class="time"><?php echo $msg['created_at']; ?></span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No messages yet.</p>
            <?php endif; ?>
        </section>
    </main>

    <!-- Back to Top -->
    <button id="backToTop" aria-label="Scroll back to top">↑ Back to Top</button>

    <script>
    document.getElementById("backToTop").addEventListener("click", function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>
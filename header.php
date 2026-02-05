<?php
session_start();
require_once 'db_connect.php';

// ✅ Secure session check
$isAdmin = isset($_SESSION['admin_logged_in']);
$isUser  = isset($_SESSION['user_id']);

// ✅ Fetch unread counts (messages + notifications)
$unreadMessages = 0;
$unreadNotifications = 0;

if ($isUser) {
    // Count unread messages
    $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($unreadMessages);
    $stmt->fetch();
    $stmt->close();

    // Count unread notifications
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($unreadNotifications);
    $stmt->fetch();
    $stmt->close();
}
?>
<header>
    <h1>Student Marketplace</h1>
    <nav>
        <ul>
            <?php if ($isUser): ?>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="favorites.php">Favorites</a></li>
                <li><a href="messages.php">Messages<?php if ($unreadMessages > 0) echo " ({$unreadMessages})"; ?></a></li>
                <li><a href="notifications.php">Notifications<?php if ($unreadNotifications > 0) echo " ({$unreadNotifications})"; ?></a></li>
                <li><a href="listings.php">Listings</a></li>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <li><a href="admin_listings.php">Admin Listings</a></li>
                <li><a href="audit_logs.php">Audit Logs</a></li>
            <?php endif; ?>

            <li>
                <form method="post" action="logout.php" class="inline-form">
                    <button type="submit" class="btn-logout">Logout</button>
                </form>
            </li>
        </ul>
    </nav>
</header>
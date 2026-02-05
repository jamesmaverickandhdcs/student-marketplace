<?php
require_once 'session_init.php'; // secure session start
require_once 'db_connect.php';
require_once 'csrf.php';

$isAdmin = isset($_SESSION['admin_logged_in']);
$isUser  = isset($_SESSION['user_id']);

$unreadMessages = 0;
$unreadNotifications = 0;

if ($isUser) {
    // Messages count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($unreadMessages);
    $stmt->fetch();
    $stmt->close();

    // Notifications count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($unreadNotifications);
    $stmt->fetch();
    $stmt->close();
}
?>
<header id="main-header" role="banner">
    <h1 id="site-title">Student Marketplace</h1>
    <nav id="main-nav" role="navigation" aria-label="Main Navigation">
        <ul id="nav-list">
            <?php if ($isUser): ?>
                <li><a id="nav-profile" href="profile.php" aria-label="Go to Profile page">Profile</a></li>
                <li><a id="nav-favorites" href="favorites.php" aria-label="View Favorites">Favorites</a></li>
                <li><a id="nav-messages" href="messages.php" aria-label="View Messages">
                    Messages (<span id="unread-messages" aria-live="polite"><?php echo $unreadMessages; ?></span>)
                </a></li>
                <li><a id="nav-notifications" href="notifications.php" aria-label="View Notifications">
                    Notifications (<span id="unread-notifications" aria-live="polite"><?php echo $unreadNotifications; ?></span>)
                </a></li>
                <li><a id="nav-listings" href="listings.php" aria-label="Browse Listings">Listings</a></li>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <li><a id="nav-admin-listings" href="admin_listings.php" aria-label="Admin Listings">Admin Listings</a></li>
                <li><a id="nav-audit-logs" href="audit_logs.php" aria-label="Audit Logs">Audit Logs</a></li>
            <?php endif; ?>

            <li>
                <form method="post" action="logout.php" class="inline-form" id="logout-form">
                    <?php echo csrf_input(); ?>
                    <button type="submit" class="btn-logout" id="btn-logout" aria-label="Logout">Logout</button>
                </form>
            </li>
            <li>
                <button id="darkModeToggle" aria-pressed="false" aria-label="Toggle Dark Mode">🌙 Dark Mode</button>
            </li>
        </ul>
    </nav>
</header>

<script>
// ✅ Dark Mode Toggle
document.getElementById("darkModeToggle").addEventListener("click", function() {
    const isDark = document.body.classList.toggle("dark-mode");
    this.setAttribute("aria-pressed", isDark);
});

// ✅ Poll counts every 10s
function updateCounts() {
    fetch("get_counts.php")
        .then(res => res.json())
        .then(data => {
            document.getElementById("unread-notifications").textContent = data.unreadNotifications;
            document.getElementById("unread-messages").textContent = data.unreadMessages;
        });
}
setInterval(updateCounts, 10000);
</script>
<?php
session_start();
include 'csrf.php';
?>
<header>
  <h1>Student Marketplace</h1>
  <nav>
    <ul>
      <li><a href="index.html">Home</a></li>
      <li><a href="listings.php">Listings</a></li>

      <?php if (!isset($_SESSION['user_id'])): ?>
        <li><a href="register.html">Register</a></li>
        <li><a href="login.html">Login</a></li>
      <?php else: ?>
        <li><a href="profile.php">My Profile (<?= htmlspecialchars($_SESSION['username']); ?>)</a></li>
        <li>
          <form method="POST" action="logout.php" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= generateToken(); ?>">
            <button type="submit" class="btn-logout">Logout</button>
          </form>
        </li>
      <?php endif; ?>

      <li><button id="darkModeToggle" class="btn-toggle">🌙 Dark Mode</button></li>
    </ul>
  </nav>

  <!-- ✅ Unified Floating Notification System -->
  <?php if (isset($_GET['success'])): ?>
    <div class="notification success" aria-live="polite">✅ <?= htmlspecialchars($_GET['success']); ?></div>
  <?php elseif (isset($_GET['error'])): ?>
    <div class="notification error" aria-live="polite">❌ <?= htmlspecialchars($_GET['error']); ?></div>
  <?php endif; ?>
</header>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    const notifications = document.querySelectorAll(".notification");
    notifications.forEach((note, index) => {
      setTimeout(() => { note.remove(); }, 5000 + (index * 1000));
    });
  });
</script>
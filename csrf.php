<?php
// ✅ Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ Helper function to embed CSRF token in forms
function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}
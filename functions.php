<?php
/**
 * Common utility functions for Student Marketplace
 */

/**
 * Redirect with a success or error message.
 *
 * @param string $url     Destination URL
 * @param string $type    Either 'success' or 'error'
 * @param string $message Message to display
 */
function redirectWithMessage($url, $type, $message) {
    $param = $type . "=" . urlencode($message);
    header("Location: " . $url . "?" . $param);
    exit;
}

/**
 * Sanitize user input for safe output.
 *
 * @param string $data Raw input
 * @return string Sanitized string
 */
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in.
 *
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if current user is an admin.
 *
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Safe redirect to login if not logged in.
 *
 * @param string $redirectUrl URL to redirect after login
 */
function requireLogin($redirectUrl = "login.html") {
    if (!isLoggedIn()) {
        redirectWithMessage($redirectUrl, "error", "You must be logged in");
    }
}

/**
 * Safe redirect to profile if not admin.
 *
 * @param string $redirectUrl URL to redirect if not admin
 */
function requireAdmin($redirectUrl = "profile.php") {
    if (!isAdmin()) {
        redirectWithMessage($redirectUrl, "error", "Access denied");
    }
}
?>
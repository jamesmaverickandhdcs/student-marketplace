<?php
session_start();
include 'csrf.php';
include 'functions.php'; // contains redirectWithMessage()

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyToken($_POST['csrf_token'])) {
        redirectWithMessage("index.html", "error", "CSRF validation failed");
    }

    // Clear session
    $_SESSION = [];
    session_unset();
    session_destroy();

    // Remove session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    redirectWithMessage("index.html", "success", "You have been logged out successfully");
} else {
    redirectWithMessage("index.html", "error", "Invalid logout request");
}
?>
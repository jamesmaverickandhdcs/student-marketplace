<?php
// ✅ Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // only if HTTPS
ini_set('session.use_strict_mode', 1);

session_start();

// ✅ Regenerate session ID periodically
if (!isset($_SESSION['last_regen'])) {
    $_SESSION['last_regen'] = time();
} elseif (time() - $_SESSION['last_regen'] > 300) { // every 5 minutes
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}
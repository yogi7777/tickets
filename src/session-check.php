<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config.php';

if (!isset($_SESSION['user'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /azure-login.php');
    exit;
}

// Session-Timeout prüfen (8 Stunden)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
    session_unset();
    session_destroy();
    setcookie(session_name(), '', ['expires' => 1, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    header('Location: /azure-login.php');
    exit;
}
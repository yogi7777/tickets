<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config.php';

if (!isset($_SESSION['user'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /azure-login.php');
    exit;
}
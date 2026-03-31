<?php
// logout.php

session_start();

// Sitzung beenden
session_unset();
session_destroy();

// Session-Cookie explizit löschen
setcookie(session_name(), '', [
    'expires'  => 1,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Weiterleitung zur Login-Seite
header('Location: login.php');
exit();


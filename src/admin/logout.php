<?php
// logout.php

session_start();

// Beende die Sitzung
session_unset();
session_destroy();

// Weiterleitung zur Login-Seite
header('Location: login.php');
exit();
?>

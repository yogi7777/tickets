<?php

//Azure Configs

define('CLIENT_ID', '');
define('CLIENT_SECRET', '');
define('TENANT_ID', '');
define('REDIRECT_URI', 'https://your-domain.tld/auth-callback.php');
define('APP_ORIGIN', 'https://your-domain.tld'); // Basis-URL der Anwendung (kein trailing slash)
define('AUTHORITY', 'https://login.microsoftonline.com/' . TENANT_ID);
define('AUTHORIZE_ENDPOINT', AUTHORITY . '/oauth2/v2.0/authorize');
define('TOKEN_ENDPOINT', AUTHORITY . '/oauth2/v2.0/token');
define('JWKS_URI', AUTHORITY . '/discovery/v2.0/keys');
define('SCOPE', 'openid profile email');
define('SESSION_LIFETIME', 28800); // 8 Stunden in Sekunden

// Sichere Session-Cookie-Einstellungen vor session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

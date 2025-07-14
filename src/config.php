<?php

//Azure Configs

define('CLIENT_ID', '');
define('CLIENT_SECRET', '');
define('TENANT_ID', '');
define('REDIRECT_URI', 'https://YOURURL/auth-callback.php');
define('AUTHORITY', 'https://login.microsoftonline.com/' . TENANT_ID);
define('AUTHORIZE_ENDPOINT', AUTHORITY . '/oauth2/v2.0/authorize');
define('TOKEN_ENDPOINT', AUTHORITY . '/oauth2/v2.0/token');
define('SCOPE', 'openid profile email');
session_start();

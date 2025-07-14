<?php
require_once __DIR__ . '/config.php';

$params = [
    'client_id' => CLIENT_ID,
    'response_type' => 'code',
    'redirect_uri' => REDIRECT_URI,
    'response_mode' => 'query',
    'scope' => SCOPE,
    'state' => 'xyz' // optional
];

$authorizeUrl = AUTHORIZE_ENDPOINT . '?' . http_build_query($params);
header("Location: $authorizeUrl");
exit;

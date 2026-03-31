<?php
require_once __DIR__ . '/config.php';

// Kryptografisch sicherer State-Parameter gegen CSRF im OAuth-Flow
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Nonce gegen Replay-Angriffe auf das ID-Token
$nonce = bin2hex(random_bytes(16));
$_SESSION['oauth_nonce'] = $nonce;

$params = [
    'client_id'     => CLIENT_ID,
    'response_type' => 'code',
    'redirect_uri'  => REDIRECT_URI,
    'response_mode' => 'query',
    'scope'         => SCOPE,
    'state'         => $state,
    'nonce'         => $nonce,
];

$authorizeUrl = AUTHORIZE_ENDPOINT . '?' . http_build_query($params);
header("Location: $authorizeUrl");
exit;

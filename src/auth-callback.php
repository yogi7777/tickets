<?php
require_once __DIR__ . '/config.php';

if (!isset($_GET['code'])) {
    echo 'Kein Code erhalten.';
    exit;
}

$code = $_GET['code'];

$data = [
    'client_id' => CLIENT_ID,
    'client_secret' => CLIENT_SECRET,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => REDIRECT_URI,
    'scope' => SCOPE
];

$ch = curl_init(TOKEN_ENDPOINT);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$token = json_decode($response, true);

if (!isset($token['id_token'])) {
    echo 'Token konnte nicht geholt werden.';
    exit;
}

// ID Token decodieren
$id_token_parts = explode('.', $token['id_token']);
$userinfo = json_decode(base64_decode(strtr($id_token_parts[1], '-_', '+/')), true);

// Session setzen
$_SESSION['user'] = [
    'name' => $userinfo['name'],
    'email' => $userinfo['preferred_username'],
];

$redirect = $_SESSION['redirect_after_login'] ?? '/index.php';
unset($_SESSION['redirect_after_login']);

header("Location: $redirect");
exit;
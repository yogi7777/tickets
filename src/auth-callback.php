<?php
require_once __DIR__ . '/config.php';

// ── 1. State-Parameter validieren (CSRF-Schutz) ──────────────────────────────
if (!isset($_GET['state'], $_SESSION['oauth_state'])
    || !hash_equals($_SESSION['oauth_state'], $_GET['state'])
) {
    unset($_SESSION['oauth_state'], $_SESSION['oauth_nonce']);
    http_response_code(400);
    exit('Ungültiger State-Parameter.');
}
unset($_SESSION['oauth_state']);

// ── 2. Authorization Code prüfen ─────────────────────────────────────────────
if (!isset($_GET['code'])) {
    http_response_code(400);
    exit('Kein Code erhalten.');
}
$code = $_GET['code'];

// ── 3. Token vom Authorization Server holen ──────────────────────────────────
$postData = http_build_query([
    'client_id'     => CLIENT_ID,
    'client_secret' => CLIENT_SECRET,
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => REDIRECT_URI,
    'scope'         => SCOPE,
]);

$ch = curl_init(TOKEN_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT        => 10,
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $response === false) {
    http_response_code(502);
    exit('Token-Endpunkt nicht erreichbar.');
}

$token = json_decode($response, true);
if (!isset($token['id_token'])) {
    http_response_code(502);
    exit('Kein ID-Token erhalten.');
}

// ── 4. JWT-Signatur validieren ────────────────────────────────────────────────
function fetchJwks(): array {
    $ch = curl_init(JWKS_URI);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body, true) ?? [];
}

function getPublicKeyFromKid(string $kid): mixed {
    $jwks = fetchJwks();
    foreach ($jwks['keys'] ?? [] as $key) {
        if (($key['kid'] ?? '') !== $kid || ($key['kty'] ?? '') !== 'RSA') {
            continue;
        }
        // Azure liefert x5c (Zertifikatskette) – daraus Public Key extrahieren
        if (!empty($key['x5c'][0])) {
            $pem = "-----BEGIN CERTIFICATE-----\n"
                 . chunk_split($key['x5c'][0], 64, "\n")
                 . "-----END CERTIFICATE-----";
            return openssl_pkey_get_public($pem);
        }
    }
    return false;
}

function verifyIdToken(string $id_token): array|false {
    $parts = explode('.', $id_token);
    if (count($parts) !== 3) return false;

    [$headerB64, $payloadB64, $signatureB64] = $parts;

    $header    = json_decode(base64_decode(strtr($headerB64, '-_', '+/')), true);
    $payload   = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);
    $signature = base64_decode(strtr($signatureB64, '-_', '+/'));

    if (!$header || !$payload) return false;
    if (($header['alg'] ?? '') !== 'RS256') return false;

    $publicKey = getPublicKeyFromKid($header['kid'] ?? '');
    if (!$publicKey) return false;

    $data   = $headerB64 . '.' . $payloadB64;
    $result = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);

    if ($result !== 1) return false;

    return $payload;
}

$payload = verifyIdToken($token['id_token']);
if ($payload === false) {
    http_response_code(401);
    exit('ID-Token-Signatur ungültig.');
}

// ── 5. Claims validieren ──────────────────────────────────────────────────────
$expectedIssuer = 'https://login.microsoftonline.com/' . TENANT_ID . '/v2.0';

if (($payload['iss'] ?? '') !== $expectedIssuer) {
    http_response_code(401);
    exit('Ungültiger Issuer.');
}

if (($payload['aud'] ?? '') !== CLIENT_ID) {
    http_response_code(401);
    exit('Ungültige Audience.');
}

if (($payload['exp'] ?? 0) < time()) {
    http_response_code(401);
    exit('Token abgelaufen.');
}

// Nonce validieren (Replay-Schutz)
$expectedNonce = $_SESSION['oauth_nonce'] ?? '';
unset($_SESSION['oauth_nonce']);
if (!$expectedNonce || !hash_equals($expectedNonce, $payload['nonce'] ?? '')) {
    http_response_code(401);
    exit('Ungültige Nonce.');
}

// ── 6. Session sichern und befüllen ──────────────────────────────────────────
session_regenerate_id(true); // Session-Fixation verhindern

$_SESSION['user'] = [
    'name'  => $payload['name'] ?? '',
    'email' => $payload['preferred_username'] ?? $payload['email'] ?? '',
];
$_SESSION['login_time'] = time();

// ── 7. Redirect mit Whitelist (Open-Redirect verhindern) ──────────────────────
$redirect = $_SESSION['redirect_after_login'] ?? '/index.php';
unset($_SESSION['redirect_after_login']);

// Nur relative Pfade erlaubt (kein Protokoll, kein externer Host)
if (!preg_match('#^/[a-zA-Z0-9/_\-\.]*$#', $redirect)) {
    $redirect = '/index.php';
}

header("Location: $redirect");
exit;

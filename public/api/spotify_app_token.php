<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../../src/config.php';

$spotify_client_id = (string)($config['spotify_client_id'] ?? '');
$spotify_client_secret = (string)($config['spotify_client_secret'] ?? '');

if ($spotify_client_id === '' || $spotify_client_secret === '') {
    http_response_code(500);
    echo json_encode(['error' => 'spotify_client_id_or_secret_missing']);
    exit;
}

if (isset($_SESSION['spotify_app_token'], $_SESSION['spotify_app_token_expires_at_ms'])) {
    $expires_at_ms = (int)$_SESSION['spotify_app_token_expires_at_ms'];
    if ($expires_at_ms > (int)(microtime(true) * 1000) + 10_000) {
        echo json_encode([
            'access_token' => (string)$_SESSION['spotify_app_token'],
            'expires_in' => (int)(($expires_at_ms - (int)(microtime(true) * 1000)) / 1000),
            'token_type' => 'Bearer',
        ]);
        exit;
    }
}

$basic_auth = base64_encode($spotify_client_id . ':' . $spotify_client_secret);

$ch = curl_init('https://accounts.spotify.com/api/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $spotify_client_id,
        'client_secret' => $spotify_client_secret,
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);

$response_body = curl_exec($ch);
$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response_body === false) {
    http_response_code(502);
    echo json_encode(['error' => 'curl_failed', 'detail' => $curl_error]);
    exit;
}

if ($http_code < 200 || $http_code >= 300) {
    http_response_code($http_code);
    echo $response_body;
    exit;
}

$token_json = json_decode($response_body, true);
if (!is_array($token_json) || !isset($token_json['access_token'], $token_json['expires_in'])) {
    http_response_code(502);
    echo json_encode(['error' => 'invalid_token_response', 'raw' => $response_body]);
    exit;
}

$_SESSION['spotify_app_token'] = (string)$token_json['access_token'];
$_SESSION['spotify_app_token_expires_at_ms'] = (int)(microtime(true) * 1000) + ((int)$token_json['expires_in'] * 1000);

echo json_encode($token_json);
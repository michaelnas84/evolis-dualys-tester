<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../../src/config.php';
require __DIR__ . '/../../src/helpers.php';
require __DIR__ . '/../../src/compositor_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$raw_body = file_get_contents('php://input');
if ($raw_body === false || trim($raw_body) === '') {
    jsonResponse(['ok' => false, 'error' => 'Empty request body.'], 400);
}

$payload = json_decode($raw_body, true);
if (!is_array($payload)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON.'], 400);
}

$csrf_token = (string)($payload['csrf_token'] ?? '');
$session_csrf_token = (string)($_SESSION['csrf_token'] ?? '');
if ($csrf_token === '' || $session_csrf_token === '' || !hash_equals($session_csrf_token, $csrf_token)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid CSRF token.'], 403);
}

$preview_only = (bool)($payload['preview_only'] ?? false);
$person_name = (string)($payload['person_name'] ?? '');
$artist_name = (string)($payload['artist_name'] ?? '');
$track_name = (string)($payload['track_name'] ?? '');
$photo_data_url = (string)($payload['photo_data_url'] ?? '');

if ($photo_data_url === '') {
    jsonResponse(['ok' => false, 'error' => 'Missing photo_data_url.'], 400);
}

$allowed_image_mime_types = (array)$config['allowed_image_mime_types'];
$max_image_bytes = (int)$config['max_image_bytes'];

try {
    $parsed_photo = parseDataUrlImage($photo_data_url);
    $photo_mime_type = (string)$parsed_photo['mime_type'];
    $photo_binary_data = (string)$parsed_photo['binary_data'];

    if (!in_array($photo_mime_type, $allowed_image_mime_types, true)) {
        throw new RuntimeException('Unsupported photo mime_type: ' . $photo_mime_type);
    }
    if (strlen($photo_binary_data) > $max_image_bytes) {
        throw new RuntimeException('Photo image too large.');
    }

    $compositor_config = loadCompositorConfig();
    $result = composeFinalImageDataUrl(
        $compositor_config,
        $photo_data_url,
        $person_name,
        $artist_name,
        $track_name,
        $preview_only
    );

    // Preview do dashboard: mantém data_url (ok ser grande, uso pontual)
    if ($preview_only) {
        jsonResponse([
            'ok' => true,
            'final_image_data_url' => (string)$result['final_image_data_url'],
            'frame_file_name' => (string)$result['frame_file_name'],
        ]);
    }

    // Fluxo do kiosk: NÃO devolve base64. Salva no servidor e retorna só a chave.
    $final_parsed = parseDataUrlImage((string)$result['final_image_data_url']);
    $final_binary = (string)$final_parsed['binary_data'];

    if (strlen($final_binary) > $max_image_bytes) {
        throw new RuntimeException('Final image too large.');
    }

    $composed_dir = __DIR__ . '/../../storage/composed';
    ensureDirectoryExists($composed_dir);

    $composed_file_name = 'composed_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.png';
    $composed_file_path = joinPath($composed_dir, $composed_file_name);

    writeFileAtomic($composed_file_path, $final_binary);

    jsonResponse([
        'ok' => true,
        'composed_image_key' => $composed_file_name,
        'frame_file_name' => (string)$result['frame_file_name'],
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}

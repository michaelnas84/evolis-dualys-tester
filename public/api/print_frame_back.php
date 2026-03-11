<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../../src/config.php';
require __DIR__ . '/../../src/helpers.php';

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

$frame_name = trim((string)($payload['frame_name'] ?? ''));
if ($frame_name === '') {
    jsonResponse(['ok' => false, 'error' => 'Missing frame_name.'], 400);
}

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $frame_name)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid frame_name.'], 400);
}

$back_frame_name = str_replace('front', 'back', $frame_name);
$back_frame_file_path = __DIR__ . '/../frames/' . $back_frame_name . '.png';

if (!is_file($back_frame_file_path)) {
    jsonResponse(['ok' => false, 'error' => 'Back frame file not found.'], 404);
}

try {
    $hotfolder_in_path = (string)$config['hotfolder_in_path'];
    ensureDirectoryExists($hotfolder_in_path);

    $job_id = generateJobId((string)$config['job_name_prefix']);
    $job_folder_path = joinPath($hotfolder_in_path, $job_id);
    ensureDirectoryExists($job_folder_path);

    $back_binary_data = (string)file_get_contents($back_frame_file_path);
    if ($back_binary_data === '') {
        throw new RuntimeException('Failed to read back frame.');
    }

    $front_file_name = 'front.png';
    $front_file_path = joinPath($job_folder_path, $front_file_name);
    writeFileAtomic($front_file_path, $back_binary_data);

    $manifest = [
        'printer_name' => (string)$config['default_printer_name'],
        'copies' => 1,
        'duplex' => 'false',
        'fit_mode' => (string)$config['default_fit_mode'],
        'rotate_degrees' => (int)$config['default_rotate_degrees'],
        'card_size_mm' => [
            'width_mm' => (float)$config['card_size_mm']['width_mm'],
            'height_mm' => (float)$config['card_size_mm']['height_mm'],
        ],
        'front_file' => $front_file_name,
    ];

    $manifest_path = joinPath($job_folder_path, 'manifest.json');
    writeFileAtomic(
        $manifest_path,
        json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    jsonResponse([
        'ok' => true,
        'job_id' => $job_id,
        'job_folder_path' => $job_folder_path,
        'printed_back_frame_name' => $back_frame_name,
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}

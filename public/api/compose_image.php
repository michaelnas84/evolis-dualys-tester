<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../../src/config.php';
require __DIR__ . '/../../src/helpers.php';
require __DIR__ . '/../../src/compositor_helpers.php';

function saveComposedImageFromDataUrl(string $image_data_url, int $max_image_bytes, string $output_file_path): void
{
    $parsed_image = parseDataUrlImage($image_data_url);
    $image_binary_data = (string)$parsed_image['binary_data'];

    if (strlen($image_binary_data) > $max_image_bytes) {
        throw new RuntimeException('Composed image too large: ' . basename($output_file_path));
    }

    writeFileAtomic($output_file_path, $image_binary_data);
}

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
$frame_name = (string)($payload['frame_name'] ?? '');
$frame_name = __DIR__ . '/../public/frames/' . $frame_name;

$frame_pair_info = [
    'front_file_path' => $frame_name,
    'back_file_path' => str_replace($frame_name, 'front', 'back'),
];

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

    $result = composeFinalImageDataUrls(
        $compositor_config,
        $photo_data_url,
        $person_name,
        $artist_name,
        $track_name,
        $preview_only,
        $frame_pair_info
    );

    $front_image_data_url = (string)($result['front_image_data_url'] ?? $result['final_image_data_url'] ?? '');
    $back_image_data_url = (string)($result['back_image_data_url'] ?? '');
    $frame_pair_key = (string)($result['frame_pair_key'] ?? '');
    $front_frame_file_name = (string)($result['front_frame_file_name'] ?? $result['frame_file_name'] ?? '');
    $back_frame_file_name = (string)($result['back_frame_file_name'] ?? '');

    if ($front_image_data_url === '') {
        throw new RuntimeException('Composer result is missing front image data.');
    }

    if ($preview_only) {
        jsonResponse([
            'ok' => true,
            'front_image_data_url' => $front_image_data_url,
            'back_image_data_url' => $back_image_data_url,
            'frame_pair_key' => $frame_pair_key,
            'front_frame_file_name' => $front_frame_file_name,
            'back_frame_file_name' => $back_frame_file_name,

            // Backward compatibility
            'final_image_data_url' => $front_image_data_url,
            'frame_file_name' => $front_frame_file_name,
        ]);
    }

    $print_output_directory = (string)($config['hot_folder_directory'] ?? (__DIR__ . '/../../storage/composed'));
    ensureDirectoryExists($print_output_directory);

    $print_job_key = 'print_job_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8));

    $front_output_file_name = $print_job_key . '_front.png';
    $front_output_file_path = joinPath($print_output_directory, $front_output_file_name);

    saveComposedImageFromDataUrl(
        $front_image_data_url,
        $max_image_bytes,
        $front_output_file_path
    );

    $back_output_file_name = '';
    if ($back_image_data_url !== '') {
        $back_output_file_name = $print_job_key . '_back.png';
        $back_output_file_path = joinPath($print_output_directory, $back_output_file_name);

        saveComposedImageFromDataUrl(
            $back_image_data_url,
            $max_image_bytes,
            $back_output_file_path
        );
    }

    jsonResponse([
        'ok' => true,
        'print_job_key' => $print_job_key,
        'front_image_key' => $front_output_file_name,
        'back_image_key' => $back_output_file_name,
        'frame_pair_key' => $frame_pair_key,
        'front_frame_file_name' => $front_frame_file_name,
        'back_frame_file_name' => $back_frame_file_name,

        // Backward compatibility
        'frame_file_name' => $front_frame_file_name,
        'composed_image_key' => $front_output_file_name,
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}
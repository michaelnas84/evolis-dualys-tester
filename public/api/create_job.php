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

$front_composed_image_key = (string)($payload['front_composed_image_key'] ?? '');
$back_composed_image_key = (string)($payload['back_composed_image_key'] ?? '');

$print_mode = (string)($payload['print_mode'] ?? '');
$front_image_data_url = (string)($payload['front_image_data_url'] ?? '');
$back_image_data_url = (string)($payload['back_image_data_url'] ?? '');

if (!in_array($print_mode, ['front_only', 'front_and_back'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid print_mode.'], 400);
}

if ($front_image_data_url === '' && $front_composed_image_key === '') {
    jsonResponse(['ok' => false, 'error' => 'Missing front image (data_url or composed key).'], 400);
}

if ($print_mode === 'front_and_back' && $back_image_data_url === '' && $back_composed_image_key === '') {
    jsonResponse(['ok' => false, 'error' => 'Missing back image (data_url or composed key) for front_and_back.'], 400);
}

$allowed_image_mime_types = (array)$config['allowed_image_mime_types'];
$max_image_bytes = (int)$config['max_image_bytes'];

try {
    $hotfolder_in_path = (string)$config['hotfolder_in_path'];
    ensureDirectoryExists($hotfolder_in_path);

    $job_id = generateJobId((string)$config['job_name_prefix']);
    $job_folder_path = joinPath($hotfolder_in_path, $job_id);
    ensureDirectoryExists($job_folder_path);

    $front_binary_data = '';
    $front_mime_type = 'image/png';

    if ($front_composed_image_key !== '') {
        if (preg_match('/[\/\\\\]/', $front_composed_image_key)) {
            throw new RuntimeException('Invalid composed key.');
        }

        $composed_dir = __DIR__ . '/../../storage/composed';
        $composed_path = joinPath($composed_dir, $front_composed_image_key);

        if (!is_file($composed_path)) {
            throw new RuntimeException('Composed image not found.');
        }

        $front_binary_data = (string)file_get_contents($composed_path);

        if ($front_binary_data === '') {
            throw new RuntimeException('Failed to read composed image.');
        }

        // Delete after consume to avoid accumulation
        @unlink($composed_path);
    } else {
        $front_parsed = parseDataUrlImage($front_image_data_url);
        $front_mime_type = (string)$front_parsed['mime_type'];
        $front_binary_data = (string)$front_parsed['binary_data'];
    }

    if (!in_array($front_mime_type, $allowed_image_mime_types, true)) {
        throw new RuntimeException('Unsupported front image mime_type: ' . $front_mime_type);
    }

    if (strlen($front_binary_data) > $max_image_bytes) {
        throw new RuntimeException('Front image too large.');
    }

    $front_extension = $front_mime_type === 'image/png' ? '.png' : '.jpg';
    $front_file_name = 'front' . $front_extension;
    $front_file_path = joinPath($job_folder_path, $front_file_name);
    writeFileAtomic($front_file_path, $front_binary_data);

    $back_file_name = null;

    if ($print_mode !== 'front_and_back' && $back_composed_image_key !== '') {
        if (preg_match('/[\/\\\\]/', $back_composed_image_key)) {
            throw new RuntimeException('Invalid back composed key.');
        }

        $unused_back_composed_path = joinPath(__DIR__ . '/../../storage/composed', $back_composed_image_key);
        if (is_file($unused_back_composed_path)) {
            @unlink($unused_back_composed_path);
        }
    }

    if ($print_mode === 'front_and_back') {
        $back_binary_data = '';
        $back_mime_type = 'image/png';

        if ($back_composed_image_key !== '') {
            if (preg_match('/[\/\\\\]/', $back_composed_image_key)) {
                throw new RuntimeException('Invalid composed key.');
            }

            $composed_dir = __DIR__ . '/../../storage/composed';
            $composed_path = joinPath($composed_dir, $back_composed_image_key);

            if (!is_file($composed_path)) {
                throw new RuntimeException('Back composed image not found.');
            }

            $back_binary_data = (string)file_get_contents($composed_path);

            if ($back_binary_data === '') {
                throw new RuntimeException('Failed to read back composed image.');
            }

            // Delete after consume to avoid accumulation
            @unlink($composed_path);
        } else {
            $back_parsed = parseDataUrlImage($back_image_data_url);
            $back_mime_type = (string)$back_parsed['mime_type'];
            $back_binary_data = (string)$back_parsed['binary_data'];
        }

        if (!in_array($back_mime_type, $allowed_image_mime_types, true)) {
            throw new RuntimeException('Unsupported back image mime_type: ' . $back_mime_type);
        }

        if (strlen($back_binary_data) > $max_image_bytes) {
            throw new RuntimeException('Back image too large.');
        }

        $back_extension = $back_mime_type === 'image/png' ? '.png' : '.jpg';
        $back_file_name = 'back' . $back_extension;
        $back_file_path = joinPath($job_folder_path, $back_file_name);
        writeFileAtomic($back_file_path, $back_binary_data);
    }

    $manifest = [
        'printer_name' => (string)$config['default_printer_name'],
        'copies' => 1,
        'duplex' => $print_mode === 'front_and_back' ? 'true' : 'false',
        'fit_mode' => (string)$config['default_fit_mode'],
        'rotate_degrees' => (int)$config['default_rotate_degrees'],
        'card_size_mm' => [
            'width_mm' => (float)$config['card_size_mm']['width_mm'],
            'height_mm' => (float)$config['card_size_mm']['height_mm'],
        ],
        'front_file' => $front_file_name,
    ];

    if ($back_file_name !== null) {
        $manifest['back_file'] = $back_file_name;
    }

    $manifest_path = joinPath($job_folder_path, 'manifest.json');
    writeFileAtomic(
        $manifest_path,
        json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    jsonResponse([
        'ok' => true,
        'job_id' => $job_id,
        'job_folder_path' => $job_folder_path,
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}

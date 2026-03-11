<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../../src/helpers.php';
require __DIR__ . '/../../src/compositor_helpers.php';

if (!(bool)($_SESSION['admin_unlocked'] ?? false)) {
    jsonResponse(['ok' => false, 'error' => 'Not authorized.'], 403);
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

$compositor_config_update = $payload['compositor_config'] ?? null;
if (!is_array($compositor_config_update)) {
    jsonResponse(['ok' => false, 'error' => 'Missing compositor_config.'], 400);
}

try {
    $compositor_config = loadCompositorConfig();

    // Minimal validation
    if (!isset($compositor_config_update['photo_box']) || !is_array($compositor_config_update['photo_box'])) {
        throw new RuntimeException('photo_box missing.');
    }
    if (!isset($compositor_config_update['text_fields']) || !is_array($compositor_config_update['text_fields'])) {
        throw new RuntimeException('text_fields missing.');
    }

    $override_file_path = (string)$compositor_config['override_file_path'];

    $allowed_keys = ['photo_box', 'text_fields', 'font_file_path', 'text_color_rgb', 'back_first_print_enabled'];
    $filtered = [];
    foreach ($allowed_keys as $key) {
        if (array_key_exists($key, $compositor_config_update)) {
            $filtered[$key] = $compositor_config_update[$key];
        }
    }

    $encoded = json_encode($filtered, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!is_string($encoded)) {
        throw new RuntimeException('Failed to encode config.');
    }

    writeFileAtomic($override_file_path, $encoded);

    jsonResponse(['ok' => true]);
} catch (Throwable $exception) {
    jsonResponse(['ok' => false, 'error' => $exception->getMessage()], 500);
}

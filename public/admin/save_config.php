<?php
declare(strict_types=1);

require __DIR__ . '/../../src/helpers.php';
require __DIR__ . '/../../src/compositor_helpers.php';

ensureAdminUnlockedJson();

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
    $base_compositor_config = require __DIR__ . '/../../src/compositor_config.php';
    $current_override_config = loadCompositorOverrideConfig($base_compositor_config);

    if (isset($compositor_config_update['photo_box']) && !is_array($compositor_config_update['photo_box'])) {
        throw new RuntimeException('photo_box must be an object.');
    }
    if (isset($compositor_config_update['text_fields']) && !is_array($compositor_config_update['text_fields'])) {
        throw new RuntimeException('text_fields must be an object.');
    }

    $override_file_path = (string)$base_compositor_config['override_file_path'];
    $merged_override_config = array_replace_recursive($current_override_config, $compositor_config_update);

    $encoded = json_encode($merged_override_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!is_string($encoded)) {
        throw new RuntimeException('Failed to encode config.');
    }

    writeFileAtomic($override_file_path, $encoded);

    jsonResponse(['ok' => true]);
} catch (Throwable $exception) {
    jsonResponse(['ok' => false, 'error' => $exception->getMessage()], 500);
}

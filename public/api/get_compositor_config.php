<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../../src/helpers.php';
require __DIR__ . '/../../src/compositor_helpers.php';

try {
    $compositor_config = loadCompositorConfig();

    $public_config = [
        'photo_box' => (array)$compositor_config['photo_box'],
        'text_fields' => [
            'person_name' => [
                'max_chars' => (int)$compositor_config['text_fields']['person_name']['max_chars'],
            ],
            'artist_name' => [
                'max_chars' => (int)$compositor_config['text_fields']['artist_name']['max_chars'],
            ],
            'track_name' => [
                'max_chars' => (int)$compositor_config['text_fields']['track_name']['max_chars'],
            ],
        ],
    ];

    jsonResponse([
        'ok' => true,
        'compositor_config' => $public_config,
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}

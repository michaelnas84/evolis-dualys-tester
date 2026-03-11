<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/compositor_helpers.php';

function buildPublicTextFieldConfig(array $base_text_fields, array $side_text_fields, string $field_name): array
{
    $base_field_config = (array)($base_text_fields[$field_name] ?? []);
    $side_field_config = (array)($side_text_fields[$field_name] ?? []);

    return [
        'max_chars' => (int)($side_field_config['max_chars'] ?? $base_field_config['max_chars'] ?? 40),
        'box' => array_replace(
            (array)($base_field_config['box'] ?? []),
            (array)($side_field_config['box'] ?? [])
        ),
    ];
}

try {
    $compositor_config = loadCompositorConfig();

    $base_photo_box = (array)($compositor_config['photo_box'] ?? []);
    $base_text_fields = (array)($compositor_config['text_fields'] ?? []);
    $side_layouts = (array)($compositor_config['side_layouts'] ?? []);

    $front_side_layout = (array)($side_layouts['front'] ?? []);
    $back_side_layout = (array)($side_layouts['back'] ?? []);

    $front_photo_box = array_replace(
        $base_photo_box,
        (array)($front_side_layout['photo_box'] ?? [])
    );

    $back_photo_box = array_replace(
        $base_photo_box,
        (array)($back_side_layout['photo_box'] ?? [])
    );

    $front_text_fields = [
        'person_name' => buildPublicTextFieldConfig(
            $base_text_fields,
            (array)($front_side_layout['text_fields'] ?? []),
            'person_name'
        ),
        'artist_name' => buildPublicTextFieldConfig(
            $base_text_fields,
            (array)($front_side_layout['text_fields'] ?? []),
            'artist_name'
        ),
        'track_name' => buildPublicTextFieldConfig(
            $base_text_fields,
            (array)($front_side_layout['text_fields'] ?? []),
            'track_name'
        ),
    ];

    $back_text_fields = [
        'person_name' => buildPublicTextFieldConfig(
            $base_text_fields,
            (array)($back_side_layout['text_fields'] ?? []),
            'person_name'
        ),
        'artist_name' => buildPublicTextFieldConfig(
            $base_text_fields,
            (array)($back_side_layout['text_fields'] ?? []),
            'artist_name'
        ),
        'track_name' => buildPublicTextFieldConfig(
            $base_text_fields,
            (array)($back_side_layout['text_fields'] ?? []),
            'track_name'
        ),
    ];

    $public_config = [
        'print_mode' => (string)($compositor_config['print_mode'] ?? 'front_only'),
        'back_first_print_enabled' => (bool)($compositor_config['back_first_print_enabled'] ?? false),

        // Backward compatibility for old frontend code
        'photo_box' => $front_photo_box,
        'text_fields' => $front_text_fields,

        // New frontend structure
        'side_layouts' => [
            'front' => [
                'photo_box' => $front_photo_box,
                'text_fields' => $front_text_fields,
            ],
            'back' => [
                'photo_box' => $back_photo_box,
                'text_fields' => $back_text_fields,
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

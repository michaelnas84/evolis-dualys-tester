<?php
declare(strict_types=1);

return [
    // Folder under public/ that contains the frame PNGs.
    'frames_directory' => __DIR__ . '/../public/frames',

    // Persistent state (rotates frame pairs sequentially).
    'frame_state_file_path' => __DIR__ . '/../storage/frame_state.json',

    // Config overrides saved by the dashboard.
    'override_file_path' => __DIR__ . '/../storage/compositor_config.json',

    // Password hash file for admin unlock.
    'admin_password_hash_file_path' => __DIR__ . '/../storage/admin_password_hash.txt',

    // Default admin password (only used to bootstrap admin_password_hash.txt).
    'default_admin_password' => 'admin123',

    // Print mode used by the kiosk frontend/job creation flow.
    'print_mode' => 'front_and_back',

    // When true, the back side also receives photo + dynamic texts.
    'compose_back_with_dynamic_content' => false,

    // Base layout.
    // Keep this aligned with the front side for backward compatibility.
    'photo_box' => [
        'x' => 454,
        'y' => 478,
        'width' => 464,
        'height' => 545,
        'oversize_factor' => 1.08,
    ],

    'text_fields' => [
        'person_name' => [
            'box' => [
                'x' => 200,
                'y' => 1064,
                'width' => 978,
                'height' => 101,
            ],
            'max_chars' => 26,
            'font_size' => 54,
        ],
        'artist_name' => [
            'box' => [
                'x' => 200,
                'y' => 1262,
                'width' => 472,
                'height' => 54,
            ],
            'max_chars' => 20,
            'font_size' => 26,
        ],
        'track_name' => [
            'box' => [
                'x' => 698,
                'y' => 1262,
                'width' => 479,
                'height' => 51,
            ],
            'max_chars' => 22,
            'font_size' => 26,
        ],
    ],

    // Side-specific layout overrides.
    // Start with the same values on both sides.
    // Adjust the back side later if its frame artwork needs different positions.
    'side_layouts' => [
        'front' => [
            'photo_box' => [
                'x' => 454,
                'y' => 478,
                'width' => 464,
                'height' => 545,
                'oversize_factor' => 1.08,
            ],
            'text_fields' => [
                'person_name' => [
                    'box' => [
                        'x' => 200,
                        'y' => 1064,
                        'width' => 978,
                        'height' => 101,
                    ],
                    'max_chars' => 26,
                    'font_size' => 54,
                ],
                'artist_name' => [
                    'box' => [
                        'x' => 200,
                        'y' => 1262,
                        'width' => 472,
                        'height' => 54,
                    ],
                    'max_chars' => 20,
                    'font_size' => 26,
                ],
                'track_name' => [
                    'box' => [
                        'x' => 698,
                        'y' => 1262,
                        'width' => 479,
                        'height' => 51,
                    ],
                    'max_chars' => 22,
                    'font_size' => 26,
                ],
            ],
        ],
        'back' => [
            'photo_box' => [
                'x' => 454,
                'y' => 478,
                'width' => 464,
                'height' => 545,
                'oversize_factor' => 1.08,
            ],
            'text_fields' => [
                'person_name' => [
                    'box' => [
                        'x' => 200,
                        'y' => 1064,
                        'width' => 978,
                        'height' => 101,
                    ],
                    'max_chars' => 26,
                    'font_size' => 54,
                ],
                'artist_name' => [
                    'box' => [
                        'x' => 200,
                        'y' => 1262,
                        'width' => 472,
                        'height' => 54,
                    ],
                    'max_chars' => 20,
                    'font_size' => 26,
                ],
                'track_name' => [
                    'box' => [
                        'x' => 698,
                        'y' => 1262,
                        'width' => 479,
                        'height' => 51,
                    ],
                    'max_chars' => 22,
                    'font_size' => 26,
                ],
            ],
        ],
    ],

    // Path to a TTF font file.
    'font_file_path' => 'C:\\projetos\\evolis-dualys-tester\\public\\fonts\\DejaVuSans-Bold.ttf',

    // Text color.
    'text_color_rgb' => [
        'r' => 20,
        'g' => 20,
        'b' => 20,
    ],

    'imagemagick_binary_path' => 'C:\\Program Files\\ImageMagick-7.1.2-Q16-HDRI\\magick.exe',
];
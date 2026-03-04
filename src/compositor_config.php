<?php
declare(strict_types=1);

return [
    // Folder under public/ that contains the frame PNGs.
    'frames_directory' => __DIR__ . '/../public/frames',

    // Persistent state (rotates frames sequentially).
    'frame_state_file_path' => __DIR__ . '/../storage/frame_state.json',

    // Config overrides saved by the dashboard.
    'override_file_path' => __DIR__ . '/../storage/compositor_config.json',

    // Password hash file for admin unlock.
    'admin_password_hash_file_path' => __DIR__ . '/../storage/admin_password_hash.txt',

    // Default admin password (only used to bootstrap admin_password_hash.txt).
    'default_admin_password' => 'admin123',

    // Defaults based on frame-01.png auto-detection.
    'photo_box' => [
        'x' => 506,
        'y' => 576,
        'width' => 366,
        'height' => 433,
        'oversize_factor' => 1.08,
    ],

    'text_fields' => [
        'person_name' => [
            'box' => ['x' => 276, 'y' => 1053, 'width' => 826, 'height' => 98],
            'max_chars' => 26,
            'font_size' => 54,
        ],
        'artist_name' => [
            'box' => ['x' => 274, 'y' => 1223, 'width' => 404, 'height' => 51],
            'max_chars' => 20,
            'font_size' => 26,
        ],
        'track_name' => [
            'box' => ['x' => 700, 'y' => 1223, 'width' => 405, 'height' => 51],
            'max_chars' => 22,
            'font_size' => 26,
        ],
    ],

    // Path to a TTF font file.
    'font_file_path' => 'C:\\projetos\\evolis-dualys-tester\\public\\fonts\\DejaVuSans-Bold.ttf',

    // Text color
    'text_color_rgb' => ['r' => 20, 'g' => 20, 'b' => 20],

    'imagemagick_binary_path' => 'C:\\Program Files\\ImageMagick-7.1.2-Q16-HDRI\\magick.exe',
];

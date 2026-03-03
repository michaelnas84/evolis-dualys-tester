<?php
declare(strict_types=1);

return [
    // This must match the folder monitored by your hotfolder script.
    'hotfolder_in_path' => 'C:\\card_hotfolder\\in',

    // Used in manifest.json (your hotfolder script can read it).
    'default_printer_name' => 'Evolis Dualys',

    // Safety limits
    'max_image_bytes' => 8 * 1024 * 1024,
    'allowed_image_mime_types' => ['image/jpeg', 'image/png'],

    // Job naming
    'job_name_prefix' => 'job',

    // Default printing options written into manifest.json
    'default_fit_mode' => 'fill',   // contain | fill | stretch
    'default_rotate_degrees' => 0,  // 0 | 90 | 180 | 270

    // CR80 by default
    'card_size_mm' => [
        'width_mm' => 85.6,
        'height_mm' => 53.98,
    ],
];
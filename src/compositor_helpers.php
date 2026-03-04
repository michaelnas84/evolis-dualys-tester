<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function loadCompositorConfig(): array
{
    $base = require __DIR__ . '/compositor_config.php';

    ensureAdminPasswordHashFile($base);

    $override_file_path = (string)$base['override_file_path'];
    if (is_file($override_file_path)) {
        $raw = file_get_contents($override_file_path);
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $base = array_replace_recursive($base, $decoded);
            }
        }
    }

    return $base;
}

function ensureAdminPasswordHashFile(array $base_config): void
{
    $hash_file_path = (string)$base_config['admin_password_hash_file_path'];
    if (is_file($hash_file_path)) {
        return;
    }

    $default_password = (string)$base_config['default_admin_password'];
    $hash = password_hash($default_password, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Failed to generate admin password hash.');
    }

    writeFileAtomic($hash_file_path, $hash);
}

function verifyAdminPassword(array $compositor_config, string $password): bool
{
    $hash_file_path = (string)$compositor_config['admin_password_hash_file_path'];
    $hash = is_file($hash_file_path) ? file_get_contents($hash_file_path) : false;
    if (!is_string($hash) || trim($hash) === '') {
        return false;
    }
    return password_verify($password, trim($hash));
}

function listFrameFiles(string $frames_directory): array
{
    if (!is_dir($frames_directory)) {
        return [];
    }

    $files = array_values(array_filter(scandir($frames_directory) ?: [], function ($file_name) use ($frames_directory) {
        if (!is_string($file_name)) {
            return false;
        }
        if ($file_name === '.' || $file_name === '..') {
            return false;
        }
        $full_path = joinPath($frames_directory, $file_name);
        if (!is_file($full_path)) {
            return false;
        }
        return (bool)preg_match('/\\.png$/i', $file_name);
    }));

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}

function chooseNextFrameFile(array $compositor_config, bool $preview_only): array
{
    $frames_directory = (string)$compositor_config['frames_directory'];
    $frame_files = listFrameFiles($frames_directory);
    if (count($frame_files) === 0) {
        throw new RuntimeException('No frame PNGs found in frames_directory.');
    }

    $state_file_path = (string)$compositor_config['frame_state_file_path'];
    ensureDirectoryExists(dirname($state_file_path));

    $fp = fopen($state_file_path, 'c+');
    if ($fp === false) {
        throw new RuntimeException('Failed to open frame_state_file_path.');
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            throw new RuntimeException('Failed to lock frame_state_file_path.');
        }

        $raw = stream_get_contents($fp);
        $state = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
        if (!is_array($state)) {
            $state = [];
        }

        $last_frame_file = isset($state['last_frame_file']) ? (string)$state['last_frame_file'] : '';
        $current_index = array_search($last_frame_file, $frame_files, true);
        $next_index = $current_index === false ? 0 : (($current_index + 1) % count($frame_files));

        $selected_frame_file = $frame_files[$preview_only ? ($current_index === false ? 0 : $current_index) : $next_index];

        if (!$preview_only) {
            $state['last_frame_file'] = $selected_frame_file;
            $encoded = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (!is_string($encoded)) {
                throw new RuntimeException('Failed to encode frame state.');
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $encoded);
            fflush($fp);
        }

        flock($fp, LOCK_UN);

        return [
            'frame_file_name' => $selected_frame_file,
            'frame_file_path' => joinPath($frames_directory, $selected_frame_file),
        ];
    } finally {
        fclose($fp);
    }
}

function safeTrimToMaxChars(string $value, int $max_chars): string
{
    $value = trim($value);
    if ($max_chars <= 0) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max_chars);
    }

    return substr($value, 0, $max_chars);
}

function runCommandOrThrow(array $command_parts): string
{
    $escaped = array_map('escapeshellarg', $command_parts);
    $command = implode(' ', $escaped);

    $descriptor_spec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptor_spec, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start ImageMagick process.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exit_code = proc_close($process);
    if ($exit_code !== 0) {
        throw new RuntimeException('ImageMagick error: ' . trim((string)$stderr));
    }

    return trim((string)$stdout);
}

function detectImageSize(string $image_path): array
{
    $output = runCommandOrThrow(['/opt/imagemagick/bin/magick', 'identify', '-format', '%w %h', $image_path]);
    $parts = preg_split('/\s+/', trim($output));
    if (!is_array($parts) || count($parts) < 2) {
        throw new RuntimeException('Failed to detect image size.');
    }

    return [
        'width' => (int)$parts[0],
        'height' => (int)$parts[1],
    ];
}

function measureTextSize(string $text, string $font_file_path, int $font_size): array
{
    $output = runCommandOrThrow([
        '/opt/imagemagick/bin/magick',
        '-background', 'none',
        '-fill', 'black',
        '-font', $font_file_path,
        '-pointsize', (string)$font_size,
        'label:' . $text,
        '-format', '%w %h',
        'info:'
    ]);

    $parts = preg_split('/\s+/', trim($output));
    if (!is_array($parts) || count($parts) < 2) {
        return ['width' => 0, 'height' => 0];
    }

    return [
        'width' => (int)$parts[0],
        'height' => (int)$parts[1],
    ];
}

function fitFontSizeForBox(string $text, string $font_file_path, int $initial_font_size, int $box_width, int $box_height): int
{
    $font_size = $initial_font_size;
    $min_font_size = 10;

    while ($font_size > $min_font_size) {
        $size = measureTextSize($text, $font_file_path, $font_size);
        if ($size['width'] <= ($box_width - 8) && $size['height'] <= ($box_height - 4)) {
            break;
        }
        $font_size -= 1;
    }

    return $font_size;
}

function buildTextImage(string $text, array $box, string $font_file_path, int $font_size, array $color_rgb, string $output_path): void
{
    $box_w = (int)$box['width'];
    $box_h = (int)$box['height'];

    if ($text === '') {
        runCommandOrThrow([
            '/opt/imagemagick/bin/magick',
            '-size', (string)$box_w . 'x' . (string)$box_h,
            'xc:none',
            $output_path
        ]);
        return;
    }

    $fitted_font_size = fitFontSizeForBox($text, $font_file_path, $font_size, $box_w, $box_h);
    $fill = sprintf('rgb(%d,%d,%d)', (int)$color_rgb['r'], (int)$color_rgb['g'], (int)$color_rgb['b']);

    runCommandOrThrow([
        '/opt/imagemagick/bin/magick',
        '-background', 'none',
        '-fill', $fill,
        '-font', $font_file_path,
        '-pointsize', (string)$fitted_font_size,
        'label:' . $text,
        '-gravity', 'center',
        '-extent', (string)$box_w . 'x' . (string)$box_h,
        $output_path
    ]);
}

function buildProcessedPhoto(string $photo_path, array $photo_box, float $oversize_factor, string $output_path): void
{
    $target_w = (int)$photo_box['width'];
    $target_h = (int)$photo_box['height'];

    $oversize_w = (int)round($target_w * max(1.0, $oversize_factor));
    $oversize_h = (int)round($target_h * max(1.0, $oversize_factor));

    runCommandOrThrow([
        '/opt/imagemagick/bin/magick',
        $photo_path,
        '-auto-orient',
        '-resize', (string)$oversize_w . 'x' . (string)$oversize_h . '^',
        '-gravity', 'center',
        '-extent', (string)$oversize_w . 'x' . (string)$oversize_h,
        '-gravity', 'center',
        '-crop', (string)$target_w . 'x' . (string)$target_h . '+0+0',
        '+repage',
        $output_path
    ]);
}

function composeFinalImageDataUrl(array $compositor_config, string $photo_data_url, string $person_name, string $artist_name, string $track_name, bool $preview_only): array
{
    $frame_info = chooseNextFrameFile($compositor_config, $preview_only);
    $frame_file_path = (string)$frame_info['frame_file_path'];
    $frame_file_name = (string)$frame_info['frame_file_name'];

    $size = detectImageSize($frame_file_path);
    $width = (int)$size['width'];
    $height = (int)$size['height'];

    $font_file_path = (string)$compositor_config['font_file_path'];
    if (!is_file($font_file_path)) {
        throw new RuntimeException('Font not found: ' . $font_file_path);
    }

    $color_rgb = (array)$compositor_config['text_color_rgb'];

    $text_fields = (array)$compositor_config['text_fields'];

    $person_cfg = (array)($text_fields['person_name'] ?? []);
    $artist_cfg = (array)($text_fields['artist_name'] ?? []);
    $track_cfg = (array)($text_fields['track_name'] ?? []);

    $person_text = safeTrimToMaxChars($person_name, (int)($person_cfg['max_chars'] ?? 0));
    $artist_text = safeTrimToMaxChars($artist_name, (int)($artist_cfg['max_chars'] ?? 0));
    $track_text = safeTrimToMaxChars($track_name, (int)($track_cfg['max_chars'] ?? 0));

    $storage_directory = dirname((string)$compositor_config['frame_state_file_path']);
    ensureDirectoryExists($storage_directory);

    $photo_tmp_path = tempnam($storage_directory, 'photo_');
    if ($photo_tmp_path === false) {
        throw new RuntimeException('Failed to create temp file.');
    }
    $photo_tmp_path_with_ext = $photo_tmp_path . '.bin';
    rename($photo_tmp_path, $photo_tmp_path_with_ext);

    $processed_photo_stub_path = tempnam($storage_directory, 'processed_photo_');
    if ($processed_photo_stub_path === false) {
        throw new RuntimeException('Failed to create temp file.');
    }
    $processed_photo_path = $processed_photo_stub_path . '.png';
    rename($processed_photo_stub_path, $processed_photo_path);

    $person_text_stub_path = tempnam($storage_directory, 'person_text_');
    if ($person_text_stub_path === false) {
        throw new RuntimeException('Failed to create temp file.');
    }
    $person_text_path = $person_text_stub_path . '.png';
    rename($person_text_stub_path, $person_text_path);

    $artist_text_stub_path = tempnam($storage_directory, 'artist_text_');
    if ($artist_text_stub_path === false) {
        throw new RuntimeException('Failed to create temp file.');
    }
    $artist_text_path = $artist_text_stub_path . '.png';
    rename($artist_text_stub_path, $artist_text_path);

    $track_text_stub_path = tempnam($storage_directory, 'track_text_');
    if ($track_text_stub_path === false) {
        throw new RuntimeException('Failed to create temp file.');
    }
    $track_text_path = $track_text_stub_path . '.png';
    rename($track_text_stub_path, $track_text_path);

    $output_stub_path = tempnam($storage_directory, 'final_');
    if ($output_stub_path === false) {
        throw new RuntimeException('Failed to create temp file.');
    }
    $output_path = $output_stub_path . '.png';
    rename($output_stub_path, $output_path);

    try {
        $parsed_photo = parseDataUrlImage($photo_data_url);
        writeFileAtomic($photo_tmp_path_with_ext, (string)$parsed_photo['binary_data']);

        $photo_box = (array)$compositor_config['photo_box'];
        $oversize_factor = (float)($photo_box['oversize_factor'] ?? 1.0);
        buildProcessedPhoto($photo_tmp_path_with_ext, $photo_box, $oversize_factor, $processed_photo_path);

        buildTextImage($person_text, (array)$person_cfg['box'], $font_file_path, (int)($person_cfg['font_size'] ?? 24), $color_rgb, $person_text_path);
        buildTextImage($artist_text, (array)$artist_cfg['box'], $font_file_path, (int)($artist_cfg['font_size'] ?? 18), $color_rgb, $artist_text_path);
        buildTextImage($track_text, (array)$track_cfg['box'], $font_file_path, (int)($track_cfg['font_size'] ?? 18), $color_rgb, $track_text_path);

        $photo_x = (int)$photo_box['x'];
        $photo_y = (int)$photo_box['y'];

        $person_box = (array)$person_cfg['box'];
        $artist_box = (array)$artist_cfg['box'];
        $track_box = (array)$track_cfg['box'];

        runCommandOrThrow([
            '/opt/imagemagick/bin/magick',
            '-size', (string)$width . 'x' . (string)$height,
            'xc:none',
            $processed_photo_path,
            '-geometry', '+' . (string)$photo_x . '+' . (string)$photo_y,
            '-composite',
            $frame_file_path,
            '-composite',
            $person_text_path,
            '-geometry', '+' . (string)((int)$person_box['x']) . '+' . (string)((int)$person_box['y']),
            '-composite',
            $artist_text_path,
            '-geometry', '+' . (string)((int)$artist_box['x']) . '+' . (string)((int)$artist_box['y']),
            '-composite',
            $track_text_path,
            '-geometry', '+' . (string)((int)$track_box['x']) . '+' . (string)((int)$track_box['y']),
            '-composite',
            $output_path,
        ]);

        $png_binary = file_get_contents($output_path);
        if (!is_string($png_binary) || $png_binary === '') {
            throw new RuntimeException('Failed to read final PNG.');
        }

        $data_url = 'data:image/png;base64,' . base64_encode($png_binary);
    } finally {
        @unlink($photo_tmp_path_with_ext);
        @unlink($processed_photo_path);
        @unlink($person_text_path);
        @unlink($artist_text_path);
        @unlink($track_text_path);
        @unlink($output_path);
    }

    return [
        'final_image_data_url' => $data_url,
        'frame_file_name' => $frame_file_name,
    ];
}

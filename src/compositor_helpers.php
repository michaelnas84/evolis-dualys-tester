<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function loadJsonFileArray(string $file_path): array
{
    if (!is_file($file_path)) {
        return [];
    }

    $raw = file_get_contents($file_path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function loadCompositorOverrideConfig(?array $base_config = null): array
{
    $base_config = $base_config ?? require __DIR__ . '/compositor_config.php';
    $override_file_path = (string)$base_config['override_file_path'];

    return loadJsonFileArray($override_file_path);
}

function loadCompositorConfig(): array
{
    $base = require_once __DIR__ . '/compositor_config.php';

    ensureAdminPasswordHashFile($base);

    $base = array_replace_recursive($base, loadCompositorOverrideConfig($base));

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

function resolveImagemagickBinary(array $compositor_config): string
{
    $env_path = getenv('IMAGEMAGICK_BINARY');
    if (is_string($env_path) && trim($env_path) !== '') {
        return trim($env_path);
    }

    $configured_path = (string)($compositor_config['imagemagick_binary_path'] ?? '');
    if (trim($configured_path) !== '') {
        return trim($configured_path);
    }

    return 'magick';
}

function normalizeProcessOutput(string $value): string
{
    $value = trim($value);

    if (function_exists('mb_convert_encoding')) {
        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }

    return $value;
}

function runCommandOrThrow(array $command_parts, ?string $working_directory = null): string
{
    $descriptor_spec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $options = [
        'bypass_shell' => true,
    ];

    $process = @proc_open($command_parts, $descriptor_spec, $pipes, $working_directory, null, $options);
    if (!is_resource($process)) {
        $debug_command = implode(' ', array_map('strval', $command_parts));
        throw new RuntimeException('Failed to start ImageMagick process. Command: ' . $debug_command);
    }

    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';

    fclose($pipes[1]);
    fclose($pipes[2]);

    $exit_code = proc_close($process);

    $stdout = normalizeProcessOutput($stdout);
    $stderr = normalizeProcessOutput($stderr);

    if ($exit_code !== 0) {
        $debug_command = implode(' ', array_map('strval', $command_parts));
        $combined_output = trim($stderr !== '' ? $stderr : $stdout);
        throw new RuntimeException(
            'ImageMagick error (exit ' . $exit_code . '): ' . $combined_output . ' | Command: ' . $debug_command
        );
    }

    return trim($stdout);
}

function detectImageSize(array $compositor_config, string $image_path): array
{
    $magick_binary = resolveImagemagickBinary($compositor_config);
    $output = runCommandOrThrow([$magick_binary, 'identify', '-format', '%w %h', $image_path]);

    $parts = preg_split('/\s+/', trim($output));
    if (!is_array($parts) || count($parts) < 2) {
        throw new RuntimeException('Failed to detect image size.');
    }

    return [
        'width' => (int)$parts[0],
        'height' => (int)$parts[1],
    ];
}

function measureTextSize(array $compositor_config, string $text, string $font_file_path, int $font_size): array
{
    $magick_binary = resolveImagemagickBinary($compositor_config);

    $output = runCommandOrThrow([
        $magick_binary,
        '-background', 'none',
        '-fill', 'black',
        '-font', $font_file_path,
        '-pointsize', (string)$font_size,
        'label:' . $text,
        '-format', '%w %h',
        'info:',
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

function fitFontSizeForBox(
    array $compositor_config,
    string $text,
    string $font_file_path,
    int $initial_font_size,
    int $box_width,
    int $box_height
): int {
    $font_size = $initial_font_size;
    $min_font_size = 10;

    while ($font_size > $min_font_size) {
        $size = measureTextSize($compositor_config, $text, $font_file_path, $font_size);
        if ($size['width'] <= ($box_width - 8) && $size['height'] <= ($box_height - 4)) {
            break;
        }

        $font_size -= 1;
    }

    return $font_size;
}

function buildTextImage(
    array $compositor_config,
    string $text,
    array $box,
    string $font_file_path,
    int $font_size,
    array $color_rgb,
    string $output_path
): void {
    $magick_binary = resolveImagemagickBinary($compositor_config);

    $box_width = (int)$box['width'];
    $box_height = (int)$box['height'];

    if ($text === '') {
        runCommandOrThrow([$magick_binary, '-size', $box_width . 'x' . $box_height, 'xc:none', $output_path]);
        return;
    }

    $fitted_font_size = fitFontSizeForBox(
        $compositor_config,
        $text,
        $font_file_path,
        $font_size,
        $box_width,
        $box_height
    );

    $fill = sprintf(
        'rgb(%d,%d,%d)',
        (int)$color_rgb['r'],
        (int)$color_rgb['g'],
        (int)$color_rgb['b']
    );

    runCommandOrThrow([
        $magick_binary,
        '-background', 'none',
        '-fill', $fill,
        '-font', $font_file_path,
        '-weight', '700',
        '-pointsize', (string)$fitted_font_size,
        'label:' . $text,
        '-gravity', 'center',
        '-extent', $box_width . 'x' . $box_height,
        $output_path,
    ]);
}

function resolveFontFilePath(string $font_file_path): string
{
    $normalized_font_file_path = trim($font_file_path);
    if ($normalized_font_file_path === '') {
        throw new RuntimeException('Font path is empty.');
    }

    if (!is_file($normalized_font_file_path)) {
        throw new RuntimeException('Font not found: ' . $normalized_font_file_path);
    }

    return normalizeImagemagickPath($normalized_font_file_path);
}

function normalizeImagemagickPath(string $path): string
{
    $normalized_path = trim($path);
    if ($normalized_path === '') {
        return '';
    }

    $real_path = realpath($normalized_path);
    if ($real_path !== false) {
        $normalized_path = $real_path;
    }

    return str_replace('\\', '/', $normalized_path);
}

function buildProcessedPhoto(
    array $compositor_config,
    string $photo_path,
    array $photo_box,
    float $oversize_factor,
    string $output_path
): void {
    $magick_binary = resolveImagemagickBinary($compositor_config);

    $target_width = (int)$photo_box['width'];
    $target_height = (int)$photo_box['height'];

    $oversize_width = (int)round($target_width * max(1.0, $oversize_factor));
    $oversize_height = (int)round($target_height * max(1.0, $oversize_factor));

    runCommandOrThrow([
        $magick_binary,
        $photo_path,
        '-auto-orient',
        '-resize', $oversize_width . 'x' . $oversize_height . '^',
        '-gravity', 'center',
        '-extent', $oversize_width . 'x' . $oversize_height,
        '-gravity', 'center',
        '-crop', $target_width . 'x' . $target_height . '+0+0',
        '+repage',
        $output_path,
    ]);
}

function createTempFileWithExtension(string $directory, string $prefix, string $extension): string
{
    $temp_path = tempnam($directory, $prefix);
    if ($temp_path === false) {
        throw new RuntimeException('Failed to create temp file.');
    }

    $target_path = $temp_path . $extension;
    if (!rename($temp_path, $target_path)) {
        @unlink($temp_path);
        throw new RuntimeException('Failed to rename temp file.');
    }

    return $target_path;
}

function buildImageDataUrlFromFile(string $image_path): string
{
    $binary_data = file_get_contents($image_path);
    if (!is_string($binary_data) || $binary_data === '') {
        throw new RuntimeException('Failed to read image file: ' . $image_path);
    }

    return 'data:image/png;base64,' . base64_encode($binary_data);
}

function composeImageDataUrlUsingFrame(
    array $compositor_config,
    string $photo_data_url,
    string $person_name,
    string $artist_name,
    string $track_name,
    string $frame_file_path
): string {
    $magick_binary = resolveImagemagickBinary($compositor_config);

    $size = detectImageSize($compositor_config, $frame_file_path);
    $width = (int)$size['width'];
    $height = (int)$size['height'];

    $default_font_file_path = resolveFontFilePath((string)$compositor_config['font_file_path']);

    $color_rgb = (array)$compositor_config['text_color_rgb'];
    $text_fields = (array)$compositor_config['text_fields'];

    $person_config = (array)($text_fields['person_name'] ?? []);
    $artist_config = (array)($text_fields['artist_name'] ?? []);
    $track_config = (array)($text_fields['track_name'] ?? []);

    $person_text = safeTrimToMaxChars($person_name, (int)($person_config['max_chars'] ?? 0));
    $artist_text = safeTrimToMaxChars($artist_name, (int)($artist_config['max_chars'] ?? 0));
    $track_text = safeTrimToMaxChars($track_name, (int)($track_config['max_chars'] ?? 0));

    $storage_directory = dirname((string)$compositor_config['frame_state_file_path']);
    ensureDirectoryExists($storage_directory);

    $photo_tmp_path_with_ext = createTempFileWithExtension($storage_directory, 'photo_', '.bin');
    $processed_photo_path = createTempFileWithExtension($storage_directory, 'processed_photo_', '.png');
    $person_text_path = createTempFileWithExtension($storage_directory, 'person_text_', '.png');
    $artist_text_path = createTempFileWithExtension($storage_directory, 'artist_text_', '.png');
    $track_text_path = createTempFileWithExtension($storage_directory, 'track_text_', '.png');
    $output_path = createTempFileWithExtension($storage_directory, 'final_', '.png');

    try {
        $parsed_photo = parseDataUrlImage($photo_data_url);
        writeFileAtomic($photo_tmp_path_with_ext, (string)$parsed_photo['binary_data']);

        $photo_box = (array)$compositor_config['photo_box'];
        $oversize_factor = (float)($photo_box['oversize_factor'] ?? 1.0);

        buildProcessedPhoto(
            $compositor_config,
            $photo_tmp_path_with_ext,
            $photo_box,
            $oversize_factor,
            $processed_photo_path
        );

        buildTextImage(
            $compositor_config,
            $person_text,
            (array)$person_config['box'],
            resolveFontFilePath((string)($person_config['font_file_path'] ?? $default_font_file_path)),
            (int)($person_config['font_size'] ?? 24),
            $color_rgb,
            $person_text_path
        );

        buildTextImage(
            $compositor_config,
            $artist_text,
            (array)$artist_config['box'],
            resolveFontFilePath((string)($person_config['font_file_path'] ?? $default_font_file_path)),
            (int)($artist_config['font_size'] ?? 18),
            $color_rgb,
            $artist_text_path
        );

        buildTextImage(
            $compositor_config,
            $track_text,
            (array)$track_config['box'],
            resolveFontFilePath((string)($person_config['font_file_path'] ?? $default_font_file_path)),
            (int)($track_config['font_size'] ?? 18),
            $color_rgb,
            $track_text_path
        );

        $photo_box = (array)$compositor_config['photo_box'];
        $photo_x = (int)$photo_box['x'];
        $photo_y = (int)$photo_box['y'];

        $person_box = (array)$person_config['box'];
        $artist_box = (array)$artist_config['box'];
        $track_box = (array)$track_config['box'];

        runCommandOrThrow([
            $magick_binary,
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

        return buildImageDataUrlFromFile($output_path);
    } finally {
        @unlink($photo_tmp_path_with_ext);
        @unlink($processed_photo_path);
        @unlink($person_text_path);
        @unlink($artist_text_path);
        @unlink($track_text_path);
        @unlink($output_path);
    }
}

function composeFinalImageDataUrls(
    array $compositor_config,
    string $photo_data_url,
    string $person_name,
    string $artist_name,
    string $track_name,
    bool $preview_only,
    array $frame_pair_info
): array {
    $front_frame_file_path = (string)$frame_pair_info['front_file_path'];
    $back_frame_file_path = (string)$frame_pair_info['back_file_path'];

    $front_image_data_url = composeImageDataUrlUsingFrame(
        $compositor_config,
        $photo_data_url,
        $person_name,
        $artist_name,
        $track_name,
        $front_frame_file_path,
        'front'
    );

    $compose_back_with_dynamic_content = (bool)($compositor_config['compose_back_with_dynamic_content'] ?? false);

    if ($compose_back_with_dynamic_content) {
        $back_image_data_url = composeImageDataUrlUsingFrame(
            $compositor_config,
            $photo_data_url,
            $person_name,
            $artist_name,
            $track_name,
            $back_frame_file_path,
            'back'
        );
    } else {
        $back_image_data_url = buildImageDataUrlFromFile($back_frame_file_path);
    }

    return [
        'front_image_data_url' => $front_image_data_url,
        'back_image_data_url' => $back_image_data_url,
        'front_frame_file_name' => (string)$frame_pair_info['front_file_name'],
        'back_frame_file_name' => (string)$frame_pair_info['back_file_name'],

        // Backward compatibility
        'final_image_data_url' => $front_image_data_url,
        'frame_file_name' => (string)$frame_pair_info['front_file_name'],
    ];
}

<?php
declare(strict_types=1);

function ensureDirectoryExists(string $directory_path): void
{
    if (!is_dir($directory_path)) {
        if (!mkdir($directory_path, 0775, true) && !is_dir($directory_path)) {
            throw new RuntimeException('Failed to create directory: ' . $directory_path);
        }
    }
}

function generateJobId(string $job_name_prefix): string
{
    $timestamp = date('Ymd_His');
    $random_suffix = bin2hex(random_bytes(4));
    return $job_name_prefix . '_' . $timestamp . '_' . $random_suffix;
}

function sanitizeFileName(string $file_name): string
{
    $file_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name) ?? 'file';
    $file_name = trim($file_name, '._-');
    return $file_name === '' ? 'file' : $file_name;
}

function joinPath(string ...$parts): string
{
    $filtered_parts = array_values(array_filter($parts, fn($part) => $part !== ''));
    if (count($filtered_parts) === 0) {
        return '';
    }

    $path = array_shift($filtered_parts);
    foreach ($filtered_parts as $part) {
        $path = rtrim($path, "\\/") . DIRECTORY_SEPARATOR . ltrim($part, "\\/");
    }
    return $path;
}

/**
 * Parses a data URL (data:image/jpeg;base64,...) and returns:
 * - mime_type
 * - binary_data
 */
function parseDataUrlImage(string $data_url): array
{
    if (!str_starts_with($data_url, 'data:')) {
        throw new InvalidArgumentException('Invalid data_url format.');
    }

    $comma_pos = strpos($data_url, ',');
    if ($comma_pos === false) {
        throw new InvalidArgumentException('Invalid data_url format (missing comma).');
    }

    $meta = substr($data_url, 5, $comma_pos - 5); // after "data:"
    $base64_data = substr($data_url, $comma_pos + 1);

    $meta_parts = explode(';', $meta);
    $mime_type = $meta_parts[0] ?? '';
    $is_base64 = in_array('base64', $meta_parts, true);

    if ($mime_type === '' || !$is_base64) {
        throw new InvalidArgumentException('Invalid data_url metadata.');
    }

    $binary_data = base64_decode($base64_data, true);
    if ($binary_data === false) {
        throw new InvalidArgumentException('Invalid base64 payload.');
    }

    return [
        'mime_type' => $mime_type,
        'binary_data' => $binary_data,
    ];
}

function writeFileAtomic(string $file_path, string $contents): void
{
    $directory_path = dirname($file_path);
    ensureDirectoryExists($directory_path);

    $temp_file_path = $file_path . '.tmp_' . bin2hex(random_bytes(4));
    $bytes_written = file_put_contents($temp_file_path, $contents, LOCK_EX);

    if ($bytes_written === false) {
        @unlink($temp_file_path);
        throw new RuntimeException('Failed to write temp file: ' . $temp_file_path);
    }

    if (!rename($temp_file_path, $file_path)) {
        @unlink($temp_file_path);
        throw new RuntimeException('Failed to finalize file: ' . $file_path);
    }
}

function jsonResponse(array $payload, int $status_code = 200): void
{
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureSessionStarted(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function isAdminUnlocked(): bool
{
    ensureSessionStarted();
    return (bool)($_SESSION['admin_unlocked'] ?? false);
}

function ensureAdminUnlockedJson(): void
{
    if (!isAdminUnlocked()) {
        jsonResponse(['ok' => false, 'error' => 'Not authorized.'], 403);
    }
}

function ensureCsrfToken(): string
{
    ensureSessionStarted();

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return (string)$_SESSION['csrf_token'];
}

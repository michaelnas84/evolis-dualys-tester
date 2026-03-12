<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/hotfolder_reprint.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$csrfToken = (string)($_GET['csrf_token'] ?? '');
$sessionCsrfToken = (string)($_SESSION['csrf_token'] ?? '');
if ($csrfToken === '' || $sessionCsrfToken === '' || !hash_equals($sessionCsrfToken, $csrfToken)) {
    http_response_code(403);
    exit;
}

$status = (string)($_GET['status'] ?? '');
$jobId = (string)($_GET['job_id'] ?? '');
$asset = (string)($_GET['asset'] ?? '');

try {
    $job = hotfolderReprintLoadJob($config, $status, $jobId);
    $assetPath = hotfolderReprintResolveAssetPath($job, $asset);
    if ($assetPath === null) {
        http_response_code(404);
        exit;
    }

    $mimeType = hotfolderReprintAssetMimeType($assetPath);
    $contents = @file_get_contents($assetPath);
    if (!is_string($contents)) {
        http_response_code(500);
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string)strlen($contents));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $contents;
    exit;
} catch (Throwable) {
    http_response_code(500);
    exit;
}

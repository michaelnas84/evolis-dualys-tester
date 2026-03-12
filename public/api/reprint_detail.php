<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/hotfolder_reprint.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || trim($rawBody) === '') {
    jsonResponse(['ok' => false, 'error' => 'Empty request body.'], 400);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON.'], 400);
}

$csrfToken = (string)($payload['csrf_token'] ?? '');
$sessionCsrfToken = (string)($_SESSION['csrf_token'] ?? '');
if ($csrfToken === '' || $sessionCsrfToken === '' || !hash_equals($sessionCsrfToken, $csrfToken)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid CSRF token.'], 403);
}

$status = (string)($payload['status'] ?? '');
$jobId = (string)($payload['job_id'] ?? '');

try {
    jsonResponse([
        'ok' => true,
        'job' => hotfolderReprintBuildDetailPayload($config, $status, $jobId, $csrfToken),
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}

<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../../src/helpers.php';
require __DIR__ . '/../../src/compositor_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$content_type = (string)($_SERVER['CONTENT_TYPE'] ?? '');
$payload = null;

if (str_contains($content_type, 'application/json')) {
    $raw_body = file_get_contents('php://input');
    if (is_string($raw_body) && trim($raw_body) !== '') {
        $decoded = json_decode($raw_body, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
} else {
    $payload = $_POST;
}

if (!is_array($payload)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid payload.'], 400);
}

$password = (string)($payload['password'] ?? '');

try {
    $compositor_config = loadCompositorConfig();
    if (!verifyAdminPassword($compositor_config, $password)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid password.'], 403);
    }

    $_SESSION['admin_unlocked'] = true;
    jsonResponse(['ok' => true]);
} catch (Throwable $exception) {
    jsonResponse(['ok' => false, 'error' => $exception->getMessage()], 500);
}

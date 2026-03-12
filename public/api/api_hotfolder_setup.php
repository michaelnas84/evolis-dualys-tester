<?php
declare(strict_types=1);

require_once __DIR__ . '\\api_common.php';

/* English comments only */

try {
    assertRequestMethod('POST');
    assertAdminUnlocked();

    $created_directories = ensureHotfolderStructure();
    $payload = getJsonInput();
    $copy_python_only = (bool)($payload['copy_python_only'] ?? false);
    $config_result = $copy_python_only ? ['created' => false] : ensureConfigFileExists();
    $copy_result = copyProjectPythonToHotfolder();
    $status_payload = getHotfolderStatus();

    respondJson(200, [
        'success' => true,
        'message' => $copy_python_only ? 'Python copied successfully.' : 'Hotfolder prepared successfully.',
        'created_directories' => $created_directories,
        'config_created' => $config_result['created'],
        'copied_python' => $copy_result,
        'status' => $status_payload,
    ]);
} catch (Throwable $throwable) {
    respondJson(500, [
        'success' => false,
        'message' => $throwable->getMessage(),
    ]);
}

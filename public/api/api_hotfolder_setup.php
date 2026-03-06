<?php
declare(strict_types=1);

require_once __DIR__ . '\\api_common.php';

/* English comments only */

try {
    assertRequestMethod('POST');

    $created_directories = ensureHotfolderStructure();
    $config_result = ensureConfigFileExists();
    $copy_result = copyProjectPythonToHotfolder();
    $status_payload = getHotfolderStatus();

    respondJson(200, [
        'success' => true,
        'message' => 'Hotfolder prepared successfully.',
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
<?php
declare(strict_types=1);

require_once __DIR__ . '\\api_common.php';

/* English comments only */

try {
    assertRequestMethod('GET');

    ensureHotfolderStructure();
    $config_result = ensureConfigFileExists();

    respondJson(200, [
        'success' => true,
        'data' => $config_result['data'],
        'status' => getHotfolderStatus(),
    ]);
} catch (Throwable $throwable) {
    respondJson(500, [
        'success' => false,
        'message' => $throwable->getMessage(),
    ]);
}
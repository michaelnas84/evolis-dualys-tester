<?php
declare(strict_types=1);

require_once __DIR__ . '\\api_common.php';

/* English comments only */

try {
    assertRequestMethod('GET');
    assertAdminUnlocked();

    ensureHotfolderStructure();
    $config_result = ensureConfigFileExists();

    respondJson(200, [
        'success' => true,
        'data' => $config_result['data'],
        'raw_data' => $config_result['raw_data'],
        'status' => getHotfolderStatus(),
    ]);
} catch (Throwable $throwable) {
    respondJson(500, [
        'success' => false,
        'message' => $throwable->getMessage(),
    ]);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '\\api_common.php';

/* English comments only */

try {
    assertRequestMethod('POST');

    ensureHotfolderStructure();

    $config_payload = getJsonInput();
    validateConfigPayload($config_payload);
    writeConfigFile($config_payload);

    respondJson(200, [
        'success' => true,
        'message' => 'Config saved successfully.',
        'status' => getHotfolderStatus(),
    ]);
} catch (Throwable $throwable) {
    respondJson(500, [
        'success' => false,
        'message' => $throwable->getMessage(),
    ]);
}
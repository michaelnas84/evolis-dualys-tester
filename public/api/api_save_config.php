<?php
declare(strict_types=1);

require_once __DIR__ . '\\api_common.php';

/* English comments only */

try {
    assertRequestMethod('POST');
    assertAdminUnlocked();

    ensureHotfolderStructure();
    $config_result = ensureConfigFileExists();

    $config_payload = getJsonInput();
    validateConfigPayload($config_payload);
    $merged_payload = deepMergeArrays((array)($config_result['raw_data'] ?? []), $config_payload);
    validateConfigPayload($merged_payload);
    writeConfigFile($merged_payload);

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

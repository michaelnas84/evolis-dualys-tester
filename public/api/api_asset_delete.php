<?php
declare(strict_types=1);

require_once __DIR__ . '\\api_common.php';

/* English comments only */

try {
    assertRequestMethod('POST');
    ensureHotfolderStructure();

    $payload = getJsonInput();
    $asset_key = (string) ($payload['asset_key'] ?? '');

    if ($asset_key === '') {
        throw new RuntimeException('Missing asset_key.');
    }

    $target_file_path = getAssetTargetPathByKey($asset_key);

    if (file_exists($target_file_path) && !unlink($target_file_path)) {
        throw new RuntimeException('Could not delete asset.');
    }

    respondJson(200, [
        'success' => true,
        'message' => 'Asset deleted successfully.',
        'status' => getHotfolderStatus(),
    ]);
} catch (Throwable $throwable) {
    respondJson(500, [
        'success' => false,
        'message' => $throwable->getMessage(),
    ]);
}
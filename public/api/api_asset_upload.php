<?php
declare(strict_types=1);

require_once __DIR__ . '\\api_common.php';

/* English comments only */

try {
    assertRequestMethod('POST');
    ensureHotfolderStructure();

    $asset_key = (string) ($_POST['asset_key'] ?? '');
    if ($asset_key === '') {
        throw new RuntimeException('Missing asset_key.');
    }

    if (!isset($_FILES['asset_file']) || !is_array($_FILES['asset_file'])) {
        throw new RuntimeException('Missing asset_file.');
    }

    $uploaded_file = $_FILES['asset_file'];

    if ((int) ($uploaded_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed.');
    }

    $original_file_name = (string) ($uploaded_file['name'] ?? '');
    $temporary_file_path = (string) ($uploaded_file['tmp_name'] ?? '');

    if (!is_uploaded_file($temporary_file_path)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $extension = strtolower((string) pathinfo($original_file_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['png', 'jpg', 'jpeg', 'bmp', 'tif', 'tiff'];

    if (!in_array($extension, $allowed_extensions, true)) {
        throw new RuntimeException('Invalid file extension.');
    }

    $target_file_path = getAssetTargetPathByKey($asset_key);

    if (!move_uploaded_file($temporary_file_path, $target_file_path)) {
        throw new RuntimeException('Could not move uploaded file.');
    }

    respondJson(200, [
        'success' => true,
        'message' => 'Asset uploaded successfully.',
        'asset' => buildAssetStatus($target_file_path, basename($target_file_path)),
        'status' => getHotfolderStatus(),
    ]);
} catch (Throwable $throwable) {
    respondJson(500, [
        'success' => false,
        'message' => $throwable->getMessage(),
    ]);
}
<?php
declare(strict_types=1);

$config = require __DIR__ . '/../../src/config.php';
require __DIR__ . '/../../src/helpers.php';

try {
    $hotfolder_in_path = (string)$config['hotfolder_in_path'];
    ensureDirectoryExists($hotfolder_in_path);

    $test_file_path = joinPath($hotfolder_in_path, '.__write_test__');
    $ok = @file_put_contents($test_file_path, 'ok', LOCK_EX);
    if ($ok === false) {
        throw new RuntimeException('No write permission to hotfolder_in_path: ' . $hotfolder_in_path);
    }
    @unlink($test_file_path);

    jsonResponse([
        'ok' => true,
        'hotfolder_in_path' => $hotfolder_in_path,
        'default_printer_name' => (string)$config['default_printer_name'],
    ]);
} catch (Throwable $exception) {
    jsonResponse([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 500);
}
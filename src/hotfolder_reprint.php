<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const HOTFOLDER_REPRINT_TIMEZONE = 'America/Sao_Paulo';

function hotfolderReprintReadJsonFile(string $filePath): array
{
    if (!is_file($filePath)) {
        return [];
    }

    $raw = @file_get_contents($filePath);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function hotfolderReprintTimezone(): DateTimeZone
{
    return new DateTimeZone(HOTFOLDER_REPRINT_TIMEZONE);
}

function hotfolderRootPath(array $config): string
{
    $hotfolderInPath = trim((string)($config['hotfolder_in_path'] ?? ''));
    if ($hotfolderInPath === '') {
        throw new RuntimeException('Hotfolder in path is not configured.');
    }

    $rootPath = dirname(rtrim($hotfolderInPath, "\\/"));
    if ($rootPath === '' || $rootPath === '.' || $rootPath === DIRECTORY_SEPARATOR) {
        throw new RuntimeException('Invalid hotfolder root path.');
    }

    return $rootPath;
}

function hotfolderReprintStatusDirectories(array $config): array
{
    $rootPath = hotfolderRootPath($config);

    return [
        'done' => joinPath($rootPath, 'done'),
        'error' => joinPath($rootPath, 'error'),
    ];
}

function hotfolderReprintAssertStatus(string $status): string
{
    if (!in_array($status, ['done', 'error'], true)) {
        throw new RuntimeException('Invalid job status.');
    }

    return $status;
}

function hotfolderReprintAssertJobId(string $jobId): string
{
    $jobId = trim($jobId);
    if ($jobId === '' || preg_match('/[\\\\\\/]/', $jobId)) {
        throw new RuntimeException('Invalid job id.');
    }

    return $jobId;
}

function hotfolderReprintManifestPrintMode(array $manifest): string
{
    $hasBack = trim((string)($manifest['back_file'] ?? '')) !== '';
    $duplex = strtolower(trim((string)($manifest['duplex'] ?? '')));

    if ($hasBack || $duplex === 'true') {
        return 'front_and_back';
    }

    return 'front_only';
}

function hotfolderReprintStatusLabel(string $status): string
{
    return $status === 'error' ? 'Falha' : 'Pronto';
}

function hotfolderReprintFormatModifiedAt(int $timestamp): array
{
    $dateTime = (new DateTimeImmutable('@' . $timestamp))->setTimezone(hotfolderReprintTimezone());

    return [
        'iso' => $dateTime->format(DateTimeInterface::ATOM),
        'display' => $dateTime->format('d/m/Y H:i:s'),
        'sort' => $timestamp,
    ];
}

function hotfolderReprintListRecentJobs(array $config, int $limit = 30): array
{
    $limit = max(1, min(30, $limit));
    $jobs = [];

    foreach (hotfolderReprintStatusDirectories($config) as $status => $directoryPath) {
        if (!is_dir($directoryPath)) {
            continue;
        }

        $items = scandir($directoryPath);
        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $itemName) {
            if ($itemName === '.' || $itemName === '..') {
                continue;
            }

            $jobPath = joinPath($directoryPath, $itemName);
            if (!is_dir($jobPath)) {
                continue;
            }

            $manifestPath = joinPath($jobPath, 'manifest.json');
            if (!is_file($manifestPath)) {
                continue;
            }

            $modifiedAt = @filemtime($jobPath);
            if (!is_int($modifiedAt) && !is_float($modifiedAt)) {
                continue;
            }

            $manifest = hotfolderReprintReadJsonFile($manifestPath);
            $timestamps = hotfolderReprintFormatModifiedAt((int)$modifiedAt);

            $jobs[] = [
                'job_id' => $itemName,
                'status' => $status,
                'status_label' => hotfolderReprintStatusLabel($status),
                'modified_at_iso' => $timestamps['iso'],
                'modified_at_display' => $timestamps['display'],
                'modified_at_sort' => $timestamps['sort'],
                'print_mode' => hotfolderReprintManifestPrintMode($manifest),
                'printer_name' => trim((string)($manifest['printer_name'] ?? '')),
                'copies' => max(1, (int)($manifest['copies'] ?? 1)),
                'has_front' => trim((string)($manifest['front_file'] ?? '')) !== '',
                'has_back' => trim((string)($manifest['back_file'] ?? '')) !== '',
            ];
        }
    }

    usort(
        $jobs,
        static fn(array $left, array $right): int => $right['modified_at_sort'] <=> $left['modified_at_sort']
    );

    return array_slice($jobs, 0, $limit);
}

function hotfolderReprintLoadJob(array $config, string $status, string $jobId): array
{
    $status = hotfolderReprintAssertStatus($status);
    $jobId = hotfolderReprintAssertJobId($jobId);

    $directories = hotfolderReprintStatusDirectories($config);
    $baseDirectory = $directories[$status] ?? '';
    $jobPath = joinPath($baseDirectory, $jobId);
    $realJobPath = realpath($jobPath);
    $realBaseDirectory = realpath($baseDirectory);

    if ($realJobPath === false || $realBaseDirectory === false || !str_starts_with($realJobPath, $realBaseDirectory)) {
        throw new RuntimeException('Job not found.');
    }

    if (!is_dir($realJobPath)) {
        throw new RuntimeException('Job folder not found.');
    }

    $manifestPath = joinPath($realJobPath, 'manifest.json');
    $manifest = hotfolderReprintReadJsonFile($manifestPath);
    if ($manifest === []) {
        throw new RuntimeException('Manifest not found or invalid.');
    }

    $modifiedAt = @filemtime($realJobPath);
    $timestamps = hotfolderReprintFormatModifiedAt((int)($modifiedAt ?: time()));

    return [
        'job_id' => $jobId,
        'status' => $status,
        'status_label' => hotfolderReprintStatusLabel($status),
        'job_path' => $realJobPath,
        'modified_at_iso' => $timestamps['iso'],
        'modified_at_display' => $timestamps['display'],
        'manifest' => $manifest,
    ];
}

function hotfolderReprintResolveAssetPath(array $job, string $asset): ?string
{
    $manifest = (array)($job['manifest'] ?? []);
    $jobPath = (string)($job['job_path'] ?? '');

    if ($asset === 'front') {
        $fileName = trim((string)($manifest['front_file'] ?? ''));
    } elseif ($asset === 'back') {
        $fileName = trim((string)($manifest['back_file'] ?? ''));
    } else {
        throw new RuntimeException('Invalid asset.');
    }

    if ($fileName === '') {
        return null;
    }

    if (preg_match('/[\\\\\\/]/', $fileName)) {
        throw new RuntimeException('Invalid file name in manifest.');
    }

    $assetPath = joinPath($jobPath, $fileName);
    $realAssetPath = realpath($assetPath);
    $realJobPath = realpath($jobPath);

    if ($realAssetPath === false || $realJobPath === false || !str_starts_with($realAssetPath, $realJobPath) || !is_file($realAssetPath)) {
        return null;
    }

    return $realAssetPath;
}

function hotfolderReprintAssetMimeType(string $assetPath): string
{
    $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
    $mimeByExtension = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    if (isset($mimeByExtension[$extension])) {
        return $mimeByExtension[$extension];
    }

    if (function_exists('mime_content_type')) {
        $mimeType = (string)@mime_content_type($assetPath);
        if ($mimeType !== '') {
            return $mimeType;
        }
    }

    return 'application/octet-stream';
}

function hotfolderReprintBuildDetailPayload(array $config, string $status, string $jobId, string $csrfToken): array
{
    $job = hotfolderReprintLoadJob($config, $status, $jobId);
    $manifest = (array)$job['manifest'];
    $frontPath = hotfolderReprintResolveAssetPath($job, 'front');
    $backPath = hotfolderReprintResolveAssetPath($job, 'back');

    return [
        'job_id' => $job['job_id'],
        'status' => $job['status'],
        'status_label' => $job['status_label'],
        'modified_at_iso' => $job['modified_at_iso'],
        'modified_at_display' => $job['modified_at_display'],
        'printer_name' => trim((string)($manifest['printer_name'] ?? '')),
        'copies' => max(1, (int)($manifest['copies'] ?? 1)),
        'fit_mode' => trim((string)($manifest['fit_mode'] ?? '')),
        'duplex' => trim((string)($manifest['duplex'] ?? '')),
        'print_mode' => hotfolderReprintManifestPrintMode($manifest),
        'front_available' => $frontPath !== null,
        'back_available' => $backPath !== null,
        'front_image_url' => $frontPath !== null
            ? 'api/reprint_asset.php?status=' . rawurlencode((string)$job['status']) . '&job_id=' . rawurlencode((string)$job['job_id']) . '&asset=front&csrf_token=' . rawurlencode($csrfToken)
            : null,
        'back_image_url' => $backPath !== null
            ? 'api/reprint_asset.php?status=' . rawurlencode((string)$job['status']) . '&job_id=' . rawurlencode((string)$job['job_id']) . '&asset=back&csrf_token=' . rawurlencode($csrfToken)
            : null,
    ];
}

function hotfolderReprintCopyRecursive(string $sourcePath, string $destinationPath): void
{
    if (is_dir($sourcePath)) {
        ensureDirectoryExists($destinationPath);

        $items = scandir($sourcePath);
        if (!is_array($items)) {
            throw new RuntimeException('Failed to read directory: ' . $sourcePath);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            hotfolderReprintCopyRecursive(
                joinPath($sourcePath, $item),
                joinPath($destinationPath, $item)
            );
        }

        return;
    }

    $contents = @file_get_contents($sourcePath);
    if (!is_string($contents)) {
        throw new RuntimeException('Failed to read source file: ' . $sourcePath);
    }

    writeFileAtomic($destinationPath, $contents);
}

function hotfolderReprintEnqueueCopy(array $config, string $status, string $jobId): array
{
    $job = hotfolderReprintLoadJob($config, $status, $jobId);
    $jobPath = (string)$job['job_path'];
    $manifest = (array)$job['manifest'];

    $inPath = trim((string)($config['hotfolder_in_path'] ?? ''));
    ensureDirectoryExists($inPath);

    $jobPrefix = trim((string)($config['job_name_prefix'] ?? 'job'));
    $newJobId = generateJobId($jobPrefix . '_reprint');
    $newJobPath = joinPath($inPath, $newJobId);
    ensureDirectoryExists($newJobPath);

    $items = scandir($jobPath);
    if (!is_array($items)) {
        throw new RuntimeException('Failed to read source job folder.');
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || strcasecmp($item, 'manifest.json') === 0) {
            continue;
        }

        hotfolderReprintCopyRecursive(
            joinPath($jobPath, $item),
            joinPath($newJobPath, $item)
        );
    }

    $manifest['reprint_of_job_id'] = $job['job_id'];
    $manifest['reprint_source_status'] = $job['status'];
    $manifest['reprint_requested_at'] = (new DateTimeImmutable('now', hotfolderReprintTimezone()))->format(DateTimeInterface::ATOM);

    writeFileAtomic(
        joinPath($newJobPath, 'manifest.json'),
        json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    return [
        'job_id' => $newJobId,
        'job_folder_path' => $newJobPath,
        'source_job_id' => $job['job_id'],
        'source_status' => $job['status'],
    ];
}

<?php

declare(strict_types=1);

require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/database.php';

const REPORT_TIMEZONE = 'America/Sao_Paulo';
const HOTFOLDER_ROOT = 'C:\\card_hotfolder';
const REPORT_MIN_DATE = '2026-03-12';

function reportTimezone(): DateTimeZone
{
    return new DateTimeZone(REPORT_TIMEZONE);
}

function reportUtcTimezone(): DateTimeZone
{
    return new DateTimeZone('UTC');
}

function reportTodayString(): string
{
    return (new DateTimeImmutable('today', reportTimezone()))->format('Y-m-d');
}

function reportMinimumDateString(): string
{
    return REPORT_MIN_DATE;
}

function clampInt(mixed $value, int $min, int $max, int $fallback): int
{
    if (!is_numeric($value)) {
        return $fallback;
    }

    return max($min, min($max, (int)$value));
}

function normalizeReportDate(string $input, string $fallback, string $minimum, string $maximum): string
{
    $normalized = preg_match('/^\d{4}-\d{2}-\d{2}$/', $input) ? $input : $fallback;
    if ($normalized < $minimum) {
        return $minimum;
    }

    if ($normalized > $maximum) {
        return $maximum;
    }

    return $normalized;
}

function localDateTimeFromUtcString(string $utcDateTime): ?DateTimeImmutable
{
    if (trim($utcDateTime) === '') {
        return null;
    }

    try {
        $value = new DateTimeImmutable($utcDateTime, reportUtcTimezone());
    } catch (Throwable) {
        return null;
    }

    return $value->setTimezone(reportTimezone());
}

function localDateTimeFromTimestamp(int $timestamp): DateTimeImmutable
{
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone(reportTimezone());
}

function buildUtcRange(string $dateFrom, string $dateTo): array
{
    $timezone = reportTimezone();
    $utcTimezone = reportUtcTimezone();

    $localStart = new DateTimeImmutable($dateFrom . ' 00:00:00', $timezone);
    $localEnd = new DateTimeImmutable($dateTo . ' 23:59:59', $timezone);

    return [
        'local_start' => $localStart,
        'local_end' => $localEnd,
        'utc_start' => $localStart->setTimezone($utcTimezone)->format('Y-m-d\TH:i:s.v\Z'),
        'utc_end' => $localEnd->setTimezone($utcTimezone)->format('Y-m-d\TH:i:s.v\Z'),
    ];
}

function recordMatchesHourWindow(DateTimeImmutable $localDateTime, int $hourFrom, int $hourTo): bool
{
    $hour = (int)$localDateTime->format('G');
    return $hour >= $hourFrom && $hour <= $hourTo;
}

function safeJsonDecodeFile(string $filePath): array
{
    if (!is_file($filePath)) {
        return [];
    }

    $raw = file_get_contents($filePath);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function incrementCounter(array &$counters, string $key, int $amount = 1): void
{
    $counters[$key] = ($counters[$key] ?? 0) + $amount;
}

function countByValue(array $rows, string $field): array
{
    $counts = [];

    foreach ($rows as $row) {
        $value = trim((string)($row[$field] ?? ''));
        if ($value === '') {
            $value = 'Nao informado';
        }

        incrementCounter($counts, $value);
    }

    arsort($counts);
    return $counts;
}

function topItems(array $counts, int $limit = 8): array
{
    $result = [];
    $index = 0;

    foreach ($counts as $label => $value) {
        $result[] = [
            'label' => $label,
            'value' => $value,
        ];
        $index++;

        if ($index >= $limit) {
            break;
        }
    }

    return $result;
}

function buildDateLabels(string $dateFrom, string $dateTo): array
{
    $labels = [];
    $cursor = new DateTimeImmutable($dateFrom, reportTimezone());
    $end = new DateTimeImmutable($dateTo, reportTimezone());

    while ($cursor <= $end) {
        $labels[] = $cursor->format('Y-m-d');
        $cursor = $cursor->modify('+1 day');
    }

    return $labels;
}

function scanHotfolderJobs(DateTimeImmutable $localStart, DateTimeImmutable $localEnd, int $hourFrom, int $hourTo): array
{
    $statuses = [
        'in' => HOTFOLDER_ROOT . '\\in',
        'done' => HOTFOLDER_ROOT . '\\done',
        'error' => HOTFOLDER_ROOT . '\\error',
    ];

    $jobs = [];

    foreach ($statuses as $status => $directoryPath) {
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

            $fullPath = $directoryPath . DIRECTORY_SEPARATOR . $itemName;
            $isDirectory = is_dir($fullPath);
            if (!$isDirectory && !is_file($fullPath)) {
                continue;
            }

            $modifiedAt = @filemtime($fullPath);
            if (!is_int($modifiedAt) && !is_float($modifiedAt)) {
                continue;
            }

            $localDateTime = localDateTimeFromTimestamp((int)$modifiedAt);
            if ($localDateTime < $localStart || $localDateTime > $localEnd) {
                continue;
            }
            if (!recordMatchesHourWindow($localDateTime, $hourFrom, $hourTo)) {
                continue;
            }

            $manifest = $isDirectory ? safeJsonDecodeFile($fullPath . DIRECTORY_SEPARATOR . 'manifest.json') : [];
            $jobs[$itemName] = [
                'job_id' => $itemName,
                'status' => $status,
                'path' => $fullPath,
                'is_directory' => $isDirectory,
                'modified_at_local' => $localDateTime->format(DateTimeInterface::ATOM),
                'manifest' => $manifest,
            ];
        }
    }

    return $jobs;
}

function scanHotfolderErrors(DateTimeImmutable $localStart, DateTimeImmutable $localEnd, int $hourFrom, int $hourTo): array
{
    $logPath = HOTFOLDER_ROOT . '\\logs\\hotfolder.log';
    if (!is_file($logPath)) {
        return [];
    }

    $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $errors = [];

    foreach ($lines as $line) {
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}):(\d{2}):(\d{2}),\d+\s+(INFO|ERROR)\s+(.*)$/', $line, $matches)) {
            continue;
        }

        $localDateTime = new DateTimeImmutable(
            sprintf('%s %s:%s:%s', $matches[1], $matches[2], $matches[3], $matches[4]),
            reportTimezone()
        );

        if ($localDateTime < $localStart || $localDateTime > $localEnd) {
            continue;
        }
        if (!recordMatchesHourWindow($localDateTime, $hourFrom, $hourTo)) {
            continue;
        }

        if ($matches[5] !== 'ERROR') {
            continue;
        }

        $errors[] = [
            'level' => $matches[5],
            'message' => trim($matches[6]),
            'occurred_at_local' => $localDateTime->format(DateTimeInterface::ATOM),
        ];
    }

    return $errors;
}

function buildReportPayload(string $dateFrom, string $dateTo, int $hourFrom, int $hourTo): array
{
    $range = buildUtcRange($dateFrom, $dateTo);
    $pdo = db_connect();

    $stmt = $pdo->prepare(
        'SELECT cpf, cpf_formatted, person_name, fandom, track, frame_name, job_id, job_folder_path, print_mode, created_at
         FROM participants
         WHERE created_at >= :start AND created_at <= :end
         ORDER BY created_at DESC'
    );
    $stmt->execute([
        ':start' => $range['utc_start'],
        ':end' => $range['utc_end'],
    ]);

    $participants = [];
    foreach ($stmt->fetchAll() as $row) {
        $localDateTime = localDateTimeFromUtcString((string)$row['created_at']);
        if (!$localDateTime) {
            continue;
        }
        if (!recordMatchesHourWindow($localDateTime, $hourFrom, $hourTo)) {
            continue;
        }

        $row['created_at_local'] = $localDateTime->format(DateTimeInterface::ATOM);
        $row['date_label'] = $localDateTime->format('d/m');
        $row['date_key'] = $localDateTime->format('Y-m-d');
        $row['hour_key'] = (int)$localDateTime->format('G');
        $row['time_label'] = $localDateTime->format('H:i');
        $participants[] = $row;
    }

    $eventStmt = $pdo->prepare(
        'SELECT event, detail, cpf, created_at
         FROM event_log
         WHERE created_at >= :start AND created_at <= :end
         ORDER BY created_at DESC'
    );
    $eventStmt->execute([
        ':start' => $range['utc_start'],
        ':end' => $range['utc_end'],
    ]);

    $events = [];
    foreach ($eventStmt->fetchAll() as $row) {
        $localDateTime = localDateTimeFromUtcString((string)$row['created_at']);
        if (!$localDateTime) {
            continue;
        }
        if (!recordMatchesHourWindow($localDateTime, $hourFrom, $hourTo)) {
            continue;
        }

        $row['created_at_local'] = $localDateTime->format(DateTimeInterface::ATOM);
        $events[] = $row;
    }

    $hotfolderJobs = scanHotfolderJobs($range['local_start'], $range['local_end'], $hourFrom, $hourTo);
    $hotfolderErrors = scanHotfolderErrors($range['local_start'], $range['local_end'], $hourFrom, $hourTo);

    $byDay = [];
    $byHour = array_fill(0, 24, 0);
    $heatmapRows = [];
    foreach (buildDateLabels($dateFrom, $dateTo) as $dateKey) {
        $byDay[$dateKey] = 0;
        $heatmapRows[$dateKey] = array_fill(0, 24, 0);
    }

    $hotfolderStatusCounts = ['in' => 0, 'done' => 0, 'error' => 0];
    foreach ($hotfolderJobs as $job) {
        $status = (string)($job['status'] ?? '');
        if (isset($hotfolderStatusCounts[$status])) {
            incrementCounter($hotfolderStatusCounts, $status);
        }
    }

    $participantStatusCounts = ['in' => 0, 'done' => 0, 'error' => 0, 'not_found' => 0];
    $reportParticipants = [];
    $cleanupRows = [];

    foreach ($participants as $participant) {
        $jobId = trim((string)($participant['job_id'] ?? ''));
        $jobStatus = $jobId !== '' && isset($hotfolderJobs[$jobId]) ? (string)$hotfolderJobs[$jobId]['status'] : 'not_found';
        if (isset($participantStatusCounts[$jobStatus])) {
            incrementCounter($participantStatusCounts, $jobStatus);
        }

        if ($jobStatus === 'done') {
            $reportParticipants[] = $participant + ['job_status' => $jobStatus];
            continue;
        }

        $cleanupRows[] = [
            'time' => (string)$participant['time_label'],
            'date' => (string)$participant['date_label'],
            'person_name' => (string)$participant['person_name'],
            'cpf_formatted' => (string)$participant['cpf_formatted'],
            'fandom' => (string)$participant['fandom'],
            'track' => (string)$participant['track'],
            'frame_name' => (string)$participant['frame_name'],
            'print_mode' => (string)$participant['print_mode'],
            'job_id' => $jobId,
            'job_status' => $jobStatus,
        ];
    }

    foreach ($reportParticipants as $participant) {
        incrementCounter($byDay, (string)$participant['date_key']);
        $hourKey = (int)$participant['hour_key'];
        $byHour[$hourKey]++;
        $heatmapRows[(string)$participant['date_key']][$hourKey]++;
    }

    $activeHours = [];

    foreach ($heatmapRows as $row) {
        foreach ($row as $hour => $value) {
            if ((int)$value > 0) {
                $activeHours[(int)$hour] = true;
            }
        }
    }

    $visibleHeatmapHours = array_keys($activeHours);
    sort($visibleHeatmapHours);

    $filteredHeatmapValues = [];
    foreach ($heatmapRows as $dateKey => $row) {
        $filteredRow = [];

        foreach ($visibleHeatmapHours as $hour) {
            $filteredRow[] = (int)($row[$hour] ?? 0);
        }

        $filteredHeatmapValues[] = $filteredRow;
    }

    $recentRows = [];
    foreach ($reportParticipants as $participant) {
        $recentRows[] = [
            'time' => (string)$participant['time_label'],
            'date' => (string)$participant['date_label'],
            'person_name' => (string)$participant['person_name'],
            'fandom' => (string)$participant['fandom'],
            'track' => (string)$participant['track'],
            'frame_name' => (string)$participant['frame_name'],
            'print_mode' => (string)$participant['print_mode'],
            'job_id' => (string)$participant['job_id'],
            'job_status' => 'done',
        ];
    }

    $currentHour = (int)(new DateTimeImmutable('now', reportTimezone()))->format('G');
    $previousHour = max(0, $currentHour - 1);
    $peakHourValue = max($byHour);
    $peakHour = array_search($peakHourValue, $byHour, true);
    if ($peakHourValue === 0) {
        $peakHour = false;
    }

    $duplicateCpfCount = 0;
    foreach ($events as $event) {
        if (($event['event'] ?? '') === 'cpf_duplicate') {
            $duplicateCpfCount++;
        }
    }

    $frameCounts = countByValue($reportParticipants, 'frame_name');
    $printModeCounts = countByValue($reportParticipants, 'print_mode');
    $fandomCounts = countByValue($reportParticipants, 'fandom');
    $trackCounts = countByValue($reportParticipants, 'track');

    $insights = [];
    $totalParticipants = count($reportParticipants);
    $cleanupCandidatesTotal = count($cleanupRows);
    $totalParticipantsTracked = array_sum($participantStatusCounts);

    if ($totalParticipants === 0) {
        $insights[] = 'Nenhuma ativacao consolidada foi encontrada no periodo selecionado.';
    } else {
        $insights[] = sprintf('O periodo analisado registrou %d ativacoes consolidadas.', $totalParticipants);
        if ($peakHour !== false && $peakHourValue > 0) {
            $insights[] = sprintf('O maior volume ocorreu as %02dh, com %d ativacoes.', (int)$peakHour, $peakHourValue);
        }
        if (!empty($frameCounts)) {
            $topFrame = array_key_first($frameCounts);
            $insights[] = sprintf('O frame com maior recorrencia foi %s.', (string)$topFrame);
        }
        if (!empty($fandomCounts)) {
            $topFandom = array_key_first($fandomCounts);
            $insights[] = sprintf('O fandom com maior participacao foi %s.', (string)$topFandom);
        }
        if (!empty($trackCounts)) {
            $topTrack = array_key_first($trackCounts);
            $insights[] = sprintf('A trilha mais recorrente entre as ativacoes foi %s.', (string)$topTrack);
        }
    }

    return [
        'generated_at' => (new DateTimeImmutable('now', reportTimezone()))->format(DateTimeInterface::ATOM),
        'filters' => [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'hour_from' => $hourFrom,
            'hour_to' => $hourTo,
            'today' => reportTodayString(),
        ],
        'summary' => [
            'activations_total' => $totalParticipants,
            'duplicates_total' => $duplicateCpfCount,
            'cleanup_candidates_total' => $cleanupCandidatesTotal,
            'jobs_in_total' => $participantStatusCounts['in'],
            'jobs_done_total' => $hotfolderStatusCounts['done'],
            'jobs_error_total' => $participantStatusCounts['error'],
            'jobs_not_found_total' => $participantStatusCounts['not_found'],
            'current_hour_activations' => $byHour[$currentHour] ?? 0,
            'previous_hour_activations' => $byHour[$previousHour] ?? 0,
            'peak_hour_label' => $peakHour !== false ? sprintf('%02d:00', (int)$peakHour) : '--:--',
            'peak_hour_value' => $peakHourValue,
        ],
        'charts' => [
            'by_day' => [
                'labels' => array_map(
                    static fn(string $dateKey): string => (new DateTimeImmutable($dateKey, reportTimezone()))->format('d/m'),
                    array_keys($byDay)
                ),
                'values' => array_values($byDay),
            ],
            'by_hour' => [
                'labels' => array_map(static fn(int $hour): string => sprintf('%02d:00', $hour), range(0, 23)),
                'values' => array_values($byHour),
            ],
            'frames' => topItems($frameCounts, 6),
            'print_modes' => topItems($printModeCounts, 6),
            'top_fandoms' => topItems($fandomCounts, 8),
            'top_tracks' => topItems($trackCounts, 8),
            'job_status' => [
                ['label' => 'Consolidados', 'value' => $participantStatusCounts['done']],
                ['label' => 'Aguardando', 'value' => $participantStatusCounts['in']],
                ['label' => 'Erro', 'value' => $participantStatusCounts['error']],
                ['label' => 'Sem correspondencia', 'value' => $participantStatusCounts['not_found']],
            ],
            'heatmap' => [
                'days' => array_map(
                    static fn(string $dateKey): string => (new DateTimeImmutable($dateKey, reportTimezone()))->format('d/m'),
                    array_keys($heatmapRows)
                ),
                'hours' => array_map(
                    static fn(int $hour): string => sprintf('%02d', $hour),
                    $visibleHeatmapHours
                ),
                'values' => $filteredHeatmapValues,
            ],
        ],
        'recent_activations' => array_slice($recentRows, 0, 30),
        'cleanup_candidates' => $cleanupRows,
        'recent_errors' => array_slice(array_reverse($hotfolderErrors), -20),
        'insights' => $insights,
    ];
}

$today = reportTodayString();
$minimumDate = reportMinimumDateString();
$dateFrom = normalizeReportDate((string)($_GET['date_from'] ?? ''), $today, $minimumDate, $today);
$dateTo = normalizeReportDate((string)($_GET['date_to'] ?? ''), $dateFrom, $minimumDate, $today);
if ($dateTo < $dateFrom) {
    $dateTo = $dateFrom;
}

$hourFrom = clampInt($_GET['hour_from'] ?? 0, 0, 23, 0);
$hourTo = clampInt($_GET['hour_to'] ?? 23, 0, 23, 23);
if ($hourTo < $hourFrom) {
    $hourTo = $hourFrom;
}

$initialPayload = buildReportPayload($dateFrom, $dateTo, $hourFrom, $hourTo);

if (($_GET['format'] ?? '') === 'json') {
    jsonResponse([
        'ok' => true,
        'report' => $initialPayload,
    ]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatorio Analitico - Evento Vivo</title>
    <script src="js/tailwind.js"></script>
    <style>
        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(16px) scale(0.99);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(56, 189, 248, 0.18), transparent 24%),
                radial-gradient(circle at top right, rgba(45, 212, 191, 0.12), transparent 22%),
                linear-gradient(180deg, #f8fafc 0%, #e2f3ef 52%, #f8fafc 100%);
        }

        .card-enter {
            animation: floatIn 0.35s ease-out forwards;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .shadow-panel {
            box-shadow:
                0 14px 40px rgba(15, 23, 42, 0.08),
                0 2px 10px rgba(15, 23, 42, 0.05);
        }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(92px, 1fr));
            gap: 0.75rem;
            align-items: end;
            min-height: 240px;
        }

        .bar-card {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }

        .bar-track {
            position: relative;
            height: 180px;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(226, 232, 240, 0.18), rgba(226, 232, 240, 0.92));
            overflow: hidden;
        }

        .bar-fill {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 24px;
            background: linear-gradient(180deg, #0f766e 0%, #14b8a6 100%);
            min-height: 4px;
        }

        .mini-bar-track {
            height: 12px;
            border-radius: 999px;
            background: rgba(226, 232, 240, 0.9);
            overflow: hidden;
        }

        .mini-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #0f766e 0%, #2dd4bf 100%);
        }

        .heatmap-grid {
            display: grid;
            gap: 6px;
            align-items: center;
        }

        .heatmap-cell {
            aspect-ratio: 1 / 1;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: #0f172a;
        }

        .line-chart {
            width: 100%;
            height: 280px;
        }

        .line-chart text {
            fill: #64748b;
            font-size: 12px;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.35rem 0.8rem;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .print-only {
            display: none;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm;
            }

            body {
                background: #ffffff !important;
                color: #0f172a;
            }

            .no-print {
                display: none !important;
            }

            .print-only {
                display: block !important;
            }

            .glass-panel {
                background: #ffffff !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
            }

            .shadow-panel {
                box-shadow: none !important;
            }

            section,
            header,
            .glass-panel,
            .card-enter {
                break-inside: avoid;
                page-break-inside: avoid;
                animation: none !important;
            }

            .rounded-\[2rem\],
            .rounded-\[1\.7rem\],
            .rounded-3xl,
            .rounded-2xl {
                border-radius: 18px !important;
            }
        }
    </style>
</head>

<body class="min-h-screen text-slate-800">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        <header class="card-enter mb-6">
            <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-6 lg:p-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="pill bg-teal-100 text-teal-700">Relatorio analitico</div>
                        <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-900 lg:text-5xl">Evento Vivo</h1>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Panorama consolidado das ativacoes registradas no periodo selecionado, com destaque para volume, comportamento horario e preferencias do publico.</p>
                    </div>

                    <div class="no-print flex flex-wrap gap-3">
                        <button id="print_button" type="button" class="rounded-2xl bg-teal-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-teal-500">
                            Imprimir / PDF
                        </button>
                        <button id="refresh_button" type="button" class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800">
                            Atualizar agora
                        </button>
                        <a href="index.php" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                            Voltar
                        </a>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-3 hidden">
                    <div class="rounded-3xl border border-slate-200 bg-white px-4 py-4">
                        <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Periodo analisado</div>
                        <div id="header_period" class="mt-2 text-lg font-black text-slate-900">--</div>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white px-4 py-4">
                        <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Janela de horario</div>
                        <div id="header_window" class="mt-2 text-lg font-black text-slate-900">--</div>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white px-4 py-4">
                        <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Emissao do relatorio</div>
                        <div id="header_generated_at" class="mt-2 text-lg font-black text-slate-900">--</div>
                    </div>
                </div>
            </div>
        </header>

        <section class="card-enter mb-6 no-print">
            <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto]">
                    <label class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Data inicial</div>
                        <input id="date_from" type="date" min="<?= htmlspecialchars($minimumDate, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>" class="mt-2 w-full bg-transparent text-base font-semibold outline-none" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
                    </label>

                    <label class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Data final</div>
                        <input id="date_to" type="date" min="<?= htmlspecialchars($minimumDate, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>" class="mt-2 w-full bg-transparent text-base font-semibold outline-none" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
                    </label>

                    <label class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Hora inicial</div>
                        <input id="hour_from" type="number" min="0" max="23" class="mt-2 w-full bg-transparent text-base font-semibold outline-none" value="<?= $hourFrom ?>">
                    </label>

                    <label class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Hora final</div>
                        <input id="hour_to" type="number" min="0" max="23" class="mt-2 w-full bg-transparent text-base font-semibold outline-none" value="<?= $hourTo ?>">
                    </label>

                    <div class="flex flex-col justify-between gap-3">
                        <button id="apply_filters_button" type="button" class="rounded-2xl bg-teal-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-teal-500">
                            Aplicar filtro
                        </button>
                        <div id="refresh_status" class="text-right text-xs font-semibold text-slate-500">
                            Atualizado agora
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="summary_cards" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"></section>

        <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.45fr_1fr]">
            <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Tendencia por data</h2>
                        <p class="mt-1 text-sm text-slate-500">Volume diario de ativacoes consolidadas.</p>
                    </div>
                    <div class="pill bg-slate-100 text-slate-700">Data x volume</div>
                </div>
                <div id="chart_by_day" class="mt-5"></div>
            </div>

            <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Destaques do periodo</h2>
                        <p class="mt-1 text-sm text-slate-500">Leitura sintetica do comportamento das ativacoes.</p>
                    </div>
                    <div class="pill bg-amber-100 text-amber-700">Resumo</div>
                </div>
                <div id="comparison_cards" class="mt-5 grid grid-cols-1 gap-3"></div>
            </div>
        </section>

        <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Ativacoes por hora</h2>
                        <p class="mt-1 text-sm text-slate-500">Distribuicao local hora a hora das ativacoes consolidadas.</p>
                    </div>
                    <div class="pill bg-emerald-100 text-emerald-700">Hora</div>
                </div>
                <div id="chart_by_hour" class="mt-5"></div>
            </div>

            <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5 no-print">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Status de consolidacao</h2>
                        <p class="mt-1 text-sm text-slate-500">Painel interno para conferencia tecnica dos registros.</p>
                    </div>
                    <div class="pill bg-rose-100 text-rose-700">Interno</div>
                </div>
                <div id="chart_job_status" class="mt-5 space-y-4"></div>
            </div>
        </section>

        <section class="mt-6 glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-900">Mapa de calor data x hora</h2>
                    <p class="mt-1 text-sm text-slate-500">Quanto mais escuro, mais ativacoes naquela combinacao.</p>
                </div>
                <div class="pill bg-cyan-100 text-cyan-700">Heatmap</div>
            </div>
            <div id="heatmap_chart" class="mt-5 overflow-x-auto"></div>
        </section>

        <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-4">
            <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5">
                <h2 class="text-xl font-black text-slate-900">Frames mais usados</h2>
                <div id="chart_frames" class="mt-5 space-y-4"></div>
            </div>

            <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5">
                <h2 class="text-xl font-black text-slate-900">Formatos gerados</h2>
                <div id="chart_print_modes" class="mt-5 space-y-4"></div>
            </div>

            <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5">
                <h2 class="text-xl font-black text-slate-900">Top fandoms</h2>
                <div id="chart_fandoms" class="mt-5 space-y-4"></div>
            </div>

            <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5">
                <h2 class="text-xl font-black text-slate-900">Top musicas</h2>
                <div id="chart_tracks" class="mt-5 space-y-4"></div>
            </div>
        </section>

        <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Amostra de ativacoes</h2>
                        <p class="mt-1 text-sm text-slate-500">Recorte recente das participacoes consolidadas no periodo.</p>
                    </div>
                    <div class="pill bg-slate-100 text-slate-700">Amostra</div>
                </div>
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-slate-500">
                            <tr>
                                <th class="pb-3 pr-4 font-semibold">Hora</th>
                                <th class="pb-3 pr-4 font-semibold">Nome</th>
                                <th class="pb-3 pr-4 font-semibold">Fandom</th>
                                <th class="pb-3 pr-4 font-semibold">Musica</th>
                                <th class="pb-3 pr-4 font-semibold">Frame</th>
                                <th class="pb-3 pr-4 font-semibold">Formato</th>
                            </tr>
                        </thead>
                        <tbody id="recent_activations_body"></tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5">
                    <h2 class="text-xl font-black text-slate-900">Insights do periodo</h2>
                    <div id="insights_list" class="mt-5 space-y-3"></div>
                </div>

                <div class="glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5 no-print">
                    <h2 class="text-xl font-black text-slate-900">Ocorrencias tecnicas recentes</h2>
                    <div id="recent_errors_list" class="mt-5 space-y-3"></div>
                </div>
            </div>
        </section>

        <section class="mt-6 glass-panel shadow-panel rounded-[2rem] border border-white/70 p-5 no-print hidden">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-900">Registros complementares para conferencia</h2>
                    <p class="mt-1 text-sm text-slate-500">Lista de apoio para validacao interna antes do fechamento definitivo.</p>
                </div>
                <div class="pill bg-amber-100 text-amber-700">Interno</div>
            </div>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-slate-500">
                        <tr>
                            <th class="pb-3 pr-4 font-semibold">Hora</th>
                            <th class="pb-3 pr-4 font-semibold">Nome</th>
                            <th class="pb-3 pr-4 font-semibold">CPF</th>
                            <th class="pb-3 pr-4 font-semibold">Fandom</th>
                            <th class="pb-3 pr-4 font-semibold">Musica</th>
                            <th class="pb-3 pr-4 font-semibold">Frame</th>
                            <th class="pb-3 pr-4 font-semibold">Status</th>
                            <th class="pb-3 pr-4 font-semibold">Job ID</th>
                        </tr>
                    </thead>
                    <tbody id="cleanup_candidates_body"></tbody>
                </table>
            </div>
        </section>
    </div>
    <script>
        const initialReport = <?= json_encode($initialPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

        function getElement(id) {
            return document.getElementById(id);
        }

        function formatNumber(value) {
            return new Intl.NumberFormat('pt-BR').format(Number(value || 0));
        }

        function formatIsoToLocal(isoValue) {
            if (!isoValue) return '--';
            const date = new Date(isoValue);
            return new Intl.DateTimeFormat('pt-BR', {
                year: 'numeric',
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        }

        function formatShortDate(dateValue) {
            if (!dateValue || !/^\d{4}-\d{2}-\d{2}$/.test(dateValue)) return '--';
            const [year, month, day] = dateValue.split('-');
            return `${day}/${month}/${year}`;
        }

        function formatHourWindow(hourFrom, hourTo) {
            const start = String(hourFrom ?? 0).padStart(2, '0');
            const end = String(hourTo ?? 23).padStart(2, '0');
            return `${start}:00 as ${end}:59`;
        }

        function updateRefreshStatus(label) {
            getElement('refresh_status').textContent = label;
        }

        function updateReportMeta(report) {
            const filters = report.filters || {};
            getElement('header_period').textContent = `${formatShortDate(filters.date_from)} a ${formatShortDate(filters.date_to)}`;
            getElement('header_window').textContent = formatHourWindow(filters.hour_from, filters.hour_to);
            getElement('header_generated_at').textContent = formatIsoToLocal(report.generated_at);
        }

        function getJobStatusMeta(status) {
            const statusClasses = {
                done: 'bg-emerald-100 text-emerald-700',
                in: 'bg-amber-100 text-amber-700',
                error: 'bg-rose-100 text-rose-700',
                not_found: 'bg-slate-100 text-slate-700'
            };
            const statusLabels = {
                done: 'Concluido',
                in: 'Aguardando',
                error: 'Erro',
                not_found: 'Sem correspondencia'
            };

            return {
                className: statusClasses[status] || statusClasses.not_found,
                label: statusLabels[status] || statusLabels.not_found,
            };
        }

        function summaryCard(label, value, detail, tone) {
            const tones = {
                teal: 'bg-teal-100 text-teal-700',
                emerald: 'bg-emerald-100 text-emerald-700',
                amber: 'bg-amber-100 text-amber-700',
                rose: 'bg-rose-100 text-rose-700',
                slate: 'bg-slate-100 text-slate-700'
            };

            return `
                <div class="card-enter glass-panel shadow-panel rounded-[1.7rem] border border-white/70 p-5">
                    <div class="pill ${tones[tone] || tones.slate}">${label}</div>
                    <div class="mt-4 text-4xl font-black tracking-tight text-slate-900">${formatNumber(value)}</div>
                    <div class="mt-2 text-sm text-slate-500">${detail}</div>
                </div>
            `;
        }

        function renderSummary(report) {
            const summary = report.summary || {};
            getElement('summary_cards').innerHTML = [
                summaryCard('Ativacoes registradas', summary.activations_total, 'Total consolidado no periodo analisado.', 'teal'),
                summaryCard('Registros consolidados', summary.jobs_done_total, 'Base validada para composicao do relatorio.', 'emerald'),
                summaryCard('Hora de pico', summary.peak_hour_value, `Maior volume em ${summary.peak_hour_label || '--:--'}.`, 'amber'),
                summaryCard('Janela mais recente', summary.current_hour_activations, 'Ativacoes registradas na ultima hora observada.', 'slate')
            ].join('');
        }

        function renderComparison(report) {
            const summary = report.summary || {};
            const delta = Number(summary.current_hour_activations || 0) - Number(summary.previous_hour_activations || 0);
            const deltaLabel = delta === 0 ? 'Mesmo volume da hora anterior.' : (delta > 0 ? `+${delta} vs hora anterior.` : `${delta} vs hora anterior.`);

            getElement('comparison_cards').innerHTML = [
                {
                    title: 'Maior concentracao',
                    value: summary.peak_hour_label || '--:--',
                    detail: `${formatNumber(summary.peak_hour_value || 0)} ativacoes no pico`
                },
                {
                    title: 'Base consolidada',
                    value: formatNumber(summary.jobs_done_total || 0),
                    detail: 'Registros considerados na analise'
                }
            ].map((item) => `
                <div class="rounded-3xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">${item.title}</div>
                    <div class="mt-2 text-2xl font-black text-slate-900">${item.value}</div>
                    <div class="mt-1 text-sm text-slate-500">${item.detail}</div>
                </div>
            `).join('');
        }

        function renderBarColumns(containerId, labels, values) {
            const container = getElement(containerId);
            const maxValue = Math.max(1, ...values);

            container.innerHTML = `
                <div class="chart-grid">
                    ${labels.map((label, index) => {
                        const value = Number(values[index] || 0);
                        const height = Math.max(3, Math.round((value / maxValue) * 100));
                        return `
                            <div class="bar-card">
                                <div class="bar-track">
                                    <div class="bar-fill" style="height:${height}%"></div>
                                </div>
                                <div class="text-sm font-bold text-slate-900">${label}</div>
                                <div class="text-xs text-slate-500">${formatNumber(value)}</div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }

        function renderRankedBars(containerId, items, color = 'teal') {
            const container = getElement(containerId);
            const maxValue = Math.max(1, ...items.map((item) => Number(item.value || 0)));
            const gradients = {
                teal: 'linear-gradient(90deg, #0f766e 0%, #2dd4bf 100%)',
                emerald: 'linear-gradient(90deg, #047857 0%, #34d399 100%)',
                amber: 'linear-gradient(90deg, #d97706 0%, #f59e0b 100%)',
                rose: 'linear-gradient(90deg, #be123c 0%, #fb7185 100%)',
                slate: 'linear-gradient(90deg, #334155 0%, #94a3b8 100%)'
            };

            if (!items.length) {
                container.innerHTML = '<div class="rounded-3xl border border-dashed border-slate-300 bg-white px-4 py-6 text-sm text-slate-500">Sem dados no periodo filtrado.</div>';
                return;
            }

            container.innerHTML = items.map((item) => {
                const width = Math.max(4, Math.round((Number(item.value || 0) / maxValue) * 100));
                return `
                    <div class="rounded-3xl border border-slate-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold text-slate-900">${item.label}</div>
                                <div class="mt-1 text-xs text-slate-500">${formatNumber(item.value)} registros</div>
                            </div>
                            <div class="text-sm font-black text-slate-900">${formatNumber(item.value)}</div>
                        </div>
                        <div class="mini-bar-track mt-3">
                            <div class="mini-bar-fill" style="width:${width}%;background:${gradients[color] || gradients.teal};"></div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderLineChart(containerId, labels, values) {
            const container = getElement(containerId);
            if (!labels.length) {
                container.innerHTML = '<div class="rounded-3xl border border-dashed border-slate-300 bg-white px-4 py-10 text-sm text-slate-500">Sem dados para montar a tendencia.</div>';
                return;
            }

            const width = 860;
            const height = 280;
            const padding = 42;
            const maxValue = Math.max(1, ...values);
            const xStep = labels.length === 1 ? 0 : (width - padding * 2) / (labels.length - 1);
            const points = labels.map((_, index) => {
                const x = padding + (xStep * index);
                const y = height - padding - ((Number(values[index] || 0) / maxValue) * (height - padding * 2));
                return `${x},${y}`;
            }).join(' ');
            const areaPoints = `${padding},${height - padding} ${points} ${width - padding},${height - padding}`;

            container.innerHTML = `
                <svg viewBox="0 0 ${width} ${height}" class="line-chart" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="lineFillGradient" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="rgba(20,184,166,0.35)"></stop>
                            <stop offset="100%" stop-color="rgba(20,184,166,0.02)"></stop>
                        </linearGradient>
                    </defs>
                    ${[0, 0.25, 0.5, 0.75, 1].map((tick) => {
                        const value = Math.round(maxValue * tick);
                        const y = height - padding - (tick * (height - padding * 2));
                        return `
                            <line x1="${padding}" y1="${y}" x2="${width - padding}" y2="${y}" stroke="rgba(148,163,184,0.25)" stroke-width="1"></line>
                            <text x="8" y="${y + 4}">${value}</text>
                        `;
                    }).join('')}
                    <polygon points="${areaPoints}" fill="url(#lineFillGradient)"></polygon>
                    <polyline points="${points}" fill="none" stroke="#0f766e" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></polyline>
                    ${labels.map((label, index) => {
                        const x = padding + (xStep * index);
                        const y = height - padding - ((Number(values[index] || 0) / maxValue) * (height - padding * 2));
                        return `
                            <circle cx="${x}" cy="${y}" r="4.5" fill="#14b8a6"></circle>
                            <text x="${x}" y="${height - 12}" text-anchor="middle">${label}</text>
                        `;
                    }).join('')}
                </svg>
            `;
        }

        function renderHeatmap(report) {
            const heatmap = report.charts?.heatmap || {
                days: [],
                hours: [],
                values: []
            };
            const container = getElement('heatmap_chart');
            const flatValues = (heatmap.values || []).reduce((all, row) => all.concat(row || []), []).map((value) => Number(value || 0));
            const maxValue = Math.max(1, ...flatValues);

            if (!heatmap.days.length) {
                container.innerHTML = '<div class="rounded-3xl border border-dashed border-slate-300 bg-white px-4 py-10 text-sm text-slate-500">Sem dados para o mapa de calor.</div>';
                return;
            }

            const cells = [];
            cells.push('<div></div>');
            heatmap.hours.forEach((hour) => {
                cells.push(`<div class="text-center text-[11px] font-bold text-slate-500">${hour}</div>`);
            });

            heatmap.days.forEach((dayLabel, dayIndex) => {
                cells.push(`<div class="text-sm font-bold text-slate-700">${dayLabel}</div>`);
                heatmap.values[dayIndex].forEach((rawValue) => {
                    const value = Number(rawValue || 0);
                    const intensity = value === 0 ? 0.06 : 0.14 + ((value / maxValue) * 0.86);
                    const background = `rgba(15, 118, 110, ${intensity.toFixed(3)})`;
                    const textColor = intensity > 0.55 ? '#f8fafc' : '#0f172a';
                    cells.push(`<div class="heatmap-cell" style="background:${background};color:${textColor}" title="${dayLabel} ${value}">${value}</div>`);
                });
            });

            const hour_count = heatmap.hours.length;
            const min_width = Math.max(320, 72 + (hour_count * 32));

            container.innerHTML = `
                <div
                    class="heatmap-grid"
                    style="grid-template-columns: 72px repeat(${hour_count}, minmax(20px, 1fr)); min-width:${min_width}px;"
                >
                    ${cells.join('')}
                </div>
            `;
        }

        function renderRecentActivations(report) {
            const rows = report.recent_activations || [];
            const body = getElement('recent_activations_body');

            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="6" class="py-6 text-sm text-slate-500">Nenhuma ativacao encontrada neste filtro.</td></tr>';
                return;
            }

            body.innerHTML = rows.map((row) => {
                const formatLabel = row.print_mode === 'front_and_back' ? 'Frente e verso' : 'Somente frente';
                return `
                <tr class="border-t border-slate-100">
                    <td class="py-3 pr-4 font-semibold text-slate-700">${row.date} ${row.time}</td>
                    <td class="py-3 pr-4 font-bold text-slate-900">${row.person_name}</td>
                    <td class="py-3 pr-4 text-slate-700">${row.fandom}</td>
                    <td class="py-3 pr-4 text-slate-700">${row.track}</td>
                    <td class="py-3 pr-4 text-slate-700">${row.frame_name}</td>
                    <td class="py-3 pr-4 text-slate-700">${formatLabel}</td>
                </tr>
            `;
            }).join('');
        }

        function renderCleanupCandidates(report) {
            const rows = report.cleanup_candidates || [];
            const body = getElement('cleanup_candidates_body');

            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="8" class="py-6 text-sm text-slate-500">Nenhum registro complementar neste filtro.</td></tr>';
                return;
            }

            body.innerHTML = rows.map((row) => {
                const meta = getJobStatusMeta(row.job_status);
                return `
                <tr class="border-t border-slate-100">
                    <td class="py-3 pr-4 font-semibold text-slate-700">${row.date} ${row.time}</td>
                    <td class="py-3 pr-4 font-bold text-slate-900">${row.person_name}</td>
                    <td class="py-3 pr-4 text-slate-700">${row.cpf_formatted || '--'}</td>
                    <td class="py-3 pr-4 text-slate-700">${row.fandom}</td>
                    <td class="py-3 pr-4 text-slate-700">${row.track}</td>
                    <td class="py-3 pr-4 text-slate-700">${row.frame_name}</td>
                    <td class="py-3 pr-4">
                        <span class="pill ${meta.className}">${meta.label}</span>
                    </td>
                    <td class="py-3 pr-4 font-mono text-xs text-slate-500">${row.job_id || '--'}</td>
                </tr>
            `;
            }).join('');
        }

        function renderInsights(report) {
            getElement('insights_list').innerHTML = (report.insights || []).map((item) => `
                <div class="rounded-3xl border border-slate-200 bg-white px-4 py-4 text-sm leading-6 text-slate-700">${item}</div>
            `).join('');
        }

        function renderErrors(report) {
            const items = report.recent_errors || [];
            const container = getElement('recent_errors_list');

            if (!items.length) {
                container.innerHTML = '<div class="rounded-3xl border border-dashed border-slate-300 bg-white px-4 py-6 text-sm text-slate-500">Sem ocorrencias tecnicas recentes na janela filtrada.</div>';
                return;
            }

            container.innerHTML = items.slice().reverse().map((item) => `
                <div class="rounded-3xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="pill ${item.level === 'ERROR' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700'}">${item.level}</div>
                        <div class="text-xs font-semibold text-slate-500">${formatIsoToLocal(item.occurred_at_local)}</div>
                    </div>
                    <div class="mt-3 text-sm leading-6 text-slate-700">${item.message}</div>
                </div>
            `).join('');
        }

        function renderReport(report) {
            updateReportMeta(report);
            renderSummary(report);
            renderComparison(report);
            renderLineChart('chart_by_day', report.charts?.by_day?.labels || [], report.charts?.by_day?.values || []);
            renderBarColumns('chart_by_hour', report.charts?.by_hour?.labels || [], report.charts?.by_hour?.values || []);
            renderRankedBars('chart_job_status', report.charts?.job_status || [], 'amber');
            renderHeatmap(report);
            renderRankedBars('chart_frames', report.charts?.frames || [], 'teal');
            renderRankedBars('chart_print_modes', report.charts?.print_modes || [], 'slate');
            renderRankedBars('chart_fandoms', report.charts?.top_fandoms || [], 'emerald');
            renderRankedBars('chart_tracks', report.charts?.top_tracks || [], 'rose');
            renderRecentActivations(report);
            renderCleanupCandidates(report);
            renderInsights(report);
            renderErrors(report);
        }

        async function loadReport(silent = false) {
            const params = new URLSearchParams({
                format: 'json',
                date_from: getElement('date_from').value,
                date_to: getElement('date_to').value,
                hour_from: getElement('hour_from').value,
                hour_to: getElement('hour_to').value
            });

            history.replaceState({}, '', `report.php?${params.toString().replace('format=json&', '')}`);

            if (!silent) {
                updateRefreshStatus('Atualizando...');
            }

            try {
                const response = await fetch(`report.php?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json'
                    },
                    cache: 'no-store'
                });
                const payload = await response.json();

                if (!response.ok || !payload.ok) {
                    throw new Error(payload.error || 'Falha ao atualizar o relatorio.');
                }

                renderReport(payload.report);
                updateRefreshStatus(`Atualizado em ${formatIsoToLocal(payload.report.generated_at)}`);
            } catch (error) {
                updateRefreshStatus(error.message || 'Falha ao atualizar.');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderReport(initialReport);
            updateRefreshStatus(`Atualizado em ${formatIsoToLocal(initialReport.generated_at)}`);

            getElement('print_button').addEventListener('click', () => window.print());
            getElement('apply_filters_button').addEventListener('click', () => loadReport(false));
            getElement('refresh_button').addEventListener('click', () => loadReport(false));

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    loadReport(true);
                }
            });

            window.addEventListener('focus', () => loadReport(true));
        });
    </script>
</body>

</html>
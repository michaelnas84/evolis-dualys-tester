<?php
declare(strict_types=1);

header('X-Frame-Options: SAMEORIGIN');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* English comments only */

const PROJECT_ROOT_PATH = __DIR__ . '\\..';
const HOTFOLDER_ROOT_PATH = 'C:\\card_hotfolder';
const HOTFOLDER_IN_PATH = HOTFOLDER_ROOT_PATH . '\\in';
const HOTFOLDER_DONE_PATH = HOTFOLDER_ROOT_PATH . '\\done';
const HOTFOLDER_ERROR_PATH = HOTFOLDER_ROOT_PATH . '\\error';
const HOTFOLDER_LOGS_PATH = HOTFOLDER_ROOT_PATH . '\\logs';
const HOTFOLDER_CONFIG_PATH = HOTFOLDER_ROOT_PATH . '\\config';
const HOTFOLDER_BACKGROUNDS_PATH = HOTFOLDER_ROOT_PATH . '\\backgrounds';

const HOTFOLDER_CONFIG_FILE_PATH = HOTFOLDER_CONFIG_PATH . '\\app_config.json';
const HOTFOLDER_PRINTER_FILE_PATH = HOTFOLDER_ROOT_PATH . '\\card_hotfolder_printer.py';

const PROJECT_PYTHON_SOURCE_FILE_PATH = PROJECT_ROOT_PATH . '\\..\\card_hotfolder\\card_hotfolder_printer.py';

const TEMPLATE_FRONT_FILE_NAME = 'template_front.png';
const TEMPLATE_BACK_FILE_NAME = 'template_back.png';
const STATIC_BACK_FILE_NAME = 'static_back.png';

const HOTFOLDER_TEMPLATE_FRONT_FILE_PATH = HOTFOLDER_BACKGROUNDS_PATH . '\\' . TEMPLATE_FRONT_FILE_NAME;
const HOTFOLDER_TEMPLATE_BACK_FILE_PATH = HOTFOLDER_BACKGROUNDS_PATH . '\\' . TEMPLATE_BACK_FILE_NAME;
const HOTFOLDER_STATIC_BACK_FILE_PATH = HOTFOLDER_BACKGROUNDS_PATH . '\\' . STATIC_BACK_FILE_NAME;

function getDefaultAppConfig(): array
{
    return [
        'printer_defaults' => [
            'printer_name' => '',
            'copies' => 1,
            'duplex' => 'auto',
            'fit_mode' => 'fill',
            'rotate_degrees' => 0,
            'card_size_mm' => [
                'width_mm' => 85.6,
                'height_mm' => 53.98,
            ],
            'form_name' => 'CR80',
            'print_dpi' => 300,
            'auto_rotate' => true,
            'background_color_rgb' => [255, 255, 255],
        ],
        'static_back' => [
            'enabled' => false,
            'image_path' => HOTFOLDER_STATIC_BACK_FILE_PATH,
            'fit_mode' => 'fill',
            'rotate_degrees' => 0,
            'auto_rotate' => false,
        ],
        'template_print' => [
            'front_image_path' => HOTFOLDER_TEMPLATE_FRONT_FILE_PATH,
            'back_image_path' => HOTFOLDER_TEMPLATE_BACK_FILE_PATH,
            'mode' => 'front',
            'copies' => 1,
            'fit_mode' => 'fill',
            'rotate_degrees' => 0,
            'auto_rotate' => true,
            'background_color_rgb' => [255, 255, 255],
            'duplex' => 'true',
            'form_name' => 'CR80',
            'print_dpi' => 300,
            'card_size_mm' => [
                'width_mm' => 85.6,
                'height_mm' => 53.98,
            ],
        ],
        'job_detection' => [
            'enable_folder_manifest_jobs' => true,
            'enable_named_front_back_jobs' => true,
            'enable_single_file_jobs' => true,
        ],
    ];
}

function deepMergeArrays(array $base_array, array $override_array): array
{
    foreach ($override_array as $key => $value) {
        if (is_array($value) && isset($base_array[$key]) && is_array($base_array[$key])) {
            $base_array[$key] = deepMergeArrays($base_array[$key], $value);
        } else {
            $base_array[$key] = $value;
        }
    }

    return $base_array;
}

function ensureDirectoryExists(string $directory_path): void
{
    if (is_dir($directory_path)) {
        return;
    }

    if (!mkdir($directory_path, 0777, true) && !is_dir($directory_path)) {
        throw new RuntimeException('Could not create directory: ' . $directory_path);
    }
}

function ensureHotfolderStructure(): array
{
    $created_directories = [];
    $required_directories = [
        HOTFOLDER_ROOT_PATH,
        HOTFOLDER_IN_PATH,
        HOTFOLDER_DONE_PATH,
        HOTFOLDER_ERROR_PATH,
        HOTFOLDER_LOGS_PATH,
        HOTFOLDER_CONFIG_PATH,
        HOTFOLDER_BACKGROUNDS_PATH,
    ];

    foreach ($required_directories as $directory_path) {
        if (!is_dir($directory_path)) {
            ensureDirectoryExists($directory_path);
            $created_directories[] = $directory_path;
        }
    }

    return $created_directories;
}

function ensureConfigFileExists(): array
{
    ensureDirectoryExists(HOTFOLDER_CONFIG_PATH);

    $created = false;
    $default_config = getDefaultAppConfig();

    if (!file_exists(HOTFOLDER_CONFIG_FILE_PATH)) {
        $encoded_json = json_encode($default_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded_json === false) {
            throw new RuntimeException('Could not encode default config.');
        }

        if (file_put_contents(HOTFOLDER_CONFIG_FILE_PATH, $encoded_json, LOCK_EX) === false) {
            throw new RuntimeException('Could not write config file.');
        }

        $created = true;
    }

    $decoded_json = readHotfolderConfigFile();

    $merged_config = deepMergeArrays($default_config, $decoded_json);

    return [
        'created' => $created,
        'data' => $merged_config,
        'raw_data' => $decoded_json,
    ];
}

function readHotfolderConfigFile(): array
{
    if (!file_exists(HOTFOLDER_CONFIG_FILE_PATH)) {
        return [];
    }

    $raw_json = file_get_contents(HOTFOLDER_CONFIG_FILE_PATH);
    if ($raw_json === false) {
        throw new RuntimeException('Could not read config file.');
    }

    $decoded_json = json_decode($raw_json, true);
    return is_array($decoded_json) ? $decoded_json : [];
}

function writeConfigFile(array $config_payload): void
{
    ensureDirectoryExists(HOTFOLDER_CONFIG_PATH);

    $encoded_json = json_encode($config_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded_json === false) {
        throw new RuntimeException('Could not encode config JSON.');
    }

    $temporary_file_path = HOTFOLDER_CONFIG_FILE_PATH . '.tmp';

    if (file_put_contents($temporary_file_path, $encoded_json, LOCK_EX) === false) {
        throw new RuntimeException('Could not write temporary config file.');
    }

    if (!rename($temporary_file_path, HOTFOLDER_CONFIG_FILE_PATH)) {
        @unlink($temporary_file_path);
        throw new RuntimeException('Could not replace config file.');
    }
}

function copyProjectPythonToHotfolder(): array
{
    ensureDirectoryExists(HOTFOLDER_ROOT_PATH);

    if (!file_exists(PROJECT_PYTHON_SOURCE_FILE_PATH)) {
        throw new RuntimeException('Project Python source file not found: ' . PROJECT_PYTHON_SOURCE_FILE_PATH);
    }

    $copied = copy(PROJECT_PYTHON_SOURCE_FILE_PATH, HOTFOLDER_PRINTER_FILE_PATH);
    if ($copied === false) {
        throw new RuntimeException('Could not copy Python file to hotfolder.');
    }

    return [
        'source_path' => PROJECT_PYTHON_SOURCE_FILE_PATH,
        'destination_path' => HOTFOLDER_PRINTER_FILE_PATH,
    ];
}

function getHotfolderStatus(): array
{
    return [
        'paths' => [
            'hotfolder_root_path' => HOTFOLDER_ROOT_PATH,
            'hotfolder_in_path' => HOTFOLDER_IN_PATH,
            'hotfolder_done_path' => HOTFOLDER_DONE_PATH,
            'hotfolder_error_path' => HOTFOLDER_ERROR_PATH,
            'hotfolder_logs_path' => HOTFOLDER_LOGS_PATH,
            'hotfolder_config_path' => HOTFOLDER_CONFIG_PATH,
            'hotfolder_backgrounds_path' => HOTFOLDER_BACKGROUNDS_PATH,
            'hotfolder_config_file_path' => HOTFOLDER_CONFIG_FILE_PATH,
            'hotfolder_printer_file_path' => HOTFOLDER_PRINTER_FILE_PATH,
            'project_python_source_file_path' => PROJECT_PYTHON_SOURCE_FILE_PATH,
        ],
        'exists' => [
            'hotfolder_root_path' => is_dir(HOTFOLDER_ROOT_PATH),
            'hotfolder_in_path' => is_dir(HOTFOLDER_IN_PATH),
            'hotfolder_done_path' => is_dir(HOTFOLDER_DONE_PATH),
            'hotfolder_error_path' => is_dir(HOTFOLDER_ERROR_PATH),
            'hotfolder_logs_path' => is_dir(HOTFOLDER_LOGS_PATH),
            'hotfolder_config_path' => is_dir(HOTFOLDER_CONFIG_PATH),
            'hotfolder_backgrounds_path' => is_dir(HOTFOLDER_BACKGROUNDS_PATH),
            'hotfolder_config_file_path' => file_exists(HOTFOLDER_CONFIG_FILE_PATH),
            'hotfolder_printer_file_path' => file_exists(HOTFOLDER_PRINTER_FILE_PATH),
            'project_python_source_file_path' => file_exists(PROJECT_PYTHON_SOURCE_FILE_PATH),
            'template_front_file_path' => file_exists(HOTFOLDER_TEMPLATE_FRONT_FILE_PATH),
            'template_back_file_path' => file_exists(HOTFOLDER_TEMPLATE_BACK_FILE_PATH),
            'static_back_file_path' => file_exists(HOTFOLDER_STATIC_BACK_FILE_PATH),
        ],
        'files' => [
            'template_front' => buildAssetStatus(HOTFOLDER_TEMPLATE_FRONT_FILE_PATH, TEMPLATE_FRONT_FILE_NAME),
            'template_back' => buildAssetStatus(HOTFOLDER_TEMPLATE_BACK_FILE_PATH, TEMPLATE_BACK_FILE_NAME),
            'static_back' => buildAssetStatus(HOTFOLDER_STATIC_BACK_FILE_PATH, STATIC_BACK_FILE_NAME),
        ],
    ];
}

function buildAssetStatus(string $absolute_file_path, string $file_name): array
{
    $exists = file_exists($absolute_file_path);

    return [
        'file_name' => $file_name,
        'absolute_file_path' => $absolute_file_path,
        'exists' => $exists,
        'size_bytes' => $exists ? filesize($absolute_file_path) : null,
        'modified_at' => $exists ? date('Y-m-d H:i:s', (int) filemtime($absolute_file_path)) : null,
    ];
}

function respondJson(int $status_code, array $payload): void
{
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array
{
    $raw_input = file_get_contents('php://input');
    if ($raw_input === false || trim($raw_input) === '') {
        throw new RuntimeException('Empty request body.');
    }

    $decoded_input = json_decode($raw_input, true);
    if (!is_array($decoded_input)) {
        throw new RuntimeException('Invalid JSON body.');
    }

    return $decoded_input;
}

function validateConfigPayload(array $config_payload): void
{
    $valid_duplex_values = ['auto', 'true', 'false'];
    $valid_fit_mode_values = ['fill', 'contain', 'stretch'];
    $valid_rotate_values = [0, 90, 180, 270];
    $valid_template_mode_values = ['front', 'front_back'];

    if (!isset($config_payload['printer_defaults']) || !is_array($config_payload['printer_defaults'])) {
        throw new RuntimeException('Missing printer_defaults.');
    }

    if (!isset($config_payload['static_back']) || !is_array($config_payload['static_back'])) {
        throw new RuntimeException('Missing static_back.');
    }

    if (!isset($config_payload['template_print']) || !is_array($config_payload['template_print'])) {
        throw new RuntimeException('Missing template_print.');
    }

    if (!isset($config_payload['job_detection']) || !is_array($config_payload['job_detection'])) {
        throw new RuntimeException('Missing job_detection.');
    }

    if (!in_array((string) ($config_payload['printer_defaults']['duplex'] ?? ''), $valid_duplex_values, true)) {
        throw new RuntimeException('Invalid printer_defaults.duplex.');
    }

    if (!in_array((string) ($config_payload['printer_defaults']['fit_mode'] ?? ''), $valid_fit_mode_values, true)) {
        throw new RuntimeException('Invalid printer_defaults.fit_mode.');
    }

    if (!in_array((int) ($config_payload['printer_defaults']['rotate_degrees'] ?? -1), $valid_rotate_values, true)) {
        throw new RuntimeException('Invalid printer_defaults.rotate_degrees.');
    }

    if (!in_array((string) ($config_payload['static_back']['fit_mode'] ?? ''), $valid_fit_mode_values, true)) {
        throw new RuntimeException('Invalid static_back.fit_mode.');
    }

    if (!in_array((int) ($config_payload['static_back']['rotate_degrees'] ?? -1), $valid_rotate_values, true)) {
        throw new RuntimeException('Invalid static_back.rotate_degrees.');
    }

    if (!in_array((string) ($config_payload['template_print']['mode'] ?? ''), $valid_template_mode_values, true)) {
        throw new RuntimeException('Invalid template_print.mode.');
    }

    if (!in_array((string) ($config_payload['template_print']['fit_mode'] ?? ''), $valid_fit_mode_values, true)) {
        throw new RuntimeException('Invalid template_print.fit_mode.');
    }

    if (!in_array((int) ($config_payload['template_print']['rotate_degrees'] ?? -1), $valid_rotate_values, true)) {
        throw new RuntimeException('Invalid template_print.rotate_degrees.');
    }

    if (!in_array((string) ($config_payload['template_print']['duplex'] ?? ''), $valid_duplex_values, true)) {
        throw new RuntimeException('Invalid template_print.duplex.');
    }
}

function assertRequestMethod(string $expected_method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($expected_method)) {
        throw new RuntimeException('Method not allowed.');
    }
}

function assertAdminUnlocked(): void
{
    if (!(bool)($_SESSION['admin_unlocked'] ?? false)) {
        throw new RuntimeException('Not authorized.');
    }
}

function getAssetTargetPathByKey(string $asset_key): string
{
    return match ($asset_key) {
        'template_front' => HOTFOLDER_TEMPLATE_FRONT_FILE_PATH,
        'template_back' => HOTFOLDER_TEMPLATE_BACK_FILE_PATH,
        'static_back' => HOTFOLDER_STATIC_BACK_FILE_PATH,
        default => throw new RuntimeException('Invalid asset key.'),
    };
}

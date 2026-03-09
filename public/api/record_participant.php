<?php

declare(strict_types=1);

/**
 * api/record_participant.php
 *
 * Endpoint POST – grava o participante completo no banco após o job
 * de impressão ser criado com sucesso.
 *
 * Chamado pelo front-end logo após create_job.php retornar { ok: true }.
 *
 * Request body (JSON):
 *   {
 *     "csrf_token":        "...",
 *
 *     // Identificação
 *     "cpf":               "12345678901",   // somente dígitos
 *
 *     // Formulário
 *     "person_name":       "João Silva",
 *     "fandom":            "Swifties",
 *     "track":             "Cruel Summer",
 *
 *     // Frame selecionado
 *     "frame_name":        "frame_01_front",
 *
 *     // Job de impressão
 *     "job_id":            "JOB-20240309-001",
 *     "job_folder_path":   "/path/to/job",
 *     "print_mode":        "front_only",
 *
 *     // Chaves das imagens compostas
 *     "front_image_key":   "abc123.jpg",
 *     "back_image_key":    "",
 *
 *     // Foto (opcional – omitir para economizar espaço no banco)
 *     "photo_data_url":    "data:image/jpeg;base64,..."
 *   }
 *
 * Response (JSON):
 *   { "ok": true, "participant_id": 42 }
 *   { "ok": false, "error": "mensagem" }
 */

session_start();

// ── Caminhos ────────────────────────────────────────────────────────────────
$project_root = dirname(__DIR__, 2);
require_once $project_root . '/src/helpers.php';
require_once $project_root . '/src/database.php';

// ── Método ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

// ── Body ─────────────────────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    jsonResponse(['ok' => false, 'error' => 'Empty request body.'], 400);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON.'], 400);
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
$csrf         = (string)($payload['csrf_token'] ?? '');
$session_csrf = (string)($_SESSION['csrf_token'] ?? '');
if ($csrf === '' || $session_csrf === '' || !hash_equals($session_csrf, $csrf)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid CSRF token.'], 403);
}

// ── Campos obrigatórios ──────────────────────────────────────────────────────
$required = ['cpf', 'person_name', 'fandom', 'track', 'frame_name', 'job_id'];
foreach ($required as $field) {
    if (trim((string)($payload[$field] ?? '')) === '') {
        jsonResponse(['ok' => false, 'error' => "Campo obrigatório ausente: {$field}."], 400);
    }
}

// ── Grava no banco ───────────────────────────────────────────────────────────
try {
    $db = db_connect();

    $result = db_record_participant($db, [
        'cpf'              => (string)($payload['cpf']              ?? ''),
        'person_name'      => (string)($payload['person_name']      ?? ''),
        'fandom'           => (string)($payload['fandom']           ?? ''),
        'track'            => (string)($payload['track']            ?? ''),
        'frame_name'       => (string)($payload['frame_name']       ?? ''),
        'job_id'           => (string)($payload['job_id']           ?? ''),
        'job_folder_path'  => (string)($payload['job_folder_path']  ?? ''),
        'print_mode'       => (string)($payload['print_mode']       ?? ''),
        'front_image_key'  => (string)($payload['front_image_key']  ?? ''),
        'back_image_key'   => (string)($payload['back_image_key']   ?? ''),
        // photo_data_url é opcional — omitir do payload economiza muito espaço
        'photo_data_url'   => isset($payload['photo_data_url'])
                              ? (string)$payload['photo_data_url']
                              : null,
    ]);

    if (!$result['ok']) {
        jsonResponse(['ok' => false, 'error' => $result['error']], 422);
    }

    jsonResponse(['ok' => true, 'participant_id' => $result['participant_id']]);

} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}

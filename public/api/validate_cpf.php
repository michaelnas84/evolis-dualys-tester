<?php

declare(strict_types=1);

/**
 * api/validate_cpf.php
 *
 * Endpoint POST – valida o CPF digitado pelo usuário.
 *
 * Request body (JSON):
 *   {
 *     "csrf_token": "...",
 *     "cpf": "12345678901"   // somente dígitos (11 chars)
 *   }
 *
 * Response (JSON):
 *   { "ok": true }
 *   { "ok": false, "error": "mensagem" }
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * NOTA: O registro completo do participante (com nome, fandom, frame, job_id,
 * etc.) é feito em api/record_participant.php, chamado pelo front-end logo
 * após o create_job retornar com sucesso.
 *
 * Se preferir gravar tudo de uma vez dentro do create_job.php, veja o
 * comentário "INTEGRAÇÃO COM create_job.php" no final deste arquivo.
 */

session_start();

// ── Caminhos ────────────────────────────────────────────────────────────────
$project_root = dirname(__DIR__, 2);             // two levels up from api/
require_once $project_root . '/src/helpers.php';
require_once $project_root . '/src/database.php';

$config = require $project_root . '/src/config.php';

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
$csrf          = (string)($payload['csrf_token'] ?? '');
$session_csrf  = (string)($_SESSION['csrf_token'] ?? '');
if ($csrf === '' || $session_csrf === '' || !hash_equals($session_csrf, $csrf)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid CSRF token.'], 403);
}

// ── Extrai CPF ───────────────────────────────────────────────────────────────
$cpf_raw = (string)($payload['cpf'] ?? '');
if ($cpf_raw === '') {
    jsonResponse(['ok' => false, 'error' => 'CPF não informado.'], 400);
}

// ── Banco + validação ────────────────────────────────────────────────────────
try {
    $db     = db_connect();
    $result = db_validate_cpf($db, $cpf_raw);

    if (!$result['ok']) {
        jsonResponse(['ok' => false, 'error' => $result['error']], 422);
    }

    jsonResponse(['ok' => true]);

} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}

/*
 * ─────────────────────────────────────────────────────────────────────────────
 * INTEGRAÇÃO COM create_job.php
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Se quiser gravar tudo dentro do create_job.php (sem um endpoint separado),
 * adicione no final do bloco try de create_job.php, logo após o jsonResponse
 * de sucesso, algo como:
 *
 *   require_once __DIR__ . '/../src/database.php';
 *   $db = db_connect();
 *   db_record_participant($db, [
 *       'cpf'              => (string)($payload['cpf']        ?? ''),
 *       'person_name'      => (string)($payload['person_name'] ?? ''),
 *       'fandom'           => (string)($payload['artist_name'] ?? ''),
 *       'track'            => (string)($payload['track_name']  ?? ''),
 *       'frame_name'       => (string)($payload['frame_name']  ?? ''),
 *       'job_id'           => $job_id,
 *       'job_folder_path'  => $job_folder_path,
 *       'print_mode'       => $print_mode,
 *       'front_image_key'  => $front_composed_image_key,
 *       'back_image_key'   => $back_composed_image_key,
 *       // 'photo_data_url' => ...,  // omitir para economizar espaço
 *   ]);
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */

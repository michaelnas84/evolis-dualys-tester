<?php

declare(strict_types=1);

/**
 * database.php
 *
 * Centraliza toda a lógica de banco de dados (SQLite).
 *
 * Uso:
 *   $db = db_connect();              // abre/cria o banco
 *   db_migrate($db);                 // cria tabelas se não existirem
 *   db_validate_cpf($db, '12345..'); // retorna ['ok'=>bool, 'error'=>string]
 *   db_record_participant($db, [...]);
 *
 * O arquivo SQLite fica em:
 *   <project_root>/storage/kiosk.sqlite
 *
 * Estrutura de diretórios esperada (igual ao restante do projeto):
 *   project/
 *     api/
 *       validate_cpf.php
 *       create_job.php
 *       ...
 *     src/
 *       database.php      ← este arquivo
 *       config.php
 *       helpers.php
 *     storage/
 *       kiosk.sqlite      ← criado automaticamente
 *       composed/
 *     public/
 *       index.php
 */

// ──────────────────────────────────────────────────────────────────────────────
// Caminho do banco
// ──────────────────────────────────────────────────────────────────────────────
define('DB_PATH', __DIR__ . '/../storage/kiosk.sqlite');

// ──────────────────────────────────────────────────────────────────────────────
// db_connect()
//   Abre (ou cria) a conexão PDO com o SQLite.
//   Chama db_migrate() automaticamente na primeira abertura.
// ──────────────────────────────────────────────────────────────────────────────
function db_connect(): PDO
{
    $storage_dir = dirname(DB_PATH);
    if (!is_dir($storage_dir)) {
        if (!mkdir($storage_dir, 0775, true) && !is_dir($storage_dir)) {
            throw new RuntimeException('Não foi possível criar o diretório de storage: ' . $storage_dir);
        }
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ]);

    // WAL mode: melhor performance para leituras/escritas simultâneas
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA synchronous = NORMAL');

    db_migrate($pdo);

    return $pdo;
}

// ──────────────────────────────────────────────────────────────────────────────
// db_migrate()
//   Cria as tabelas necessárias caso não existam.
//   Seguro para rodar toda vez que o banco é aberto (IF NOT EXISTS).
// ──────────────────────────────────────────────────────────────────────────────
function db_migrate(PDO $pdo): void
{
    // ── Tabela principal de participantes ──────────────────────────────────
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS participants (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,

            -- Identificação
            cpf              TEXT    NOT NULL UNIQUE,        -- somente dígitos (11 chars)
            cpf_formatted    TEXT    NOT NULL,               -- formatado: 000.000.000-00

            -- Dados do formulário
            person_name      TEXT    NOT NULL,
            fandom           TEXT    NOT NULL,               -- campo "FANDOM"
            track            TEXT    NOT NULL,               -- campo "VIVO OUVINDO"

            -- Frame de impressão selecionado
            frame_name       TEXT    NOT NULL DEFAULT '',    -- ex: frame_01_front

            -- Job de impressão gerado
            job_id           TEXT    NOT NULL DEFAULT '',
            job_folder_path  TEXT    NOT NULL DEFAULT '',
            print_mode       TEXT    NOT NULL DEFAULT '',    -- front_only | front_and_back

            -- Composição de imagem
            front_image_key  TEXT    NOT NULL DEFAULT '',
            back_image_key   TEXT    NOT NULL DEFAULT '',

            -- Foto original (data-url JPEG) — pode ser grande; use com cautela
            -- Deixe NULL se quiser economizar espaço e armazenar só o job_id
            photo_data_url   TEXT,

            -- Metadados
            created_at       TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
            updated_at       TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
            ip_address       TEXT    NOT NULL DEFAULT '',
            user_agent       TEXT    NOT NULL DEFAULT ''
        )
    SQL);

    // Índice para acelerar buscas por CPF
    $pdo->exec(<<<SQL
        CREATE INDEX IF NOT EXISTS idx_participants_cpf
        ON participants (cpf)
    SQL);

    // Índice por data de criação (relatórios)
    $pdo->exec(<<<SQL
        CREATE INDEX IF NOT EXISTS idx_participants_created_at
        ON participants (created_at)
    SQL);

    // ── Tabela de log de erros/eventos (opcional, útil para debug) ─────────
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS event_log (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            event      TEXT NOT NULL,          -- 'cpf_duplicate', 'job_created', 'error', ...
            detail     TEXT NOT NULL DEFAULT '',
            cpf        TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
            ip_address TEXT NOT NULL DEFAULT ''
        )
    SQL);

    $pdo->exec(<<<SQL
        CREATE INDEX IF NOT EXISTS idx_event_log_created_at
        ON event_log (created_at)
    SQL);
}

// ──────────────────────────────────────────────────────────────────────────────
// db_validate_cpf()
//   Valida o formato do CPF e verifica se já foi utilizado.
//
//   Retorna:
//     ['ok' => true]
//     ['ok' => false, 'error' => 'mensagem']
// ──────────────────────────────────────────────────────────────────────────────
function db_validate_cpf(PDO $pdo, string $cpf_raw): array
{
    // Remove qualquer caractere não-dígito
    $digits = preg_replace('/\D/', '', $cpf_raw);

    if ($digits === null || strlen($digits) !== 11) {
        return ['ok' => false, 'error' => 'CPF deve conter 11 dígitos.'];
    }

    // Rejeita CPFs com todos os dígitos iguais (000...0, 111...1, etc.)
    if (preg_match('/^(\d)\1{10}$/', $digits)) {
        return ['ok' => false, 'error' => 'CPF inválido.'];
    }

    // Verificação do primeiro dígito verificador
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (int)$digits[$i] * (10 - $i);
    }
    $remainder = $sum % 11;
    $d1 = $remainder < 2 ? 0 : 11 - $remainder;
    if ((int)$digits[9] !== $d1) {
        // return ['ok' => false, 'error' => 'CPF inválido.'];
    }

    // Verificação do segundo dígito verificador
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $sum += (int)$digits[$i] * (11 - $i);
    }
    $remainder = $sum % 11;
    $d2 = $remainder < 2 ? 0 : 11 - $remainder;
    if ((int)$digits[10] !== $d2) {
        // return ['ok' => false, 'error' => 'CPF inválido.'];
    }

    // Verifica se já foi usado
    $stmt = $pdo->prepare('SELECT id FROM participants WHERE cpf = :cpf LIMIT 1');
    $stmt->execute([':cpf' => $digits]);
    if ($stmt->fetch() !== false) {
        // Registra tentativa duplicada no log
        db_log_event($pdo, 'cpf_duplicate', 'CPF já utilizado', $digits);
        return ['ok' => false, 'error' => 'Este CPF já foi utilizado.'];
    }

    return ['ok' => true];
}

// ──────────────────────────────────────────────────────────────────────────────
// db_record_participant()
//   Grava (INSERT ou UPDATE) o participante com todos os dados coletados
//   ao longo da jornada.
//
//   $data = [
//     // Obrigatórios
//     'cpf'              => '12345678901',   // somente dígitos
//
//     // Formulário
//     'person_name'      => 'João Silva',
//     'fandom'           => 'Swifties',
//     'track'            => 'Cruel Summer',
//
//     // Frame selecionado
//     'frame_name'       => 'frame_01_front',
//
//     // Job de impressão (preenchido após create_job)
//     'job_id'           => 'JOB-20240309-001',
//     'job_folder_path'  => '/path/to/job',
//     'print_mode'       => 'front_only',
//
//     // Chaves das imagens compostas
//     'front_image_key'  => 'abc123.jpg',
//     'back_image_key'   => '',
//
//     // Foto (opcional – pode ser omitida para economizar espaço)
//     'photo_data_url'   => 'data:image/jpeg;base64,...',
//
//     // Metadados (opcionais – preenchidos automaticamente se não informados)
//     'ip_address'       => '192.168.1.1',
//     'user_agent'       => 'Mozilla/5.0 ...',
//   ]
//
//   Comportamento:
//     - Se o CPF ainda não existe: INSERT
//     - Se já existe (ex: re-tentativa de job): UPDATE dos campos de job
//
//   Retorna:
//     ['ok' => true,  'participant_id' => 42]
//     ['ok' => false, 'error' => 'mensagem']
// ──────────────────────────────────────────────────────────────────────────────
function db_record_participant(PDO $pdo, array $data): array
{
    $digits = preg_replace('/\D/', '', (string)($data['cpf'] ?? ''));
    if ($digits === null || strlen($digits) !== 11) {
        return ['ok' => false, 'error' => 'CPF inválido para gravação.'];
    }

    $cpf_formatted = sprintf(
        '%s.%s.%s-%s',
        substr($digits, 0, 3),
        substr($digits, 3, 3),
        substr($digits, 6, 3),
        substr($digits, 9, 2)
    );

    $person_name     = trim((string)($data['person_name']     ?? ''));
    $fandom          = trim((string)($data['fandom']          ?? ''));
    $track           = trim((string)($data['track']           ?? ''));
    $frame_name      = trim((string)($data['frame_name']      ?? ''));
    $job_id          = trim((string)($data['job_id']          ?? ''));
    $job_folder_path = trim((string)($data['job_folder_path'] ?? ''));
    $print_mode      = trim((string)($data['print_mode']      ?? ''));
    $front_image_key = trim((string)($data['front_image_key'] ?? ''));
    $back_image_key  = trim((string)($data['back_image_key']  ?? ''));
    $photo_data_url  = isset($data['photo_data_url']) ? (string)$data['photo_data_url'] : null;
    $ip_address      = trim((string)($data['ip_address']      ?? ($_SERVER['REMOTE_ADDR'] ?? '')));
    $user_agent      = trim((string)($data['user_agent']      ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    $now             = gmdate('Y-m-d\TH:i:s.') . sprintf('%03d', (int)(microtime(true) * 1000) % 1000) . 'Z';

    // Verifica se CPF já existe
    $stmt = $pdo->prepare('SELECT id FROM participants WHERE cpf = :cpf LIMIT 1');
    $stmt->execute([':cpf' => $digits]);
    $existing = $stmt->fetch();

    if ($existing !== false) {
        // UPDATE — atualiza dados de job (o usuário completou uma segunda tentativa)
        $stmt = $pdo->prepare(<<<SQL
            UPDATE participants SET
                person_name      = :person_name,
                fandom           = :fandom,
                track            = :track,
                frame_name       = :frame_name,
                job_id           = :job_id,
                job_folder_path  = :job_folder_path,
                print_mode       = :print_mode,
                front_image_key  = :front_image_key,
                back_image_key   = :back_image_key,
                photo_data_url   = COALESCE(:photo_data_url, photo_data_url),
                updated_at       = :updated_at
            WHERE cpf = :cpf
        SQL);

        $stmt->execute([
            ':person_name'     => $person_name,
            ':fandom'          => $fandom,
            ':track'           => $track,
            ':frame_name'      => $frame_name,
            ':job_id'          => $job_id,
            ':job_folder_path' => $job_folder_path,
            ':print_mode'      => $print_mode,
            ':front_image_key' => $front_image_key,
            ':back_image_key'  => $back_image_key,
            ':photo_data_url'  => $photo_data_url,
            ':updated_at'      => $now,
            ':cpf'             => $digits,
        ]);

        $participant_id = (int)$existing['id'];
    } else {
        // INSERT
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO participants (
                cpf, cpf_formatted,
                person_name, fandom, track,
                frame_name,
                job_id, job_folder_path, print_mode,
                front_image_key, back_image_key,
                photo_data_url,
                created_at, updated_at,
                ip_address, user_agent
            ) VALUES (
                :cpf, :cpf_formatted,
                :person_name, :fandom, :track,
                :frame_name,
                :job_id, :job_folder_path, :print_mode,
                :front_image_key, :back_image_key,
                :photo_data_url,
                :created_at, :updated_at,
                :ip_address, :user_agent
            )
        SQL);

        $stmt->execute([
            ':cpf'             => $digits,
            ':cpf_formatted'   => $cpf_formatted,
            ':person_name'     => $person_name,
            ':fandom'          => $fandom,
            ':track'           => $track,
            ':frame_name'      => $frame_name,
            ':job_id'          => $job_id,
            ':job_folder_path' => $job_folder_path,
            ':print_mode'      => $print_mode,
            ':front_image_key' => $front_image_key,
            ':back_image_key'  => $back_image_key,
            ':photo_data_url'  => $photo_data_url,
            ':created_at'      => $now,
            ':updated_at'      => $now,
            ':ip_address'      => $ip_address,
            ':user_agent'      => $user_agent,
        ]);

        $participant_id = (int)$pdo->lastInsertId();
    }

    db_log_event($pdo, 'participant_recorded', "job_id={$job_id} frame={$frame_name}", $digits);

    return ['ok' => true, 'participant_id' => $participant_id];
}

// ──────────────────────────────────────────────────────────────────────────────
// db_log_event()
//   Grava um evento no log interno. Não lança exceção em caso de falha.
// ──────────────────────────────────────────────────────────────────────────────
function db_log_event(PDO $pdo, string $event, string $detail = '', string $cpf = ''): void
{
    try {
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO event_log (event, detail, cpf, ip_address)
            VALUES (:event, :detail, :cpf, :ip)
        SQL);
        $stmt->execute([
            ':event'  => $event,
            ':detail' => $detail,
            ':cpf'    => preg_replace('/\D/', '', $cpf) ?? '',
            ':ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable) {
        // Silencia erros de log para não interromper o fluxo principal
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// db_get_participant_by_cpf()
//   Busca um participante pelo CPF (somente dígitos ou formatado).
//   Retorna o array de colunas ou null se não encontrado.
// ──────────────────────────────────────────────────────────────────────────────
function db_get_participant_by_cpf(PDO $pdo, string $cpf_raw): ?array
{
    $digits = preg_replace('/\D/', '', $cpf_raw) ?? '';
    $stmt = $pdo->prepare('SELECT * FROM participants WHERE cpf = :cpf LIMIT 1');
    $stmt->execute([':cpf' => $digits]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

// ──────────────────────────────────────────────────────────────────────────────
// db_list_participants()
//   Lista participantes com filtros e paginação (útil para o admin).
//
//   $opts = [
//     'limit'        => 50,
//     'offset'       => 0,
//     'order_by'     => 'created_at',   // coluna válida
//     'order_dir'    => 'DESC',
//     'search_name'  => 'João',         // busca parcial em person_name
//     'date_from'    => '2024-03-01',
//     'date_to'      => '2024-03-31',
//   ]
// ──────────────────────────────────────────────────────────────────────────────
function db_list_participants(PDO $pdo, array $opts = []): array
{
    $limit     = max(1, min(500, (int)($opts['limit'] ?? 50)));
    $offset    = max(0, (int)($opts['offset'] ?? 0));
    $allowed_cols = ['id','cpf','person_name','fandom','track','frame_name','job_id','created_at'];
    $order_by  = in_array($opts['order_by'] ?? '', $allowed_cols, true)
                 ? $opts['order_by'] : 'created_at';
    $order_dir = strtoupper($opts['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    $where  = [];
    $params = [];

    if (!empty($opts['search_name'])) {
        $where[]             = "person_name LIKE :sname";
        $params[':sname']    = '%' . $opts['search_name'] . '%';
    }
    if (!empty($opts['date_from'])) {
        $where[]             = "created_at >= :dfrom";
        $params[':dfrom']    = $opts['date_from'];
    }
    if (!empty($opts['date_to'])) {
        $where[]             = "created_at <= :dto";
        $params[':dto']      = $opts['date_to'] . 'T23:59:59Z';
    }

    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM participants {$where_sql}");
    $count_stmt->execute($params);
    $total = (int)($count_stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->prepare(<<<SQL
        SELECT id, cpf_formatted, person_name, fandom, track,
               frame_name, job_id, print_mode, created_at, ip_address
        FROM participants
        {$where_sql}
        ORDER BY {$order_by} {$order_dir}
        LIMIT :lim OFFSET :off
    SQL);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return [
        'total'  => $total,
        'limit'  => $limit,
        'offset' => $offset,
        'rows'   => $rows,
    ];
}

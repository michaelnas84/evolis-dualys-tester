<?php
declare(strict_types=1);

require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/compositor_helpers.php';

ensureSessionStarted();
$csrf_token = ensureCsrfToken();

function getPrinterProfiles(): array
{
    return [
        'evolis_dualys_series' => [
            'label' => 'Evolis Dualys',
            'project_python_relative_path' => 'card_hotfolder\\evolis_dualys_series\\card_hotfolder_printer.py',
        ],
        'zebra_zc300' => [
            'label' => 'Zebra ZC300',
            'project_python_relative_path' => 'card_hotfolder\\zebra_zc300\\card_hotfolder_printer.py',
        ],
    ];
}

function syncHotfolderPython(string $printer_profile, string $hotfolder_root_path): string
{
    $printer_profiles = getPrinterProfiles();

    if (!isset($printer_profiles[$printer_profile])) {
        throw new RuntimeException('Invalid printer_profile: ' . $printer_profile);
    }

    $project_root_path = __DIR__;
    $source_python_path = $project_root_path . DIRECTORY_SEPARATOR . $printer_profiles[$printer_profile]['project_python_relative_path'];
    $target_python_path = $hotfolder_root_path . '\\card_hotfolder_printer.py';

    if (!is_file($source_python_path)) {
        throw new RuntimeException('Python source file not found: ' . $source_python_path);
    }

    if (!is_dir($hotfolder_root_path) && !mkdir($hotfolder_root_path, 0777, true) && !is_dir($hotfolder_root_path)) {
        throw new RuntimeException('Could not create hotfolder root: ' . $hotfolder_root_path);
    }

    if (!copy($source_python_path, $target_python_path)) {
        throw new RuntimeException('Could not copy Python file to hotfolder.');
    }

    return $target_python_path;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'print_template_batch') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        ensureAdminUnlockedJson();

        $raw_input = file_get_contents('php://input');
        if ($raw_input === false || trim($raw_input) === '') {
            throw new RuntimeException('Empty request body.');
        }

        $payload = json_decode($raw_input, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid JSON body.');
        }

        $printer_profile = (string) ($payload['printer_profile'] ?? 'evolis_dualys_series');
        $template_mode = (string) ($payload['template_mode'] ?? 'front');
        $template_copies = (int) ($payload['template_copies'] ?? 1);

        if (!in_array($template_mode, ['front', 'front_back'], true)) {
            throw new RuntimeException('Invalid template_mode.');
        }

        if ($template_copies < 1) {
            throw new RuntimeException('template_copies must be at least 1.');
        }

        $hotfolder_root_path = 'C:\\card_hotfolder';
        $python_script_path = syncHotfolderPython($printer_profile, $hotfolder_root_path);

        if (!file_exists($python_script_path)) {
            throw new RuntimeException('Python file not found in hotfolder: ' . $python_script_path);
        }

        $command_suffix =
            escapeshellarg($python_script_path) .
            ' --root ' . escapeshellarg($hotfolder_root_path) .
            ' --print_template ' .
            ' --template_mode ' . escapeshellarg($template_mode);

        $command_candidates = [
            'py -3 ' . $command_suffix,
            'python ' . $command_suffix,
        ];

        $last_output = [];
        $last_exit_code = 1;
        $executed_command = null;

        foreach ($command_candidates as $command) {
            $output_lines = [];
            $exit_code = 1;

            @exec($command . ' 2>&1', $output_lines, $exit_code);

            $executed_command = $command;
            $last_output = $output_lines;
            $last_exit_code = $exit_code;

            if ($exit_code === 0) {
                break;
            }
        }

        if ($last_exit_code !== 0) {
            throw new RuntimeException(
                "Could not execute template print. Last command: {$executed_command}. Output: " .
                implode(' | ', $last_output)
            );
        }

        echo json_encode([
            'success' => true,
            'message' => 'Template batch sent to printer successfully.',
            'printer_profile' => $printer_profile,
            'template_mode' => $template_mode,
            'template_copies' => $template_copies,
            'executed_command' => $executed_command,
            'output' => $last_output,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $throwable) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $throwable->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$printer_profiles = getPrinterProfiles();
$admin_unlocked = isAdminUnlocked();
$compositor_base_config = require __DIR__ . '/../src/compositor_config.php';
$compositor_override_config = loadCompositorOverrideConfig($compositor_base_config);
$compositor_effective_config = array_replace_recursive($compositor_base_config, $compositor_override_config);

if (!$admin_unlocked):
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Central</title>
    <script src="js/tailwind.js"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="mx-auto flex min-h-screen max-w-md items-center px-6 py-10">
        <div class="w-full rounded-3xl border border-slate-800 bg-slate-900 p-8 shadow-2xl shadow-slate-950/40">
            <div class="text-sm font-semibold uppercase tracking-[0.24em] text-emerald-300">Painel central</div>
            <h1 class="mt-3 text-3xl font-black text-white">Acesso protegido por senha</h1>
            <p class="mt-3 text-sm leading-6 text-slate-400">
                Esta central unifica hotfolder e compositor. Ela sempre carrega os arquivos finais em uso antes de salvar.
            </p>

            <div class="mt-6">
                <label for="password_input" class="text-sm font-semibold text-slate-300">Senha</label>
                <input id="password_input" type="password" class="mt-2 w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 outline-none transition focus:border-emerald-400" />
            </div>

            <button id="login_button" class="mt-5 w-full rounded-2xl bg-white px-4 py-3 text-sm font-black uppercase tracking-[0.18em] text-slate-950 transition hover:bg-emerald-100">
                Entrar
            </button>

            <div id="login_error" class="mt-3 min-h-5 text-sm text-rose-300"></div>

            <a href="index.php" class="mt-6 inline-flex text-sm font-semibold text-slate-400 transition hover:text-white">
                Voltar para a central
            </a>
        </div>
    </div>

    <script>
        (() => {
            const passwordInput = document.getElementById("password_input");
            const loginButton = document.getElementById("login_button");
            const loginError = document.getElementById("login_error");

            async function submitLogin() {
                loginError.textContent = "";

                const password = passwordInput.value || "";
                if (!password) {
                    loginError.textContent = "Digite a senha.";
                    return;
                }

                try {
                    const response = await fetch("admin/unlock.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({ password })
                    });
                    const payload = await response.json();

                    if (!response.ok || !payload.ok) {
                        loginError.textContent = payload.error || "Senha inválida.";
                        return;
                    }

                    window.location.reload();
                } catch {
                    loginError.textContent = "Falha ao autenticar.";
                }
            }

            loginButton.addEventListener("click", submitLogin);
            passwordInput.addEventListener("keydown", (event) => {
                if (event.key === "Enter") {
                    event.preventDefault();
                    submitLogin();
                }
            });
        })();
    </script>
</body>
</html>
<?php
exit;
endif;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Unificado da Hotfolder</title>
    <script src="js/tailwind.js"></script>
    <style>
        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(18px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .glass_panel {
            background: rgba(255, 255, 255, 0.80);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .card_enter {
            animation: floatIn 0.45s ease-out forwards;
        }

        .section_shadow {
            box-shadow:
                0 10px 40px rgba(15, 23, 42, 0.08),
                0 2px 10px rgba(15, 23, 42, 0.05);
        }

        .gradient_background {
            background:
                radial-gradient(circle at top left, rgba(129, 140, 248, 0.18), transparent 26%),
                radial-gradient(circle at top right, rgba(236, 72, 153, 0.12), transparent 24%),
                radial-gradient(circle at bottom center, rgba(34, 197, 94, 0.12), transparent 24%),
                linear-gradient(180deg, #f8fafc 0%, #eef2ff 45%, #f8fafc 100%);
        }

        .input_focus_ring:focus {
            outline: none;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
            border-color: rgb(99, 102, 241);
        }

        .switch_track {
            transition: all 0.2s ease;
        }

        .switch_thumb {
            transition: transform 0.2s ease;
        }

        .toast_enter {
            animation: floatIn 0.25s ease-out forwards;
        }

        .field_label {
            display: block;
            margin-bottom: 0.45rem;
            font-size: 0.875rem;
            font-weight: 700;
            color: rgb(51 65 85);
        }

        .field_help {
            margin-top: 0.35rem;
            font-size: 0.75rem;
            line-height: 1.35;
            color: rgb(100 116 139);
        }

        .section_title {
            font-size: 1.5rem;
            font-weight: 900;
            color: rgb(15 23 42);
        }

        .section_subtitle {
            margin-top: 0.35rem;
            font-size: 0.875rem;
            line-height: 1.45;
            color: rgb(71 85 105);
        }

        .input_block {
            border-radius: 1.5rem;
            border: 1px solid rgb(226 232 240);
            background: white;
            padding: 1rem;
        }

        .compositor_box {
            position: absolute;
            border-width: 2px;
        }

        .compositor_label {
            position: absolute;
            left: 0;
            top: -1.8rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.86);
            padding: 0.2rem 0.55rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: white;
        }

        .compositor_handle {
            position: absolute;
            right: 0;
            bottom: 0;
            height: 1rem;
            width: 1rem;
            cursor: nwse-resize;
        }
    </style>
</head>
<body class="gradient_background min-h-screen text-slate-800">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        <header class="card_enter mb-8">
            <div class="glass_panel section_shadow rounded-3xl border border-white/60 p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-4 py-1.5 text-sm font-semibold text-indigo-700">
                                Estrutura e ativação
                            </span>
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-4 py-1.5 text-sm font-semibold text-emerald-700">
                                Configuração e pré-impressão
                            </span>
                        </div>

                        <h1 class="text-4xl font-black tracking-tight text-slate-900">
                            Dashboard unificado da hotfolder
                        </h1>

                        <p class="mt-3 max-w-4xl text-sm text-slate-600 lg:text-base">
                            Esta tela centraliza a preparação da estrutura em
                            <span class="font-semibold">C:\card_hotfolder</span>,
                            a cópia do Python correto por perfil de impressora, o gerenciamento das imagens padrão,
                            a edição direta do <span class="font-semibold">app_config.json</span>
                            e a pré-impressão em lote do cartão base.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="index.php" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                            Voltar
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="space-y-6">
            <section class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
                    <div>
                        <h2 class="section_title">Ações rápidas</h2>
                        <p class="section_subtitle">
                            Use estes botões para preparar a hotfolder, sincronizar o Python do perfil selecionado e salvar as alterações do painel.
                        </p>
                    </div>

                    <div class="input_block">
                        <label for="printer_profile" class="field_label">Perfil da impressora</label>
                        <select id="printer_profile" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <?php foreach ($printer_profiles as $profile_key => $profile_data): ?>
                                <option value="<?= htmlspecialchars($profile_key, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($profile_data['label'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field_help">
                            Esse perfil define qual Python será copiado para a hotfolder e qual estrutura base do JSON será usada.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button id="save_button" class="rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-500">
                        Salvar configurações
                    </button>
                    <button id="reload_button" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Recarregar tudo
                    </button>
                    <button id="prepare_hotfolder_button" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-500">
                        Criar estrutura + copiar Python + criar config
                    </button>
                    <button id="copy_python_button" class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800">
                        Copiar somente o Python
                    </button>
                </div>
            </section>

            <section class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                <div class="mb-6">
                    <h2 class="section_title">Pré-impressão do cartão base</h2>
                    <p class="section_subtitle">
                        Aqui você prepara o lote de cartões padrão para adiantar a operação. Exemplo:
                        imprimir <span class="font-semibold">300 cartões base</span> com frente + verso padrão,
                        e depois no evento imprimir somente os dados variáveis por cima.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="input_block">
                        <label for="template_batch_mode" class="field_label">Modo da pré-impressão</label>
                        <select id="template_batch_mode" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <option value="front">Somente frente</option>
                            <option value="front_back">Frente + verso padrão</option>
                        </select>
                        <p class="field_help">
                            Use <span class="font-semibold">Frente + verso padrão</span> quando quiser deixar os cartões base prontos com as duas faces.
                        </p>
                    </div>

                    <div class="input_block">
                        <label for="template_batch_copies" class="field_label">Quantidade para imprimir agora</label>
                        <input id="template_batch_copies" type="number" min="1" value="1" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                        <p class="field_help">
                            Esse valor será aplicado em <span class="font-semibold">template_print.copies</span> antes de disparar a impressão do lote.
                        </p>
                    </div>

                    <div class="input_block">
                        <div class="field_label">Resumo do lote</div>
                        <div id="template_batch_summary" class="rounded-2xl bg-indigo-50 p-4 text-sm text-indigo-800">
                            Lote padrão ainda não definido.
                        </div>
                        <p class="field_help">
                            O botão abaixo salva o JSON e depois tenta rodar o Python em modo template.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button id="apply_template_batch_to_form_button" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Aplicar lote no formulário
                    </button>
                    <button id="save_template_batch_button" class="rounded-2xl bg-amber-500 px-5 py-3 text-sm font-bold text-white transition hover:bg-amber-400">
                        Salvar lote padrão no JSON
                    </button>
                    <button id="print_template_batch_button" class="rounded-2xl bg-fuchsia-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-fuchsia-500">
                        Salvar e imprimir lote padrão agora
                    </button>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                    <h2 class="section_title">Status da estrutura</h2>
                    <p class="section_subtitle">
                        Verifique se as pastas principais da hotfolder já existem no destino correto.
                    </p>
                    <div id="hotfolder_paths_status" class="mt-5 space-y-3"></div>
                </div>

                <div class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                    <h2 class="section_title">Arquivos principais</h2>
                    <p class="section_subtitle">
                        Aqui você confirma se o Python da hotfolder, o config JSON e o arquivo do projeto estão onde deveriam.
                    </p>
                    <div id="hotfolder_files_status" class="mt-5 space-y-3"></div>
                </div>
            </section>

            <section class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                <div class="mb-6">
                    <h2 class="section_title">Imagens padrão</h2>
                    <p class="section_subtitle">
                        Envie, substitua ou apague os arquivos padrão usados na pré-impressão e no verso estático.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5">
                        <div class="mb-4">
                            <div class="text-lg font-black text-slate-900">Template frente</div>
                            <div class="text-sm text-slate-500">template_front.png</div>
                            <p class="field_help">
                                Arte fixa da frente do cartão base.
                            </p>
                        </div>
                        <div id="asset_template_front_status" class="mb-4 text-sm text-slate-600"></div>
                        <label for="asset_template_front_input" class="field_label">Selecionar nova imagem</label>
                        <input id="asset_template_front_input" type="file" accept=".png,.jpg,.jpeg,.bmp,.tif,.tiff" class="mb-4 block w-full text-sm">
                        <div class="flex gap-3">
                            <button data-asset-key="template_front" class="upload_asset_button rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white">
                                Enviar ou substituir
                            </button>
                            <button data-asset-key="template_front" class="delete_asset_button rounded-2xl bg-rose-600 px-4 py-2 text-sm font-bold text-white">
                                Apagar
                            </button>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5">
                        <div class="mb-4">
                            <div class="text-lg font-black text-slate-900">Template verso</div>
                            <div class="text-sm text-slate-500">template_back.png</div>
                            <p class="field_help">
                                Arte fixa do verso quando o lote padrão for frente + verso.
                            </p>
                        </div>
                        <div id="asset_template_back_status" class="mb-4 text-sm text-slate-600"></div>
                        <label for="asset_template_back_input" class="field_label">Selecionar nova imagem</label>
                        <input id="asset_template_back_input" type="file" accept=".png,.jpg,.jpeg,.bmp,.tif,.tiff" class="mb-4 block w-full text-sm">
                        <div class="flex gap-3">
                            <button data-asset-key="template_back" class="upload_asset_button rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white">
                                Enviar ou substituir
                            </button>
                            <button data-asset-key="template_back" class="delete_asset_button rounded-2xl bg-rose-600 px-4 py-2 text-sm font-bold text-white">
                                Apagar
                            </button>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5">
                        <div class="mb-4">
                            <div class="text-lg font-black text-slate-900">Verso estático</div>
                            <div class="text-sm text-slate-500">static_back.png</div>
                            <p class="field_help">
                                Use este arquivo quando quiser forçar um verso fixo independente do job recebido.
                            </p>
                        </div>
                        <div id="asset_static_back_status" class="mb-4 text-sm text-slate-600"></div>
                        <label for="asset_static_back_input" class="field_label">Selecionar nova imagem</label>
                        <input id="asset_static_back_input" type="file" accept=".png,.jpg,.jpeg,.bmp,.tif,.tiff" class="mb-4 block w-full text-sm">
                        <div class="flex gap-3">
                            <button data-asset-key="static_back" class="upload_asset_button rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white">
                                Enviar ou substituir
                            </button>
                            <button data-asset-key="static_back" class="delete_asset_button rounded-2xl bg-rose-600 px-4 py-2 text-sm font-bold text-white">
                                Apagar
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                <div class="space-y-6 xl:col-span-7">
                    <div class="glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                        <div class="mb-5">
                            <h2 class="section_title">Padrões da impressora</h2>
                            <p class="section_subtitle">
                                Estes valores viram o padrão do sistema quando o job não sobrescreve nada no manifest.
                            </p>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="printer_name" class="field_label">Nome da impressora</label>
                                <input id="printer_name" type="text" placeholder="Ex.: Evolis Dualys" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <p class="field_help">Nome padrão da impressora usada pelo sistema.</p>
                            </div>

                            <div>
                                <label for="copies" class="field_label">Cópias padrão</label>
                                <input id="copies" type="number" min="1" placeholder="1" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <p class="field_help">Quantidade padrão para jobs comuns.</p>
                            </div>

                            <div>
                                <label for="duplex" class="field_label">Duplex padrão</label>
                                <select id="duplex" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <option value="auto">auto</option>
                                    <option value="true">true</option>
                                    <option value="false">false</option>
                                </select>
                                <p class="field_help">Modo padrão de impressão frente e verso para jobs normais.</p>
                            </div>

                            <div>
                                <label for="fit_mode" class="field_label">Modo de encaixe da arte</label>
                                <select id="fit_mode" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <option value="fill">fill</option>
                                    <option value="contain">contain</option>
                                    <option value="stretch">stretch</option>
                                </select>
                                <p class="field_help">Define como a imagem ocupa a área útil do cartão.</p>
                            </div>

                            <div>
                                <label for="rotate_degrees" class="field_label">Rotação padrão</label>
                                <select id="rotate_degrees" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <option value="0">0</option>
                                    <option value="90">90</option>
                                    <option value="180">180</option>
                                    <option value="270">270</option>
                                </select>
                                <p class="field_help">Rotação aplicada antes de imprimir, quando necessário.</p>
                            </div>

                            <div>
                                <label for="form_name" class="field_label">Form name</label>
                                <input id="form_name" type="text" placeholder="CR80" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <p class="field_help">Nome do formulário da mídia cadastrado no driver.</p>
                            </div>

                            <div>
                                <label for="print_dpi" class="field_label">Resolução de impressão</label>
                                <input id="print_dpi" type="number" min="1" placeholder="300" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <p class="field_help">DPI usado para rasterizar e enviar o job.</p>
                            </div>

                            <div>
                                <label for="card_width_mm" class="field_label">Largura do cartão em mm</label>
                                <input id="card_width_mm" type="number" min="0.01" step="0.01" placeholder="85.6" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            </div>

                            <div>
                                <label for="card_height_mm" class="field_label">Altura do cartão em mm</label>
                                <input id="card_height_mm" type="number" min="0.01" step="0.01" placeholder="53.98" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            </div>

                            <div>
                                <label class="field_label">Cor de fundo padrão</label>
                                <div class="grid grid-cols-3 gap-3">
                                    <input id="background_red" type="number" min="0" max="255" placeholder="R" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <input id="background_green" type="number" min="0" max="255" placeholder="G" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <input id="background_blue" type="number" min="0" max="255" placeholder="B" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>
                                <p class="field_help">Usado principalmente no modo contain para preencher sobras.</p>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800">Auto rotate</div>
                                        <p class="field_help">O sistema escolhe a rotação que melhor ocupa a área do cartão.</p>
                                    </div>
                                    <button type="button" id="auto_rotate_toggle" class="switch_track relative h-8 w-16 rounded-full bg-slate-300">
                                        <span id="auto_rotate_thumb" class="switch_thumb absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow-md"></span>
                                    </button>
                                </div>
                                <input id="auto_rotate" type="hidden" value="true">
                            </div>
                        </div>
                    </div>

                    <div class="glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                        <div class="mb-5">
                            <h2 class="section_title">Verso estático</h2>
                            <p class="section_subtitle">
                                Use este bloco quando quiser que o sistema sempre use um verso fixo, independentemente do job variável.
                            </p>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="static_back_image_path" class="field_label">Caminho da imagem do verso estático</label>
                                <input id="static_back_image_path" type="text" placeholder="C:\card_hotfolder\backgrounds\static_back.png" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            </div>

                            <div>
                                <label for="static_back_fit_mode" class="field_label">Modo de encaixe do verso estático</label>
                                <select id="static_back_fit_mode" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <option value="fill">fill</option>
                                    <option value="contain">contain</option>
                                    <option value="stretch">stretch</option>
                                </select>
                            </div>

                            <div>
                                <label for="static_back_rotate_degrees" class="field_label">Rotação do verso estático</label>
                                <select id="static_back_rotate_degrees" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <option value="0">0</option>
                                    <option value="90">90</option>
                                    <option value="180">180</option>
                                    <option value="270">270</option>
                                </select>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800">Ativar verso estático</div>
                                        <p class="field_help">Quando ativo, ele substitui o verso variável do job.</p>
                                    </div>
                                    <button type="button" id="static_back_enabled_toggle" class="switch_track relative h-8 w-16 rounded-full bg-slate-300">
                                        <span id="static_back_enabled_thumb" class="switch_thumb absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow-md"></span>
                                    </button>
                                </div>
                                <input id="static_back_enabled" type="hidden" value="false">
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800">Auto rotate do verso estático</div>
                                        <p class="field_help">Deixe desligado se sua impressora for sensível à orientação.</p>
                                    </div>
                                    <button type="button" id="static_back_auto_rotate_toggle" class="switch_track relative h-8 w-16 rounded-full bg-slate-300">
                                        <span id="static_back_auto_rotate_thumb" class="switch_thumb absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow-md"></span>
                                    </button>
                                </div>
                                <input id="static_back_auto_rotate" type="hidden" value="false">
                            </div>
                        </div>
                    </div>

                    <div class="glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                        <div class="mb-5">
                            <h2 class="section_title">Template print</h2>
                            <p class="section_subtitle">
                                Este bloco define o cartão base padrão usado na pré-impressão. O lote em cima da página atual escreve aqui automaticamente.
                            </p>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="template_front_image_path" class="field_label">Caminho da imagem padrão da frente</label>
                                <input id="template_front_image_path" type="text" placeholder="C:\card_hotfolder\backgrounds\template_front.png" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            </div>

                            <div class="md:col-span-2">
                                <label for="template_back_image_path" class="field_label">Caminho da imagem padrão do verso</label>
                                <input id="template_back_image_path" type="text" placeholder="C:\card_hotfolder\backgrounds\template_back.png" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            </div>

                            <div>
                                <label for="template_mode" class="field_label">Modo do template</label>
                                <select id="template_mode" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <option value="front">front</option>
                                    <option value="front_back">front_back</option>
                                </select>
                                <p class="field_help">Define se o cartão base será só frente ou frente + verso.</p>
                            </div>

                            <div>
                                <label for="template_copies" class="field_label">Quantidade do template</label>
                                <input id="template_copies" type="number" min="1" placeholder="1" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <p class="field_help">Quantidade usada quando o Python imprime em modo template.</p>
                            </div>

                            <div>
                                <label for="template_fit_mode" class="field_label">Modo de encaixe do template</label>
                                <select id="template_fit_mode" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <option value="fill">fill</option>
                                    <option value="contain">contain</option>
                                    <option value="stretch">stretch</option>
                                </select>
                            </div>

                            <div>
                                <label for="template_rotate_degrees" class="field_label">Rotação do template</label>
                                <select id="template_rotate_degrees" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <option value="0">0</option>
                                    <option value="90">90</option>
                                    <option value="180">180</option>
                                    <option value="270">270</option>
                                </select>
                            </div>

                            <div>
                                <label for="template_duplex" class="field_label">Duplex do template</label>
                                <select id="template_duplex" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <option value="true">true</option>
                                    <option value="false">false</option>
                                    <option value="auto">auto</option>
                                </select>
                                <p class="field_help">Para frente + verso padrão, o esperado aqui normalmente é <span class="font-semibold">true</span>.</p>
                            </div>

                            <div>
                                <label for="template_form_name" class="field_label">Form name do template</label>
                                <input id="template_form_name" type="text" placeholder="CR80" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            </div>

                            <div>
                                <label for="template_print_dpi" class="field_label">DPI do template</label>
                                <input id="template_print_dpi" type="number" min="1" placeholder="300" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            </div>

                            <div>
                                <label for="template_card_width_mm" class="field_label">Largura do template em mm</label>
                                <input id="template_card_width_mm" type="number" min="0.01" step="0.01" placeholder="85.6" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            </div>

                            <div>
                                <label for="template_card_height_mm" class="field_label">Altura do template em mm</label>
                                <input id="template_card_height_mm" type="number" min="0.01" step="0.01" placeholder="53.98" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            </div>

                            <div>
                                <label class="field_label">Cor de fundo do template</label>
                                <div class="grid grid-cols-3 gap-3">
                                    <input id="template_background_red" type="number" min="0" max="255" placeholder="R" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <input id="template_background_green" type="number" min="0" max="255" placeholder="G" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    <input id="template_background_blue" type="number" min="0" max="255" placeholder="B" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800">Auto rotate do template</div>
                                        <p class="field_help">Aplica rotação automática na arte base antes de imprimir.</p>
                                    </div>
                                    <button type="button" id="template_auto_rotate_toggle" class="switch_track relative h-8 w-16 rounded-full bg-slate-300">
                                        <span id="template_auto_rotate_thumb" class="switch_thumb absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow-md"></span>
                                    </button>
                                </div>
                                <input id="template_auto_rotate" type="hidden" value="true">
                            </div>
                        </div>
                    </div>

                    <div class="glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                        <div class="mb-5">
                            <h2 class="section_title">Detecção de jobs</h2>
                            <p class="section_subtitle">
                                Ligue ou desligue os modos pelos quais a hotfolder reconhece arquivos novos.
                            </p>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="mb-3 text-sm font-bold text-slate-800">Folder manifest</div>
                                <p class="field_help mb-3">Processa pastas que possuem um manifest.json.</p>
                                <button type="button" id="enable_folder_manifest_jobs_toggle" class="switch_track relative h-8 w-16 rounded-full bg-slate-300">
                                    <span id="enable_folder_manifest_jobs_thumb" class="switch_thumb absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow-md"></span>
                                </button>
                                <input id="enable_folder_manifest_jobs" type="hidden" value="true">
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="mb-3 text-sm font-bold text-slate-800">Named front/back</div>
                                <p class="field_help mb-3">Processa pares com sufixos _front e _back.</p>
                                <button type="button" id="enable_named_front_back_jobs_toggle" class="switch_track relative h-8 w-16 rounded-full bg-slate-300">
                                    <span id="enable_named_front_back_jobs_thumb" class="switch_thumb absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow-md"></span>
                                </button>
                                <input id="enable_named_front_back_jobs" type="hidden" value="true">
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="mb-3 text-sm font-bold text-slate-800">Single file jobs</div>
                                <p class="field_help mb-3">Processa imagem ou PDF isolado dentro da entrada.</p>
                                <button type="button" id="enable_single_file_jobs_toggle" class="switch_track relative h-8 w-16 rounded-full bg-slate-300">
                                    <span id="enable_single_file_jobs_thumb" class="switch_thumb absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow-md"></span>
                                </button>
                                <input id="enable_single_file_jobs" type="hidden" value="true">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-5">
                    <div class="glass_panel section_shadow rounded-3xl border border-white/60 p-6 sticky top-6">
                        <h2 class="section_title">Prévia do JSON</h2>
                        <p class="section_subtitle">
                            Este bloco mostra exatamente o que será salvo no <span class="font-semibold">app_config.json</span>.
                        </p>
                        <pre id="json_preview" class="mt-5 max-h-[750px] overflow-auto rounded-3xl bg-slate-950 p-5 text-xs leading-6 text-emerald-300"></pre>
                    </div>
                </div>
            </section>

            <section class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="section_title">Compositor e kiosk</h2>
                        <p class="section_subtitle">
                            Esta ediÃ§Ã£o usa o arquivo real <span class="font-semibold">storage/compositor_config.json</span> como base de salvamento.
                            Se algum valor sÃ³ existir no arquivo final, ele Ã© preservado e nÃ£o some ao clicar em salvar.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        Fonte da verdade: storage/compositor_config.json
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,560px)_minmax(0,1fr)]">
                    <div class="space-y-6">
                        <div class="rounded-3xl border border-slate-200 bg-white p-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="text-lg font-black text-slate-900">Layout visual por lado</div>
                                    <p class="field_help">
                                        Arraste e redimensione as caixas no frame. A ediÃ§Ã£o da frente atualiza tambÃ©m a base legada do arquivo para manter compatibilidade.
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button id="compositor_side_front_button" type="button" class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">
                                        Frente
                                    </button>
                                    <button id="compositor_side_back_button" type="button" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">
                                        Verso
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 rounded-3xl border border-slate-200 bg-slate-950 p-3">
                                <div id="compositor_stage" class="relative mx-auto overflow-hidden rounded-[28px]" style="max-width: 520px;">
                                    <img id="compositor_frame_image" src="frames/frame_01_front.png" alt="Frame do compositor" class="block h-auto w-full" />

                                    <div id="compositor_box_photo" class="compositor_box border-emerald-400/90 bg-emerald-400/10">
                                        <div class="compositor_label">Foto</div>
                                        <div class="compositor_handle bg-emerald-400"></div>
                                    </div>

                                    <div id="compositor_box_person_name" class="compositor_box border-sky-400/90 bg-sky-400/10">
                                        <div class="compositor_label">Nome</div>
                                        <div class="compositor_handle bg-sky-400"></div>
                                    </div>

                                    <div id="compositor_box_artist_name" class="compositor_box border-fuchsia-400/90 bg-fuchsia-400/10">
                                        <div class="compositor_label">Artista</div>
                                        <div class="compositor_handle bg-fuchsia-400"></div>
                                    </div>

                                    <div id="compositor_box_track_name" class="compositor_box border-amber-400/90 bg-amber-400/10">
                                        <div class="compositor_label">MÃºsica</div>
                                        <div class="compositor_handle bg-amber-400"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-slate-200 bg-white p-5">
                            <div class="text-lg font-black text-slate-900">Campos principais</div>
                            <p class="field_help">
                                Estes campos sÃ£o preenchidos a partir do layout efetivo em uso. O JSON abaixo continua disponÃ­vel para ediÃ§Ã£o avanÃ§ada.
                            </p>

                            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label for="compositor_font_file_path" class="field_label">Fonte TTF</label>
                                    <input id="compositor_font_file_path" type="text" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>

                                <div>
                                    <label for="compositor_oversize_factor" class="field_label">Oversize da foto</label>
                                    <input id="compositor_oversize_factor" type="number" step="0.01" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>

                                <div>
                                    <label for="compositor_print_mode" class="field_label">Modo de impressÃ£o</label>
                                    <select id="compositor_print_mode" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                        <option value="front_only">front_only</option>
                                        <option value="front_and_back">front_and_back</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="field_label">Cor do texto</label>
                                    <div class="grid grid-cols-3 gap-3">
                                        <input id="compositor_text_color_r" type="number" min="0" max="255" placeholder="R" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                        <input id="compositor_text_color_g" type="number" min="0" max="255" placeholder="G" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                        <input id="compositor_text_color_b" type="number" min="0" max="255" placeholder="B" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <input id="compositor_back_first_print_enabled" type="checkbox" class="mt-1 h-5 w-5 rounded border-slate-300">
                                        <span>
                                            <span class="block text-sm font-bold text-slate-900">Imprimir verso primeiro</span>
                                            <span class="mt-1 block text-xs text-slate-500">Controla a confirmaÃ§Ã£o dupla antes da frente na ativaÃ§Ã£o.</span>
                                        </span>
                                    </label>

                                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <input id="compositor_compose_back_with_dynamic_content" type="checkbox" class="mt-1 h-5 w-5 rounded border-slate-300">
                                        <span>
                                            <span class="block text-sm font-bold text-slate-900">Compor verso dinÃ¢mico</span>
                                            <span class="mt-1 block text-xs text-slate-500">Quando ligado, o verso tambÃ©m usa foto e textos dinÃ¢micos.</span>
                                        </span>
                                    </label>
                                </div>

                                <div>
                                    <label for="compositor_max_person" class="field_label">MÃ¡x chars - nome</label>
                                    <input id="compositor_max_person" type="number" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>

                                <div>
                                    <label for="compositor_size_person" class="field_label">Fonte - nome</label>
                                    <input id="compositor_size_person" type="number" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>

                                <div>
                                    <label for="compositor_max_artist" class="field_label">MÃ¡x chars - artista</label>
                                    <input id="compositor_max_artist" type="number" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>

                                <div>
                                    <label for="compositor_size_artist" class="field_label">Fonte - artista</label>
                                    <input id="compositor_size_artist" type="number" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>

                                <div>
                                    <label for="compositor_max_track" class="field_label">MÃ¡x chars - mÃºsica</label>
                                    <input id="compositor_max_track" type="number" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>

                                <div>
                                    <label for="compositor_size_track" class="field_label">Fonte - mÃºsica</label>
                                    <input id="compositor_size_track" type="number" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-slate-200 bg-white p-5">
                            <div class="text-lg font-black text-slate-900">Preview do compositor</div>
                            <p class="field_help">
                                O preview usa o endpoint real de composiÃ§Ã£o em modo <span class="font-semibold">preview_only</span> sem avanÃ§ar frame nem gerar job.
                            </p>

                            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="compositor_preview_person" class="field_label">Nome</label>
                                    <input id="compositor_preview_person" type="text" value="Seu Nome" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>

                                <div>
                                    <label for="compositor_preview_artist" class="field_label">Artista</label>
                                    <input id="compositor_preview_artist" type="text" value="Artista" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>

                                <div>
                                    <label for="compositor_preview_track" class="field_label">MÃºsica</label>
                                    <input id="compositor_preview_track" type="text" value="MÃºsica" class="input_focus_ring w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                </div>

                                <div>
                                    <label for="compositor_preview_photo" class="field_label">Foto</label>
                                    <input id="compositor_preview_photo" type="file" accept="image/*" class="block w-full text-sm text-slate-700">
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <button id="compositor_preview_button" type="button" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-500">
                                    Gerar preview
                                </button>
                            </div>

                            <div id="compositor_preview_error" class="mt-3 min-h-5 text-sm text-rose-500"></div>
                            <img id="compositor_preview_image" class="mt-4 hidden w-full rounded-3xl border border-slate-200 bg-slate-100" alt="Preview do compositor">
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="rounded-3xl border border-slate-200 bg-white p-5">
                            <div class="flex flex-wrap gap-3">
                                <button id="compositor_save_button" type="button" class="rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-500">
                                    Salvar compositor
                                </button>
                                <button id="compositor_reload_button" type="button" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                                    Recarregar compositor
                                </button>
                            </div>

                            <div id="compositor_save_status" class="mt-3 min-h-5 text-sm text-slate-500"></div>
                        </div>

                        <div class="rounded-3xl border border-slate-200 bg-white p-5">
                            <div class="text-lg font-black text-slate-900">JSON do override</div>
                            <p class="field_help">
                                Este campo mostra o conteúdo que serÃ¡ gravado no override. Campos ausentes no arquivo final continuam ausentes atÃ© vocÃª realmente alterÃ¡-los.
                            </p>

                            <textarea id="compositor_config_json" rows="30" class="mt-4 w-full rounded-3xl border border-slate-200 bg-slate-950 px-4 py-4 font-mono text-xs leading-6 text-emerald-300 outline-none"></textarea>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div id="toast_container" class="fixed bottom-5 right-5 z-50 flex w-full max-w-sm flex-col gap-3"></div>

    <script>
        const compositorBaseConfig = <?= json_encode($compositor_base_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        const initialCompositorSource = <?= json_encode($compositor_override_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        const initialCompositorEffective = <?= json_encode($compositor_effective_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        const compositorPreviewCsrfToken = <?= json_encode($csrf_token, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

        function getElement(element_id) {
            return document.getElementById(element_id);
        }

        function getSelectedPrinterProfile() {
            return getElement("printer_profile").value || "evolis_dualys_series";
        }

        function deepClone(value) {
            if (Array.isArray(value)) {
                return value.map((item) => deepClone(item));
            }

            if (value && typeof value === "object") {
                const cloned_object = {};
                for (const [key_name, key_value] of Object.entries(value)) {
                    cloned_object[key_name] = deepClone(key_value);
                }
                return cloned_object;
            }

            return value;
        }

        let initialHotfolderRawConfig = {};
        let currentHotfolderRawConfig = {};
        let initialCompositorSourceState = deepClone(initialCompositorSource);
        let currentCompositorSource = deepClone(initialCompositorSourceState);
        let currentCompositorSide = "front";

        function mergeDeep(base_object, override_object) {
            const merged_object = deepClone(base_object);

            for (const [key_name, value] of Object.entries(override_object)) {
                if (
                    value &&
                    typeof value === "object" &&
                    !Array.isArray(value) &&
                    merged_object[key_name] &&
                    typeof merged_object[key_name] === "object" &&
                    !Array.isArray(merged_object[key_name])
                ) {
                    merged_object[key_name] = mergeDeep(merged_object[key_name], value);
                } else {
                    merged_object[key_name] = deepClone(value);
                }
            }

            return merged_object;
        }

        function showToast(message_text, toast_type = "success") {
            const toast_container = getElement("toast_container");
            const toast_element = document.createElement("div");

            let color_classes = "bg-emerald-500 text-white";

            if (toast_type === "error") {
                color_classes = "bg-rose-500 text-white";
            } else if (toast_type === "warning") {
                color_classes = "bg-amber-500 text-white";
            } else if (toast_type === "info") {
                color_classes = "bg-indigo-500 text-white";
            }

            toast_element.className = `toast_enter rounded-2xl px-5 py-4 shadow-2xl ${color_classes}`;
            toast_element.innerHTML = `<div class="text-sm font-bold">${message_text}</div>`;
            toast_container.appendChild(toast_element);

            setTimeout(() => {
                toast_element.style.transition = "all 0.25s ease";
                toast_element.style.opacity = "0";
                toast_element.style.transform = "translateY(8px)";
                setTimeout(() => toast_element.remove(), 260);
            }, 3200);
        }

        function formatExistsBadge(exists_value) {
            if (exists_value) {
                return '<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">Existe</span>';
            }

            return '<span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-700">Não existe</span>';
        }

        function setToggle(toggle_id, hidden_input_id, is_enabled) {
            const toggle_element = getElement(toggle_id);
            const thumb_element = getElement(toggle_id.replace("_toggle", "_thumb"));
            const hidden_input_element = getElement(hidden_input_id);

            hidden_input_element.value = is_enabled ? "true" : "false";

            if (is_enabled) {
                toggle_element.classList.remove("bg-slate-300");
                toggle_element.classList.add("bg-indigo-500");
                thumb_element.style.transform = "translateX(32px)";
                return;
            }

            toggle_element.classList.remove("bg-indigo-500");
            toggle_element.classList.add("bg-slate-300");
            thumb_element.style.transform = "translateX(0)";
        }

        function bindToggle(toggle_id, hidden_input_id) {
            const toggle_element = getElement(toggle_id);

            toggle_element.addEventListener("click", async () => {
                const hidden_input_element = getElement(hidden_input_id);
                setToggle(toggle_id, hidden_input_id, hidden_input_element.value !== "true");
                currentHotfolderRawConfig = deepClone(payload);
                refreshJsonPreview();
                await loadDashboard(false);
            });
        }

        function getBooleanFromInput(input_id) {
            return getElement(input_id).value === "true";
        }

        function getNumberValue(input_id, fallback_value = 0) {
            const raw_value = getElement(input_id).value.trim();

            if (raw_value === "") {
                return fallback_value;
            }

            const parsed_value = Number(raw_value);

            if (Number.isNaN(parsed_value)) {
                return fallback_value;
            }

            return parsed_value;
        }

        function clampRgbValue(value) {
            const numeric_value = Number(value);

            if (Number.isNaN(numeric_value)) {
                return 0;
            }

            return Math.max(0, Math.min(255, Math.round(numeric_value)));
        }

        function renderAssetStatusCard(asset_key, asset_payload) {
            const target_element = getElement(`asset_${asset_key}_status`);

            if (!target_element) {
                return;
            }

            if (!asset_payload || !asset_payload.exists) {
                target_element.innerHTML = `
                    <div class="rounded-2xl bg-rose-50 p-3 text-rose-700">
                        Arquivo ausente.
                    </div>
                `;
                return;
            }

            target_element.innerHTML = `
                <div class="rounded-2xl bg-emerald-50 p-3 text-emerald-700">
                    <div class="font-bold">Arquivo disponível</div>
                    <div class="mt-1 text-xs break-all">${asset_payload.absolute_file_path ?? "-"}</div>
                    <div class="mt-1 text-xs">Tamanho: ${asset_payload.size_bytes ?? 0} bytes</div>
                    <div class="mt-1 text-xs">Modificado em: ${asset_payload.modified_at ?? "-"}</div>
                </div>
            `;
        }

        function renderStatus(status_payload) {
            const paths_status_container = getElement("hotfolder_paths_status");
            const files_status_container = getElement("hotfolder_files_status");

            if (!status_payload || !status_payload.paths || !status_payload.exists) {
                paths_status_container.innerHTML = `
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                        O endpoint não retornou o bloco de status.
                    </div>
                `;
                files_status_container.innerHTML = `
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                        O endpoint não retornou o bloco de status.
                    </div>
                `;
                return;
            }

            const paths_entries = [
                ["Hotfolder", "hotfolder_root_path"],
                ["Entrada", "hotfolder_in_path"],
                ["Concluído", "hotfolder_done_path"],
                ["Erro", "hotfolder_error_path"],
                ["Logs", "hotfolder_logs_path"],
                ["Config", "hotfolder_config_path"],
                ["Backgrounds", "hotfolder_backgrounds_path"]
            ];

            const files_entries = [
                ["app_config.json", "hotfolder_config_file_path"],
                ["card_hotfolder_printer.py", "hotfolder_printer_file_path"],
                ["Python do projeto (perfil ativo)", "project_python_source_file_path"]
            ];

            paths_status_container.innerHTML = paths_entries.map(([label_text, key_name]) => `
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div class="text-sm font-bold text-slate-900">${label_text}</div>
                        ${formatExistsBadge(Boolean(status_payload.exists[key_name]))}
                    </div>
                    <div class="text-xs break-all text-slate-500">${status_payload.paths[key_name] ?? "-"}</div>
                </div>
            `).join("");

            files_status_container.innerHTML = files_entries.map(([label_text, key_name]) => `
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div class="text-sm font-bold text-slate-900">${label_text}</div>
                        ${formatExistsBadge(Boolean(status_payload.exists[key_name]))}
                    </div>
                    <div class="text-xs break-all text-slate-500">${status_payload.paths[key_name] ?? "-"}</div>
                </div>
            `).join("");

            renderAssetStatusCard("template_front", status_payload.files?.template_front ?? null);
            renderAssetStatusCard("template_back", status_payload.files?.template_back ?? null);
            renderAssetStatusCard("static_back", status_payload.files?.static_back ?? null);
        }

        function populateForm(config_payload, raw_config_payload = {}) {
            initialHotfolderRawConfig = deepClone(raw_config_payload ?? {});
            currentHotfolderRawConfig = deepClone(raw_config_payload ?? {});

            const printer_profile = config_payload.printer_profile ?? getSelectedPrinterProfile();
            getElement("printer_profile").value = printer_profile;

            const printer_defaults = config_payload.printer_defaults ?? {};
            const static_back = config_payload.static_back ?? {};
            const template_print = config_payload.template_print ?? {};
            const job_detection = config_payload.job_detection ?? {};

            getElement("printer_name").value = printer_defaults.printer_name ?? "";
            getElement("copies").value = printer_defaults.copies ?? 1;
            getElement("duplex").value = printer_defaults.duplex ?? "auto";
            getElement("fit_mode").value = printer_defaults.fit_mode ?? "fill";
            getElement("rotate_degrees").value = String(printer_defaults.rotate_degrees ?? 0);
            getElement("form_name").value = printer_defaults.form_name ?? "CR80";
            getElement("print_dpi").value = printer_defaults.print_dpi ?? 300;
            getElement("card_width_mm").value = printer_defaults.card_size_mm?.width_mm ?? 85.6;
            getElement("card_height_mm").value = printer_defaults.card_size_mm?.height_mm ?? 53.98;

            const printer_background_color_rgb = printer_defaults.background_color_rgb ?? [255, 255, 255];
            getElement("background_red").value = printer_background_color_rgb[0] ?? 255;
            getElement("background_green").value = printer_background_color_rgb[1] ?? 255;
            getElement("background_blue").value = printer_background_color_rgb[2] ?? 255;
            setToggle("auto_rotate_toggle", "auto_rotate", printer_defaults.auto_rotate ?? true);

            setToggle("static_back_enabled_toggle", "static_back_enabled", static_back.enabled ?? false);
            getElement("static_back_image_path").value = static_back.image_path ?? "";
            getElement("static_back_fit_mode").value = static_back.fit_mode ?? "fill";
            getElement("static_back_rotate_degrees").value = String(static_back.rotate_degrees ?? 0);
            setToggle("static_back_auto_rotate_toggle", "static_back_auto_rotate", static_back.auto_rotate ?? false);

            getElement("template_front_image_path").value = template_print.front_image_path ?? "";
            getElement("template_back_image_path").value = template_print.back_image_path ?? "";
            getElement("template_mode").value = template_print.mode ?? "front";
            getElement("template_copies").value = template_print.copies ?? 1;
            getElement("template_fit_mode").value = template_print.fit_mode ?? "fill";
            getElement("template_rotate_degrees").value = String(template_print.rotate_degrees ?? 0);
            getElement("template_duplex").value = template_print.duplex ?? "true";
            getElement("template_form_name").value = template_print.form_name ?? "CR80";
            getElement("template_print_dpi").value = template_print.print_dpi ?? 300;
            getElement("template_card_width_mm").value = template_print.card_size_mm?.width_mm ?? 85.6;
            getElement("template_card_height_mm").value = template_print.card_size_mm?.height_mm ?? 53.98;

            const template_background_color_rgb = template_print.background_color_rgb ?? [255, 255, 255];
            getElement("template_background_red").value = template_background_color_rgb[0] ?? 255;
            getElement("template_background_green").value = template_background_color_rgb[1] ?? 255;
            getElement("template_background_blue").value = template_background_color_rgb[2] ?? 255;
            setToggle("template_auto_rotate_toggle", "template_auto_rotate", template_print.auto_rotate ?? true);

            setToggle("enable_folder_manifest_jobs_toggle", "enable_folder_manifest_jobs", job_detection.enable_folder_manifest_jobs ?? true);
            setToggle("enable_named_front_back_jobs_toggle", "enable_named_front_back_jobs", job_detection.enable_named_front_back_jobs ?? true);
            setToggle("enable_single_file_jobs_toggle", "enable_single_file_jobs", job_detection.enable_single_file_jobs ?? true);

            getElement("template_batch_mode").value = template_print.mode ?? "front";
            getElement("template_batch_copies").value = template_print.copies ?? 1;

            refreshTemplateBatchSummary();
            refreshJsonPreview();
        }

        function buildConfigPayload() {
            return mergeDeep(currentHotfolderRawConfig, {
                printer_defaults: {
                    printer_name: getElement("printer_name").value ?? "",
                    copies: Math.max(1, getNumberValue("copies", 1)),
                    duplex: getElement("duplex").value ?? "auto",
                    fit_mode: getElement("fit_mode").value ?? "fill",
                    rotate_degrees: getNumberValue("rotate_degrees", 0),
                    card_size_mm: {
                        width_mm: getNumberValue("card_width_mm", 85.6),
                        height_mm: getNumberValue("card_height_mm", 53.98)
                    },
                    form_name: getElement("form_name").value ?? "CR80",
                    print_dpi: Math.max(1, getNumberValue("print_dpi", 300)),
                    auto_rotate: getBooleanFromInput("auto_rotate"),
                    background_color_rgb: [
                        clampRgbValue(getNumberValue("background_red", 255)),
                        clampRgbValue(getNumberValue("background_green", 255)),
                        clampRgbValue(getNumberValue("background_blue", 255))
                    ]
                },
                static_back: {
                    enabled: getBooleanFromInput("static_back_enabled"),
                    image_path: getElement("static_back_image_path").value ?? "",
                    fit_mode: getElement("static_back_fit_mode").value ?? "fill",
                    rotate_degrees: getNumberValue("static_back_rotate_degrees", 0),
                    auto_rotate: getBooleanFromInput("static_back_auto_rotate")
                },
                template_print: {
                    front_image_path: getElement("template_front_image_path").value ?? "",
                    back_image_path: getElement("template_back_image_path").value ?? "",
                    mode: getElement("template_mode").value ?? "front",
                    copies: Math.max(1, getNumberValue("template_copies", 1)),
                    fit_mode: getElement("template_fit_mode").value ?? "fill",
                    rotate_degrees: getNumberValue("template_rotate_degrees", 0),
                    auto_rotate: getBooleanFromInput("template_auto_rotate"),
                    background_color_rgb: [
                        clampRgbValue(getNumberValue("template_background_red", 255)),
                        clampRgbValue(getNumberValue("template_background_green", 255)),
                        clampRgbValue(getNumberValue("template_background_blue", 255))
                    ],
                    duplex: getElement("template_duplex").value ?? "true",
                    form_name: getElement("template_form_name").value ?? "CR80",
                    print_dpi: Math.max(1, getNumberValue("template_print_dpi", 300)),
                    card_size_mm: {
                        width_mm: getNumberValue("template_card_width_mm", 85.6),
                        height_mm: getNumberValue("template_card_height_mm", 53.98)
                    }
                },
                job_detection: {
                    enable_folder_manifest_jobs: getBooleanFromInput("enable_folder_manifest_jobs"),
                    enable_named_front_back_jobs: getBooleanFromInput("enable_named_front_back_jobs"),
                    enable_single_file_jobs: getBooleanFromInput("enable_single_file_jobs")
                }
            });
        }

        function refreshJsonPreview() {
            getElement("json_preview").textContent = JSON.stringify(buildConfigPayload(), null, 2);
        }

        function refreshTemplateBatchSummary() {
            const template_mode = getElement("template_batch_mode").value;
            const template_copies = Math.max(1, getNumberValue("template_batch_copies", 1));

            let template_label = "somente frente";
            if (template_mode === "front_back") {
                template_label = "frente + verso padrão";
            }

            getElement("template_batch_summary").innerHTML = `
                <div class="font-bold">Lote pronto para configuração</div>
                <div class="mt-1">Modo: <span class="font-semibold">${template_label}</span></div>
                <div class="mt-1">Quantidade: <span class="font-semibold">${template_copies}</span></div>
                <div class="mt-2 text-xs text-indigo-700">
                    Este lote será aplicado em template_print.mode e template_print.copies antes da impressão.
                </div>
            `;
        }

        function applyTemplateBatchToForm() {
            const template_mode = getElement("template_batch_mode").value;
            const template_copies = Math.max(1, getNumberValue("template_batch_copies", 1));

            getElement("template_mode").value = template_mode;
            getElement("template_copies").value = template_copies;

            if (template_mode === "front_back") {
                getElement("template_duplex").value = "true";
            } else {
                getElement("template_duplex").value = "false";
            }

            refreshTemplateBatchSummary();
            refreshJsonPreview();
            showToast("Lote padrão aplicado ao formulário.", "info");
        }

        function normalizeDashboardPayload(response_payload) {
            return {
                config_data: response_payload.data ?? null,
                raw_config_data: response_payload.raw_data ?? null,
                status_data: response_payload.status ?? null
            };
        }

        async function loadDashboard(show_success_toast = false) {
            try {
                const printer_profile = getSelectedPrinterProfile();
                const response = await fetch(`api/api_load_config.php?printer_profile=${encodeURIComponent(printer_profile)}`, {
                    method: "GET",
                    headers: {
                        "Accept": "application/json"
                    }
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao carregar dados do dashboard.");
                }

                const normalized_payload = normalizeDashboardPayload(response_payload);

                if (normalized_payload.config_data) {
                    populateForm(normalized_payload.config_data, normalized_payload.raw_config_data ?? {});
                } else {
                    refreshJsonPreview();
                }

                if (normalized_payload.status_data) {
                    renderStatus(normalized_payload.status_data);
                } else {
                    renderStatus(null);
                }

                if (show_success_toast) {
                    showToast("Dashboard recarregado com sucesso.", "success");
                }
            } catch (error) {
                showToast(error.message || "Erro ao carregar dados do dashboard.", "error");
            }
        }

        async function saveConfig(show_success_toast = true) {
            try {
                const payload = buildConfigPayload();
                const response = await fetch("api/api_save_config.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify(payload)
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao salvar configuração.");
                }

                refreshJsonPreview();

                if (show_success_toast) {
                    showToast("Configuração salva com sucesso.", "success");
                }

                return true;
            } catch (error) {
                showToast(error.message || "Erro ao salvar configuração.", "error");
                return false;
            }
        }

        async function prepareHotfolder(success_message = "Hotfolder preparada com sucesso.", copy_python_only = false) {
            try {
                const response = await fetch("api/api_hotfolder_setup.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        printer_profile: getSelectedPrinterProfile(),
                        copy_python_only
                    })
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao preparar a hotfolder.");
                }

                if (response_payload.status) {
                    renderStatus(response_payload.status);
                }

                showToast(success_message, "success");
                await loadDashboard(false);
            } catch (error) {
                showToast(error.message || "Erro ao preparar a hotfolder.", "error");
            }
        }

        async function copyOnlyPython() {
            await prepareHotfolder("Python da impressora copiado com sucesso.", true);
        }

        async function uploadAsset(asset_key) {
            const input_element = getElement(`asset_${asset_key}_input`);

            if (!input_element || !input_element.files || input_element.files.length === 0) {
                showToast("Selecione um arquivo antes de enviar.", "warning");
                return;
            }

            const form_data = new FormData();
            form_data.append("asset_key", asset_key);
            form_data.append("asset_file", input_element.files[0]);
            form_data.append("printer_profile", getSelectedPrinterProfile());

            try {
                const response = await fetch("api/api_asset_upload.php", {
                    method: "POST",
                    body: form_data
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao enviar imagem.");
                }

                if (response_payload.status) {
                    renderStatus(response_payload.status);
                }

                input_element.value = "";
                showToast("Imagem enviada com sucesso.", "success");
                await loadDashboard(false);
            } catch (error) {
                showToast(error.message || "Erro ao enviar imagem.", "error");
            }
        }

        async function deleteAsset(asset_key) {
            try {
                const response = await fetch("api/api_asset_delete.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        asset_key,
                        printer_profile: getSelectedPrinterProfile()
                    })
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao apagar imagem.");
                }

                if (response_payload.status) {
                    renderStatus(response_payload.status);
                }

                showToast("Imagem apagada com sucesso.", "success");
                await loadDashboard(false);
            } catch (error) {
                showToast(error.message || "Erro ao apagar imagem.", "error");
            }
        }

        async function saveTemplateBatchToConfig() {
            applyTemplateBatchToForm();
            await saveConfig(true);
        }

        async function printTemplateBatch() {
            applyTemplateBatchToForm();

            const did_save = await saveConfig(false);
            if (!did_save) {
                return;
            }

            try {
                const response = await fetch("?action=print_template_batch", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        printer_profile: getSelectedPrinterProfile(),
                        template_mode: getElement("template_mode").value,
                        template_copies: Math.max(1, getNumberValue("template_copies", 1))
                    })
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao disparar a impressão do lote padrão.");
                }

                showToast("Lote padrão enviado para impressão.", "success");
            } catch (error) {
                showToast(error.message || "Erro ao imprimir lote padrão.", "error");
            }
        }

        function getCompositorMergedConfig(source_object = currentCompositorSource) {
            return mergeDeep(compositorBaseConfig, source_object ?? {});
        }

        function getCompositorSideConfig(side_name = currentCompositorSide) {
            const merged_config = getCompositorMergedConfig();
            const side_layout = merged_config.side_layouts?.[side_name] ?? {};

            return {
                photo_box: side_layout.photo_box ?? merged_config.photo_box ?? {},
                text_fields: side_layout.text_fields ?? merged_config.text_fields ?? {}
            };
        }

        function getCompositorSideTextField(side_name, field_name) {
            return getCompositorSideConfig(side_name).text_fields?.[field_name] ?? {};
        }

        function ensureCompositorSideSource(side_name) {
            if (!currentCompositorSource.side_layouts || typeof currentCompositorSource.side_layouts !== "object") {
                currentCompositorSource.side_layouts = {};
            }

            if (!currentCompositorSource.side_layouts[side_name] || typeof currentCompositorSource.side_layouts[side_name] !== "object") {
                currentCompositorSource.side_layouts[side_name] = {};
            }

            return currentCompositorSource.side_layouts[side_name];
        }

        function setCompositorPhotoBoxForSide(side_name, next_box) {
            const side_source = ensureCompositorSideSource(side_name);
            side_source.photo_box = mergeDeep(side_source.photo_box ?? {}, next_box);

            if (side_name === "front") {
                currentCompositorSource.photo_box = mergeDeep(currentCompositorSource.photo_box ?? {}, next_box);
            }
        }

        function setCompositorTextFieldForSide(side_name, field_name, next_field) {
            const side_source = ensureCompositorSideSource(side_name);
            if (!side_source.text_fields || typeof side_source.text_fields !== "object") {
                side_source.text_fields = {};
            }

            side_source.text_fields[field_name] = mergeDeep(side_source.text_fields[field_name] ?? {}, next_field);

            if (side_name === "front") {
                if (!currentCompositorSource.text_fields || typeof currentCompositorSource.text_fields !== "object") {
                    currentCompositorSource.text_fields = {};
                }

                currentCompositorSource.text_fields[field_name] = mergeDeep(currentCompositorSource.text_fields[field_name] ?? {}, next_field);
            }
        }

        function getCompositorScale() {
            const frame_image = getElement("compositor_frame_image");
            const natural_width = frame_image.naturalWidth || 1;
            const rendered_width = frame_image.clientWidth || 1;
            return rendered_width / natural_width;
        }

        function setCompositorBoxStyle(box_element, box) {
            const scale = getCompositorScale();
            box_element.style.left = `${(Number(box.x) || 0) * scale}px`;
            box_element.style.top = `${(Number(box.y) || 0) * scale}px`;
            box_element.style.width = `${(Number(box.width) || 0) * scale}px`;
            box_element.style.height = `${(Number(box.height) || 0) * scale}px`;
        }

        function syncCompositorButtons() {
            const front_button = getElement("compositor_side_front_button");
            const back_button = getElement("compositor_side_back_button");
            const is_front = currentCompositorSide === "front";

            front_button.className = is_front
                ? "rounded-2xl bg-slate-900 px-4 py-2 text-sm font-bold text-white"
                : "rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700";

            back_button.className = !is_front
                ? "rounded-2xl bg-slate-900 px-4 py-2 text-sm font-bold text-white"
                : "rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700";

            getElement("compositor_frame_image").src = `frames/frame_01_${currentCompositorSide}.png`;
        }

        function syncCompositorBoxesToDom() {
            const side_config = getCompositorSideConfig();

            setCompositorBoxStyle(getElement("compositor_box_photo"), side_config.photo_box ?? {});
            setCompositorBoxStyle(getElement("compositor_box_person_name"), side_config.text_fields?.person_name?.box ?? {});
            setCompositorBoxStyle(getElement("compositor_box_artist_name"), side_config.text_fields?.artist_name?.box ?? {});
            setCompositorBoxStyle(getElement("compositor_box_track_name"), side_config.text_fields?.track_name?.box ?? {});
        }

        function syncCompositorJsonTextarea() {
            getElement("compositor_config_json").value = JSON.stringify(currentCompositorSource, null, 2);
        }

        function syncCompositorFormFields() {
            const merged_config = getCompositorMergedConfig();
            const side_config = getCompositorSideConfig();
            const text_color = merged_config.text_color_rgb ?? {};

            getElement("compositor_font_file_path").value = merged_config.font_file_path ?? "";
            getElement("compositor_oversize_factor").value = String(side_config.photo_box?.oversize_factor ?? 1);
            getElement("compositor_print_mode").value = merged_config.print_mode ?? "front_and_back";
            getElement("compositor_text_color_r").value = String(text_color.r ?? 20);
            getElement("compositor_text_color_g").value = String(text_color.g ?? 20);
            getElement("compositor_text_color_b").value = String(text_color.b ?? 20);
            getElement("compositor_back_first_print_enabled").checked = Boolean(merged_config.back_first_print_enabled);
            getElement("compositor_compose_back_with_dynamic_content").checked = Boolean(merged_config.compose_back_with_dynamic_content);

            getElement("compositor_max_person").value = String(side_config.text_fields?.person_name?.max_chars ?? 0);
            getElement("compositor_size_person").value = String(side_config.text_fields?.person_name?.font_size ?? 0);
            getElement("compositor_max_artist").value = String(side_config.text_fields?.artist_name?.max_chars ?? 0);
            getElement("compositor_size_artist").value = String(side_config.text_fields?.artist_name?.font_size ?? 0);
            getElement("compositor_max_track").value = String(side_config.text_fields?.track_name?.max_chars ?? 0);
            getElement("compositor_size_track").value = String(side_config.text_fields?.track_name?.font_size ?? 0);

            syncCompositorJsonTextarea();
        }

        function syncCompositorUi() {
            syncCompositorButtons();
            syncCompositorBoxesToDom();
            syncCompositorFormFields();
        }

        function setTopLevelCompositorValue(key_name, next_value, fallback_value) {
            const has_own_key = Object.prototype.hasOwnProperty.call(currentCompositorSource, key_name);
            const next_json = JSON.stringify(next_value);
            const fallback_json = JSON.stringify(fallback_value);

            if (has_own_key || next_json !== fallback_json) {
                currentCompositorSource[key_name] = deepClone(next_value);
            }
        }

        function applyCompositorFieldInputsToSource(update_scope = "all") {
            if (update_scope === "all" || update_scope === "top_level") {
                const merged_config = getCompositorMergedConfig();
                const next_text_color = {
                    r: clampRgbValue(getNumberValue("compositor_text_color_r", 20)),
                    g: clampRgbValue(getNumberValue("compositor_text_color_g", 20)),
                    b: clampRgbValue(getNumberValue("compositor_text_color_b", 20))
                };

                setTopLevelCompositorValue("font_file_path", getElement("compositor_font_file_path").value ?? "", merged_config.font_file_path ?? "");
                setTopLevelCompositorValue("print_mode", getElement("compositor_print_mode").value ?? "front_and_back", merged_config.print_mode ?? "front_and_back");
                setTopLevelCompositorValue("back_first_print_enabled", Boolean(getElement("compositor_back_first_print_enabled").checked), Boolean(merged_config.back_first_print_enabled));
                setTopLevelCompositorValue(
                    "compose_back_with_dynamic_content",
                    Boolean(getElement("compositor_compose_back_with_dynamic_content").checked),
                    Boolean(merged_config.compose_back_with_dynamic_content)
                );
                setTopLevelCompositorValue("text_color_rgb", next_text_color, merged_config.text_color_rgb ?? next_text_color);
            }

            if (update_scope === "all" || update_scope === "photo_box") {
                const current_side_config = getCompositorSideConfig();
                setCompositorPhotoBoxForSide(currentCompositorSide, {
                    ...(current_side_config.photo_box ?? {}),
                    oversize_factor: Number(getElement("compositor_oversize_factor").value || current_side_config.photo_box?.oversize_factor || 1)
                });
            }

            if (update_scope === "all" || update_scope === "text_fields") {
                const current_side_config = getCompositorSideConfig();

                setCompositorTextFieldForSide(currentCompositorSide, "person_name", {
                    ...(current_side_config.text_fields?.person_name ?? {}),
                    max_chars: getNumberValue("compositor_max_person", current_side_config.text_fields?.person_name?.max_chars ?? 0),
                    font_size: getNumberValue("compositor_size_person", current_side_config.text_fields?.person_name?.font_size ?? 0)
                });

                setCompositorTextFieldForSide(currentCompositorSide, "artist_name", {
                    ...(current_side_config.text_fields?.artist_name ?? {}),
                    max_chars: getNumberValue("compositor_max_artist", current_side_config.text_fields?.artist_name?.max_chars ?? 0),
                    font_size: getNumberValue("compositor_size_artist", current_side_config.text_fields?.artist_name?.font_size ?? 0)
                });

                setCompositorTextFieldForSide(currentCompositorSide, "track_name", {
                    ...(current_side_config.text_fields?.track_name ?? {}),
                    max_chars: getNumberValue("compositor_max_track", current_side_config.text_fields?.track_name?.max_chars ?? 0),
                    font_size: getNumberValue("compositor_size_track", current_side_config.text_fields?.track_name?.font_size ?? 0)
                });
            }

            syncCompositorJsonTextarea();
        }

        function attachCompositorDragAndResize(box_id, getter, setter) {
            const box_element = getElement(box_id);
            let dragging = false;
            let resizing = false;
            let start_x = 0;
            let start_y = 0;
            let start_box = null;

            box_element.addEventListener("pointerdown", (event) => {
                const handle_clicked = event.target?.classList?.contains("compositor_handle");
                dragging = !handle_clicked;
                resizing = Boolean(handle_clicked);
                start_x = event.clientX;
                start_y = event.clientY;
                start_box = { ...getter() };
                box_element.setPointerCapture(event.pointerId);
                event.preventDefault();
            });

            box_element.addEventListener("pointermove", (event) => {
                if (!dragging && !resizing) {
                    return;
                }

                const scale = getCompositorScale();
                const delta_x = (event.clientX - start_x) / scale;
                const delta_y = (event.clientY - start_y) / scale;
                const next_box = { ...start_box };

                if (dragging) {
                    next_box.x = Math.max(0, Math.round((start_box?.x ?? 0) + delta_x));
                    next_box.y = Math.max(0, Math.round((start_box?.y ?? 0) + delta_y));
                }

                if (resizing) {
                    next_box.width = Math.max(10, Math.round((start_box?.width ?? 0) + delta_x));
                    next_box.height = Math.max(10, Math.round((start_box?.height ?? 0) + delta_y));
                }

                setter(next_box);
                syncCompositorBoxesToDom();
                syncCompositorFormFields();
            });

            box_element.addEventListener("pointerup", () => {
                dragging = false;
                resizing = false;
                start_box = null;
            });
        }

        function resetCompositorState(show_status_message = false) {
            currentCompositorSource = deepClone(initialCompositorSourceState);
            currentCompositorSide = "front";
            syncCompositorUi();
            getElement("compositor_save_status").textContent = show_status_message ? "Compositor recarregado do arquivo atual." : "";
        }

        async function saveCompositorConfig() {
            const status_element = getElement("compositor_save_status");
            status_element.textContent = "";

            let parsed_payload = null;

            try {
                parsed_payload = JSON.parse(getElement("compositor_config_json").value || "{}");
            } catch {
                status_element.textContent = "JSON do compositor invÃ¡lido.";
                return;
            }

            if (!parsed_payload || typeof parsed_payload !== "object" || Array.isArray(parsed_payload)) {
                status_element.textContent = "O JSON do compositor precisa ser um objeto.";
                return;
            }

            try {
                const response = await fetch("admin/save_config.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        compositor_config: parsed_payload
                    })
                });
                const response_payload = await response.json();

                if (!response.ok || !response_payload.ok) {
                    throw new Error(response_payload.error || "Falha ao salvar compositor.");
                }

                const merged_source = mergeDeep(initialCompositorSourceState, parsed_payload);
                initialCompositorSourceState = deepClone(merged_source);
                currentCompositorSource = deepClone(merged_source);
                syncCompositorUi();
                status_element.textContent = "Compositor salvo sem descartar campos existentes do arquivo final.";
                showToast("Compositor salvo com sucesso.", "success");
            } catch (error) {
                status_element.textContent = error.message || "Falha ao salvar compositor.";
            }
        }

        async function generateCompositorPreview() {
            const preview_error = getElement("compositor_preview_error");
            const preview_image = getElement("compositor_preview_image");
            const preview_file = getElement("compositor_preview_photo").files?.[0] ?? null;

            preview_error.textContent = "";
            preview_image.classList.add("hidden");
            preview_image.removeAttribute("src");

            if (!preview_file) {
                preview_error.textContent = "Selecione uma foto para gerar o preview.";
                return;
            }

            try {
                const photo_data_url = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(String(reader.result));
                    reader.onerror = () => reject(new Error("Falha ao ler a imagem."));
                    reader.readAsDataURL(preview_file);
                });

                const response = await fetch("api/compose_image.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        csrf_token: compositorPreviewCsrfToken,
                        preview_only: true,
                        person_name: getElement("compositor_preview_person").value ?? "",
                        artist_name: getElement("compositor_preview_artist").value ?? "",
                        track_name: getElement("compositor_preview_track").value ?? "",
                        photo_data_url
                    })
                });
                const response_payload = await response.json();

                if (!response.ok || !response_payload.ok) {
                    throw new Error(response_payload.error || "Falha ao gerar preview.");
                }

                preview_image.src = response_payload.final_image_data_url;
                preview_image.classList.remove("hidden");
            } catch (error) {
                preview_error.textContent = error.message || "Falha ao gerar preview.";
            }
        }

        function bindCompositorFieldInputs() {
            [
                "compositor_font_file_path",
                "compositor_print_mode",
                "compositor_text_color_r",
                "compositor_text_color_g",
                "compositor_text_color_b"
            ].forEach((element_id) => {
                getElement(element_id).addEventListener("input", () => {
                    applyCompositorFieldInputsToSource("top_level");
                });
            });

            [
                "compositor_oversize_factor"
            ].forEach((element_id) => {
                getElement(element_id).addEventListener("input", () => {
                    applyCompositorFieldInputsToSource("photo_box");
                });
            });

            [
                "compositor_max_person",
                "compositor_size_person",
                "compositor_max_artist",
                "compositor_size_artist",
                "compositor_max_track",
                "compositor_size_track"
            ].forEach((element_id) => {
                getElement(element_id).addEventListener("input", () => {
                    applyCompositorFieldInputsToSource("text_fields");
                });
            });

            [
                "compositor_back_first_print_enabled",
                "compositor_compose_back_with_dynamic_content"
            ].forEach((element_id) => {
                getElement(element_id).addEventListener("change", () => {
                    applyCompositorFieldInputsToSource("top_level");
                });
            });

            getElement("compositor_side_front_button").addEventListener("click", () => {
                currentCompositorSide = "front";
                syncCompositorUi();
            });

            getElement("compositor_side_back_button").addEventListener("click", () => {
                currentCompositorSide = "back";
                syncCompositorUi();
            });

            getElement("compositor_frame_image").addEventListener("load", syncCompositorBoxesToDom);
            window.addEventListener("resize", syncCompositorBoxesToDom);

            getElement("compositor_reload_button").addEventListener("click", () => {
                resetCompositorState(true);
            });

            getElement("compositor_save_button").addEventListener("click", saveCompositorConfig);
            getElement("compositor_preview_button").addEventListener("click", generateCompositorPreview);
        }

        function bindCompositorStage() {
            attachCompositorDragAndResize(
                "compositor_box_photo",
                () => ({ ...(getCompositorSideConfig().photo_box ?? {}) }),
                (next_box) => {
                    setCompositorPhotoBoxForSide(currentCompositorSide, next_box);
                }
            );

            attachCompositorDragAndResize(
                "compositor_box_person_name",
                () => ({ ...(getCompositorSideTextField(currentCompositorSide, "person_name").box ?? {}) }),
                (next_box) => {
                    const current_field = getCompositorSideTextField(currentCompositorSide, "person_name");
                    setCompositorTextFieldForSide(currentCompositorSide, "person_name", {
                        ...current_field,
                        box: next_box
                    });
                }
            );

            attachCompositorDragAndResize(
                "compositor_box_artist_name",
                () => ({ ...(getCompositorSideTextField(currentCompositorSide, "artist_name").box ?? {}) }),
                (next_box) => {
                    const current_field = getCompositorSideTextField(currentCompositorSide, "artist_name");
                    setCompositorTextFieldForSide(currentCompositorSide, "artist_name", {
                        ...current_field,
                        box: next_box
                    });
                }
            );

            attachCompositorDragAndResize(
                "compositor_box_track_name",
                () => ({ ...(getCompositorSideTextField(currentCompositorSide, "track_name").box ?? {}) }),
                (next_box) => {
                    const current_field = getCompositorSideTextField(currentCompositorSide, "track_name");
                    setCompositorTextFieldForSide(currentCompositorSide, "track_name", {
                        ...current_field,
                        box: next_box
                    });
                }
            );
        }

        function bindRealtimePreview() {
            document.addEventListener("input", () => {
                refreshJsonPreview();
                refreshTemplateBatchSummary();
            });

            document.addEventListener("change", (event) => {
                if (event.target && event.target.id === "printer_profile") {
                    refreshJsonPreview();
                } else {
                    refreshJsonPreview();
                    refreshTemplateBatchSummary();
                }
            });
        }

        function bindActions() {
            getElement("save_button").addEventListener("click", () => saveConfig(true));
            getElement("reload_button").addEventListener("click", () => loadDashboard(true));
            getElement("prepare_hotfolder_button").addEventListener("click", () => prepareHotfolder("Estrutura, Python e config garantidos com sucesso."));
            getElement("copy_python_button").addEventListener("click", copyOnlyPython);

            getElement("apply_template_batch_to_form_button").addEventListener("click", applyTemplateBatchToForm);
            getElement("save_template_batch_button").addEventListener("click", saveTemplateBatchToConfig);
            getElement("print_template_batch_button").addEventListener("click", printTemplateBatch);
            getElement("printer_profile").addEventListener("change", () => {
                refreshJsonPreview();
                loadDashboard(false);
            });

            bindToggle("auto_rotate_toggle", "auto_rotate");
            bindToggle("static_back_enabled_toggle", "static_back_enabled");
            bindToggle("static_back_auto_rotate_toggle", "static_back_auto_rotate");
            bindToggle("template_auto_rotate_toggle", "template_auto_rotate");
            bindToggle("enable_folder_manifest_jobs_toggle", "enable_folder_manifest_jobs");
            bindToggle("enable_named_front_back_jobs_toggle", "enable_named_front_back_jobs");
            bindToggle("enable_single_file_jobs_toggle", "enable_single_file_jobs");

            document.querySelectorAll(".upload_asset_button").forEach((button_element) => {
                button_element.addEventListener("click", () => {
                    const asset_key = button_element.getAttribute("data-asset-key");
                    uploadAsset(asset_key);
                });
            });

            document.querySelectorAll(".delete_asset_button").forEach((button_element) => {
                button_element.addEventListener("click", () => {
                    const asset_key = button_element.getAttribute("data-asset-key");
                    deleteAsset(asset_key);
                });
            });

            bindCompositorStage();
            bindCompositorFieldInputs();
        }

        document.addEventListener("DOMContentLoaded", () => {
            bindActions();
            bindRealtimePreview();
            refreshTemplateBatchSummary();
            refreshJsonPreview();
            resetCompositorState(false);
            loadDashboard(false);
        });
    </script>
</body>
</html>

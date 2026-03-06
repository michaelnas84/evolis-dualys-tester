<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativação do Hotfolder</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

        .card_enter {
            animation: floatIn 0.45s ease-out forwards;
        }

        .glass_panel {
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .gradient_background {
            background:
                radial-gradient(circle at top left, rgba(129, 140, 248, 0.18), transparent 26%),
                radial-gradient(circle at top right, rgba(236, 72, 153, 0.12), transparent 24%),
                radial-gradient(circle at bottom center, rgba(34, 197, 94, 0.12), transparent 24%),
                linear-gradient(180deg, #f8fafc 0%, #eef2ff 45%, #f8fafc 100%);
        }

        .section_shadow {
            box-shadow:
                0 10px 40px rgba(15, 23, 42, 0.08),
                0 2px 10px rgba(15, 23, 42, 0.05);
        }

        .toast_enter {
            animation: floatIn 0.25s ease-out forwards;
        }
    </style>
</head>
<body class="gradient_background min-h-screen text-slate-800">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        <header class="card_enter mb-8">
            <div class="glass_panel section_shadow rounded-3xl border border-white/60 p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-indigo-100 px-4 py-1.5 text-sm font-semibold text-indigo-700">
                            Ativação operacional
                        </div>
                        <h1 class="text-4xl font-black tracking-tight text-slate-900">
                            Preparar e sincronizar hotfolder
                        </h1>
                        <p class="mt-3 max-w-3xl text-sm text-slate-600 lg:text-base">
                            Esse painel cria a estrutura em <span class="font-semibold">C:\card_hotfolder</span>,
                            copia o Python do projeto PHP, garante o JSON e deixa você administrar as imagens padrão.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="index.php" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                            Voltar
                        </a>
                        <a href="dashboard.php" class="rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-500">
                            Abrir dashboard
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="space-y-6">
            <section class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">Ações rápidas</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Use isso para deixar tudo pronto sem depender de outro PHP rodando dentro da hotfolder.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button id="prepare_hotfolder_button" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-500">
                            Criar estrutura + copiar Python + criar config
                        </button>
                        <button id="copy_python_button" class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800">
                            Copiar somente o Python
                        </button>
                        <button id="reload_status_button" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                            Atualizar status
                        </button>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                    <h2 class="text-2xl font-black text-slate-900">Status da estrutura</h2>
                    <div id="hotfolder_paths_status" class="mt-5 space-y-3"></div>
                </div>

                <div class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                    <h2 class="text-2xl font-black text-slate-900">Arquivos principais</h2>
                    <div id="hotfolder_files_status" class="mt-5 space-y-3"></div>
                </div>
            </section>

            <section class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                <div class="mb-6">
                    <h2 class="text-2xl font-black text-slate-900">Imagens padrão</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Você pode adicionar, substituir ou apagar os arquivos padrão usados pela hotfolder.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5">
                        <div class="mb-4">
                            <div class="text-lg font-black text-slate-900">Template frente</div>
                            <div class="text-sm text-slate-500">template_front.png</div>
                        </div>
                        <div id="asset_template_front_status" class="mb-4 text-sm text-slate-600"></div>
                        <input id="asset_template_front_input" type="file" accept=".png,.jpg,.jpeg,.bmp,.tif,.tiff" class="mb-4 block w-full text-sm">
                        <div class="flex gap-3">
                            <button data-asset-key="template_front" class="upload_asset_button rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white">
                                Enviar
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
                        </div>
                        <div id="asset_template_back_status" class="mb-4 text-sm text-slate-600"></div>
                        <input id="asset_template_back_input" type="file" accept=".png,.jpg,.jpeg,.bmp,.tif,.tiff" class="mb-4 block w-full text-sm">
                        <div class="flex gap-3">
                            <button data-asset-key="template_back" class="upload_asset_button rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white">
                                Enviar
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
                        </div>
                        <div id="asset_static_back_status" class="mb-4 text-sm text-slate-600"></div>
                        <input id="asset_static_back_input" type="file" accept=".png,.jpg,.jpeg,.bmp,.tif,.tiff" class="mb-4 block w-full text-sm">
                        <div class="flex gap-3">
                            <button data-asset-key="static_back" class="upload_asset_button rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white">
                                Enviar
                            </button>
                            <button data-asset-key="static_back" class="delete_asset_button rounded-2xl bg-rose-600 px-4 py-2 text-sm font-bold text-white">
                                Apagar
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div id="toast_container" class="fixed bottom-5 right-5 z-50 flex w-full max-w-sm flex-col gap-3"></div>

    <script>
        function getElement(element_id) {
            return document.getElementById(element_id);
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

        function renderStatus(status_payload) {
            const paths_status_container = getElement("hotfolder_paths_status");
            const files_status_container = getElement("hotfolder_files_status");

            const paths_entries = [
                ['Hotfolder', 'hotfolder_root_path'],
                ['Entrada', 'hotfolder_in_path'],
                ['Concluído', 'hotfolder_done_path'],
                ['Erro', 'hotfolder_error_path'],
                ['Logs', 'hotfolder_logs_path'],
                ['Config', 'hotfolder_config_path'],
                ['Backgrounds', 'hotfolder_backgrounds_path']
            ];

            const files_entries = [
                ['app_config.json', 'hotfolder_config_file_path'],
                ['card_hotfolder_printer.py', 'hotfolder_printer_file_path'],
                ['Python do projeto', 'project_python_source_file_path']
            ];

            paths_status_container.innerHTML = paths_entries.map(([label_text, key_name]) => `
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div class="text-sm font-bold text-slate-900">${label_text}</div>
                        ${formatExistsBadge(Boolean(status_payload.exists[key_name]))}
                    </div>
                    <div class="text-xs break-all text-slate-500">${status_payload.paths[key_name]}</div>
                </div>
            `).join("");

            files_status_container.innerHTML = files_entries.map(([label_text, key_name]) => `
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div class="text-sm font-bold text-slate-900">${label_text}</div>
                        ${formatExistsBadge(Boolean(status_payload.exists[key_name]))}
                    </div>
                    <div class="text-xs break-all text-slate-500">${status_payload.paths[key_name]}</div>
                </div>
            `).join("");

            renderAssetStatusCard("template_front", status_payload.files.template_front);
            renderAssetStatusCard("template_back", status_payload.files.template_back);
            renderAssetStatusCard("static_back", status_payload.files.static_back);
        }

        function renderAssetStatusCard(asset_key, asset_payload) {
            const target_element = getElement(`asset_${asset_key}_status`);
            if (!target_element) {
                return;
            }

            if (!asset_payload.exists) {
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
                    <div class="mt-1 text-xs break-all">${asset_payload.absolute_file_path}</div>
                    <div class="mt-1 text-xs">Tamanho: ${asset_payload.size_bytes ?? 0} bytes</div>
                    <div class="mt-1 text-xs">Modificado em: ${asset_payload.modified_at ?? "-"}</div>
                </div>
            `;
        }

        async function loadStatus() {
            try {
                const response = await fetch("api_load_config.php", {
                    method: "GET",
                    headers: {
                        "Accept": "application/json"
                    }
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao carregar status.");
                }

                renderStatus(response_payload.status);
            } catch (error) {
                showToast(error.message || "Erro ao carregar status.", "error");
            }
        }

        async function prepareHotfolder() {
            try {
                const response = await fetch("api_hotfolder_setup.php", {
                    method: "POST",
                    headers: {
                        "Accept": "application/json"
                    }
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao preparar a hotfolder.");
                }

                renderStatus(response_payload.status);
                showToast("Hotfolder preparada com sucesso.", "success");
            } catch (error) {
                showToast(error.message || "Erro ao preparar a hotfolder.", "error");
            }
        }

        async function copyOnlyPython() {
            await prepareHotfolder();
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

            try {
                const response = await fetch("api_asset_upload.php", {
                    method: "POST",
                    body: form_data
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao enviar imagem.");
                }

                renderStatus(response_payload.status);
                input_element.value = "";
                showToast("Imagem enviada com sucesso.", "success");
            } catch (error) {
                showToast(error.message || "Erro ao enviar imagem.", "error");
            }
        }

        async function deleteAsset(asset_key) {
            try {
                const response = await fetch("api_asset_delete.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({ asset_key })
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao apagar imagem.");
                }

                renderStatus(response_payload.status);
                showToast("Imagem apagada com sucesso.", "success");
            } catch (error) {
                showToast(error.message || "Erro ao apagar imagem.", "error");
            }
        }

        function bindActions() {
            getElement("prepare_hotfolder_button").addEventListener("click", prepareHotfolder);
            getElement("copy_python_button").addEventListener("click", copyOnlyPython);
            getElement("reload_status_button").addEventListener("click", loadStatus);

            document.querySelectorAll(".upload_asset_button").forEach((button_element) => {
                button_element.addEventListener("click", () => {
                    uploadAsset(button_element.dataset.assetKey);
                });
            });

            document.querySelectorAll(".delete_asset_button").forEach((button_element) => {
                button_element.addEventListener("click", () => {
                    deleteAsset(button_element.dataset.assetKey);
                });
            });
        }

        document.addEventListener("DOMContentLoaded", () => {
            bindActions();
            loadStatus();
        });
    </script>
</body>
</html>
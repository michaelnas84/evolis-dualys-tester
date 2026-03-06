<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Configuração</title>
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

        .glass_panel {
            background: rgba(255, 255, 255, 0.78);
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
                radial-gradient(circle at top left, rgba(129, 140, 248, 0.16), transparent 30%),
                radial-gradient(circle at top right, rgba(236, 72, 153, 0.12), transparent 28%),
                radial-gradient(circle at bottom center, rgba(34, 197, 94, 0.10), transparent 26%),
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
    </style>
</head>
<body class="gradient_background min-h-screen text-slate-800">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        <header class="card_enter mb-8">
            <div class="glass_panel section_shadow rounded-3xl border border-white/60 p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-1.5 text-sm font-semibold text-emerald-700">
                            Dashboard de configuração
                        </div>
                        <h1 class="text-4xl font-black tracking-tight text-slate-900">
                            app_config.json da hotfolder
                        </h1>
                        <p class="mt-3 max-w-3xl text-sm text-slate-600 lg:text-base">
                            Esse painel salva diretamente em <span class="font-semibold">C:\card_hotfolder\config\app_config.json</span>.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="index.php" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                            Voltar
                        </a>
                        <a href="activation.php" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-500">
                            Abrir ativação
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="space-y-6">
            <section class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                <div class="flex flex-wrap gap-3">
                    <button id="save_button" class="rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-500">
                        Salvar configurações
                    </button>
                    <button id="reload_button" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Recarregar
                    </button>
                    <button id="prepare_button" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-500">
                        Garantir estrutura + Python
                    </button>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                <div class="space-y-6 xl:col-span-7">
                    <div class="glass_panel section_shadow rounded-3xl border border-white/60 p-6">
                        <h2 class="text-2xl font-black text-slate-900">Padrões da impressora</h2>
                        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <input id="printer_name" type="text" placeholder="Nome da impressora" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <input id="copies" type="number" min="1" placeholder="Cópias" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <select id="duplex" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <option value="auto">auto</option>
                                <option value="true">true</option>
                                <option value="false">false</option>
                            </select>
                            <select id="fit_mode" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <option value="fill">fill</option>
                                <option value="contain">contain</option>
                                <option value="stretch">stretch</option>
                            </select>
                            <select id="rotate_degrees" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <option value="0">0</option>
                                <option value="90">90</option>
                                <option value="180">180</option>
                                <option value="270">270</option>
                            </select>
                            <input id="form_name" type="text" placeholder="Form name" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <input id="print_dpi" type="number" min="1" placeholder="Print DPI" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <input id="card_width_mm" type="number" min="0.01" step="0.01" placeholder="Largura do cartão (mm)" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <input id="card_height_mm" type="number" min="0.01" step="0.01" placeholder="Altura do cartão (mm)" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">

                            <div class="grid grid-cols-3 gap-3">
                                <input id="background_red" type="number" min="0" max="255" placeholder="R" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <input id="background_green" type="number" min="0" max="255" placeholder="G" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <input id="background_blue" type="number" min="0" max="255" placeholder="B" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800">Auto rotate</div>
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
                        <h2 class="text-2xl font-black text-slate-900">Verso estático</h2>
                        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <input id="static_back_image_path" type="text" placeholder="Caminho do static_back" class="md:col-span-2 input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <select id="static_back_fit_mode" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <option value="fill">fill</option>
                                <option value="contain">contain</option>
                                <option value="stretch">stretch</option>
                            </select>
                            <select id="static_back_rotate_degrees" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <option value="0">0</option>
                                <option value="90">90</option>
                                <option value="180">180</option>
                                <option value="270">270</option>
                            </select>

                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800">Enabled</div>
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
                                        <div class="text-sm font-bold text-slate-800">Auto rotate</div>
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
                        <h2 class="text-2xl font-black text-slate-900">Template print</h2>
                        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <input id="template_front_image_path" type="text" placeholder="Front image path" class="md:col-span-2 input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <input id="template_back_image_path" type="text" placeholder="Back image path" class="md:col-span-2 input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">

                            <select id="template_mode" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <option value="front">front</option>
                                <option value="front_back">front_back</option>
                            </select>
                            <input id="template_copies" type="number" min="1" placeholder="Copies" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">

                            <select id="template_fit_mode" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <option value="fill">fill</option>
                                <option value="contain">contain</option>
                                <option value="stretch">stretch</option>
                            </select>
                            <select id="template_rotate_degrees" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <option value="0">0</option>
                                <option value="90">90</option>
                                <option value="180">180</option>
                                <option value="270">270</option>
                            </select>

                            <select id="template_duplex" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <option value="true">true</option>
                                <option value="false">false</option>
                                <option value="auto">auto</option>
                            </select>
                            <input id="template_form_name" type="text" placeholder="Template form name" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">

                            <input id="template_print_dpi" type="number" min="1" placeholder="Template print DPI" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <input id="template_card_width_mm" type="number" min="0.01" step="0.01" placeholder="Template width mm" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <input id="template_card_height_mm" type="number" min="0.01" step="0.01" placeholder="Template height mm" class="input_focusRing rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">

                            <div class="grid grid-cols-3 gap-3">
                                <input id="template_background_red" type="number" min="0" max="255" placeholder="R" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <input id="template_background_green" type="number" min="0" max="255" placeholder="G" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <input id="template_background_blue" type="number" min="0" max="255" placeholder="B" class="input_focus_ring rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800">Auto rotate</div>
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
                        <h2 class="text-2xl font-black text-slate-900">Job detection</h2>
                        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="mb-3 text-sm font-bold text-slate-800">Folder manifest</div>
                                <button type="button" id="enable_folder_manifest_jobs_toggle" class="switch_track relative h-8 w-16 rounded-full bg-slate-300">
                                    <span id="enable_folder_manifest_jobs_thumb" class="switch_thumb absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow-md"></span>
                                </button>
                                <input id="enable_folder_manifest_jobs" type="hidden" value="true">
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="mb-3 text-sm font-bold text-slate-800">Named front/back</div>
                                <button type="button" id="enable_named_front_back_jobs_toggle" class="switch_track relative h-8 w-16 rounded-full bg-slate-300">
                                    <span id="enable_named_front_back_jobs_thumb" class="switch_thumb absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow-md"></span>
                                </button>
                                <input id="enable_named_front_back_jobs" type="hidden" value="true">
                            </div>

                            <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                <div class="mb-3 text-sm font-bold text-slate-800">Single file jobs</div>
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
                        <h2 class="text-2xl font-black text-slate-900">Prévia do JSON</h2>
                        <pre id="json_preview" class="mt-5 max-h-[750px] overflow-auto rounded-3xl bg-slate-950 p-5 text-xs leading-6 text-emerald-300"></pre>
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

        function setToggle(toggle_id, hidden_input_id, is_enabled) {
            const toggle_element = getElement(toggle_id);
            const thumb_element = getElement(toggle_id.replace("_toggle", "_thumb"));
            const hidden_input = getElement(hidden_input_id);

            hidden_input.value = is_enabled ? "true" : "false";

            if (is_enabled) {
                toggle_element.classList.remove("bg-slate-300");
                toggle_element.classList.add("bg-indigo-500");
                thumb_element.style.transform = "translateX(32px)";
            } else {
                toggle_element.classList.remove("bg-indigo-500");
                toggle_element.classList.add("bg-slate-300");
                thumb_element.style.transform = "translateX(0)";
            }
        }

        function bindToggle(toggle_id, hidden_input_id) {
            getElement(toggle_id).addEventListener("click", () => {
                const hidden_input = getElement(hidden_input_id);
                setToggle(toggle_id, hidden_input_id, hidden_input.value !== "true");
                refreshJsonPreview();
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

        function populateForm(config_payload) {
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

            refreshJsonPreview();
        }

        function buildConfigPayload() {
            return {
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
            };
        }

        function refreshJsonPreview() {
            getElement("json_preview").textContent = JSON.stringify(buildConfigPayload(), null, 2);
        }

        async function loadConfig() {
            try {
                const response = await fetch("api_load_config.php", {
                    method: "GET",
                    headers: {
                        "Accept": "application/json"
                    }
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao carregar configuração.");
                }

                populateForm(response_payload.data);
                showToast("Configuração carregada com sucesso.", "success");
            } catch (error) {
                showToast(error.message || "Erro ao carregar configuração.", "error");
            }
        }

        async function saveConfig() {
            try {
                const response = await fetch("api_save_config.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify(buildConfigPayload())
                });

                const response_payload = await response.json();

                if (!response.ok || !response_payload.success) {
                    throw new Error(response_payload.message || "Falha ao salvar configuração.");
                }

                refreshJsonPreview();
                showToast("Configuração salva com sucesso.", "success");
            } catch (error) {
                showToast(error.message || "Erro ao salvar configuração.", "error");
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

                showToast("Estrutura e Python garantidos com sucesso.", "success");
                await loadConfig();
            } catch (error) {
                showToast(error.message || "Erro ao preparar a hotfolder.", "error");
            }
        }

        function bindRealtimePreview() {
            document.addEventListener("input", refreshJsonPreview);
            document.addEventListener("change", refreshJsonPreview);
        }

        function bindActions() {
            getElement("save_button").addEventListener("click", saveConfig);
            getElement("reload_button").addEventListener("click", loadConfig);
            getElement("prepare_button").addEventListener("click", prepareHotfolder);

            bindToggle("auto_rotate_toggle", "auto_rotate");
            bindToggle("static_back_enabled_toggle", "static_back_enabled");
            bindToggle("static_back_auto_rotate_toggle", "static_back_auto_rotate");
            bindToggle("template_auto_rotate_toggle", "template_auto_rotate");
            bindToggle("enable_folder_manifest_jobs_toggle", "enable_folder_manifest_jobs");
            bindToggle("enable_named_front_back_jobs_toggle", "enable_named_front_back_jobs");
            bindToggle("enable_single_file_jobs_toggle", "enable_single_file_jobs");
        }

        document.addEventListener("DOMContentLoaded", () => {
            bindActions();
            bindRealtimePreview();
            loadConfig();
        });
    </script>
</body>
</html>
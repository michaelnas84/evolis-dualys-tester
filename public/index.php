<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central do Hotfolder</title>
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
    </style>
</head>
<body class="gradient_background min-h-screen text-slate-800">
    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-8">
        <header class="card_enter mb-8">
            <div class="glass_panel section_shadow rounded-3xl border border-white/60 p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-indigo-100 px-4 py-1.5 text-sm font-semibold text-indigo-700">
                            Painel principal
                        </div>
                        <h1 class="text-4xl font-black tracking-tight text-slate-900">
                            Central do Hotfolder
                        </h1>
                        <p class="mt-3 max-w-3xl text-sm text-slate-600 lg:text-base">
                            Gerencie a preparação da hotfolder em <span class="font-semibold">C:\card_hotfolder</span>,
                            sincronize o script Python, controle as imagens padrão e edite o JSON de configuração.
                        </p>
                    </div>
                </div>
            </div>
        </header>

        <main class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <a href="activation.php" class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-8 transition hover:-translate-y-1 hover:border-indigo-200">
                <div class="mb-4 inline-flex rounded-2xl bg-indigo-100 px-4 py-2 text-sm font-bold text-indigo-700">
                    Ativação e sincronização
                </div>
                <h2 class="text-2xl font-black text-slate-900">Preparar hotfolder</h2>
                <p class="mt-3 text-sm text-slate-600">
                    Cria pastas obrigatórias, copia o Python para a hotfolder, cria o JSON e gerencia imagens padrão.
                </p>
                <div class="mt-6 text-sm font-bold text-indigo-700">
                    Abrir painel de ativação →
                </div>
            </a>

            <a href="dashboard.php" class="card_enter glass_panel section_shadow rounded-3xl border border-white/60 p-8 transition hover:-translate-y-1 hover:border-emerald-200">
                <div class="mb-4 inline-flex rounded-2xl bg-emerald-100 px-4 py-2 text-sm font-bold text-emerald-700">
                    Dashboard de configuração
                </div>
                <h2 class="text-2xl font-black text-slate-900">Editar app_config.json</h2>
                <p class="mt-3 text-sm text-slate-600">
                    Ajuste impressora, verso estático, template, detecção de jobs e visualize a prévia do JSON em tempo real.
                </p>
                <div class="mt-6 text-sm font-bold text-emerald-700">
                    Abrir dashboard →
                </div>
            </a>
        </main>
    </div>
</body>
</html>
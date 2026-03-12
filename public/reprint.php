<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';

$csrfToken = ensureCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Reimpressao</title>
    <script src="js/tailwind.js"></script>
    <style>
        @keyframes liftIn {
            from {
                opacity: 0;
                transform: translateY(16px) scale(0.985);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .panel_enter {
            animation: liftIn 0.42s ease-out forwards;
        }

        .page_bg {
            background:
                radial-gradient(circle at 10% 10%, rgba(251, 146, 60, 0.16), transparent 22%),
                radial-gradient(circle at 90% 12%, rgba(239, 68, 68, 0.12), transparent 24%),
                radial-gradient(circle at 50% 100%, rgba(20, 184, 166, 0.10), transparent 26%),
                linear-gradient(180deg, #fffaf5 0%, #fff7ed 40%, #f8fafc 100%);
        }

        .glass_panel {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .section_shadow {
            box-shadow:
                0 18px 60px rgba(120, 53, 15, 0.08),
                0 6px 20px rgba(15, 23, 42, 0.05);
        }

        .job_card:hover {
            transform: translateY(-2px);
            box-shadow:
                0 22px 50px rgba(15, 23, 42, 0.10),
                0 6px 16px rgba(15, 23, 42, 0.06);
        }

        .preview_frame {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.72), rgba(255,255,255,0.96)),
                linear-gradient(135deg, rgba(251,146,60,0.09), rgba(15,23,42,0.03));
        }
    </style>
</head>
<body class="page_bg min-h-screen text-slate-800">
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        <header class="panel_enter mb-6">
            <div class="glass_panel section_shadow rounded-[2rem] border border-white/70 p-6 lg:p-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <a href="index.php" class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-white/80 px-4 py-2 text-sm font-bold text-amber-800 transition hover:border-amber-300 hover:bg-white">
                            <span>&larr;</span>
                            <span>Voltar para central</span>
                        </a>
                        <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-950 lg:text-5xl">
                            Reimpressao da hotfolder
                        </h1>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 lg:text-base">
                            Veja os 30 jobs mais recentes das pastas de falha e pronto, abra a frente e o verso em um modal e confirme duas vezes antes de reenfileirar.
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-3xl border border-rose-200 bg-rose-50 px-4 py-3">
                            <div class="font-black text-rose-700">Falha</div>
                            <div class="mt-1 text-slate-600">Jobs da pasta <code>error</code></div>
                        </div>
                        <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <div class="font-black text-emerald-700">Pronto</div>
                            <div class="mt-1 text-slate-600">Jobs da pasta <code>done</code></div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.5fr)_360px]">
            <section class="panel_enter glass_panel section_shadow rounded-[2rem] border border-white/70 p-5 lg:p-6">
                <div class="flex flex-col gap-3 border-b border-slate-200/70 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">Ultimos 30 jobs</h2>
                        <p class="mt-1 text-sm text-slate-500">Ordenados do mais recente para o mais antigo.</p>
                    </div>
                    <button id="reload_button" type="button" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white transition hover:bg-slate-800">
                        Atualizar lista
                    </button>
                </div>

                <div id="list_state" class="py-10 text-center text-sm text-slate-500">
                    Carregando jobs da hotfolder...
                </div>

                <div id="job_list" class="mt-5 grid grid-cols-1 gap-3"></div>
            </section>

            <aside class="panel_enter glass_panel section_shadow rounded-[2rem] border border-white/70 p-5 lg:p-6">
                <div class="rounded-[1.75rem] border border-amber-200 bg-amber-50/80 p-5">
                    <div class="text-sm font-black uppercase tracking-[0.2em] text-amber-700">Fluxo</div>
                    <h2 class="mt-3 text-2xl font-black text-slate-900">Reenfileirar com seguranca</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Ao abrir um job, a tela mostra as artes disponiveis. O botao de reimpressao exige duas confirmacoes para evitar clique acidental.
                    </p>
                </div>

                <div class="mt-5 rounded-[1.75rem] border border-slate-200 bg-white/80 p-5">
                    <div class="text-sm font-black uppercase tracking-[0.2em] text-slate-500">Dicas</div>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <li>Use jobs em <code>Falha</code> para tentar novamente sem recriar a arte.</li>
                        <li>Jobs em <code>Pronto</code> servem como atalho de reemissao quando o cartao saiu com defeito fisico.</li>
                        <li>O reenfileiramento cria uma nova pasta em <code>C:\card_hotfolder\in</code>.</li>
                    </ul>
                </div>

                <div id="toast" class="mt-5 hidden rounded-[1.5rem] border px-4 py-3 text-sm font-semibold"></div>
            </aside>
        </main>
    </div>

    <div id="modal_overlay" class="fixed inset-0 z-40 hidden bg-slate-950/60 p-4 backdrop-blur-sm">
        <div class="flex min-h-full items-center justify-center">
            <div class="glass_panel section_shadow w-full max-w-6xl rounded-[2rem] border border-white/80 p-5 lg:p-6">
                <div class="flex flex-col gap-4 border-b border-slate-200/70 pb-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div id="modal_status_badge" class="inline-flex rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.2em]"></div>
                        <h3 id="modal_title" class="mt-3 text-2xl font-black text-slate-950"></h3>
                        <p id="modal_subtitle" class="mt-2 text-sm text-slate-500"></p>
                    </div>
                    <button id="modal_close" type="button" class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-white text-xl font-bold text-slate-500 transition hover:border-slate-300 hover:text-slate-900">
                        &times;
                    </button>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.2fr)_320px]">
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <div class="preview_frame rounded-[1.75rem] border border-slate-200 p-4">
                            <div class="mb-3 text-sm font-black uppercase tracking-[0.2em] text-slate-500">Frente</div>
                            <div class="flex min-h-[420px] items-center justify-center overflow-hidden rounded-[1.25rem] bg-white">
                                <img id="modal_front_image" alt="Preview da frente" class="max-h-[520px] w-auto max-w-full rounded-xl object-contain">
                                <div id="modal_front_empty" class="hidden px-6 text-center text-sm text-slate-400">
                                    Frente indisponivel neste job.
                                </div>
                            </div>
                        </div>

                        <div class="preview_frame rounded-[1.75rem] border border-slate-200 p-4">
                            <div class="mb-3 text-sm font-black uppercase tracking-[0.2em] text-slate-500">Verso</div>
                            <div class="flex min-h-[420px] items-center justify-center overflow-hidden rounded-[1.25rem] bg-white">
                                <img id="modal_back_image" alt="Preview do verso" class="max-h-[520px] w-auto max-w-full rounded-xl object-contain">
                                <div id="modal_back_empty" class="hidden px-6 text-center text-sm text-slate-400">
                                    Verso indisponivel neste job.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[1.75rem] border border-slate-200 bg-white/85 p-5">
                        <div class="text-sm font-black uppercase tracking-[0.2em] text-slate-500">Metadados</div>
                        <div id="modal_meta" class="mt-4 space-y-3 text-sm text-slate-600"></div>

                        <div class="mt-6 rounded-[1.5rem] border border-amber-200 bg-amber-50 p-4">
                            <div class="text-sm font-black text-amber-800">Confirmacao dupla</div>
                            <p id="modal_confirm_hint" class="mt-2 text-sm leading-6 text-amber-900/80">
                                Clique uma vez para preparar a reimpressao e outra vez para colocar o job novamente na fila.
                            </p>
                        </div>

                        <div id="modal_error" class="mt-4 hidden rounded-[1.25rem] border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700"></div>

                        <div class="mt-6 flex flex-col gap-3">
                            <button id="modal_reprint_button" type="button" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-4 text-sm font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800">
                                Confirmar reimpressao
                            </button>
                            <button id="modal_cancel_button" type="button" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:text-slate-950">
                                Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            "use strict";

            const CSRF = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const API_LIST = "api/reprint_list.php";
            const API_DETAIL = "api/reprint_detail.php";
            const API_ENQUEUE = "api/reprint_enqueue.php";

            const listState = document.getElementById("list_state");
            const jobList = document.getElementById("job_list");
            const reloadButton = document.getElementById("reload_button");
            const toast = document.getElementById("toast");

            const modalOverlay = document.getElementById("modal_overlay");
            const modalClose = document.getElementById("modal_close");
            const modalCancel = document.getElementById("modal_cancel_button");
            const modalTitle = document.getElementById("modal_title");
            const modalSubtitle = document.getElementById("modal_subtitle");
            const modalStatusBadge = document.getElementById("modal_status_badge");
            const modalFrontImage = document.getElementById("modal_front_image");
            const modalBackImage = document.getElementById("modal_back_image");
            const modalFrontEmpty = document.getElementById("modal_front_empty");
            const modalBackEmpty = document.getElementById("modal_back_empty");
            const modalMeta = document.getElementById("modal_meta");
            const modalConfirmHint = document.getElementById("modal_confirm_hint");
            const modalError = document.getElementById("modal_error");
            const modalReprintButton = document.getElementById("modal_reprint_button");

            const state = {
                jobs: [],
                selectedJob: null,
                confirmStep: 0,
                isLoadingList: false,
                isSubmitting: false,
            };

            function statusBadgeClass(status) {
                return status === "error"
                    ? "bg-rose-100 text-rose-700 border border-rose-200"
                    : "bg-emerald-100 text-emerald-700 border border-emerald-200";
            }

            function escapeHtml(value) {
                return String(value)
                    .replaceAll("&", "&amp;")
                    .replaceAll("<", "&lt;")
                    .replaceAll(">", "&gt;")
                    .replaceAll('"', "&quot;")
                    .replaceAll("'", "&#39;");
            }

            function statusIcon(status) {
                if (status === "error") {
                    return `
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
                            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 9v4"></path>
                                <path d="M12 17h.01"></path>
                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"></path>
                            </svg>
                        </span>
                    `;
                }

                return `
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 6 9 17l-5-5"></path>
                        </svg>
                    </span>
                `;
            }

            function setToast(message, variant) {
                if (!message) {
                    toast.className = "mt-5 hidden rounded-[1.5rem] border px-4 py-3 text-sm font-semibold";
                    toast.textContent = "";
                    return;
                }

                const variantClass = variant === "error"
                    ? "border-rose-200 bg-rose-50 text-rose-700"
                    : "border-emerald-200 bg-emerald-50 text-emerald-700";

                toast.className = `mt-5 rounded-[1.5rem] border px-4 py-3 text-sm font-semibold ${variantClass}`;
                toast.textContent = message;
            }

            function setListLoading(message) {
                listState.className = "py-10 text-center text-sm text-slate-500";
                listState.textContent = message;
                listState.style.display = "block";
                jobList.innerHTML = "";
            }

            function renderList() {
                if (!state.jobs.length) {
                    setListLoading("Nenhum job encontrado nas pastas done/error.");
                    return;
                }

                listState.style.display = "none";
                jobList.innerHTML = state.jobs.map((job) => {
                    const statusPillClass = job.status === "error"
                        ? "bg-rose-100 text-rose-700"
                        : "bg-emerald-100 text-emerald-700";
                    const modeLabel = job.print_mode === "front_and_back" ? "Frente e verso" : "Somente frente";
                    const sideLabel = job.has_back ? "Frente + verso" : "Frente";

                    return `
                        <button
                            type="button"
                            class="job_card group w-full rounded-[1.75rem] border border-slate-200 bg-white/85 p-4 text-left transition duration-200"
                            data-job-id="${escapeHtml(job.job_id)}"
                            data-status="${escapeHtml(job.status)}"
                        >
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex items-start gap-4">
                                    ${statusIcon(job.status)}
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full px-3 py-1 text-xs font-black uppercase tracking-[0.2em] ${statusPillClass}">
                                                ${escapeHtml(job.status_label)}
                                            </span>
                                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                                                ${escapeHtml(sideLabel)}
                                            </span>
                                        </div>
                                        <div class="mt-3 text-lg font-black text-slate-900">${escapeHtml(job.job_id)}</div>
                                        <div class="mt-1 text-sm text-slate-500">
                                            ${escapeHtml(job.modified_at_display)}
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-2 text-sm text-slate-600 sm:grid-cols-3 sm:text-right">
                                    <div>
                                        <div class="font-black text-slate-900">Modo</div>
                                        <div>${escapeHtml(modeLabel)}</div>
                                    </div>
                                    <div>
                                        <div class="font-black text-slate-900">Copias</div>
                                        <div>${escapeHtml(job.copies)}</div>
                                    </div>
                                    <div>
                                        <div class="font-black text-slate-900">Impressora</div>
                                        <div>${escapeHtml(job.printer_name || "-")}</div>
                                    </div>
                                </div>
                            </div>
                        </button>
                    `;
                }).join("");

                jobList.querySelectorAll("[data-job-id]").forEach((button) => {
                    button.addEventListener("click", () => {
                        openJobDetail(button.dataset.status || "", button.dataset.jobId || "");
                    });
                });
            }

            async function postJson(url, payload) {
                const response = await fetch(url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();
                if (!data.ok) {
                    throw new Error(data.error || "Falha na requisicao.");
                }

                return data;
            }

            async function loadJobs() {
                state.isLoadingList = true;
                reloadButton.disabled = true;
                setListLoading("Carregando jobs da hotfolder...");

                try {
                    const data = await postJson(API_LIST, {
                        csrf_token: CSRF,
                        limit: 30,
                    });
                    state.jobs = Array.isArray(data.jobs) ? data.jobs : [];
                    renderList();
                } catch (error) {
                    setListLoading(`Erro ao carregar jobs: ${String(error.message || error)}`);
                } finally {
                    state.isLoadingList = false;
                    reloadButton.disabled = false;
                }
            }

            function setModalConfirmStep(step) {
                state.confirmStep = step;

                if (step === 0) {
                    modalReprintButton.textContent = "Confirmar reimpressao";
                    modalReprintButton.className = "inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-4 text-sm font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800";
                    modalConfirmHint.textContent = "Clique uma vez para preparar a reimpressao e outra vez para colocar o job novamente na fila.";
                    return;
                }

                modalReprintButton.textContent = "Confirmar novamente";
                modalReprintButton.className = "inline-flex items-center justify-center rounded-2xl bg-amber-500 px-4 py-4 text-sm font-black uppercase tracking-[0.18em] text-slate-950 transition hover:bg-amber-400";
                modalConfirmHint.textContent = "Ultima etapa: confirme de novo para criar uma nova pasta em C:\\card_hotfolder\\in.";
            }

            function openModal() {
                modalOverlay.classList.remove("hidden");
                document.body.classList.add("overflow-hidden");
            }

            function closeModal() {
                modalOverlay.classList.add("hidden");
                document.body.classList.remove("overflow-hidden");
                state.selectedJob = null;
                state.isSubmitting = false;
                modalReprintButton.disabled = false;
                modalError.classList.add("hidden");
                modalError.textContent = "";
                modalFrontImage.removeAttribute("src");
                modalBackImage.removeAttribute("src");
                setModalConfirmStep(0);
            }

            function setImageState(imageElement, emptyElement, imageUrl, emptyText) {
                if (!imageUrl) {
                    imageElement.classList.add("hidden");
                    imageElement.removeAttribute("src");
                    emptyElement.classList.remove("hidden");
                    emptyElement.textContent = emptyText;
                    return;
                }

                emptyElement.classList.add("hidden");
                imageElement.classList.remove("hidden");
                imageElement.src = imageUrl;
            }

            async function openJobDetail(status, jobId) {
                setToast("", "success");
                state.selectedJob = null;
                state.isSubmitting = false;
                modalReprintButton.disabled = false;
                modalError.classList.add("hidden");
                modalError.textContent = "";
                modalTitle.textContent = "Carregando preview...";
                modalSubtitle.textContent = "";
                modalMeta.innerHTML = "";
                setImageState(modalFrontImage, modalFrontEmpty, "", "Carregando frente...");
                setImageState(modalBackImage, modalBackEmpty, "", "Carregando verso...");
                modalStatusBadge.className = "inline-flex rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.2em]";
                openModal();

                try {
                    const data = await postJson(API_DETAIL, {
                        csrf_token: CSRF,
                        status,
                        job_id: jobId,
                    });

                    state.selectedJob = data.job;
                    modalTitle.textContent = data.job.job_id;
                    modalSubtitle.textContent = `${data.job.modified_at_display} · ${data.job.print_mode === "front_and_back" ? "Frente e verso" : "Somente frente"}`;
                    modalStatusBadge.className = `inline-flex rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.2em] ${statusBadgeClass(data.job.status)}`;
                    modalStatusBadge.textContent = data.job.status_label;

                    setImageState(
                        modalFrontImage,
                        modalFrontEmpty,
                        data.job.front_image_url,
                        "Frente indisponivel neste job."
                    );
                    setImageState(
                        modalBackImage,
                        modalBackEmpty,
                        data.job.back_image_url,
                        "Verso indisponivel neste job."
                    );

                    modalMeta.innerHTML = [
                        ["Impressora", data.job.printer_name || "-"],
                        ["Copias", data.job.copies],
                        ["Duplex", data.job.duplex || "-"],
                        ["Fit", data.job.fit_mode || "-"],
                        ["Origem", data.job.status === "error" ? "Pasta error" : "Pasta done"],
                    ].map(([label, value]) => `
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <span class="font-bold text-slate-500">${escapeHtml(label)}</span>
                            <span class="text-right font-black text-slate-900">${escapeHtml(value)}</span>
                        </div>
                    `).join("");

                    setModalConfirmStep(0);
                } catch (error) {
                    modalError.classList.remove("hidden");
                    modalError.textContent = String(error.message || error);
                    modalTitle.textContent = "Falha ao carregar o job";
                    modalSubtitle.textContent = "Tente novamente.";
                    setImageState(modalFrontImage, modalFrontEmpty, "", "Nao foi possivel abrir a frente.");
                    setImageState(modalBackImage, modalBackEmpty, "", "Nao foi possivel abrir o verso.");
                }
            }

            async function enqueueSelectedJob() {
                if (!state.selectedJob || state.isSubmitting) {
                    return;
                }

                state.isSubmitting = true;
                modalReprintButton.disabled = true;
                modalError.classList.add("hidden");
                modalError.textContent = "";

                try {
                    const data = await postJson(API_ENQUEUE, {
                        csrf_token: CSRF,
                        status: state.selectedJob.status,
                        job_id: state.selectedJob.job_id,
                    });

                    closeModal();
                    setToast(`Job reenfileirado com sucesso: ${data.reprint_job.job_id}`, "success");
                    await loadJobs();
                } catch (error) {
                    modalError.classList.remove("hidden");
                    modalError.textContent = String(error.message || error);
                } finally {
                    state.isSubmitting = false;
                    modalReprintButton.disabled = false;
                }
            }

            modalReprintButton.addEventListener("click", async () => {
                if (!state.selectedJob) {
                    return;
                }

                if (state.confirmStep === 0) {
                    setModalConfirmStep(1);
                    return;
                }

                await enqueueSelectedJob();
            });

            modalClose.addEventListener("click", closeModal);
            modalCancel.addEventListener("click", closeModal);
            reloadButton.addEventListener("click", loadJobs);

            modalOverlay.addEventListener("click", (event) => {
                if (event.target === modalOverlay) {
                    closeModal();
                }
            });

            document.addEventListener("keydown", (event) => {
                if (event.key === "Escape" && !modalOverlay.classList.contains("hidden")) {
                    closeModal();
                }
            });

            loadJobs();
        })();
    </script>
</body>
</html>

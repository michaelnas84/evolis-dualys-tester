<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$csrf_token = (string)$_SESSION['csrf_token'];
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Card Kiosk</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    #camera_video {
      transform: scaleX(-1);
    }
  </style>
</head>

<body class="h-screen bg-slate-950 text-slate-100 select-none overflow-hidden">

  <div class="h-full w-full">

    <!-- IDLE -->
    <div id="screen_idle" class="h-full w-full relative">
      <div class="absolute inset-0 flex items-center justify-center">
        <div class="text-center px-6">
          <div class="text-4xl sm:text-5xl font-semibold">Toque para começar</div>
          <div class="mt-4 text-slate-300">Foto + nome + música</div>
          <button id="idle_start_button" class="mt-10 px-6 py-4 rounded-2xl bg-white text-slate-900 text-xl font-semibold">
            Iniciar
          </button>
          <div id="idle_health" class="mt-6 text-sm text-slate-400"></div>
          <div class="mt-3 text-xs text-slate-500">Atalho admin: Ctrl + Alt + P</div>
        </div>
      </div>
    </div>

    <!-- CAPTURE -->
    <div id="screen_capture" class="hidden h-full w-full">
      <div class="h-full w-full flex flex-col">
        <div class="px-6 pt-6 flex items-center justify-between gap-4">
          <div>
            <div class="text-3xl font-semibold">Tire sua foto</div>
            <div class="mt-1 text-slate-300">Centralize o rosto</div>
          </div>
          <div class="text-right">
            <div class="text-sm text-slate-400">Inatividade</div>
            <div id="idle_countdown" class="text-xl font-semibold">--</div>
          </div>
        </div>

        <div class="flex-1 px-6 py-6 flex items-center justify-center">
          <div id="preview_container" class="relative w-full max-w-md rounded-2xl overflow-hidden bg-black border border-slate-800" style="aspect-ratio: 1 / 1;">
            <video id="camera_video" class="absolute inset-0 h-full w-full object-cover" autoplay playsinline muted></video>
            <img id="captured_image" class="absolute inset-0 h-full w-full object-cover hidden" alt="Foto" />

            <div id="countdown_overlay" class="hidden absolute inset-0 flex items-center justify-center">
              <div class="text-7xl font-extrabold bg-black/40 px-8 py-4 rounded-2xl">3</div>
            </div>
          </div>
        </div>

        <div class="px-6 pb-8">
          <div class="flex flex-wrap gap-3 justify-center">
            <button id="capture_button" class="px-8 py-4 rounded-2xl bg-white text-slate-900 text-xl font-semibold">
              Tirar foto
            </button>
            <button id="retake_button" class="hidden px-8 py-4 rounded-2xl bg-slate-800 hover:bg-slate-700 text-xl font-semibold">
              Refazer
            </button>
            <button id="proceed_button" class="hidden px-8 py-4 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xl font-semibold">
              Prosseguir
            </button>
            <button id="switch_camera_button" class="px-6 py-4 rounded-2xl bg-slate-900 border border-slate-700">
              Trocar câmera
            </button>
            <button id="capture_cancel_button" class="px-6 py-4 rounded-2xl bg-slate-900 border border-slate-700">
              Cancelar
            </button>
          </div>
          <div id="capture_error" class="mt-4 text-center text-sm text-rose-300"></div>
        </div>
      </div>
    </div>

    <!-- FORM -->
    <div id="screen_form" class="hidden h-full w-full">
      <div class="h-full w-full flex flex-col">
        <div class="px-6 pt-6 flex items-start justify-between gap-4">
          <div>
            <div class="text-3xl font-semibold">Preencha</div>
            <div class="mt-1 text-slate-300">Nome + artista + música</div>
          </div>
          <div class="text-right">
            <div class="text-sm text-slate-400">Modo</div>
            <div id="entry_mode_label" class="text-xl font-semibold">Manual</div>
            <div id="admin_unlocked_label" class="mt-1 text-xs text-slate-500 hidden">Admin destravado</div>
          </div>
        </div>

        <div class="flex-1 overflow-auto px-6 py-6">
          <div class="w-full max-w-4xl mx-auto">
            <div class="grid grid-cols-1 gap-4">
              <div class="rounded-2xl bg-slate-900 border border-slate-800 p-4">
                <label class="text-sm text-slate-300">Nome</label>
                <input id="person_name_input" class="mt-2 w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-700" placeholder="Digite seu nome" maxlength="40" />
                <div id="person_name_counter" class="mt-2 text-xs text-slate-400"></div>
              </div>

              <div id="panel_manual" class="rounded-2xl bg-slate-900 border border-slate-800 p-4">
                <div class="flex items-center justify-between">
                  <div class="text-lg font-semibold">Manual</div>
                  <div class="text-xs text-slate-500">Ctrl + Alt + P para alternar (admin)</div>
                </div>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="text-sm text-slate-300">Artista</label>
                    <input id="manual_artist_input" class="mt-2 w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-700" placeholder="Ex.: Imagine Dragons" maxlength="40" />
                    <div id="artist_counter" class="mt-2 text-xs text-slate-400"></div>
                  </div>
                  <div>
                    <label class="text-sm text-slate-300">Música</label>
                    <input id="manual_track_input" class="mt-2 w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-700" placeholder="Ex.: Believer" maxlength="40" />
                    <div id="track_counter" class="mt-2 text-xs text-slate-400"></div>
                  </div>
                </div>
              </div>

              <div id="panel_spotify" class="hidden rounded-2xl bg-slate-900 border border-slate-800 p-4">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                  <div>
                    <div class="text-lg font-semibold">Spotify</div>
                    <div class="text-xs text-slate-400">Teste via App Token (client_credentials). Sem login de usuário.</div>
                  </div>
                  <div class="flex gap-2 items-center">
                    <button id="spotify_login_button" class="px-4 py-2 rounded-xl bg-white text-slate-900 font-semibold" disabled>Entrar</button>
                    <button id="spotify_logout_button" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700" disabled>Sair</button>
                    <span id="spotify_auth_status" class="px-3 py-1 rounded-full border border-slate-700 text-xs text-slate-300">—</span>
                  </div>
                </div>

                <div class="mt-4">
                  <div class="flex gap-2 flex-wrap">
                    <input id="spotify_artist_query" class="flex-1 min-w-[240px] px-4 py-3 rounded-xl bg-slate-950 border border-slate-700" placeholder="Buscar artista" />
                    <button id="spotify_artist_search_button" class="px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-700" disabled>Buscar</button>
                  </div>
                  <div id="spotify_artist_results" class="mt-4 grid gap-2"></div>
                </div>

                <div class="mt-6">
                  <div id="spotify_selected_artist_label" class="text-sm text-slate-300">Nenhum artista selecionado.</div>
                  <div id="spotify_track_results" class="mt-4 grid gap-2"></div>
                </div>

                <div class="mt-6 rounded-xl bg-slate-950 border border-slate-800 p-4">
                  <div class="text-sm text-slate-300">Selecionado</div>
                  <div id="spotify_selected_values" class="mt-2 text-sm text-slate-400">Nada selecionado ainda.</div>
                </div>
              </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-center gap-3">
              <button id="form_back_button" class="px-6 py-4 rounded-2xl bg-slate-900 border border-slate-700 text-xl font-semibold">
                Voltar
              </button>
              <button id="submit_button" class="px-8 py-4 rounded-2xl bg-white text-slate-900 text-xl font-semibold" disabled>
                Enviar para impressão
              </button>
            </div>
            <div id="form_error" class="mt-4 text-center text-sm text-rose-300"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- STATUS -->
    <div id="screen_status" class="hidden h-full w-full flex items-center justify-center">
      <div class="text-center px-6">
        <div id="status_title" class="text-3xl font-semibold">Processando...</div>
        <div id="status_message" class="mt-3 text-slate-300"></div>
        <button id="status_done_button" class="mt-10 px-6 py-4 rounded-2xl bg-white text-slate-900 text-xl font-semibold hidden">
          Voltar ao início
        </button>
      </div>
    </div>

  </div>

  <script>
    (() => {
      const csrf_token = <?= json_encode($csrf_token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

      const api_create_job_url = "api/create_job.php";
      const api_health_url = "api/health.php";
      const api_get_compositor_config_url = "api/get_compositor_config.php";
      const api_compose_image_url = "api/compose_image.php";

      const idle_timeout_seconds = 60;
      let idle_seconds_left = idle_timeout_seconds;
      let idle_interval_id = null;

      let compositor_config = null;
      let preview_aspect_ratio = 1;

      let current_stream = null;
      let current_device_id = null;
      let available_video_devices = [];
      let camera_ready = false;

      let captured_photo_data_url = null;
      let entry_mode = "manual"; // manual | spotify
      let admin_unlocked = false;
      let countdown_interval_id = null;

      // Spotify (App Token)
      const spotify_api_base = "https://api.spotify.com/v1";
      const spotify_app_token_api_url = "./api/spotify_app_token.php";
      let spotify_app_access_token = null;
      let spotify_app_expires_at_ms = 0;

      let spotify_selected_artist = null;
      let spotify_selected_track = null;

      const screen_idle = document.getElementById("screen_idle");
      const screen_capture = document.getElementById("screen_capture");
      const screen_form = document.getElementById("screen_form");
      const screen_status = document.getElementById("screen_status");

      const idle_start_button = document.getElementById("idle_start_button");
      const idle_health = document.getElementById("idle_health");

      const camera_video = document.getElementById("camera_video");
      const preview_container = document.getElementById("preview_container");
      const captured_image = document.getElementById("captured_image");
      const capture_button = document.getElementById("capture_button");
      const retake_button = document.getElementById("retake_button");
      const proceed_button = document.getElementById("proceed_button");
      const switch_camera_button = document.getElementById("switch_camera_button");
      const capture_cancel_button = document.getElementById("capture_cancel_button");
      const countdown_overlay = document.getElementById("countdown_overlay");
      const idle_countdown = document.getElementById("idle_countdown");
      const capture_error = document.getElementById("capture_error");

      const entry_mode_label = document.getElementById("entry_mode_label");
      const admin_unlocked_label = document.getElementById("admin_unlocked_label");

      const person_name_input = document.getElementById("person_name_input");
      const person_name_counter = document.getElementById("person_name_counter");
      const panel_manual = document.getElementById("panel_manual");
      const panel_spotify = document.getElementById("panel_spotify");
      const manual_artist_input = document.getElementById("manual_artist_input");
      const manual_track_input = document.getElementById("manual_track_input");
      const artist_counter = document.getElementById("artist_counter");
      const track_counter = document.getElementById("track_counter");
      const form_back_button = document.getElementById("form_back_button");
      const submit_button = document.getElementById("submit_button");
      const form_error = document.getElementById("form_error");

      const spotify_login_button = document.getElementById("spotify_login_button");
      const spotify_logout_button = document.getElementById("spotify_logout_button");
      const spotify_auth_status = document.getElementById("spotify_auth_status");
      const spotify_artist_query = document.getElementById("spotify_artist_query");
      const spotify_artist_search_button = document.getElementById("spotify_artist_search_button");
      const spotify_artist_results = document.getElementById("spotify_artist_results");
      const spotify_selected_artist_label = document.getElementById("spotify_selected_artist_label");
      const spotify_track_results = document.getElementById("spotify_track_results");
      const spotify_selected_values = document.getElementById("spotify_selected_values");

      const status_title = document.getElementById("status_title");
      const status_message = document.getElementById("status_message");
      const status_done_button = document.getElementById("status_done_button");

      function setScreen(screen_name) {
        const screens = [screen_idle, screen_capture, screen_form, screen_status];
        for (const screen of screens) {
          screen.classList.add("hidden");
        }
        if (screen_name === "idle") screen_idle.classList.remove("hidden");
        if (screen_name === "capture") screen_capture.classList.remove("hidden");
        if (screen_name === "form") screen_form.classList.remove("hidden");
        if (screen_name === "status") screen_status.classList.remove("hidden");
      }

      function resetState() {
        capture_error.textContent = "";
        form_error.textContent = "";

        captured_photo_data_url = null;
        captured_image.src = "";
        captured_image.classList.add("hidden");
        camera_video.classList.remove("hidden");

        capture_button.disabled = false;
        capture_button.classList.remove("opacity-60");
        retake_button.classList.add("hidden");
        proceed_button.classList.add("hidden");

        person_name_input.value = "";
        manual_artist_input.value = "";
        manual_track_input.value = "";

        spotify_selected_artist = null;
        spotify_selected_track = null;
        spotify_artist_results.innerHTML = "";
        spotify_track_results.innerHTML = "";
        spotify_selected_artist_label.textContent = "Nenhum artista selecionado.";
        spotify_selected_values.textContent = "Nada selecionado ainda.";

        updateSubmitEnabled();
      }

      function resetIdleTimer() {
        idle_seconds_left = idle_timeout_seconds;
        idle_countdown.textContent = String(idle_seconds_left) + "s";
      }

      function startIdleTimer() {
        stopIdleTimer();
        resetIdleTimer();
        idle_interval_id = window.setInterval(() => {
          idle_seconds_left -= 1;
          if (idle_seconds_left < 0) idle_seconds_left = 0;
          idle_countdown.textContent = String(idle_seconds_left) + "s";

          if (idle_seconds_left === 0) {
            goIdle();
          }
        }, 1000);
      }

      function stopIdleTimer() {
        if (idle_interval_id !== null) {
          window.clearInterval(idle_interval_id);
          idle_interval_id = null;
        }
      }

      function goIdle() {
        stopIdleTimer();
        stopCamera();
        resetState();
        setScreen("idle");
      }

      async function fetchHealth() {
        try {
          const response = await fetch(api_health_url, {
            cache: "no-store"
          });
          const data = await response.json();
          if (data.ok) {
            idle_health.textContent = "Hotfolder OK";
          } else {
            idle_health.textContent = "Hotfolder erro: " + (data.error || "desconhecido");
          }
        } catch {
          idle_health.textContent = "Falha ao checar hotfolder";
        }
      }

      function setPreviewAspectRatio(aspect_ratio) {
        preview_aspect_ratio = aspect_ratio;
        preview_container.style.aspectRatio = String(aspect_ratio);
      }

      async function enumerateVideoDevices() {
        const devices = await navigator.mediaDevices.enumerateDevices();
        available_video_devices = devices.filter(device => device.kind === "videoinput");
      }

      async function startCamera(device_id = null) {
        capture_error.textContent = "";
        camera_ready = false;

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
          throw new Error("getUserMedia não disponível neste navegador.");
        }

        stopCamera();

        const constraints = {
          video: device_id ? {
            deviceId: {
              exact: device_id
            },
            width: {
              ideal: 1920
            },
            height: {
              ideal: 1080
            }
          } : {
            width: {
              ideal: 1920
            },
            height: {
              ideal: 1080
            }
          },
          audio: false
        };

        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        current_stream = stream;
        camera_video.srcObject = stream;

        await new Promise(resolve => {
          camera_video.onloadedmetadata = () => resolve();
        });

        camera_ready = true;
      }

      function stopCamera() {
        if (current_stream) {
          for (const track of current_stream.getTracks()) {
            track.stop();
          }
          current_stream = null;
        }
        camera_video.srcObject = null;
        camera_ready = false;
      }

      function captureCurrentFrameAsJpegDataUrl() {
        const video_width = camera_video.videoWidth || 0;
        const video_height = camera_video.videoHeight || 0;
        if (video_width === 0 || video_height === 0) {
          throw new Error("Câmera ainda não está pronta.");
        }

        const target_width = 900;
        const target_height = Math.round(target_width / preview_aspect_ratio);

        const canvas = document.createElement("canvas");
        canvas.width = target_width;
        canvas.height = target_height;

        const ctx = canvas.getContext("2d");

        const video_aspect_ratio = video_width / video_height;
        let source_x = 0;
        let source_y = 0;
        let source_w = video_width;
        let source_h = video_height;

        if (video_aspect_ratio > preview_aspect_ratio) {
          source_w = Math.round(video_height * preview_aspect_ratio);
          source_x = Math.round((video_width - source_w) / 2);
        } else {
          source_h = Math.round(video_width / preview_aspect_ratio);
          source_y = Math.round((video_height - source_h) / 2);
        }

        ctx.drawImage(camera_video, source_x, source_y, source_w, source_h, 0, 0, target_width, target_height);
        return canvas.toDataURL("image/jpeg", 0.92);
      }

      async function processPhotoRemoveBg(photoDataUrl) {

        try {

          const response = await fetch("http://localhost:5001/remove-bg", {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify({
              image: photoDataUrl,
              apply_background: true,
              output_format: "jpg"
            })
          });

          const data = await response.json();

          if (!data.ok) {
            throw new Error(data.error);
          }

          // usa imagem processada
          captured_photo_data_url = data.image;

          captured_image.src = data.image;
          captured_image.classList.remove("hidden");
          camera_video.classList.add("hidden");

          retake_button.classList.remove("hidden");
          proceed_button.classList.remove("hidden");

        } catch (err) {

          capture_error.textContent = "Erro ao remover fundo";

          // fallback: usa imagem original
          captured_image.src = photoDataUrl;
          captured_image.classList.remove("hidden");

        }

      }

      function startCaptureFlow() {
        capture_error.textContent = "";

        if (!camera_ready) {
          capture_error.textContent = "Câmera não disponível.";
          return;
        }

        capture_button.disabled = true;
        capture_button.classList.add("opacity-60");

        let seconds_left = 3;
        countdown_overlay.classList.remove("hidden");
        countdown_overlay.querySelector("div").textContent = String(seconds_left);

        if (countdown_interval_id !== null) {
          window.clearInterval(countdown_interval_id);
          countdown_interval_id = null;
        }

        countdown_interval_id = window.setInterval(() => {
          seconds_left -= 1;

          if (seconds_left > 0) {
            countdown_overlay.querySelector("div").textContent = String(seconds_left);
            return;
          }

          window.clearInterval(countdown_interval_id);
          countdown_interval_id = null;
          countdown_overlay.classList.add("hidden");

          try {
            captured_photo_data_url = captureCurrentFrameAsJpegDataUrl();
            processPhotoRemoveBg(captured_photo_data_url);
            captured_image.classList.remove("hidden");
            camera_video.classList.add("hidden");

            retake_button.classList.remove("hidden");
            proceed_button.classList.remove("hidden");

            updateSubmitEnabled();
          } catch (error) {
            capture_error.textContent = String(error && error.message ? error.message : error);
            capture_button.disabled = false;
            capture_button.classList.remove("opacity-60");
          }
        }, 1000);
      }

      function updateEntryModeUi() {
        entry_mode_label.textContent = entry_mode === "spotify" ? "Spotify" : "Manual";

        if (entry_mode === "spotify") {
          panel_manual.classList.add("hidden");
          panel_spotify.classList.remove("hidden");
        } else {
          panel_spotify.classList.add("hidden");
          panel_manual.classList.remove("hidden");
        }

        updateSubmitEnabled();
      }

      function setAdminUnlocked(value) {
        admin_unlocked = value;
        if (admin_unlocked) {
          admin_unlocked_label.classList.remove("hidden");
        } else {
          admin_unlocked_label.classList.add("hidden");
        }
      }

      function updateCounter(input_element, counter_element, max_chars) {
        const value = input_element.value || "";
        const remaining = Math.max(0, max_chars - value.length);
        counter_element.textContent = `${value.length}/${max_chars} (restam ${remaining})`;
      }

      function updateSubmitEnabled() {
        const person_name = (person_name_input.value || "").trim();
        let artist_name = "";
        let track_name = "";

        if (entry_mode === "spotify") {
          artist_name = spotify_selected_artist ? String(spotify_selected_artist.name || "") : "";
          track_name = spotify_selected_track ? String(spotify_selected_track.name || "") : "";
        } else {
          artist_name = (manual_artist_input.value || "").trim();
          track_name = (manual_track_input.value || "").trim();
        }

        const ok = person_name !== "" && artist_name !== "" && track_name !== "" && captured_photo_data_url;
        submit_button.disabled = !ok;
      }

      function getFrontPhotoBox() {
        const base_photo_box = (compositor_config && compositor_config.photo_box) ? compositor_config.photo_box : {};
        const front_side_layout = (compositor_config && compositor_config.side_layouts && compositor_config.side_layouts.front) ?
          compositor_config.side_layouts.front : {};
        const front_photo_box_override = front_side_layout.photo_box || {};

        return {
          ...base_photo_box,
          ...front_photo_box_override
        };
      }

      function getFrontTextFieldConfig(field_name) {
        const base_text_fields = (compositor_config && compositor_config.text_fields) ? compositor_config.text_fields : {};
        const base_field_config = base_text_fields[field_name] || {};

        const front_side_layout = (compositor_config && compositor_config.side_layouts && compositor_config.side_layouts.front) ?
          compositor_config.side_layouts.front : {};
        const front_text_fields = front_side_layout.text_fields || {};
        const front_field_override = front_text_fields[field_name] || {};

        return {
          ...base_field_config,
          ...front_field_override,
          box: {
            ...(base_field_config.box || {}),
            ...(front_field_override.box || {})
          }
        };
      }

      function resolveConfiguredPrintMode() {
        const explicit_print_mode = String(
          (compositor_config && (
            compositor_config.print_mode ||
            compositor_config.card_print_mode ||
            compositor_config.job_print_mode
          )) || ""
        ).trim();

        if (explicit_print_mode === "front_and_back" || explicit_print_mode === "front_back" || explicit_print_mode === "dual_side") {
          return "front_and_back";
        }

        if (explicit_print_mode === "front_only" || explicit_print_mode === "single_side") {
          return "front_only";
        }

        const has_back_side_layout = !!(compositor_config &&
          compositor_config.side_layouts &&
          compositor_config.side_layouts.back);

        return has_back_side_layout ? "front_and_back" : "front_only";
      }

      async function submitJob() {
        form_error.textContent = "";
        setScreen("status");
        status_title.textContent = "Processando...";
        status_message.textContent = "Montando imagem e criando job.";
        status_done_button.classList.add("hidden");

        const person_name = (person_name_input.value || "").trim();
        const artist_name = entry_mode === "spotify" ?
          (spotify_selected_artist ? String(spotify_selected_artist.name || "") : "") :
          (manual_artist_input.value || "").trim();
        const track_name = entry_mode === "spotify" ?
          (spotify_selected_track ? String(spotify_selected_track.name || "") : "") :
          (manual_track_input.value || "").trim();

        try {
          const compose_body = {
            csrf_token,
            preview_only: false,
            person_name,
            artist_name,
            track_name,
            photo_data_url: captured_photo_data_url
          };

          const compose_response = await fetch(api_compose_image_url, {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify(compose_body)
          });

          const compose_data = await compose_response.json();
          if (!compose_data.ok) {
            throw new Error(compose_data.error || "Falha ao montar imagem.");
          }

          const configured_print_mode = resolveConfiguredPrintMode();

          const front_composed_image_key = String(
            compose_data.front_image_key ||
            compose_data.composed_image_key ||
            ""
          );

          const back_composed_image_key = String(
            compose_data.back_image_key ||
            ""
          );

          if (!front_composed_image_key) {
            throw new Error("Resposta inválida do compositor (sem front_image_key).");
          }

          const effective_print_mode =
            configured_print_mode === "front_and_back" && back_composed_image_key !== "" ?
            "front_and_back" :
            "front_only";

          const job_body = {
            csrf_token,
            print_mode: effective_print_mode,
            front_composed_image_key,
            back_composed_image_key,
            front_image_data_url: "",
            back_image_data_url: ""
          };

          const response = await fetch(api_create_job_url, {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify(job_body)
          });

          const data = await response.json();
          if (!data.ok) {
            throw new Error(data.error || "Falha ao criar job.");
          }

          status_title.textContent = "Pronto";
          status_message.textContent = "Job criado: " + data.job_id;
          status_done_button.classList.remove("hidden");
        } catch (error) {
          status_title.textContent = "Erro";
          status_message.textContent = String(error && error.message ? error.message : error);
          status_done_button.classList.remove("hidden");
        }
      }

      async function loadCompositorConfig() {
        const response = await fetch(api_get_compositor_config_url, {
          cache: "no-store"
        });

        const data = await response.json();
        if (!data.ok) {
          throw new Error(data.error || "Falha ao carregar config do compositor.");
        }

        compositor_config = data.compositor_config;

        const front_photo_box = getFrontPhotoBox();
        const aspect_ratio = Number(front_photo_box.width) / Number(front_photo_box.height);

        if (Number.isFinite(aspect_ratio) && aspect_ratio > 0) {
          setPreviewAspectRatio(aspect_ratio);
        }

        const person_name_config = getFrontTextFieldConfig("person_name");
        const artist_name_config = getFrontTextFieldConfig("artist_name");
        const track_name_config = getFrontTextFieldConfig("track_name");

        const name_max = Number(person_name_config.max_chars || 40);
        const artist_max = Number(artist_name_config.max_chars || 40);
        const track_max = Number(track_name_config.max_chars || 40);

        person_name_input.maxLength = name_max;
        manual_artist_input.maxLength = artist_max;
        manual_track_input.maxLength = track_max;

        updateCounter(person_name_input, person_name_counter, name_max);
        updateCounter(manual_artist_input, artist_counter, artist_max);
        updateCounter(manual_track_input, track_counter, track_max);
      }

      async function fetchAndStartCamera() {
        await enumerateVideoDevices();
        current_device_id = available_video_devices[0]?.deviceId || null;
        await startCamera(current_device_id);
      }

      // UI events
      idle_start_button.addEventListener("click", async () => {
        resetState();
        try {
          await fetchAndStartCamera();
          setScreen("capture");
          startIdleTimer();
          resetIdleTimer();
        } catch (error) {
          capture_error.textContent = String(error && error.message ? error.message : error);
          setScreen("capture");
        }
      });

      capture_cancel_button.addEventListener("click", () => goIdle());

      capture_button.addEventListener("click", () => {
        resetIdleTimer();
        startCaptureFlow();
      });

      retake_button.addEventListener("click", () => {
        resetIdleTimer();
        captured_photo_data_url = null;
        captured_image.src = "";
        captured_image.classList.add("hidden");
        camera_video.classList.remove("hidden");
        capture_button.disabled = false;
        capture_button.classList.remove("opacity-60");
        retake_button.classList.add("hidden");
        proceed_button.classList.add("hidden");
        updateSubmitEnabled();
      });

      proceed_button.addEventListener("click", () => {
        stopIdleTimer();
        stopCamera();
        setScreen("form");
        updateSubmitEnabled();
      });

      switch_camera_button.addEventListener("click", async () => {
        resetIdleTimer();
        try {
          if (available_video_devices.length === 0) {
            await enumerateVideoDevices();
          }
          if (available_video_devices.length <= 1) {
            return;
          }

          const current_index = available_video_devices.findIndex(device => device.deviceId === current_device_id);
          const next_index = (current_index + 1) % available_video_devices.length;
          current_device_id = available_video_devices[next_index].deviceId;

          await startCamera(current_device_id);
        } catch (error) {
          capture_error.textContent = String(error && error.message ? error.message : error);
        }
      });

      form_back_button.addEventListener("click", async () => {
        try {
          await fetchAndStartCamera();
          setScreen("capture");
          startIdleTimer();
          resetIdleTimer();
        } catch (error) {
          form_error.textContent = String(error && error.message ? error.message : error);
        }
      });

      submit_button.addEventListener("click", () => submitJob());
      status_done_button.addEventListener("click", () => goIdle());

      person_name_input.addEventListener("input", () => {
        updateCounter(person_name_input, person_name_counter, person_name_input.maxLength);
        updateSubmitEnabled();
      });
      manual_artist_input.addEventListener("input", () => {
        updateCounter(manual_artist_input, artist_counter, manual_artist_input.maxLength);
        updateSubmitEnabled();
      });
      manual_track_input.addEventListener("input", () => {
        updateCounter(manual_track_input, track_counter, manual_track_input.maxLength);
        updateSubmitEnabled();
      });

      // Global user interaction resets idle timer (only when capturing)
      ["click", "touchstart", "mousemove", "keydown"].forEach(event_name => {
        document.addEventListener(event_name, () => {
          if (!screen_capture.classList.contains("hidden")) {
            resetIdleTimer();
          }
        }, {
          passive: true
        });
      });

      function escapeHtml(value) {
        return String(value)
          .replaceAll("&", "&amp;")
          .replaceAll("<", "&lt;")
          .replaceAll(">", "&gt;")
          .replaceAll('"', "&quot;")
          .replaceAll("'", "&#039;");
      }

      async function getSpotifyAppToken() {
        if (spotify_app_access_token && Date.now() < spotify_app_expires_at_ms - 10_000) {
          return spotify_app_access_token;
        }

        const response = await fetch(spotify_app_token_api_url, {
          cache: "no-store"
        });
        const data = await response.json();

        if (!response.ok) {
          throw new Error(`Spotify app token error (${response.status}): ${JSON.stringify(data)}`);
        }

        spotify_app_access_token = data.access_token;
        spotify_app_expires_at_ms = Date.now() + (Number(data.expires_in || 0) * 1000);
        return spotify_app_access_token;
      }

      async function spotifyApiFetch(url, options = {}) {
        const access_token = await getSpotifyAppToken();

        const response = await fetch(url, {
          ...options,
          headers: {
            ...(options.headers || {}),
            Authorization: `Bearer ${access_token}`
          }
        });

        if (!response.ok) {
          const text = await response.text();
          throw new Error(`Spotify API error (${response.status}): ${text}`);
        }

        return response.json();
      }

      function spotifyUpdateAuthUi() {
        spotify_login_button.disabled = true;
        spotify_logout_button.disabled = true;

        spotify_artist_search_button.disabled = false;
        spotify_auth_status.textContent = "App token";
        spotify_auth_status.className = "px-3 py-1 rounded-full border border-slate-700 text-xs text-emerald-300";
      }

      function spotifyRenderSelected() {
        if (!spotify_selected_artist && !spotify_selected_track) {
          spotify_selected_values.textContent = "Nada selecionado ainda.";
          return;
        }

        const artist_html = spotify_selected_artist ?
          `<div><strong>Artista:</strong> ${escapeHtml(spotify_selected_artist.name)}</div>` :
          `<div><strong>Artista:</strong> (não selecionado)</div>`;

        const track_html = spotify_selected_track ?
          `<div><strong>Música:</strong> ${escapeHtml(spotify_selected_track.name)}</div>` :
          `<div><strong>Música:</strong> (não selecionada)</div>`;

        spotify_selected_values.innerHTML = `${artist_html}${track_html}`;
      }

      function spotifyRenderArtistResults(artists) {
        spotify_artist_results.innerHTML = "";

        if (!artists.length) {
          spotify_artist_results.innerHTML = `<div class="text-sm text-slate-400">Nenhum artista encontrado.</div>`;
          return;
        }

        for (const artist of artists) {
          const image_url = artist.images?.[2]?.url || artist.images?.[1]?.url || artist.images?.[0]?.url || "";
          const item = document.createElement("div");
          item.className = "flex items-center gap-3 p-3 rounded-xl border border-slate-800 bg-slate-950";
          item.innerHTML = `
            <img alt="" src="${escapeHtml(image_url)}" class="w-12 h-12 rounded-lg object-cover bg-slate-900" />
            <div class="flex-1">
              <div class="font-semibold">${escapeHtml(artist.name)}</div>
              <div class="text-xs text-slate-400">Popularidade: ${escapeHtml(artist.popularity ?? "—")}</div>
            </div>
            <button class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700">Escolher</button>
          `;
          item.querySelector("button").addEventListener("click", () => {
            spotifySelectArtist({
              id: artist.id,
              name: artist.name
            });
          });
          spotify_artist_results.appendChild(item);
        }
      }

      function spotifyRenderTrackResults(tracks) {
        spotify_track_results.innerHTML = "";

        if (!tracks.length) {
          spotify_track_results.innerHTML = `<div class="text-sm text-slate-400">Não encontrei top tracks.</div>`;
          return;
        }

        for (const track of tracks) {
          const album_image_url = track.album?.images?.[2]?.url || track.album?.images?.[1]?.url || track.album?.images?.[0]?.url || "";
          const item = document.createElement("div");
          item.className = "flex items-center gap-3 p-3 rounded-xl border border-slate-800 bg-slate-950";
          item.innerHTML = `
            <img alt="" src="${escapeHtml(album_image_url)}" class="w-12 h-12 rounded-lg object-cover bg-slate-900" />
            <div class="flex-1">
              <div class="font-semibold">${escapeHtml(track.name)}</div>
              <div class="text-xs text-slate-400">${escapeHtml(track.album?.name || "")}</div>
            </div>
            <button class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-semibold">Escolher</button>
          `;
          item.querySelector("button").addEventListener("click", () => {
            spotifySelectTrack({
              id: track.id,
              name: track.name
            });
          });
          spotify_track_results.appendChild(item);
        }
      }

      async function spotifySearchArtist() {
        const query = spotify_artist_query.value.trim();
        spotify_artist_results.innerHTML = "";
        spotify_track_results.innerHTML = "";
        spotify_selected_artist_label.textContent = "Nenhum artista selecionado.";
        spotify_selected_artist = null;
        spotify_selected_track = null;
        spotifyRenderSelected();
        updateSubmitEnabled();

        if (!query) {
          spotify_artist_results.innerHTML = `<div class="text-sm text-slate-400">Digite um nome pra buscar.</div>`;
          return;
        }

        try {
          const url = new URL(`${spotify_api_base}/search`);
          url.searchParams.set("q", query);
          url.searchParams.set("type", "artist");
          url.searchParams.set("limit", "10");
          const response_json = await spotifyApiFetch(url.toString());
          const artists = response_json?.artists?.items || [];
          spotifyRenderArtistResults(artists);
        } catch (error) {
          spotify_artist_results.innerHTML = `<div class="text-sm text-rose-300">${escapeHtml(error.message)}</div>`;
        }
      }

      async function spotifyFetchTopTracks(artist_name) {
        try {
          const url = new URL(`${spotify_api_base}/search`);
          url.searchParams.set("q", `artist:"${artist_name}"`);
          url.searchParams.set("type", "track");
          url.searchParams.set("limit", "10");
          url.searchParams.set("market", "BR");

          const response_json = await spotifyApiFetch(url.toString());
          const tracks = response_json?.tracks?.items || [];
          spotifyRenderTrackResults(tracks);
        } catch (error) {
          spotify_track_results.innerHTML = `<div class="text-sm text-rose-300">${escapeHtml(error.message)}</div>`;
        }
      }

      function spotifySelectArtist(artist) {
        spotify_selected_artist = artist;
        spotify_selected_track = null;
        spotify_selected_artist_label.innerHTML = `Artista selecionado: <strong>${escapeHtml(artist.name)}</strong>`;
        spotify_track_results.innerHTML = `<div class="text-sm text-slate-400">Buscando músicas...</div>`;
        spotifyRenderSelected();
        updateSubmitEnabled();

        spotifyFetchTopTracks(artist.name);
      }

      function spotifySelectTrack(track) {
        spotify_selected_track = track;
        spotifyRenderSelected();
        updateSubmitEnabled();
      }

      function spotifyLogout() {
        spotify_selected_artist = null;
        spotify_selected_track = null;
        spotify_artist_results.innerHTML = "";
        spotify_track_results.innerHTML = "";
        spotify_selected_artist_label.textContent = "Nenhum artista selecionado.";
        spotify_selected_values.textContent = "Nada selecionado ainda.";
        spotifyUpdateAuthUi();
        updateSubmitEnabled();
      }

      spotify_artist_search_button.addEventListener("click", spotifySearchArtist);
      spotify_artist_query.addEventListener("keydown", (event) => {
        if (event.key === "Enter" && !spotify_artist_search_button.disabled) spotifySearchArtist();
      });

      // Admin unlock + mode toggle
      document.addEventListener("keydown", async (event) => {
        const is_toggle_shortcut = event.ctrlKey && event.altKey && (event.key === "p" || event.key === "P");
        const is_dashboard_shortcut = event.ctrlKey && event.altKey && (event.key === "d" || event.key === "D");
        if (!is_toggle_shortcut && !is_dashboard_shortcut) return;

        event.preventDefault();

        if (is_dashboard_shortcut) {
          if (!admin_unlocked) {
            alert("Destrave o admin primeiro (Ctrl + Alt + P).");
            return;
          }
          window.open("admin/index.php", "_blank");
          return;
        }

        if (admin_unlocked) {
          entry_mode = entry_mode === "manual" ? "spotify" : "manual";
          updateEntryModeUi();
          return;
        }

        const password = window.prompt("Senha admin:");
        if (!password) return;

        try {
          const response = await fetch("admin/unlock.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify({
              password
            })
          });
          const data = await response.json();
          if (!data.ok) {
            alert("Senha inválida");
            return;
          }
          setAdminUnlocked(true);
          entry_mode = entry_mode === "manual" ? "spotify" : "manual";
          updateEntryModeUi();
        } catch {
          alert("Falha ao validar senha");
        }
      });

      // Init
      setScreen("idle");
      fetchHealth();

      (async () => {
        spotifyUpdateAuthUi();

        try {
          await loadCompositorConfig();
        } catch (error) {
          idle_health.textContent = String(error && error.message ? error.message : error);
        }

        updateEntryModeUi();
      })();
    })();
  </script>
</body>

</html>
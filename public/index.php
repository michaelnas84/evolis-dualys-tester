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
</head>
<body class="h-screen bg-slate-950 text-slate-100 select-none">
  <div class="h-full w-full flex items-center justify-center">
    <div class="w-full max-w-4xl px-6">

      <!-- IDLE -->
      <div id="screen_idle" class="text-center">
        <div class="text-4xl sm:text-5xl font-semibold">Toque para começar</div>
        <div class="mt-4 text-slate-300">Foto para impressão em cartão</div>
        <button id="idle_start_button" class="mt-10 px-6 py-4 rounded-2xl bg-white text-slate-900 text-xl font-semibold">
          Iniciar
        </button>
        <div id="idle_health" class="mt-6 text-sm text-slate-400"></div>
      </div>

      <!-- MODE SELECT -->
      <div id="screen_mode" class="hidden">
        <div class="text-3xl font-semibold text-center">Escolha o modo</div>
        <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
          <button id="mode_front_only" class="p-6 rounded-2xl bg-slate-800 hover:bg-slate-700 text-left">
            <div class="text-2xl font-semibold">Só frente</div>
            <div class="mt-2 text-slate-300">Captura uma foto</div>
          </button>
          <button id="mode_front_and_back" class="p-6 rounded-2xl bg-slate-800 hover:bg-slate-700 text-left">
            <div class="text-2xl font-semibold">Frente e verso</div>
            <div class="mt-2 text-slate-300">Captura duas fotos</div>
          </button>
        </div>

        <div class="mt-8 flex justify-center">
          <button id="mode_cancel_button" class="px-5 py-3 rounded-xl bg-slate-900 border border-slate-700">
            Cancelar
          </button>
        </div>
      </div>

      <!-- CAPTURE -->
      <div id="screen_capture" class="hidden">
        <div class="flex items-center justify-between gap-4">
          <div>
            <div id="capture_title" class="text-3xl font-semibold">Capturar</div>
            <div id="capture_subtitle" class="mt-1 text-slate-300">Ajuste e tire a foto</div>
          </div>
          <div class="text-right">
            <div class="text-sm text-slate-400">Inatividade</div>
            <div id="idle_countdown" class="text-xl font-semibold">--</div>
          </div>
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
          <div class="rounded-2xl overflow-hidden bg-slate-900 border border-slate-800">
            <video id="camera_video" class="w-full h-auto" autoplay playsinline muted></video>
          </div>

          <div class="rounded-2xl bg-slate-900 border border-slate-800 p-4">
            <div class="text-sm text-slate-400">Pré-visualização (aspecto CR80)</div>
            <canvas id="preview_canvas" class="mt-3 w-full rounded-xl bg-black"></canvas>

            <div class="mt-4 flex flex-wrap gap-3">
              <button id="capture_button" class="px-5 py-3 rounded-xl bg-white text-slate-900 font-semibold">
                Capturar
              </button>
              <button id="switch_camera_button" class="px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-700">
                Trocar câmera
              </button>
              <label class="px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 cursor-pointer">
                <input id="upload_input" type="file" accept="image/*" class="hidden" />
                Enviar imagem
              </label>
              <button id="capture_cancel_button" class="px-5 py-3 rounded-xl bg-slate-900 border border-slate-700">
                Cancelar
              </button>
            </div>

            <div id="capture_error" class="mt-4 text-sm text-rose-300"></div>
          </div>
        </div>
      </div>

      <!-- REVIEW -->
      <div id="screen_review" class="hidden">
        <div class="text-3xl font-semibold text-center">Revisar</div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="rounded-2xl bg-slate-900 border border-slate-800 p-4">
            <div class="text-sm text-slate-400">Frente</div>
            <img id="front_preview_image" class="mt-3 w-full rounded-xl bg-black object-contain" alt="Frente" />
            <button id="retake_front_button" class="mt-4 w-full px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-700">
              Refazer frente
            </button>
          </div>

          <div id="back_review_panel" class="rounded-2xl bg-slate-900 border border-slate-800 p-4 hidden">
            <div class="text-sm text-slate-400">Verso</div>
            <img id="back_preview_image" class="mt-3 w-full rounded-xl bg-black object-contain" alt="Verso" />
            <button id="retake_back_button" class="mt-4 w-full px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-700">
              Refazer verso
            </button>
          </div>
        </div>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
          <button id="submit_button" class="px-6 py-4 rounded-2xl bg-white text-slate-900 text-xl font-semibold">
            Enviar para impressão
          </button>
          <button id="review_cancel_button" class="px-5 py-3 rounded-xl bg-slate-900 border border-slate-700">
            Cancelar
          </button>
        </div>

        <div id="review_error" class="mt-4 text-center text-sm text-rose-300"></div>
      </div>

      <!-- STATUS -->
      <div id="screen_status" class="hidden text-center">
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

  const card_aspect_ratio = 85.6 / 53.98;

  const idle_timeout_seconds = 60;
  let idle_seconds_left = idle_timeout_seconds;
  let idle_interval_id = null;

  let current_stream = null;
  let current_device_id = null;
  let available_video_devices = [];
  let camera_ready = false;

  let print_mode = null; // "front_only" | "front_and_back"
  let capture_side = null; // "front" | "back"
  let front_image_data_url = null;
  let back_image_data_url = null;

  const screen_idle = document.getElementById("screen_idle");
  const screen_mode = document.getElementById("screen_mode");
  const screen_capture = document.getElementById("screen_capture");
  const screen_review = document.getElementById("screen_review");
  const screen_status = document.getElementById("screen_status");

  const idle_start_button = document.getElementById("idle_start_button");
  const idle_health = document.getElementById("idle_health");

  const mode_front_only = document.getElementById("mode_front_only");
  const mode_front_and_back = document.getElementById("mode_front_and_back");
  const mode_cancel_button = document.getElementById("mode_cancel_button");

  const camera_video = document.getElementById("camera_video");
  const preview_canvas = document.getElementById("preview_canvas");
  const capture_button = document.getElementById("capture_button");
  const switch_camera_button = document.getElementById("switch_camera_button");
  const upload_input = document.getElementById("upload_input");
  const capture_cancel_button = document.getElementById("capture_cancel_button");
  const capture_title = document.getElementById("capture_title");
  const capture_subtitle = document.getElementById("capture_subtitle");
  const capture_error = document.getElementById("capture_error");
  const idle_countdown = document.getElementById("idle_countdown");

  const front_preview_image = document.getElementById("front_preview_image");
  const back_preview_image = document.getElementById("back_preview_image");
  const back_review_panel = document.getElementById("back_review_panel");
  const retake_front_button = document.getElementById("retake_front_button");
  const retake_back_button = document.getElementById("retake_back_button");
  const submit_button = document.getElementById("submit_button");
  const review_cancel_button = document.getElementById("review_cancel_button");
  const review_error = document.getElementById("review_error");

  const status_title = document.getElementById("status_title");
  const status_message = document.getElementById("status_message");
  const status_done_button = document.getElementById("status_done_button");

  function setScreen(screen_name) {
    const screens = [screen_idle, screen_mode, screen_capture, screen_review, screen_status];
    for (const screen of screens) {
      screen.classList.add("hidden");
    }
    if (screen_name === "idle") screen_idle.classList.remove("hidden");
    if (screen_name === "mode") screen_mode.classList.remove("hidden");
    if (screen_name === "capture") screen_capture.classList.remove("hidden");
    if (screen_name === "review") screen_review.classList.remove("hidden");
    if (screen_name === "status") screen_status.classList.remove("hidden");
  }

  function resetState() {
    print_mode = null;
    capture_side = null;
    front_image_data_url = null;
    back_image_data_url = null;
    review_error.textContent = "";
    capture_error.textContent = "";
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
      const response = await fetch(api_health_url, { cache: "no-store" });
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

  function setCanvasSizeForCard() {
    const target_width = 1200;
    const target_height = Math.round(target_width / card_aspect_ratio);
    preview_canvas.width = target_width;
    preview_canvas.height = target_height;
  }

  function drawPreviewFrame() {
    if (!camera_ready) return;

    const ctx = preview_canvas.getContext("2d");
    const video_width = camera_video.videoWidth || 0;
    const video_height = camera_video.videoHeight || 0;

    if (video_width === 0 || video_height === 0) return;

    const video_aspect_ratio = video_width / video_height;
    let source_x = 0;
    let source_y = 0;
    let source_w = video_width;
    let source_h = video_height;

    if (video_aspect_ratio > card_aspect_ratio) {
      // Crop width
      source_w = Math.round(video_height * card_aspect_ratio);
      source_x = Math.round((video_width - source_w) / 2);
    } else {
      // Crop height
      source_h = Math.round(video_width / card_aspect_ratio);
      source_y = Math.round((video_height - source_h) / 2);
    }

    ctx.drawImage(camera_video, source_x, source_y, source_w, source_h, 0, 0, preview_canvas.width, preview_canvas.height);
    requestAnimationFrame(drawPreviewFrame);
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
      video: device_id
        ? { deviceId: { exact: device_id }, width: { ideal: 1920 }, height: { ideal: 1080 } }
        : { width: { ideal: 1920 }, height: { ideal: 1080 } },
      audio: false
    };

    const stream = await navigator.mediaDevices.getUserMedia(constraints);
    current_stream = stream;
    camera_video.srcObject = stream;

    await new Promise(resolve => {
      camera_video.onloadedmetadata = () => resolve();
    });

    camera_ready = true;
    requestAnimationFrame(drawPreviewFrame);
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
    const data_url = preview_canvas.toDataURL("image/jpeg", 0.92);
    return data_url;
  }

  async function loadFileAsDataUrl(file) {
    return await new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result));
      reader.onerror = () => reject(new Error("Falha ao ler arquivo."));
      reader.readAsDataURL(file);
    });
  }

  function startCapture(side) {
    capture_side = side;

    const label = side === "front" ? "Frente" : "Verso";
    capture_title.textContent = "Capturar: " + label;
    capture_subtitle.textContent = "Centralize o rosto e capture";

    setCanvasSizeForCard();
    setScreen("capture");
    startIdleTimer();
    resetIdleTimer();
  }

  function showReview() {
    stopIdleTimer();
    stopCamera();

    front_preview_image.src = front_image_data_url || "";
    if (print_mode === "front_and_back") {
      back_review_panel.classList.remove("hidden");
      back_preview_image.src = back_image_data_url || "";
    } else {
      back_review_panel.classList.add("hidden");
      back_preview_image.src = "";
    }

    setScreen("review");
  }

  async function submitJob() {
    review_error.textContent = "";
    setScreen("status");
    status_title.textContent = "Enviando...";
    status_message.textContent = "Criando job no hotfolder.";
    status_done_button.classList.add("hidden");

    try {
      const body = {
        csrf_token,
        print_mode,
        front_image_data_url,
        back_image_data_url: print_mode === "front_and_back" ? back_image_data_url : ""
      };

      const response = await fetch(api_create_job_url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body)
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

  // UI events
  idle_start_button.addEventListener("click", async () => {
    resetState();
    setScreen("mode");
  });

  mode_cancel_button.addEventListener("click", () => goIdle());

  mode_front_only.addEventListener("click", async () => {
    print_mode = "front_only";
    try {
      await enumerateVideoDevices();
      current_device_id = available_video_devices[0]?.deviceId || null;
      await startCamera(current_device_id);
      startCapture("front");
    } catch (error) {
      capture_error.textContent = String(error && error.message ? error.message : error);
      setScreen("capture");
    }
  });

  mode_front_and_back.addEventListener("click", async () => {
    print_mode = "front_and_back";
    try {
      await enumerateVideoDevices();
      current_device_id = available_video_devices[0]?.deviceId || null;
      await startCamera(current_device_id);
      startCapture("front");
    } catch (error) {
      capture_error.textContent = String(error && error.message ? error.message : error);
      setScreen("capture");
    }
  });

  capture_cancel_button.addEventListener("click", () => goIdle());

  capture_button.addEventListener("click", () => {
    resetIdleTimer();

    const captured_data_url = captureCurrentFrameAsJpegDataUrl();
    if (capture_side === "front") {
      front_image_data_url = captured_data_url;
      if (print_mode === "front_and_back") {
        capture_side = "back";
        startCapture("back");
      } else {
        showReview();
      }
      return;
    }

    if (capture_side === "back") {
      back_image_data_url = captured_data_url;
      showReview();
    }
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

  upload_input.addEventListener("change", async (event) => {
    resetIdleTimer();
    const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
    if (!file) return;

    try {
      const data_url = await loadFileAsDataUrl(file);
      if (capture_side === "front") {
        front_image_data_url = data_url;
        if (print_mode === "front_and_back") {
          startCapture("back");
        } else {
          showReview();
        }
      } else {
        back_image_data_url = data_url;
        showReview();
      }
    } catch (error) {
      capture_error.textContent = String(error && error.message ? error.message : error);
    } finally {
      upload_input.value = "";
    }
  });

  retake_front_button.addEventListener("click", async () => {
    try {
      await enumerateVideoDevices();
      current_device_id = available_video_devices[0]?.deviceId || null;
      await startCamera(current_device_id);
      startCapture("front");
    } catch (error) {
      review_error.textContent = String(error && error.message ? error.message : error);
    }
  });

  retake_back_button.addEventListener("click", async () => {
    try {
      await enumerateVideoDevices();
      current_device_id = available_video_devices[0]?.deviceId || null;
      await startCamera(current_device_id);
      startCapture("back");
    } catch (error) {
      review_error.textContent = String(error && error.message ? error.message : error);
    }
  });

  submit_button.addEventListener("click", () => submitJob());
  review_cancel_button.addEventListener("click", () => goIdle());
  status_done_button.addEventListener("click", () => goIdle());

  // Global user interaction resets idle timer (only when capturing)
  ["click", "touchstart", "mousemove", "keydown"].forEach(event_name => {
    document.addEventListener(event_name, () => {
      if (!screen_capture.classList.contains("hidden")) {
        resetIdleTimer();
      }
    }, { passive: true });
  });

  // Init
  setScreen("idle");
  fetchHealth();
})();
</script>
</body>
</html>
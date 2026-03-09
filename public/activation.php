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
  <title>Card Kiosk – Exagerados</title>
  <script src="js/tailwind.js"></script>
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html,
    body {
      width: 100vw;
      height: 100vh;
      overflow: hidden;
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: #1a0030;
      touch-action: manipulation;
    }

    /* ── Screens ── */
    .screen {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.5s ease;
      overflow: hidden;
    }

    .screen.active {
      opacity: 1;
      pointer-events: all;
    }

    .bg-img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      user-select: none;
      pointer-events: none;
      display: block;
    }

    .screen-body {
      position: relative;
      z-index: 10;
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    /* Camera mirror */
    #camera_video {
      transform: scaleX(-1);
    }

    /* ── Global fade overlay ── */
    #fade_overlay {
      position: fixed;
      inset: 0;
      background: #000;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.4s ease;
      z-index: 9999;
    }

    #fade_overlay.fading {
      opacity: 1;
      pointer-events: all;
    }

    /* ── Loading spinner overlay ── */
    #loading_overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.72);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      z-index: 9998;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.35s ease;
    }

    #loading_overlay.visible {
      opacity: 1;
      pointer-events: all;
    }

    .spinner {
      width: 58px;
      height: 58px;
      border: 4px solid rgba(255, 255, 255, 0.18);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    /* ── Bouncing dots ── */
    .dots span {
      display: inline-block;
      width: 13px;
      height: 13px;
      border-radius: 50%;
      background: #fff;
      margin: 0 5px;
      animation: dot-bounce 1.2s ease-in-out infinite;
    }

    .dots span:nth-child(2) {
      animation-delay: .2s;
    }

    .dots span:nth-child(3) {
      animation-delay: .4s;
    }

    @keyframes dot-bounce {

      0%,
      60%,
      100% {
        transform: translateY(0);
        opacity: .5;
      }

      30% {
        transform: translateY(-14px);
        opacity: 1;
      }
    }

    /* ── Buttons ── */
    .btn-p {
      background: linear-gradient(135deg, #ff80c0, #c060ff);
      color: #fff;
      font-size: 20px;
      font-weight: 700;
      padding: 16px 52px;
      border-radius: 999px;
      border: none;
      cursor: pointer;
      letter-spacing: 1px;
      transition: opacity .2s, transform .12s;
      box-shadow: 0 4px 24px rgba(180, 80, 255, .45);
      white-space: nowrap;
    }

    .btn-p:disabled {
      opacity: .32;
      cursor: default;
    }

    .btn-p:not(:disabled):active {
      transform: scale(.95);
    }

    .btn-s {
      background: rgba(255, 255, 255, .12);
      color: #fff;
      font-size: 16px;
      font-weight: 600;
      padding: 12px 28px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, .25);
      cursor: pointer;
      transition: background .2s;
    }

    .btn-s:active {
      background: rgba(255, 255, 255, .24);
    }

    /* ── Field label ── */
    .flabel {
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 2.5px;
      color: rgba(255, 255, 255, .65);
      text-transform: uppercase;
      margin-bottom: 7px;
    }

    /* ── Kiosk input ── */
    .ki {
      width: 100%;
      background: rgba(255, 192, 218, .32);
      border: 1.5px solid transparent;
      border-radius: 999px;
      color: #fff;
      font-size: 19px;
      padding: 14px 22px;
      outline: none;
      caret-color: transparent;
      cursor: pointer;
      transition: border-color .2s, background .2s;
    }

    .ki.focused {
      border-color: rgba(255, 180, 230, .8);
      background: rgba(255, 192, 218, .46);
    }

    /* ── CPF display ── */
    .cpf-box {
      font-size: 38px;
      font-weight: 700;
      letter-spacing: 5px;
      color: #fff;
      text-align: center;
      background: rgba(255, 192, 218, .22);
      border: 2px solid rgba(255, 255, 255, .28);
      border-radius: 18px;
      padding: 18px 36px;
      min-width: 310px;
      cursor: pointer;
      transition: border-color .2s;
      user-select: none;
    }

    .cpf-box.focused {
      border-color: rgba(255, 200, 240, .85);
    }

    /* ── Numpad ── */
    .numpad {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(18, 6, 38, .94);
      backdrop-filter: blur(10px);
      padding: 14px 24px 26px;
      border-top: 1px solid rgba(255, 255, 255, .1);
      opacity: 0;
      pointer-events: none;
      transition: opacity .25s ease;
      z-index: 500;
    }

    .numpad.open {
      opacity: 1;
      pointer-events: all;
    }

    .np-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      max-width: 360px;
      margin: 0 auto;
    }

    .np-key {
      background: rgba(255, 255, 255, .14);
      border: 1px solid rgba(255, 255, 255, .16);
      border-bottom: 3px solid rgba(0, 0, 0, .45);
      border-radius: 14px;
      color: #fff;
      font-size: 30px;
      font-weight: 600;
      height: 72px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      user-select: none;
      transition: background .1s, transform .1s;
    }

    .np-key:active {
      background: rgba(255, 255, 255, .3);
      transform: scale(.92);
    }

    .np-key.del {
      font-size: 22px;
      background: rgba(200, 50, 50, .32);
    }

    .np-key.empty {
      background: transparent;
      border: none;
      cursor: default;
    }

    /* ── Frame selection ── */
    .frame-card {
      position: relative;
      opacity: .42;
      border: 3px solid transparent;
      border-radius: 20px;
      overflow: hidden;
      cursor: pointer;
      transform: scale(.92);
      transition:
        opacity .38s ease,
        border-color .38s ease,
        transform .38s ease,
        box-shadow .38s ease;
      background: #000;
      flex-shrink: 0;
    }

    .frame-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      pointer-events: none;
      user-select: none;
    }

    .frame-card.selected {
      opacity: 1;
      border-color: #ff80c0;
      transform: scale(1.06);
      box-shadow:
        0 0 0 4px rgba(255, 128, 192, .45),
        0 10px 36px rgba(180, 80, 255, .6);
    }

    /* ── QWERTY keyboard ── */
    .vkb {
      position: fixed;
      left: 0;
      right: 0;
      background: rgba(18, 6, 38, .96);
      backdrop-filter: blur(14px);
      padding: 8px 5px 16px;
      border-top: 1px solid rgba(255, 255, 255, .1);
      opacity: 0;
      pointer-events: none;
      transition: opacity .25s ease;
      z-index: 800;
      bottom: 0;
    }

    .vkb.open {
      opacity: 1;
      pointer-events: all;
    }

    .vkb-row {
      display: flex;
      justify-content: center;
      gap: 4px;
      margin: 3px 0;
    }

    .vk {
      background: rgba(255, 255, 255, .14);
      border: 1px solid rgba(255, 255, 255, .15);
      border-bottom: 3px solid rgba(0, 0, 0, .45);
      border-radius: 9px;
      color: #fff;
      font-size: 21px;
      font-weight: 500;
      height: 56px;
      min-width: 32px;
      padding: 0 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      user-select: none;
      transition: background .1s, transform .08s;
      flex: 1;
      max-width: 58px;
    }

    .vk:active {
      background: rgba(255, 255, 255, .3);
      transform: scale(.91);
    }

    .vk.wide {
      max-width: none;
      flex: 2;
    }

    .vk.xwide {
      max-width: none;
      flex: 5;
      font-size: 14px;
    }

    .vk.accent {
      background: rgba(180, 80, 255, .38);
    }

    .vk.del {
      font-size: 22px;
    }

    /* ── Capture countdown ── */
    #cap_cdown_wrap {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, .34);
      opacity: 0;
      pointer-events: none;
      transition: opacity .3s;
    }

    #cap_cdown_wrap.on {
      opacity: 1;
    }

    .cdown-circle {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      background: rgba(0, 0, 0, .52);
      backdrop-filter: blur(4px);
      border: 4px solid rgba(255, 255, 255, .6);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 74px;
      font-weight: 900;
      color: #fff;
    }

    /* ── Progress bar ── */
    .prog-wrap {
      width: 100%;
      max-width: 440px;
      height: 6px;
      background: rgba(255, 255, 255, .14);
      border-radius: 999px;
      overflow: hidden;
    }

    .prog-fill {
      height: 100%;
      background: linear-gradient(90deg, #ff80c0, #c060ff);
      border-radius: 999px;
      transition: width 1s linear;
    }

    /* ── Error ── */
    .err {
      color: #ff8080;
      font-size: 14px;
      text-align: center;
      min-height: 20px;
    }
  </style>
</head>

<body>

  <!-- Global overlays -->
  <div id="fade_overlay"></div>
  <div id="loading_overlay">
    <div class="spinner"></div>
    <p style="color:#fff;margin-top:16px;font-size:16px;letter-spacing:1px;">Processando foto...</p>
  </div>

  <!-- ═══════════════════════════════
     TELA 1 – IDLE
═══════════════════════════════ -->
  <div id="s_idle" class="screen active">
    <img src="assets/tela-01.png" class="bg-img" alt="" />
    <div id="idle_tap" style="position:absolute;inset:0;z-index:20;cursor:pointer;"></div>
    <div id="idle_health" style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);
    font-size:12px;color:rgba(255,255,255,.3);z-index:30;pointer-events:none;"></div>
  </div>

  <!-- ═══════════════════════════════
     TELA 2 – CPF
═══════════════════════════════ -->
  <div id="s_cpf" class="screen">
    <img src="assets/tela-05.png" class="bg-img" alt="" />
    <div class="screen-body" style="justify-content:center;padding:0 32px;gap:0;">

      <div style="text-align:center;margin-bottom:36px;">
        <p style="font-size:12px;letter-spacing:4px;color:rgba(255,255,255,.55);text-transform:uppercase;margin-bottom:6px;">VIVA OS FÃS</p>
        <p style="font-size:24px;font-weight:800;letter-spacing:2px;color:#fff;text-transform:uppercase;">DIGITE SEU CPF</p>
      </div>

      <div id="cpf_box" class="cpf-box">
        <span id="cpf_txt" style="opacity:.38;">000.000.000-00</span>
      </div>

      <div id="cpf_err" class="err" style="margin-top:14px;"></div>

      <div id="cpf_btn_area" style="margin-top:20px;display:flex;justify-content:center;
      transition:opacity .25s;min-height:58px;align-items:center;">
        <button id="cpf_btn" class="btn-p" disabled>CONTINUAR</button>
      </div>
    </div>

    <!-- Numpad -->
    <div id="numpad" class="numpad">
      <div class="np-grid">
        <div class="np-key" data-d="1">1</div>
        <div class="np-key" data-d="2">2</div>
        <div class="np-key" data-d="3">3</div>
        <div class="np-key" data-d="4">4</div>
        <div class="np-key" data-d="5">5</div>
        <div class="np-key" data-d="6">6</div>
        <div class="np-key" data-d="7">7</div>
        <div class="np-key" data-d="8">8</div>
        <div class="np-key" data-d="9">9</div>
        <div class="np-key empty"></div>
        <div class="np-key" data-d="0">0</div>
        <div class="np-key del" id="np_del">⌫</div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════
     TELA 3 – SELEÇÃO DE FRAME
═══════════════════════════════ -->
  <div id="s_frame" class="screen">
    <img src="assets/tela-04.png" class="bg-img" alt="" />
    <div class="screen-body" style="justify-content:flex-start;padding:0;">

      <div style="text-align:center;padding:28px 24px 16px;">
        <p style="font-size:12px;letter-spacing:4px;color:rgba(255,255,255,.55);text-transform:uppercase;margin-bottom:5px;">VIVA OS FÃS</p>
        <p style="font-size:22px;font-weight:800;letter-spacing:1.5px;color:#fff;text-transform:uppercase;">ESCOLHA SEU FRAME</p>
      </div>

      <!-- Frame cards -->
      <div style="flex:1;display:flex;align-items:center;justify-content:center;
      width:100%;padding:0 18px;gap:14px;" id="frame_grid">
        <div class="frame-card" data-frame="frame_01_front" style="width:calc(33.3% - 10px);aspect-ratio:2/3;">
          <img src="frames/frame_01_front.png" alt="Frame 1" />
        </div>
        <div class="frame-card" data-frame="frame_02_front" style="width:calc(33.3% - 10px);aspect-ratio:2/3;">
          <img src="frames/frame_02_front.png" alt="Frame 2" />
        </div>
        <div class="frame-card" data-frame="frame_03_front" style="width:calc(33.3% - 10px);aspect-ratio:2/3;">
          <img src="frames/frame_03_front.png" alt="Frame 3" />
        </div>
      </div>

      <div style="padding:20px 24px 36px;width:100%;display:flex;justify-content:center;">
        <button id="frame_btn" class="btn-p" disabled>CONTINUAR</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════
     TELA 4 – FORMULÁRIO
═══════════════════════════════ -->
  <div id="s_form" class="screen">
    <img src="assets/tela-04.png" class="bg-img" alt="" />
    <div class="screen-body" style="padding:0;overflow:hidden;">

      <div style="text-align:center;padding:28px 24px 14px;flex-shrink:0;">
        <p style="font-size:12px;letter-spacing:4px;color:rgba(255,255,255,.55);text-transform:uppercase;margin-bottom:5px;">VIVA OS FÃS</p>
        <p style="font-size:20px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;">PREENCHA OS DADOS</p>
        <p style="font-size:20px;font-weight:800;letter-spacing:1px;color:#fff;text-transform:uppercase;">PARA PARTICIPAR</p>
      </div>

      <!-- Fields -->
      <div style="width:100%;max-width:500px;padding:0 24px;flex-shrink:0;">
        <div style="margin-bottom:13px;">
          <div class="flabel">NOME</div>
          <input id="f_name" class="ki" readonly autocomplete="off" maxlength="40" />
        </div>
        <div style="margin-bottom:13px;">
          <div class="flabel">FANDOM</div>
          <input id="f_fandom" class="ki" readonly autocomplete="off" maxlength="40" />
        </div>
        <div style="margin-bottom:13px;">
          <div class="flabel">VIVO OUVINDO</div>
          <input id="f_track" class="ki" readonly autocomplete="off" maxlength="40" />
        </div>
        <div id="form_err" class="err" style="margin-top:6px;"></div>
      </div>

      <!-- Button -->
      <div id="form_btn_area" style="padding:14px 24px 0;width:100%;
      display:flex;justify-content:center;transition:opacity .25s;
      min-height:60px;align-items:center;flex-shrink:0;">
        <button id="form_btn" class="btn-p" disabled>CONTINUAR</button>
      </div>

      <!-- Spacer so layout shifts up when VKB opens -->
      <div id="vkb_spacer" style="flex-shrink:0;height:0;transition:height .25s ease;"></div>
    </div>

    <!-- QWERTY keyboard (fixed to bottom of viewport) -->
    <div id="vkb" class="vkb">
      <div id="vkb_rows"></div>
    </div>
  </div>

  <!-- ═══════════════════════════════
     TELA 5 – CAPTURA
═══════════════════════════════ -->
  <div id="s_capture" class="screen">
    <img src="assets/tela-02.png" class="bg-img" alt="" />
    <div class="screen-body" style="justify-content:flex-start;">

      <div style="text-align:center;padding:26px 24px 0;position:relative;z-index:10;flex-shrink:0;">
        <p style="font-size:30px;font-weight:900;letter-spacing:7px;color:#fff;
        text-transform:uppercase;text-shadow:0 2px 14px rgba(0,0,0,.5);">SORRIA!</p>
      </div>

      <div style="flex:1;display:flex;align-items:center;justify-content:center;
      width:100%;padding:10px 24px;position:relative;z-index:10;">
        <div id="cap_box" style="position:relative;width:100%;max-width:390px;
        border-radius:24px;overflow:hidden;background:#111;aspect-ratio:3/4;">
          <video id="camera_video" style="position:absolute;inset:0;width:100%;height:100%;
          object-fit:cover;" autoplay playsinline muted></video>
          <img id="cap_img" style="position:absolute;inset:0;width:100%;height:100%;
          object-fit:cover;display:none;" alt="" />
          <div id="cap_cdown_wrap">
            <div class="cdown-circle" id="cap_cdown_num">3</div>
          </div>
        </div>
      </div>

      <div style="padding:0 24px 28px;z-index:10;position:relative;width:100%;flex-shrink:0;">
        <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">
          <button id="cap_shoot_btn" class="btn-p">TIRAR FOTO</button>
          <button id="cap_retake_btn" class="btn-s" style="display:none;">REFAZER</button>
          <button id="cap_proceed_btn" class="btn-p" style="display:none;">CONTINUAR</button>
          <button id="cap_switch_btn" class="btn-s" style="font-size:14px;padding:10px 18px;">↻ Câmera</button>
          <button id="cap_cancel_btn" class="btn-s" style="font-size:14px;padding:10px 18px;">Cancelar</button>
        </div>
        <div id="cap_err" class="err" style="margin-top:10px;"></div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════
     TELA 6 – AGUARDANDO
═══════════════════════════════ -->
  <div id="s_waiting" class="screen">
    <img src="assets/tela-03.png" class="bg-img" alt="" />
    <div class="screen-body" style="justify-content:flex-start;padding-top:175px;">
      <div style="text-align:center;z-index:10;position:relative;padding:0 24px;">
        <p style="font-size:33px;font-weight:900;letter-spacing:4px;color:#fff;
        text-transform:uppercase;text-shadow:0 2px 18px rgba(0,0,0,.5);">AGUARDE</p>
        <p style="font-size:33px;font-weight:900;letter-spacing:4px;color:#fff;
        text-transform:uppercase;text-shadow:0 2px 18px rgba(0,0,0,.5);">IMPRIMINDO CARTÃO</p>
        <div class="dots" style="margin-top:30px;"><span></span><span></span><span></span></div>
        <div style="margin-top:30px;">
          <div class="prog-wrap">
            <div class="prog-fill" id="prog_fill" style="width:0%"></div>
          </div>
          <p id="prog_label" style="color:rgba(255,255,255,.45);font-size:13px;margin-top:8px;"></p>
        </div>
        <div id="wait_msg" style="color:rgba(255,255,255,.55);font-size:14px;margin-top:14px;"></div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════
     TELA 7 – PRONTO
═══════════════════════════════ -->
  <div id="s_done" class="screen">
    <img src="assets/tela-06.png" class="bg-img" alt="" />
    <div id="done_tap" style="position:absolute;inset:0;z-index:20;cursor:pointer;"></div>
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
    text-align:center;z-index:15;pointer-events:none;">
      <p style="font-size:32px;font-weight:900;letter-spacing:3px;color:#fff;
      text-shadow:0 2px 18px rgba(0,0,0,.6);text-transform:uppercase;">CARTÃO PRONTO</p>
      <p style="font-size:32px;font-weight:900;letter-spacing:3px;color:#fff;
      text-shadow:0 2px 18px rgba(0,0,0,.6);text-transform:uppercase;">OBRIGADO!</p>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════ -->
  <script>
    (() => {
      'use strict';

      /* ── Config ───────────────────────────────────────── */
      const CSRF = <?= json_encode($csrf_token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const API_JOB = "api/create_job.php";
      const API_HEALTH = "api/health.php";
      const API_CFG = "api/get_compositor_config.php";
      const API_COMPOSE = "api/compose_image.php";
      const API_CPF = "api/validate_cpf.php";
      const API_BG = "http://localhost:5001/remove-bg";
      const WAIT_SECS = 48; // ← tempo de espera em segundos (editável)

      /* ── DOM helpers ──────────────────────────────────── */
      const $ = id => document.getElementById(id);

      const screens = {
        idle: $("s_idle"),
        cpf: $("s_cpf"),
        frame: $("s_frame"),
        form: $("s_form"),
        capture: $("s_capture"),
        waiting: $("s_waiting"),
        done: $("s_done"),
      };

      const fade_ovl = $("fade_overlay");
      const load_ovl = $("loading_overlay");

      /* idle */
      const idle_tap = $("idle_tap");
      const idle_health = $("idle_health");

      /* cpf */
      const cpf_box = $("cpf_box");
      const cpf_txt = $("cpf_txt");
      const cpf_btn = $("cpf_btn");
      const cpf_err = $("cpf_err");
      const numpad = $("numpad");
      const np_del = $("np_del");
      const cpf_barea = $("cpf_btn_area");

      /* frame */
      const frame_cards = Array.from(document.querySelectorAll(".frame-card"));
      const frame_btn = $("frame_btn");

      /* form */
      const f_name = $("f_name");
      const f_fandom = $("f_fandom");
      const f_track = $("f_track");
      const form_btn = $("form_btn");
      const form_err = $("form_err");
      const vkb = $("vkb");
      const form_barea = $("form_btn_area");
      const vkb_spacer = $("vkb_spacer");

      /* capture */
      const cam_video = $("camera_video");
      const cap_img = $("cap_img");
      const cap_box = $("cap_box");
      const cap_cdown = $("cap_cdown_wrap");
      const cap_cnum = $("cap_cdown_num");
      const cap_shoot = $("cap_shoot_btn");
      const cap_retake = $("cap_retake_btn");
      const cap_proceed = $("cap_proceed_btn");
      const cap_switch = $("cap_switch_btn");
      const cap_cancel = $("cap_cancel_btn");
      const cap_err = $("cap_err");

      /* waiting */
      const prog_fill = $("prog_fill");
      const prog_label = $("prog_label");
      const wait_msg = $("wait_msg");

      /* done */
      const done_tap = $("done_tap");

      /* ── State ────────────────────────────────────────── */
      let cur_screen = "idle";
      let cpf_digits = "";
      let selected_frame = null; // e.g. "frame_01_front"
      let active_inp = null;
      let vkb_shift = false;
      let vkb_mode = "alpha"; // alpha | num_sym
      let cam_stream = null;
      let cam_devid = null;
      let cam_devs = [];
      let cam_ready = false;
      let photo_url = null;
      let cdown_tid = null;
      let wait_tid = null;
      let comp_cfg = null;
      let prev_ar = 3 / 4;

      /* ── Screen transition ────────────────────────────── */
      function showScreen(name) {
        return new Promise(res => {
          fade_ovl.classList.add("fading");
          setTimeout(() => {
            Object.values(screens).forEach(s => s.classList.remove("active"));
            screens[name].classList.add("active");
            cur_screen = name;
            fade_ovl.classList.remove("fading");
            setTimeout(res, 450);
          }, 400);
        });
      }

      /* ── Loading ──────────────────────────────────────── */
      const showLoad = () => load_ovl.classList.add("visible");
      const hideLoad = () => load_ovl.classList.remove("visible");

      /* ── Health check ─────────────────────────────────── */
      async function fetchHealth() {
        try {
          const r = await fetch(API_HEALTH, {
            cache: "no-store"
          });
          const d = await r.json();
          idle_health.textContent = d.ok ? "Hotfolder OK" : "Erro: " + (d.error || "?");
        } catch {
          idle_health.textContent = "";
        }
      }

      /* ════════════════════════════════════════════════════
         TELA CPF
      ════════════════════════════════════════════════════ */
      function fmtCpf(d) {
        const s = d.padEnd(11, " ");
        return `${s.slice(0,3)}.${s.slice(3,6)}.${s.slice(6,9)}-${s.slice(9,11)}`.trimEnd();
      }

      function renderCpf() {
        if (cpf_digits.length === 0) {
          cpf_txt.textContent = "000.000.000-00";
          cpf_txt.style.opacity = ".38";
        } else {
          cpf_txt.textContent = fmtCpf(cpf_digits);
          cpf_txt.style.opacity = "1";
        }
        cpf_btn.disabled = cpf_digits.length < 11;
      }

      function openNumpad() {
        numpad.classList.add("open");
        cpf_box.classList.add("focused");
        cpf_barea.style.opacity = "0";
        cpf_barea.style.pointerEvents = "none";
      }

      function closeNumpad() {
        numpad.classList.remove("open");
        cpf_box.classList.remove("focused");
        cpf_barea.style.opacity = "1";
        cpf_barea.style.pointerEvents = "all";
      }

      cpf_box.addEventListener("click", () =>
        numpad.classList.contains("open") ? closeNumpad() : openNumpad()
      );

      document.addEventListener("click", e => {
        if (cur_screen !== "cpf") return;
        if (!numpad.contains(e.target) && !cpf_box.contains(e.target)) closeNumpad();
      });

      numpad.querySelectorAll(".np-key[data-d]").forEach(k => {
        k.addEventListener("click", e => {
          e.stopPropagation();
          if (cpf_digits.length >= 11) return;
          cpf_digits += k.dataset.d;
          renderCpf();
        });
      });

      np_del.addEventListener("click", e => {
        e.stopPropagation();
        cpf_digits = cpf_digits.slice(0, -1);
        renderCpf();
      });

      cpf_btn.addEventListener("click", async () => {
        cpf_err.textContent = "";
        closeNumpad();
        showLoad();
        try {
          const r = await fetch(API_CPF, {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify({
              csrf_token: CSRF,
              cpf: cpf_digits
            })
          });
          const d = await r.json();
          hideLoad();
          if (!d.ok) {
            cpf_err.textContent = d.error || "CPF inválido ou já utilizado.";
            return;
          }
          await showScreen("frame");
        } catch {
          hideLoad();
          cpf_err.textContent = "Erro ao validar CPF.";
        }
      });

      /* ════════════════════════════════════════════════════
         TELA FRAME
      ════════════════════════════════════════════════════ */
      frame_cards.forEach(card => {
        card.addEventListener("click", () => {
          frame_cards.forEach(c => c.classList.remove("selected"));
          card.classList.add("selected");
          selected_frame = card.dataset.frame;
          frame_btn.disabled = false;
        });
      });

      frame_btn.addEventListener("click", async () => {
        await showScreen("form");
      });

      /* ════════════════════════════════════════════════════
         TELA FORMULÁRIO – teclado QWERTY
      ════════════════════════════════════════════════════ */
      const VKB_ALPHA = [
        ["q", "w", "e", "r", "t", "y", "u", "i", "o", "p"],
        ["a", "s", "d", "f", "g", "h", "j", "k", "l"],
        ["SHIFT", "z", "x", "c", "v", "b", "n", "m", "⌫"],
        ["123", "SPACE", "@", ".", "OK"],
      ];
      const VKB_NUM = [
        ["1", "2", "3", "4", "5", "6", "7", "8", "9", "0"],
        ["-", "/", ":", ";", "(", ")", "$", "&", "@", '"'],
        ["ABC", ".", ",", "?", "!", "'", "⌫"],
        ["ABC", "SPACE", ".", ",", "OK"],
      ];

      function buildVkb() {
        const rows = vkb_mode === "alpha" ? VKB_ALPHA : VKB_NUM;
        const c = $("vkb_rows");
        c.innerHTML = "";
        rows.forEach(row => {
          const rd = document.createElement("div");
          rd.className = "vkb-row";
          row.forEach(k => {
            const b = document.createElement("button");
            b.className = "vk";
            if (k === "SPACE") {
              b.className += " xwide";
              b.textContent = "espaço";
            } else if (k === "SHIFT") {
              b.className += " wide accent";
              b.textContent = vkb_shift ? "⬆" : "⇧";
            } else if (k === "ABC" || k === "123") {
              b.className += " wide accent";
              b.textContent = k;
            } else if (k === "OK") {
              b.className += " wide accent";
              b.textContent = "OK ✓";
            } else if (k === "⌫") {
              b.className += " wide del";
              b.textContent = "⌫";
            } else {
              b.textContent = (vkb_shift && vkb_mode === "alpha") ? k.toUpperCase() : k;
            }
            b.addEventListener("mousedown", e => e.preventDefault());
            b.addEventListener("touchstart", e => e.preventDefault(), {
              passive: false
            });
            b.addEventListener("click", () => vkbKey(k));
            rd.appendChild(b);
          });
          c.appendChild(rd);
        });
      }

      function vkbKey(k) {
        if (!active_inp) return;
        if (k === "SHIFT") {
          vkb_shift = !vkb_shift;
          buildVkb();
          return;
        }
        if (k === "123") {
          vkb_mode = "num_sym";
          buildVkb();
          return;
        }
        if (k === "ABC") {
          vkb_mode = "alpha";
          buildVkb();
          return;
        }
        if (k === "OK") {
          closeVkb();
          return;
        }
        if (k === "⌫") {
          active_inp.value = active_inp.value.slice(0, -1);
          active_inp.dispatchEvent(new Event("input"));
          return;
        }
        const ch = k === "SPACE" ? " " :
          (vkb_shift && vkb_mode === "alpha") ? k.toUpperCase() : k;
        if (active_inp.value.length < (active_inp.maxLength || 40)) {
          active_inp.value += ch;
          active_inp.dispatchEvent(new Event("input"));
        }
        if (vkb_shift && k !== "SHIFT") {
          vkb_shift = false;
          buildVkb();
        }
      }

      // VKB height constant (4 rows × 62px + padding)
      const VKB_H = 292;

      function openVkb(inp) {
        active_inp = inp;
        inp.classList.add("focused");
        // Push the form content up so the focused input stays visible above keyboard
        vkb_spacer.style.height = VKB_H + "px";
        // Hide button while typing
        form_barea.style.opacity = "0";
        form_barea.style.pointerEvents = "none";
        vkb.classList.add("open");
        buildVkb();
        // Scroll focused input into view after spacer expands
        setTimeout(() => inp.scrollIntoView({
          block: "nearest",
          behavior: "smooth"
        }), 50);
      }

      function closeVkb() {
        if (active_inp) active_inp.classList.remove("focused");
        active_inp = null;
        vkb.classList.remove("open");
        vkb_spacer.style.height = "0";
        form_barea.style.opacity = "1";
        form_barea.style.pointerEvents = "all";
      }

      [f_name, f_fandom, f_track].forEach(inp => {
        inp.addEventListener("click", e => {
          e.stopPropagation();
          openVkb(inp);
        });
        inp.addEventListener("input", checkFormReady);
      });

      document.addEventListener("click", e => {
        if (cur_screen !== "form") return;
        const inVkb = vkb.contains(e.target);
        const inInput = [f_name, f_fandom, f_track].includes(e.target);
        if (!inVkb && !inInput) closeVkb();
      });

      function checkFormReady() {
        form_btn.disabled = !(
          f_name.value.trim() && f_fandom.value.trim() && f_track.value.trim()
        );
      }

      form_btn.addEventListener("click", async () => {
        closeVkb();
        await startCaptureScreen();
      });

      /* ════════════════════════════════════════════════════
         TELA CAPTURA
      ════════════════════════════════════════════════════ */
      async function startCaptureScreen() {
        cap_err.textContent = "";
        resetCapture();
        try {
          await enumDevices();
          cam_devid = cam_devs[0]?.deviceId || null;
          await startCam(cam_devid);
        } catch (e) {
          cap_err.textContent = String(e.message || e);
        }
        await showScreen("capture");
      }

      async function enumDevices() {
        const ds = await navigator.mediaDevices.enumerateDevices();
        cam_devs = ds.filter(d => d.kind === "videoinput");
      }

      async function startCam(devid) {
        stopCam();
        cam_ready = false;
        if (!navigator.mediaDevices?.getUserMedia) throw new Error("getUserMedia indisponível.");
        const constraints = {
          video: devid ?
            {
              deviceId: {
                exact: devid
              },
              width: {
                ideal: 1920
              },
              height: {
                ideal: 1080
              }
            } :
            {
              width: {
                ideal: 1920
              },
              height: {
                ideal: 1080
              }
            },
          audio: false
        };
        const s = await navigator.mediaDevices.getUserMedia(constraints);
        cam_stream = s;
        cam_video.srcObject = s;
        await new Promise(res => {
          cam_video.onloadedmetadata = () => res();
        });
        cam_ready = true;
      }

      function stopCam() {
        cam_stream?.getTracks().forEach(t => t.stop());
        cam_stream = null;
        cam_video.srcObject = null;
        cam_ready = false;
      }

      function resetCapture() {
        photo_url = null;
        cap_img.src = "";
        cap_img.style.display = "none";
        cam_video.style.display = "";
        cap_shoot.style.display = "";
        cap_shoot.disabled = false;
        cap_retake.style.display = "none";
        cap_proceed.style.display = "none";
        cap_cdown.classList.remove("on");
        cap_err.textContent = "";
      }

      function snapFrame() {
        if (!cam_ready) throw new Error("Câmera não disponível.");
        const vw = cam_video.videoWidth,
          vh = cam_video.videoHeight;
        if (!vw || !vh) throw new Error("Câmera não está pronta.");
        const tw = 900,
          th = Math.round(tw / prev_ar);
        const cv = document.createElement("canvas");
        cv.width = tw;
        cv.height = th;
        const ctx = cv.getContext("2d");
        const va = vw / vh;
        let sx = 0,
          sy = 0,
          sw = vw,
          sh = vh;
        if (va > prev_ar) {
          sw = Math.round(vh * prev_ar);
          sx = Math.round((vw - sw) / 2);
        } else {
          sh = Math.round(vw / prev_ar);
          sy = Math.round((vh - sh) / 2);
        }
        ctx.drawImage(cam_video, sx, sy, sw, sh, 0, 0, tw, th);
        return cv.toDataURL("image/jpeg", 0.92);
      }

      async function removeBg(dataUrl) {
        const r = await fetch(API_BG, {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            image: dataUrl,
            apply_background: true,
            output_format: "jpg"
          })
        });
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || "BG removal failed");
        return d.image;
      }

      cap_shoot.addEventListener("click", () => {
        if (!cam_ready) {
          cap_err.textContent = "Câmera não disponível.";
          return;
        }
        cap_shoot.disabled = true;
        let secs = 3;
        cap_cdown.classList.add("on");
        cap_cnum.textContent = secs;
        if (cdown_tid) clearInterval(cdown_tid);
        cdown_tid = setInterval(async () => {
          secs--;
          if (secs > 0) {
            cap_cnum.textContent = secs;
            return;
          }
          clearInterval(cdown_tid);
          cdown_tid = null;
          cap_cdown.classList.remove("on");
          try {
            const raw = snapFrame();
            cap_img.src = raw;
            cap_img.style.display = "";
            cam_video.style.display = "none";
            photo_url = raw;
            showLoad();
            try {
              const proc = await removeBg(raw);
              photo_url = proc;
              cap_img.src = proc;
            } catch {
              /* fallback to raw */ }
            hideLoad();
            cap_shoot.style.display = "none";
            cap_retake.style.display = "";
            cap_proceed.style.display = "";
          } catch (e) {
            cap_err.textContent = String(e.message || e);
            cap_shoot.disabled = false;
          }
        }, 1000);
      });

      cap_retake.addEventListener("click", () => {
        resetCapture();
        cam_video.style.display = "";
        cap_img.style.display = "none";
      });

      cap_proceed.addEventListener("click", () => {
        stopCam();
        submitAndWait();
      });

      cap_switch.addEventListener("click", async () => {
        try {
          if (!cam_devs.length) await enumDevices();
          if (cam_devs.length <= 1) return;
          const idx = cam_devs.findIndex(d => d.deviceId === cam_devid);
          cam_devid = cam_devs[(idx + 1) % cam_devs.length].deviceId;
          await startCam(cam_devid);
        } catch (e) {
          cap_err.textContent = String(e.message || e);
        }
      });

      cap_cancel.addEventListener("click", goIdle);

      /* ════════════════════════════════════════════════════
         SUBMIT + AGUARDAR
      ════════════════════════════════════════════════════ */
      async function submitAndWait() {
        await showScreen("waiting");
        startWait();

        const person_name = f_name.value.trim();
        const artist_name = f_fandom.value.trim();
        const track_name = f_track.value.trim();

        wait_msg.textContent = "Montando imagem...";

        try {
          const cr = await fetch(API_COMPOSE, {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify({
              csrf_token: CSRF,
              preview_only: false,
              person_name,
              artist_name,
              track_name,
              photo_data_url: photo_url,
              cpf: cpf_digits, // ← CPF
              frame_name: selected_frame // ← frame selecionado
            })
          });
          const cd = await cr.json();
          if (!cd.ok) throw new Error(cd.error || "Falha ao montar imagem.");

          const fk = String(cd.front_image_key || cd.composed_image_key || "");
          const bk = String(cd.back_image_key || "");
          if (!fk) throw new Error("Sem front_image_key.");

          wait_msg.textContent = "Enviando para impressão...";

          const jr = await fetch(API_JOB, {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify({
              csrf_token: CSRF,
              print_mode: bk ? "front_and_back" : "front_only",
              front_composed_image_key: fk,
              back_composed_image_key: bk,
              front_image_data_url: "",
              back_image_data_url: "",
              cpf: cpf_digits, // ← CPF
              frame_name: selected_frame // ← frame selecionado
            })
          });
          const jd = await jr.json();
          if (!jd.ok) throw new Error(jd.error || "Falha ao criar job.");
          wait_msg.textContent = "Job criado: " + jd.job_id;
        } catch (e) {
          wait_msg.textContent = "Erro: " + String(e.message || e);
        }
      }

      function startWait() {
        if (wait_tid) clearInterval(wait_tid);
        let el = 0;
        prog_fill.style.width = "0%";
        prog_label.textContent = `0 / ${WAIT_SECS}s`;
        wait_tid = setInterval(async () => {
          el++;
          const pct = Math.min(100, (el / WAIT_SECS) * 100);
          prog_fill.style.width = pct + "%";
          prog_label.textContent = `${el} / ${WAIT_SECS}s`;
          if (el >= WAIT_SECS) {
            clearInterval(wait_tid);
            wait_tid = null;
            await showScreen("done");
          }
        }, 1000);
      }

      /* ════════════════════════════════════════════════════
         IDLE / DONE / RESET
      ════════════════════════════════════════════════════ */
      async function goIdle() {
        stopCam();
        if (cdown_tid) {
          clearInterval(cdown_tid);
          cdown_tid = null;
        }
        if (wait_tid) {
          clearInterval(wait_tid);
          wait_tid = null;
        }

        /* reset CPF */
        cpf_digits = "";
        renderCpf();
        cpf_err.textContent = "";
        closeNumpad();

        /* reset frame */
        selected_frame = null;
        frame_cards.forEach(c => c.classList.remove("selected"));
        frame_btn.disabled = true;

        /* reset form */
        f_name.value = "";
        f_fandom.value = "";
        f_track.value = "";
        form_err.textContent = "";
        form_btn.disabled = true;
        closeVkb();

        /* reset capture */
        photo_url = null;
        resetCapture();

        await showScreen("idle");
      }

      idle_tap.addEventListener("click", async () => {
        renderCpf();
        await showScreen("cpf");
      });

      done_tap.addEventListener("click", goIdle);

      /* ════════════════════════════════════════════════════
         COMPOSITOR CONFIG
      ════════════════════════════════════════════════════ */
      async function loadCfg() {
        try {
          const r = await fetch(API_CFG, {
            cache: "no-store"
          });
          const d = await r.json();
          if (!d.ok) return;
          comp_cfg = d.compositor_config;
          const pb = comp_cfg?.side_layouts?.front?.photo_box || comp_cfg?.photo_box || {};
          const ar = Number(pb.width) / Number(pb.height);
          if (Number.isFinite(ar) && ar > 0) {
            prev_ar = ar;
            cap_box.style.aspectRatio = String(ar);
          }
          const tf = n => ({
            ...(comp_cfg?.text_fields?.[n] || {}),
            ...(comp_cfg?.side_layouts?.front?.text_fields?.[n] || {})
          });
          f_name.maxLength = Number(tf("person_name").max_chars || 40);
          f_fandom.maxLength = Number(tf("artist_name").max_chars || 40);
          f_track.maxLength = Number(tf("track_name").max_chars || 40);
        } catch {
          /* ignore */ }
      }

      /* ── Admin shortcut ──────────────────────────────── */
      let admin_ok = false;
      document.addEventListener("keydown", async e => {
        const isP = e.ctrlKey && e.altKey && (e.key === "p" || e.key === "P");
        const isD = e.ctrlKey && e.altKey && (e.key === "d" || e.key === "D");
        if (!isP && !isD) return;
        e.preventDefault();
        if (isD) {
          if (!admin_ok) {
            alert("Destrave o admin primeiro.");
            return;
          }
          window.open("admin/index.php", "_blank");
          return;
        }
        if (admin_ok) return;
        const pw = window.prompt("Senha admin:");
        if (!pw) return;
        try {
          const r = await fetch("admin/unlock.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify({
              password: pw
            })
          });
          const d = await r.json();
          if (!d.ok) {
            alert("Senha inválida");
            return;
          }
          admin_ok = true;
        } catch {
          alert("Falha ao validar senha");
        }
      });

      /* ── Init ────────────────────────────────────────── */
      buildVkb();
      fetchHealth();
      loadCfg();

    })();
  </script>
</body>

</html>
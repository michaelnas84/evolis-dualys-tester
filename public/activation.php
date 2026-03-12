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
  <title>Card Kiosk - Exagerados</title>
  <script src="js/tailwind.js"></script>
  <style>
    @font-face {
      font-family: 'ExageradosDisplay';
      src:
        url('fonts/ExageradosDisplay.woff2') format('woff2'),
        url('fonts/ExageradosDisplay.otf') format('opentype');
      font-display: swap;
    }

    :root {
      --brand-bg-top: #2b0056;
      --brand-bg-mid: #7c3aa7;
      --brand-bg-bottom: #ffb1cf;
      --brand-white: #ffffff;
      --brand-soft-white: rgba(255, 255, 255, 0.82);
      --brand-purple: #4a116f;
      --brand-purple-strong: #34104f;
      --brand-glow: rgba(255, 181, 226, 0.45);
      --brand-shadow: 0 20px 60px rgba(53, 0, 84, 0.38);
      --brand-font-ui: 'ExageradosDisplay', 'Segoe UI', system-ui, sans-serif;
    }

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
      font-family: var(--brand-font-ui);
      background: var(--brand-bg-top);
      touch-action: manipulation;
    }

    body {
      color: var(--brand-white);
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
      object-position: center top;
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

    .brand-backdrop {
      position: absolute;
      inset: 0;
      overflow: hidden;
      background:
        radial-gradient(circle at 50% 10%, rgba(255, 194, 233, 0.18), transparent 22%),
        radial-gradient(circle at 10% 78%, rgba(255, 220, 238, 0.5), transparent 18%),
        radial-gradient(circle at 90% 82%, rgba(255, 210, 234, 0.52), transparent 18%),
        linear-gradient(180deg, var(--brand-bg-top) 0%, var(--brand-bg-mid) 56%, var(--brand-bg-bottom) 100%);
    }

    .brand-backdrop::before,
    .brand-backdrop::after {
      content: "";
      position: absolute;
      inset: auto;
      border-radius: 999px;
      filter: blur(12px);
      opacity: 0.8;
      pointer-events: none;
    }

    .brand-backdrop::before {
      left: -12%;
      bottom: -8%;
      width: 44%;
      height: 18%;
      background: radial-gradient(circle at center, rgba(255, 218, 236, 0.95), rgba(255, 193, 221, 0.22) 60%, transparent 72%);
    }

    .brand-backdrop::after {
      right: -10%;
      bottom: -7%;
      width: 42%;
      height: 18%;
      background: radial-gradient(circle at center, rgba(255, 214, 233, 0.92), rgba(255, 191, 220, 0.18) 60%, transparent 72%);
    }

    .brand-backdrop--sunset {
      background:
        radial-gradient(circle at 50% 20%, rgba(232, 173, 255, 0.14), transparent 26%),
        linear-gradient(180deg, #4a1191 0%, #8f57bd 52%, #ffc3b8 100%);
    }

    .brand-backdrop--sunset .sunset-clouds {
      position: absolute;
      inset: auto 0 0;
      height: 34%;
      background:
        radial-gradient(circle at 50% 58%, rgba(238, 113, 138, 0.88), transparent 28%),
        radial-gradient(circle at 42% 70%, rgba(255, 226, 201, 0.9), transparent 24%),
        linear-gradient(180deg, rgba(255, 255, 255, 0) 0%, rgba(255, 198, 213, 0.34) 100%);
      opacity: 0.95;
    }

    .brand-hero-copy {
      position: relative;
      width: 100%;
      max-width: 760px;
      text-align: center;
      padding: 8vh 32px 0;
      text-transform: uppercase;
    }

    .brand-kicker {
      font-family: 'Segoe UI', system-ui, sans-serif;
      font-size: clamp(18px, 2.8vw, 34px);
      font-weight: 400;
      letter-spacing: 1px;
      color: var(--brand-white);
      margin-bottom: 0.8rem;
    }

    .brand-title {
      font-size: clamp(64px, 12vw, 180px);
      line-height: 0.9;
      color: var(--brand-white);
      letter-spacing: 1px;
    }

    .brand-subtitle {
      margin-top: 1.8rem;
      font-family: 'Segoe UI', system-ui, sans-serif;
      font-size: clamp(26px, 4.6vw, 54px);
      font-weight: 800;
      line-height: 1.1;
      letter-spacing: 1px;
      color: var(--brand-white);
      text-transform: uppercase;
    }

    .brand-note {
      margin-top: 0.8rem;
      font-family: 'Segoe UI', system-ui, sans-serif;
      font-size: clamp(14px, 1.8vw, 20px);
      color: rgba(255, 255, 255, 0.78);
      letter-spacing: 0.5px;
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
      background: rgba(255, 255, 255, 0.96);
      color: var(--brand-purple-strong);
      font-size: 18px;
      font-weight: 700;
      padding: 15px 40px;
      border-radius: 999px;
      border: none;
      cursor: pointer;
      letter-spacing: 0.8px;
      transition: opacity .2s, transform .12s;
      box-shadow: 0 10px 36px rgba(64, 12, 91, .24);
      white-space: nowrap;
      text-transform: uppercase;
      font-family: 'Segoe UI', system-ui, sans-serif;
    }

    .btn-p:disabled {
      opacity: .32;
      cursor: default;
    }

    .btn-p:not(:disabled):active {
      transform: scale(.95);
    }

    .btn-s {
      background: rgba(255, 255, 255, .16);
      color: #fff;
      font-size: 16px;
      font-weight: 700;
      padding: 14px 32px;
      border-radius: 999px;
      border: 2px solid rgba(255, 255, 255, .35);
      cursor: pointer;
      transition: background .2s;
      letter-spacing: .5px;
      font-family: 'Segoe UI', system-ui, sans-serif;
      text-transform: uppercase;
    }

    .btn-s:active {
      background: rgba(255, 255, 255, .28);
    }

    /* ── Field label ── */
    .flabel {
      font-size: 19px;
      font-weight: 800;
      letter-spacing: 1px;
      color: rgba(255, 255, 255, .92);
      text-transform: uppercase;
      margin-bottom: 10px;
      padding-left: 18px;
      font-family: 'Segoe UI', system-ui, sans-serif;
    }

    /* ── Kiosk input ── */
    .ki {
      width: 100%;
      background: rgba(255, 255, 255, 0.96);
      border: 2.5px solid transparent;
      border-radius: 999px;
      color: var(--brand-purple);
      font-size: 22px;
      font-weight: 700;
      padding: 6px 26px;
      outline: none;
      caret-color: transparent;
      cursor: pointer;
      transition: border-color .2s, box-shadow .2s;
      font-family: 'Segoe UI', system-ui, sans-serif;
      box-shadow: 0 8px 24px rgba(72, 17, 108, 0.08);
    }

    .ki.focused {
      border-color: rgba(255, 128, 200, .9);
      box-shadow: 0 0 0 4px rgba(255, 128, 200, .22);
    }

    .ki::placeholder {
      color: rgba(100, 60, 140, .35);
      font-weight: 500;
    }

    /* ── CPF display ── */
    .cpf-box {
      font-size: 34px;
      font-weight: 700;
      letter-spacing: 4px;
      color: var(--brand-purple);
      text-align: center;
      background: rgba(255, 255, 255, 0.96);
      border: 2.5px solid transparent;
      border-radius: 999px;
      padding: 6px 28px;
      width: 100%;
      cursor: pointer;
      transition: border-color .2s, box-shadow .2s;
      user-select: none;
      font-family: 'Segoe UI', system-ui, sans-serif;
      box-shadow: 0 8px 24px rgba(72, 17, 108, 0.08);
    }

    .cpf-box.focused {
      border-color: rgba(255, 128, 200, .9);
      box-shadow: 0 0 0 4px rgba(255, 128, 200, .22);
    }

    /* ── Numpad ── */
    .numpad {
      position: absolute;
      bottom: 36vh;
      left: 0;
      right: 0;
      background: rgba(67, 15, 103, .72);
      backdrop-filter: blur(16px);
      padding: 14px 24px 26px;
      border-top: 1px solid rgba(255, 255, 255, .12);
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

    .np-actions {
      display: flex;
      justify-content: center;
      margin-top: 14px;
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

    .np-ok {
      min-width: 180px;
      height: 62px;
      padding: 0 28px;
      border-radius: 16px;
      border: 1px solid rgba(255, 255, 255, .22);
      border-bottom: 3px solid rgba(0, 0, 0, .45);
      background: rgba(255, 255, 255, .18);
      color: #fff;
      font-family: 'Segoe UI', system-ui, sans-serif;
      font-size: 20px;
      font-weight: 800;
      letter-spacing: 1px;
      cursor: pointer;
      transition: background .16s, transform .1s;
    }

    .np-ok:active {
      background: rgba(255, 255, 255, .3);
      transform: scale(.96);
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
      background: rgba(67, 15, 103, .72);
      backdrop-filter: blur(16px);
      padding: 8px 5px 16px;
      border-top: 1px solid rgba(255, 255, 255, .12);
      opacity: 0;
      pointer-events: none;
      transition: opacity .25s ease;
      z-index: 800;
      bottom: 27vh;
    }

    .vkb.open {
      opacity: 1;
      pointer-events: all;
    }

    .vkb-row {
      display: flex;
      justify-content: center;
      gap: 12px;
      margin: 3px 0;
    }

    .vk {
      background: rgba(255, 255, 255, .14);
      border: 1px solid rgba(255, 255, 255, .15);
      border-bottom: 3px solid rgba(0, 0, 0, .45);
      border-radius: 9px;
      color: #fff;
      font-size: 30px;
      font-weight: 500;
      height: 62px;
      min-width: 32px;
      padding: 2.3rem;
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
      height: 7px;
      background: rgba(255, 255, 255, .18);
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
      font-size: 16px;
      font-weight: 600;
      text-align: center;
      min-height: 20px;
      text-shadow: 0 1px 4px rgba(0, 0, 0, .4);
      font-family: 'Segoe UI', system-ui, sans-serif;
    }

    .floating-prompt {
      position: relative;
      z-index: 12;
      width: min(100%, 780px);
      margin-top: 7vh;
      padding: 0 28px;
      text-align: center;
    }

    .floating-prompt h2 {
      font-family: 'Segoe UI', system-ui, sans-serif;
      font-size: clamp(30px, 5vw, 58px);
      line-height: 1.06;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .floating-prompt p {
      margin-top: 12px;
      font-family: 'Segoe UI', system-ui, sans-serif;
      font-size: clamp(14px, 2vw, 21px);
      color: rgba(255, 255, 255, 0.8);
    }

    .capture-stage,
    .waiting-stage {
      position: absolute;
      inset: auto 0 0;
      height: 42%;
      pointer-events: none;
    }

    .capture-stage::before,
    .waiting-stage::before {
      content: "";
      position: absolute;
      left: 50%;
      bottom: 0;
      transform: translateX(-50%);
      width: min(92vw, 760px);
      height: 82%;
      border-radius: 50% 50% 0 0 / 70% 70% 0 0;
      background:
        radial-gradient(circle at 50% 30%, rgba(255, 221, 241, 0.96), rgba(246, 173, 216, 0.56) 40%, rgba(0, 0, 0, 0) 74%);
      filter: blur(6px);
      opacity: 0.75;
    }

    .capture-stage::after {
      content: "";
      position: absolute;
      left: 50%;
      bottom: 12%;
      transform: translateX(-50%);
      width: min(74vw, 560px);
      aspect-ratio: 1 / 0.24;
      border-radius: 50%;
      border: 4px solid rgba(92, 36, 119, 0.55);
      box-shadow:
        0 0 0 26px rgba(255, 195, 224, 0.22),
        0 0 0 48px rgba(112, 44, 146, 0.12);
      opacity: 0.65;
    }

    .waiting-character {
      position: absolute;
      left: 50%;
      bottom: 9%;
      transform: translateX(-50%);
      width: min(60vw, 340px);
      aspect-ratio: 0.72;
      border-radius: 46% 46% 42% 42% / 34% 34% 50% 50%;
      background:
        radial-gradient(circle at 50% 24%, rgba(255, 233, 209, 0.98), rgba(255, 233, 209, 0.98) 10%, transparent 11%),
        radial-gradient(circle at 50% 18%, rgba(255, 241, 189, 0.95), rgba(255, 196, 65, 0.94) 16%, rgba(0, 0, 0, 0) 26%),
        radial-gradient(circle at 50% 55%, rgba(255, 199, 59, 0.98), rgba(247, 152, 42, 0.94) 32%, rgba(59, 17, 16, 0.95) 33%, rgba(59, 17, 16, 0.95) 39%, rgba(241, 170, 50, 0.94) 40%, rgba(241, 170, 50, 0.94) 48%, rgba(55, 17, 16, 0.95) 49%, rgba(55, 17, 16, 0.95) 55%, rgba(226, 143, 36, 0.96) 56%);
      box-shadow: var(--brand-shadow);
      opacity: 0.94;
    }

    .waiting-character::before,
    .waiting-character::after {
      content: "";
      position: absolute;
      top: 18%;
      width: 38%;
      height: 24%;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.24);
      border: 2px solid rgba(255, 255, 255, 0.22);
      filter: blur(1px);
    }

    .waiting-character::before {
      left: 8%;
      transform: rotate(-24deg);
    }

    .waiting-character::after {
      right: 8%;
      transform: rotate(24deg);
    }

    .confirm-front-overlay {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px;
      background: rgba(7, 2, 16, 0.52);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      opacity: 0;
      transition: opacity .3s ease;
      pointer-events: none;
      z-index: 11;
    }

    .confirm-front-panel {
      width: min(100%, 640px);
      border-radius: 32px;
      border: 1px solid rgba(255, 255, 255, .16);
      background: linear-gradient(180deg, rgba(55, 10, 90, .92) 0%, rgba(33, 7, 56, .88) 100%);
      box-shadow: 0 28px 80px rgba(0, 0, 0, .4);
      padding: 34px 28px 30px;
      text-align: center;
      opacity: 0;
      transform: translateY(18px) scale(.98);
      transition: opacity .34s ease, transform .34s ease;
    }

    #s_confirm_front.active .confirm-front-overlay {
      opacity: 1;
      pointer-events: auto;
    }

    #s_confirm_front.active .confirm-front-panel {
      opacity: 1;
      transform: translateY(0) scale(1);
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

  <button id="flow_back_btn" type="button" style="position:fixed;top:24px;left:24px;z-index:10000;display:none;align-items:center;gap:10px;padding:12px 18px;border:none;border-radius:999px;background:rgba(34,7,56,.82);color:#fff;font-family:'Segoe UI',system-ui,sans-serif;font-size:15px;font-weight:700;letter-spacing:.4px;box-shadow:0 12px 28px rgba(0,0,0,.28);">
    <span style="font-size:18px;line-height:1;">&larr;</span>
    <span>VOLTAR</span>
  </button>

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
    <img src="assets/tela-02.png" class="bg-img" alt="" />
    <div class="screen-body" style="justify-content:flex-start;padding:0 32px;gap:3rem;">

      <div class="brand-hero-copy" style="padding-top:24vh;">
        <p class="brand-subtitle" style="font-size:clamp(26px,4vw,48px);margin-top:2.2rem;">PARA COMEÇAR,<BR>INSIRA SEU CPF</p>
      </div>

      <div style="width:100%;max-width:75vw;">

        <div class="flabel">CPF</div>

        <div id="cpf_box" class="cpf-box">
          <span id="cpf_txt" style="color:rgba(100,60,160,.35);letter-spacing:5px;">000.000.000-00</span>
        </div>

        <div id="cpf_err" class="err" style="margin-top:12px;"></div>

        <div id="cpf_btn_area" style="margin-top:18px;display:flex;justify-content:center;
          transition:opacity .25s;min-height:58px;align-items:center;">
          <button id="cpf_btn" class="btn-p" disabled>CONTINUAR</button>
        </div>
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
      <div class="np-actions">
        <button id="np_ok" type="button" class="np-ok">OK</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════
       TELA 3 – SELEÇÃO DE FRAME
  ═══════════════════════════════ -->
  <div id="s_frame" class="screen">
    <img src="assets/tela-02.png" class="bg-img" alt="" />
    <div class="screen-body" style="justify-content:flex-start;padding:0 32px;gap:3rem;">

      <div class="brand-hero-copy" style="padding-top:24vh;">
        <p class="brand-subtitle" style="font-size:clamp(26px,4vw,48px);margin-top:2.2rem;">Escolha seu frame</p>
      </div>

      <!-- Frame cards -->
      <div style="display:flex;align-items:center;justify-content:center;
        width:100%;padding:3vh 18px 0;gap:14px;" id="frame_grid">
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

      <div style="padding:24px 24px 0;width:100%;display:flex;justify-content:center;">
        <button id="frame_btn" class="btn-p" disabled>CONTINUAR</button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════
       TELA 4 – FORMULÁRIO
  ═══════════════════════════════ -->
  <div id="s_form" class="screen">
    <img src="assets/tela-04.png" class="bg-img" alt="" />
    <div class="screen-body" style="justify-content:flex-start;padding:0 32px;gap:3rem;">

      <div class="brand-hero-copy" style="padding-top:24vh;">
        <p class="brand-subtitle" style="font-size:clamp(26px,4vw,48px);margin-top:2.2rem;">PREENCHA OS DADOS<BR>PARA PARTICIPAR</p>
      </div>

      <!-- Campos posicionados logo abaixo do texto do background ("PREENCHA OS DADOS PARA PARTICIPAR") -->
      <div style="width:100%;max-width:75vw;padding:0 28px;flex-shrink:0;gap:2rem;display:flex;flex-direction:column;">

        <div style="margin-bottom:14px;">
          <div class="flabel">NOME</div>
          <input id="f_name" class="ki" readonly autocomplete="off" maxlength="26"/>
        </div>

        <div style="margin-bottom:14px;">
          <div class="flabel">FANDOM</div>
          <input id="f_fandom" class="ki" readonly autocomplete="off" maxlength="26"/>
        </div>

        <div style="margin-bottom:14px;">
          <div class="flabel">VIVO OUVINDO</div>
          <input id="f_track" class="ki" readonly autocomplete="off" maxlength="26"/>
        </div>

        <div id="form_err" class="err" style="margin-top:6px;display:none"></div>
      </div>

      <!-- Button -->
      <div id="form_btn_area" style="width:100%;
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
    <img src="assets/tela-05.png" class="bg-img" alt="" />
    <div class="screen-body" style="justify-content:flex-start;padding:0 32px;gap:3rem;">
      <div class="brand-hero-copy" style="padding-top:24vh;">
        <p class="brand-subtitle" style="margin-top:2rem;">Sorria</p>
      </div>

      <div style="display:flex;align-items:center;justify-content:center;
        width:100%;padding:0 28px;margin-top:8vh;">
        <div id="cap_box" style="position:relative;width:100%;max-width:340px;
          border-radius:28px;overflow:hidden;background:#111;aspect-ratio:3/4;
          box-shadow:0 8px 40px rgba(0,0,0,.55);border:4px solid rgba(255,255,255,.78);">
          <video id="camera_video" style="position:absolute;inset:0;width:100%;height:100%;
            object-fit:cover;" autoplay playsinline muted></video>
          <img id="cap_img" style="position:absolute;inset:0;width:100%;height:100%;
            object-fit:cover;display:none;" alt="" />
          <div id="cap_cdown_wrap">
            <div class="cdown-circle" id="cap_cdown_num">3</div>
          </div>
        </div>
      </div>

      <!-- Botões -->
      <div style="padding:18px 24px 0;z-index:10;position:relative;width:100%;flex-shrink:0;">
        <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">
          <button id="cap_shoot_btn" class="btn-p">TIRAR FOTO</button>
          <button id="cap_retake_btn" class="btn-s" style="display:none;">REFAZER</button>
          <button id="cap_proceed_btn" class="btn-p" style="display:none;">CONTINUAR</button>
          <button id="cap_switch_btn" class="btn-s" style="font-size:14px;padding:10px 18px;display:none;">↻ Câmera</button>
          <button id="cap_cancel_btn" class="btn-s" style="font-size:14px;padding:10px 18px;display:none;">Cancelar</button>
        </div>
        <div id="cap_err" class="err" style="margin-top:10px;"></div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════
       TELA 6 – AGUARDANDO
  ═══════════════════════════════ -->
  <div id="s_confirm_front" class="screen">
    <img src="assets/tela-06.png" class="bg-img" alt="" />
    <div class="confirm-front-overlay">
      <div class="confirm-front-panel">
        <div class="brand-hero-copy" style="width:100%;max-width:none;padding:0;text-align:center;">
          <p class="brand-subtitle" style="font-size:clamp(26px,4vw,48px);margin-top:0;">Confirmar impressao da frente</p>
          <p class="brand-note" style="max-width:520px;margin:1.2rem auto 0;">
            O verso ja foi enviado. Confirme duas vezes para imprimir a frente.
          </p>
        </div>

        <div style="margin-top:24px;border-radius:28px;background:rgba(255,255,255,.08);box-shadow:inset 0 0 0 1px rgba(255,255,255,.08);padding:28px 24px;text-align:center;">
          <div id="front_confirm_status" style="font-family:'Segoe UI',system-ui,sans-serif;font-size:20px;font-weight:700;color:#fff;">
            Confirmacao 1 de 2
          </div>
          <div id="front_confirm_hint" style="margin-top:12px;font-family:'Segoe UI',system-ui,sans-serif;font-size:16px;color:rgba(255,255,255,.8);">
            Toque em confirmar para liberar a impressao da frente.
          </div>
        </div>

        <div style="padding:22px 0 0;width:100%;display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">
          <button id="front_confirm_btn" class="btn-p">CONFIRMAR FRENTE</button>
          <button id="front_confirm_cancel_btn" class="btn-s">CANCELAR</button>
        </div>
        <div id="front_confirm_err" class="err" style="margin-top:12px;"></div>
      </div>
    </div>
  </div>

  <div id="s_waiting" class="screen">
    <img src="assets/tela-06.png" class="bg-img" alt="" />
    <div class="screen-body" style="justify-content:flex-start;padding:0 32px;gap:3rem;">
      <div style="text-align:center;z-index:10;position:relative;
        padding:0 32px;width:100%;margin-top:42.5vh;">

        <div class="dots" style="margin-bottom:22px;">
          <span></span><span></span><span></span>
        </div>

        <div style="display:flex;justify-content:center;">
          <div style="width:100%;max-width:340px;">
            <div class="prog-wrap">
              <div class="prog-fill" id="prog_fill" style="width:0%"></div>
            </div>
            <p id="prog_label" style="color:rgba(255,255,255,.55);font-size:13px;
              font-weight:600;margin-top:8px;letter-spacing:1px;"></p>
          </div>
        </div>

        <div id="wait_msg" style="color:rgba(255,255,255,.6);font-size:14px;
          font-weight:600;margin-top:14px;display:none;
          text-shadow:0 1px 6px rgba(0,0,0,.5);"></div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════
       TELA 7 – PRONTO
  ═══════════════════════════════ -->
  <div id="s_done" class="screen">
    <!-- ATENÇÃO: usa tela-07.png que já contém "CARTÃO PRONTO, OBRIGADO" -->
    <img src="assets/tela-07.png" class="bg-img" alt="" />
    <div id="done_tap" style="position:absolute;inset:0;z-index:20;cursor:pointer;"></div>
    <!-- Sem texto overlay: tela-07.png já possui o conteúdo visual completo -->
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
      const API_PRINT_BACK = "api/print_frame_back.php";
      const API_RECORD = "api/record_participant.php";
      const API_HEALTH = "api/health.php";
      const API_CFG = "api/get_compositor_config.php";
      const API_COMPOSE = "api/compose_image.php";
      const API_CPF = "api/validate_cpf.php";
      const API_BG = "http://localhost:5001/remove-bg";
      const WAIT_SECS_FRONT_AND_BACK = 60;
      const WAIT_SECS_BACK_FIRST_FRONT_ONLY = 28;

      /* ── DOM helpers ──────────────────────────────────── */
      const $ = id => document.getElementById(id);

      const screens = {
        idle: $("s_idle"),
        cpf: $("s_cpf"),
        frame: $("s_frame"),
        form: $("s_form"),
        capture: $("s_capture"),
        confirm_front: $("s_confirm_front"),
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
      const np_ok = $("np_ok");
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

      /* confirm front */
      const front_confirm_status = $("front_confirm_status");
      const front_confirm_hint = $("front_confirm_hint");
      const front_confirm_btn = $("front_confirm_btn");
      const front_confirm_cancel_btn = $("front_confirm_cancel_btn");
      const front_confirm_err = $("front_confirm_err");

      /* waiting */
      const prog_fill = $("prog_fill");
      const prog_label = $("prog_label");
      const wait_msg = $("wait_msg");

      /* done */
      const done_tap = $("done_tap");
      const flow_back_btn = $("flow_back_btn");

      /* ── State ────────────────────────────────────────── */
      let cur_screen = "idle";
      let cpf_digits = "";
      let selected_frame = null;
      let active_inp = null;
      let vkb_shift = false;
      let vkb_mode = "alpha";
      let cam_stream = null;
      let cam_devid = null;
      let cam_devs = [];
      let cam_ready = false;
      let photo_url = null;
      let cdown_tid = null;
      let wait_tid = null;
      let comp_cfg = null;
      let prev_ar = 3 / 4;
      let back_first_print_enabled = false;
      let front_confirm_count = 0;

      /* ── Screen transition ────────────────────────────── */
      function showScreen(name) {
        return new Promise(res => {
          fade_ovl.classList.add("fading");
          setTimeout(() => {
            Object.values(screens).forEach(s => s.classList.remove("active"));
            screens[name].classList.add("active");
            cur_screen = name;
            syncBackButton();
            setTimeout(() => {
              fade_ovl.classList.remove("fading");
              setTimeout(res, 450);
            }, 250);
          }, 400);
        });
      }

      function syncBackButton() {
        const should_show_back = ["cpf", "frame", "form", "capture", "confirm_front"].includes(cur_screen);
        flow_back_btn.style.display = should_show_back ? "inline-flex" : "none";
      }

      async function goBackOneScreen() {
        if (cur_screen === "cpf") {
          await goIdle();
          return;
        }

        if (cur_screen === "frame") {
          await showScreen("cpf");
          return;
        }

        if (cur_screen === "form") {
          closeVkb();
          form_err.textContent = "";
          await showScreen("frame");
          return;
        }

        if (cur_screen === "capture") {
          stopCam();
          if (cdown_tid) {
            clearInterval(cdown_tid);
            cdown_tid = null;
          }
          resetCapture();
          await showScreen("form");
          return;
        }

        if (cur_screen === "confirm_front") {
          resetFrontConfirmState();
          await startCaptureScreen();
        }
      }

      /* ── Loading ──────────────────────────────────────── */
      const showLoad = () => load_ovl.classList.add("visible");
      const hideLoad = () => load_ovl.classList.remove("visible");

      function resetFrontConfirmState() {
        front_confirm_count = 0;
        front_confirm_status.textContent = "Confirmacao 1 de 2";
        front_confirm_hint.textContent = "Toque em confirmar para liberar a impressao da frente.";
        front_confirm_err.textContent = "";
        front_confirm_btn.disabled = false;
      }

      async function printBackForSelectedFrame() {
        if (!selected_frame) throw new Error("Nenhum frame selecionado.");

        showLoad();
        try {
          const r = await fetch(API_PRINT_BACK, {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify({
              csrf_token: CSRF,
              frame_name: selected_frame
            })
          });
          const d = await r.json();
          if (!d.ok) throw new Error(d.error || "Falha ao imprimir verso.");
        } finally {
          hideLoad();
        }
      }

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
          cpf_txt.style.color = "rgba(100,60,160,.35)";
        } else {
          cpf_txt.textContent = fmtCpf(cpf_digits);
          cpf_txt.style.color = "#3a0060";
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

      np_ok.addEventListener("click", e => {
        e.stopPropagation();
        closeNumpad();
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
        try {
          if (back_first_print_enabled) {
            await printBackForSelectedFrame();
            resetFrontConfirmState();
          }
          await showScreen("form");
        } catch (e) {
          alert(String(e.message || e));
        }
      });

      /* ════════════════════════════════════════════════════
         TELA FORMULÁRIO – teclado QWERTY
      ════════════════════════════════════════════════════ */
      const VKB_ALPHA = [
        ["Q", "W", "E", "R", "T", "Y", "U", "I", "O", "P"],
        ["A", "S", "D", "F", "G", "H", "J", "K", "L"],
        ["Z", "X", "C", "V", "B", "N", "M", "⌫"],
        ["123", "SPACE", "@", ".", "OK"],
      ];
      const VKB_NUM = [
        ["1", "2", "3", "4", "5", "6", "7", "8", "9", "0"],
        ["-", "/", ":", ";", "(", ")", "$", "&", "@", '"'],
        ["ABC", ".", ",", "?", "!", "'", "⌫"],
        ["ABC", "SPACE", "OK"],
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
              b.textContent = "ESPAÇO";
            } else if (k === "ABC" || k === "123") {
              b.className += " wide accent";
              b.textContent = k;
            } else if (k === "OK") {
              b.className += " wide accent";
              b.textContent = "OK ✓";
            } else if (k === "⌫") {
              b.className += " del";
              b.textContent = "⌫";
            } else {
              b.textContent = k;
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
        const ch = k === "SPACE" ? " " : k;
        if (active_inp.value.length < (active_inp.maxLength || 40)) {
          active_inp.value += ch;
          active_inp.dispatchEvent(new Event("input"));
        }
      }

      const VKB_H = 292;

      function openVkb(inp) {
        active_inp = inp;
        inp.classList.add("focused");
        form_barea.style.opacity = "0";
        form_barea.style.pointerEvents = "none";
        vkb.classList.add("open");
        buildVkb();
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

      cap_proceed.addEventListener("click", async () => {
        stopCam();
        if (back_first_print_enabled) {
          resetFrontConfirmState();
          await showScreen("confirm_front");
          return;
        }
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
      flow_back_btn.addEventListener("click", goBackOneScreen);

      front_confirm_btn.addEventListener("click", async () => {
        front_confirm_err.textContent = "";

        if (front_confirm_count === 0) {
          front_confirm_count = 1;
          front_confirm_status.textContent = "Confirmacao 2 de 2";
          front_confirm_hint.textContent = "Confirme novamente para imprimir a frente agora.";
          return;
        }

        front_confirm_btn.disabled = true;
        await submitAndWait(true);
      });

      front_confirm_cancel_btn.addEventListener("click", async () => {
        await goBackOneScreen();
      });

      /* ════════════════════════════════════════════════════
         SUBMIT + AGUARDAR
      ════════════════════════════════════════════════════ */
      function getWaitSeconds(front_only_override = false) {
        if (front_only_override && back_first_print_enabled) {
          return WAIT_SECS_BACK_FIRST_FRONT_ONLY;
        }
        return WAIT_SECS_FRONT_AND_BACK;
      }

      async function submitAndWait(front_only_override = false) {
        await showScreen("waiting");
        startWait(getWaitSeconds(front_only_override));

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
              cpf: cpf_digits,
              frame_name: selected_frame
            })
          });
          const cd = await cr.json();
          if (!cd.ok) throw new Error(cd.error || "Falha ao montar imagem.");

          const fk = String(cd.front_image_key || cd.composed_image_key || "");
          const bk = String(cd.back_image_key || "");
          if (!fk) throw new Error("Sem front_image_key.");

          wait_msg.textContent = "Enviando para impressão...";
          const should_print_back_now = !front_only_override && Boolean(bk);
          const final_print_mode = should_print_back_now ? "front_and_back" : "front_only";

          const jr = await fetch(API_JOB, {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify({
              csrf_token: CSRF,
              print_mode: final_print_mode,
              front_composed_image_key: fk,
              back_composed_image_key: bk,
              front_image_data_url: "",
              back_image_data_url: "",
              cpf: cpf_digits,
              frame_name: selected_frame
            })
          });
          const jd = await jr.json();
          if (!jd.ok) throw new Error(jd.error || "Falha ao criar job.");

          wait_msg.textContent = "Salvando participante...";

          fetch(API_RECORD, {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify({
              csrf_token: CSRF,
              cpf: cpf_digits,
              person_name: f_name.value.trim(),
              fandom: f_fandom.value.trim(),
              track: f_track.value.trim(),
              frame_name: selected_frame,
              job_id: jd.job_id,
              job_folder_path: jd.job_folder_path || "",
              print_mode: back_first_print_enabled ? "front_and_back" : final_print_mode,
              front_image_key: fk,
              back_image_key: should_print_back_now ? bk : "",
            })
          }).catch(() => {});

          wait_msg.textContent = "Job criado: " + jd.job_id;
        } catch (e) {
          wait_msg.textContent = "Erro: " + String(e.message || e);
        }
      }

      function startWait(wait_seconds) {
        if (wait_tid) clearInterval(wait_tid);
        let el = 0;
        prog_fill.style.width = "0%";
        prog_label.textContent = `0 / ${wait_seconds}s`;
        wait_tid = setInterval(async () => {
          el++;
          const pct = Math.min(100, (el / wait_seconds) * 100);
          prog_fill.style.width = pct + "%";
          prog_label.textContent = `${el} / ${wait_seconds}s`;
          if (el >= wait_seconds) {
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
        resetFrontConfirmState();
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
          back_first_print_enabled = Boolean(comp_cfg?.back_first_print_enabled);
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
          window.open("dashboard.php", "_blank");
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
      resetFrontConfirmState();
      fetchHealth();
      loadCfg();

    })();
  </script>
</body>

</html>

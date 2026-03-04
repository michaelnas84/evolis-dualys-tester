<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../../src/helpers.php';
require __DIR__ . '/../../src/compositor_helpers.php';

$admin_unlocked = (bool)($_SESSION['admin_unlocked'] ?? false);

try {
    $compositor_config = loadCompositorConfig();
} catch (Throwable $exception) {
    $compositor_config = null;
    $load_error = $exception->getMessage();
}

$public_config_for_editor = null;
if (is_array($compositor_config)) {
    $public_config_for_editor = [
        'photo_box' => (array)$compositor_config['photo_box'],
        'text_fields' => (array)$compositor_config['text_fields'],
        'font_file_path' => (string)$compositor_config['font_file_path'],
        'text_color_rgb' => (array)$compositor_config['text_color_rgb'],
    ];
}

?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard - Compositor</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">

<div class="max-w-6xl mx-auto px-6 py-8">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <div>
      <div class="text-3xl font-semibold">Dashboard</div>
      <div class="text-slate-400">Posições + limites + preview</div>
    </div>
    <div class="flex gap-2">
      <a href="../index.php" class="px-4 py-2 rounded-xl bg-slate-900 border border-slate-700">Voltar ao app</a>
    </div>
  </div>

  <?php if (isset($load_error)): ?>
    <div class="mt-6 p-4 rounded-xl bg-rose-950/40 border border-rose-900 text-rose-200">
      <?= htmlspecialchars($load_error, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if (!$admin_unlocked): ?>
    <div class="mt-8 max-w-md">
      <div class="rounded-2xl bg-slate-900 border border-slate-800 p-6">
        <div class="text-xl font-semibold">Login</div>
        <div class="mt-2 text-sm text-slate-400">Senha padrão: <span class="font-mono">admin123</span> (troque depois)</div>

        <div class="mt-4">
          <label class="text-sm text-slate-300">Senha</label>
          <input id="password_input" type="password" class="mt-2 w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-700" />
        </div>

        <button id="login_button" class="mt-4 w-full px-4 py-3 rounded-xl bg-white text-slate-900 font-semibold">Entrar</button>
        <div id="login_error" class="mt-3 text-sm text-rose-300"></div>
      </div>
    </div>

    <script>
    (() => {
      const login_button = document.getElementById('login_button');
      const password_input = document.getElementById('password_input');
      const login_error = document.getElementById('login_error');

      login_button.addEventListener('click', async () => {
        login_error.textContent = '';
        const password = password_input.value || '';
        if (!password) {
          login_error.textContent = 'Digite a senha.';
          return;
        }

        try {
          const response = await fetch('unlock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password })
          });

          const data = await response.json();
          if (!data.ok) {
            login_error.textContent = data.error || 'Senha inválida.';
            return;
          }

          window.location.reload();
        } catch {
          login_error.textContent = 'Falha ao autenticar.';
        }
      });
    })();
    </script>

  <?php else: ?>

    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

      <div class="rounded-2xl bg-slate-900 border border-slate-800 p-4">
        <div class="text-lg font-semibold">Frame + caixas</div>
        <div class="mt-2 text-sm text-slate-400">Arraste as caixas. Use o canto inferior direito pra redimensionar.</div>

        <div class="mt-4 rounded-2xl overflow-hidden bg-black border border-slate-800 relative" id="stage" style="max-width: 520px;">
          <img id="frame_image" src="../frames/frame-01.png" alt="Frame" class="w-full h-auto block" />

          <div id="box_photo" class="absolute border-2 border-emerald-400/80 bg-emerald-400/10">
            <div class="absolute -top-6 left-0 text-xs bg-black/70 px-2 py-1 rounded">Foto</div>
            <div class="resize_handle absolute right-0 bottom-0 w-4 h-4 bg-emerald-400"></div>
          </div>
          <div id="box_person_name" class="absolute border-2 border-sky-400/80 bg-sky-400/10">
            <div class="absolute -top-6 left-0 text-xs bg-black/70 px-2 py-1 rounded">Nome</div>
            <div class="resize_handle absolute right-0 bottom-0 w-4 h-4 bg-sky-400"></div>
          </div>
          <div id="box_artist_name" class="absolute border-2 border-fuchsia-400/80 bg-fuchsia-400/10">
            <div class="absolute -top-6 left-0 text-xs bg-black/70 px-2 py-1 rounded">Artista</div>
            <div class="resize_handle absolute right-0 bottom-0 w-4 h-4 bg-fuchsia-400"></div>
          </div>
          <div id="box_track_name" class="absolute border-2 border-amber-400/80 bg-amber-400/10">
            <div class="absolute -top-6 left-0 text-xs bg-black/70 px-2 py-1 rounded">Música</div>
            <div class="resize_handle absolute right-0 bottom-0 w-4 h-4 bg-amber-400"></div>
          </div>
        </div>
      </div>

      <div class="rounded-2xl bg-slate-900 border border-slate-800 p-4">
        <div class="text-lg font-semibold">Config</div>
        <div class="mt-2 text-sm text-slate-400">Salvar escreve em <span class="font-mono">storage/compositor_config.json</span>.</div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm text-slate-300">Fonte (TTF)</label>
            <input id="font_file_path" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700" />
          </div>
          <div>
            <label class="text-sm text-slate-300">Oversize foto</label>
            <input id="oversize_factor" type="number" step="0.01" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700" />
          </div>

          <div>
            <label class="text-sm text-slate-300">Máx chars - Nome</label>
            <input id="max_person" type="number" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700" />
          </div>
          <div>
            <label class="text-sm text-slate-300">Fonte - Nome</label>
            <input id="size_person" type="number" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700" />
          </div>

          <div>
            <label class="text-sm text-slate-300">Máx chars - Artista</label>
            <input id="max_artist" type="number" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700" />
          </div>
          <div>
            <label class="text-sm text-slate-300">Fonte - Artista</label>
            <input id="size_artist" type="number" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700" />
          </div>

          <div>
            <label class="text-sm text-slate-300">Máx chars - Música</label>
            <input id="max_track" type="number" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700" />
          </div>
          <div>
            <label class="text-sm text-slate-300">Fonte - Música</label>
            <input id="size_track" type="number" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700" />
          </div>
        </div>

        <div class="mt-4">
          <label class="text-sm text-slate-300">JSON (avançado)</label>
          <textarea id="config_json" rows="10" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 font-mono text-xs"></textarea>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <button id="save_button" class="px-4 py-3 rounded-xl bg-white text-slate-900 font-semibold">Salvar</button>
          <button id="reload_button" class="px-4 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 font-semibold">Recarregar</button>
        </div>

        <div id="save_status" class="mt-3 text-sm text-slate-300"></div>

        <div class="mt-8 border-t border-slate-800 pt-6">
          <div class="text-lg font-semibold">Preview</div>
          <div class="mt-2 text-sm text-slate-400">Não avança o frame (preview_only).</div>

          <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm text-slate-300">Nome</label>
              <input id="preview_person" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700" value="Seu Nome" />
            </div>
            <div>
              <label class="text-sm text-slate-300">Artista</label>
              <input id="preview_artist" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700" value="Artista" />
            </div>
            <div>
              <label class="text-sm text-slate-300">Música</label>
              <input id="preview_track" class="mt-2 w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700" value="Música" />
            </div>
            <div>
              <label class="text-sm text-slate-300">Foto</label>
              <input id="preview_photo" type="file" accept="image/*" class="mt-2 w-full text-sm" />
            </div>
          </div>

          <button id="preview_button" class="mt-4 px-4 py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-semibold">Gerar preview</button>

          <div id="preview_error" class="mt-3 text-sm text-rose-300"></div>
          <img id="preview_image" class="mt-4 w-full rounded-2xl border border-slate-800 bg-black" alt="Preview" />
        </div>

      </div>

    </div>

    <script>
    (() => {
      const initial_config = <?= json_encode($public_config_for_editor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

      const stage = document.getElementById('stage');
      const frame_image = document.getElementById('frame_image');

      const box_photo = document.getElementById('box_photo');
      const box_person_name = document.getElementById('box_person_name');
      const box_artist_name = document.getElementById('box_artist_name');
      const box_track_name = document.getElementById('box_track_name');

      const config_json = document.getElementById('config_json');
      const save_button = document.getElementById('save_button');
      const reload_button = document.getElementById('reload_button');
      const save_status = document.getElementById('save_status');

      const font_file_path = document.getElementById('font_file_path');
      const oversize_factor = document.getElementById('oversize_factor');
      const max_person = document.getElementById('max_person');
      const size_person = document.getElementById('size_person');
      const max_artist = document.getElementById('max_artist');
      const size_artist = document.getElementById('size_artist');
      const max_track = document.getElementById('max_track');
      const size_track = document.getElementById('size_track');

      const preview_person = document.getElementById('preview_person');
      const preview_artist = document.getElementById('preview_artist');
      const preview_track = document.getElementById('preview_track');
      const preview_photo = document.getElementById('preview_photo');
      const preview_button = document.getElementById('preview_button');
      const preview_error = document.getElementById('preview_error');
      const preview_image = document.getElementById('preview_image');

      let current_config = JSON.parse(JSON.stringify(initial_config));

      function setBoxStyle(box_element, box) {
        box_element.style.left = box.x + 'px';
        box_element.style.top = box.y + 'px';
        box_element.style.width = box.width + 'px';
        box_element.style.height = box.height + 'px';
      }

      function getScale() {
        const natural_width = frame_image.naturalWidth || 1;
        const rendered_width = frame_image.clientWidth || 1;
        return rendered_width / natural_width;
      }

      function syncBoxesToDom() {
        const scale = getScale();

        function applyScaled(box_element, box) {
          box_element.style.left = (box.x * scale) + 'px';
          box_element.style.top = (box.y * scale) + 'px';
          box_element.style.width = (box.width * scale) + 'px';
          box_element.style.height = (box.height * scale) + 'px';
        }

        applyScaled(box_photo, current_config.photo_box);
        applyScaled(box_person_name, current_config.text_fields.person_name.box);
        applyScaled(box_artist_name, current_config.text_fields.artist_name.box);
        applyScaled(box_track_name, current_config.text_fields.track_name.box);
      }

      function syncFormFields() {
        font_file_path.value = current_config.font_file_path || '';
        oversize_factor.value = String(current_config.photo_box.oversize_factor || 1.0);

        max_person.value = String(current_config.text_fields.person_name.max_chars || 0);
        size_person.value = String(current_config.text_fields.person_name.font_size || 0);

        max_artist.value = String(current_config.text_fields.artist_name.max_chars || 0);
        size_artist.value = String(current_config.text_fields.artist_name.font_size || 0);

        max_track.value = String(current_config.text_fields.track_name.max_chars || 0);
        size_track.value = String(current_config.text_fields.track_name.font_size || 0);

        config_json.value = JSON.stringify(current_config, null, 2);
      }

      function applyFormFieldsToConfig() {
        current_config.font_file_path = font_file_path.value || current_config.font_file_path;
        current_config.photo_box.oversize_factor = Number(oversize_factor.value || current_config.photo_box.oversize_factor);

        current_config.text_fields.person_name.max_chars = Number(max_person.value || current_config.text_fields.person_name.max_chars);
        current_config.text_fields.person_name.font_size = Number(size_person.value || current_config.text_fields.person_name.font_size);

        current_config.text_fields.artist_name.max_chars = Number(max_artist.value || current_config.text_fields.artist_name.max_chars);
        current_config.text_fields.artist_name.font_size = Number(size_artist.value || current_config.text_fields.artist_name.font_size);

        current_config.text_fields.track_name.max_chars = Number(max_track.value || current_config.text_fields.track_name.max_chars);
        current_config.text_fields.track_name.font_size = Number(size_track.value || current_config.text_fields.track_name.font_size);

        config_json.value = JSON.stringify(current_config, null, 2);
      }

      function attachDragAndResize(box_element, getBox, setBox) {
        let dragging = false;
        let resizing = false;
        let start_x = 0;
        let start_y = 0;
        let start_box = null;

        box_element.addEventListener('pointerdown', (event) => {
          const scale = getScale();
          const handle = event.target && event.target.classList && event.target.classList.contains('resize_handle');
          dragging = !handle;
          resizing = !!handle;

          start_x = event.clientX;
          start_y = event.clientY;
          start_box = { ...getBox() };

          box_element.setPointerCapture(event.pointerId);
          event.preventDefault();
        });

        box_element.addEventListener('pointermove', (event) => {
          if (!dragging && !resizing) return;

          const scale = getScale();
          const dx = (event.clientX - start_x) / scale;
          const dy = (event.clientY - start_y) / scale;

          const next = { ...start_box };

          if (dragging) {
            next.x = Math.max(0, Math.round(start_box.x + dx));
            next.y = Math.max(0, Math.round(start_box.y + dy));
          }

          if (resizing) {
            next.width = Math.max(10, Math.round(start_box.width + dx));
            next.height = Math.max(10, Math.round(start_box.height + dy));
          }

          setBox(next);
          syncBoxesToDom();
          syncFormFields();
        });

        box_element.addEventListener('pointerup', () => {
          dragging = false;
          resizing = false;
          start_box = null;
        });
      }

      attachDragAndResize(box_photo, () => current_config.photo_box, (next) => { current_config.photo_box = next; });
      attachDragAndResize(box_person_name, () => current_config.text_fields.person_name.box, (next) => { current_config.text_fields.person_name.box = next; });
      attachDragAndResize(box_artist_name, () => current_config.text_fields.artist_name.box, (next) => { current_config.text_fields.artist_name.box = next; });
      attachDragAndResize(box_track_name, () => current_config.text_fields.track_name.box, (next) => { current_config.text_fields.track_name.box = next; });

      window.addEventListener('resize', () => syncBoxesToDom());
      frame_image.addEventListener('load', () => syncBoxesToDom());

      [font_file_path, oversize_factor, max_person, size_person, max_artist, size_artist, max_track, size_track].forEach((el) => {
        el.addEventListener('input', () => {
          applyFormFieldsToConfig();
        });
      });

      reload_button.addEventListener('click', () => {
        current_config = JSON.parse(JSON.stringify(initial_config));
        syncBoxesToDom();
        syncFormFields();
        save_status.textContent = '';
      });

      save_button.addEventListener('click', async () => {
        save_status.textContent = '';

        let parsed = null;
        try {
          parsed = JSON.parse(config_json.value || '{}');
        } catch {
          save_status.textContent = 'JSON inválido.';
          return;
        }

        try {
          const response = await fetch('save_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ compositor_config: parsed })
          });

          const data = await response.json();
          if (!data.ok) {
            save_status.textContent = data.error || 'Falha ao salvar.';
            return;
          }

          save_status.textContent = 'Salvo.';
        } catch {
          save_status.textContent = 'Falha ao salvar.';
        }
      });

      preview_button.addEventListener('click', async () => {
        preview_error.textContent = '';
        preview_image.removeAttribute('src');

        const file = preview_photo.files && preview_photo.files[0] ? preview_photo.files[0] : null;
        if (!file) {
          preview_error.textContent = 'Escolha uma foto.';
          return;
        }

        const photo_data_url = await new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onload = () => resolve(String(reader.result));
          reader.onerror = () => reject(new Error('Falha ao ler foto.'));
          reader.readAsDataURL(file);
        });

        try {
          const response = await fetch('../api/compose_image.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              csrf_token: <?= json_encode((string)($_SESSION['csrf_token'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
              preview_only: true,
              person_name: preview_person.value || '',
              artist_name: preview_artist.value || '',
              track_name: preview_track.value || '',
              photo_data_url
            })
          });

          const data = await response.json();
          if (!data.ok) {
            preview_error.textContent = data.error || 'Falha ao gerar preview.';
            return;
          }

          preview_image.src = data.final_image_data_url;
        } catch (error) {
          preview_error.textContent = String(error && error.message ? error.message : error);
        }
      });

      // initial
      syncBoxesToDom();
      syncFormFields();
    })();
    </script>

  <?php endif; ?>

</div>
</body>
</html>

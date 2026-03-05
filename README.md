# Card Kiosk (PHP + JS + Tailwind)

## Rodar local (dev)

```bash
py -m pip install flask flask-cors rembg pillow onnxruntime
py -3.11 C:\card_hotfolder\card_hotfolder_printer.py --root C:\card_hotfolder --printer "Evolis Dualys Series"
cd public
php -S 127.0.0.1:8000
```

Abra:
- App: `http://127.0.0.1:8000/`
- Dashboard: `http://127.0.0.1:8000/admin/`

## Fluxo

1. **Idle** → toque em **Iniciar**
2. **Foto** → **Tirar foto** (countdown 3s) → **Prosseguir**
3. **Form** → Nome + (Manual ou Spotify) → **Enviar para impressão**
4. Backend compõe a imagem usando o **próximo frame** em `public/frames/` e cria o job no hotfolder via `public/api/create_job.php` (sem mexer na lógica de impressora).

## Alternar Manual / Spotify

- `Ctrl + Alt + P`
  - Primeira vez pede senha (admin) e alterna o modo.
  - Depois de destravado, alterna sem pedir senha.
- `Ctrl + Alt + D` abre o dashboard (requer admin destravado).

## Frames

Coloque seus PNGs em:

```
public/frames/
```

O sistema guarda o último frame usado em:

```
storage/frame_state.json
```

## Config do compositor

O dashboard escreve overrides em:

```
storage/compositor_config.json
```

Defaults ficam em:

```
src/compositor_config.php
```

Itens principais:
- `photo_box` (x, y, width, height, oversize_factor)
- `text_fields.*.box` + `max_chars` + `font_size`
- `font_file_path`

## Admin

Senha padrão (bootstrap): `admin123`

O hash fica em:

```
storage/admin_password_hash.txt
```

## Hotfolder / Impressora

A lógica de hotfolder e criação do `manifest.json` continua em:
- `public/api/create_job.php`
- `scripts/card_hotfolder_printer.py`

Configure o caminho do hotfolder em `src/config.php`.

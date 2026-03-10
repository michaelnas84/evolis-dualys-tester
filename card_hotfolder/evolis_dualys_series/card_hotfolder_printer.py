import argparse
import json
import logging
import shutil
import sys
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Optional, List, Tuple, Dict, Any

import win32con
import win32print
import win32ui
from PIL import Image, ImageOps, ImageWin

# Runtime globals loaded from JSON config.
static_back_enabled = False
static_back_image_path = Path(r"C:\card_hotfolder\backgrounds\static_back.png")
static_back_fit_mode = "fill"  # "contain" | "fill" | "stretch"
static_back_rotate_degrees = 0  # 0 | 90 | 180 | 270
static_back_auto_rotate = False

_static_back_cache = {"mtime": None, "image": None}


DEFAULT_APP_CONFIG: Dict[str, Any] = {
    "printer_defaults": {
        "printer_name": "",
        "printer_is_duplex": True,
        "copies": 1,
        "duplex": "auto",
        "fit_mode": "fill",
        "rotate_degrees": 0,
        "card_size_mm": {
            "width_mm": 85.6,
            "height_mm": 53.98,
        },
        "form_name": "CR80",
        "print_dpi": 300,
        "auto_rotate": True,
        "background_color_rgb": [255, 255, 255],
    },
    "static_back": {
        "enabled": False,
        "image_path": r"C:\card_hotfolder\backgrounds\static_back.png",
        "fit_mode": "fill",
        "rotate_degrees": 0,
        "auto_rotate": False,
    },
    "template_print": {
        "printer_name": "",
        "printer_is_duplex": True,
        "front_image_path": r"C:\card_hotfolder\backgrounds\template_front.png",
        "back_image_path": r"C:\card_hotfolder\backgrounds\template_back.png",
        "mode": "front",
        "copies": 1,
        "fit_mode": "fill",
        "rotate_degrees": 0,
        "auto_rotate": True,
        "background_color_rgb": [255, 255, 255],
        "duplex": "true",
        "form_name": "CR80",
        "print_dpi": 300,
        "card_size_mm": {
            "width_mm": 85.6,
            "height_mm": 53.98,
        },
    },
    "job_detection": {
        "enable_folder_manifest_jobs": True,
        "enable_named_front_back_jobs": True,
        "enable_single_file_jobs": True,
    },
}


@dataclass
class PrintJob:
    source_path: Path
    printer_name: str
    copies: int
    duplex: str  # "auto" | "true" | "false"
    fit_mode: str  # "contain" | "fill" | "stretch"
    rotate_degrees: int
    card_size_mm: Tuple[float, float]  # (width_mm, height_mm)
    front_path: Optional[Path] = None
    back_path: Optional[Path] = None
    pdf_path: Optional[Path] = None

    # Backward-compatible defaults.
    form_name: str = "CR80"
    print_dpi: int = 300
    auto_rotate: bool = True
    background_color_rgb: Tuple[int, int, int] = (255, 255, 255)
    printer_is_duplex: bool = False


def deepMergeDicts(base_dict: Dict[str, Any], override_dict: Dict[str, Any]) -> Dict[str, Any]:
    merged_dict = dict(base_dict)
    for key, value in override_dict.items():
        if isinstance(value, dict) and isinstance(merged_dict.get(key), dict):
            merged_dict[key] = deepMergeDicts(merged_dict[key], value)
        else:
            merged_dict[key] = value
    return merged_dict


def saveJsonFile(file_path: Path, payload: Dict[str, Any]) -> None:
    file_path.parent.mkdir(parents=True, exist_ok=True)
    with open(file_path, "w", encoding="utf-8") as file_handle:
        json.dump(payload, file_handle, indent=2, ensure_ascii=False)


def loadJsonFile(file_path: Path) -> Dict[str, Any]:
    with open(file_path, "r", encoding="utf-8") as file_handle:
        return json.load(file_handle)


def ensureAppConfig(config_file_path: Path) -> Dict[str, Any]:
    if not config_file_path.exists():
        saveJsonFile(config_file_path, DEFAULT_APP_CONFIG)
        return json.loads(json.dumps(DEFAULT_APP_CONFIG))

    loaded_config = loadJsonFile(config_file_path)
    return deepMergeDicts(DEFAULT_APP_CONFIG, loaded_config)


def applyRuntimeConfig(app_config: Dict[str, Any]) -> None:
    global static_back_enabled
    global static_back_image_path
    global static_back_fit_mode
    global static_back_rotate_degrees
    global static_back_auto_rotate

    static_back_config = app_config.get("static_back", {})

    static_back_enabled = bool(static_back_config.get("enabled", False))
    static_back_image_path = Path(
        str(static_back_config.get("image_path", r"C:\card_hotfolder\backgrounds\static_back.png"))
    )
    static_back_fit_mode = str(static_back_config.get("fit_mode", "fill")).lower()
    static_back_rotate_degrees = normalizeDegrees(int(static_back_config.get("rotate_degrees", 0)))
    static_back_auto_rotate = bool(static_back_config.get("auto_rotate", False))


def getStaticBackImage() -> Image.Image:
    # Cache to avoid reloading file on every job.
    if not static_back_image_path.exists():
        raise FileNotFoundError(f"Static back image not found: {static_back_image_path}")

    waitForFileReady(static_back_image_path)

    current_mtime = static_back_image_path.stat().st_mtime
    cached_image = _static_back_cache.get("image")

    if _static_back_cache.get("mtime") == current_mtime and cached_image is not None:
        return cached_image.copy()

    with Image.open(static_back_image_path) as image_handle:
        loaded_image = image_handle.convert("RGB").copy()

    _static_back_cache["mtime"] = current_mtime
    _static_back_cache["image"] = loaded_image
    return loaded_image.copy()


def normalizeDegrees(value: int) -> int:
    value = int(value) % 360
    if value not in (0, 90, 180, 270):
        raise ValueError("rotate_degrees must be one of: 0, 90, 180, 270")
    return value


def normalizeFormToken(value: str) -> str:
    return "".join(character for character in str(value).lower() if character.isalnum())


def parseBackgroundColor(background_color_value: Any) -> Tuple[int, int, int]:
    if not isinstance(background_color_value, (list, tuple)) or len(background_color_value) != 3:
        raise ValueError("background_color_rgb must contain exactly 3 numeric values")

    parsed_channels = []
    for channel_value in background_color_value:
        parsed_channel = max(0, min(255, int(channel_value)))
        parsed_channels.append(parsed_channel)

    return tuple(parsed_channels)


def resolveFormName(printer_handle, requested_form_name: str) -> Optional[str]:
    requested_token = normalizeFormToken(requested_form_name)
    if not requested_token:
        return None

    try:
        forms = win32print.EnumForms(printer_handle, 1)
    except Exception:
        return None

    for form in forms:
        form_name = str(form.get("Name", ""))
        if normalizeFormToken(form_name) == requested_token:
            return form_name

    for form in forms:
        form_name = str(form.get("Name", ""))
        if requested_token in normalizeFormToken(form_name):
            return form_name

    return None


def applyCardSizeToDevmode(devmode, card_size_mm: Tuple[float, float]) -> None:
    width_tenths_mm = int(round(float(card_size_mm[0]) * 10))
    height_tenths_mm = int(round(float(card_size_mm[1]) * 10))

    devmode.PaperWidth = width_tenths_mm
    devmode.PaperLength = height_tenths_mm
    devmode.Fields |= (win32con.DM_PAPERWIDTH | win32con.DM_PAPERLENGTH)


def buildDevmodeForJob(
    printer_name: str,
    form_name: str,
    print_dpi: int,
    duplex: str,
    orientation: str,  # "portrait" | "landscape"
    card_size_mm: Tuple[float, float],
):
    printer_handle = win32print.OpenPrinter(printer_name)
    try:
        printer_info = win32print.GetPrinter(printer_handle, 2)
        devmode = printer_info.get("pDevMode")
        if devmode is None:
            return None

        resolved_form_name = resolveFormName(printer_handle, form_name)
        if resolved_form_name:
            devmode.FormName = resolved_form_name
            devmode.Fields |= win32con.DM_FORMNAME
        else:
            applyCardSizeToDevmode(devmode, card_size_mm)

        if int(print_dpi) > 0:
            devmode.PrintQuality = int(print_dpi)
            devmode.YResolution = int(print_dpi)
            devmode.Fields |= (win32con.DM_PRINTQUALITY | win32con.DM_YRESOLUTION)

        if orientation == "landscape":
            devmode.Orientation = win32con.DMORIENT_LANDSCAPE
            devmode.Fields |= win32con.DM_ORIENTATION
        elif orientation == "portrait":
            devmode.Orientation = win32con.DMORIENT_PORTRAIT
            devmode.Fields |= win32con.DM_ORIENTATION

        try:
            if duplex == "true":
                devmode.Duplex = win32con.DMDUP_VERTICAL
                devmode.Fields |= win32con.DM_DUPLEX
            elif duplex == "false":
                devmode.Duplex = win32con.DMDUP_SIMPLEX
                devmode.Fields |= win32con.DM_DUPLEX
        except Exception:
            pass

        try:
            devmode = win32print.DocumentProperties(
                None,
                printer_handle,
                printer_name,
                devmode,
                devmode,
                win32con.DM_IN_BUFFER | win32con.DM_OUT_BUFFER,
            )
        except Exception:
            pass

        return devmode
    finally:
        win32print.ClosePrinter(printer_handle)


def createPrinterDeviceContext(printer_name: str, devmode):
    device_context = win32ui.CreateDC()

    if devmode is not None:
        try:
            device_context.CreateDC("WINSPOOL", printer_name, None, devmode)
            return device_context
        except Exception:
            pass

    device_context.CreatePrinterDC(printer_name)
    return device_context


def listPrinters() -> List[str]:
    printers = []
    flags = win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS
    for printer_info in win32print.EnumPrinters(flags):
        printers.append(printer_info[2])
    return printers


def setupLogging(log_folder_path: Path) -> None:
    log_folder_path.mkdir(parents=True, exist_ok=True)
    log_file_path = log_folder_path / "hotfolder.log"

    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(message)s",
        handlers=[
            logging.FileHandler(log_file_path, encoding="utf-8"),
            logging.StreamHandler(sys.stdout),
        ],
    )


def waitForFileReady(file_path: Path, max_attempts: int = 50, delay_seconds: float = 0.2) -> None:
    # Wait until file stops changing size and is readable.
    last_size = -1
    for _ in range(max_attempts):
        try:
            current_size = file_path.stat().st_size
            if current_size == last_size and current_size > 0:
                with open(file_path, "rb"):
                    return
            last_size = current_size
        except OSError:
            pass
        time.sleep(delay_seconds)
    raise RuntimeError(f"File is locked or incomplete: {file_path}")


def waitForFolderReady(folder_path: Path, max_attempts: int = 50, delay_seconds: float = 0.2) -> None:
    # Wait until folder contents settle.
    last_snapshot = None
    for _ in range(max_attempts):
        try:
            snapshot = sorted(
                [
                    (path_item.name, path_item.stat().st_size, int(path_item.stat().st_mtime_ns))
                    for path_item in folder_path.glob("*")
                    if path_item.is_file()
                ]
            )
            if snapshot == last_snapshot and len(snapshot) > 0:
                return
            last_snapshot = snapshot
        except OSError:
            pass
        time.sleep(delay_seconds)
    raise RuntimeError(f"Folder is incomplete: {folder_path}")


def loadManifest(manifest_path: Path) -> dict:
    waitForFileReady(manifest_path)
    with open(manifest_path, "r", encoding="utf-8") as file_handle:
        return json.load(file_handle)


def chooseAutoRotateDegrees(image: Image.Image, target_width_px: int, target_height_px: int) -> int:
    candidate_degrees = [0, 90, 180, 270]
    best_degrees = 0
    best_scale = -1.0

    image_width, image_height = image.size

    for degrees in candidate_degrees:
        if degrees in (90, 270):
            rotated_width, rotated_height = image_height, image_width
        else:
            rotated_width, rotated_height = image_width, image_height

        scale = min(target_width_px / rotated_width, target_height_px / rotated_height)
        if scale > best_scale:
            best_scale = scale
            best_degrees = degrees

    return best_degrees


def prepareImageForCard(
    image: Image.Image,
    target_width_px: int,
    target_height_px: int,
    fit_mode: str,
    rotate_degrees: int,
    auto_rotate: bool,
    background_color_rgb: Tuple[int, int, int],
) -> Image.Image:
    # Handle EXIF orientation if present.
    image = ImageOps.exif_transpose(image)

    effective_rotate_degrees = rotate_degrees
    if auto_rotate and rotate_degrees == 0:
        effective_rotate_degrees = chooseAutoRotateDegrees(image, target_width_px, target_height_px)

    if effective_rotate_degrees != 0:
        image = image.rotate(effective_rotate_degrees, expand=True)

    image = image.convert("RGB")

    if fit_mode == "stretch":
        return image.resize((target_width_px, target_height_px), Image.Resampling.LANCZOS)

    if fit_mode == "fill":
        return ImageOps.fit(
            image,
            (target_width_px, target_height_px),
            Image.Resampling.LANCZOS,
            centering=(0.5, 0.5),
        )

    if fit_mode == "contain":
        contained = ImageOps.contain(image, (target_width_px, target_height_px), Image.Resampling.LANCZOS)
        canvas = Image.new("RGB", (target_width_px, target_height_px), background_color_rgb)
        paste_left = (target_width_px - contained.width) // 2
        paste_top = (target_height_px - contained.height) // 2
        canvas.paste(contained, (paste_left, paste_top))
        return canvas

    raise ValueError("fit_mode must be one of: contain, fill, stretch")


def getPrinterCaps(printer_name: str, devmode=None) -> Tuple[int, int, int, int, int, int]:
    device_context = createPrinterDeviceContext(printer_name, devmode)

    printable_width_px = device_context.GetDeviceCaps(win32con.HORZRES)
    printable_height_px = device_context.GetDeviceCaps(win32con.VERTRES)
    dpi_x = device_context.GetDeviceCaps(win32con.LOGPIXELSX)
    dpi_y = device_context.GetDeviceCaps(win32con.LOGPIXELSY)

    offset_x_px = device_context.GetDeviceCaps(win32con.PHYSICALOFFSETX)
    offset_y_px = device_context.GetDeviceCaps(win32con.PHYSICALOFFSETY)

    device_context.DeleteDC()
    return printable_width_px, printable_height_px, dpi_x, dpi_y, offset_x_px, offset_y_px


def renderPdfToImages(pdf_path: Path, dpi: int = 300) -> List[Image.Image]:
    try:
        import fitz  # PyMuPDF
    except Exception as exc:
        raise RuntimeError("PDF support requires pymupdf (pip install pymupdf).") from exc

    waitForFileReady(pdf_path)

    document = fitz.open(str(pdf_path))
    images: List[Image.Image] = []

    zoom = dpi / 72.0
    matrix = fitz.Matrix(zoom, zoom)

    for page_index in range(document.page_count):
        page = document.load_page(page_index)
        pix = page.get_pixmap(matrix=matrix, alpha=False)
        image = Image.frombytes("RGB", (pix.width, pix.height), pix.samples)
        images.append(image)

    document.close()
    return images


def resolveRequestedDuplex(duplex: str, page_count: int, printer_is_duplex: bool) -> str:
    normalized_duplex = str(duplex).lower()

    if normalized_duplex == "true":
        if not printer_is_duplex:
            raise RuntimeError("Job requires duplex, but printer_is_duplex is false.")
        return "true"

    if normalized_duplex == "false":
        return "false"

    if normalized_duplex != "auto":
        raise ValueError("duplex must be one of: auto, true, false")

    if page_count >= 2:
        if not printer_is_duplex:
            raise RuntimeError("Job generated front/back pages, but printer_is_duplex is false.")
        return "true"

    return "false"


def printImagePages(
    printer_name: str,
    pages: List[Image.Image],
    copies: int,
    fit_mode: str,
    rotate_degrees: int,
    card_size_mm: Tuple[float, float],
    form_name: str,
    print_dpi: int,
    duplex: str,
    auto_rotate: bool,
    background_color_rgb: Tuple[int, int, int],
    page_fit_modes: Optional[List[str]] = None,
    page_rotate_degrees: Optional[List[int]] = None,
    page_auto_rotate: Optional[List[bool]] = None,
) -> None:
    orientation = "landscape" if card_size_mm[0] >= card_size_mm[1] else "portrait"

    devmode = buildDevmodeForJob(
        printer_name=printer_name,
        form_name=form_name,
        print_dpi=print_dpi,
        duplex=duplex,
        orientation=orientation,
        card_size_mm=card_size_mm,
    )

    printable_width_px, printable_height_px, _, _, offset_x_px, offset_y_px = getPrinterCaps(printer_name, devmode)
    device_context = createPrinterDeviceContext(printer_name, devmode)

    # This matches Windows "Print Pictures": draw into the printable area.
    draw_left = int(offset_x_px)
    draw_top = int(offset_y_px)
    draw_right = draw_left + int(printable_width_px)
    draw_bottom = draw_top + int(printable_height_px)

    target_width_px = int(printable_width_px)
    target_height_px = int(printable_height_px)

    try:
        for copy_index in range(max(1, copies)):
            document_name = f"CardJob_{int(time.time())}_{copy_index + 1}"
            device_context.StartDoc(document_name)

            for page_index, page_image in enumerate(pages):
                effective_fit_mode = fit_mode
                if page_fit_modes and page_index < len(page_fit_modes):
                    effective_fit_mode = page_fit_modes[page_index]

                effective_rotate_degrees = rotate_degrees
                if page_rotate_degrees and page_index < len(page_rotate_degrees):
                    effective_rotate_degrees = page_rotate_degrees[page_index]

                effective_auto_rotate = auto_rotate
                if page_auto_rotate and page_index < len(page_auto_rotate):
                    effective_auto_rotate = page_auto_rotate[page_index]

                device_context.StartPage()

                prepared = prepareImageForCard(
                    image=page_image,
                    target_width_px=target_width_px,
                    target_height_px=target_height_px,
                    fit_mode=effective_fit_mode,
                    rotate_degrees=effective_rotate_degrees,
                    auto_rotate=effective_auto_rotate,
                    background_color_rgb=background_color_rgb,
                )

                dib = ImageWin.Dib(prepared)
                dib.draw(device_context.GetHandleOutput(), (draw_left, draw_top, draw_right, draw_bottom))

                device_context.EndPage()

            device_context.EndDoc()
    finally:
        device_context.DeleteDC()


def buildJobFromManifest(job_folder_path: Path, manifest: dict, app_config: Dict[str, Any], default_printer_name: str) -> PrintJob:
    printer_defaults = app_config.get("printer_defaults", {})
    merged_manifest = deepMergeDicts(printer_defaults, manifest)

    printer_name = str(merged_manifest.get("printer_name") or default_printer_name).strip()
    copies = max(1, int(merged_manifest.get("copies", 1)))
    duplex = str(merged_manifest.get("duplex", "auto")).lower()
    fit_mode = str(merged_manifest.get("fit_mode", "contain")).lower()
    rotate_degrees = normalizeDegrees(int(merged_manifest.get("rotate_degrees", 0)))

    card_size = merged_manifest.get("card_size_mm", {"width_mm": 85.6, "height_mm": 53.98})
    card_width_mm = float(card_size.get("width_mm", 85.6))
    card_height_mm = float(card_size.get("height_mm", 53.98))

    front_file = manifest.get("front_file")
    back_file = manifest.get("back_file")
    pdf_file = manifest.get("pdf_file")

    front_path = (job_folder_path / front_file) if front_file else None
    back_path = (job_folder_path / back_file) if back_file else None
    pdf_path = (job_folder_path / pdf_file) if pdf_file else None

    form_name = str(merged_manifest.get("form_name", "CR80"))
    print_dpi = int(merged_manifest.get("print_dpi", 300))
    auto_rotate = bool(merged_manifest.get("auto_rotate", True))
    background_color_rgb = parseBackgroundColor(merged_manifest.get("background_color_rgb", [255, 255, 255]))
    printer_is_duplex = bool(merged_manifest.get("printer_is_duplex", False))

    return PrintJob(
        source_path=job_folder_path,
        printer_name=printer_name,
        copies=copies,
        duplex=duplex,
        fit_mode=fit_mode,
        rotate_degrees=rotate_degrees,
        card_size_mm=(card_width_mm, card_height_mm),
        front_path=front_path,
        back_path=back_path,
        pdf_path=pdf_path,
        form_name=form_name,
        print_dpi=print_dpi,
        auto_rotate=auto_rotate,
        background_color_rgb=background_color_rgb,
        printer_is_duplex=printer_is_duplex,
    )


def buildJobFromSingleFile(file_path: Path, app_config: Dict[str, Any], default_printer_name: str) -> PrintJob:
    printer_defaults = app_config.get("printer_defaults", {})
    card_size = printer_defaults.get("card_size_mm", {"width_mm": 85.6, "height_mm": 53.98})

    printer_name = str(printer_defaults.get("printer_name") or default_printer_name).strip()
    copies = max(1, int(printer_defaults.get("copies", 1)))
    duplex = str(printer_defaults.get("duplex", "auto")).lower()
    fit_mode = str(printer_defaults.get("fit_mode", "fill")).lower()
    rotate_degrees = normalizeDegrees(int(printer_defaults.get("rotate_degrees", 0)))
    form_name = str(printer_defaults.get("form_name", "CR80"))
    print_dpi = int(printer_defaults.get("print_dpi", 300))
    auto_rotate = bool(printer_defaults.get("auto_rotate", True))
    background_color_rgb = parseBackgroundColor(printer_defaults.get("background_color_rgb", [255, 255, 255]))
    printer_is_duplex = bool(printer_defaults.get("printer_is_duplex", False))

    suffix = file_path.suffix.lower()

    if suffix == ".pdf":
        return PrintJob(
            source_path=file_path,
            printer_name=printer_name,
            copies=copies,
            duplex=duplex,
            fit_mode=fit_mode,
            rotate_degrees=rotate_degrees,
            card_size_mm=(float(card_size.get("width_mm", 85.6)), float(card_size.get("height_mm", 53.98))),
            pdf_path=file_path,
            form_name=form_name,
            print_dpi=print_dpi,
            auto_rotate=auto_rotate,
            background_color_rgb=background_color_rgb,
            printer_is_duplex=printer_is_duplex,
        )

    return PrintJob(
        source_path=file_path,
        printer_name=printer_name,
        copies=copies,
        duplex="false",
        fit_mode=fit_mode,
        rotate_degrees=rotate_degrees,
        card_size_mm=(float(card_size.get("width_mm", 85.6)), float(card_size.get("height_mm", 53.98))),
        front_path=file_path,
        form_name=form_name,
        print_dpi=print_dpi,
        auto_rotate=auto_rotate,
        background_color_rgb=background_color_rgb,
        printer_is_duplex=printer_is_duplex,
    )


def buildTemplateJob(
    app_config: Dict[str, Any],
    default_printer_name: str,
    root_path: Path,
    template_mode: Optional[str] = None,
) -> PrintJob:
    template_config = app_config.get("template_print", {})
    printer_defaults = app_config.get("printer_defaults", {})
    card_size = template_config.get("card_size_mm", {"width_mm": 85.6, "height_mm": 53.98})

    effective_mode = str(template_mode or template_config.get("mode", "front")).lower()
    if effective_mode not in {"front", "front_back"}:
        raise ValueError("template mode must be 'front' or 'front_back'")

    front_image_path = Path(str(template_config.get("front_image_path", r"C:\card_hotfolder\backgrounds\template_front.png")))
    back_image_path = Path(str(template_config.get("back_image_path", r"C:\card_hotfolder\backgrounds\template_back.png")))

    if not front_image_path.exists():
        raise FileNotFoundError(f"Template front image not found: {front_image_path}")

    resolved_back_path = None
    effective_duplex = "false"

    if effective_mode == "front_back":
        if not back_image_path.exists():
            raise FileNotFoundError(f"Template back image not found: {back_image_path}")
        resolved_back_path = back_image_path
        effective_duplex = str(template_config.get("duplex", "true")).lower()

    template_printer_name = str(
        template_config.get("printer_name")
        or printer_defaults.get("printer_name")
        or default_printer_name
    ).strip()

    printer_is_duplex = bool(
        template_config.get("printer_is_duplex", printer_defaults.get("printer_is_duplex", False))
    )

    return PrintJob(
        source_path=root_path,
        printer_name=template_printer_name,
        copies=max(1, int(template_config.get("copies", 1))),
        duplex=effective_duplex,
        fit_mode=str(template_config.get("fit_mode", "fill")).lower(),
        rotate_degrees=normalizeDegrees(int(template_config.get("rotate_degrees", 0))),
        card_size_mm=(float(card_size.get("width_mm", 85.6)), float(card_size.get("height_mm", 53.98))),
        front_path=front_image_path,
        back_path=resolved_back_path,
        form_name=str(template_config.get("form_name", printer_defaults.get("form_name", "CR80"))),
        print_dpi=int(template_config.get("print_dpi", printer_defaults.get("print_dpi", 300))),
        auto_rotate=bool(template_config.get("auto_rotate", printer_defaults.get("auto_rotate", True))),
        background_color_rgb=parseBackgroundColor(
            template_config.get("background_color_rgb", printer_defaults.get("background_color_rgb", [255, 255, 255]))
        ),
        printer_is_duplex=printer_is_duplex,
    )


def detectFrontBackByName(incoming_path: Path, app_config: Dict[str, Any], default_printer_name: str) -> List[PrintJob]:
    jobs: List[PrintJob] = []
    image_extensions = {".png", ".jpg", ".jpeg", ".bmp", ".tif", ".tiff"}
    images = [path_item for path_item in incoming_path.iterdir() if path_item.is_file() and path_item.suffix.lower() in image_extensions]

    printer_defaults = app_config.get("printer_defaults", {})
    card_size = printer_defaults.get("card_size_mm", {"width_mm": 85.6, "height_mm": 53.98})

    front_map = {}
    back_map = {}

    for image_path in images:
        name_lower = image_path.stem.lower()
        if name_lower.endswith("__front") or name_lower.endswith("_front") or name_lower.endswith("-front"):
            key = name_lower.replace("__front", "").replace("_front", "").replace("-front", "")
            front_map[key] = image_path
        elif name_lower.endswith("__back") or name_lower.endswith("_back") or name_lower.endswith("-back"):
            key = name_lower.replace("__back", "").replace("_back", "").replace("-back", "")
            back_map[key] = image_path

    paired_keys = sorted(set(front_map.keys()) & set(back_map.keys()))
    for key in paired_keys:
        jobs.append(
            PrintJob(
                source_path=front_map[key],
                printer_name=str(printer_defaults.get("printer_name") or default_printer_name).strip(),
                copies=max(1, int(printer_defaults.get("copies", 1))),
                duplex="true",
                fit_mode=str(printer_defaults.get("fit_mode", "fill")).lower(),
                rotate_degrees=normalizeDegrees(int(printer_defaults.get("rotate_degrees", 0))),
                card_size_mm=(float(card_size.get("width_mm", 85.6)), float(card_size.get("height_mm", 53.98))),
                front_path=front_map[key],
                back_path=back_map[key],
                form_name=str(printer_defaults.get("form_name", "CR80")),
                print_dpi=int(printer_defaults.get("print_dpi", 300)),
                auto_rotate=bool(printer_defaults.get("auto_rotate", True)),
                background_color_rgb=parseBackgroundColor(printer_defaults.get("background_color_rgb", [255, 255, 255])),
                printer_is_duplex=bool(printer_defaults.get("printer_is_duplex", False)),
            )
        )

    return jobs


def movePath(source_path: Path, destination_root: Path) -> Path:
    destination_root.mkdir(parents=True, exist_ok=True)
    destination_path = destination_root / source_path.name

    if destination_path.exists():
        destination_path = destination_root / f"{source_path.stem}_{int(time.time())}{source_path.suffix}"

    shutil.move(str(source_path), str(destination_path))
    return destination_path


def getPathFingerprint(path_item: Path) -> str:
    if path_item.is_file():
        stat_result = path_item.stat()
        return f"file::{path_item.resolve()}::{stat_result.st_size}::{int(stat_result.st_mtime_ns)}"

    manifest_path = path_item / "manifest.json"
    manifest_stamp = "missing"
    if manifest_path.exists():
        manifest_stat = manifest_path.stat()
        manifest_stamp = f"{manifest_stat.st_size}:{int(manifest_stat.st_mtime_ns)}"

    child_items = []
    for child_path in sorted(path_item.glob("*")):
        if child_path.is_file():
            child_stat = child_path.stat()
            child_items.append(f"{child_path.name}:{child_stat.st_size}:{int(child_stat.st_mtime_ns)}")

    return f"dir::{path_item.resolve()}::{manifest_stamp}::{len(child_items)}::{'|'.join(child_items)}"


def processJob(job: PrintJob, default_printer_name: str, done_path: Path, error_path: Path, move_source: bool = True) -> None:
    del error_path  # Intentionally unused inside this function.

    printer_name = (job.printer_name or default_printer_name).strip()
    if not printer_name:
        raise RuntimeError("No printer_name provided and no default_printer_name set.")

    logging.info(f"Printing job from: {job.source_path} -> printer: {printer_name}")

    pages: List[Image.Image] = []
    page_fit_modes: Optional[List[str]] = None
    page_rotate_degrees: Optional[List[int]] = None
    page_auto_rotate: Optional[List[bool]] = None

    use_static_back = bool(static_back_enabled)

    if job.pdf_path:
        pdf_images = renderPdfToImages(job.pdf_path, dpi=int(job.print_dpi))
        if len(pdf_images) == 0:
            raise RuntimeError(f"PDF has no pages: {job.pdf_path}")

        front_image = pdf_images[0]

        if use_static_back:
            try:
                back_image = getStaticBackImage()
            except Exception as exc:
                logging.exception(f"Static back is enabled but could not be loaded: {exc}")
                use_static_back = False

        if use_static_back:
            pages = [front_image, back_image]
            page_fit_modes = [job.fit_mode, str(static_back_fit_mode).lower()]
            page_rotate_degrees = [job.rotate_degrees, normalizeDegrees(int(static_back_rotate_degrees))]
            page_auto_rotate = [job.auto_rotate, bool(static_back_auto_rotate)]
        else:
            if job.duplex == "false":
                pages = [front_image]
            else:
                pages = pdf_images[:2] if len(pdf_images) >= 2 else [front_image]
    else:
        if not job.front_path:
            raise RuntimeError("Missing front_path and pdf_path.")

        waitForFileReady(job.front_path)
        with Image.open(job.front_path) as image_handle:
            front_image = image_handle.convert("RGB").copy()

        if use_static_back:
            try:
                back_image = getStaticBackImage()
            except Exception as exc:
                logging.exception(f"Static back is enabled but could not be loaded: {exc}")
                use_static_back = False

        if use_static_back:
            pages = [front_image, back_image]
            page_fit_modes = [job.fit_mode, str(static_back_fit_mode).lower()]
            page_rotate_degrees = [job.rotate_degrees, normalizeDegrees(int(static_back_rotate_degrees))]
            page_auto_rotate = [job.auto_rotate, bool(static_back_auto_rotate)]
        else:
            pages = [front_image]

            if job.back_path:
                waitForFileReady(job.back_path)
                with Image.open(job.back_path) as image_handle:
                    back_image_from_job = image_handle.convert("RGB").copy()
                if job.duplex in ("auto", "true"):
                    pages = [front_image, back_image_from_job]

    effective_duplex = resolveRequestedDuplex(
        duplex=job.duplex,
        page_count=len(pages),
        printer_is_duplex=job.printer_is_duplex,
    )

    printImagePages(
        printer_name=printer_name,
        pages=pages,
        copies=job.copies,
        fit_mode=job.fit_mode,
        rotate_degrees=job.rotate_degrees,
        card_size_mm=job.card_size_mm,
        form_name=job.form_name,
        print_dpi=job.print_dpi,
        duplex=effective_duplex,
        auto_rotate=job.auto_rotate,
        background_color_rgb=job.background_color_rgb,
        page_fit_modes=page_fit_modes,
        page_rotate_degrees=page_rotate_degrees,
        page_auto_rotate=page_auto_rotate,
    )

    if move_source:
        movePath(job.source_path, done_path)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", default=r"C:\card_hotfolder", help="Hotfolder root path")
    parser.add_argument("--printer", default="", help="Default printer name (Windows)")
    parser.add_argument("--poll_seconds", type=float, default=0.8, help="Polling interval")
    parser.add_argument("--list_printers", action="store_true", help="List installed printers and exit")

    # JSON config layer.
    parser.add_argument(
        "--config_json",
        default="",
        help="Path to editable app config JSON. Default: <root>\\config\\app_config.json",
    )
    parser.add_argument(
        "--write_default_config",
        action="store_true",
        help="Write default JSON config and exit",
    )

    # Template print mode.
    parser.add_argument(
        "--print_template",
        action="store_true",
        help="Print template images defined in JSON and exit",
    )
    parser.add_argument(
        "--template_mode",
        default="",
        choices=["", "front", "front_back"],
        help="Override template mode: front | front_back",
    )

    args = parser.parse_args()

    root_path = Path(args.root)
    incoming_path = root_path / "in"
    done_path = root_path / "done"
    error_path = root_path / "error"
    log_path = root_path / "logs"

    setupLogging(log_path)

    config_file_path = Path(args.config_json) if str(args.config_json).strip() else (root_path / "config" / "app_config.json")

    if args.write_default_config:
        saveJsonFile(config_file_path, DEFAULT_APP_CONFIG)
        print(f"Default config written to: {config_file_path}")
        return

    app_config = ensureAppConfig(config_file_path)
    applyRuntimeConfig(app_config)

    if args.list_printers:
        for printer in listPrinters():
            print(printer)
        return

    default_printer_name = str(args.printer).strip()
    if not default_printer_name:
        default_printer_name = str(app_config.get("printer_defaults", {}).get("printer_name", "")).strip()

    incoming_path.mkdir(parents=True, exist_ok=True)
    done_path.mkdir(parents=True, exist_ok=True)
    error_path.mkdir(parents=True, exist_ok=True)

    if args.print_template:
        try:
            template_job = buildTemplateJob(
                app_config=app_config,
                default_printer_name=default_printer_name,
                root_path=root_path,
                template_mode=(args.template_mode or None),
            )
            processJob(
                job=template_job,
                default_printer_name=default_printer_name,
                done_path=done_path,
                error_path=error_path,
                move_source=False,
            )
            logging.info("Template print completed successfully.")
        except Exception as exc:
            logging.exception(f"Template print failed: {exc}")
            raise
        return

    logging.info(f"Hotfolder started. Watching: {incoming_path}")
    logging.info(f"Config JSON: {config_file_path}")

    if default_printer_name:
        logging.info(f"Default printer: {default_printer_name}")
    else:
        logging.info("No default printer set. Use --printer, config JSON, or manifest.json printer_name.")

    processed_fingerprints = set()
    job_detection = app_config.get("job_detection", {})

    while True:
        try:
            # 1) Process job folders with manifest.json
            if bool(job_detection.get("enable_folder_manifest_jobs", True)):
                for folder_path in incoming_path.iterdir():
                    if not folder_path.is_dir():
                        continue

                    manifest_path = folder_path / "manifest.json"
                    if not manifest_path.exists():
                        continue

                    fingerprint = getPathFingerprint(folder_path)
                    if fingerprint in processed_fingerprints:
                        continue

                    try:
                        waitForFolderReady(folder_path)
                        manifest = loadManifest(manifest_path)
                        job = buildJobFromManifest(folder_path, manifest, app_config, default_printer_name)
                        processJob(job, default_printer_name, done_path, error_path)
                        processed_fingerprints.add(fingerprint)
                    except Exception as exc:
                        logging.exception(f"Folder job failed: {folder_path} ({exc})")
                        movePath(folder_path, error_path)
                        processed_fingerprints.add(fingerprint)

            # 2) Process paired front/back files (no folder)
            if bool(job_detection.get("enable_named_front_back_jobs", True)):
                paired_jobs = detectFrontBackByName(incoming_path, app_config, default_printer_name)
                for job in paired_jobs:
                    if not job.front_path or not job.back_path:
                        continue

                    pair_fingerprint = f"{getPathFingerprint(job.front_path)}||{getPathFingerprint(job.back_path)}"
                    if pair_fingerprint in processed_fingerprints:
                        continue

                    try:
                        processJob(
                            job=job,
                            default_printer_name=default_printer_name,
                            done_path=done_path,
                            error_path=error_path,
                            move_source=False,
                        )
                        if job.front_path.exists():
                            movePath(job.front_path, done_path)
                        if job.back_path.exists():
                            movePath(job.back_path, done_path)
                        processed_fingerprints.add(pair_fingerprint)
                    except Exception as exc:
                        logging.exception(f"Paired job failed: {job.front_path} | {job.back_path} ({exc})")
                        if job.front_path.exists():
                            movePath(job.front_path, error_path)
                        if job.back_path.exists():
                            movePath(job.back_path, error_path)
                        processed_fingerprints.add(pair_fingerprint)

            # 3) Process single files (images or PDFs) in root
            if bool(job_detection.get("enable_single_file_jobs", True)):
                for file_path in incoming_path.iterdir():
                    if not file_path.is_file():
                        continue

                    file_fingerprint = getPathFingerprint(file_path)
                    if file_fingerprint in processed_fingerprints:
                        continue

                    suffix = file_path.suffix.lower()
                    if suffix not in {".png", ".jpg", ".jpeg", ".bmp", ".tif", ".tiff", ".pdf"}:
                        continue

                    stem_lower = file_path.stem.lower()
                    if stem_lower.endswith("__front") or stem_lower.endswith("_front") or stem_lower.endswith("-front"):
                        continue
                    if stem_lower.endswith("__back") or stem_lower.endswith("_back") or stem_lower.endswith("-back"):
                        continue

                    try:
                        job = buildJobFromSingleFile(file_path, app_config, default_printer_name)
                        processJob(job, default_printer_name, done_path, error_path)
                        processed_fingerprints.add(file_fingerprint)
                    except Exception as exc:
                        logging.exception(f"File job failed: {file_path} ({exc})")
                        movePath(file_path, error_path)
                        processed_fingerprints.add(file_fingerprint)

        except Exception as exc:
            logging.exception(f"Loop error: {exc}")

        time.sleep(max(0.2, float(args.poll_seconds)))


if __name__ == "__main__":
    main()
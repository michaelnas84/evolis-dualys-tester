import argparse
import json
import logging
import os
import shutil
import sys
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Optional, List, Tuple

import win32con
import win32print
import win32ui
from PIL import Image, ImageOps, ImageWin


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
    # Wait until folder contents settle (basic heuristic).
    last_snapshot = None
    for _ in range(max_attempts):
        try:
            snapshot = sorted([(p.name, p.stat().st_size) for p in folder_path.glob("*") if p.is_file()])
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


def normalizeDegrees(value: int) -> int:
    value = int(value) % 360
    if value not in (0, 90, 180, 270):
        raise ValueError("rotate_degrees must be one of: 0, 90, 180, 270")
    return value


def mmToInches(mm_value: float) -> float:
    return float(mm_value) / 25.4


def prepareImageForCard(
    image: Image.Image,
    target_width_px: int,
    target_height_px: int,
    fit_mode: str,
    rotate_degrees: int,
) -> Image.Image:
    if rotate_degrees != 0:
        image = image.rotate(rotate_degrees, expand=True)

    image = image.convert("RGB")

    if fit_mode == "stretch":
        return image.resize((target_width_px, target_height_px), Image.Resampling.LANCZOS)

    if fit_mode == "contain":
        # Letterbox as needed
        return ImageOps.contain(image, (target_width_px, target_height_px), Image.Resampling.LANCZOS)

    if fit_mode == "fill":
        # Crop to fill the whole card
        return ImageOps.fit(image, (target_width_px, target_height_px), Image.Resampling.LANCZOS, centering=(0.5, 0.5))

    raise ValueError("fit_mode must be one of: contain, fill, stretch")


def getPrinterCaps(printer_name: str) -> Tuple[int, int, int, int]:
    device_context = win32ui.CreateDC()
    device_context.CreatePrinterDC(printer_name)

    printable_width_px = device_context.GetDeviceCaps(win32con.HORZRES)
    printable_height_px = device_context.GetDeviceCaps(win32con.VERTRES)
    dpi_x = device_context.GetDeviceCaps(win32con.LOGPIXELSX)
    dpi_y = device_context.GetDeviceCaps(win32con.LOGPIXELSY)

    device_context.DeleteDC()
    return printable_width_px, printable_height_px, dpi_x, dpi_y


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


def printImagePages(
    printer_name: str,
    pages: List[Image.Image],
    copies: int,
    fit_mode: str,
    rotate_degrees: int,
    card_size_mm: Tuple[float, float],
) -> None:
    printable_width_px, printable_height_px, dpi_x, dpi_y = getPrinterCaps(printer_name)

    card_width_in = mmToInches(card_size_mm[0])
    card_height_in = mmToInches(card_size_mm[1])

    target_width_px = int(round(card_width_in * dpi_x))
    target_height_px = int(round(card_height_in * dpi_y))

    # Fallback: if driver reports weird DPI/caps, use printable area.
    if target_width_px <= 0 or target_height_px <= 0:
        target_width_px = printable_width_px
        target_height_px = printable_height_px

    device_context = win32ui.CreateDC()
    device_context.CreatePrinterDC(printer_name)

    for copy_index in range(max(1, copies)):
        device_context.StartDoc(f"CardJob_{int(time.time())}_{copy_index+1}")

        for page_image in pages:
            device_context.StartPage()

            prepared = prepareImageForCard(
                image=page_image,
                target_width_px=target_width_px,
                target_height_px=target_height_px,
                fit_mode=fit_mode,
                rotate_degrees=rotate_degrees,
            )

            # Center inside printable area
            left = int((printable_width_px - target_width_px) / 2)
            top = int((printable_height_px - target_height_px) / 2)
            right = left + target_width_px
            bottom = top + target_height_px

            dib = ImageWin.Dib(prepared)
            dib.draw(device_context.GetHandleOutput(), (left, top, right, bottom))

            device_context.EndPage()

        device_context.EndDoc()

    device_context.DeleteDC()


def buildJobFromManifest(job_folder_path: Path, manifest: dict, default_printer_name: str) -> PrintJob:
    printer_name = str(manifest.get("printer_name") or default_printer_name)
    copies = int(manifest.get("copies", 1))
    duplex = str(manifest.get("duplex", "auto")).lower()
    fit_mode = str(manifest.get("fit_mode", "fill")).lower()
    rotate_degrees = normalizeDegrees(int(manifest.get("rotate_degrees", 0)))

    card_size = manifest.get("card_size_mm", {"width_mm": 85.6, "height_mm": 53.98})
    card_width_mm = float(card_size.get("width_mm", 85.6))
    card_height_mm = float(card_size.get("height_mm", 53.98))

    front_file = manifest.get("front_file")
    back_file = manifest.get("back_file")
    pdf_file = manifest.get("pdf_file")

    front_path = (job_folder_path / front_file) if front_file else None
    back_path = (job_folder_path / back_file) if back_file else None
    pdf_path = (job_folder_path / pdf_file) if pdf_file else None

    return PrintJob(
        source_path=job_folder_path,
        printer_name=printer_name,
        copies=max(1, copies),
        duplex=duplex,
        fit_mode=fit_mode,
        rotate_degrees=rotate_degrees,
        card_size_mm=(card_width_mm, card_height_mm),
        front_path=front_path,
        back_path=back_path,
        pdf_path=pdf_path,
    )


def detectFrontBackByName(incoming_path: Path) -> List[PrintJob]:
    jobs: List[PrintJob] = []
    images = [p for p in incoming_path.iterdir() if p.is_file() and p.suffix.lower() in {".png", ".jpg", ".jpeg", ".bmp", ".tif", ".tiff"}]

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
                source_path=incoming_path,
                printer_name="",
                copies=1,
                duplex="true",
                fit_mode="fill",
                rotate_degrees=0,
                card_size_mm=(85.6, 53.98),
                front_path=front_map[key],
                back_path=back_map[key],
            )
        )

    return jobs


def movePath(source_path: Path, destination_root: Path) -> Path:
    destination_root.mkdir(parents=True, exist_ok=True)
    destination_path = destination_root / source_path.name

    # Ensure unique destination
    if destination_path.exists():
        destination_path = destination_root / f"{source_path.stem}_{int(time.time())}{source_path.suffix}"

    shutil.move(str(source_path), str(destination_path))
    return destination_path


def processJob(job: PrintJob, default_printer_name: str, done_path: Path, error_path: Path) -> None:
    printer_name = job.printer_name or default_printer_name
    if not printer_name:
        raise RuntimeError("No printer_name provided and no default_printer_name set.")

    logging.info(f"Printing job from: {job.source_path} -> printer: {printer_name}")

    pages: List[Image.Image] = []

    if job.pdf_path:
        pdf_images = renderPdfToImages(job.pdf_path, dpi=300)
        if len(pdf_images) == 0:
            raise RuntimeError(f"PDF has no pages: {job.pdf_path}")

        if job.duplex == "false":
            pages = [pdf_images[0]]
        else:
            # auto/true: use 2 pages if available
            pages = pdf_images[:2] if len(pdf_images) >= 2 else [pdf_images[0]]

    else:
        if not job.front_path:
            raise RuntimeError("Missing front_path and pdf_path.")

        waitForFileReady(job.front_path)
        front_image = Image.open(job.front_path)

        pages = [front_image]

        if job.back_path:
            waitForFileReady(job.back_path)
            back_image = Image.open(job.back_path)

            if job.duplex in ("auto", "true"):
                pages = [front_image, back_image]

    printImagePages(
        printer_name=printer_name,
        pages=pages,
        copies=job.copies,
        fit_mode=job.fit_mode,
        rotate_degrees=job.rotate_degrees,
        card_size_mm=job.card_size_mm,
    )

    # Move source to done
    if job.source_path.is_dir():
        movePath(job.source_path, done_path)
    else:
        movePath(job.source_path, done_path)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", default=r"C:\card_hotfolder", help="Hotfolder root path")
    parser.add_argument("--printer", default="", help="Default printer name (Windows)")
    parser.add_argument("--poll_seconds", type=float, default=0.8, help="Polling interval")
    parser.add_argument("--list_printers", action="store_true", help="List installed printers and exit")

    args = parser.parse_args()

    root_path = Path(args.root)
    incoming_path = root_path / "in"
    done_path = root_path / "done"
    error_path = root_path / "error"
    log_path = root_path / "logs"

    setupLogging(log_path)

    if args.list_printers:
        for printer in listPrinters():
            print(printer)
        return

    default_printer_name = str(args.printer).strip()

    incoming_path.mkdir(parents=True, exist_ok=True)
    done_path.mkdir(parents=True, exist_ok=True)
    error_path.mkdir(parents=True, exist_ok=True)

    logging.info(f"Hotfolder started. Watching: {incoming_path}")
    if default_printer_name:
        logging.info(f"Default printer: {default_printer_name}")
    else:
        logging.info("No default printer set. Use --printer or manifest.json printer_name.")

    processed_paths = set()

    while True:
        try:
            # 1) Process job folders with manifest.json
            for folder_path in incoming_path.iterdir():
                if not folder_path.is_dir():
                    continue

                manifest_path = folder_path / "manifest.json"
                if not manifest_path.exists():
                    continue

                if str(folder_path) in processed_paths:
                    continue

                try:
                    waitForFolderReady(folder_path)
                    manifest = loadManifest(manifest_path)
                    job = buildJobFromManifest(folder_path, manifest, default_printer_name)
                    processJob(job, default_printer_name, done_path, error_path)
                    processed_paths.add(str(folder_path))
                except Exception as exc:
                    logging.exception(f"Folder job failed: {folder_path} ({exc})")
                    movePath(folder_path, error_path)
                    processed_paths.add(str(folder_path))

            # 2) Process paired front/back files (no folder)
            paired_jobs = detectFrontBackByName(incoming_path)
            for job in paired_jobs:
                key = f"{job.front_path}|{job.back_path}"
                if key in processed_paths:
                    continue

                try:
                    job.printer_name = default_printer_name
                    processJob(job, default_printer_name, done_path, error_path)
                    # After printing, move the two files too
                    if job.front_path and job.front_path.exists():
                        movePath(job.front_path, done_path)
                    if job.back_path and job.back_path.exists():
                        movePath(job.back_path, done_path)
                    processed_paths.add(key)
                except Exception as exc:
                    logging.exception(f"Paired job failed: {key} ({exc})")
                    if job.front_path and job.front_path.exists():
                        movePath(job.front_path, error_path)
                    if job.back_path and job.back_path.exists():
                        movePath(job.back_path, error_path)
                    processed_paths.add(key)

            # 3) Process single files (images or PDFs) in root
            for file_path in incoming_path.iterdir():
                if not file_path.is_file():
                    continue

                if str(file_path) in processed_paths:
                    continue

                suffix = file_path.suffix.lower()
                if suffix not in {".png", ".jpg", ".jpeg", ".bmp", ".tif", ".tiff", ".pdf"}:
                    continue

                # Skip front/back markers (handled above)
                stem_lower = file_path.stem.lower()
                if stem_lower.endswith("__front") or stem_lower.endswith("_front") or stem_lower.endswith("-front"):
                    continue
                if stem_lower.endswith("__back") or stem_lower.endswith("_back") or stem_lower.endswith("-back"):
                    continue

                try:
                    if suffix == ".pdf":
                        job = PrintJob(
                            source_path=file_path,
                            printer_name=default_printer_name,
                            copies=1,
                            duplex="auto",
                            fit_mode="fill",
                            rotate_degrees=0,
                            card_size_mm=(85.6, 53.98),
                            pdf_path=file_path,
                        )
                    else:
                        job = PrintJob(
                            source_path=file_path,
                            printer_name=default_printer_name,
                            copies=1,
                            duplex="false",
                            fit_mode="fill",
                            rotate_degrees=0,
                            card_size_mm=(85.6, 53.98),
                            front_path=file_path,
                        )

                    processJob(job, default_printer_name, done_path, error_path)
                    processed_paths.add(str(file_path))
                except Exception as exc:
                    logging.exception(f"File job failed: {file_path} ({exc})")
                    movePath(file_path, error_path)
                    processed_paths.add(str(file_path))

        except Exception as exc:
            logging.exception(f"Loop error: {exc}")

        time.sleep(max(0.2, float(args.poll_seconds)))


if __name__ == "__main__":
    main()
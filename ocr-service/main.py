"""
FastAPI + PaddleOCR microservice with OpenCV image preprocessing.

Exposes:
  GET  /health         -> liveness check
  POST /ocr             -> accepts an image or PDF, preprocesses it with OpenCV,
                           then runs PaddleOCR and returns extracted text

Preprocessing steps (configurable via env vars):
  - Resize if longest side exceeds PREPROCESS_MAX_SIZE (default 4000)
  - Convert to grayscale (PREPROCESS_GRAYSCALE, default true)
  - Denoise (PREPROCESS_DENOISE, default true)
  - Adaptive threshold / binarization (PREPROCESS_BINARIZE, default true)
  - Deskew (PREPROCESS_DESKEW, default true)

This service is intentionally stateless: it does not know anything about
"orders" or the Laravel app's schema. It only turns pixels into text so it
can be reused by any other consumer later.
"""

import io
import logging
import os
import tempfile
from typing import List

import cv2
import numpy as np
from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.responses import JSONResponse
from PIL import Image

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("ocr-service")

app = FastAPI(title="PaddleOCR Service", version="1.1.0")

SUPPORTED_IMAGE_TYPES = {"image/jpeg", "image/jpg", "image/png", "image/webp"}
SUPPORTED_PDF_TYPE = "application/pdf"

# Lazily initialised so the container starts fast and model weights are
# only downloaded/loaded on first real request (and once per process).
_ocr_engine = None


# ── Preprocessing helpers ──────────────────────────────────────────────


def _compute_skew_angle(image: np.ndarray) -> float:
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY) if len(image.shape) == 3 else image
    gray = cv2.bitwise_not(gray)
    thresh = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY | cv2.THRESH_OTSU)[1]
    coords = np.column_stack(np.where(thresh > 0))
    if len(coords) < 10:
        return 0.0
    angle = cv2.minAreaRect(coords)[-1]
    if angle < -45:
        angle = 90 + angle
    return -angle


def _deskew(image: np.ndarray) -> np.ndarray:
    angle = _compute_skew_angle(image)
    if abs(angle) < 0.5:
        return image
    h, w = image.shape[:2]
    center = (w // 2, h // 2)
    matrix = cv2.getRotationMatrix2D(center, angle, 1.0)
    cos = abs(matrix[0, 0])
    sin = abs(matrix[0, 1])
    new_w = int((h * sin) + (w * cos))
    new_h = int((h * cos) + (w * sin))
    matrix[0, 2] += (new_w / 2) - center[0]
    matrix[1, 2] += (new_h / 2) - center[1]
    return cv2.warpAffine(
        image, matrix, (new_w, new_h),
        flags=cv2.INTER_CUBIC,
        borderMode=cv2.BORDER_REPLICATE,
    )


def _resize_if_needed(image: np.ndarray, max_size: int = 4000) -> np.ndarray:
    h, w = image.shape[:2]
    longest = max(h, w)
    if longest <= max_size:
        return image
    scale = max_size / longest
    new_w = int(w * scale)
    new_h = int(h * scale)
    return cv2.resize(image, (new_w, new_h), interpolation=cv2.INTER_AREA)


def preprocess_image(image: np.ndarray) -> np.ndarray:
    """
    Apply OpenCV preprocessing steps to improve OCR accuracy.

    Every step can be disabled via environment variable:
      PREPROCESS_ENABLED, PREPROCESS_GRAYSCALE, PREPROCESS_DENOISE,
      PREPROCESS_BINARIZE, PREPROCESS_DESKEW, PREPROCESS_MAX_SIZE
    """
    enabled = os.getenv("PREPROCESS_ENABLED", "true").lower() in ("1", "true", "yes")
    if not enabled:
        return image

    max_size = int(os.getenv("PREPROCESS_MAX_SIZE", "4000"))
    do_grayscale = os.getenv("PREPROCESS_GRAYSCALE", "true").lower() in ("1", "true", "yes")
    do_denoise = os.getenv("PREPROCESS_DENOISE", "true").lower() in ("1", "true", "yes")
    do_binarize = os.getenv("PREPROCESS_BINARIZE", "true").lower() in ("1", "true", "yes")
    do_deskew = os.getenv("PREPROCESS_DESKEW", "true").lower() in ("1", "true", "yes")

    image = _resize_if_needed(image, max_size)

    if do_grayscale or do_binarize:
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    else:
        gray = image

    if do_denoise:
        gray = cv2.fastNlMeansDenoising(gray, h=30)

    if do_binarize:
        gray = cv2.adaptiveThreshold(
            gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
            cv2.THRESH_BINARY, 31, 2,
        )

    result = gray

    if do_deskew:
        if do_binarize:
            color_for_deskew = cv2.cvtColor(result, cv2.COLOR_GRAY2BGR)
            result = cv2.cvtColor(_deskew(color_for_deskew), cv2.COLOR_BGR2GRAY)
        else:
            result = _deskew(result)

    return result


# ── OCR core ────────────────────────────────────────────────────────────


def get_ocr_engine():
    global _ocr_engine
    if _ocr_engine is None:
        from paddleocr import PaddleOCR

        lang = os.getenv("OCR_LANG", "japan")
        logger.info("Loading PaddleOCR model (lang=%s)...", lang)
        _ocr_engine = PaddleOCR(use_angle_cls=True, lang=lang, show_log=False)
    return _ocr_engine


def image_to_lines(image: np.ndarray) -> List[dict]:
    """Preprocess image, then run PaddleOCR and return lines."""
    processed = preprocess_image(image)
    engine = get_ocr_engine()
    result = engine.ocr(processed, cls=True)

    lines = []
    if result and result[0]:
        for detection in result[0]:
            box, (text, confidence) = detection
            lines.append({
                "text": text,
                "confidence": float(confidence),
                "box": box,
            })
    return lines


def pdf_to_images(pdf_bytes: bytes) -> List[np.ndarray]:
    """Convert every page of a PDF into an RGB numpy array image."""
    try:
        from pdf2image import convert_from_bytes
        pages = convert_from_bytes(pdf_bytes, dpi=200)
    except Exception as e:
        if "poppler" in str(e).lower():
            raise HTTPException(
                status_code=400,
                detail="PDF support requires poppler-utils. Run via Docker (pre-installed) or install poppler: "
                       "https://github.com/oschwartz10612/poppler-windows/releases",
            )
        raise HTTPException(status_code=400, detail=f"Failed to process PDF: {e}")
    return [np.array(page.convert("RGB")) for page in pages]


# ── Endpoints ───────────────────────────────────────────────────────────


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/ocr")
async def ocr(file: UploadFile = File(...)):
    content_type = file.content_type
    raw = await file.read()

    if not raw:
        raise HTTPException(status_code=400, detail="Uploaded file is empty.")

    try:
        if content_type == SUPPORTED_PDF_TYPE or file.filename.lower().endswith(".pdf"):
            images = pdf_to_images(raw)
        elif content_type in SUPPORTED_IMAGE_TYPES or file.filename.lower().endswith(
            (".jpg", ".jpeg", ".png", ".webp")
        ):
            pil_image = Image.open(io.BytesIO(raw)).convert("RGB")
            images = [np.array(pil_image)]
        else:
            raise HTTPException(
                status_code=415,
                detail=f"Unsupported content type: {content_type}",
            )
    except HTTPException:
        raise
    except Exception as exc:
        logger.exception("Failed to decode uploaded file")
        raise HTTPException(status_code=400, detail=f"Could not read file: {exc}") from exc

    all_lines: List[dict] = []
    try:
        for page_index, image in enumerate(images):
            page_lines = image_to_lines(image)
            for line in page_lines:
                line["page"] = page_index + 1
            all_lines.extend(page_lines)
    except Exception as exc:
        logger.exception("OCR processing failed")
        raise HTTPException(status_code=500, detail=f"OCR processing failed: {exc}") from exc

    full_text = "\n".join(line["text"] for line in all_lines)

    return JSONResponse({
        "text": full_text,
        "lines": all_lines,
        "pages": len(images),
    })

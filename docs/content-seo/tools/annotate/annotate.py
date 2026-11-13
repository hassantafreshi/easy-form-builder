#!/usr/bin/env python3
"""
annotate.py - turn a raw admin screenshot + a small JSON spec into an annotated
WebP that follows templates/images/IMAGE-STYLE-GUIDE.md.

Usage
    python3 annotate.py INPUT.png SPEC.json -o OUTPUT.webp [options]

Options
    -o, --output PATH      output file (default: INPUT stem + .webp next to the input)
    --quality N            WebP quality 1-100 (default: spec output.quality or 82)
    --max-width N          never export wider than N px (default: spec or 1600)
    --lossless             write lossless WebP (large; only for tiny fragments)
    --png-preview PATH     also write a PNG copy (handy for reviewing in a viewer)
    --strict               exit code 2 if the lint finds any warning
    --json                 print the lint report as JSON (default: human text)

Spec (JSON). Either a bare list of annotations, or an object:
    {
      "scale": 2,              # device scale factor of the capture (default 1)
      "units": "css",          # "css" (multiplied by scale) or "px" (raw pixels)
      "rtl": false,            # true for ar captures: mirrors "start/end" and badge corner
      "output": {"max_width": 1600, "quality": 82},
      "annotations": [ ... ]
    }

All coordinates refer to the RAW screenshot, even after a crop. The tool
translates them. Rectangles are [x, y, w, h]; points are [x, y].

Annotation types
    crop   {"type":"crop","coords":[x,y,w,h],"pad":0}
           At most one. Applied first. "pad" (same units) grows the rectangle.
    box    {"type":"box","coords":[x,y,w,h],"color":"primary|secondary",
            "style":"solid|dashed","pad":8,"label":"optional"}
    arrow  {"type":"arrow","target":[x,y,w,h],"from":"auto|start|end|top|bottom|<deg>",
            "length":0.17,"curve":0.18,"color":"primary|secondary","label":"optional"}
           target = the control to point at (tip stops 10u outside its border; preferred),
           or "to":[x,y] for an exact spot, or explicit "coords":[x1,y1,x2,y2] (tail -> tip).
           "from" = side the tail comes from; "length" is a fraction of the output width
           when < 5, else css/px.
    badge  {"type":"badge","label":"1","coords":[x,y]}            (centre)
           or {"type":"badge","label":"1","coords":[x,y,w,h]}      (sits on the box's
           start corner: top-left, or top-right when rtl)
    blur   {"type":"blur","coords":[x,y,w,h],"radius":null,"mode":"gaussian|pixelate"}
           Last-resort redaction of incidental private data. Prefer demo data at source.
    redact {"type":"redact","coords":[x,y,w,h]}                    solid bar (secrets, keys)
    zoom   {"type":"zoom","coords":[x,y,w,h],"factor":1.8,"at":[cx,cy],"diameter":null}
           Circular magnifier. By default it sits on the region it magnifies.

Layer order is fixed: crop -> blur/redact -> zoom -> box -> arrow -> badge, so
a badge is never hidden by an arrow and a blur is never drawn over an annotation.

Design tokens (final width 1600 px => unit u = 1 px; everything scales with width):
    colours   primary #D81B60, secondary #652EBA, ink #0A0E1C, halo #FFFFFF
    halo      3u white casing around every stroke/badge (keeps contrast on indigo/pink UI)
    arrow     shaft 10u, head 38u long / 40u wide, default length 17% of width, slight curve
    box       stroke 6u, radius 10u, pad 8u
    badge     diameter 56u, bold digits
    zoom      factor 1.8, white ring 8u + 2u grey edge, soft shadow
Requires: Python 3.9+, Pillow >= 10.1 with WebP support (pip install pillow).
"""
from __future__ import annotations

import argparse
import json
import math
import os
import sys
from typing import Any

from PIL import Image, ImageChops, ImageDraw, ImageFilter, ImageFont

# --------------------------------------------------------------------------- tokens
COLORS = {
    "primary": (216, 27, 96),     # #D81B60  theme --brand-pink-strong
    "secondary": (101, 46, 186),  # #652EBA  theme --brand-purple
    "ink": (10, 14, 28),          # #0A0E1C  theme --ink-1
    "halo": (255, 255, 255),
    "ring_edge": (184, 192, 204),
}
BASE_W = 1600          # reference width for the unit u
SS = 3                 # supersampling factor for crisp anti-aliased strokes
DEFAULT_MAX_W = 1600
DEFAULT_QUALITY = 82
MAX_ANNOTATIONS = 3    # style-guide limit (WPForms sample: 1.3 on average, 96% <= 2)
KB_SOFT_LIMIT = 250    # lint threshold for the exported file

VALID_TYPES = {"crop", "box", "arrow", "badge", "blur", "redact", "zoom"}
LAYER_ORDER = ["blur", "redact", "zoom", "box", "arrow", "badge"]
FONT_CANDIDATES = [
    "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
    "/usr/share/fonts/opentype/inter/Inter-Bold.otf",
    "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
    "/Library/Fonts/Arial Bold.ttf",
    "C:\\Windows\\Fonts\\arialbd.ttf",
]


WARNINGS: list[str] = []   # filled by drawing helpers, merged into the lint report


class SpecError(ValueError):
    """Raised for an invalid spec (message names the offending annotation)."""


# --------------------------------------------------------------------------- helpers
def _font(size: int) -> ImageFont.ImageFont:
    for path in FONT_CANDIDATES:
        if os.path.exists(path):
            try:
                return ImageFont.truetype(path, size)
            except OSError:
                continue
    return ImageFont.load_default(size=size)  # Pillow >= 10.1: scalable built-in font


def _rgba(name_or_rgb: Any, alpha: int = 255) -> tuple[int, int, int, int]:
    rgb = COLORS.get(name_or_rgb, name_or_rgb) if isinstance(name_or_rgb, str) else name_or_rgb
    if isinstance(rgb, str):
        raise SpecError(f"unknown colour '{name_or_rgb}' (use primary|secondary)")
    return (int(rgb[0]), int(rgb[1]), int(rgb[2]), alpha)


def _num_list(ann: dict, key: str, n: tuple[int, ...]) -> list[float]:
    v = ann.get(key)
    if not isinstance(v, (list, tuple)) or len(v) not in n or not all(isinstance(x, (int, float)) for x in v):
        raise SpecError(f"{ann.get('type')}: '{key}' must be a list of {' or '.join(map(str, n))} numbers, got {v!r}")
    return [float(x) for x in v]


class Geometry:
    """Maps raw-screenshot coordinates to output pixels (crop + resize)."""

    def __init__(self, scale: float, units: str, crop_px: tuple[int, int, int, int], out_w: int, out_h: int):
        self.k = scale if units == "css" else 1.0
        self.cx, self.cy, self.cw, self.ch = crop_px
        self.f = out_w / self.cw                      # raw px -> output px
        self.out_w, self.out_h = out_w, out_h
        self.u = out_w / BASE_W                       # design unit

    def pt(self, x: float, y: float) -> tuple[float, float]:
        return ((x * self.k - self.cx) * self.f, (y * self.k - self.cy) * self.f)

    def rect(self, r: list[float]) -> tuple[float, float, float, float]:
        x0, y0 = self.pt(r[0], r[1])
        x1, y1 = self.pt(r[0] + r[2], r[1] + r[3])
        return (x0, y0, x1, y1)

    def length(self, v: float) -> float:
        """A length given as a fraction (<5) of output width, or as css/px units."""
        return v * self.out_w if v < 5 else v * self.k * self.f


# --------------------------------------------------------------------------- overlay (supersampled)
class Overlay:
    """RGBA layer drawn at SS x resolution, flattened with LANCZOS for smooth edges."""

    def __init__(self, w: int, h: int):
        self.w, self.h = w, h
        self.im = Image.new("RGBA", (w * SS, h * SS), (0, 0, 0, 0))
        self.d = ImageDraw.Draw(self.im)

    def s(self, v: float) -> float:
        return v * SS

    def flatten(self) -> Image.Image:
        return self.im.resize((self.w, self.h), Image.LANCZOS)


def _bezier(p0, p1, p2, n=48):
    pts = []
    for i in range(n + 1):
        t = i / n
        x = (1 - t) ** 2 * p0[0] + 2 * (1 - t) * t * p1[0] + t * t * p2[0]
        y = (1 - t) ** 2 * p0[1] + 2 * (1 - t) * t * p1[1] + t * t * p2[1]
        pts.append((x, y))
    return pts


def _line(ov: Overlay, pts, width: float, fill):
    sp = [(ov.s(x), ov.s(y)) for x, y in pts]
    ov.d.line(sp, fill=fill, width=max(1, int(ov.s(width))), joint="curve")
    r = ov.s(width) / 2
    for x, y in (sp[0], sp[-1]):
        ov.d.ellipse([x - r, y - r, x + r, y + r], fill=fill)


def _rounded(ov: Overlay, rect, radius: float, width: float, fill, dashed=False):
    x0, y0, x1, y1 = [ov.s(v) for v in rect]
    if not dashed:
        ov.d.rounded_rectangle([x0, y0, x1, y1], radius=ov.s(radius), outline=fill, width=max(1, int(ov.s(width))))
        return
    # dashed: draw the four sides as dash runs (corner radius ignored for dashes)
    dash, gap = ov.s(width * 3.2), ov.s(width * 2.0)
    for (ax, ay, bx, by) in ((x0, y0, x1, y0), (x1, y0, x1, y1), (x1, y1, x0, y1), (x0, y1, x0, y0)):
        length = math.hypot(bx - ax, by - ay)
        if length == 0:
            continue
        ux, uy = (bx - ax) / length, (by - ay) / length
        pos = 0.0
        while pos < length:
            end = min(pos + dash, length)
            ov.d.line([(ax + ux * pos, ay + uy * pos), (ax + ux * end, ay + uy * end)],
                      fill=fill, width=max(1, int(ov.s(width))))
            pos += dash + gap


# --------------------------------------------------------------------------- annotation drawing
def draw_box(ov: Overlay, g: Geometry, ann: dict, rtl: bool):
    r = _num_list(ann, "coords", (4,))
    x0, y0, x1, y1 = g.rect(r)
    # "pad" is given in the same units as the coordinates; default = 8u (8 px at 1600 px wide)
    p = float(ann["pad"]) * g.k * g.f if "pad" in ann else 8 * g.u
    rect = (x0 - p, y0 - p, x1 + p, y1 + p)
    colour = ann.get("color", "primary")
    width, halo = 6 * g.u, 3 * g.u
    dashed = ann.get("style", "solid") == "dashed"
    _rounded(ov, rect, 10 * g.u, width + 2 * halo, _rgba("halo"), dashed)   # white casing
    _rounded(ov, rect, 10 * g.u, width, _rgba(colour), dashed)
    return rect


def _auto_from(to, g: Geometry, length: float, rtl: bool) -> tuple[float, float]:
    """Pick the tail position: the free direction with the largest margin to the borders."""
    best, best_score = None, -1e9
    for deg in range(0, 360, 15):
        a = math.radians(deg)
        sx, sy = to[0] + math.cos(a) * length, to[1] + math.sin(a) * length
        margin = min(sx, sy, g.out_w - sx, g.out_h - sy)
        if margin < 0.04 * g.out_w:
            continue
        # house style: arrows come in from the reading-end side and slightly from below
        end_side = (math.cos(a) < 0) if rtl else (math.cos(a) > 0)
        score = margin + (0.06 * g.out_w if end_side else 0) + (0.03 * g.out_w if math.sin(a) > 0 else 0)
        if score > best_score:
            best, best_score = (sx, sy), score
    if best is None:  # tiny image: fall back to straight from below
        best = (to[0], min(g.out_h - 1, to[1] + length))
    return best


def _dir_from_spec(src, rtl: bool) -> float | None:
    table = {"start": 180 if not rtl else 0, "end": 0 if not rtl else 180, "top": 270, "bottom": 90}
    if src == "auto":
        return None
    if isinstance(src, (int, float)):
        return float(src)
    if src in table:
        return float(table[src])
    raise SpecError(f"arrow: 'from' must be auto|start|end|top|bottom|<degrees>, got {src!r}")


def _edge_distance(hw: float, hh: float, ux: float, uy: float) -> float:
    """Distance from a rectangle's centre to its border along the unit vector (ux, uy)."""
    tx = hw / abs(ux) if abs(ux) > 1e-9 else float("inf")
    ty = hh / abs(uy) if abs(uy) > 1e-9 else float("inf")
    return min(tx, ty)


def _resolve_arrow(ann: dict, g: Geometry, rtl: bool):
    """Return (tail, tip) in output px. Three ways to say where it goes:
       coords [x1,y1,x2,y2]  explicit tail -> tip
       target [x,y,w,h]      point at a control: the tip stops 10u outside its border (preferred)
       to [x,y]              point at an exact spot
    """
    halo_gap = 10 * g.u
    if "coords" in ann:
        c = _num_list(ann, "coords", (4,))
        return g.pt(c[0], c[1]), g.pt(c[2], c[3])
    length = g.length(float(ann.get("length", 0.17)))
    deg = _dir_from_spec(ann.get("from", "auto"), rtl)
    if "target" in ann:
        x0, y0, x1, y1 = g.rect(_num_list(ann, "target", (4,)))
        cx, cy, hw, hh = (x0 + x1) / 2, (y0 + y1) / 2, (x1 - x0) / 2, (y1 - y0) / 2

        def place(angle: float):
            a = math.radians(angle)
            ux, uy = math.cos(a), math.sin(a)
            r = _edge_distance(hw, hh, ux, uy) + halo_gap
            tip = (cx + ux * r, cy + uy * r)
            return (tip[0] + ux * length, tip[1] + uy * length), tip

        if deg is not None:
            return place(deg)
        best, best_score = None, -1e9
        for angle in range(0, 360, 15):
            tail, tip = place(angle)
            margin = min(tail[0], tail[1], g.out_w - tail[0], g.out_h - tail[1])
            if margin < 0.03 * g.out_w:
                continue
            a = math.radians(angle)
            end_side = (math.cos(a) < 0) if rtl else (math.cos(a) > 0)
            score = margin + (0.06 * g.out_w if end_side else 0) + (0.03 * g.out_w if math.sin(a) > 0 else 0)
            if score > best_score:
                best, best_score = (tail, tip), score
        return best if best else place(90)
    to = _num_list(ann, "to", (2,))
    tip = g.pt(*to)
    if deg is None:
        return _auto_from(tip, g, length, rtl), tip
    a = math.radians(deg)
    return (tip[0] + math.cos(a) * length, tip[1] + math.sin(a) * length), tip


def draw_arrow(ov: Overlay, g: Geometry, ann: dict, rtl: bool):
    shaft, head_len, head_w, halo = 10 * g.u, 38 * g.u, 40 * g.u, 3 * g.u
    tail, tip = _resolve_arrow(ann, g, rtl)
    dx, dy = tip[0] - tail[0], tip[1] - tail[1]
    dist = math.hypot(dx, dy)
    if dist < head_len * 1.6:
        raise SpecError(f"arrow: too short ({dist:.0f}px; needs >= {head_len * 1.6:.0f}px at this width). Use a longer 'length' or other coords")
    if not (0 <= tail[0] <= g.out_w and 0 <= tail[1] <= g.out_h):
        WARNINGS.append("an arrow tail starts outside the image; choose another 'from' side or a shorter 'length'")
    curve = float(ann.get("curve", 0.18))
    nx, ny = -dy / dist, dx / dist
    ctrl = ((tail[0] + tip[0]) / 2 + nx * curve * dist, (tail[1] + tip[1]) / 2 + ny * curve * dist)
    pts = _bezier(tail, ctrl, tip)
    # direction at the tip, and shaft end pulled back so the head covers the join
    tx, ty = tip[0] - pts[-4][0], tip[1] - pts[-4][1]
    tl = math.hypot(tx, ty) or 1
    ux, uy = tx / tl, ty / tl
    base = (tip[0] - ux * head_len, tip[1] - uy * head_len)
    shaft_end = (tip[0] - ux * head_len * 0.8, tip[1] - uy * head_len * 0.8)
    shaft_pts = [p for p in pts if math.hypot(p[0] - tip[0], p[1] - tip[1]) > head_len * 0.9] + [shaft_end]
    if len(shaft_pts) < 2:
        shaft_pts = [tail, shaft_end]
    colour = ann.get("color", "primary")
    left = (base[0] - uy * head_w / 2, base[1] + ux * head_w / 2)
    right = (base[0] + uy * head_w / 2, base[1] - ux * head_w / 2)
    # halo first (white casing), then colour
    _line(ov, shaft_pts, shaft + 2 * halo, _rgba("halo"))
    ov.d.polygon([(ov.s(x), ov.s(y)) for x, y in _grow_triangle(tip, left, right, halo)], fill=_rgba("halo"))
    _line(ov, shaft_pts, shaft, _rgba(colour))
    ov.d.polygon([(ov.s(x), ov.s(y)) for x, y in (tip, left, right)], fill=_rgba(colour))
    return tail, tip


def _grow_triangle(a, b, c, amount):
    cx, cy = (a[0] + b[0] + c[0]) / 3, (a[1] + b[1] + c[1]) / 3
    out = []
    for x, y in (a, b, c):
        vx, vy = x - cx, y - cy
        n = math.hypot(vx, vy) or 1
        out.append((x + vx / n * amount * 1.6, y + vy / n * amount * 1.6))
    return out


def draw_badge(ov: Overlay, g: Geometry, ann: dict, rtl: bool):
    label = str(ann.get("label", "1"))
    if not label or len(label) > 2:
        raise SpecError("badge: 'label' must be 1-2 characters (a step number)")
    c = _num_list(ann, "coords", (2, 4))
    if len(c) == 2:
        cx, cy = g.pt(c[0], c[1])
    else:
        x0, y0, x1, y1 = g.rect(c)
        cx, cy = (x1, y0) if rtl else (x0, y0)
    d = 56 * g.u
    r, halo = d / 2, 4 * g.u
    m = r + halo
    cx, cy = min(max(cx, m), g.out_w - m), min(max(cy, m), g.out_h - m)
    box = [ov.s(cx - r - halo), ov.s(cy - r - halo), ov.s(cx + r + halo), ov.s(cy + r + halo)]
    ov.d.ellipse(box, fill=_rgba("halo"))
    box = [ov.s(cx - r), ov.s(cy - r), ov.s(cx + r), ov.s(cy + r)]
    ov.d.ellipse(box, fill=_rgba(ann.get("color", "primary")))
    font = _font(int(ov.s(30 * g.u)))
    ov.d.text((ov.s(cx), ov.s(cy)), label, font=font, fill=(255, 255, 255, 255), anchor="mm")
    return (cx, cy)


def draw_label(ov: Overlay, g: Geometry, text: str, anchor: tuple[float, float]):
    """Optional short text pill. Discouraged by the style guide (it needs translating)."""
    font = _font(int(ov.s(24 * g.u)))
    pad = 10 * g.u
    bbox = ov.d.textbbox((0, 0), text, font=font)
    w, h = (bbox[2] - bbox[0]) / SS, (bbox[3] - bbox[1]) / SS
    x = min(max(anchor[0], pad), g.out_w - w - 2 * pad)
    y = min(max(anchor[1], pad), g.out_h - h - 2 * pad)
    rect = [ov.s(x), ov.s(y), ov.s(x + w + 2 * pad), ov.s(y + h + 2 * pad)]
    ov.d.rounded_rectangle([rect[0] - ov.s(3 * g.u), rect[1] - ov.s(3 * g.u), rect[2] + ov.s(3 * g.u), rect[3] + ov.s(3 * g.u)],
                           radius=ov.s(12 * g.u), fill=_rgba("halo"))
    ov.d.rounded_rectangle(rect, radius=ov.s(10 * g.u), fill=_rgba("ink"))
    ov.d.text((ov.s(x + pad), ov.s(y + pad) - bbox[1]), text, font=font, fill=(255, 255, 255, 255))


# --------------------------------------------------------------------------- pixel-level ops
def apply_blur(base: Image.Image, g: Geometry, ann: dict):
    r = _num_list(ann, "coords", (4,))
    x0, y0, x1, y1 = [int(round(v)) for v in g.rect(r)]
    x0, y0 = max(0, x0), max(0, y0)
    x1, y1 = min(base.width, x1), min(base.height, y1)
    if x1 - x0 < 2 or y1 - y0 < 2:
        raise SpecError("blur: region is empty or outside the image")
    radius = ann.get("radius") or min(60 * g.u, max(10 * g.u, 0.5 * (y1 - y0)))
    mode = ann.get("mode", "gaussian")
    if mode == "pixelate":
        region = base.crop((x0, y0, x1, y1))
        block = max(6, int(radius))
        small = region.resize((max(1, region.width // block), max(1, region.height // block)), Image.BILINEAR)
        base.paste(small.resize(region.size, Image.NEAREST), (x0, y0))
    elif mode == "gaussian":
        # blur a margin around the region and cut the centre back out, so the edges blend with the
        # surroundings instead of repeating the outermost pixel column (which would leak structure)
        m = int(radius * 2)
        ex0, ey0, ex1, ey1 = max(0, x0 - m), max(0, y0 - m), min(base.width, x1 + m), min(base.height, y1 + m)
        big = base.crop((ex0, ey0, ex1, ey1)).filter(ImageFilter.GaussianBlur(radius)).filter(ImageFilter.GaussianBlur(radius / 2))
        base.paste(big.crop((x0 - ex0, y0 - ey0, x1 - ex0, y1 - ey0)), (x0, y0))
    else:
        raise SpecError(f"blur: mode must be gaussian|pixelate, got {mode!r}")


def apply_redact(base: Image.Image, g: Geometry, ann: dict):
    r = _num_list(ann, "coords", (4,))
    x0, y0, x1, y1 = g.rect(r)
    d = ImageDraw.Draw(base)
    d.rounded_rectangle([x0, y0, x1, y1], radius=6 * g.u, fill=(231, 233, 239))
    # neutral dots: reads as "hidden value" and carries no information about the original length
    cy, h = (y0 + y1) / 2, min(y1 - y0, 40 * g.u)
    n = max(4, min(12, int((x1 - x0) / (h * 1.1))))
    step = h * 0.55
    start = (x0 + x1) / 2 - step * (n - 1) / 2
    for i in range(n):
        cx = start + i * step
        d.ellipse([cx - h * 0.1, cy - h * 0.1, cx + h * 0.1, cy + h * 0.1], fill=(120, 128, 144))


def apply_zoom(base: Image.Image, hi: Image.Image, g: Geometry, ann: dict):
    r = _num_list(ann, "coords", (4,))
    factor = float(ann.get("factor", 1.8))
    if not 1.2 <= factor <= 3.0:
        raise SpecError("zoom: factor must be between 1.2 and 3.0")
    x0, y0, x1, y1 = g.rect(r)
    centre = g.pt(*_num_list(ann, "at", (2,))) if "at" in ann else ((x0 + x1) / 2, (y0 + y1) / 2)
    src_centre = ((x0 + x1) / 2, (y0 + y1) / 2)
    region = max(x1 - x0, y1 - y0)
    d = ann.get("diameter")
    diameter = g.length(float(d)) if d else region * factor * 1.15
    diameter = int(min(max(diameter, 0.18 * g.out_w), 0.42 * g.out_w))
    side = diameter / factor                                  # source side in output px
    side_hi = side / g.f                                      # ... in crop px (hi-res)
    sx, sy = src_centre[0] / g.f, src_centre[1] / g.f
    left = min(max(sx - side_hi / 2, 0), max(0, hi.width - side_hi))      # keep the source tile inside the capture
    top = min(max(sy - side_hi / 2, 0), max(0, hi.height - side_hi))
    box = (left, top, left + side_hi, top + side_hi)
    tile = hi.crop(tuple(int(round(v)) for v in box)).resize((diameter, diameter), Image.LANCZOS)
    mask = Image.new("L", (diameter * SS, diameter * SS), 0)
    ImageDraw.Draw(mask).ellipse([0, 0, diameter * SS - 1, diameter * SS - 1], fill=255)
    mask = mask.resize((diameter, diameter), Image.LANCZOS)
    cx, cy = centre
    r_out = diameter / 2
    margin = r_out + 4 * g.u
    clamped = (min(max(cx, margin), g.out_w - margin), min(max(cy, margin), g.out_h - margin))
    link = None
    if clamped != (cx, cy):
        WARNINGS.append("zoom circle did not fit and was moved inside the image")
    cx, cy = clamped
    if math.hypot(cx - src_centre[0], cy - src_centre[1]) > 0.5 * diameter:
        link = (x0, y0, x1, y1)               # circle is away from its source: outline the source instead
    px, py = int(round(cx - diameter / 2)), int(round(cy - diameter / 2))
    # soft shadow
    pad = int(30 * g.u)
    shadow = Image.new("RGBA", (diameter + 2 * pad, diameter + 2 * pad), (0, 0, 0, 0))
    ImageDraw.Draw(shadow).ellipse([pad, pad + int(6 * g.u), pad + diameter, pad + diameter + int(6 * g.u)], fill=(10, 14, 28, 80))
    shadow = shadow.filter(ImageFilter.GaussianBlur(14 * g.u))
    base.paste(shadow, (px - pad, py - pad), shadow)
    base.paste(tile, (px, py), mask)
    # ring: grey hairline outside a white band
    ov = Overlay(base.width, base.height)
    for rad, col, wd in ((r_out - 4 * g.u, _rgba("halo"), 8 * g.u), (r_out + 1 * g.u, _rgba("ring_edge"), 2 * g.u)):
        ov.d.ellipse([ov.s(cx - rad), ov.s(cy - rad), ov.s(cx + rad), ov.s(cy + rad)], outline=col, width=max(1, int(ov.s(wd))))
    base.paste(ov.flatten(), (0, 0), ov.flatten())
    return link


# --------------------------------------------------------------------------- main pipeline
def load_spec(path: str) -> dict:
    with open(path, "r", encoding="utf-8") as fh:
        data = json.load(fh)
    if isinstance(data, list):
        data = {"annotations": data}
    if not isinstance(data, dict) or not isinstance(data.get("annotations", []), list):
        raise SpecError("spec must be a list of annotations or an object with an 'annotations' list")
    for i, a in enumerate(data.get("annotations", [])):
        if not isinstance(a, dict) or a.get("type") not in VALID_TYPES:
            raise SpecError(f"annotation #{i + 1}: 'type' must be one of {sorted(VALID_TYPES)}, got {a!r}")
    return data


def annotate(src_path: str, spec: dict, out_path: str, quality: int | None = None, max_width: int | None = None,
             lossless: bool = False, png_preview: str | None = None) -> dict:
    WARNINGS.clear()
    scale = float(spec.get("scale", 1))
    units = spec.get("units", "css" if scale != 1 else "px")
    if units not in ("css", "px"):
        raise SpecError("units must be 'css' or 'px'")
    rtl = bool(spec.get("rtl", False))
    outcfg = spec.get("output", {}) or {}
    quality = int(quality or outcfg.get("quality", DEFAULT_QUALITY))
    max_width = int(max_width or outcfg.get("max_width", DEFAULT_MAX_W))
    anns = spec.get("annotations", [])

    raw = Image.open(src_path)
    raw.load()
    if raw.mode in ("RGBA", "LA", "P"):
        bg = Image.new("RGB", raw.size, (255, 255, 255))
        bg.paste(raw.convert("RGBA"), mask=raw.convert("RGBA").split()[-1])
        raw = bg
    else:
        raw = raw.convert("RGB")
    k = scale if units == "css" else 1.0

    crops = [a for a in anns if a["type"] == "crop"]
    if len(crops) > 1:
        raise SpecError("only one crop annotation is allowed")
    if crops:
        c = _num_list(crops[0], "coords", (4,))
        pad = float(crops[0].get("pad", 0))
        x, y, w, h = c[0] - pad, c[1] - pad, c[2] + 2 * pad, c[3] + 2 * pad
        crop_px = tuple(int(round(v * k)) for v in (x, y, x + w, y + h))
        x0, y0, x1, y1 = max(0, crop_px[0]), max(0, crop_px[1]), min(raw.width, crop_px[2]), min(raw.height, crop_px[3])
        if x1 - x0 < 16 or y1 - y0 < 16:
            raise SpecError("crop: region is empty or outside the screenshot")
        crop_px = (x0, y0, x1 - x0, y1 - y0)
    else:
        crop_px = (0, 0, raw.width, raw.height)
    hi = raw.crop((crop_px[0], crop_px[1], crop_px[0] + crop_px[2], crop_px[1] + crop_px[3]))
    out_w = min(max_width, hi.width)                 # never upscale
    out_h = int(round(hi.height * out_w / hi.width))
    base = hi.resize((out_w, out_h), Image.LANCZOS) if out_w != hi.width else hi.copy()
    g = Geometry(scale, units, crop_px, out_w, out_h)

    drawn = [a for a in anns if a["type"] != "crop"]
    ordered = sorted(drawn, key=lambda a: LAYER_ORDER.index(a["type"]))
    for a in ordered:
        if a["type"] == "blur":
            apply_blur(base, g, a)
        elif a["type"] == "redact":
            apply_redact(base, g, a)
    links = []
    for a in ordered:
        if a["type"] == "zoom":
            link = apply_zoom(base, hi, g, a)
            if link:
                links.append(link)
    ov = Overlay(out_w, out_h)
    for (x0, y0, x1, y1) in links:              # circle sits elsewhere: outline the magnified source region
        pad = 6 * g.u
        rect = (x0 - pad, y0 - pad, x1 + pad, y1 + pad)
        _rounded(ov, rect, 10 * g.u, 6 * g.u + 6 * g.u, _rgba("halo"))
        _rounded(ov, rect, 10 * g.u, 6 * g.u, _rgba("primary"))
    for a in ordered:
        t = a["type"]
        if t == "box":
            rect = draw_box(ov, g, a, rtl)
            if a.get("label"):
                draw_label(ov, g, str(a["label"]), (rect[0], rect[3] + 8 * g.u))
        elif t == "arrow":
            tail, tip = draw_arrow(ov, g, a, rtl)
            if a.get("label"):
                draw_label(ov, g, str(a["label"]), (tail[0], tail[1]))
        elif t == "badge":
            draw_badge(ov, g, a, rtl)
    flat = ov.flatten()
    base = base.convert("RGBA")
    base.alpha_composite(flat)
    final = base.convert("RGB")

    os.makedirs(os.path.dirname(os.path.abspath(out_path)), exist_ok=True)
    save_kwargs = dict(format="WEBP", quality=quality, method=6)
    if lossless:
        save_kwargs.update(lossless=True)
    final.save(out_path, **save_kwargs)       # no EXIF/ICC is written: nothing about the capture host leaks
    if png_preview:
        final.save(png_preview, format="PNG")

    kb = os.path.getsize(out_path) / 1024
    report = lint(final.size, kb, anns, scale, crop_px, rtl)
    report.update({"output": out_path, "size_px": list(final.size), "kb": round(kb, 1), "format": "WEBP",
                   "quality": None if lossless else quality, "annotation_count": len(drawn)})
    return report


def lint(size, kb, anns, k, crop_px, rtl):
    w, h = size
    warnings: list[str] = []
    drawn = [a for a in anns if a["type"] in ("box", "arrow", "badge", "zoom")]
    if w > 1600:
        warnings.append(f"width {w}px > 1600px target")
    if kb > KB_SOFT_LIMIT:
        warnings.append(f"file {kb:.0f} KB > {KB_SOFT_LIMIT} KB; lower the quality or crop tighter")
    if len(drawn) > MAX_ANNOTATIONS:
        warnings.append(f"{len(drawn)} annotations > {MAX_ANNOTATIONS}; split into two screenshots")
    ar = w / h
    if ar > 4.2 or ar < 0.5:
        warnings.append(f"aspect ratio {ar:.2f} outside 0.5-4.2")
    if sum(1 for a in anns if a["type"] == "arrow") > 2:
        warnings.append("more than 2 arrows; use numbered badges or split the image")
    css_w = crop_px[2] / k
    if css_w > 900:
        warnings.append(f"crop is {css_w:.0f} CSS px wide; UI text will render below ~85% of native size in the 760 px article column (crop to <= 900)")
    for a in anns:
        if a["type"] == "blur" and (a.get("radius") or 99) < 6:
            warnings.append("blur radius < 6: text may stay legible")
    return {"warnings": warnings + list(dict.fromkeys(WARNINGS))}


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description="Annotate a screenshot and export a WebP per IMAGE-STYLE-GUIDE.md")
    ap.add_argument("input")
    ap.add_argument("spec")
    ap.add_argument("-o", "--output")
    ap.add_argument("--quality", type=int)
    ap.add_argument("--max-width", type=int)
    ap.add_argument("--lossless", action="store_true")
    ap.add_argument("--png-preview")
    ap.add_argument("--strict", action="store_true")
    ap.add_argument("--json", action="store_true")
    a = ap.parse_args(argv)
    out = a.output or os.path.splitext(a.input)[0] + ".webp"
    try:
        report = annotate(a.input, load_spec(a.spec), out, a.quality, a.max_width, a.lossless, a.png_preview)
    except (SpecError, OSError, json.JSONDecodeError) as exc:
        print(f"annotate: error: {exc}", file=sys.stderr)
        return 1
    if a.json:
        print(json.dumps(report, indent=2))
    else:
        print(f"wrote {report['output']}  {report['size_px'][0]}x{report['size_px'][1]}px  {report['kb']} KB  "
              f"{report['annotation_count']} annotation(s)")
        for w in report["warnings"]:
            print(f"  warning: {w}")
    return 2 if (a.strict and report["warnings"]) else 0


if __name__ == "__main__":
    sys.exit(main())

#!/usr/bin/env python3
"""Generate the synthetic demo screenshot and the sample specs used by the tests and the README.

The picture is NOT a real Easy Form Builder screen: it is a flat, invented "admin panel" drawn with
Pillow (no real keys, e-mails or brand assets) captured as if at device scale factor 2. Coordinates
in the specs are CSS pixels, like the capture tool will emit from Playwright's boundingBox().

    python3 make_sample.py            # writes sample-input.png + three sample-spec*.json next to this file
"""
import json
import os

from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
S = 2  # device scale factor of the pretend capture
W, H = 800, 500  # CSS px

INK, MUTED, LINE = (26, 34, 54), (102, 111, 130), (214, 218, 228)
INDIGO, PINK, GREEN = (54, 68, 210), (255, 75, 147), (21, 128, 61)


def font(size, bold=False):
    path = "/usr/share/fonts/truetype/dejavu/DejaVuSans%s.ttf" % ("-Bold" if bold else "")
    return ImageFont.truetype(path, int(size * S)) if os.path.exists(path) else ImageFont.load_default(size=int(size * S))


def main():
    img = Image.new("RGB", (W * S, H * S), (240, 240, 241))
    d = ImageDraw.Draw(img)

    def R(x, y, w, h):
        return [x * S, y * S, (x + w) * S, (y + h) * S]

    def text(x, y, s, size=13, fill=INK, bold=False):
        d.text((x * S, y * S), s, font=font(size, bold), fill=fill)

    # sidebar
    d.rectangle(R(0, 0, 120, H), fill=(29, 35, 39))
    for i, (label, active) in enumerate([("Dashboard", False), ("Forms", True), ("Entries", False), ("Settings", False), ("Add-ons", False)]):
        y = 60 + i * 38
        if active:
            d.rectangle(R(0, y - 8, 120, 34), fill=INDIGO)
        text(16, y, label, 13, (235, 238, 245) if active else (170, 178, 190), active)
    text(16, 16, "Demo Admin", 11, (150, 158, 170))

    # card
    d.rounded_rectangle(R(140, 50, 640, 430), radius=14 * S, fill=(255, 255, 255), outline=LINE, width=S)
    text(164, 70, "Email notifications", 20, INK, True)
    d.rounded_rectangle(R(650, 74, 100, 26), radius=13 * S, fill=(220, 252, 231))
    text(676, 79, "Active", 12, GREEN, True)

    # toggle row
    d.rounded_rectangle(R(164, 126, 44, 24), radius=12 * S, fill=INDIGO)
    d.ellipse(R(188, 129, 18, 18), fill=(255, 255, 255))
    text(222, 129, "Send an email for every new entry", 13)

    # recipient field (contains a fake address: the blur target)
    text(164, 176, "Send notifications to", 12, MUTED)
    d.rounded_rectangle(R(164, 196, 430, 38), radius=8 * S, fill=(250, 251, 253), outline=LINE, width=S)
    text(178, 207, "sam.rivera@example.com", 14)

    # key field (fake placeholder secret: the redact target)
    text(164, 252, "Service API key", 12, MUTED)
    d.rounded_rectangle(R(164, 272, 430, 38), radius=8 * S, fill=(250, 251, 253), outline=LINE, width=S)
    text(178, 283, "demo_key_0000000000000000", 14)

    # buttons
    d.rounded_rectangle(R(164, 340, 140, 40), radius=8 * S, fill=INDIGO)
    text(190, 351, "Save settings", 13, (255, 255, 255), True)
    d.rounded_rectangle(R(320, 340, 160, 40), radius=8 * S, outline=PINK, width=2 * S)
    text(346, 351, "Send test email", 13, PINK, True)

    out = os.path.join(HERE, "sample-input.png")
    img.save(out, optimize=True)

    crop = {"type": "crop", "coords": [130, 40, 660, 360]}
    specs = {
        # recommended pattern: one crop, redact the secret, blur the address, magnify the toggle, point at the action
        "sample-spec.json": {
            "scale": S, "units": "css", "output": {"max_width": 1600, "quality": 82},
            "annotations": [
                crop,
                {"type": "blur", "coords": [172, 202, 300, 26]},
                {"type": "redact", "coords": [172, 278, 300, 26]},
                {"type": "zoom", "coords": [164, 126, 44, 24], "factor": 2.0, "diameter": 0.19, "at": [610, 170]},
                {"type": "arrow", "target": [320, 340, 160, 40], "from": "end", "length": 0.15},
            ],
        },
        # sequential targets in one image: numbered badges (only when one image must show 2-3 actions)
        "sample-spec-badges.json": {
            "scale": S, "units": "css",
            "annotations": [
                crop,
                {"type": "box", "coords": [164, 340, 140, 40], "pad": 4},
                {"type": "badge", "label": "1", "coords": [164, 126, 44, 24]},
                {"type": "badge", "label": "2", "coords": [164, 340, 140, 40]},
            ],
        },
        # rtl capture: "start" = right side and the badge sits on the top-right corner (same LTR input here, only to show the effect)
        "sample-spec-rtl.json": {
            "scale": S, "units": "css", "rtl": True,
            "annotations": [
                crop,
                {"type": "arrow", "target": [320, 340, 160, 40], "from": "start", "length": 0.15},
                {"type": "badge", "label": "1", "coords": [320, 340, 160, 40]},
            ],
        },
    }
    for name, spec in specs.items():
        with open(os.path.join(HERE, name), "w", encoding="utf-8") as fh:
            json.dump(spec, fh, indent=2)
            fh.write("\n")
    print("wrote", out, "and", ", ".join(specs))


if __name__ == "__main__":
    main()

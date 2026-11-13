#!/usr/bin/env python3
"""Self-tests for annotate.py (no external test runner needed).

    python3 -m unittest -v test_annotate        # from this directory
"""
import json
import os
import subprocess
import sys
import tempfile
import unittest

from PIL import Image, ImageChops, ImageDraw, ImageStat

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import annotate as A  # noqa: E402

PRIMARY = A.COLORS["primary"][:3]


def make_raw(path, size=(1600, 1000), scale=2):
    """White canvas with a dark text-like block at css (100,100,200x40) and a blue block at css (400,300,160x40)."""
    im = Image.new("RGB", size, (255, 255, 255))
    d = ImageDraw.Draw(im)
    d.rectangle([100 * scale, 100 * scale, 300 * scale, 140 * scale], fill=(20, 20, 20))
    for i in range(0, 200 * scale, 8):  # high-frequency stripes so a blur is measurable
        d.line([(100 * scale + i, 100 * scale), (100 * scale + i, 140 * scale)], fill=(240, 240, 240), width=2)
    d.rectangle([400 * scale, 300 * scale, 560 * scale, 340 * scale], fill=(54, 68, 210))
    im.save(path)


def px_close(a, b, tol=24):
    return all(abs(x - y) <= tol for x, y in zip(a[:3], b[:3]))


class AnnotateTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.raw = os.path.join(self.tmp.name, "raw.png")
        self.out = os.path.join(self.tmp.name, "out.webp")
        make_raw(self.raw)

    def tearDown(self):
        self.tmp.cleanup()

    def run_spec(self, spec, **kw):
        kw.setdefault("lossless", True)       # pixel assertions must not depend on lossy WebP artefacts
        return A.annotate(self.raw, spec, self.out, **kw)

    def test_output_is_webp_with_expected_size_and_no_metadata(self):
        rep = self.run_spec({"scale": 2, "annotations": [{"type": "crop", "coords": [50, 50, 700, 400]}]})
        with Image.open(self.out) as im:
            self.assertEqual(im.format, "WEBP")
            self.assertEqual(im.size, (1400, 800))
            self.assertNotIn("exif", im.info)
        self.assertEqual(rep["size_px"], [1400, 800])
        self.assertLess(rep["kb"], 250)

    def test_never_upscales_and_caps_width(self):
        big = os.path.join(self.tmp.name, "big.png")
        make_raw(big, size=(3200, 2000), scale=4)
        rep = A.annotate(big, {"scale": 4, "annotations": []}, self.out)
        self.assertEqual(rep["size_px"][0], 1600)
        small = os.path.join(self.tmp.name, "small.png")
        make_raw(small, size=(800, 500), scale=1)
        rep = A.annotate(small, {"annotations": []}, self.out)
        self.assertEqual(rep["size_px"], [800, 500])

    def test_crop_translates_annotation_coordinates(self):
        # box (css) around the blue block at (400,300,160,40); crop starts at css (350,250)
        spec = {"scale": 2, "annotations": [
            {"type": "crop", "coords": [350, 250, 400, 250]},
            {"type": "box", "coords": [400, 300, 160, 40], "pad": 0}]}
        self.run_spec(spec)
        with Image.open(self.out).convert("RGB") as im:
            self.assertEqual(im.size, (800, 500))
            # block starts at ((400-350)*2, (300-250)*2) = (100,100); the stroke straddles that edge
            samples = [im.getpixel((100, 160)), im.getpixel((101, 160)), im.getpixel((99, 160))]
            self.assertTrue(any(px_close(p, PRIMARY, 40) for p in samples), samples)
            self.assertTrue(px_close(im.getpixel((200, 160)), (54, 68, 210), 12))  # block interior untouched

    def test_primary_colour_has_white_halo(self):
        spec = {"scale": 2, "annotations": [{"type": "box", "coords": [400, 300, 160, 40], "pad": 0}]}
        self.run_spec(spec)
        with Image.open(self.out).convert("RGB") as im:
            # outside the stroke (above the blue block) there must be near-white casing pixels
            self.assertTrue(any(im.getpixel((800, y))[0] > 240 and im.getpixel((800, y))[1] > 240 for y in range(586, 594)))

    def test_badge_is_drawn_at_corner_and_mirrors_for_rtl(self):
        base = {"scale": 2, "annotations": [{"type": "badge", "label": "2", "coords": [400, 300, 160, 40]}]}
        self.run_spec(base)
        with Image.open(self.out).convert("RGB") as ltr:
            left = ltr.getpixel((800, 600 - 22))         # inside the badge disc (r=28), above the digit
            self.assertTrue(px_close(left, PRIMARY, 30), left)
        rtl = dict(base, rtl=True)
        self.run_spec(rtl)
        with Image.open(self.out).convert("RGB") as im:
            right = im.getpixel((1120, 600 - 22))        # inside the badge disc at the top-right corner
            self.assertTrue(px_close(right, PRIMARY, 30), right)

    def test_blur_removes_detail_and_redact_is_flat(self):
        def detail(img):  # mean absolute horizontal gradient = how much fine structure is left
            px = list(ImageChops.difference(img, ImageChops.offset(img, 1, 0)).getdata())
            return sum(px) / len(px)
        box = (210, 205, 590, 275)  # inside the striped block
        before = detail(Image.open(self.raw).convert("L").crop(box))
        self.run_spec({"scale": 2, "annotations": [{"type": "blur", "coords": [95, 95, 210, 50]}]})
        after = detail(Image.open(self.out).convert("L").crop(box))
        self.assertGreater(before, 20)
        self.assertLess(after, 1.5)
        self.run_spec({"scale": 2, "annotations": [{"type": "redact", "coords": [100, 100, 200, 40]}]})
        flat = Image.open(self.out).convert("L").crop((210, 205, 230, 215))   # left padding: no dots there
        self.assertLess(ImageStat.Stat(flat).stddev[0], 4)

    def test_arrow_target_stays_outside_the_target_and_points_at_it(self):
        spec = {"scale": 2, "annotations": [{"type": "arrow", "target": [400, 300, 160, 40], "from": "end", "length": 0.15}]}
        self.run_spec(spec)
        raw = Image.open(self.raw).convert("RGB")
        out = Image.open(self.out).convert("RGB")
        inside = (800, 600, 1120, 680)
        diff = ImageChops.difference(raw.crop(inside), out.crop(inside))
        self.assertIsNone(diff.getbbox(), "the target control must not be painted over")
        full = ImageChops.difference(raw, out).getbbox()
        self.assertIsNotNone(full)
        self.assertGreater(full[0], 1120 - 1)  # drawn to the right of the target ("end" side, LTR)

    def test_zoom_draws_circle_inside_image(self):
        spec = {"scale": 2, "annotations": [{"type": "zoom", "coords": [100, 100, 200, 40], "factor": 2, "diameter": 0.2}]}
        rep = self.run_spec(spec)
        self.assertEqual(rep["warnings"], [])
        out = Image.open(self.out).convert("RGB")
        self.assertIsNotNone(ImageChops.difference(Image.open(self.raw).convert("RGB"), out).getbbox())

    def test_lint_warns_on_too_many_annotations_and_wide_crops(self):
        anns = [{"type": "box", "coords": [10 + i * 60, 10, 40, 20]} for i in range(4)]
        rep = self.run_spec({"scale": 2, "annotations": anns})
        self.assertTrue(any("annotations" in w for w in rep["warnings"]), rep)
        wide = os.path.join(self.tmp.name, "wide.png")
        make_raw(wide, size=(2000, 1000), scale=2)
        rep = A.annotate(wide, {"scale": 2, "annotations": []}, self.out)
        self.assertTrue(any("CSS px wide" in w for w in rep["warnings"]), rep)

    def test_invalid_specs_raise(self):
        for bad in (
            {"annotations": [{"type": "sparkle", "coords": [0, 0, 1, 1]}]},
            {"annotations": [{"type": "box", "coords": [1, 2, 3]}]},
            {"annotations": [{"type": "badge", "label": "123", "coords": [10, 10]}]},
            {"annotations": [{"type": "crop", "coords": [0, 0, 10, 10]}, {"type": "crop", "coords": [0, 0, 20, 20]}]},
            {"annotations": [{"type": "zoom", "coords": [10, 10, 40, 40], "factor": 9}]},
        ):
            path = os.path.join(self.tmp.name, "bad.json")
            with open(path, "w") as fh:
                json.dump(bad, fh)
            try:
                with self.assertRaises(A.SpecError):
                    A.annotate(self.raw, A.load_spec(path), self.out)
            except A.SpecError:
                pass  # load_spec itself rejected it: also fine

    def test_cli_and_bare_list_spec(self):
        spec = os.path.join(self.tmp.name, "list.json")
        with open(spec, "w") as fh:
            json.dump([{"type": "crop", "coords": [0, 0, 800, 500]}, {"type": "box", "coords": [400, 300, 160, 40]}], fh)
        res = subprocess.run([sys.executable, os.path.join(HERE, "annotate.py"), self.raw, spec, "-o", self.out, "--json"],
                             capture_output=True, text=True)
        self.assertEqual(res.returncode, 0, res.stderr)
        report = json.loads(res.stdout)
        self.assertEqual(report["format"], "WEBP")
        self.assertTrue(os.path.exists(self.out))

    def test_samples_regenerate_and_pass_lint(self):
        sdir = os.path.join(HERE, "samples")
        for spec in ("sample-spec.json", "sample-spec-badges.json", "sample-spec-rtl.json"):
            rep = A.annotate(os.path.join(sdir, "sample-input.png"), A.load_spec(os.path.join(sdir, spec)), self.out)
            self.assertEqual(rep["warnings"], [], (spec, rep))
            self.assertLess(rep["kb"], 80)


if __name__ == "__main__":
    unittest.main(verbosity=2)

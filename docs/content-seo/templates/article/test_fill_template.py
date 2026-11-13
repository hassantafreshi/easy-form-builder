#!/usr/bin/env python3
"""Tests for fill_template.py and the template contract.   python3 -m unittest -v test_fill_template"""
import copy
import os
import re
import sys
import unittest

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import fill_template as F  # noqa: E402

TPL = open(os.path.join(HERE, "article-template.en.html"), encoding="utf-8").read()


def demo():
    return copy.deepcopy(F.DEMO)


class FillTests(unittest.TestCase):
    def test_demo_fills_cleanly(self):
        out = F.fill(TPL, demo())
        self.assertEqual(F.validate(out), [])
        self.assertNotIn("<!--#", out)
        self.assertNotIn("{{", out)

    def test_every_placeholder_in_template_is_covered_by_demo_or_cloned(self):
        used = set(F.PH.findall(F.HASH_COMMENT.sub("", TPL)))
        data = demo()
        # optional in the template and deliberately absent from the demo: FAQ_4, STEP_1/2_EXTRA
        absent = sorted(k for k in used if k not in data)
        self.assertEqual(absent, ["FAQ_4_A", "FAQ_4_Q", "STEP_1_EXTRA", "STEP_2_EXTRA"])

    def test_template_has_no_h1_script_toc_and_balanced_comments(self):
        body = F.HASH_COMMENT.sub("", TPL)
        self.assertNotRegex(body, r"<h1\b")
        self.assertNotRegex(body, r"<script\b")
        self.assertNotRegex(body, r"On this page|Table of contents")
        self.assertEqual(len(re.findall(r"<!-- wp:(?!.*/-->)", body)), len(re.findall(r"<!-- /wp:", body)))

    def test_optional_spans_dropped_without_data(self):
        d = demo()
        for k in ("VIDEO_EMBED", "VIDEO_TRANSCRIPT", "PREREQ_1", "PREREQ_2", "NOTE_1", "WARNING_1", "REQUIREMENTS_ITEMS",
                  "RESULT_BODY", "IMG_RESULT_ID", "IMG_RESULT_URL", "IMG_RESULT_W", "IMG_RESULT_H", "IMG_RESULT_ALT",
                  "CTA_URL", "CTA_LABEL", "UPDATE_NOTE", "FAQ_3_Q", "FAQ_3_A", "STEP_3_EXTRA"):
            d.pop(k)
        out = F.fill(TPL, d)
        for gone in ("Video transcript", "Before you start", "wp-block-callout warn", "What you should see", "wp-block-buttons", "Note:"):
            self.assertNotIn(gone, out, gone)
        self.assertEqual(F.validate(out), [])
        self.assertEqual(out.count("<details"), 2)

    def test_clone_steps_and_faq(self):
        d = demo()
        for n in (4, 5):
            d.update({f"STEP_{n}_ID": f"step-{n}-x", f"STEP_{n}_TITLE": f"Title {n}", f"STEP_{n}_BODY": f"Body {n}",
                      f"IMG_{n}_ID": str(100 + n), f"IMG_{n}_URL": f"https://example.com/s{n}.webp", f"IMG_{n}_W": "1200",
                      f"IMG_{n}_H": "700", f"IMG_{n}_ALT": "Clicking the highlighted setting in the Easy Form Builder panel to continue"})
        d.update({"FAQ_4_Q": "Q4?", "FAQ_4_A": "A4.", "FAQ_5_Q": "Q5?", "FAQ_5_A": "A5.", "FAQ_6_Q": "Q6?", "FAQ_6_A": "A6."})
        out = F.fill(TPL, d)
        self.assertEqual(F.validate(out), [])
        self.assertEqual(len(re.findall(r">Step \d — ", out)), 5)
        self.assertIn("Step 5 — Title 5", out)
        self.assertIn('id="step-5-x"', out)
        self.assertIn('class="wp-image-105"', out)
        self.assertEqual(out.count("<details"), 7)  # 6 FAQ + transcript

    def test_missing_required_data_is_an_error(self):
        d = demo()
        d.pop("DIRECT_ANSWER")
        with self.assertRaisesRegex(ValueError, "DIRECT_ANSWER"):
            F.fill(TPL, d)

    def test_escaping_and_rejections(self):
        d = demo()
        d["IMG_1_ALT"] = 'Clicking the "Save" button <b>in</b> the Easy Form Builder form settings panel'
        out = F.fill(TPL, d)
        self.assertIn("&quot;Save&quot; button &lt;b&gt;in&lt;/b&gt;", out)       # attribute values are escaped
        for key, bad in (("STEP_1_BODY", "<script>alert(1)</script>"),
                         ("STEP_1_BODY", '<a href="javascript:alert(1)">x</a>'),
                         ("STEP_1_BODY", '<img src=x onerror=alert(1)>'),
                         ("STEP_1_EXTRA", '<p onclick="x()">hi</p>'),
                         ("IMG_1_URL", "javascript:alert(1)"),
                         ("IMG_1_ID", "12abc")):
            d = demo()
            d[key] = bad
            with self.assertRaises(ValueError, msg=(key, bad)):
                F.fill(TPL, d)

    def test_validate_flags_bad_alt_h1_script(self):
        out = F.fill(TPL, demo())
        bad = out.replace('alt="Clicking the Google Sheets tab in the Easy Form Builder settings screen"', 'alt="screenshot"')
        self.assertTrue(any("alt" in p for p in F.validate(bad)))
        self.assertTrue(any("<h1>" in p for p in F.validate(out + "<h1>x</h1>")))
        self.assertTrue(any("<script>" in p for p in F.validate(out + "<script>1</script>")))


if __name__ == "__main__":
    unittest.main(verbosity=2)

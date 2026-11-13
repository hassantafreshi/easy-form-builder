#!/usr/bin/env python3
"""Tests for assemble_jsonld.py and jsonld-templates.json.   python3 -m unittest -v test_assemble_jsonld"""
import copy
import json
import os
import sys
import unittest

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import assemble_jsonld as J  # noqa: E402

TEMPLATES = json.load(open(os.path.join(HERE, "jsonld-templates.json"), encoding="utf-8"))


def demo():
    return copy.deepcopy(J.DEMO)


class AssembleTests(unittest.TestCase):
    def test_template_file_is_valid_and_covers_the_four_types(self):
        self.assertEqual(set(TEMPLATES["templates"]), {"TechArticle", "BreadcrumbList", "VideoObject", "FAQPage"})
        for mode, names in TEMPLATES["assemble"].items():
            if mode != "output":
                self.assertTrue(set(names) <= set(TEMPLATES["templates"]), mode)

    def test_every_documented_placeholder_is_used_and_every_used_one_documented(self):
        import re
        blob = json.dumps(TEMPLATES["templates"])
        used = set(re.findall(r"\{\{([A-Z0-9_]+)", blob))
        documented = {k.replace("FAQ_n_", "FAQ_%d_") for k in TEMPLATES["_meta"]["placeholders"]}
        used_norm = {re.sub(r"FAQ_\d+_", "FAQ_%d_", u) for u in used}
        self.assertEqual(used_norm - documented, set())
        self.assertEqual(documented - used_norm - {"FAQ_%d_Q", "FAQ_%d_A_PLAIN"}, set())

    def test_supplement_has_no_article_or_breadcrumb(self):
        objs = J.assemble(TEMPLATES, demo(), "supplement", "youtube")
        self.assertEqual([o["@type"] for o in objs], ["VideoObject", "FAQPage"])
        self.assertEqual(J.validate(objs), [])

    def test_standalone_has_four_objects_and_valid_values(self):
        objs = J.assemble(TEMPLATES, demo(), "standalone", "self_hosted")
        self.assertEqual([o["@type"] for o in objs], ["TechArticle", "BreadcrumbList", "VideoObject", "FAQPage"])
        self.assertEqual(J.validate(objs), [])
        art, crumbs, video, faq = objs
        self.assertIsInstance(art["wordCount"], int)
        self.assertEqual(art["inLanguage"], "en-US")
        self.assertIn("Easy Form Builder 4.3.1", art["dependencies"])
        self.assertEqual([i["position"] for i in crumbs["itemListElement"]], [1, 2, 3])
        self.assertIn("contentUrl", video)
        self.assertNotIn("embedUrl", video)
        self.assertEqual(video["width"], 1920)
        self.assertFalse([k for o in objs for k in o if k.startswith("_")])

    def test_youtube_variant_uses_embed_url(self):
        v = J.assemble(TEMPLATES, demo(), "supplement", "youtube")[0]
        self.assertIn("embedUrl", v)
        self.assertNotIn("contentUrl", v)

    def test_video_dropped_when_none_or_no_data(self):
        self.assertEqual([o["@type"] for o in J.assemble(TEMPLATES, demo(), "supplement", "none")], ["FAQPage"])
        d = demo()
        d.pop("VIDEO_NAME")
        self.assertEqual([o["@type"] for o in J.assemble(TEMPLATES, d, "supplement", "youtube")], ["FAQPage"])

    def test_faq_items_follow_data_and_clone_up_to_six(self):
        d = demo()
        d.pop("FAQ_3_Q")
        d.pop("FAQ_3_A_PLAIN")
        faq = J.assemble(TEMPLATES, d, "supplement", "none")[0]
        self.assertEqual([q["name"] for q in faq["mainEntity"]], ["Does this need the Pro plan?", "Can I use an existing spreadsheet?"])
        d = demo()
        for n in (4, 5, 6):
            d[f"FAQ_{n}_Q"], d[f"FAQ_{n}_A_PLAIN"] = f"Q{n}?", f"A{n}."
        faq = J.assemble(TEMPLATES, d, "supplement", "none")[0]
        self.assertEqual(len(faq["mainEntity"]), 6)
        self.assertEqual(faq["mainEntity"][5]["acceptedAnswer"]["text"], "A6.")

    def test_single_faq_and_missing_data_are_errors(self):
        d = demo()
        for k in ("FAQ_2_Q", "FAQ_2_A_PLAIN", "FAQ_3_Q", "FAQ_3_A_PLAIN"):
            d.pop(k)
        with self.assertRaises(ValueError):
            J.assemble(TEMPLATES, d, "supplement", "none")
        d = demo()
        d.pop("PAGE_URL")
        with self.assertRaisesRegex(J.Missing, "PAGE_URL"):
            J.assemble(TEMPLATES, d, "standalone", "none")

    def test_validation_catches_bad_values(self):
        d = demo()
        d["VIDEO_DURATION_ISO"] = "58 seconds"
        d["LANG_BCP47"] = "english"
        d["FAQ_1_A_PLAIN"] = "bad </script><script>alert(1)</script>"
        problems = J.validate(J.assemble(TEMPLATES, d, "supplement", "youtube"))
        joined = " | ".join(problems)
        self.assertIn("duration", joined)
        self.assertIn("inLanguage", joined)
        self.assertIn("</script", joined)

    def test_output_is_json_array_and_survives_the_mu_plugin_rule(self):
        # the mu-plugin accepts anything json_decode turns into an array (object or list)
        text = json.dumps(J.assemble(TEMPLATES, demo(), "standalone", "youtube"), ensure_ascii=False)
        self.assertIsInstance(json.loads(text), list)


if __name__ == "__main__":
    unittest.main(verbosity=2)

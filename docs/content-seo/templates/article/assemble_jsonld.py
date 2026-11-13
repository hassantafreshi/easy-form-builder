#!/usr/bin/env python3
"""
assemble_jsonld.py - build the efb_jsonld meta value from jsonld-templates.json.

    python3 assemble_jsonld.py jsonld-templates.json data.json --mode supplement --video youtube -o efb_jsonld.json
    python3 assemble_jsonld.py jsonld-templates.json --demo --mode standalone --video self_hosted

--mode   supplement | standalone   which objects to emit (see _meta.modes in the template file)
--video  youtube | self_hosted | none   (VideoObject is dropped with "none")
data.json is a flat object {"PLACEHOLDER": "value"}; numbers may be passed as JSON numbers or strings.
Output: a JSON array of schema.org objects, compact, UTF-8, ready for the efb_jsonld post meta.
Exit 0 = ok, 1 = problem (stderr).  Standard library only.
"""
from __future__ import annotations

import argparse
import copy
import json
import re
import sys

WHOLE = re.compile(r"^\{\{([A-Z0-9_]+)(?::(int))?\}\}$")
INLINE = re.compile(r"\{\{([A-Z0-9_]+)\}\}")
ISO_DT = re.compile(r"^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})?)?$")
ISO_DUR = re.compile(r"^PT(?=\d)(\d+H)?(\d+M)?(\d+S)?$")
LANGS = {"en-US", "de-DE", "ar", "en", "de"}


class Missing(Exception):
    pass


def has(data: dict, key: str) -> bool:
    return str(data.get(key, "")).strip() != ""


def subst(node, data: dict, missing: set):
    if isinstance(node, dict):
        return {k: subst(v, data, missing) for k, v in node.items() if not k.startswith("_")}
    if isinstance(node, list):
        return [subst(v, data, missing) for v in node]
    if isinstance(node, str):
        m = WHOLE.match(node)
        if m:
            key, cast = m.groups()
            if not has(data, key):
                missing.add(key)
                return node
            return int(data[key]) if cast == "int" else str(data[key])
        def rep(mm):
            if not has(data, mm.group(1)):
                missing.add(mm.group(1))
                return mm.group(0)
            return str(data[mm.group(1)])
        return INLINE.sub(rep, node)
    return node


def build_faq(tpl: dict, data: dict) -> dict | None:
    """Keep the question items that have data; items 5 and 6 are clones of the last template item."""
    items = tpl["mainEntity"]
    out = []
    for n in range(1, 7):
        base = items[n - 1] if n <= len(items) else items[-1]
        text = re.sub(r"FAQ_\d+_", f"FAQ_{n}_", json.dumps(base))
        if has(data, f"FAQ_{n}_Q") and has(data, f"FAQ_{n}_A_PLAIN"):
            out.append(json.loads(text))
    if not out:
        return None
    if len(out) < 2:
        raise ValueError("FAQPage needs at least 2 questions (drop the FAQ block instead)")
    tpl = copy.deepcopy(tpl)
    tpl["mainEntity"] = out
    return tpl


def assemble(templates: dict, data: dict, mode: str, video: str) -> list:
    emit = list(templates["assemble"][mode])
    if video == "none" or not has(data, "VIDEO_NAME"):
        emit = [e for e in emit if e != "VideoObject"]
    objs, missing = [], set()
    for name in emit:
        tpl = copy.deepcopy(templates["templates"][name])
        if name == "VideoObject":
            variant = tpl.pop("_variant_youtube" if video == "youtube" else "_variant_self_hosted")
            tpl.pop("_variant_self_hosted", None)
            tpl.pop("_variant_youtube", None)
            tpl.update(variant)
        if name == "FAQPage":
            tpl = build_faq(tpl, data)
            if tpl is None:
                continue
        objs.append(subst(tpl, data, missing))
    if missing:
        raise Missing("no data for: " + ", ".join(sorted(missing)))
    return objs


def validate(objs: list) -> list[str]:
    problems = []
    blob = json.dumps(objs, ensure_ascii=False)
    if "{{" in blob:
        problems.append("unresolved placeholder")
    if re.search(r"</script", blob, re.I):
        problems.append("'</script' inside a value")
    for o in objs:
        t = o.get("@type")
        if o.get("inLanguage") and o["inLanguage"] not in LANGS:
            problems.append(f"{t}: inLanguage {o['inLanguage']!r} not in {sorted(LANGS)}")
        for k in ("datePublished", "dateModified", "uploadDate"):
            if k in o and not ISO_DT.match(o[k]):
                problems.append(f"{t}.{k}: not ISO 8601 ({o[k]!r})")
        if t == "VideoObject":
            if not ISO_DUR.match(o.get("duration", "")):
                problems.append(f"VideoObject.duration not ISO 8601 duration ({o.get('duration')!r})")
            if ("embedUrl" in o) == ("contentUrl" in o):
                problems.append("VideoObject needs exactly one of embedUrl / contentUrl")
        if t == "FAQPage" and not 2 <= len(o["mainEntity"]) <= 6:
            problems.append("FAQPage should have 2-6 questions")
        if t in ("TechArticle", "Article") and not o.get("author", {}).get("name"):
            problems.append("Article without a real author name")
    return problems


DEMO = {
    "PAGE_URL": "https://whitestudio.team/document/connect-easy-form-builder-to-google-sheets/",
    "SITE_URL": "https://whitestudio.team/", "PUBLISHER_NAME": "White Studio Team",
    "TITLE": "How to Connect Easy Form Builder to Google Sheets",
    "META_DESCRIPTION": "Send every Easy Form Builder entry to Google Sheets in about ten minutes. Step-by-step guide with screenshots, tested on version 4.3.1.",
    "LANG_BCP47": "en-US", "DATE_PUBLISHED_ISO": "2026-11-13T07:00:00+00:00", "DATE_MODIFIED_ISO": "2026-11-13T07:00:00+00:00",
    "AUTHOR_NAME": "Example Author", "AUTHOR_URL": "https://whitestudio.team/author/example/",
    "IMAGE_URL": "https://whitestudio.team/wp-content/uploads/2026/11/cover-demo.jpg", "CATEGORY_NAME": "Documentation",
    "WORD_COUNT": 1020, "EFB_VERSION": "4.3.1", "WP_MIN": "5.7",
    "CRUMB_2_NAME": "Documentation", "CRUMB_2_URL": "https://whitestudio.team/documents/",
    "VIDEO_NAME": "Connect Easy Form Builder to Google Sheets in 60 seconds", "VIDEO_DESCRIPTION": "Short demo of the Google Sheets add-on.",
    "VIDEO_THUMBNAIL_URL": "https://whitestudio.team/wp-content/uploads/2026/11/demo-poster.jpg", "VIDEO_UPLOAD_DATE_ISO": "2026-11-13",
    "VIDEO_DURATION_ISO": "PT58S", "VIDEO_LANG": "en", "VIDEO_TRANSCRIPT": "Open the settings, add credentials, authorize, map the fields.",
    "VIDEO_EMBED_URL": "https://www.youtube.com/embed/dQw4w9WgXcQ",
    "VIDEO_CONTENT_URL": "https://whitestudio.team/wp-content/uploads/2026/11/demo.mp4", "VIDEO_VTT_URL": "https://whitestudio.team/wp-content/uploads/2026/11/demo.en.vtt",
    "FAQ_1_Q": "Does this need the Pro plan?", "FAQ_1_A_PLAIN": "Yes, the Google Sheets add-on is a Pro add-on.",
    "FAQ_2_Q": "Can I use an existing spreadsheet?", "FAQ_2_A_PLAIN": "Yes, pick it in step 3.",
    "FAQ_3_Q": "Are old entries sent?", "FAQ_3_A_PLAIN": "No, only new entries are sent after you save.",
}


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("templates")
    ap.add_argument("data", nargs="?")
    ap.add_argument("--mode", choices=["supplement", "standalone"], required=True)
    ap.add_argument("--video", choices=["youtube", "self_hosted", "none"], default="none")
    ap.add_argument("--demo", action="store_true")
    ap.add_argument("-o", "--output")
    ap.add_argument("--pretty", action="store_true")
    a = ap.parse_args(argv)
    try:
        templates = json.load(open(a.templates, encoding="utf-8"))
        data = DEMO if a.demo else json.load(open(a.data, encoding="utf-8"))
        objs = assemble(templates, data, a.mode, a.video)
        problems = validate(objs)
    except (Missing, ValueError, OSError, KeyError) as exc:
        print(f"assemble_jsonld: error: {exc}", file=sys.stderr)
        return 1
    text = json.dumps(objs, ensure_ascii=False, indent=2 if a.pretty else None, separators=None if a.pretty else (",", ":"))
    if a.output:
        open(a.output, "w", encoding="utf-8").write(text + "\n")
    else:
        print(text)
    for p in problems:
        print(f"assemble_jsonld: warning: {p}", file=sys.stderr)
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())

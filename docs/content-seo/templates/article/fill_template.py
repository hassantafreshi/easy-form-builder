#!/usr/bin/env python3
"""
fill_template.py - reference implementation of the fill step for article-template.en.html.

    python3 fill_template.py article-template.en.html data.json -o body.html
    python3 fill_template.py article-template.en.html --demo -o body.html     # built-in demo data

data.json is a flat object {"PLACEHOLDER": "value", ...} (keys without braces). Procedure (in this order):
  1. clone  STEP_BLOCK / FAQ_BLOCK spans for steps 4..8 and FAQ 5..6 when data exists
  2. drop   OPTIONAL spans in which any placeholder has no data
  3. strip  every template comment (hash comments), so notes that mention placeholders are ignored
  4. fill   placeholders, escaping by kind (attribute / inline-HTML text / raw block)
  5. check  no placeholder left, wp: block comments balanced, no <script>/<h1>/inline event handlers

Exit code 0 = ok, 1 = problem (message on stderr). No dependencies beyond the standard library.
"""
from __future__ import annotations

import argparse
import html
import json
import re
import sys
from html.parser import HTMLParser

PH = re.compile(r"\{\{([A-Z0-9_]+)\}\}")
OPTIONAL = re.compile(r"<!--# OPTIONAL (\w+) #-->(.*?)<!--# /OPTIONAL #-->", re.S)
STEP_BLOCK = re.compile(r"<!--# STEP_BLOCK (\d+) #-->\n(.*?)\n<!--# /STEP_BLOCK #-->", re.S)
FAQ_BLOCK = re.compile(r"<!--# FAQ_BLOCK (\d+) #-->\n(.*?)\n<!--# /FAQ_BLOCK #-->", re.S)
LINE_COMMENT = re.compile(r"^[ \t]*<!--#(?:(?!#-->).)*#-->[ \t]*\n", re.S | re.M)   # a note on its own line(s): remove the line
HASH_COMMENT = re.compile(r"<!--#.*?#-->", re.S)                            # any remaining (inline) note

ATTR = re.compile(r"^(TITLE|VERIFIED_DATE_ISO|CTA_URL|NEXT_URL|RELATED_\d+_URL|STEP_\d+_ID|IMG_\w+_(ALT|URL|ID|W|H))$")
RAW_BLOCK = re.compile(r"^(VIDEO_EMBED|REQUIREMENTS_ITEMS|STEP_\d+_EXTRA)$")
INT_ONLY = re.compile(r"^IMG_\w+_(ID|W|H)$")
URL_KEYS = re.compile(r"(_URL)$")
INLINE_OK = {"a", "strong", "em", "b", "i", "code", "kbd", "br", "bdi", "span", "mark"}
MAX_STEPS, MAX_FAQ = 8, 6


class _Inline(HTMLParser):
    """Allow only a small set of inline tags in text placeholders (no script, no event handlers)."""

    def __init__(self):
        super().__init__()
        self.errors: list[str] = []

    def handle_starttag(self, tag, attrs):
        if tag not in INLINE_OK:
            self.errors.append(f"tag <{tag}> not allowed in a text placeholder")
        for k, v in attrs:
            if k.lower().startswith("on") or (k == "href" and not re.match(r"^(https://|/|#|mailto:)", v or "")):
                self.errors.append(f"attribute {k}={v!r} not allowed")


def check_inline(key: str, value: str) -> None:
    p = _Inline()
    p.feed(value)
    if p.errors:
        raise ValueError(f"{key}: " + "; ".join(p.errors))


def check_raw(key: str, value: str) -> None:
    if re.search(r"<\s*(script|iframe\s+srcdoc|style|object|embed)\b", value, re.I) or re.search(r"\son\w+\s*=", value, re.I):
        raise ValueError(f"{key}: script/style/object or inline event handler found in a raw block")


def render_value(key: str, value: str) -> str:
    value = str(value)
    if INT_ONLY.match(key) and not value.isdigit():
        raise ValueError(f"{key}: must be digits, got {value!r}")
    if URL_KEYS.search(key) and not re.match(r"^(https://|/)", value):
        raise ValueError(f"{key}: must start with https:// or /, got {value!r}")
    if RAW_BLOCK.match(key):
        check_raw(key, value)
        return value
    if ATTR.match(key):
        return html.escape(value, quote=True)
    check_inline(key, value)
    return value


def clone_blocks(tpl: str, data: dict, pattern: re.Pattern, prefixes: tuple[str, ...], max_n: int) -> str:
    m = pattern.search(tpl)
    if not m:
        return tpl
    src_n, body = int(m.group(1)), m.group(2)
    extra = []
    for n in range(src_n + 1, max_n + 1):
        if not any(k.startswith(f"{p}_{n}_") for k in data for p in prefixes):
            break
        clone = re.sub(r"\b(" + "|".join(prefixes) + r")_" + str(src_n) + r"(?=_)", lambda mm: f"{mm.group(1)}_{n}", body)
        extra.append(clone.replace(f"Step {src_n} —", f"Step {n} —"))
    return tpl[:m.start()] + body + ("\n\n" + "\n\n".join(extra) if extra else "") + tpl[m.end():]


def fill(tpl: str, data: dict[str, str]) -> str:
    data = {k: v for k, v in data.items() if not k.startswith("_")}
    tpl = clone_blocks(tpl, data, STEP_BLOCK, ("STEP", "IMG"), MAX_STEPS)
    tpl = clone_blocks(tpl, data, FAQ_BLOCK, ("FAQ",), MAX_FAQ)

    def drop_or_keep(m: re.Match) -> str:
        keys = set(PH.findall(m.group(2)))
        return m.group(0) if all(str(data.get(k, "")).strip() for k in keys) else ""
    # a step's optional EXTRA sits inside the step block, so run until stable (nested spans)
    prev = None
    while prev != tpl:
        prev, tpl = tpl, OPTIONAL.sub(drop_or_keep, tpl)

    tpl = HASH_COMMENT.sub("", LINE_COMMENT.sub("", tpl))      # notes may mention placeholders: strip them first
    missing = sorted({k for k in PH.findall(tpl) if not str(data.get(k, "")).strip()})
    if missing:
        raise ValueError("no data for: " + ", ".join(missing))
    tpl = PH.sub(lambda m: render_value(m.group(1), data[m.group(1)]), tpl)
    return re.sub(r"\n{3,}", "\n\n", tpl).strip() + "\n"


def validate(out: str) -> list[str]:
    problems = []
    if "{{" in out or "}}" in out:
        problems.append("unresolved placeholder left in output")
    if re.search(r"<h1\b", out, re.I):
        problems.append("<h1> in body (the theme prints the H1)")
    if re.search(r"<script\b", out, re.I):
        problems.append("<script> in body (JSON-LD goes to efb_jsonld meta)")
    opens = re.findall(r"<!-- wp:([a-z/-]+)", out)
    stack = []
    for tag in re.findall(r"<!-- (/?)wp:([a-z0-9/-]+)[^>]*?(/?)-->", out):
        closing, name, selfclose = tag
        if selfclose:
            continue
        if closing:
            if not stack or stack.pop() != name:
                problems.append(f"unbalanced wp:{name} comment")
        else:
            stack.append(name)
    if stack:
        problems.append("unclosed wp: blocks: " + ", ".join(stack))
    heads = re.findall(r'<h2[^>]*id="([^"]+)"', out)
    if len(heads) != len(set(heads)):
        problems.append("duplicate H2 id")
    imgs = re.findall(r"<img [^>]*>", out)
    for im in imgs:
        alt = re.search(r'alt="([^"]*)"', im)
        words = len(alt.group(1).split()) if alt else 0
        if not alt or not (8 <= words <= 14):
            problems.append(f"image alt should be 8-14 words (got {words}): {im[:70]}")
    return problems


DEMO = {
    "TITLE": "How to Connect Easy Form Builder to Google Sheets",
    "DIRECT_ANSWER": "To send form entries to Google Sheets, open <strong>Easy Form Builder &raquo; Settings</strong>, add your Google credentials, then enable the Google Sheets add-on on the form you want. This guide uses Easy Form Builder 4.3.1 with the Pro add-on and takes about ten minutes.",
    "PLAN_LABEL": "Pro (Google Sheets add-on)",
    "TIME_TO_COMPLETE": "about 10 minutes",
    "EFB_VERSION": "4.3.1",
    "WP_VERSION": "7.1",
    "VERIFIED_DATE_ISO": "2026-11-13",
    "VERIFIED_DATE_HUMAN": "November 13, 2026",
    "REQUIREMENTS_ITEMS": "<li><strong>Requires:</strong> a Google account that can create a Cloud project</li>",
    "PREREQ_1": "A form with at least one field and one test entry.",
    "PREREQ_2": "Administrator access to your WordPress site.",
    "STEP_1_ID": "step-1-create-the-connection", "STEP_1_TITLE": "Create the connection",
    "STEP_1_BODY": "Open <strong>Easy Form Builder &raquo; Settings</strong> and choose the <strong>Google Sheets</strong> tab.",
    "IMG_1_ID": "101", "IMG_1_URL": "https://example.com/wp-content/uploads/demo-step-01.webp", "IMG_1_W": "1320", "IMG_1_H": "720",
    "IMG_1_ALT": "Clicking the Google Sheets tab in the Easy Form Builder settings screen",
    "STEP_2_ID": "step-2-authorize-access", "STEP_2_TITLE": "Authorize access",
    "STEP_2_BODY": "Paste the client ID and secret, then press <strong>Authorize</strong>.",
    "IMG_2_ID": "102", "IMG_2_URL": "https://example.com/wp-content/uploads/demo-step-02.webp", "IMG_2_W": "1320", "IMG_2_H": "720",
    "IMG_2_ALT": "Entering the client ID and secret in Easy Form Builder Google Sheets settings",
    "NOTE_1": "Use a dedicated Google account for tutorials so no personal spreadsheet is ever listed.",
    "STEP_3_ID": "step-3-map-the-fields", "STEP_3_TITLE": "Map the form fields",
    "STEP_3_BODY": "Choose a spreadsheet, then map each column to a form field.",
    "IMG_3_ID": "103", "IMG_3_URL": "https://example.com/wp-content/uploads/demo-step-03.webp", "IMG_3_W": "1320", "IMG_3_H": "720",
    "IMG_3_ALT": "Mapping form fields to spreadsheet columns in the Google Sheets add-on panel",
    "STEP_3_EXTRA": "<!-- wp:paragraph -->\n<p class=\"wp-block-paragraph\">Only mapped columns are written.</p>\n<!-- /wp:paragraph -->",
    "RESULT_BODY": "Submit a test entry: a new row appears within seconds.",
    "IMG_RESULT_ID": "104", "IMG_RESULT_URL": "https://example.com/wp-content/uploads/demo-result.webp", "IMG_RESULT_W": "1320", "IMG_RESULT_H": "720",
    "IMG_RESULT_ALT": "A new row with test entry values appearing in the connected Google spreadsheet",
    "TROUBLE_1_SYMPTOM": "No row appears", "TROUBLE_1_CAUSE": "The add-on is not enabled on this form", "TROUBLE_1_FIX": "Enable it in the form settings and save.",
    "TROUBLE_2_SYMPTOM": "Authorization fails", "TROUBLE_2_CAUSE": "Redirect URI mismatch", "TROUBLE_2_FIX": "Copy the redirect URI shown in the plugin.",
    "TROUBLE_3_SYMPTOM": "Columns are empty", "TROUBLE_3_CAUSE": "Field not mapped", "TROUBLE_3_FIX": "Map the column to a field.",
    "WARNING_1": "Never share the client secret in screenshots or support tickets.",
    "FAQ_1_Q": "Does this need the Pro plan?", "FAQ_1_A": "Yes, the Google Sheets add-on is a Pro add-on.",
    "FAQ_2_Q": "Can I use an existing spreadsheet?", "FAQ_2_A": "Yes, pick it in step 3.",
    "FAQ_3_Q": "Are old entries sent?", "FAQ_3_A": "No, only new entries are sent after you save.",
    "RECAP_SENTENCE": "That’s it. New entries now flow into your spreadsheet automatically.",
    "NEXT_URL": "https://whitestudio.team/document/example-next/", "NEXT_ANCHOR": "Send entries to Telegram", "NEXT_REASON": "get a ping for every new entry",
    "RELATED_1_URL": "https://whitestudio.team/document/example-a/", "RELATED_1_ANCHOR": "Manage form entries",
    "RELATED_2_URL": "https://whitestudio.team/document/example-b/", "RELATED_2_ANCHOR": "Add conditional logic",
    "CTA_URL": "https://whitestudio.team/#pricing", "CTA_LABEL": "Get Easy Form Builder Pro",
    "UPDATE_NOTE": "Menu names match version 4.3.1.",
    "VIDEO_EMBED": "<!-- wp:html -->\n<figure class=\"wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio\"><div class=\"wp-block-embed__wrapper\"><iframe title=\"Demo video\" width=\"1280\" height=\"720\" src=\"https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ\" loading=\"lazy\" allowfullscreen></iframe></div></figure>\n<!-- /wp:html -->",
    "VIDEO_TRANSCRIPT": "Open the settings, add credentials, authorize, map the fields and send a test entry.",
}


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("template")
    ap.add_argument("data", nargs="?")
    ap.add_argument("-o", "--output")
    ap.add_argument("--demo", action="store_true", help="use the built-in demo data (placeholder image URLs)")
    a = ap.parse_args(argv)
    try:
        with open(a.template, encoding="utf-8") as fh:
            tpl = fh.read()
        data = DEMO if a.demo else json.load(open(a.data, encoding="utf-8"))
        out = fill(tpl, data)
        problems = validate(out)
    except (ValueError, OSError, json.JSONDecodeError) as exc:
        print(f"fill_template: error: {exc}", file=sys.stderr)
        return 1
    if a.output:
        with open(a.output, "w", encoding="utf-8") as fh:
            fh.write(out)
    else:
        sys.stdout.write(out)
    for p in problems:
        print(f"fill_template: warning: {p}", file=sys.stderr)
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())

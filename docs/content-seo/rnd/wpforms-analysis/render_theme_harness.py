#!/usr/bin/env python3
"""Render the article template with the real whitestudio.team theme CSS in headless Chrome.

Purpose: re-verify after a theme upgrade that every component of article-template.en.html still renders
(callout, quick facts, steps, table, details, buttons). WordPress is NOT involved: core block CSS and
server-side behaviour (lightbox, srcset, oEmbed) are absent, so this proves the theme CSS only.

    # 1. fetch the theme files once (same directory layout as on the site)
    T=/tmp/ws-theme; B=https://whitestudio.team/wp-content/themes/simple-ws-theme
    for f in assets/css/styles.css assets/css/post.css assets/css/wp-bridge.css assets/vendor/bootstrap/bootstrap.min.css style.css; do
        mkdir -p $T/$(dirname $f); curl -sS -L -o $T/$f "$B/$f"; done
    # 2. render
    python3 render_theme_harness.py --theme-dir /tmp/ws-theme --out /tmp/harness.png [--width 1400] [--chrome google-chrome]

Uses the demo data of fill_template.py and four images from tools/annotate/samples/. Exit 1 if Chrome fails.
"""
import argparse
import os
import shutil
import subprocess
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, "..", ".."))
sys.path.insert(0, os.path.join(ROOT, "templates", "article"))
import fill_template as F  # noqa: E402

NEEDED = ["assets/css/styles.css", "assets/css/post.css", "assets/css/wp-bridge.css",
          "assets/vendor/bootstrap/bootstrap.min.css", "style.css"]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--theme-dir", required=True)
    ap.add_argument("--out", required=True)
    ap.add_argument("--width", type=int, default=1400)
    ap.add_argument("--height", type=int, default=5200)
    ap.add_argument("--chrome", default="google-chrome")
    a = ap.parse_args()
    for f in NEEDED:
        if not os.path.exists(os.path.join(a.theme_dir, f)):
            sys.exit(f"missing theme file: {f}")
    tpl = open(os.path.join(ROOT, "templates", "article", "article-template.en.html"), encoding="utf-8").read()
    body = F.fill(tpl, F.DEMO)
    work = tempfile.mkdtemp(prefix="efb-harness-")
    shutil.copytree(a.theme_dir, os.path.join(work, "theme"))
    samples = os.path.join(ROOT, "tools", "annotate", "samples")
    for n, name in enumerate(("sample-output.webp", "sample-output-badges.webp", "sample-output-rtl.webp"), 1):
        shutil.copy(os.path.join(samples, name), os.path.join(work, f"img{n}.webp"))
    for n, key in enumerate(("demo-step-01", "demo-step-02", "demo-step-03"), 1):
        body = body.replace(f"https://example.com/wp-content/uploads/{key}.webp", f"img{n}.webp")
    body = body.replace("https://example.com/wp-content/uploads/demo-result.webp", "img1.webp")
    page = f"""<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="theme/assets/vendor/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="theme/assets/css/styles.css">
<link rel="stylesheet" href="theme/style.css"><link rel="stylesheet" href="theme/assets/css/post.css"><link rel="stylesheet" href="theme/assets/css/wp-bridge.css"></head>
<body class="wp-singular single single-post wp-theme-simple-ws-theme"><main class="wp-block-group ws-single mt-0">
<header class="post-head"><div class="container"><h1>{F.DEMO['TITLE']}</h1></div></header>
<div class="wp-block-group post-layout"><div class="wp-block-group container"><div class="wp-block-group post-grid"><nav class="toc" hidden></nav>
<article class="wp-block-group article"><div class="entry-content wp-block-post-content">{body}</div></article></div></div></div></main></body></html>"""
    html_path = os.path.join(work, "preview.html")
    open(html_path, "w", encoding="utf-8").write(page)
    cmd = [a.chrome, "--headless=new", "--no-sandbox", "--disable-gpu", "--hide-scrollbars",
           f"--window-size={a.width},{a.height}", f"--screenshot={os.path.abspath(a.out)}", "file://" + html_path]
    res = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
    shutil.rmtree(work, ignore_errors=True)
    if res.returncode != 0 or not os.path.exists(a.out):
        sys.exit("chrome failed: " + res.stderr[-400:])
    print("wrote", a.out)


if __name__ == "__main__":
    main()

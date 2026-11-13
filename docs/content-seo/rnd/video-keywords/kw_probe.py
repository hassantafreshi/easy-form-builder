#!/usr/bin/env python3
"""Free video-keyword probe: YouTube + Google autosuggest and YouTube search results.

Standard library only. No login, no API key, no cookies. Unofficial public endpoints:
the endpoints can change or throttle at any time, so keep volumes small and sleep between calls.

Usage (run from anywhere, Python 3.9+):
  python3 kw_probe.py suggest "wordpress contact form" "stop wordpress form spam" > suggest.json
  python3 kw_probe.py serp    "wordpress contact form" > serp.json
  python3 kw_probe.py all     "wordpress contact form" --out out_dir

What it records (see METHOD.md for how to read it):
  suggest: for each seed and each variant ("how to <seed>", "<seed> tutorial", "<seed> wordpress",
           "<seed> plugin") the ordered YouTube and Google suggestions.
  serp:    the first 8 organic YouTube results with title, channel, duration, views, age, chapter count.

Exit code 0 even if a request fails; failures are stored as null so a gap is visible, not silent.
"""
import json
import random
import re
import subprocess
import sys
import time
import urllib.parse

UA = "Mozilla/5.0 (X11; Linux x86_64) Chrome/120 Safari/537.36"
SUGGEST_YT = "https://suggestqueries.google.com/complete/search?client=youtube&ds=yt&hl=en&gl=us&q="
SUGGEST_GG = "https://suggestqueries.google.com/complete/search?client=firefox&hl=en&gl=us&q="
SERP_URL = "https://www.youtube.com/results"


def curl(args):
    """Use curl so the environment's proxy and CA settings apply unchanged. TLS verification stays on."""
    r = subprocess.run(["curl", "-sS", "-m", "40", "-L", "-A", UA] + args, capture_output=True, text=True)
    return r.stdout


def polite_sleep():
    time.sleep(random.uniform(0.5, 1.0))


def variants(seed):
    v = [seed, "how to " + seed, seed + " tutorial"]
    if "wordpress" not in seed:
        v.append(seed + " wordpress")
    v.append(seed + " plugin")
    return v


def yt_suggest(q):
    txt = curl([SUGGEST_YT + urllib.parse.quote(q)])
    m = re.search(r"window\.google\.ac\.h\((.*)\)\s*$", txt.strip(), re.S)
    if not m:
        return None
    return [x[0] for x in json.loads(m.group(1))[1]]


def gg_suggest(q):
    try:
        return json.loads(curl([SUGGEST_GG + urllib.parse.quote(q)]))[1]
    except Exception:
        return None


def suggest(seeds):
    out = []
    for seed in seeds:
        for q in variants(seed):
            out.append({"seed": seed, "query": q, "youtube": yt_suggest(q), "google": gg_suggest(q)})
            polite_sleep()
    return out


def _initial_data(html):
    m = re.search(r"var ytInitialData\s*=\s*(\{.*?\});</script>", html, re.S)
    if not m:
        m = re.search(r'<script id="yt-initial-data"[^>]*>(\{.*?\})</script>', html, re.S)
    return json.loads(m.group(1)) if m else None


def _walk(o, key, acc):
    if isinstance(o, dict):
        for k, v in o.items():
            if k == key:
                acc.append(v)
            else:
                _walk(v, key, acc)
    elif isinstance(o, list):
        for x in o:
            _walk(x, key, acc)


def _txt(x):
    if not x:
        return None
    return x.get("simpleText") or "".join(r.get("text", "") for r in x.get("runs", []))


def _secs(s):
    if not s:
        return None
    n = 0
    for part in s.split(":"):
        n = n * 60 + int(part)
    return n


def serp(query, limit=8):
    html = curl(["-H", "Accept-Language: en-US,en;q=0.9", "-G",
                 "--data-urlencode", "search_query=" + query,
                 "--data-urlencode", "hl=en", "--data-urlencode", "gl=US", SERP_URL])
    data = _initial_data(html)
    if not data:
        return None
    vids = []
    _walk(data, "videoRenderer", vids)
    out = []
    for v in vids[:limit]:
        em = v.get("expandableMetadata", {}).get("expandableMetadataRenderer", {})
        chapters = _txt(em.get("header", {}).get("collapsedLabel")) if em else None
        out.append({
            "video_id": v.get("videoId"),
            "title": _txt(v.get("title")),
            "channel": _txt(v.get("ownerText")),
            "duration_s": _secs(_txt(v.get("lengthText"))),
            "views": _txt(v.get("viewCountText")),
            "age": _txt(v.get("publishedTimeText")),
            "chapters": chapters,
        })
    return out


def main(argv):
    if len(argv) < 3 or argv[1] not in ("suggest", "serp", "all"):
        print(__doc__)
        return 0
    cmd, rest = argv[1], argv[2:]
    outdir = None
    if "--out" in rest:
        i = rest.index("--out")
        outdir = rest[i + 1]
        rest = rest[:i] + rest[i + 2:]
    result = {}
    if cmd in ("suggest", "all"):
        result["suggest"] = suggest(rest)
    if cmd in ("serp", "all"):
        result["serp"] = {}
        for q in rest:
            result["serp"][q] = serp(q)
            time.sleep(random.uniform(2.0, 3.0))
    text = json.dumps(result if cmd == "all" else result[cmd], indent=2, ensure_ascii=False)
    if outdir:
        import os
        os.makedirs(outdir, exist_ok=True)
        with open(os.path.join(outdir, "probe.json"), "w", encoding="utf-8") as f:
            f.write(text)
    else:
        print(text)
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))

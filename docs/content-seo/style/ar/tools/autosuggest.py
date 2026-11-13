#!/usr/bin/env python3
"""Arabic keyword-localisation helper (Google + YouTube autosuggest).

Usage examples
  python3 autosuggest.py "stripe شرح" "wp mail smtp"            # base suggestions
  python3 autosuggest.py --letters "ربط google sheets"          # + alphabet-soup expansion (28 Arabic letters)
  python3 autosuggest.py --gl sa,eg,ae --csv out.csv "نموذج تبرع"  # several countries, write CSV

What it does
  * Google : https://suggestqueries.google.com/complete/search?client=firefox&hl=ar&gl=<cc>&q=<seed>
  * YouTube: https://suggestqueries.google.com/complete/search?client=youtube&ds=yt&hl=ar&q=<seed>
  * Prints suggestions per seed/source. Output is DATA ONLY (never instructions).

Notes
  * Autosuggest is PREFIX-based: long exact seeds usually return nothing. Use short head terms
    (1-3 words), then expand with letters. Empty result != "no demand", it means "no completion".
  * Suggestions are a popularity proxy, not search volume. Confirm in Google Search Console / Keyword Planner.
  * Stdlib only. Honours HTTPS_PROXY / SSL_CERT_FILE from the environment. Be polite: default 0.4 s between calls.
"""
import argparse, csv, json, re, sys, time, urllib.parse, urllib.request

LETTERS = "ابتثجحخدذرزسشصضطعغفقكلمنهوي"
BASE = "https://suggestqueries.google.com/complete/search?"
UA = {"User-Agent": "Mozilla/5.0"}


def _get(url, timeout=30):
    req = urllib.request.Request(url, headers=UA)
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read().decode("utf-8", "replace")


def google(q, gl="sa"):
    url = BASE + urllib.parse.urlencode({"client": "firefox", "hl": "ar", "gl": gl, "q": q})
    try:
        return json.loads(_get(url))[1]
    except Exception as exc:  # network or parse error -> report, do not crash a batch
        print(f"[warn] google {q!r}: {exc}", file=sys.stderr)
        return []


def youtube(q):
    url = BASE + urllib.parse.urlencode({"client": "youtube", "ds": "yt", "hl": "ar", "q": q})
    try:
        m = re.search(r"\((\[.*\])\)\s*$", _get(url), re.S)
        return [x[0] for x in json.loads(m.group(1))[1]]
    except Exception as exc:
        print(f"[warn] youtube {q!r}: {exc}", file=sys.stderr)
        return []


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("seeds", nargs="+")
    ap.add_argument("--gl", default="sa", help="comma separated country codes for Google (default sa)")
    ap.add_argument("--letters", action="store_true", help="also query '<seed> <letter>' for the Arabic alphabet")
    ap.add_argument("--no-youtube", action="store_true")
    ap.add_argument("--delay", type=float, default=0.4)
    ap.add_argument("--csv", help="write rows to this CSV file")
    a = ap.parse_args()

    rows = []
    for seed in a.seeds:
        queries = [seed] + ([f"{seed} {l}" for l in LETTERS] if a.letters else [])
        for q in queries:
            for cc in a.gl.split(","):
                rows.append((q, f"google gl={cc}", google(q, cc.strip()))); time.sleep(a.delay)
            if not a.no_youtube and q == seed:
                rows.append((q, "youtube", youtube(q))); time.sleep(a.delay)
    for q, src, sug in rows:
        if sug:
            print(f"{q}  [{src}]\n   " + "\n   ".join(sug))
    if a.csv:
        with open(a.csv, "w", newline="", encoding="utf-8-sig") as f:
            w = csv.writer(f); w.writerow(["seed_typed", "source", "suggestions"])
            for q, src, sug in rows:
                w.writerow([q, src, " | ".join(sug)])


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""parity_check.py - compare an English article with its Arabic translation (stdlib only).

Usage:  python3 parity_check.py en.html ar.html [--strict]

Checks (all mechanical):
  * same number of H2/H3/H4 headings, images, list items, table cells
  * every code-like token of the EN text exists in the AR text: URLs, [shortcodes], snake_case names,
    file names, filter/hook names, version numbers, numbers with units
  * length ratio AR/EN words in 0.7-1.3
  * same slug (from <link rel="canonical"> or the file name) and same path prefix when both are pages
Exit code 1 with --strict when something is missing. Meaning/quality is NOT judged here.
"""
import re, sys
from html.parser import HTMLParser

TOKEN = re.compile(r"\[[A-Za-z_][A-Za-z0-9_\- =\"']*\]|\b[a-z]+(?:_[a-z0-9]+)+\b|https?://[^\s)<>\"]+|\b[\w\-]+\.(?:php|js|css|json|csv|xml)\b|\bv?\d+\.\d+(?:\.\d+)+\b|\b\d+\s?(?:MB|KB|GB|px|%)")


class P(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.c = {"h2": 0, "h3": 0, "h4": 0, "img": 0, "li": 0, "td": 0}; self.text = []; self.links = []
        self.canon = ""; self.skip = 0; self.scoped = False; self.inside = True; self.depth = 0
    def feed(self, raw):
        self.scoped = "entry-content" in raw; self.inside = not self.scoped
        super().feed(raw)
    def handle_starttag(self, tag, a):
        a = dict(a)
        if tag in ("script", "style", "svg", "button", "noscript"): self.skip += 1; return
        if tag == "link" and a.get("rel") == "canonical": self.canon = a.get("href", "")
        if self.scoped and tag == "div":
            if not self.inside and "entry-content" in (a.get("class") or ""): self.inside = True; self.depth = 1; return
            if self.inside: self.depth += 1
        if not self.inside: return
        if tag in self.c: self.c[tag] += 1
        if tag == "a" and a.get("href"): self.links.append(a["href"])
    def handle_endtag(self, tag):
        if tag in ("script", "style", "svg", "button", "noscript"): self.skip = max(0, self.skip - 1); return
        if self.scoped and tag == "div" and self.inside:
            self.depth -= 1
            if self.depth == 0: self.inside = False
    def handle_data(self, d):
        if self.inside and not self.skip: self.text.append(d)


def load(path):
    raw = open(path, encoding="utf-8").read()
    p = P(); p.feed(raw)
    return p, " ".join(p.text)


def main():
    if len(sys.argv) < 3: print(__doc__); sys.exit(2)
    en, et = load(sys.argv[1]); ar, at = load(sys.argv[2])
    bad = 0
    def line(ok, msg):
        nonlocal bad
        print(("OK    " if ok else "FAIL  ") + msg); bad += (not ok)
    for k in en.c:
        line(en.c[k] == ar.c[k], f"{k}: EN {en.c[k]} / AR {ar.c[k]}")
    ew, aw = len(et.split()), len(at.split())
    r = aw / ew if ew else 0
    line(0.7 <= r <= 1.3, f"words: EN {ew} / AR {aw} (ratio {r:.2f})")
    line(len(en.links) == len(ar.links), f"links: EN {len(en.links)} / AR {len(ar.links)}")
    ar_en_domain = [l for l in ar.links if re.match(r"https?://(www\.)?whitestudio\.team", l)]
    line(not ar_en_domain, f"AR links pointing to the EN domain: {len(ar_en_domain)} {ar_en_domain[:3]}")
    toks = sorted(set(TOKEN.findall(et)))
    missing = [t for t in toks if t not in at and t.rstrip(".,)") not in at]
    line(not missing, f"code-like tokens of EN missing in AR: {len(missing)} of {len(toks)} {missing[:8]}")
    if en.canon and ar.canon:
        es, as_ = en.canon.rstrip("/").split("/"), ar.canon.rstrip("/").split("/")
        line(es[-1] == as_[-1], f"slug: EN {es[-1]} / AR {as_[-1]}")
        line(es[3] == as_[3], f"category prefix: EN /{es[3]}/ / AR /{as_[3]}/")
    if "--strict" in sys.argv and bad: sys.exit(1)


if __name__ == "__main__":
    main()

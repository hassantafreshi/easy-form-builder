#!/usr/bin/env python3
"""ar_lint.py - rule-based linter for Arabic Easy Form Builder articles (stdlib only).

Checks the mechanical rules of STYLE-GUIDE.md and GLOSSARY.csv. It does NOT judge meaning:
a clean run is necessary, not sufficient (see QA-CHECKLIST.md for the human/LLM review).

Usage
  python3 ar_lint.py article.html            # HTML: checks h1/title/meta/alt + body
  python3 ar_lint.py article.md              # Markdown/plain text: first '# ' line = title
  python3 ar_lint.py article.html --json     # machine-readable
  python3 ar_lint.py article.html --strict   # exit code 1 on any ERROR

Severity: ERROR = must fix before publishing, WARN = fix unless there is a reason, INFO = review.
"""
import json, re, sys
from html.parser import HTMLParser

AR = r"؀-ۿ"
WB = rf"(?<![{AR}])(?:%s)(?![{AR}])"          # whole Arabic word


def words(*ws):
    return re.compile(WB % "|".join(re.escape(w) for w in ws))


# (id, severity, regex, message)
RULES = [
    ("digits-arabic-indic", "ERROR", re.compile(r"[٠-٩۰-۹]"), "Arabic-Indic/Persian digits: use Western digits 0-9"),
    ("latin-punct-in-arabic", "WARN", re.compile(rf"[{AR}]\s?[,;?]"), "Latin comma/semicolon/question mark after Arabic: use ، ؛ ؟"),
    ("quotes-curly-or-straight", "WARN", re.compile(rf"[“”‘’]|\"[{AR}]|[{AR}]\""), "Use « » for quoted text (not “ ” or \")"),
    ("tatweel", "WARN", re.compile("ـ"), "Tatweel (ـ): write the full word, do not glue prefixes to Latin (بـ، لـ، فـ)"),
    ("ellipsis-dots", "WARN", re.compile(r"\.\.\."), "Use the single character … "),
    ("brand-dup", "ERROR", re.compile(r"منشئ منشئ|السهل السهل|السهلل"), "Brand name duplicated or misspelled"),
    ("brand-old-spelling", "ERROR", re.compile(r"م[ُ]?نشيء|منشِئ"), "Use «منشئ النماذج السهل» (not مُنشيء / منشِئ)"),
    ("brand-singular", "ERROR", re.compile(r"منشئ النموذج السهل"), "Brand is «منشئ النماذج السهل» (plural النماذج)"),
    ("wp-article", "ERROR", re.compile(r"الووردبريس|الوردبريس|(?<!و)وردبريس|ووردبرس|ورد بريس"), "Spell WordPress «ووردبريس», no ال attached"),
    ("persian-letters", "WARN", re.compile(r"[پچژگکی‌]"), "Persian-only letters/ZWNJ (پ چ ژ گ ک ی): not Arabic (ignore only when deliberately quoting Persian text)"),
    ("term-confirmation-code", "ERROR", re.compile(r"كود التأكيد|كود التتبع"), "UI term is «رمز التأكيد»"),
    ("term-activation", "WARN", words("تنشيط", "نشّط", "نشط"), "Activate = تفعيل (UI/WordPress), not تنشيط"),
    ("term-required", "WARN", words("إلزامي", "إلزامية", "الإلزامية", "إجباري", "اجباري"), "Required field = «مطلوب» (UI/WordPress)"),
    ("term-server", "WARN", words("سيرفر", "السيرفر"), "Server = خادم"),
    ("term-email", "WARN", words("إيميل", "الإيميل", "ايميل", "الايميل"), "Email = البريد الإلكتروني"),
    ("term-admin", "WARN", words("الأدمن", "أدمن", "المسؤول", "مسؤول"), "Administrator = مدير / مدير الموقع (WP core + UI); check context"),
    ("term-hosting", "WARN", words("هوستنج", "هوستينج"), "Hosting = الاستضافة"),
    ("term-theme", "WARN", words("ثيم", "الثيم"), "Theme = قالب"),
    ("term-google", "WARN", words("غوغل", "قوقل"), "Google = جوجل (UI spelling)"),
    ("term-telegram", "WARN", words("تلجرام", "تليجرام", "تلغرام", "تيلجرام"), "Telegram = تيليجرام (UI spelling)"),
    ("term-megabyte", "WARN", words("ميغابايت", "ميغا"), "Use ميجابايت (ج, consistent with جوجل/تيليجرام)"),
    ("term-checkbox", "WARN", words("خانة", "خانات"), "Checkbox = مربع اختيار (UI)"),
    ("term-plan", "WARN", words("باقة", "الباقة", "باقات"), "Plan = خطة (UI)"),
    ("term-dragdrop", "WARN", re.compile(r"السحب والإسقاط|سحب وإسقاط|السحب والترك"), "Drag & drop = السحب والإفلات (UI)"),
    ("term-condlogic", "ERROR", re.compile(r"المنطق المشروط"), "Conditional logic = المنطق الشرطي (UI)"),
    ("term-shortcode", "WARN", re.compile(r"الاختصار البرمجي|شورت ?كود|الرمز المختصر|الكود المختصر|الرمز القصير"), "Shortcode = الكود القصير (UI)"),
    ("term-addon-as-plugin", "WARN", re.compile(r"إضافة (?:Stripe|PayPal|تيليجرام|Telegram|SMS|المنطق الشرطي)|إضافات Stripe"), "EFB add-on = ملحق (إضافة = WordPress plugin)"),
    ("term-responsive", "INFO", words("مستجيب", "مستجيبة"), "Site uses «متجاوب»"),
    ("term-spam-sms", "INFO", re.compile(r"الرسائل المزعجة"), "«البريد المزعج» is the UI term for spam"),
    ("term-mumkin", "INFO", words("ممكن"), "Colloquial «ممكن» as a sentence opener: prefer يمكن / من الممكن"),
    ("term-faq", "INFO", re.compile(r"الأسئلة المتكررة|الأسئلة التكررة"), "Default FAQ heading: «الأسئلة الشائعة»"),
    ("term-upload-download", "INFO", re.compile(r"تحميل"), "رفع = upload, تنزيل = download; تحميل only in download-intent keywords"),
    ("term-redirect", "INFO", re.compile(r"التحويل"), "Redirect = إعادة التوجيه; التحويل means transfer/conversion"),
    ("calque-qum-bi", "WARN", re.compile(rf"(?<![{AR}])قم ب[{AR}]+"), "Calque «قم بـ + noun»: use a direct imperative verb"),
    ("calque-tutorial", "WARN", re.compile(r"في هذا البرنامج التعليمي"), "Calque of 'In this tutorial': use «في هذا الدليل» or skip"),
    ("calque-thats-it", "WARN", re.compile(r"هذا كل شيء"), "Calque of 'That's it!': delete or use a concrete result sentence"),
    ("calque-journey", "WARN", re.compile(r"ابدأ رحلتك|رحلة (?:إدارة|بناء|إنشاء)"), "Marketing calque 'journey'"),
    ("calque-effortless", "INFO", re.compile(r"بكل سهولة|بسلاسة"), "Filler 'effortlessly/seamlessly': keep only with a concrete reason"),
    ("calque-please", "INFO", re.compile(r"يُرجى|يرجى"), "'Please ...' is formal filler in tutorials"),
    ("calque-looking-for", "INFO", re.compile(r"هل تبحث عن"), "Overused opener 'Are you looking for' (11 old articles)"),
    ("dialect", "ERROR", words("عشان", "ليش", "وين", "مو", "لازم", "اللي", "تقدر", "تقدرون", "بدك", "بدي", "هيك", "شو", "إيش", "ايش", "دلوقتي", "كده", "كدا", "شلون", "بدري", "تنضاف", "يبي"), "Dialect word: write Modern Standard Arabic"),
    ("dialect-halprefix", "WARN", re.compile(rf"(?<![{AR}])ها[لا][{AR}]{{3,}}"), "Possible dialect demonstrative (هالـ…)"),
    ("plural-address", "WARN", words("لا تترددوا", "تواصلوا", "اتصلوا", "يمكنكم", "ننصحكم", "لديكم", "تستطيعون", "ستحتاجون"), "Address the reader in masculine singular (أنت), not plural"),
    ("emoji", "WARN", re.compile("[\U0001F300-\U0001FAFF✅❌✔⭐✨]"), "No emoji in headings/body"),
    ("nav-gt", "WARN", re.compile(rf"[{AR}A-Za-z\)]\s*>\s*[{AR}A-Za-z«“]"), "Navigation path with '>' mirrors in RTL: use ←"),
    ("email-obfuscated", "ERROR", re.compile(r"\[email\s*protected\]"), "Cloudflare obfuscation leaked into text"),
    ("space-before-period", "WARN", re.compile(rf"[{AR}]\s+\.(?!\w)"), "Space before period (dropped token / glued tag?)"),
    ("orphan-preposition", "ERROR", re.compile(rf"(?<![{AR}])(?:من|في|إلى|على|عن|مع|بين|عبر)\s+[.،:]"), "Preposition followed by punctuation: a word/placeholder was dropped"),
    ("em-dash", "INFO", re.compile("—"), "Em dash: prefer ':' or '،' (review if more than 3)"),
    ("double-space", "WARN", re.compile(r"[ \t]{2,}"), "Double space"),
    ("nbsp", "INFO", re.compile(" "), "Non-breaking space inside text"),
]

LATIN_PROSE = re.compile(r"[A-Za-z]{2,}")
AR_RE = re.compile(f"[{AR}]")


class Doc(HTMLParser):
    """Collects h1/title/meta everywhere, but body blocks and images only inside
    <div class="entry-content"> when the page has one (skips theme chrome, sidebar, related posts)."""
    def __init__(self, scoped):
        super().__init__(convert_charrefs=True)
        self.scoped = scoped; self.depth = 0; self.inside = not scoped
        self.h1 = ""; self.title = ""; self.meta = ""; self.imgs = []; self.blocks = []
        self._tag = None; self._buf = []; self._skip = 0
    def handle_starttag(self, tag, a):
        a = dict(a)
        if tag in ("script", "style", "svg", "button", "noscript"): self._skip += 1; return
        if tag == "meta" and (a.get("name") or "").lower() == "description": self.meta = a.get("content", "")
        if self.scoped and tag == "div":
            if not self.inside and "entry-content" in (a.get("class") or ""): self.inside = True; self.depth = 1; return
            if self.inside: self.depth += 1
        if tag == "img" and self.inside: self.imgs.append(a.get("alt", ""))
        if tag in ("h1", "title") or (self.inside and tag in ("h2", "h3", "h4", "p", "li", "td", "th", "figcaption")):
            self._flush(); self._tag = tag
    def handle_endtag(self, tag):
        if tag in ("script", "style", "svg", "button", "noscript"): self._skip = max(0, self._skip - 1); return
        if self.scoped and tag == "div" and self.inside:
            self.depth -= 1
            if self.depth == 0: self._flush(); self.inside = False; return
        if tag == self._tag: self._flush()
    def handle_data(self, d):
        if not self._skip and self._tag: self._buf.append(d)
    def _flush(self):
        t = re.sub(r"\s+", " ", "".join(self._buf)).strip()
        if t and self._tag:
            if self._tag == "h1" and not self.h1: self.h1 = t
            elif self._tag == "title" and not self.title: self.title = t
            elif self._tag not in ("h1", "title"): self.blocks.append((self._tag, t))
        self._buf = []; self._tag = None


def load(path):
    raw = open(path, encoding="utf-8").read()
    if re.search(r"<(html|h1|p|article)\b", raw, re.I):
        d = Doc(scoped="entry-content" in raw); d.feed(raw); d._flush()
        return d.h1, d.title, d.meta, d.imgs, d.blocks
    lines = raw.splitlines(); h1 = ""
    for i, l in enumerate(lines):
        if l.startswith("# "): h1 = l[2:].strip(); lines.pop(i); break
    return h1, "", "", [], [("p", l) for l in lines if l.strip()]


def lint(h1, title, meta, imgs, blocks):
    out = []
    def add(sev, rid, msg, ctx=""): out.append({"severity": sev, "rule": rid, "message": msg, "context": ctx[:90]})
    text_items = [("h1", h1)] + [(t, x) for t, x in blocks]
    for tag, txt in text_items:
        if not txt: continue
        for rid, sev, rx, msg in RULES:
            for m in rx.finditer(txt):
                s = max(0, m.start() - 25); add(sev, rid, msg, txt[s:m.end() + 25])
                break  # one hit per rule per block keeps the report readable
        if tag in ("p", "li") and not AR_RE.search(txt) and len(LATIN_PROSE.findall(txt)) >= 6 and not re.search(r"[{};=<>$]|https?:", txt):
            add("WARN", "english-prose-block", "Untranslated English block", txt)
    n = len(h1)
    if h1 and n > 60: add("WARN", "title-length", f"H1 is {n} chars (target <= 60)", h1)
    if h1 and re.match(r"^(كيفية|كيف)\s", h1): add("INFO", "title-kayfiyya", "Title starts with كيفية/كيف: vary the pattern (56% of old titles do)", h1)
    if h1 and "|" in h1: add("WARN", "title-pipe", "Avoid '|' in titles", h1)
    if title and len(title) > 60: add("INFO", "titletag-length", f"<title> is {len(title)} chars (target <= 60 incl. suffix)", title)
    if title and title.count("النماذج السهل") >= 2: add("WARN", "titletag-brand-twice", "Brand appears twice in <title>", title)
    if meta:
        ml = len(meta)
        if not AR_RE.search(meta): add("ERROR", "meta-not-arabic", "Meta description is not Arabic", meta)
        if not 140 <= ml <= 155: add("WARN", "meta-length", f"Meta description is {ml} chars (target 140-155)", meta)
    elif h1:
        add("INFO", "meta-missing", "No <meta name=description> found (ignore for plain-text input)")
    empty = sum(1 for a in imgs if not a.strip())
    if empty: add("WARN", "alt-empty", f"{empty} image(s) with empty alt")
    for a in imgs:
        if a.strip() and len(a) > 150: add("INFO", "alt-long", f"alt is {len(a)} chars (target <= 150)", a)
        if a.strip() and not AR_RE.search(a): add("WARN", "alt-not-arabic", "alt text is not Arabic", a)
    passives = sum(len(re.findall(rf"(?<![{AR}])(?:تم|يتم)(?![{AR}])", t)) for _, t in blocks)
    wc = sum(len(t.split()) for _, t in blocks) or 1
    if passives * 1000 / wc > 8: add("INFO", "passive-density", f"{passives} تم/يتم in {wc} words: prefer active voice in steps")
    return out, wc


def main():
    if len(sys.argv) < 2: print(__doc__); sys.exit(2)
    path = sys.argv[1]
    res, wc = lint(*load(path))
    if "--json" in sys.argv:
        print(json.dumps({"words": wc, "findings": res}, ensure_ascii=False, indent=1))
    else:
        order = {"ERROR": 0, "WARN": 1, "INFO": 2}
        for f in sorted(res, key=lambda f: order[f["severity"]]):
            print(f"{f['severity']:5s} {f['rule']:24s} {f['message']}" + (f"\n        … {f['context']}" if f["context"] else ""))
        c = {s: sum(1 for f in res if f["severity"] == s) for s in order}
        print(f"\n{wc} words | ERROR {c['ERROR']} | WARN {c['WARN']} | INFO {c['INFO']}")
    if "--strict" in sys.argv and any(f["severity"] == "ERROR" for f in res): sys.exit(1)


if __name__ == "__main__":
    main()

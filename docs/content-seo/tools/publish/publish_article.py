#!/usr/bin/env python3
"""Article build + publish for one language (first used for R47, 2026-11-14).

POST/AUTHOR/CATS/TAGS/STEP_IMAGES below are R47 values: set them per row (from content-queue.csv) before use.

  build.py <lang> render           -> body.<lang>.html (uses uploads.<lang>.json if present, else placeholder images)
  build.py <lang> upload           -> uploads screenshots, writes uploads.<lang>.json (idempotent by filename)
  build.py <lang> publish          -> PUT the post (content, title, excerpt, author, categories, tags, featured, SEO meta)
  build.py <lang> verify           -> read back + live page checks

Credentials come only from the environment (WP_<L>_USER / WP_<L>_APP_PASSWORD); nothing secret is printed or written.
"""
import base64, importlib.util, json, os, re, sys, urllib.request, urllib.error, html as H

HERE = os.environ.get("EFB_ARTICLE_DIR", os.path.dirname(os.path.abspath(__file__)))  # folder with content_<lang>.py and uploads.<lang>.json
REPO = "/home/user/easy-form-builder/docs/content-seo"
sys.path.insert(0, os.path.join(REPO, "templates/article"))
import fill_template as FT  # noqa: E402

SHOTS = os.environ.get("EFB_SHOTS_DIR", "/tmp/efb-shots")  # per-language screenshots: <SHOTS>/<lang>/<nn-name>.webp
SITE = {"en": "https://whitestudio.team", "ar": "https://ar.whitestudio.team", "de": "https://de.whitestudio.team"}
POST = {"en": 1455, "ar": 1331, "de": 1432}
AUTHOR = {"en": 1, "ar": 1, "de": 1}            # decision 8c (owner): EN admin, AR admin, DE hassan
CATS = {"en": [3, 5], "ar": [3], "de": [2]}      # primary section = document on all three (hreflang needs the same path)
TAGS = {"en": [7, 9, 10, 11, 12], "ar": [7, 9, 10, 11, 12], "de": [17, 24, 38, 52]}
# step -> screenshot names (first = step image, rest = extra images)
STEP_IMAGES = {1: ["00-free-plus-plan"], 2: ["01-addons-stripe"], 3: ["02-payments-keys"],
               4: ["03-payment-form-template", "04-builder-fields"], 5: ["05-price-options"], 6: ["06-shortcode"],
               7: ["07-frontend-form", "08-confirmation"], 8: ["09-stripe-payments-completed"]}


def load_content(lang):
    spec = importlib.util.spec_from_file_location(f"content_{lang}", os.path.join(HERE, f"content_{lang}.py"))
    m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m); return m


def auth(lang):
    u, p = os.environ[f"WP_{lang.upper()}_USER"], os.environ[f"WP_{lang.upper()}_APP_PASSWORD"]
    return "Basic " + base64.b64encode(f"{u}:{p}".encode()).decode()


def api(lang, method, path, body=None, raw=None, headers=None):
    url = SITE[lang] + "/wp-json" + path
    h = {"Authorization": auth(lang), "User-Agent": "efb-content-pipeline/1.0"}
    data = None
    if raw is not None:
        data = raw
    elif body is not None:
        data = json.dumps(body).encode(); h["Content-Type"] = "application/json"
    h.update(headers or {})
    req = urllib.request.Request(url, data=data, method=method, headers=h)
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            return r.status, json.loads(r.read() or b"null")
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read() or b"null")


def img_block(img):
    return ('<!-- wp:image {"id":%d,"sizeSlug":"large","linkDestination":"none"} -->\n'
            '<figure class="wp-block-image size-large"><img src="%s" alt="%s" class="wp-image-%d" width="%d" height="%d"/></figure>\n'
            '<!-- /wp:image -->') % (img["id"], H.escape(img["url"]), H.escape(img["alt"]), img["id"], img["w"], img["h"])


def render(lang):
    C = load_content(lang)
    data = {k: v for k, v in C.D.items() if isinstance(v, str)}
    up_path = os.path.join(HERE, f"uploads.{lang}.json")
    ups = json.load(open(up_path)) if os.path.exists(up_path) else {}
    alts = C.ALTS
    for n, names in STEP_IMAGES.items():
        imgs = []
        for name in names:
            u = ups.get(name) or {"id": 900 + n, "url": f"https://example.invalid/{name}.webp", "w": 1600, "h": 900}
            imgs.append({**u, "alt": alts[name]})
        first = imgs[0]
        data.update({f"IMG_{n}_ID": str(first["id"]), f"IMG_{n}_URL": first["url"], f"IMG_{n}_W": str(first["w"]),
                     f"IMG_{n}_H": str(first["h"]), f"IMG_{n}_ALT": first["alt"]})
        if len(imgs) > 1:
            extra = "\n\n".join(img_block(i) for i in imgs[1:])
            data[f"STEP_{n}_EXTRA"] = extra if f"STEP_{n}_EXTRA" not in data else data[f"STEP_{n}_EXTRA"] + "\n\n" + extra
    data["VIDEO_EMBED"] = ('<!-- wp:embed {"url":"https://www.youtube.com/watch?v=%s","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->\n'
                           '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">\n'
                           'https://www.youtube.com/watch?v=%s\n</div></figure>\n<!-- /wp:embed -->') % (C.YT, C.YT)
    tpl = open(os.path.join(REPO, "templates/article/article-template.en.html"), encoding="utf-8").read()
    out = FT.fill(tpl, data)
    # extra troubleshooting rows
    rows = "".join("<tr><td>%s</td><td>%s</td><td>%s</td></tr>" % tuple(r) for r in C.D.get("TROUBLE_EXTRA_ROWS", []))
    out = out.replace("</tbody></table></figure>", rows + "</tbody></table></figure>", 1)
    # third related link
    if C.D.get("RELATED_3_URL"):
        li = ('<!-- wp:list-item -->\n<li><a href="%s">%s</a></li>\n<!-- /wp:list-item -->' % (H.escape(C.D["RELATED_3_URL"]), C.D["RELATED_3_ANCHOR"]))
        i = out.rfind("<!-- /wp:list-item --></ul>")
        out = out[:i] + "<!-- /wp:list-item -->\n\n" + li + "</ul>" + out[i + len("<!-- /wp:list-item --></ul>"):]
    # localise fixed template strings
    for pat, rep in C.LABELS_REPLACE:
        out, n = re.subn(pat, rep, out)
        if n == 0:
            raise SystemExit(f"label pattern not found: {pat}")
    cap = getattr(C, "VIDEO_CAPTION", "")
    if cap:
        out = out.replace("</div></figure>\n<!-- /wp:embed -->", '</div><figcaption class="wp-element-caption">%s</figcaption></figure>\n<!-- /wp:embed -->' % H.escape(cap), 1)
    if lang == "ar":  # LTR fragments: code blocks and inline code
        out = out.replace("<code>", '<code dir="ltr">')
    problems = FT.validate(out)
    open(os.path.join(HERE, f"body.{lang}.html"), "w", encoding="utf-8").write(out)
    return out, problems


def upload(lang):
    C = load_content(lang)
    up_path = os.path.join(HERE, f"uploads.{lang}.json")
    ups = json.load(open(up_path)) if os.path.exists(up_path) else {}
    names = [n for v in STEP_IMAGES.values() for n in v] + ["cover"]
    for name in names:
        if name in ups:
            continue
        src = os.path.join(SHOTS, lang, f"{name}.webp") if name != "cover" else C.COVER_FILE
        fn = f"stripe-payment-form-wordpress-{lang}-{name}.webp" if name != "cover" else os.path.basename(C.COVER_FILE)
        ctype = "image/webp" if src.endswith(".webp") else ("image/jpeg" if src.endswith(".jpg") else "image/png")
        st, r = api(lang, "POST", "/wp/v2/media", raw=open(src, "rb").read(),
                    headers={"Content-Type": ctype, "Content-Disposition": f'attachment; filename="{fn}"'})
        if st not in (200, 201):
            raise SystemExit(f"upload {name}: HTTP {st} {str(r)[:200]}")
        alt = C.ALTS[name]
        api(lang, "POST", f"/wp/v2/media/{r['id']}", body={"alt_text": alt, "title": alt[:80]})
        md = r.get("media_details") or {}
        ups[name] = {"id": r["id"], "url": r["source_url"], "w": md.get("width"), "h": md.get("height")}
        json.dump(ups, open(up_path, "w"), indent=1)
        print("uploaded", name, r["id"], md.get("width"), md.get("height"))
    return ups


def publish(lang):
    C = load_content(lang)
    body, problems = render(lang)
    if problems:
        raise SystemExit("render problems: " + "; ".join(problems))
    if "example.invalid" in body:
        raise SystemExit("placeholder image left in body")
    ups = json.load(open(os.path.join(HERE, f"uploads.{lang}.json")))
    payload = {"title": C.META["post_title"], "content": body, "excerpt": C.META["excerpt"], "author": AUTHOR[lang],
               "categories": CATS[lang], "tags": TAGS[lang], "featured_media": ups["cover"]["id"], "status": "publish"}
    if lang == "en":
        payload["meta"] = {"_yoast_wpseo_title": C.META["seo_title"], "_yoast_wpseo_metadesc": C.META["meta_desc"],
                           "_yoast_wpseo_focuskw": C.META["focus_kw"]}
    st, r = api(lang, "POST", f"/wp/v2/posts/{POST[lang]}", body=payload)
    print("post", st, r.get("link") if isinstance(r, dict) else r)
    if st != 200:
        raise SystemExit(str(r)[:400])
    if lang == "ar":
        st2, r2 = api(lang, "POST", "/rankmath/v1/updateMeta", body={"objectType": "post", "objectID": POST[lang],
                      "meta": {"rank_math_title": C.META["seo_title"], "rank_math_description": C.META["meta_desc"],
                               "rank_math_focus_keyword": C.META["focus_kw"]}})
        print("rankmath", st2, str(r2)[:200])
    return r


if __name__ == "__main__":
    lang, cmd = sys.argv[1], sys.argv[2]
    if cmd == "render":
        out, p = render(lang); print(len(out), "bytes;", "problems:", p)
    elif cmd == "upload":
        upload(lang)
    elif cmd == "publish":
        publish(lang)

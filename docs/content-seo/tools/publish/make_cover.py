#!/usr/bin/env python3
"""cover.py <lang> <ui_crop.png> -> cover-<lang>.jpg (1600x900, JPEG q82). Brand gradient, real UI crop, <=5 words, logo."""
import sys, os, subprocess, json
HERE = os.path.dirname(os.path.abspath(__file__))
lang, crop = sys.argv[1], sys.argv[2]
T = {"en": ("FREE PLUS", "Stripe Payment Form", "in WordPress", "ltr"),
     "de": ("FREE PLUS", "Stripe-Zahlungs&shy;formular", "in WordPress", "ltr"),
     "ar": ("مجاني بلس", "نموذج دفع Stripe", "في ووردبريس", "rtl")}[lang]
chip, h1, h2, d = T
side_txt, side_img = ("left", "right") if d == "ltr" else ("right", "left")
rot = "3deg" if d == "ltr" else "-3deg"
html = f"""<!doctype html><html dir="{d}"><head><meta charset="utf-8">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@800;900&display=swap" rel="stylesheet">
<style>
html,body{{margin:0;width:1600px;height:900px;overflow:hidden}}
body{{background:linear-gradient(135deg,#652EBA 0%,#4C238B 100%);position:relative;font-family:'Inter Display','Inter','Cairo',sans-serif;color:#fff}}
.blob{{position:absolute;width:1000px;height:1000px;border-radius:50%;{side_img}:-300px;top:-300px;background:radial-gradient(circle,rgba(255,75,147,.35) 0%,rgba(255,75,147,0) 60%)}}
.txt{{position:absolute;{side_txt}:90px;top:150px;width:690px}}
.chip{{display:inline-block;background:#FF4B93;font-weight:900;font-size:50px;padding:8px 28px;border-radius:16px}}
h1{{margin:34px 0 0;font-weight:900;font-size:{'104' if lang!='de' else '96'}px;line-height:1.02;letter-spacing:{'-2px' if d=='ltr' else '0'};hyphens:manual}}
h2{{margin:18px 0 0;font-weight:800;font-size:58px;color:#E2D5FA}}
.brand{{position:absolute;{side_txt}:84px;bottom:64px;display:flex;align-items:center;gap:22px;font-size:58px;font-weight:900;font-family:'Inter Display','Inter',sans-serif}}
.brand img{{width:110px;height:108px;background:#fff;border-radius:24px;padding:10px;box-sizing:border-box}}
.card{{position:absolute;{side_img}:80px;top:150px;width:700px;border-radius:26px;overflow:hidden;box-shadow:0 34px 80px rgba(0,0,0,.45);transform:rotate({rot});border:8px solid #fff;background:#fff}}
.card img{{display:block;width:100%}}
</style></head><body><div class="blob"></div>
<div class="txt"><span class="chip">{chip}</span><h1>{h1}</h1><h2>{h2}</h2></div>
<div class="card"><img src="file://{os.path.abspath(crop)}"></div>
<div class="brand" dir="ltr"><img src="file:///home/user/easy-form-builder/includes/admin/assets/image/logo.svg"><span>Easy Form Builder</span></div>
</body></html>"""
p = os.path.join(HERE, f"cover-{lang}.html"); open(p, "w", encoding="utf-8").write(html)
png = os.path.join(HERE, f"cover-{lang}.png")
subprocess.run(["node", "-e", f"""const {{chromium}}=require('playwright');(async()=>{{const b=await chromium.launch();const pg=await b.newPage({{viewport:{{width:1600,height:900}},deviceScaleFactor:1}});await pg.goto('file://{p}');await pg.waitForLoadState('networkidle');await pg.evaluate(()=>document.fonts.ready);await pg.screenshot({{path:'{png}'}});await b.close()}})()"""], check=True)
from PIL import Image
im = Image.open(png).convert("RGB"); out = os.path.join(HERE, f"cover-{lang}.jpg"); im.save(out, quality=82, optimize=True, progressive=True)
print(out, os.path.getsize(out))

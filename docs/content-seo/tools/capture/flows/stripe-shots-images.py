#!/usr/bin/env python3
"""
stripe-shots-images.py - turn the raw stills of flows/stripe-shots.mjs into article images (R47, Stripe payment form).

    python3 tools/capture/flows/stripe-shots-images.py --raw $EFB_WORK/r47-raw --out DIR [--specs DIR] [en de ar]

For every <lang>/raw/<nn>-<name>.png it reads <lang>/boxes/<nn>-<name>.json (DOM boxes written during the capture, CSS px),
derives the crop and the marks (IMAGE-STYLE-GUIDE: crop <= 800 CSS px, one or two pink marks, arrow tips outside the
control, RTL mirrored by the boxes themselves), writes the annotate.py spec and runs tools/annotate/annotate.py ->
DIR/<lang>/<nn>-<name>.webp. Coordinates always come from the locale's own DOM, never from another language.
A report (what each image shows + lint) is written to SPECS/report.json.
"""
import argparse, json, os, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
ANN = os.path.normpath(os.path.join(HERE, '..', '..', 'annotate', 'annotate.py'))
_ap = argparse.ArgumentParser()
_ap.add_argument('--raw', default=os.path.join(os.environ.get('EFB_WORK', '/tmp/efb-tutorial'), 'r47-raw'))
_ap.add_argument('--out', required=True)
_ap.add_argument('--specs', default=None)
_ap.add_argument('langs', nargs='*', default=['en', 'de', 'ar'])
_A = _ap.parse_args()
RAW, OUT, LANGS = _A.raw, _A.out, _A.langs
SPECS = _A.specs or os.path.join(os.path.dirname(os.path.abspath(RAW)), 'r47-specs')


def B(d, k):
    b = d['boxes'].get(k)
    if not b:
        raise KeyError(k)
    return [b['x'], b['y'], b['w'], b['h']]


def union(*bs):
    x0 = min(b[0] for b in bs); y0 = min(b[1] for b in bs)
    x1 = max(b[0] + b[2] for b in bs); y1 = max(b[1] + b[3] for b in bs)
    return [x0, y0, x1 - x0, y1 - y0]


def clamp(c, vw, vh, lo_x=0, hi_x=None):
    x, y, w, h = c
    hi_x = vw if hi_x is None else hi_x
    w = min(w, hi_x - lo_x); h = min(h, vh)
    x = max(lo_x, min(x, hi_x - w)); y = max(0, min(y, vh - h))
    return [round(x, 1), round(y, 1), round(w, 1), round(h, 1)]


def content_x(d):
    """x range of the admin content (without the 160 px admin menu, which flips side in RTL)."""
    vw = d['viewport']['w']
    return (0, vw - 160) if d['dir'] == 'rtl' else (160, vw)


def spec_for(lang, name, d):
    rtl = d['dir'] == 'rtl'
    vw, vh = d['viewport']['w'], d['viewport']['h']
    lo, hi = content_x(d)
    A = []; maxw = 1600; what = ''
    if name == '00-free-plus-plan':
        card, btn = B(d, 'card'), B(d, 'button')
        crop = clamp([card[0] - 14, card[1] - 12, card[2] + 28, card[3] + 12 + 46], vw, vh)
        A = [{'type': 'arrow', 'target': btn, 'from': 'bottom', 'length': 0.085}]
        maxw = 800; what = 'Plan chooser (first run): Free Plus card with the Continue with Free Plus button'
    elif name == '01-addons-stripe':
        card, btn = B(d, 'card'), B(d, 'button')
        crop = clamp([card[0] - 24, card[1] - 26, card[2] + 48, card[3] + 26 + 8], vw, vh, lo, hi)
        A = [{'type': 'arrow', 'target': btn, 'from': 'bottom', 'length': 0.075}]
        maxw = 800; what = 'Add-ons page: Stripe payment add-on card, installed (button now offers Remove)'
    elif name == '02-payments-keys':
        pk, sk, save, tab = B(d, 'pk'), B(d, 'sk'), B(d, 'save'), B(d, 'tab')
        top = tab[1] - 52
        crop = clamp([(lo + hi) / 2 - 400, top, 800, save[1] + save[3] + 85 - top], vw, vh, lo, hi)
        keys = union(pk, sk); keys = [keys[0] - 8, keys[1] - 32, keys[2] + 16, keys[3] + 32]
        A = [{'type': 'box', 'coords': keys, 'pad': 5},
             {'type': 'arrow', 'target': save, 'from': 'bottom', 'length': 0.07}]
        what = 'Settings > Payments tab: Stripe Public Key and Secret Key entered (masked: pk_test_ / sk_test_ + dots), Save button'
    elif name == '03-payment-form-template':
        card, btn = B(d, 'card'), B(d, 'button')
        crop = clamp([card[0] - 12, card[1] - 26, card[2] + 24, card[3] + 26 + 12], vw, vh, lo, hi)
        A = [{'type': 'arrow', 'target': btn, 'from': 'bottom', 'length': 0.075}]
        maxw = 800; what = 'Create page: Payment Form template card and its Create button'
    elif name == '04-builder-fields':
        cv = B(d, 'canvas')
        cb = {b['tag']: b for b in d['canvasBoxes'] if b['tag'] in ('payRadio', 'stripe')}
        st, pr = cb['stripe'], cb['payRadio']
        bottom = st['y'] + 214                      # just below the Card Number box of the Stripe block
        crop = clamp([cv[0] - 16, cv[1] + 14, cv[2] + 32, bottom - cv[1] - 14], vw, vh, lo, hi)
        A = [{'type': 'box', 'coords': [pr['x'] + 6, pr['y'] + 2, pr['w'] - 12, pr['h'] - 4], 'pad': 2}]
        maxw = 1200; what = 'Builder canvas: First/Last name, Email, Payment Single Choose (default options) and the Stripe card block'
    elif name == '05-price-options':
        panel, o1, p2, lab = B(d, 'panel'), B(d, 'opt1'), B(d, 'price2'), B(d, 'label')
        x0 = panel[0] - 4
        crop = clamp([x0, 36, panel[2] + 8, o1[1] + 150 - 36], vw, vh, lo, hi)
        opts = union(o1, p2, B(d, 'price1'), B(d, 'opt2'))
        A = [{'type': 'box', 'coords': opts, 'pad': 10}]
        maxw = 800; what = 'Field settings panel of Payment Single Choose: label, option titles and prices (25 / 40)'
    elif name == '06-shortcode':
        m, copy, code = B(d, 'modal'), B(d, 'copy'), B(d, 'code')
        crop = clamp([m[0] - 60, m[1] - 20, m[2] + 120, m[3] + 40], vw, vh)
        A = [{'type': 'arrow', 'target': copy, 'from': 'end', 'length': 0.08}]
        maxw = 1200; what = 'Save dialog: the shortcode [EMS_Form_Builder id=N] and the Copy Shortcode button'
    elif name == '07-frontend-form':
        pay, opt, form = B(d, 'pay'), B(d, 'option'), B(d, 'form')
        cx = form[0] + form[2] / 2 + (-40 if rtl else 40)
        top = B(d, 'email')[1] + B(d, 'email')[3] + 10
        crop = clamp([cx - 400, top, 800, pay[1] + pay[3] + 24 - top], vw, vh)
        A = [{'type': 'arrow', 'target': pay, 'from': 'end', 'length': 0.075}]
        what = 'Published form (visitor): ticket option chosen, test card 4242 4242 4242 4242 12/30 123 entered, pay button'
    elif name == '08-confirmation':
        code = B(d, 'code'); root = B(d, 'root')
        cx = root[0] + root[2] / 2
        crop = clamp([cx - 400, root[1] - 110, 800, min(540, root[3] - 30 + 110)], vw, vh)
        A = [{'type': 'box', 'coords': [code[0] - 4, code[1] + 6, code[2] + 8, code[3] - 10], 'pad': 3}]
        what = 'Success message after paying, with the confirmation code'
    elif name == '09-stripe-payments-completed':
        row, table = B(d, 'row'), B(d, 'table')
        user = d['cellBoxes'][5]                                          # crop ends before the User column
        if rtl:
            x1 = table[0] + table[2] + 30; x0 = max(x1 - 800, user['x'] + user['w'] + 6)
        else:
            x0 = table[0] - 30; x1 = min(x0 + 800, user['x'] - 6)        # x0 keeps the "Total: N" line whole
        crop = clamp([x0, table[1] - 48, x1 - x0, table[3] + 48 + 84], vw, vh, lo, hi)
        A = [{'type': 'box', 'coords': [crop[0] + 22, row[1] + 2, crop[2] - 44, row[3] - 4], 'pad': 4}]
        maxw = 1200
        what = 'Stripe Payments list: the test payment ($25.00, One-time) with status Completed'
    else:
        raise ValueError(name)
    cw, ch = crop[2], crop[3]
    if min(maxw, cw * 2) * ch / cw > 1100:          # style guide: height <= 1100 px -> export a little narrower
        maxw = int(1100 * cw / ch)
    spec = {'scale': 2, 'units': 'css', 'rtl': rtl, 'output': {'max_width': maxw, 'quality': 82},
            'annotations': [{'type': 'crop', 'coords': crop}] + A}
    return spec, what


def main():
    os.makedirs(SPECS, exist_ok=True)
    report = {}
    for lang in LANGS:
        od = os.path.join(OUT, lang); os.makedirs(od, exist_ok=True)
        for f in sorted(os.listdir(os.path.join(RAW, lang, 'boxes'))):
            name = f[:-5]
            d = json.load(open(os.path.join(RAW, lang, 'boxes', f)))
            spec, what = spec_for(lang, name, d)
            sp = os.path.join(SPECS, f'{lang}-{name}.json')
            json.dump(spec, open(sp, 'w'), indent=1)
            out = os.path.join(od, f'{name}.webp')
            r = subprocess.run([sys.executable, ANN, os.path.join(RAW, lang, 'raw', name + '.png'), sp, '-o', out, '--json'],
                               capture_output=True, text=True)
            try:
                rep = json.loads(r.stdout)
            except Exception:
                rep = {'raw': r.stdout[-400:], 'err': r.stderr[-400:]}
            report[f'{lang}/{name}'] = {'what': what, 'lint': rep.get('warnings', rep), 'size': rep.get('size'), 'kb': rep.get('kb')}
            print(lang, name, rep.get('size'), rep.get('kb'), rep.get('warnings', rep))
    json.dump(report, open(os.path.join(SPECS, 'report.json'), 'w'), indent=1)


if __name__ == '__main__':
    main()

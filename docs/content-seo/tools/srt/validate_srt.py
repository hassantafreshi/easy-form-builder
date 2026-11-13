#!/usr/bin/env python3
"""Validate SRT subtitle files for the Easy Form Builder video pipeline.

Standard library only (Python 3.8+). The rules are the ones written in
docs/content-seo/templates/video/subtitles-spec.md; keep the two in step.

Usage
  validate_srt.py FILE [FILE ...] [--lang en|de|ar|fa] [--profile landscape|shorts]
                  [--duration SECONDS] [--ref EN.srt] [--strict] [--json]
                  [--max-cpl N] [--max-cps N] [--quiet]
  validate_srt.py --selftest          run the bundled samples (good must pass, bad must fail)

Exit status: 0 = no errors, 1 = at least one error (or warning with --strict),
2 = usage / file problem.

Language comes from --lang or from the file name <slug>.<lang>.srt.
Counting rule: characters per line and per second are counted on *visible* text:
format characters (RLM, LRM, ZWNJ, ZWJ, BOM) are not counted; a line break inside
a cue counts as one space.
"""
import argparse
import json
import os
import re
import sys
import unicodedata

# ---------------------------------------------------------------- profiles
# max_* are hard limits (error); target_* are comfort limits (warning).
# en/de/ar: Netflix Timed Text Style Guides (42 characters per line; adult reading speed
# en 20, de 17, ar 20 characters per second), fetched 2026-11-13.
# fa: no Netflix guide exists; max_cps 17 / target 15 is an engineering assumption (UNVERIFIED).
PROFILES = {
    'en': dict(max_cpl=42, target_cpl=37, max_cps=20.0, target_cps=17.0, rtl=False),
    'de': dict(max_cpl=42, target_cpl=38, max_cps=17.0, target_cps=15.0, rtl=False),
    'ar': dict(max_cpl=42, target_cpl=36, max_cps=20.0, target_cps=17.0, rtl=True),
    'fa': dict(max_cpl=42, target_cpl=36, max_cps=17.0, target_cps=15.0, rtl=True),
}
SHORTS = dict(max_cpl=32, target_cpl=28)  # vertical 9:16 variant (UNVERIFIED how YouTube wraps captions)
MAX_LINES = 2
MIN_DUR_HARD = 0.83     # 5/6 s: Netflix general requirement
MIN_DUR_SOFT = 1.00     # below this a cue with more than 12 visible characters gets a warning
MAX_DUR_HARD = 7.00     # Netflix general requirement
MAX_DUR_SOFT = 6.00
MIN_GAP = 0.08          # two frames at 25 fps; Netflix: 2 frames minimum
REF_IN_DRIFT = 0.50     # translated in-time may differ from the reference by this much (warning)
REF_OUT_DRIFT = 1.50

RLM, LRM, ZWNJ, ZWJ, BOM = '‏', '‎', '‌', '‍', '﻿'
INVISIBLE = {RLM, LRM, ZWNJ, ZWJ, BOM}
NAME_RE = re.compile(r'^(?P<slug>[a-z0-9]+(?:-[a-z0-9]+)*)\.(?P<lang>en|de|ar|fa)\.srt$')
LEGACY_NAME_RE = re.compile(r'^captions\.(?P<lang>en|de|ar|fa)\.srt$')
TIME_RE = re.compile(r'^(\d{2}):(\d{2}):(\d{2})([,.])(\d{3})\s*-->\s*(\d{2}):(\d{2}):(\d{2})([,.])(\d{3})\s*$')
TAG_RE = re.compile(r'<[^>]*>|\{\\[^}]*\}')


class Report:
    def __init__(self, path):
        self.path = path
        self.items = []  # (level, code, cue, message)

    def add(self, level, code, cue, msg):
        self.items.append((level, code, cue, msg))

    def count(self, level):
        return sum(1 for i in self.items if i[0] == level)


def to_seconds(h, m, s, ms):
    return int(h) * 3600 + int(m) * 60 + int(s) + int(ms) / 1000.0


def visible(text):
    """Text as a viewer counts it: no format characters, newline = one space."""
    t = ''.join(ch for ch in text.replace('\n', ' ') if ch not in INVISIBLE)
    return t


def first_strong(line):
    for ch in line:
        c = unicodedata.bidirectional(ch)
        if c in ('L', 'R', 'AL'):
            return c
    return None


def parse(raw, rep):
    """Return (cues, text) where cues = [dict(index, start, end, lines, line_no)]."""
    if raw.startswith(b'\xef\xbb\xbf'):
        rep.add('ERROR', 'E_BOM', None, 'UTF-8 byte order mark at the start of the file; save as UTF-8 without BOM.')
    try:
        text = raw.decode('utf-8-sig')
    except UnicodeDecodeError as exc:
        rep.add('ERROR', 'E_ENCODING', None, 'Not valid UTF-8: %s' % exc)
        return [], ''
    if '\x00' in text:
        rep.add('ERROR', 'E_NUL', None, 'File contains NUL characters.')
    crlf, lf = text.count('\r\n'), text.count('\n') - text.count('\r\n')
    if text.count('\r') - crlf > 0:
        rep.add('ERROR', 'E_CR', None, 'Bare carriage returns (old Mac line endings) found.')
    if crlf and lf:
        rep.add('WARN', 'W_MIXED_EOL', None, 'Mixed CRLF and LF line endings (%d CRLF, %d LF).' % (crlf, lf))
    if text and not text.endswith('\n'):
        rep.add('WARN', 'W_NO_FINAL_NEWLINE', None, 'File does not end with a newline.')
    lines = text.replace('\r\n', '\n').replace('\r', '\n').split('\n')
    cues, block, start_no = [], [], 1
    for no, line in enumerate(lines + ['']):
        if line.strip() == '':
            if block:
                cues.append((start_no, block))
                block = []
            start_no = no + 2
        else:
            if not block:
                start_no = no + 1
            block.append(line)
    out = []
    for expected, (line_no, blk) in enumerate(cues, 1):
        cue_id = expected
        idx_line = blk[0].strip()
        rest = blk[1:]
        if TIME_RE.match(blk[0].strip()):
            rep.add('ERROR', 'E_NO_INDEX', cue_id, 'Cue %d (line %d) has no index line.' % (expected, line_no))
            idx, tline, textlines = None, blk[0].strip(), blk[1:]
        else:
            if not re.fullmatch(r'\d+', idx_line):
                rep.add('ERROR', 'E_INDEX', cue_id, 'Line %d: cue index must be an integer, got %r.' % (line_no, idx_line[:30]))
                idx = None
            else:
                idx = int(idx_line)
                if idx != expected:
                    rep.add('ERROR', 'E_INDEX_SEQ', cue_id, 'Cue index %d where %d was expected (indexes must be 1..N without gaps).' % (idx, expected))
            if not rest:
                rep.add('ERROR', 'E_TRUNCATED', cue_id, 'Cue %d has no timing line.' % expected)
                continue
            tline, textlines = rest[0].strip(), rest[1:]
        m = TIME_RE.match(tline)
        if not m:
            rep.add('ERROR', 'E_TIMING_FORMAT', cue_id, 'Line %d: timing must look like 00:00:01,000 --> 00:00:03,500, got %r.' % (line_no + 1, tline[:50]))
            continue
        g = m.groups()
        if g[3] == '.' or g[8] == '.':
            rep.add('WARN', 'W_TIMING_DOT', cue_id, 'Milliseconds separated by "." instead of ",".')
        start = to_seconds(g[0], g[1], g[2], g[4])
        end = to_seconds(g[5], g[6], g[7], g[9])
        out.append(dict(index=cue_id, start=start, end=end, lines=textlines, line_no=line_no))
    return out, text


def check_cue(cue, prof, lang, rep, shorts):
    i = cue['index']
    lines = cue['lines']
    dur = cue['end'] - cue['start']
    if cue['start'] < 0:
        rep.add('ERROR', 'E_NEGATIVE', i, 'Negative start time.')
    if dur <= 0:
        rep.add('ERROR', 'E_DURATION_NONPOSITIVE', i, 'End time (%.3f) is not after start time (%.3f).' % (cue['end'], cue['start']))
        return
    if not any(l.strip() for l in lines):
        rep.add('ERROR', 'E_EMPTY_CUE', i, 'Cue has no text.')
        return
    if len(lines) > MAX_LINES:
        rep.add('ERROR', 'E_LINES', i, 'Cue has %d lines; the maximum is %d.' % (len(lines), MAX_LINES))
    max_cpl = prof['max_cpl']
    tgt_cpl = prof['target_cpl']
    if shorts:
        max_cpl, tgt_cpl = SHORTS['max_cpl'], SHORTS['target_cpl']
    if prof.get('override_cpl'):
        max_cpl = prof['override_cpl']
    max_cps = prof.get('override_cps') or prof['max_cps']
    for ln, line in enumerate(lines, 1):
        v = visible(line)
        if v != v.strip():
            rep.add('WARN', 'W_EDGE_SPACE', i, 'Line %d has leading or trailing space.' % ln)
        if '  ' in v:
            rep.add('WARN', 'W_DOUBLE_SPACE', i, 'Line %d contains a double space.' % ln)
        n = len(v.strip())
        if n > max_cpl:
            rep.add('ERROR', 'E_CPL', i, 'Line %d has %d characters; the maximum is %d.' % (ln, n, max_cpl))
        elif n > tgt_cpl:
            rep.add('WARN', 'W_CPL', i, 'Line %d has %d characters; the target is %d or fewer.' % (ln, n, tgt_cpl))
        if TAG_RE.search(line):
            rep.add('ERROR', 'E_MARKUP', i, 'Line %d contains markup (%s); YouTube ignores SRT markup and shows it as text.' % (ln, TAG_RE.search(line).group(0)))
        if '\t' in line:
            rep.add('ERROR', 'E_TAB', i, 'Line %d contains a tab character.' % ln)
        if BOM in line:
            rep.add('ERROR', 'E_BOM_IN_TEXT', i, 'Line %d contains U+FEFF.' % ln)
        if ' ' in line:
            rep.add('WARN', 'W_NBSP', i, 'Line %d contains a non-breaking space; use a normal space.' % ln)
        if '...' in line:
            rep.add('WARN', 'W_ELLIPSIS', i, 'Line %d uses "..."; use the single character U+2026.' % ln)
        bad_bidi = [c for c in line if c in '‪‫‬‭‮⁦⁧⁨⁩']
        if bad_bidi:
            rep.add('WARN', 'W_BIDI_EMBED', i, 'Line %d uses bidi embedding/isolate controls (U+%04X); use only RLM/LRM.' % (ln, ord(bad_bidi[0])))
        lang_line_checks(line, ln, i, lang, prof, rep)
    v_all = visible('\n'.join(l.strip() for l in lines)).strip()
    chars = len(v_all)
    cps = chars / dur
    if cps > max_cps:
        rep.add('ERROR', 'E_CPS', i, 'Reading speed %.1f characters/second (%d characters in %.2f s); the maximum is %.0f.' % (cps, chars, dur, max_cps))
    elif cps > prof['target_cps']:
        rep.add('WARN', 'W_CPS', i, 'Reading speed %.1f characters/second; the target is %.0f or less.' % (cps, prof['target_cps']))
    if dur < MIN_DUR_HARD:
        rep.add('ERROR', 'E_MIN_DURATION', i, 'Duration %.2f s is below the minimum %.2f s.' % (dur, MIN_DUR_HARD))
    elif dur < MIN_DUR_SOFT and chars > 12:
        rep.add('WARN', 'W_SHORT_DURATION', i, 'Duration %.2f s for %d characters; aim for at least %.1f s.' % (dur, chars, MIN_DUR_SOFT))
    if dur > MAX_DUR_HARD:
        rep.add('ERROR', 'E_MAX_DURATION', i, 'Duration %.2f s is above the maximum %.1f s.' % (dur, MAX_DUR_HARD))
    elif dur > MAX_DUR_SOFT:
        rep.add('WARN', 'W_LONG_DURATION', i, 'Duration %.2f s; consider splitting (comfort limit %.1f s).' % (dur, MAX_DUR_SOFT))


def lang_line_checks(line, ln, i, lang, prof, rep):
    if not line.strip():
        rep.add('ERROR', 'E_BLANK_LINE_IN_CUE', i, 'Line %d is blank.' % ln)
        return
    if prof['rtl']:
        core = line.lstrip(' ')
        strong = first_strong(line)
        has_latin_or_digit = bool(re.search(r'[A-Za-z0-9]', line))
        starts_rlm = core.startswith(RLM)
        if strong == 'L' and not starts_rlm:
            rep.add('ERROR', 'E_RTL_STARTS_LTR', i, 'Line %d starts with left-to-right text; prefix the line with U+200F (RLM) or it renders left-to-right.' % ln)
        elif has_latin_or_digit and not starts_rlm:
            rep.add('WARN', 'W_RTL_NO_RLM', i, 'Line %d mixes Latin text or digits but has no leading RLM (U+200F); add it for stable ordering.' % ln)
        if re.search(r'[؀-ۿ][ ]*[?,;]', line) or re.search(r'[?,;][ ]*[؀-ۿ]', line):
            rep.add('WARN', 'W_LATIN_PUNCT', i, 'Line %d uses Latin ? , ; next to Arabic-script text; use ؟ ، ؛.' % ln)
        if lang == 'fa':
            if re.search('[يك]', line):
                rep.add('ERROR', 'E_FA_ARABIC_LETTERS', i, 'Line %d uses Arabic yeh/kaf (U+064A/U+0643); use Persian ی and ک.' % ln)
            if re.search('[٠-٩]', line):
                rep.add('ERROR', 'E_FA_ARABIC_DIGITS', i, 'Line %d uses Arabic-Indic digits (U+0660-0669); use Persian digits ۰-۹ or Latin 0-9.' % ln)
            if re.search(r'(?<![؀-ۿ‌])(?:ن?می)[ ](?=[؀-ۿ])', line.replace(RLM, '')):
                rep.add('WARN', 'W_FA_ZWNJ_MI', i, 'Line %d: missing ZWNJ after می/نمی (write می‌کنید).' % ln)
            if re.search(r'[؀-ۿ] ها(?![؀-ۿ])', line):
                rep.add('WARN', 'W_FA_DETACHED_HA', i, 'Line %d: plural «ها» detached by a space; use ZWNJ.' % ln)
            if 'فرم ساز آسان' in line or 'فرمساز آسان' in line:
                rep.add('WARN', 'W_FA_BRAND', i, 'Line %d: brand must be فرم‌ساز آسان (with ZWNJ).' % ln)
        if lang == 'ar':
            if re.search('[٠-٩]', line):
                rep.add('WARN', 'W_AR_EASTERN_DIGITS', i, 'Line %d uses Eastern Arabic-Indic digits; this project uses Western digits 0-9 in Arabic subtitles.' % ln)
            if re.search('[پچژگکی]', line):
                rep.add('WARN', 'W_AR_PERSIAN_LETTERS', i, 'Line %d contains Persian-only letters (پ چ ژ گ ک ی); wrong script variant for Arabic?' % ln)
    else:
        if RLM in line:
            rep.add('WARN', 'W_RLM_IN_LTR', i, 'Line %d contains RLM in a left-to-right language.' % ln)
        if re.search('[؀-ۿ]', line):
            rep.add('WARN', 'W_UNEXPECTED_SCRIPT', i, 'Line %d contains Arabic-script characters in a %s track.' % (ln, lang))


def check_file(path, lang, args):
    rep = Report(path)
    base = os.path.basename(path)
    m = NAME_RE.match(base)
    legacy = LEGACY_NAME_RE.match(base)
    if m:
        name_lang = m.group('lang')
    elif legacy:
        name_lang = legacy.group('lang')
        rep.add('WARN', 'W_LEGACY_NAME', None, 'File name %r is legacy; use <slug>.<lang>.srt.' % base)
    else:
        name_lang = None
        rep.add('ERROR', 'E_FILENAME', None, 'File name must be <slug>.<lang>.srt (lower-case slug, lang en|de|ar|fa); got %r.' % base)
    if lang and name_lang and lang != name_lang:
        rep.add('ERROR', 'E_LANG_MISMATCH', None, '--lang %s does not match the language code in the file name (%s).' % (lang, name_lang))
    use_lang = lang or name_lang
    if use_lang not in PROFILES:
        rep.add('ERROR', 'E_LANG_UNKNOWN', None, 'Language unknown: pass --lang en|de|ar|fa or use the <slug>.<lang>.srt file name.')
        return rep, [], None
    prof = dict(PROFILES[use_lang])
    if args.max_cpl:
        prof['override_cpl'] = args.max_cpl
    if args.max_cps:
        prof['override_cps'] = args.max_cps
    try:
        with open(path, 'rb') as fh:
            raw = fh.read()
    except OSError as exc:
        rep.add('ERROR', 'E_IO', None, 'Cannot read file: %s' % exc)
        return rep, [], use_lang
    if not raw.strip():
        rep.add('ERROR', 'E_EMPTY_FILE', None, 'File is empty.')
        return rep, [], use_lang
    cues, _ = parse(raw, rep)
    if not cues and rep.count('ERROR') == 0:
        rep.add('ERROR', 'E_NO_CUES', None, 'No cues found.')
    prev = None
    for cue in cues:
        check_cue(cue, prof, use_lang, rep, args.profile == 'shorts')
        if prev is not None:
            if cue['start'] < prev['start']:
                rep.add('ERROR', 'E_ORDER', cue['index'], 'Cue starts (%.3f) before the previous cue starts (%.3f).' % (cue['start'], prev['start']))
            elif cue['start'] < prev['end']:
                rep.add('ERROR', 'E_OVERLAP', cue['index'], 'Overlaps the previous cue by %.3f s.' % (prev['end'] - cue['start']))
            elif cue['start'] - prev['end'] < MIN_GAP - 1e-9:
                rep.add('WARN', 'W_GAP', cue['index'], 'Gap to the previous cue is %.0f ms; keep at least %.0f ms.' % ((cue['start'] - prev['end']) * 1000, MIN_GAP * 1000))
        prev = cue
    if cues and args.duration is not None:
        last = cues[-1]['end']
        if last > args.duration + 0.05:
            rep.add('ERROR', 'E_PAST_END', cues[-1]['index'], 'Last cue ends at %.3f s, after the video duration %.3f s.' % (last, args.duration))
        if cues[0]['start'] > 6.0:
            rep.add('WARN', 'W_LATE_START', cues[0]['index'], 'First cue starts at %.1f s; the hook should be captioned in the first 6 s.' % cues[0]['start'])
    return rep, cues, use_lang


def compare_ref(rep, cues, ref_cues, allow_diff):
    if len(cues) != len(ref_cues):
        rep.add('WARN' if allow_diff else 'ERROR', 'E_REF_COUNT', None,
                'Cue count %d differs from the reference track (%d); translated tracks keep the same cues.' % (len(cues), len(ref_cues)))
    for c, r in zip(cues, ref_cues):
        if abs(c['start'] - r['start']) > REF_IN_DRIFT:
            rep.add('WARN', 'W_REF_IN_DRIFT', c['index'], 'Start differs from the reference by %.2f s (limit %.2f s).' % (c['start'] - r['start'], REF_IN_DRIFT))
        if abs(c['end'] - r['end']) > REF_OUT_DRIFT:
            rep.add('WARN', 'W_REF_OUT_DRIFT', c['index'], 'End differs from the reference by %.2f s (limit %.2f s).' % (c['end'] - r['end'], REF_OUT_DRIFT))


def print_report(rep, quiet):
    for level, code, cue, msg in rep.items:
        if quiet and level != 'ERROR':
            continue
        where = 'cue %d' % cue if cue else 'file'
        print('%s: %s: %s [%s] %s' % (rep.path, where, level, code, msg))


def run(args):
    ref_cues = None
    if args.ref:
        rrep, ref_cues, _ = check_file(args.ref, 'en', argparse.Namespace(max_cpl=None, max_cps=None, profile='landscape', duration=None))
        if not ref_cues:
            print('Reference %s could not be parsed.' % args.ref, file=sys.stderr)
            return 2, []
    reports = []
    for path in args.files:
        rep, cues, use_lang = check_file(path, args.lang, args)
        if ref_cues is not None and cues and use_lang != 'en':
            compare_ref(rep, cues, ref_cues, args.allow_count_diff)
        reports.append(rep)
    failed = False
    for rep in reports:
        e, w = rep.count('ERROR'), rep.count('WARN')
        if not args.json:
            print_report(rep, args.quiet)
            print('%s: %s (%d errors, %d warnings)' % (rep.path, 'FAIL' if (e or (args.strict and w)) else 'OK', e, w))
        if e or (args.strict and w):
            failed = True
    if args.json:
        print(json.dumps([{'file': r.path, 'errors': r.count('ERROR'), 'warnings': r.count('WARN'),
                           'issues': [dict(level=l, code=c, cue=q, message=m) for l, c, q, m in r.items]} for r in reports],
                         ensure_ascii=False, indent=2))
    return (1 if failed else 0), reports


def selftest():
    here = os.path.dirname(os.path.abspath(__file__))
    root = os.path.join(here, 'samples')
    good_dir, bad_dir = os.path.join(root, 'good'), os.path.join(root, 'bad')
    ok = True
    ns = argparse.Namespace(max_cpl=None, max_cps=None, profile='landscape', duration=None)
    ref = None
    for fn in sorted(os.listdir(good_dir)):
        if not fn.endswith('.srt'):
            continue
        rep, cues, lang = check_file(os.path.join(good_dir, fn), None, ns)
        if lang == 'en':
            ref = cues
        e = rep.count('ERROR')
        if ref is not None and lang != 'en' and cues:
            compare_ref(rep, cues, ref, False)
            e = rep.count('ERROR')
        print('good  %-40s errors=%d warnings=%d  %s' % (fn, e, rep.count('WARN'), 'ok' if e == 0 else 'UNEXPECTED FAILURE'))
        if e:
            ok = False
            print_report(rep, True)
    exp_path = os.path.join(bad_dir, 'expected.txt')
    expected = {}
    with open(exp_path, encoding='utf-8') as fh:
        for line in fh:
            line = line.strip()
            if line and not line.startswith('#'):
                name, codes = line.split(':', 1)
                expected[name.strip()] = {c.strip() for c in codes.split(',') if c.strip()}
    for name, codes in sorted(expected.items()):
        rep, cues, lang = check_file(os.path.join(bad_dir, name), None, ns)
        found = {c for _, c, _, _ in rep.items}
        missing = codes - found
        print('bad   %-40s found=%d expected=%d  %s' % (name, len(found & codes), len(codes), 'ok' if not missing else 'MISSING ' + ','.join(sorted(missing))))
        if missing:
            ok = False
    print('SELFTEST', 'PASSED' if ok else 'FAILED')
    return 0 if ok else 1


def main(argv=None):
    ap = argparse.ArgumentParser(description='Validate SRT files (see docs/content-seo/templates/video/subtitles-spec.md).')
    ap.add_argument('files', nargs='*')
    ap.add_argument('--lang', choices=sorted(PROFILES))
    ap.add_argument('--profile', choices=['landscape', 'shorts'], default='landscape')
    ap.add_argument('--duration', type=float, help='video duration in seconds; the last cue must end before it')
    ap.add_argument('--ref', help='English reference SRT: translated tracks must keep its cue count and timing')
    ap.add_argument('--allow-count-diff', action='store_true', help='downgrade a cue-count mismatch with --ref to a warning')
    ap.add_argument('--max-cpl', type=int, help='override the maximum characters per line')
    ap.add_argument('--max-cps', type=float, help='override the maximum characters per second')
    ap.add_argument('--strict', action='store_true', help='treat warnings as errors')
    ap.add_argument('--json', action='store_true')
    ap.add_argument('--quiet', action='store_true', help='print errors only')
    ap.add_argument('--selftest', action='store_true')
    args = ap.parse_args(argv)
    if args.selftest:
        return selftest()
    if not args.files:
        ap.print_usage(sys.stderr)
        return 2
    code, _ = run(args)
    return code


if __name__ == '__main__':
    sys.exit(main())

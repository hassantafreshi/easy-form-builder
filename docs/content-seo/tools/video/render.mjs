#!/usr/bin/env node
/**
 * render.mjs - render a storyboard JSON with Remotion's server-side renderer.
 *
 *   node render.mjs --storyboard storyboards/poc.json --out DIR [--formats 16x9,9x16] [--browser PATH]
 *                   [--concurrency 4] [--crf 18] [--frames 0-89] [--name poc]
 *
 * - asset paths in the storyboard are relative to the storyboard file; they are copied into a staging
 *   public/ folder (Remotion serves it) and the storyboard handed to the composition is rewritten accordingly.
 * - uses a locally installed Chromium (--browser / $EFB_BROWSER / Playwright's) - never downloads one.
 * - writes <name>-<format>.mp4 (silent H.264) and render-report.json (wall-clock, peak RSS of the whole process tree).
 */
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const args = Object.fromEntries(process.argv.slice(2).reduce((a, v, i, all) => (v.startsWith('--') ? [...a, [v.slice(2), all[i + 1] && !all[i + 1].startsWith('--') ? all[i + 1] : true]] : a), []));
const WORK = process.env.EFB_WORK || path.join(process.env.TMPDIR || os.tmpdir(), 'efb-tutorial');
const sbPath = path.resolve(String(args.storyboard || path.join(HERE, 'storyboards/poc.json')));
const outDir = path.resolve(String(args.out || path.join(WORK, 'render')));
const formats = String(args.formats || '16x9,9x16').split(',');
const name = String(args.name || path.basename(sbPath, '.json'));
fs.mkdirSync(outDir, { recursive: true });

const { bundle } = await import('@remotion/bundler');
const { renderMedia, renderStill, selectComposition } = await import('@remotion/renderer');

function findBrowser() {
  const cands = [args.browser, process.env.EFB_BROWSER, process.env.PUPPETEER_EXECUTABLE_PATH];
  const pw = '/opt/pw-browsers';
  if (fs.existsSync(pw)) for (const d of fs.readdirSync(pw).sort().reverse()) if (/^chromium-\d+$/.test(d)) cands.push(path.join(pw, d, 'chrome-linux64', 'chrome'));
  cands.push('/usr/bin/google-chrome', '/usr/bin/chromium');
  const hit = cands.find((c) => typeof c === 'string' && fs.existsSync(c));
  if (!hit) throw new Error('no Chromium found; pass --browser /path/to/chrome');
  return hit;
}
const browserExecutable = findBrowser();

// ---- stage assets ------------------------------------------------------------------------------------------
const sb = JSON.parse(fs.readFileSync(sbPath, 'utf8'));
const sbDir = path.dirname(sbPath);
const stage = fs.mkdtempSync(path.join(WORK, 'public-'));
const copy = (rel, tag) => {
  if (!rel) return rel;
  rel = rel.replace(/\$\{EFB_WORK\}/g, WORK);
  const src = path.isAbsolute(rel) ? rel : path.resolve(sbDir, rel);
  if (!fs.existsSync(src)) throw new Error(`asset not found: ${src}`);
  const dest = `${tag}${path.extname(src)}`;
  fs.mkdirSync(path.join(stage, path.dirname(dest)), { recursive: true });
  fs.copyFileSync(src, path.join(stage, dest));
  return dest;
};
for (const [k, a] of Object.entries(sb.assets || {})) {
  for (const f of ['video', 'image', 'events', 'markers']) if (a[f]) a[f] = copy(a[f], `assets/${k}.${f}`);
}
if (sb.captions?.srt) sb.captions.srt = copy(sb.captions.srt, 'captions');

// ---- peak RSS of the whole process tree --------------------------------------------------------------------
let peakKb = 0, baseline = 0;
function treeRssKb() {
  try {
    const rows = execFileSync('ps', ['-eo', 'pid=,ppid=,rss='], { encoding: 'utf8' }).trim().split('\n').map((l) => l.trim().split(/\s+/).map(Number));
    const kids = new Map(); for (const [pid, ppid, rss] of rows) { kids.set(pid, { ppid, rss }); }
    let sum = 0;
    for (const [pid, { rss }] of kids) { let p = pid, ok = false; for (let i = 0; i < 12 && p; i++) { if (p === process.pid) { ok = true; break; } p = kids.get(p)?.ppid; } if (ok) sum += rss; }
    return sum;
  } catch { return 0; }
}
const sampler = setInterval(() => { peakKb = Math.max(peakKb, treeRssKb()); }, 500);

const report = { storyboard: sbPath, browser: browserExecutable, formats: {}, started: new Date().toISOString() };
const tB = Date.now();
const serveUrl = await bundle({ entryPoint: path.join(HERE, 'src/index.ts'), publicDir: stage, onProgress: () => {} });
report.bundle_s = +((Date.now() - tB) / 1000).toFixed(1);
console.log(`[bundle] ${report.bundle_s}s`);

const chromiumOptions = { headless: true, gl: String(args.gl || 'angle'), disableWebSecurity: false };
for (const f of formats) {
  const id = `Tutorial-${f}`;
  const out = path.join(outDir, `${name}-${f}.mp4`);
  const t0 = Date.now(); peakKb = 0; baseline = treeRssKb();
  const inputProps = { storyboard: sb };
  const composition = await selectComposition({ serveUrl, id, inputProps, browserExecutable, chromiumOptions });
  const tSel = Date.now();
  if (args.still !== undefined) { // quick visual check: --still 45,120,300 -> PNGs
    for (const fr of String(args.still).split(',').map(Number)) {
      const png = path.join(outDir, `${name}-${f}-frame${fr}.png`);
      await renderStill({ composition, serveUrl, inputProps, frame: fr, output: png, browserExecutable, chromiumOptions });
      console.log(`  [still] ${png}`);
    }
    continue;
  }
  let frameRange = null;
  if (args.frames) { const [a, b] = String(args.frames).split('-').map(Number); frameRange = [a, b]; }
  let lastPct = -1;
  await renderMedia({
    composition, serveUrl, inputProps, codec: 'h264', outputLocation: out, pixelFormat: 'yuv420p', colorSpace: 'bt709', crf: Number(args.crf || 18),
    browserExecutable, chromiumOptions, concurrency: Number(args.concurrency || os.cpus().length), frameRange, muted: true,
    onProgress: ({ progress }) => { const p = Math.floor(progress * 10) * 10; if (p !== lastPct) { lastPct = p; console.log(`  [${f}] ${p}%`); } },
  });
  const secs = (Date.now() - t0) / 1000;
  const frames = frameRange ? frameRange[1] - frameRange[0] + 1 : composition.durationInFrames;
  report.formats[f] = { gl: chromiumOptions.gl, concurrency: Number(args.concurrency || os.cpus().length), file: out, width: composition.width, height: composition.height, fps: composition.fps, frames, video_seconds: +(frames / composition.fps).toFixed(2), select_s: +((tSel - t0) / 1000).toFixed(1), render_s: +secs.toFixed(1), x_realtime: +(secs / (frames / composition.fps)).toFixed(2), peak_rss_mb: Math.round(peakKb / 1024), bytes: fs.statSync(out).size };
  console.log(`[${f}] ${frames} frames -> ${out} in ${secs.toFixed(1)}s, peak tree RSS ${Math.round(peakKb / 1024)} MB`);
}
clearInterval(sampler);
report.total_s = +((Date.now() - tB) / 1000).toFixed(1);
report.node_version = process.version;
fs.writeFileSync(path.join(outDir, `${name}-render-report.json`), JSON.stringify(report, null, 1));
fs.rmSync(stage, { recursive: true, force: true });
console.log(`[done] total ${report.total_s}s`);

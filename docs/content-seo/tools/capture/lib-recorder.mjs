/**
 * lib-recorder.mjs - sharp screen recorder for tutorial flows (replaces Playwright's recordVideo for zoomed shots).
 *
 * Why: Playwright's recordVideo ignores deviceScaleFactor and writes low-bitrate VP8 at CSS-pixel size, so UI text
 * turns soft as soon as the Remotion camera zooms past ~1.5x. This recorder grabs device-pixel JPEG frames through CDP
 * (Page.captureScreenshot with clip.scale = 2 -> 3200x1800 for a 1600x900 viewport), stamps them on its own clock and
 * encodes a high-quality H.264 (CRF 16, keyframe every second) with ffmpeg. Measured: ~7-9 frames/s at 3200x1800.
 * The cursor is NOT in the frames: Remotion draws it (smoothly, at 30 fps) from events.json, which is written from the
 * Node side here, so mouse moves over cross-origin iframes (Stripe Elements) are recorded too.
 *
 *   const rec = await createRecorder(ctx, page, { dir, width: 1600, height: 900 });
 *   rec.chapter('id', 'label'); await rec.rect('name', locator); await rec.click(locator); await rec.type(locator, 'text');
 *   rec.pause(); ...long wait (page load, vendor server)...; await rec.resume();   // jump cut in the source video
 *   await rec.finish();   // -> flow.mp4, events.json, markers.json (same schema as capture.mjs)
 *
 * `pause()` stops the clock: nothing that happens while paused is in the video, the events or the markers.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { sleep } from '../lib/common.mjs';

export async function createRecorder(ctx, page, { dir, width, height, scale = 2, quality = 88, fps = 30, crf = 16 }) {
  const framesDir = path.join(dir, 'frames');
  fs.rmSync(dir, { recursive: true, force: true });
  fs.mkdirSync(framesDir, { recursive: true });
  let cdp = await ctx.newCDPSession(page);
  // A capture requested while the page navigates can stay unanswered forever; give up after a while and use a fresh
  // CDP session (the old request is abandoned).
  const send = async (method, params, ms = 4000) => {
    let timer;
    try {
      return await Promise.race([cdp.send(method, params), new Promise((_, rej) => { timer = setTimeout(() => rej(new Error('cdp timeout')), ms); })]);
    } catch (e) {
      if (String(e.message).includes('cdp timeout')) { timeouts++; const old = cdp; old.detach().catch(() => {}); cdp = await ctx.newCDPSession(page); }
      throw e;
    } finally { clearTimeout(timer); }
  };
  let timeouts = 0;
  const t0 = Date.now();
  let paused = false, pausedTotal = 0, pauseStart = 0, running = true;
  const now = () => (Date.now() - t0 - pausedTotal - (paused ? Date.now() - pauseStart : 0)) / 1000;
  const frames = []; const pending = [];
  const events = [];
  const markers = { chapters: [], rects: {}, viewport: { width, height }, fps, source: { scale, encoder: 'cdp-jpeg+x264' } };
  let cursor = { x: width * 0.55, y: height * 0.5 };
  let grabs = 0, grabErrors = 0;

  // captureScreenshot's clip is in DOCUMENT coordinates: follow the scroll position or a scrolled page is captured
  // from its top (blank/stale areas).
  const viewClip = async () => {
    const m = await send('Page.getLayoutMetrics', {});
    const v = m.cssVisualViewport || m.visualViewport || { pageX: 0, pageY: 0 };
    return { x: v.pageX, y: v.pageY, width, height, scale };
  };
  async function grab() {
    const ta = now();
    const r = await send('Page.captureScreenshot', { format: 'jpeg', quality, optimizeForSpeed: true, clip: await viewClip() });
    const tb = now();
    if (paused) return;                           // paused while the screenshot was in flight: drop it
    const file = path.join(framesDir, `${String(frames.length).padStart(6, '0')}.jpg`);
    frames.push({ file, t: (ta + tb) / 2 });
    pending.push(fs.promises.writeFile(file, Buffer.from(r.data, 'base64')));
    grabs++;
  }
  const loop = (async () => {
    while (running) {
      if (paused) { await sleep(25); continue; }
      try { await grab(); } catch { grabErrors++; await sleep(40); } // "Unable to capture screenshot" during navigations
    }
  })();

  const rec = {
    now,
    page,
    get cursor() { return cursor; },
    chapter(id, label = id) { markers.chapters.push({ id, label, t: +now().toFixed(2) }); console.log(`  [chapter] ${id} @${now().toFixed(1)}s`); },
    async rect(name, loc) {
      try { if (loc.count && !(await loc.count())) throw new Error("missing"); const b = await loc.boundingBox({ timeout: 2500 }); if (b) markers.rects[name] = { x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height), t: +now().toFixed(2) }; }
      catch { console.log(`  [rect] ${name}: not found`); }
    },
    /** 2x PNG still of the current screen, taken through the recorder's CDP session (page.screenshot() would
     *  re-apply device metrics and break the frames being recorded). */
    async still(file) {
      const r = await send('Page.captureScreenshot', { format: 'png', clip: await viewClip() }, 15000);
      fs.writeFileSync(file, Buffer.from(r.data, 'base64'));
    },
    pause() { if (!paused) { paused = true; pauseStart = Date.now(); } },
    async resume(settleMs = 0) {
      if (!paused) return;
      pausedTotal += Date.now() - pauseStart; paused = false;
      try { await grab(); } catch { /* next loop iteration retries */ }  // first frame right at the cut
      if (settleMs) await sleep(settleMs);
    },
    /** Move the real mouse along an eased path and log every step (works over cross-origin iframes). */
    async moveTo(x, y, { steps = 26, stepMs = 16 } = {}) {
      const a = { ...cursor };
      for (let i = 1; i <= steps; i++) {
        const p = i / steps, e = p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
        const nx = a.x + (x - a.x) * e, ny = a.y + (y - a.y) * e;
        await page.mouse.move(nx, ny);
        if (!paused) events.push({ type: 'move', x: Math.round(nx), y: Math.round(ny), t: +now().toFixed(3) });
        await sleep(stepMs);
      }
      cursor = { x, y };
    },
    async glide(loc, { pause = 380, steps = 26, dx = 0.5, dy = 0.5 } = {}) {
      await loc.scrollIntoViewIfNeeded();
      const b = await loc.boundingBox();
      if (!b) throw new Error('glide: element has no box');
      await rec.moveTo(b.x + b.width * dx, b.y + b.height * dy, { steps });
      await sleep(pause);
      return b;
    },
    async clickHere() {
      if (!paused) events.push({ type: 'down', x: Math.round(cursor.x), y: Math.round(cursor.y), t: +now().toFixed(3) });
      await page.mouse.down(); await sleep(70); await page.mouse.up();
    },
    async click(loc, opts) { const b = await rec.glide(loc, opts); await rec.clickHere(); return b; },
    /** Click into a field, select what is there and type the text visibly. */
    async type(loc, text, { delay = 45, replace = false } = {}) {
      await rec.click(loc, { pause: 160, steps: 20 });
      if (replace) { await page.keyboard.press('Control+A'); await sleep(60); }
      await page.keyboard.type(text, { delay });
      await sleep(200);
    },
    async finish() {
      running = false; await loop; await Promise.all(pending);
      const end = now();
      markers.duration = +end.toFixed(2);
      if (!frames.length) throw new Error('recorder: no frames captured');
      // concat list: every frame lasts until the next one; the first starts at 0 (clock zero)
      const lines = [];
      frames.forEach((f, i) => {
        const start = i === 0 ? 0 : f.t;
        const next = i + 1 < frames.length ? frames[i + 1].t : end;
        lines.push(`file '${f.file}'`, `duration ${Math.max(0.001, next - start).toFixed(4)}`);
      });
      lines.push(`file '${frames.at(-1).file}'`);
      const list = path.join(dir, 'frames.txt');
      fs.writeFileSync(list, lines.join('\n') + '\n');
      const out = path.join(dir, 'flow.mp4');
      const r = spawnSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', list,
        '-vf', `fps=${fps},format=yuv420p`, '-c:v', 'libx264', '-preset', 'medium', '-crf', String(crf), '-g', String(fps),
        '-color_primaries', 'bt709', '-color_trc', 'bt709', '-colorspace', 'bt709', '-movflags', '+faststart', out], { stdio: 'inherit' });
      if (r.status !== 0) throw new Error('ffmpeg encode of the recording failed');
      fs.writeFileSync(path.join(dir, 'events.json'), JSON.stringify(events));
      fs.writeFileSync(path.join(dir, 'markers.json'), JSON.stringify(markers, null, 1));
      fs.rmSync(framesDir, { recursive: true, force: true });
      fs.rmSync(list, { force: true });
      const stats = { seconds: markers.duration, frames: grabs, fps_avg: +(grabs / Math.max(1, end)).toFixed(1), grab_errors: grabErrors, grab_timeouts: timeouts, events: events.length, chapters: markers.chapters.length, file: out };
      console.log(`[recorder] ${stats.seconds}s, ${stats.frames} frames (${stats.fps_avg}/s at ${width * scale}x${height * scale}) -> ${out}`);
      return stats;
    },
  };
  return rec;
}

/**
 * Keep secret-looking settings fields unreadable in every frame: the input's own text is made transparent and an
 * overlay shows only the key prefix ("pk_test_" / "sk_test_") followed by dots. Install with
 * `ctx.addInitScript(maskFieldsInitScript, ids)`; then call `assertMasked(page, ids)` before typing a real value.
 */
export function maskFieldsInitScript(ids) {
  const mask = (v) => { if (!v) return ''; const m = String(v).match(/^(pk|sk|rk)_(test|live)_/); const pre = m ? m[0] : ''; return pre + '•'.repeat(Math.min(18, Math.max(4, String(v).length - pre.length))); };
  const apply = () => {
    for (const id of ids) {
      const el = document.getElementById(id);
      if (!el) continue;
      for (const [k, v] of [['color', 'transparent'], ['-webkit-text-fill-color', 'transparent'], ['text-shadow', 'none'], ['caret-color', '#3640d4']]) el.style.setProperty(k, v, 'important');
      let ov = el.__efbMask;
      if (!ov || !ov.isConnected) {
        ov = document.createElement('div');
        ov.setAttribute('data-efb-mask', id);
        ov.style.cssText = 'position:fixed;pointer-events:none;z-index:2147483646;display:flex;align-items:center;overflow:hidden;white-space:nowrap;';
        document.documentElement.appendChild(ov); el.__efbMask = ov;
      }
      const r = el.getBoundingClientRect(); const cs = getComputedStyle(el);
      const visible = r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && el.offsetParent !== null;
      ov.style.display = visible ? 'flex' : 'none';
      if (!visible) continue;
      const padL = parseFloat(cs.paddingLeft) + parseFloat(cs.borderLeftWidth);
      Object.assign(ov.style, { left: `${r.left + padL}px`, top: `${r.top}px`, height: `${r.height}px`, width: `${Math.max(0, r.width - padL * 2)}px`, font: cs.font, letterSpacing: '0.5px' });
      if (el.value) { ov.textContent = '\u202A' + mask(el.value) + '\u202C'; /* LTR embedding: keeps "pk_test_" before the dots on RTL admin pages */ ov.style.color = '#3b3f5c'; } else { ov.textContent = el.placeholder || ''; ov.style.color = '#a3a7bd'; }
    }
    requestAnimationFrame(apply);
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => requestAnimationFrame(apply)); else requestAnimationFrame(apply);
}

/** Throws unless every field is present, transparent and covered by its overlay. */
export async function assertMasked(page, ids) {
  const st = await page.evaluate((ids) => ids.map((id) => {
    const el = document.getElementById(id); if (!el) return `${id}: missing`;
    const cs = getComputedStyle(el);
    const ok = cs.webkitTextFillColor === 'rgba(0, 0, 0, 0)' && cs.color === 'rgba(0, 0, 0, 0)' && el.__efbMask && el.__efbMask.isConnected;
    return ok ? '' : `${id}: not masked (fill=${cs.webkitTextFillColor}, color=${cs.color})`;
  }).filter(Boolean), ids);
  if (st.length) throw new Error('secret mask check failed: ' + st.join('; '));
}

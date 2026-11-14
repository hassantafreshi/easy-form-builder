#!/usr/bin/env node
/**
 * fit-storyboard.mjs - make the picture follow the narration.
 *
 *   node fit-storyboard.mjs --storyboard poc.json --timing timing.json --out fitted.json --srt captions.en.srt [--lead 2]
 *
 * - every scene that has a `narration` gets duration = its narration window (see align.mjs); recordings get the
 *   playback `speed` that makes their from..to range fit; camera / callout / lower-third times are rescaled by the
 *   same ratio so the choreography keeps its shape.
 * - camera / callout / lower-third `at` may instead name a capture chapter ("install-click", "install-click+0.4",
 *   "pay-click-0.5"): it is placed where that chapter falls in the fitted scene (offset in output seconds), so zooms
 *   and callouts stay on the real click whatever playback speed the narration forces.
 * - the voice-over starts `lead` seconds into the video (the intro); the SRT is written on that same clock.
 * - `captions.srt` of the fitted storyboard points at the SRT so Remotion can burn captions in.
 */
import fs from 'node:fs';
import path from 'node:path';
import { parseArgs, EFB_WORK } from '../lib/common.mjs';
import { toSrt } from './lib-sentences.mjs';

const a = parseArgs(process.argv.slice(2), { lead: '2' });
const sb = JSON.parse(fs.readFileSync(a.storyboard, 'utf8'));
const timing = JSON.parse(fs.readFileSync(a.timing, 'utf8'));
const lead = Number(a.lead);
const sbDir = path.dirname(path.resolve(a.storyboard));
const expand = (p) => path.resolve(sbDir, String(p).replace(/\$\{EFB_WORK\}/g, EFB_WORK));
const markers = {};
for (const [k, as] of Object.entries(sb.assets || {})) if (as.markers) markers[k] = JSON.parse(fs.readFileSync(expand(as.markers), 'utf8'));
// a time reference is a chapter id, or the name of a marker rect (its capture time: the moment just before the click)
const chapter = (key, v, dflt) => (v === undefined ? dflt : typeof v === 'number' ? v : (markers[key]?.chapters.find((c) => c.id === v)?.t ?? markers[key]?.rects?.[v]?.t ?? (() => { throw new Error(`chapter ${v} not in markers of ${key}`); })()));

const report = [];
for (const s of sb.scenes) {
  const win = s.id && timing.scenes[s.id];
  if (!win) continue;
  let dur = +(win.end - win.start).toFixed(3);
  if (s.type === 'outro') dur = Math.max(dur, 3) + (s.hold || 0); // hold: silent seconds after the last word
  let nominal = s.dur;
  if (s.type === 'recording') {
    const from = chapter(s.src, s.from, 0), to = chapter(s.src, s.to, from + (s.dur || 5) * (s.speed || 1));
    nominal = nominal ?? (to - from) / (s.speed || 1);
    const speed = Math.min(s.maxSpeed || 4, Math.max(0.6, (to - from) / dur)); // maxSpeed: per-scene cap (default 4x)
    if ((to - from) / dur > (s.maxSpeed || 4)) report.push(`  WARNING ${s.id}: needs ${((to - from) / dur).toFixed(2)}x, capped -> the scene ends before "${s.to}"`);
    report.push(`${s.id}: ${nominal.toFixed(2)}s @${(s.speed || 1).toFixed(2)}x -> ${dur.toFixed(2)}s @${speed.toFixed(2)}x`);
    s.speed = +speed.toFixed(3);
    delete s.dur;
    s.dur = dur;
  } else {
    nominal = nominal ?? dur;
    report.push(`${s.id}: ${nominal.toFixed(2)}s -> ${dur.toFixed(2)}s`);
    s.dur = dur;
  }
  const f = dur / nominal;
  const from = s.type === 'recording' ? chapter(s.src, s.from, 0) : 0;
  const at = (v) => {
    if (typeof v === 'number') return +(v * f).toFixed(3);
    if (s.type !== 'recording') throw new Error(`scene ${s.id}: chapter reference "${v}" only works in recording scenes`);
    // an exact chapter/marker name wins ("card-number-2" is a name, not "card-number" minus 2 s); otherwise the
    // trailing +N / -N is the offset
    const mk = markers[s.src] || {};
    const known = (k) => mk.chapters?.some((c) => c.id === k) || !!mk.rects?.[k];
    const m = known(String(v)) ? [v, String(v), 0] : String(v).match(/^(.*)([+-]\d+(?:\.\d+)?)$/) || [v, String(v), 0];
    const t = (chapter(s.src, m[1]) - from) / s.speed + Number(m[2] || 0);
    return +Math.min(Math.max(t, 0), dur).toFixed(3);
  };
  const scaleDur = (x) => +(x * f).toFixed(3); // durations given with a chapter-referenced `at` stay in output seconds
  for (const k of s.camera || []) k.at = at(k.at);
  for (const c of s.callouts || []) { const ref = typeof c.at !== 'number'; c.at = at(c.at); if (c.dur && !ref) c.dur = scaleDur(c.dur); }
  if (s.lowerThird) { const ref = typeof s.lowerThird.at !== 'number'; s.lowerThird.at = at(s.lowerThird.at); if (s.lowerThird.dur && !ref) s.lowerThird.dur = scaleDur(s.lowerThird.dur); }
}
const total = sb.scenes.reduce((t, s) => t + (s.dur ?? 0), 0);
fs.writeFileSync(a.srt, toSrt(timing.sentences, { offset: lead }));
sb.captions = { srt: path.resolve(a.srt), style: sb.captions?.style || 'bar' };
sb.meta.fitted = { lead, audio_seconds: timing.duration, scenes_total_seconds: +total.toFixed(2) };
fs.writeFileSync(a.out, JSON.stringify(sb, null, 2));
console.log(report.join('\n'));
console.log(`[fit] video length ~${total.toFixed(2)}s (voice-over ${timing.duration.toFixed(2)}s starts at ${lead}s) -> ${a.out}, captions -> ${a.srt}`);

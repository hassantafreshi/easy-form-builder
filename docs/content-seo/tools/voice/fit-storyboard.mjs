#!/usr/bin/env node
/**
 * fit-storyboard.mjs - make the picture follow the narration.
 *
 *   node fit-storyboard.mjs --storyboard poc.json --timing timing.json --out fitted.json --srt captions.en.srt [--lead 2]
 *
 * - every scene that has a `narration` gets duration = its narration window (see align.mjs); recordings get the
 *   playback `speed` that makes their from..to range fit; camera / callout / lower-third times are rescaled by the
 *   same ratio so the choreography keeps its shape.
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
const chapter = (key, v, dflt) => (v === undefined ? dflt : typeof v === 'number' ? v : (markers[key]?.chapters.find((c) => c.id === v)?.t ?? (() => { throw new Error(`chapter ${v} not in markers of ${key}`); })()));

const report = [];
for (const s of sb.scenes) {
  const win = s.id && timing.scenes[s.id];
  if (!win) continue;
  let dur = +(win.end - win.start).toFixed(3);
  if (s.type === 'outro') dur = Math.max(dur, 3);
  let nominal = s.dur;
  if (s.type === 'recording') {
    const from = chapter(s.src, s.from, 0), to = chapter(s.src, s.to, from + (s.dur || 5) * (s.speed || 1));
    nominal = nominal ?? (to - from) / (s.speed || 1);
    const speed = Math.min(4, Math.max(0.6, (to - from) / dur));
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
  for (const k of s.camera || []) k.at = +(k.at * f).toFixed(3);
  for (const c of s.callouts || []) { c.at = +(c.at * f).toFixed(3); if (c.dur) c.dur = +(c.dur * f).toFixed(3); }
  if (s.lowerThird) { s.lowerThird.at = +(s.lowerThird.at * f).toFixed(3); if (s.lowerThird.dur) s.lowerThird.dur = +(s.lowerThird.dur * f).toFixed(3); }
}
const total = sb.scenes.reduce((t, s) => t + (s.dur ?? 0), 0);
fs.writeFileSync(a.srt, toSrt(timing.sentences, { offset: lead }));
sb.captions = { srt: path.resolve(a.srt), style: sb.captions?.style || 'bar' };
sb.meta.fitted = { lead, audio_seconds: timing.duration, scenes_total_seconds: +total.toFixed(2) };
fs.writeFileSync(a.out, JSON.stringify(sb, null, 2));
console.log(report.join('\n'));
console.log(`[fit] video length ~${total.toFixed(2)}s (voice-over ${timing.duration.toFixed(2)}s starts at ${lead}s) -> ${a.out}, captions -> ${a.srt}`);

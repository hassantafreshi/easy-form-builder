#!/usr/bin/env node
/**
 * align.mjs - sentence-level timing for a single-take narration, without any API call.
 *
 *   node align.mjs --audio voiceover.mp3 --storyboard storyboard.json --out timing.json [--noise -38] [--min-gap 0.18]
 *
 * The narration of every scene is split into sentences. ElevenLabs leaves a ~0.45 s silence at every sentence end,
 * so ffmpeg `silencedetect` finds the n-1 sentence boundaries of an n-sentence script. If there are extra gaps
 * (commas) the n-1 longest are used; if there are fewer, the script falls back to a character-proportional split
 * and marks the result approx:true (then use word timestamps from the TTS API / Scribe instead).
 *
 * timing.json: { duration, approx, sentences:[{scene,text,start,end}], scenes:{id:{start,end}} } (audio time, seconds);
 *   scene windows partition [0,duration] at the midpoints of the gaps, so they tile the whole narration.
 */
import fs from 'node:fs';
import { execFileSync, spawnSync } from 'node:child_process';
import { parseArgs } from '../lib/common.mjs';
import { sentencesOfStoryboard } from './lib-sentences.mjs';

const a = parseArgs(process.argv.slice(2), { noise: '-38', 'min-gap': '0.18' });
const sb = JSON.parse(fs.readFileSync(a.storyboard, 'utf8'));
const sents = sentencesOfStoryboard(sb);
const duration = parseFloat(execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', a.audio], { encoding: 'utf8' }));
const r = spawnSync('ffmpeg', ['-hide_banner', '-nostats', '-i', a.audio, '-af', `silencedetect=noise=${a.noise}dB:d=${a['min-gap']}`, '-f', 'null', '-'], { encoding: 'utf8' });
const gaps = []; let cur = null;
for (const line of r.stderr.split('\n')) {
  let m;
  if ((m = line.match(/silence_start: ([\d.]+)/))) cur = { s: +m[1] };
  else if ((m = line.match(/silence_end: ([\d.]+)/)) && cur) { cur.e = +m[1]; gaps.push(cur); cur = null; }
}
if (cur) { cur.e = duration; gaps.push(cur); }
const inner = gaps.filter((g) => g.s > 0.3 && g.e < duration - 0.05);
const need = sents.length - 1;
let approx = false; let bounds = [];
if (inner.length >= need) {
  bounds = [...inner].sort((x, y) => (y.e - y.s) - (x.e - x.s)).slice(0, need).sort((x, y) => x.s - y.s);
} else {
  approx = true;
  const total = sents.reduce((s, x) => s + x.text.length, 0); let acc = 0;
  for (let i = 0; i < need; i++) { acc += sents[i].text.length; const t = duration * (acc / total); bounds.push({ s: t - 0.2, e: t + 0.2 }); }
}
const first = gaps.find((g) => g.s < 0.05); const speechStart = first ? first.e : 0;
const last = gaps.find((g) => g.e >= duration - 0.05 && g.s > duration / 2); const speechEnd = last ? last.s : duration;
const sentences = sents.map((s, i) => ({ ...s, start: +(i === 0 ? speechStart : bounds[i - 1].e).toFixed(3), end: +(i === sents.length - 1 ? speechEnd : bounds[i].s).toFixed(3) }));
// scene windows tile [0,duration] (cut at gap midpoints)
const mids = bounds.map((b) => (b.s + b.e) / 2);
const scenes = {};
sents.forEach((s, i) => {
  const w = (scenes[s.scene] ||= { start: i === 0 ? 0 : mids[i - 1], end: duration, firstIdx: i });
  w.end = i === sents.length - 1 ? duration : mids[i];
});
for (const k of Object.keys(scenes)) delete scenes[k].firstIdx;
const out = { audio: a.audio, duration, approx, gaps_found: gaps.length, sentences, scenes };
fs.writeFileSync(a.out, JSON.stringify(out, null, 1));
console.log(`[align] ${sents.length} sentences, ${gaps.length} gaps found${approx ? ' (APPROXIMATE split)' : ''}, duration ${duration.toFixed(2)}s -> ${a.out}`);
for (const s of sentences) console.log(`  ${s.start.toFixed(2).padStart(6)}-${s.end.toFixed(2).padStart(6)}  [${s.scene}] ${s.text}`);

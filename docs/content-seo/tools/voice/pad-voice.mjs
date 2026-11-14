#!/usr/bin/env node
/**
 * pad-voice.mjs - give busy screen scenes more room WITHOUT a new (paid) TTS generation.
 *
 *   node pad-voice.mjs --storyboard sb.json --timing timing.json --audio voiceover.mp3 --out-dir DIR
 *
 * A scene with `"pad": 1.5` gets 1.5 s of silence inserted at the end of its narration window (the midpoint of the
 * pause before the next scene's first sentence), so its recording plays slower. All later sentence and scene times
 * shift by the same amount. Writes DIR/voiceover.mp3 (WAV-quality intermediate re-encoded at 192 kb/s) and
 * DIR/timing.json in the same schema as tts-elevenlabs.mjs / align.mjs, so fit-storyboard.mjs and the SRT writer
 * work unchanged. Scenes without `pad` are untouched; with no pads at all the files are copied.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { parseArgs } from '../lib/common.mjs';

const a = parseArgs(process.argv.slice(2));
const sb = JSON.parse(fs.readFileSync(a.storyboard, 'utf8'));
const timing = JSON.parse(fs.readFileSync(a.timing, 'utf8'));
fs.mkdirSync(a['out-dir'], { recursive: true });
const outAudio = path.join(a['out-dir'], 'voiceover.mp3');

// cut points (source seconds) with the silence to insert there
const cuts = sb.scenes.filter((s) => s.pad && timing.scenes[s.id]).map((s) => ({ at: timing.scenes[s.id].end, pad: Number(s.pad), scene: s.id }))
  .filter((c) => c.at < timing.duration - 0.05).sort((x, y) => x.at - y.at);
const shift = (t) => t + cuts.filter((c) => c.at <= t + 1e-6).reduce((n, c) => n + c.pad, 0);

if (!cuts.length) {
  if (path.resolve(a.audio) !== path.resolve(outAudio)) fs.copyFileSync(a.audio, outAudio);
} else {
  // [0,c1] + silence + [c1,c2] + silence ... + [cn,end]
  const bounds = [0, ...cuts.map((c) => c.at), null];
  const parts = []; let graph = '';
  for (let i = 0; i < bounds.length - 1; i++) {
    const end = bounds[i + 1] === null ? '' : `:end=${bounds[i + 1].toFixed(3)}`;
    graph += `[0:a]atrim=start=${bounds[i].toFixed(3)}${end},asetpts=PTS-STARTPTS[s${i}];`;
    parts.push(`[s${i}]`);
    if (i < cuts.length) { graph += `anullsrc=r=44100:cl=mono,atrim=duration=${cuts[i].pad.toFixed(3)}[z${i}];`; parts.push(`[z${i}]`); }
  }
  graph += `${parts.join('')}concat=n=${parts.length}:v=0:a=1[out]`;
  const r = spawnSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-i', a.audio, '-filter_complex', graph, '-map', '[out]', '-ac', '1', '-ar', '44100', '-c:a', 'libmp3lame', '-b:a', '192k', outAudio], { stdio: 'inherit' });
  if (r.status !== 0) throw new Error('ffmpeg padding failed');
}
const sentences = timing.sentences.map((s) => ({ ...s, start: +shift(s.start).toFixed(3), end: +shift(s.end).toFixed(3) }));
const scenes = {};
for (const [id, w] of Object.entries(timing.scenes)) {
  const own = cuts.find((c) => c.scene === id);
  scenes[id] = { start: +shift(w.start).toFixed(3), end: +(shift(w.end) + (own ? 0 : 0)).toFixed(3) };
  if (own) scenes[id].end = +(shift(w.end - 1e-3) + own.pad).toFixed(3); // the scene owns the silence after it
}
const duration = +(timing.duration + cuts.reduce((n, c) => n + c.pad, 0)).toFixed(3);
fs.writeFileSync(path.join(a['out-dir'], 'timing.json'), JSON.stringify({ ...timing, audio: 'voiceover.mp3', duration, padded: cuts, sentences, scenes }, null, 1));
console.log(`[pad] ${cuts.map((c) => `${c.scene} +${c.pad}s @${c.at.toFixed(2)}`).join(', ') || 'no pads'} -> ${duration.toFixed(2)}s`);

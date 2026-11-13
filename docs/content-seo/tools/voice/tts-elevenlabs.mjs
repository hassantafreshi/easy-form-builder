#!/usr/bin/env node
/**
 * tts-elevenlabs.mjs - narration -> voice-over MP3 + sentence timing, via the ElevenLabs REST API (unattended path).
 *
 *   ELEVENLABS_API_KEY=... node tts-elevenlabs.mjs --storyboard sb.json --out-dir DIR [--voice VOICE_ID] [--model eleven_v4] [--force]
 *
 * One request for the whole script ("with-timestamps" returns the audio AND character-level alignment), so there is
 * no separate alignment step and exactly ONE billed generation per distinct script. Results are cached by
 * sha1(text|voice|model) under $EFB_WORK/voice-cache: re-running a pipeline with an unchanged script costs nothing.
 *
 * STATUS: written from the public API reference; NOT executed in the spike container (no API key there - the spike
 * used the ElevenLabs MCP connector instead and tools/voice/align.mjs for timing). Treat as untested until the owner
 * supplies a key.
 */
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { parseArgs, EFB_WORK } from '../lib/common.mjs';
import { sentencesOfStoryboard } from './lib-sentences.mjs';

const a = parseArgs(process.argv.slice(2), { model: 'eleven_v4', voice: 'hpp4J3VqNfWAUOO0d1Us' }); // Bella (premade), see README
const sb = JSON.parse(fs.readFileSync(a.storyboard, 'utf8'));
const sents = sentencesOfStoryboard(sb);
const text = sb.scenes.filter((s) => s.narration).map((s) => s.narration).join('\n\n');
const key = crypto.createHash('sha1').update(`${text}|${a.voice}|${a.model}`).digest('hex').slice(0, 16);
const cache = path.join(EFB_WORK, 'voice-cache'); fs.mkdirSync(cache, { recursive: true });
const mp3 = path.join(cache, `${key}.mp3`), meta = path.join(cache, `${key}.json`);
fs.mkdirSync(a['out-dir'], { recursive: true });

if (a.force || !fs.existsSync(mp3)) {
  if (!process.env.ELEVENLABS_API_KEY) { console.error('no cached audio for this script and ELEVENLABS_API_KEY is not set'); process.exit(3); }
  const res = await fetch(`https://api.elevenlabs.io/v1/text-to-speech/${a.voice}/with-timestamps?output_format=mp3_44100_128`, {
    method: 'POST', headers: { 'xi-api-key': process.env.ELEVENLABS_API_KEY, 'content-type': 'application/json' },
    body: JSON.stringify({ text, model_id: a.model }), signal: AbortSignal.timeout(120000),
  });
  if (!res.ok) { console.error('ElevenLabs HTTP', res.status, (await res.text()).slice(0, 300)); process.exit(4); } // never auto-retry: a retry is a second charge
  const j = await res.json();
  fs.writeFileSync(mp3, Buffer.from(j.audio_base64, 'base64'));
  fs.writeFileSync(meta, JSON.stringify(j.alignment));
}
const al = JSON.parse(fs.readFileSync(meta, 'utf8'));
// map each sentence to its character range in `text`, then to start/end seconds
let cursor = 0; const sentences = [];
for (const s of sents) {
  const i = text.indexOf(s.text, cursor); if (i < 0) throw new Error('sentence not found in text: ' + s.text);
  cursor = i + s.text.length;
  sentences.push({ ...s, start: al.character_start_times_seconds[i], end: al.character_end_times_seconds[cursor - 1] });
}
const duration = al.character_end_times_seconds.at(-1);
const scenes = {};
sentences.forEach((s, i) => { const w = (scenes[s.scene] ||= { start: i === 0 ? 0 : (sentences[i - 1].end + s.start) / 2, end: duration }); w.end = i === sentences.length - 1 ? duration : (s.end + sentences[i + 1].start) / 2; });
fs.copyFileSync(mp3, path.join(a['out-dir'], 'voiceover.mp3'));
fs.writeFileSync(path.join(a['out-dir'], 'timing.json'), JSON.stringify({ audio: 'voiceover.mp3', duration, approx: false, source: 'elevenlabs-with-timestamps', sentences, scenes }, null, 1));
console.log(`[tts] ${sents.length} sentences, ${duration.toFixed(2)}s, cache key ${key}`);

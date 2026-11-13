#!/usr/bin/env node
/**
 * srt-from-timing.mjs - write an SRT for ANY language from the sentence timing of the English voice-over.
 *
 *   node srt-from-timing.mjs --timing timing.json --out captions.de.srt [--lead 2] [--text translated.json] [--max-chars 42]
 *
 * --text is a JSON array of strings, one per narration sentence (same order as timing.sentences); omit it for the
 * original (English) sentences. The cue times are the English sentence times, so a translated cue appears exactly
 * while its source sentence is spoken. Long translations are wrapped (<=2 lines per cue) and split proportionally.
 * RTL languages (ar, fa, he) get a U+200F mark at the start of every line so players keep the punctuation on the left.
 */
import fs from 'node:fs';
import { parseArgs } from '../lib/common.mjs';
import { toSrt } from './lib-sentences.mjs';

const a = parseArgs(process.argv.slice(2), { lead: '2', 'max-chars': '42' });
const t = JSON.parse(fs.readFileSync(a.timing, 'utf8'));
let sentences = t.sentences;
if (a.text) {
  const tr = JSON.parse(fs.readFileSync(a.text, 'utf8'));
  if (tr.length !== sentences.length) throw new Error(`translation has ${tr.length} entries, timing has ${sentences.length} sentences`);
  sentences = sentences.map((s, i) => ({ ...s, text: String(tr[i]).trim() }));
}
let srt = toSrt(sentences, { offset: Number(a.lead), maxChars: Number(a['max-chars']) });
if (/\.(ar|fa|he)\.srt$/i.test(a.out)) srt = srt.split('\n').map((l) => (l && !/^\d+$/.test(l) && !l.includes('-->') ? '‏' + l : l)).join('\n');
fs.writeFileSync(a.out, srt);
console.log(`[srt] ${a.out}: ${srt.trim().split(/\n\s*\n/).length} cues`);

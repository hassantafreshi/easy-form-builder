// Shared helpers: narration script <-> sentences, SRT formatting.
export function splitSentences(text) {
  return text.replace(/\s+/g, ' ').trim().split(/(?<=[.!?])\s+/).filter(Boolean);
}

/** Narration units in storyboard order: [{scene, text}] one per sentence. */
export function sentencesOfStoryboard(sb) {
  const out = [];
  sb.scenes.forEach((s, i) => { if (s.narration) for (const t of splitSentences(s.narration)) out.push({ scene: s.id || `scene${i}`, text: t }); });
  return out;
}

export const fmtSrt = (sec) => {
  const ms = Math.max(0, Math.round(sec * 1000));
  const h = Math.floor(ms / 3600000), m = Math.floor((ms % 3600000) / 60000), s = Math.floor((ms % 60000) / 1000), r = ms % 1000;
  const p = (n, w = 2) => String(n).padStart(w, '0');
  return `${p(h)}:${p(m)}:${p(s)},${p(r, 3)}`;
};

/** Break a sentence into <=2 lines of <=maxChars (greedy on spaces). */
export function wrapLines(text, maxChars = 42) {
  const words = text.split(' '); const lines = []; let cur = '';
  for (const w of words) { if ((cur + ' ' + w).trim().length > maxChars && cur) { lines.push(cur); cur = w; } else cur = (cur + ' ' + w).trim(); }
  if (cur) lines.push(cur);
  return lines;
}

/** Sentences with {start,end} (output timeline, seconds) -> SRT text. Long sentences are split into several cues. */
export function toSrt(sentences, { maxChars = 42, maxLines = 2, offset = 0 } = {}) {
  const cues = [];
  for (const s of sentences) {
    const lines = wrapLines(s.text, maxChars);
    const groups = []; for (let i = 0; i < lines.length; i += maxLines) groups.push(lines.slice(i, i + maxLines));
    const total = groups.reduce((a, g) => a + g.join(' ').length, 0);
    let t = s.start + offset;
    for (const g of groups) { const d = (s.end - s.start) * (g.join(' ').length / total); cues.push({ start: t, end: t + d, text: g.join('\n') }); t += d; }
  }
  return cues.map((c, i) => `${i + 1}\n${fmtSrt(c.start)} --> ${fmtSrt(c.end)}\n${c.text}\n`).join('\n');
}

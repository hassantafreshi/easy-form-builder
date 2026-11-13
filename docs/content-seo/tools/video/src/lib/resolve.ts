import { staticFile } from 'remotion';
import type { Brand, Cue, Resolved, ResolvedAsset, ResolvedScene, Storyboard } from './types';

export const DEFAULT_BRAND: Brand = { primary: '#3640d4', accent: '#ff4b93', dark: '#0e1230', text: '#ffffff', font: 'Inter, "DejaVu Sans", sans-serif' };

export function parseSrt(srt: string): Cue[] {
  const cues: Cue[] = [];
  const blocks = srt.replace(/\r/g, '').trim().split(/\n\s*\n/);
  const ts = (s: string) => { const m = s.trim().match(/(\d+):(\d+):(\d+)[,.](\d+)/); return m ? +m[1] * 3600 + +m[2] * 60 + +m[3] + +m[4].padEnd(3, '0').slice(0, 3) / 1000 : 0; };
  for (const b of blocks) {
    const lines = b.split('\n');
    const i = lines.findIndex((l) => l.includes('-->'));
    if (i < 0) continue;
    const [a, z] = lines[i].split('-->');
    cues.push({ start: ts(a), end: ts(z), text: lines.slice(i + 1).join('\n').replace(/<[^>]+>/g, '').trim() });
  }
  return cues;
}

const getJson = async (name?: string) => (name ? (await fetch(staticFile(name))).json() : null);

/** Runs inside calculateMetadata (browser context, before frames are rendered). */
export async function resolveStoryboard(sb: Storyboard): Promise<Resolved> {
  const fps = sb.meta.fps || 30;
  const brand = { ...DEFAULT_BRAND, ...(sb.meta.brand || {}) } as Brand;
  const assets: Record<string, ResolvedAsset> = {};
  for (const [key, a] of Object.entries(sb.assets || {})) {
    const markers = await getJson(a.markers);
    const events = (await getJson(a.events)) || [];
    const size = a.size || (markers?.viewport ? [markers.viewport.width, markers.viewport.height] : [1440, 900]);
    assets[key] = { video: a.video, image: a.image, size: size as [number, number], events, rects: markers?.rects || {}, chapters: markers?.chapters || [] };
  }
  const cues = sb.captions?.srt ? parseSrt(await (await fetch(staticFile(sb.captions.srt))).text()) : [];

  let cursor = 0;
  const scenes: ResolvedScene[] = sb.scenes.map((s) => {
    let sceneDur = s.dur;
    let fromSec: number | undefined; let speed = 1;
    if (s.type === 'recording' || s.type === 'image') {
      const asset = assets[s.src];
      if (!asset) throw new Error(`scene ${s.id || ''}: unknown asset "${s.src}"`);
      const chapter = (v: number | string | undefined, dflt: number) => {
        if (v === undefined) return dflt;
        if (typeof v === 'number') return v;
        const c = asset.chapters.find((x) => x.id === v);
        if (!c) throw new Error(`scene ${s.id || ''}: chapter "${v}" not found in markers (have: ${asset.chapters.map((x) => x.id).join(', ')})`);
        return c.t;
      };
      if (s.type === 'recording') {
        speed = s.speed || 1;
        fromSec = chapter(s.from, 0);
        const toSec = chapter(s.to, fromSec + (s.dur || 5) * speed);
        if (sceneDur === undefined) sceneDur = (toSec - fromSec) / speed;
      }
      if (sceneDur === undefined) sceneDur = 4;
    } else if (sceneDur === undefined) sceneDur = s.type === 'intro' ? 2 : 3;
    const durFrames = Math.max(1, Math.round(sceneDur * fps));
    const r: ResolvedScene = { ...s, startFrame: cursor, durFrames, fromSec, speed };
    cursor += durFrames;
    return r;
  });
  return { fps, brand, portraitZoom: sb.meta.portraitZoom || 1, assets, scenes, cues, captionStyle: sb.captions?.style || 'bar', totalFrames: cursor };
}

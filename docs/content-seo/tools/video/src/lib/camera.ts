import { Easing, interpolate } from 'remotion';
import type { CameraKey, Focus, ResolvedAsset } from './types';

export type Card = { x: number; y: number; w: number; h: number };
export type Cam = { zoom: number; cx: number; cy: number; s: number; card: Card; size: [number, number] };

/** Resolve a storyboard focus into source (CSS px) coordinates. */
export function focusPoint(f: Focus, asset: ResolvedAsset): { x: number; y: number } {
  const [sw, sh] = asset.size;
  if (!f) return { x: sw / 2, y: sh / 2 };
  if (typeof f === 'string') {
    const r = asset.rects[f];
    if (!r) throw new Error(`focus "${f}" is not a known marker (have: ${Object.keys(asset.rects).join(', ')})`);
    return { x: r.x + r.w / 2, y: r.y + r.h / 2 };
  }
  if (Array.isArray(f)) return { x: f[0] * sw, y: f[1] * sh };
  return f;
}

const ease = (k?: string) => (k === 'linear' ? (t: number) => t : k === 'out' ? Easing.out(Easing.cubic) : Easing.inOut(Easing.cubic));

/** Camera (zoom + focus point in source px) at scene-local time `t` seconds, interpolating storyboard keyframes. */
export function cameraAt(keys: CameraKey[] | undefined, t: number, asset: ResolvedAsset, card: Card, zoomBoost = 1): Cam {
  const ks = (keys && keys.length ? [...keys] : [{ at: 0, zoom: 1 }]).sort((a, b) => a.at - b.at);
  const pts = ks.map((k) => ({ at: k.at, zoom: k.zoom ?? 1, ...focusPoint(k.focus, asset), e: ease(k.ease) }));
  let zoom = pts[0].zoom, cx = pts[0].x, cy = pts[0].y;
  if (t >= pts[pts.length - 1].at) { const l = pts[pts.length - 1]; zoom = l.zoom; cx = l.x; cy = l.y; }
  else if (t > pts[0].at) {
    for (let i = 0; i < pts.length - 1; i++) {
      const a = pts[i], b = pts[i + 1];
      if (t >= a.at && t <= b.at) {
        const p = b.e((t - a.at) / Math.max(1e-6, b.at - a.at));
        zoom = a.zoom + (b.zoom - a.zoom) * p; cx = a.x + (b.x - a.x) * p; cy = a.y + (b.y - a.y) * p; break;
      }
    }
  }
  const [sw, sh] = asset.size;
  const s0 = Math.max(card.w / sw, card.h / sh); // 'cover' fit of the source into the card
  const s = s0 * zoom * zoomBoost;
  // keep the visible window inside the source
  const hw = card.w / (2 * s), hh = card.h / (2 * s);
  cx = Math.min(Math.max(cx, hw), sw - hw); cy = Math.min(Math.max(cy, hh), sh - hh);
  return { zoom, cx, cy, s, card, size: asset.size };
}

export const project = (cam: Cam, x: number, y: number) => ({
  x: cam.card.x + cam.card.w / 2 + (x - cam.cx) * cam.s,
  y: cam.card.y + cam.card.h / 2 + (y - cam.cy) * cam.s,
});

export const clamp01 = (v: number) => Math.min(1, Math.max(0, v));
export const ramp = (t: number, a: number, b: number) => interpolate(t, [a, b], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

import React from 'react';
import { spring, useVideoConfig } from 'remotion';
import type { Cam } from '../lib/camera';
import { focusPoint, project, ramp } from '../lib/camera';
import type { Brand, Callout as CalloutT, ResolvedAsset } from '../lib/types';

export type Box = { x: number; y: number; w: number; h: number };

/** The target's bounding box in output pixels (a marker rect projected through the camera; a point gets a small box). */
export function targetBox(target: CalloutT['target'], asset: ResolvedAsset, cam: Cam): Box {
  if (typeof target === 'string' && asset.rects[target]) {
    const r = asset.rects[target];
    const a = project(cam, r.x, r.y), b = project(cam, r.x + r.w, r.y + r.h);
    return { x: a.x, y: a.y, w: b.x - a.x, h: b.y - a.y };
  }
  const p = focusPoint(target, asset); const q = project(cam, p.x, p.y);
  return { x: q.x - 12, y: q.y - 12, w: 24, h: 24 };
}

const intersects = (a: Box, b: Box) => a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;

/**
 * Where the badge goes: BESIDE the target, never on it. Sides are tried in order (storyboard `side` first, then
 * right, left, below, above); a side is used when the badge fits inside the safe area (outer margin, caption band at
 * the bottom) without touching the focus ring or any `avoid` box (lower third / path pill). If none fits, the side
 * with the least overlap wins.
 */
export function placeBadge(box: Box, bw: number, bh: number, prefer: string | undefined, safe: Box, gap: number, avoid: Box[]): { x: number; y: number; side: string } {
  const sides = [prefer, 'right', 'left', 'below', 'above'].filter((v, i, a): v is string => !!v && a.indexOf(v) === i);
  const cx = box.x + box.w / 2, cy = box.y + box.h / 2;
  const clampX = (x: number) => Math.min(Math.max(x, safe.x), safe.x + safe.w - bw);
  const clampY = (y: number) => Math.min(Math.max(y, safe.y), safe.y + safe.h - bh);
  let best: { x: number; y: number; side: string; cost: number } | null = null;
  for (const side of sides) {
    let x = 0, y = 0;
    if (side === 'right') { x = box.x + box.w + gap; y = clampY(cy - bh / 2); }
    else if (side === 'left') { x = box.x - gap - bw; y = clampY(cy - bh / 2); }
    else if (side === 'below') { y = box.y + box.h + gap; x = clampX(cx - bw / 2); }
    else { y = box.y - gap - bh; x = clampX(cx - bw / 2); }
    const b = { x, y, w: bw, h: bh };
    const inside = x >= safe.x - 0.5 && y >= safe.y - 0.5 && x + bw <= safe.x + safe.w + 0.5 && y + bh <= safe.y + safe.h + 0.5;
    const ring = { x: box.x - gap / 2, y: box.y - gap / 2, w: box.w + gap, h: box.h + gap };
    const hits = [ring, ...avoid].filter((a) => intersects(a, b)).length;
    if (inside && !hits) return { x, y, side };
    const cb = { x: clampX(x), y: clampY(y), w: bw, h: bh };
    const cost = [ring, ...avoid].reduce((n, a) => n + (intersects(a, cb) ? Math.min(cb.x + bw, a.x + a.w) - Math.max(cb.x, a.x) : 0) * (intersects(a, cb) ? Math.min(cb.y + bh, a.y + a.h) - Math.max(cb.y, a.y) : 0), 0);
    if (!best || cost < best.cost) best = { x: cb.x, y: cb.y, side, cost };
  }
  return best!;
}

/** Focus ring around the real UI element + numbered badge placed beside it (follows the camera). */
export const CalloutBadge: React.FC<{ c: CalloutT; cam: Cam; asset: ResolvedAsset; t: number; unit: number; brand: Brand; W: number; H: number; portrait: boolean; avoid?: Box[] }> = ({ c, cam, asset, t, unit, brand, W, H, portrait, avoid = [] }) => {
  const { fps } = useVideoConfig();
  const dur = c.dur ?? 2.5;
  if (t < c.at || t > c.at + dur + 0.4) return null;
  const pop = spring({ frame: Math.round((t - c.at) * fps), fps, config: { damping: 200, stiffness: 120, mass: 1 } });
  const ringIn = ramp(t, c.at - 0.05, c.at + 0.3);
  const out = 1 - ramp(t, c.at + dur, c.at + dur + 0.3);
  const box = targetBox(c.target, asset, cam);
  const pad = unit * 10;
  const ring = { x: box.x - pad, y: box.y - pad, w: box.w + pad * 2, h: box.h + pad * 2 };
  const fs = unit * 32;
  const hasN = c.n !== undefined && c.n !== null;
  const bh = unit * 70;
  const bw = (hasN ? unit * 64 : 0) + unit * 50 + c.text.length * fs * 0.54;
  const margin = unit * 48;
  const bottomReserve = portrait ? unit * 470 : unit * 215; // burned-in caption band
  const safe = { x: margin, y: margin, w: W - margin * 2, h: H - margin - bottomReserve };
  const gap = unit * 30;
  const pos = placeBadge(ring, bw, bh, c.side, safe, gap, avoid);
  // connector: from the ring edge to the badge edge facing it
  const rcx = ring.x + ring.w / 2, rcy = ring.y + ring.h / 2;
  const a = pos.side === 'right' ? { x: ring.x + ring.w, y: Math.min(Math.max(pos.y + bh / 2, ring.y), ring.y + ring.h) }
    : pos.side === 'left' ? { x: ring.x, y: Math.min(Math.max(pos.y + bh / 2, ring.y), ring.y + ring.h) }
    : pos.side === 'below' ? { x: Math.min(Math.max(pos.x + bw / 2, ring.x), ring.x + ring.w), y: ring.y + ring.h }
    : { x: Math.min(Math.max(pos.x + bw / 2, ring.x), ring.x + ring.w), y: ring.y };
  const b = pos.side === 'right' ? { x: pos.x, y: pos.y + bh / 2 } : pos.side === 'left' ? { x: pos.x + bw, y: pos.y + bh / 2 }
    : pos.side === 'below' ? { x: pos.x + bw / 2, y: pos.y } : { x: pos.x + bw / 2, y: pos.y + bh };
  void rcx; void rcy;
  const perim = 2 * (ring.w + ring.h);
  const radius = Math.min(unit * 14, ring.h / 2, ring.w / 2);
  const showRing = c.ring !== false;
  return (
    <div style={{ position: 'absolute', inset: 0, opacity: out, pointerEvents: 'none' }}>
      <svg width={W} height={H} style={{ position: 'absolute', inset: 0, overflow: 'visible' }}>
        {showRing ? (
          <>
            <rect x={ring.x} y={ring.y} width={ring.w} height={ring.h} rx={radius} fill="none" stroke={brand.accent} strokeWidth={unit * 5}
              strokeDasharray={perim} strokeDashoffset={perim * (1 - ringIn)} />
            <rect x={ring.x + unit * 3.5} y={ring.y + unit * 3.5} width={Math.max(0, ring.w - unit * 7)} height={Math.max(0, ring.h - unit * 7)} rx={Math.max(0, radius - unit * 3)} fill="none" stroke="#fff" strokeWidth={unit * 2} opacity={ringIn} />
          </>
        ) : null}
        <line x1={a.x} y1={a.y} x2={a.x + (b.x - a.x) * pop} y2={a.y + (b.y - a.y) * pop} stroke={brand.accent} strokeWidth={unit * 4} strokeLinecap="round" />
      </svg>
      <div style={{ position: 'absolute', left: pos.x, top: pos.y, width: bw, height: bh, boxSizing: 'border-box', transform: `scale(${0.92 + 0.08 * pop})`, opacity: pop, transformOrigin: pos.side === 'right' ? 'left center' : pos.side === 'left' ? 'right center' : pos.side === 'below' ? 'center top' : 'center bottom',
        display: 'flex', alignItems: 'center', gap: unit * 14, background: brand.dark, color: brand.text, border: `${unit * 3}px solid ${brand.accent}`, borderRadius: bh / 2,
        padding: `0 ${unit * 26}px 0 ${hasN ? unit * 6 : unit * 26}px`, boxShadow: '0 10px 30px rgba(0,0,0,.35)', whiteSpace: 'nowrap', fontFamily: brand.font }}>
        {hasN ? <div style={{ flex: 'none', width: unit * 56, height: unit * 56, borderRadius: '50%', background: brand.accent, color: '#fff', fontWeight: 800, fontSize: unit * 32, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{c.n}</div> : null}
        <div style={{ fontSize: fs, fontWeight: 600 }}>{c.text}</div>
      </div>
    </div>
  );
};

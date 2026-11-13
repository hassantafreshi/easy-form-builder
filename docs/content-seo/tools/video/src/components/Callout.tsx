import React from 'react';
import { spring, useCurrentFrame, useVideoConfig } from 'remotion';
import type { Cam } from '../lib/camera';
import { focusPoint, project, ramp } from '../lib/camera';
import type { Brand, Callout as CalloutT, ResolvedAsset } from '../lib/types';

/** Numbered badge + label anchored to a UI element; follows the camera so it stays attached to the element. */
export const CalloutBadge: React.FC<{ c: CalloutT; cam: Cam; asset: ResolvedAsset; t: number; unit: number; brand: Brand; W: number; H: number }> = ({ c, cam, asset, t, unit, brand, W, H }) => {
  const { fps } = useVideoConfig();
  const frame = useCurrentFrame();
  const dur = c.dur ?? 2.5;
  if (t < c.at || t > c.at + dur + 0.4) return null;
  const pop = spring({ frame: Math.round((t - c.at) * fps), fps, config: { damping: 14, stiffness: 160 } });
  const out = 1 - ramp(t, c.at + dur, c.at + dur + 0.3);
  const target = project(cam, focusPoint(c.target, asset).x, focusPoint(c.target, asset).y);
  const d = unit * 120;
  const side = c.side || 'below';
  const off = { above: [0, -d], below: [0, d], left: [-d * 1.5, 0], right: [d * 1.5, 0] }[side];
  let bx = target.x + off[0], by = target.y + off[1];
  const pad = unit * 140;
  bx = Math.min(Math.max(bx, pad), W - pad); by = Math.min(Math.max(by, unit * 90), H - unit * 90);
  const r = unit * 30;
  void frame;
  return (
    <div style={{ position: 'absolute', inset: 0, opacity: out, pointerEvents: 'none' }}>
      <svg width={W} height={H} style={{ position: 'absolute', inset: 0 }}>
        <line x1={target.x} y1={target.y} x2={target.x + (bx - target.x) * pop} y2={target.y + (by - target.y) * pop} stroke={brand.accent} strokeWidth={unit * 4} strokeLinecap="round" />
        <circle cx={target.x} cy={target.y} r={unit * 9 * pop} fill={brand.accent} />
      </svg>
      <div style={{ position: 'absolute', left: bx, top: by, transform: `translate(-50%,-50%) scale(${pop})`, display: 'flex', alignItems: 'center', gap: unit * 14, background: brand.dark, color: brand.text, border: `${unit * 3}px solid ${brand.accent}`, borderRadius: unit * 44, padding: `${unit * 8}px ${unit * 26}px ${unit * 8}px ${unit * 8}px`, boxShadow: '0 10px 30px rgba(0,0,0,.4)', whiteSpace: 'nowrap', fontFamily: brand.font }}>
        <div style={{ width: r * 2, height: r * 2, borderRadius: '50%', background: brand.accent, color: '#fff', fontWeight: 800, fontSize: unit * 34, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{c.n ?? ''}</div>
        <div style={{ fontSize: unit * 32, fontWeight: 600 }}>{c.text}</div>
      </div>
    </div>
  );
};

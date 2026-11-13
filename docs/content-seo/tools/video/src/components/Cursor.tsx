import React from 'react';
import type { Cam } from '../lib/camera';
import { project } from '../lib/camera';
import type { CursorEvt } from '../lib/types';

/** Cursor position at video time tv (seconds), from capture.mjs events. Hidden before the first event. */
export function cursorAt(events: CursorEvt[], tv: number): { x: number; y: number } | null {
  if (!events.length || tv < events[0].t) return null;
  let lo = 0, hi = events.length - 1;
  while (lo < hi) { const m = (lo + hi + 1) >> 1; if (events[m].t <= tv) lo = m; else hi = m - 1; }
  const a = events[lo], b = events[lo + 1];
  if (!b || b.t - a.t > 0.35) return { x: a.x, y: a.y }; // long gap (page load): hold
  const p = (tv - a.t) / (b.t - a.t);
  return { x: a.x + (b.x - a.x) * p, y: a.y + (b.y - a.y) * p };
}

export const Cursor: React.FC<{ cam: Cam; events: CursorEvt[]; tv: number; unit: number; accent: string }> = ({ cam, events, tv, unit, accent }) => {
  const pos = cursorAt(events, tv);
  if (!pos) return null;
  const p = project(cam, pos.x, pos.y);
  const ripples = events.filter((e) => e.type === 'down' && tv >= e.t && tv - e.t < 0.7);
  return (
    <>
      {ripples.map((e, i) => {
        const q = (tv - e.t) / 0.7;
        const rp = project(cam, e.x, e.y);
        const r = unit * (14 + q * 70);
        return <div key={i} style={{ position: 'absolute', left: rp.x - r, top: rp.y - r, width: r * 2, height: r * 2, borderRadius: '50%', border: `${Math.max(2, unit * 4 * (1 - q))}px solid ${accent}`, opacity: 1 - q, boxShadow: `0 0 ${unit * 20}px ${accent}66` }} />;
      })}
      {/* soft highlight halo */}
      <div style={{ position: 'absolute', left: p.x - unit * 28, top: p.y - unit * 28, width: unit * 56, height: unit * 56, borderRadius: '50%', background: `radial-gradient(circle, ${accent}55 0%, ${accent}00 70%)` }} />
      <svg style={{ position: 'absolute', left: p.x - unit * 3, top: p.y - unit * 2, width: unit * 30, height: unit * 38, filter: 'drop-shadow(0 3px 4px rgba(0,0,0,.45))' }} viewBox="0 0 24 30">
        <path d="M2 1 L2 23 L8 17.5 L12.2 27.5 L16 25.8 L11.8 16 L20 16 Z" fill="#fff" stroke="#111" strokeWidth="1.6" strokeLinejoin="round" />
      </svg>
    </>
  );
};

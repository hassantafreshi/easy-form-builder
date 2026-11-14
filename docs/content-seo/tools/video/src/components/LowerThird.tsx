import React from 'react';
import { spring, useVideoConfig } from 'remotion';
import { ramp } from '../lib/camera';
import type { Brand, LowerThird as LT } from '../lib/types';

/**
 * Step label. Default position is the TOP-left ("path pill" of the motion style guide): the bottom band belongs to the
 * burned-in captions (and to YouTube's own caption track), so the two can never overlap. `position: 'bottom'` keeps
 * the old lower-third placement, lifted above the caption band.
 */
const geom = (lt: LT, unit: number, portrait: boolean, H: number) => {
  const left = unit * 48;
  const h = unit * (lt.subtitle ? 104 : 70);
  const top = portrait ? unit * 150 : lt.position === 'bottom' ? H - unit * 215 - h - unit * 24 : unit * 44;
  const chars = Math.max(lt.title.length * 1.15, (lt.subtitle || '').length);
  const w = unit * 14 + unit * 56 + chars * unit * 15.5;
  return { left, top, w, h };
};

/** Box the step label occupies (callout badges keep out of it). */
export const lowerThirdBox = (lt: LT, unit: number, portrait: boolean, H: number) => {
  const g = geom(lt, unit, portrait, H);
  return { x: g.left - unit * 10, y: g.top - unit * 10, w: g.w + unit * 20, h: g.h + unit * 20 };
};

export const LowerThirdBar: React.FC<{ lt: LT; t: number; unit: number; brand: Brand; portrait: boolean; H: number }> = ({ lt, t, unit, brand, portrait, H }) => {
  const { fps } = useVideoConfig();
  const dur = lt.dur ?? 3;
  if (t < lt.at || t > lt.at + dur + 0.5) return null;
  const inP = spring({ frame: Math.round((t - lt.at) * fps), fps, config: { damping: 200, stiffness: 120 } });
  const out = ramp(t, lt.at + dur, lt.at + dur + 0.35);
  const g = geom(lt, unit, portrait, H);
  return (
    <div style={{ position: 'absolute', left: g.left, top: g.top, opacity: inP * (1 - out), transform: `translateY(${(1 - inP) * unit * -16 + out * unit * -10}px)`, fontFamily: brand.font }}>
      <div style={{ display: 'flex', alignItems: 'stretch', borderRadius: unit * 16, overflow: 'hidden', boxShadow: '0 12px 34px rgba(0,0,0,.35)' }}>
        <div style={{ width: unit * 12, background: brand.accent }} />
        <div style={{ background: `linear-gradient(90deg, ${brand.dark}f5, ${brand.primary}ee)`, color: brand.text, padding: `${unit * 12}px ${unit * 30}px ${unit * 14}px ${unit * 22}px` }}>
          <div style={{ fontSize: unit * 34, fontWeight: 800, letterSpacing: -0.3, lineHeight: 1.15 }}>{lt.title}</div>
          {lt.subtitle ? <div style={{ fontSize: unit * 27, fontWeight: 600, opacity: 0.9, marginTop: unit * 4, whiteSpace: 'nowrap' }}>{lt.subtitle}</div> : null}
        </div>
      </div>
    </div>
  );
};

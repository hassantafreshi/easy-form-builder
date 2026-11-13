import React from 'react';
import { spring, useVideoConfig } from 'remotion';
import { ramp } from '../lib/camera';
import type { Brand, LowerThird as LT } from '../lib/types';

export const LowerThirdBar: React.FC<{ lt: LT; t: number; unit: number; brand: Brand; portrait: boolean; H: number }> = ({ lt, t, unit, brand, portrait, H }) => {
  const { fps } = useVideoConfig();
  const dur = lt.dur ?? 3;
  if (t < lt.at || t > lt.at + dur + 0.5) return null;
  const inP = spring({ frame: Math.round((t - lt.at) * fps), fps, config: { damping: 18, stiffness: 120 } });
  const out = ramp(t, lt.at + dur, lt.at + dur + 0.4);
  const x = (1 - inP) * -unit * 700 - out * unit * 700;
  const top = portrait ? unit * 150 : H - unit * 270;
  return (
    <div style={{ position: 'absolute', left: unit * 64, top, transform: `translateX(${x}px)`, fontFamily: brand.font }}>
      <div style={{ display: 'flex', alignItems: 'stretch', boxShadow: '0 14px 40px rgba(0,0,0,.45)' }}>
        <div style={{ width: unit * 14, background: brand.accent }} />
        <div style={{ background: `linear-gradient(90deg, ${brand.dark}f2, ${brand.primary}e6)`, color: brand.text, padding: `${unit * 18}px ${unit * 40}px ${unit * 20}px ${unit * 28}px`, minWidth: unit * 460 }}>
          <div style={{ fontSize: unit * 46, fontWeight: 800, letterSpacing: -0.5 }}>{lt.title}</div>
          {lt.subtitle ? <div style={{ fontSize: unit * 30, fontWeight: 500, opacity: 0.85, marginTop: unit * 4 }}>{lt.subtitle}</div> : null}
        </div>
      </div>
    </div>
  );
};

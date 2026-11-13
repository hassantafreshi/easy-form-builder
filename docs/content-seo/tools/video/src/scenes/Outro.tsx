import React from 'react';
import { AbsoluteFill, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { ramp } from '../lib/camera';
import type { Brand, OutroScene } from '../lib/types';
import { Backdrop } from './Intro';

export const Outro: React.FC<{ s: OutroScene; brand: Brand; unit: number }> = ({ s, brand, unit }) => {
  const frame = useCurrentFrame();
  const { fps, width } = useVideoConfig();
  const p = spring({ frame, fps, config: { damping: 15, stiffness: 100 } });
  const cta = spring({ frame: frame - 18, fps, config: { damping: 12, stiffness: 140 } });
  return (
    <AbsoluteFill style={{ fontFamily: brand.font, color: brand.text, alignItems: 'center', justifyContent: 'center' }}>
      <Backdrop brand={brand} angle={300 - frame * 0.5} />
      <div style={{ position: 'absolute', width: unit * 1100, height: unit * 1100, borderRadius: '50%', left: '50%', top: '50%', transform: `translate(-50%,-50%) scale(${0.6 + 0.4 * p})`, background: `radial-gradient(circle, ${brand.accent}33, transparent 62%)` }} />
      <div style={{ textAlign: 'center', maxWidth: width * 0.9, padding: `0 ${unit * 70}px`, opacity: p, transform: `translateY(${(1 - p) * 26}px)` }}>
        <div dir="auto" style={{ fontSize: unit * 92, fontWeight: 800, lineHeight: 1.08, letterSpacing: -1.5 }}>{s.title}</div>
        {s.subtitle ? <div dir="auto" style={{ fontSize: unit * 42, opacity: 0.88 * ramp(frame / fps, 0.3, 0.7), marginTop: unit * 26, fontWeight: 500 }}>{s.subtitle}</div> : null}
        {s.cta ? <div dir="auto" style={{ display: 'inline-block', marginTop: unit * 52, background: brand.accent, color: '#fff', fontWeight: 800, fontSize: unit * 40, padding: `${unit * 20}px ${unit * 56}px`, borderRadius: unit * 60, transform: `scale(${Math.max(0, cta)})`, boxShadow: `0 12px 40px ${brand.accent}66` }}>{s.cta}</div> : null}
      </div>
    </AbsoluteFill>
  );
};

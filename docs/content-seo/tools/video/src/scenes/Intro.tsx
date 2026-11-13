import React from 'react';
import { AbsoluteFill, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { ramp } from '../lib/camera';
import type { Brand, IntroScene } from '../lib/types';

export const Backdrop: React.FC<{ brand: Brand; angle?: number }> = ({ brand, angle = 135 }) => (
  <AbsoluteFill style={{ background: `linear-gradient(${angle}deg, ${brand.dark} 0%, #1b1f66 55%, ${brand.primary} 130%)` }} />
);

export const Intro: React.FC<{ s: IntroScene; brand: Brand; portrait: boolean; unit: number }> = ({ s, brand, unit }) => {
  const frame = useCurrentFrame();
  const { fps, width, height } = useVideoConfig();
  const p = spring({ frame, fps, config: { damping: 16, stiffness: 110 } });
  const sub = ramp(frame / fps, 0.35, 0.8);
  const spin = frame * 0.6;
  return (
    <AbsoluteFill style={{ fontFamily: brand.font, color: brand.text, alignItems: 'center', justifyContent: 'center' }}>
      <Backdrop brand={brand} angle={120 + frame * 0.8} />
      <div style={{ position: 'absolute', width: unit * 900, height: unit * 900, borderRadius: '50%', right: -unit * 250, top: -unit * 300, background: `radial-gradient(circle, ${brand.accent}66, transparent 65%)`, transform: `rotate(${spin}deg)` }} />
      <div style={{ position: 'absolute', width: unit * 700, height: unit * 700, borderRadius: '50%', left: -unit * 200, bottom: -unit * 260, background: `radial-gradient(circle, ${brand.primary}99, transparent 65%)` }} />
      <div style={{ transform: `scale(${0.85 + 0.15 * p}) translateY(${(1 - p) * 30}px)`, opacity: p, textAlign: 'center', padding: `0 ${unit * 90}px`, maxWidth: width * 0.92 }}>
        {s.badge ? <div style={{ display: 'inline-block', background: brand.accent, color: '#fff', fontWeight: 800, fontSize: unit * 30, letterSpacing: 4, textTransform: 'uppercase', padding: `${unit * 8}px ${unit * 28}px`, borderRadius: unit * 40, marginBottom: unit * 34 }}>{s.badge}</div> : null}
        <div dir="auto" style={{ fontSize: unit * 104, fontWeight: 800, lineHeight: 1.05, letterSpacing: -2 }}>{s.title}</div>
        {s.subtitle ? <div dir="auto" style={{ fontSize: unit * 44, fontWeight: 500, opacity: sub * 0.9, marginTop: unit * 28, transform: `translateY(${(1 - sub) * 14}px)` }}>{s.subtitle}</div> : null}
      </div>
      <div style={{ position: 'absolute', left: 0, bottom: 0, height: unit * 10, width: width * ramp(frame, 0, 20), background: brand.accent }} />
      <div style={{ position: 'absolute', top: height - unit * 70, fontSize: unit * 24, opacity: 0.5 }}>Easy Form Builder</div>
    </AbsoluteFill>
  );
};

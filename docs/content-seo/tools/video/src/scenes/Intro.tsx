import React from 'react';
import { AbsoluteFill, Img, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { ramp } from '../lib/camera';
import type { Brand, IntroScene, ResolvedAsset } from '../lib/types';

export const Backdrop: React.FC<{ brand: Brand; angle?: number }> = ({ brand, angle = 135 }) => (
  <AbsoluteFill style={{ background: `linear-gradient(${angle}deg, ${brand.dark} 0%, #1b1f66 55%, ${brand.primary} 130%)` }} />
);

/**
 * Hook card (scene has `bg`): the result screenshot fills the frame (slow push-in), a dark gradient on the left
 * carries the headline. Text is fully visible at frame 0 (only position animates), per the motion style guide.
 */
const Hook: React.FC<{ s: IntroScene; brand: Brand; portrait: boolean; unit: number; asset: ResolvedAsset }> = ({ s, brand, portrait, unit, asset }) => {
  const frame = useCurrentFrame();
  const { fps, width: W, height: H, durationInFrames } = useVideoConfig();
  const k = frame / Math.max(1, durationInFrames);
  const [sw, sh] = asset.size;
  // bgFocus (0..1 of the screenshot) lands at 76% of the width (landscape) / 70% of the height (portrait)
  const [fx, fy] = s.bgFocus || [0.5, 0.5];
  const cover = Math.max(W / sw, H / sh) * (s.bgZoom || 1) * (1 + 0.04 * k);
  const iw = sw * cover, ih = sh * cover;
  const il = portrait ? W / 2 - fx * iw : W * 0.76 - fx * iw;
  const it = portrait ? H * 0.7 - fy * ih : H / 2 - fy * ih;
  const slide = spring({ frame, fps, config: { damping: 200, stiffness: 140 } });
  const fadeOut = Math.min(1, (durationInFrames - frame) / 6);
  return (
    <AbsoluteFill style={{ fontFamily: brand.font, color: brand.text, opacity: fadeOut, background: brand.dark }}>
      <Img src={staticFile(asset.image!)} style={{ position: 'absolute', width: iw, height: ih, left: il, top: it }} />
      <AbsoluteFill style={{ background: portrait ? `linear-gradient(180deg, ${brand.dark} 0%, ${brand.dark}f5 40%, ${brand.dark}00 58%)` : `linear-gradient(90deg, ${brand.dark} 0%, ${brand.dark} 38%, ${brand.dark}e6 45%, ${brand.dark}00 56%)` }} />
      <div style={{ position: 'absolute', left: unit * 110, top: portrait ? unit * 220 : H * 0.27, width: portrait ? W - unit * 200 : W * 0.38, transform: `translateX(${(1 - slide) * -unit * 40}px)` }}>
        {s.badge ? <div style={{ display: 'inline-block', background: brand.accent, color: '#fff', fontWeight: 800, fontSize: unit * 26, letterSpacing: 3, textTransform: 'uppercase', padding: `${unit * 8}px ${unit * 24}px`, borderRadius: unit * 40, marginBottom: unit * 30 }}>{s.badge}</div> : null}
        <div style={{ fontSize: unit * 88, fontWeight: 800, lineHeight: 1.04, letterSpacing: -1.5 }}>{s.title}</div>
        {s.subtitle ? <div style={{ fontSize: unit * 40, fontWeight: 500, opacity: 0.9, marginTop: unit * 26 }}>{s.subtitle}</div> : null}
        {s.chips?.length ? <div style={{ display: 'flex', gap: unit * 14, marginTop: unit * 34, flexWrap: 'wrap' }}>{s.chips.map((c, i) => <div key={i} style={{ fontSize: unit * 24, fontWeight: 700, letterSpacing: 1, textTransform: 'uppercase', padding: `${unit * 8}px ${unit * 20}px`, borderRadius: unit * 30, background: 'rgba(255,255,255,.14)', border: '1px solid rgba(255,255,255,.35)' }}>{c}</div>)}</div> : null}
      </div>
    </AbsoluteFill>
  );
};

export const Intro: React.FC<{ s: IntroScene; brand: Brand; portrait: boolean; unit: number; asset?: ResolvedAsset }> = (props) =>
  props.s.bg && props.asset?.image ? <Hook {...props} asset={props.asset} /> : <TitleCard {...props} />;

const TitleCard: React.FC<{ s: IntroScene; brand: Brand; portrait: boolean; unit: number }> = ({ s, brand, unit }) => {
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

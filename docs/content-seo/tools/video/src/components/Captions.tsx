import React from 'react';
import { useCurrentFrame, useVideoConfig } from 'remotion';
import type { Brand, Cue } from '../lib/types';

/** Burned-in captions read from an SRT (parsed in resolveStoryboard). Separate .srt delivery is independent of this. */
export const Captions: React.FC<{ cues: Cue[]; style: 'bar' | 'plain'; brand: Brand; unit: number; portrait: boolean; W: number; H: number }> = ({ cues, style, brand, unit, portrait, W, H }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const t = frame / fps;
  const cue = cues.find((c) => t >= c.start && t < c.end);
  if (!cue) return null;
  const maxW = portrait ? W * 0.9 : W * 0.72;
  return (
    <div style={{ position: 'absolute', left: (W - maxW) / 2, width: maxW, top: portrait ? H - unit * 430 : H - unit * 170, display: 'flex', justifyContent: 'center', fontFamily: brand.font }}>
      <div dir="auto" style={{ unicodeBidi: 'plaintext', textAlign: 'center', fontSize: unit * (portrait ? 46 : 40), fontWeight: 600, lineHeight: 1.25, color: '#fff', padding: style === 'bar' ? `${unit * 10}px ${unit * 26}px` : 0, borderRadius: unit * 14, background: style === 'bar' ? 'rgba(8,10,30,.78)' : 'transparent', textShadow: style === 'plain' ? '0 2px 8px rgba(0,0,0,.9)' : 'none', whiteSpace: 'pre-line' }}>
        {cue.text}
      </div>
    </div>
  );
};

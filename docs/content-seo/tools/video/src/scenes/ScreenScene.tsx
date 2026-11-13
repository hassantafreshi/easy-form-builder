import React from 'react';
import { AbsoluteFill, Img, OffthreadVideo, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { cameraAt, type Card } from '../lib/camera';
import type { Brand, ResolvedAsset, ResolvedScene, ScreenScene } from '../lib/types';
import { Backdrop } from './Intro';
import { CalloutBadge } from '../components/Callout';
import { Cursor } from '../components/Cursor';
import { LowerThirdBar } from '../components/LowerThird';

export const ScreenSceneView: React.FC<{ s: ScreenScene & ResolvedScene; asset: ResolvedAsset; brand: Brand; portrait: boolean; unit: number; zoomBoost: number }> = ({ s, asset, brand, portrait, unit, zoomBoost }) => {
  const frame = useCurrentFrame();
  const { fps, width: W, height: H } = useVideoConfig();
  const t = frame / fps;                                  // scene-local seconds (output time)
  const speed = s.speed || 1;
  const tv = (s.fromSec || 0) + t * speed;                // time inside the recording
  const card: Card = portrait ? { x: 0, y: Math.round(H * 0.215), w: W, h: Math.round(H * 0.56) } : { x: 0, y: 0, w: W, h: H };
  const cam = cameraAt(s.camera, t, asset, card, portrait ? zoomBoost : 1);
  const [sw, sh] = asset.size;
  const left = card.x + card.w / 2 - cam.cx * cam.s, top = card.y + card.h / 2 - cam.cy * cam.s;
  const mediaStyle: React.CSSProperties = { position: 'absolute', left, top, width: sw * cam.s, height: sh * cam.s };
  const fadeIn = Math.min(1, frame / 6), fadeOut = Math.min(1, (s.durFrames - frame) / 6);
  return (
    <AbsoluteFill style={{ opacity: Math.min(fadeIn, fadeOut), fontFamily: brand.font }}>
      <Backdrop brand={brand} angle={150} />
      <div style={{ position: 'absolute', left: card.x, top: card.y, width: card.w, height: card.h, overflow: 'hidden', background: '#fff', boxShadow: portrait ? '0 20px 60px rgba(0,0,0,.5)' : 'none' }}>
        <div style={{ position: 'absolute', left: -card.x, top: -card.y, width: W, height: H }}>
          {s.type === 'recording' && asset.video ? (
            <OffthreadVideo src={staticFile(asset.video)} muted playbackRate={speed} startFrom={Math.round((s.fromSec || 0) * fps)} style={mediaStyle} />
          ) : (
            <Img src={staticFile(asset.image!)} style={mediaStyle} />
          )}
        </div>
      </div>
      {s.cursor !== false && s.type === 'recording' ? (
        <div style={{ position: 'absolute', inset: 0, overflow: 'hidden', clipPath: `inset(${card.y}px 0 ${H - card.y - card.h}px 0)` }}>
          <Cursor cam={cam} events={asset.events} tv={tv} unit={unit} accent={brand.accent} />
        </div>
      ) : null}
      {(s.callouts || []).map((c, i) => <CalloutBadge key={i} c={c} cam={cam} asset={asset} t={t} unit={unit} brand={brand} W={W} H={H} />)}
      {s.lowerThird ? <LowerThirdBar lt={s.lowerThird} t={t} unit={unit} brand={brand} portrait={portrait} H={H} /> : null}
    </AbsoluteFill>
  );
};

import React from 'react';
import { AbsoluteFill, Sequence, useVideoConfig } from 'remotion';
import { Captions } from './components/Captions';
import { Intro } from './scenes/Intro';
import { Outro } from './scenes/Outro';
import { ScreenSceneView } from './scenes/ScreenScene';
import type { TutorialProps } from './lib/types';

export const Tutorial: React.FC<TutorialProps> = ({ resolved }) => {
  const { width: W, height: H } = useVideoConfig();
  if (!resolved) return <AbsoluteFill style={{ background: '#000' }} />;
  const portrait = H > W;
  const unit = (portrait ? W / 1080 : W / 1920) * (portrait ? 1.0 : 1.0); // all sizes are authored for 1080-wide / 1920-wide canvases
  const { brand, scenes, assets } = resolved;
  return (
    <AbsoluteFill style={{ background: brand.dark }}>
      {scenes.map((s: any, i: number) => (
        <Sequence key={i} from={s.startFrame} durationInFrames={s.durFrames} name={`${s.type}:${s.id || i}`}>
          {s.type === 'intro' ? <Intro s={s} brand={brand} portrait={portrait} unit={unit} />
            : s.type === 'outro' ? <Outro s={s} brand={brand} unit={unit} />
            : <ScreenSceneView s={s} asset={assets[s.src]} brand={brand} portrait={portrait} unit={unit} zoomBoost={resolved.portraitZoom} />}
        </Sequence>
      ))}
      {resolved.cues.length ? <Captions cues={resolved.cues} style={resolved.captionStyle} brand={brand} unit={unit} portrait={portrait} W={W} H={H} /> : null}
    </AbsoluteFill>
  );
};

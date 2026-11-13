import React from 'react';
import { Composition } from 'remotion';
import { Tutorial } from './Tutorial';
import { resolveStoryboard } from './lib/resolve';
import type { TutorialProps } from './lib/types';

const empty: TutorialProps = { storyboard: { meta: { title: 'empty' }, assets: {}, scenes: [] } };

const calc = async ({ props }: { props: TutorialProps }) => {
  const resolved = await resolveStoryboard(props.storyboard);
  return { durationInFrames: Math.max(1, resolved.totalFrames), fps: resolved.fps, props: { ...props, resolved } };
};

export const RemotionRoot: React.FC = () => (
  <>
    <Composition id="Tutorial-16x9" component={Tutorial as any} width={1920} height={1080} fps={30} durationInFrames={60} defaultProps={empty} calculateMetadata={calc as any} />
    <Composition id="Tutorial-9x16" component={Tutorial as any} width={1080} height={1920} fps={30} durationInFrames={60} defaultProps={empty} calculateMetadata={calc as any} />
  </>
);

// Storyboard schema (JSON) -> resolved props. A new video = a new storyboard JSON, no code.

export type Brand = { primary: string; accent: string; dark: string; text: string; font: string };

export type Focus = string | { x: number; y: number } | [number, number] | null | undefined; // marker rect name | source px | normalised 0..1

export type CameraKey = { at: number; zoom?: number; focus?: Focus; ease?: 'linear' | 'inOut' | 'out' };

export type Callout = {
  at: number; dur?: number; n?: number; text: string;
  target: Focus; side?: 'above' | 'below' | 'left' | 'right';
};

export type LowerThird = { at: number; dur?: number; title: string; subtitle?: string };

export type AssetRef = {
  video?: string; image?: string;       // path, relative to the storyboard file (or absolute)
  events?: string; markers?: string;    // outputs of capture.mjs
  size?: [number, number];              // logical CSS-px size of the source (default: markers.viewport, or 1440x900)
};

export type SceneBase = { id?: string; dur?: number; note?: string };
export type IntroScene = SceneBase & { type: 'intro'; title: string; subtitle?: string; badge?: string };
export type OutroScene = SceneBase & { type: 'outro'; title: string; subtitle?: string; cta?: string };
export type ScreenScene = SceneBase & {
  type: 'recording' | 'image';
  src: string;                          // key into storyboard.assets
  from?: number | string;               // recording: seconds or chapter id
  to?: number | string;
  speed?: number;
  camera?: CameraKey[];
  callouts?: Callout[];
  lowerThird?: LowerThird;
  cursor?: boolean;
};
export type Scene = IntroScene | OutroScene | ScreenScene;

export type Storyboard = {
  meta: { title: string; fps?: number; brand?: Partial<Brand>; portraitZoom?: number };
  assets: Record<string, AssetRef>;
  captions?: { srt: string; style?: 'bar' | 'plain' } | null;
  scenes: Scene[];
};

export type Cue = { start: number; end: number; text: string };
export type CursorEvt = { type: 'move' | 'down'; x: number; y: number; t: number };
export type Markers = { chapters: { id: string; t: number }[]; rects: Record<string, { x: number; y: number; w: number; h: number }>; viewport?: { width: number; height: number } };

export type ResolvedAsset = { video?: string; image?: string; size: [number, number]; events: CursorEvt[]; rects: Markers['rects']; chapters: Markers['chapters'] };
export type ResolvedScene = Scene & { startFrame: number; durFrames: number; fromSec?: number; speed?: number };
export type Resolved = {
  fps: number; brand: Brand; portraitZoom: number;
  assets: Record<string, ResolvedAsset>; scenes: ResolvedScene[];
  cues: Cue[]; captionStyle: 'bar' | 'plain'; totalFrames: number;
};
export type TutorialProps = { storyboard: Storyboard; resolved?: Resolved };

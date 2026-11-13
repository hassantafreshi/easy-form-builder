#!/usr/bin/env bash
# make_video.sh -- one storyboard JSON in, tutorial videos + SRT out:
#   site -> capture (Playwright) -> voice (ElevenLabs or cached file) -> fit -> render (Remotion) -> mux (ffmpeg) -> verify
#
#   make_video.sh --storyboard tools/video/storyboards/poc.json [options]
#
#   --name NAME            output basename (default: storyboard file name)
#   --out DIR              output root (default: $EFB_WORK/out/<name>/<UTC timestamp>)
#   --formats 16x9,9x16    which Remotion compositions to render (default both)
#   --stages LIST          subset of: site,capture,voice,fit,render,mux  (default all)
#   --reuse-capture        keep the last capture in $EFB_WORK/capture instead of re-recording
#   --voice-file MP3       use this narration audio (cached take) instead of calling the TTS API
#   --lead SECONDS         voice-over starts this far into the video (default 2 = intro length)
#   --music FILE           optional background bed (mixed at -24 dB)
#   --fresh-site           rebuild the WordPress site from scratch first (wp-env/reset.sh, ~10 s with warm cache)
#   --crf N                x264 quality for Remotion (default 18)
#
# Idempotency / unattended rules
#   * single instance: flock on $EFB_WORK/pipeline.lock
#   * every stage logs to <out>/logs/<stage>.log and records seconds in <out>/report.json
#   * capture: up to 2 attempts (browser crash / timeout), each bounded by `timeout`; render: 2 attempts
#   * voice: NEVER retried automatically (a retry is a second paid generation); cached by script hash when using the API
#   * finished outputs are written to a temp name and renamed, so a half-written MP4 never looks complete
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOLS="$(cd "$HERE/.." && pwd)"
source "$TOOLS/wp-env/env.sh"

SB=""; NAME=""; OUT=""; FORMATS="16x9,9x16"; STAGES="site,capture,voice,fit,render,mux"; REUSE=0; VOICE_FILE=""; LEAD=2; MUSIC=""; FRESH=0; CRF=18
while [ $# -gt 0 ]; do case "$1" in
  --storyboard) SB="$2"; shift 2;; --name) NAME="$2"; shift 2;; --out) OUT="$2"; shift 2;; --formats) FORMATS="$2"; shift 2;;
  --stages) STAGES="$2"; shift 2;; --reuse-capture) REUSE=1; shift;; --voice-file) VOICE_FILE="$2"; shift 2;; --lead) LEAD="$2"; shift 2;;
  --music) MUSIC="$2"; shift 2;; --fresh-site) FRESH=1; shift;; --crf) CRF="$2"; shift 2;;
  -h|--help) sed -n '2,28p' "$0"; exit 0;; *) echo "unknown arg $1" >&2; exit 2;; esac; done
[ -n "$SB" ] || { echo "--storyboard required" >&2; exit 2; }
SB="$(cd "$(dirname "$SB")" && pwd)/$(basename "$SB")"
NAME="${NAME:-$(basename "$SB" .json)}"
OUT="${OUT:-$EFB_WORK/out/$NAME/$(date -u +%Y%m%dT%H%M%SZ)}"
mkdir -p "$OUT/logs" "$EFB_WORK"
exec 9>"$EFB_WORK/pipeline.lock"; flock -n 9 || { echo "another pipeline run holds $EFB_WORK/pipeline.lock" >&2; exit 75; }

REPORT="$OUT/report.json"; echo '{"stages":{}}' > "$REPORT"
T_ALL=$(date +%s.%N)
has() { [[ ",$STAGES," == *",$1,"* ]]; }
record() { python3 - "$REPORT" "$1" "$2" "$3" <<'PY'
import json,sys
p,stage,sec,status=sys.argv[1:5]; d=json.load(open(p)); d['stages'][stage]={'seconds':round(float(sec),1),'status':status}; json.dump(d,open(p,'w'),indent=1)
PY
}
# run STAGE TIMEOUT_SECONDS ATTEMPTS cmd...
run() {
  local stage="$1" tmo="$2" tries="$3"; shift 3
  local t0 n=1 rc=0; t0=$(date +%s.%N)
  while :; do
    efb_log "[$stage] attempt $n/$tries"
    if timeout "$tmo" "$@" >>"$OUT/logs/$stage.log" 2>&1; then rc=0; break; else rc=$?; fi
    efb_log "[$stage] failed (exit $rc), see $OUT/logs/$stage.log"
    [ "$n" -ge "$tries" ] && break; n=$((n+1)); sleep $((5*n))
  done
  record "$stage" "$(echo "$(date +%s.%N) - $t0" | bc)" "$([ $rc = 0 ] && echo ok || echo "failed($rc)")"
  [ $rc = 0 ] || { efb_log "pipeline aborted at stage $stage"; exit $rc; }
}

WORKSB="$OUT/storyboard.json"; cp "$SB" "$WORKSB"
CAP="$EFB_WORK/capture"

# --- site ---------------------------------------------------------------------------------------------------------
if has site; then
  if [ "$FRESH" = 1 ] || [ ! -d "$WP_ROOT" ]; then run site 600 1 "$TOOLS/wp-env/reset.sh"; else run site 120 2 "$TOOLS/wp-env/server.sh" start; fi
fi
# --- capture ------------------------------------------------------------------------------------------------------
if has capture; then
  if [ "$REUSE" = 1 ] && [ -f "$CAP/video/flow.webm" ]; then efb_log "[capture] reusing $CAP"; record capture 0 reused
  else run capture 600 2 node "$TOOLS/capture/capture.mjs" --out "$CAP" --locales "${EFB_CAPTURE_LOCALES:-en_US}" --skip-addons; fi
fi
# --- voice --------------------------------------------------------------------------------------------------------
if has voice; then
  mkdir -p "$OUT/voice"
  if [ -n "$VOICE_FILE" ]; then
    cp "$VOICE_FILE" "$OUT/voice/voiceover.mp3"
    run voice 120 1 node "$TOOLS/voice/align.mjs" --audio "$OUT/voice/voiceover.mp3" --storyboard "$WORKSB" --out "$OUT/voice/timing.json"
  elif [ -n "${ELEVENLABS_API_KEY:-}" ]; then
    run voice 180 1 node "$TOOLS/voice/tts-elevenlabs.mjs" --storyboard "$WORKSB" --out-dir "$OUT/voice"
  else efb_log "[voice] no --voice-file and no ELEVENLABS_API_KEY: cannot continue"; record voice 0 "skipped-no-audio"; exit 3; fi
fi
# --- fit ----------------------------------------------------------------------------------------------------------
if has fit; then
  run fit 60 1 node "$TOOLS/voice/fit-storyboard.mjs" --storyboard "$WORKSB" --timing "$OUT/voice/timing.json" --out "$OUT/storyboard.fitted.json" --srt "$OUT/$NAME.en.srt" --lead "$LEAD"
fi
# --- render -------------------------------------------------------------------------------------------------------
if has render; then
  "$TOOLS/video/install.sh" >>"$OUT/logs/render.log" 2>&1
  run render 2400 2 node "$TOOLS/video/render.mjs" --storyboard "$OUT/storyboard.fitted.json" --out "$OUT/render" --name "$NAME" --formats "$FORMATS" --crf "$CRF"
fi
# --- mux ----------------------------------------------------------------------------------------------------------
if has mux; then
  for f in ${FORMATS//,/ }; do
    tmp="$OUT/.$NAME-$f.mp4.part"; final="$OUT/$NAME-$f.mp4"
    args=(--video "$OUT/render/$NAME-$f.mp4" --voice "$OUT/voice/voiceover.mp3" --out "$tmp" --lead "$LEAD"); [ -n "$MUSIC" ] && args+=(--music "$MUSIC")
    run "mux-$f" 300 1 "$TOOLS/assemble/mux.sh" "${args[@]}"
    mv "$tmp" "$final"; "$TOOLS/assemble/verify.sh" "$final" | tee -a "$OUT/logs/verify.log"
  done
fi
record total "$(echo "$(date +%s.%N) - $T_ALL" | bc)" ok
efb_log "done -> $OUT"; ls -1 "$OUT" | grep -E '\.(mp4|srt)$' >&2 || true

#!/usr/bin/env bash
# make-bed.sh out.wav [seconds] -- synthesise a soft, loop-safe background pad with ffmpeg (no third-party audio,
# so no licence question). Placeholder quality: replace with licensed music (see README) for real releases.
set -euo pipefail
OUT="${1:?out.wav}"; SEC="${2:-70}"
ffmpeg -hide_banner -loglevel error -y -f lavfi -i "aevalsrc='0.20*sin(2*PI*110*t)+0.14*sin(2*PI*164.81*t)+0.11*sin(2*PI*220*t)+0.08*sin(2*PI*261.63*t)+0.05*sin(2*PI*329.63*t)':s=48000:d=${SEC}" \
  -af "tremolo=f=0.12:d=0.35,lowpass=f=1400,highpass=f=70,afade=t=in:d=2,afade=t=out:st=$((SEC-3)):d=3,volume=0.8" "$OUT"
echo "[bed] wrote $OUT (${SEC}s synthetic pad)" >&2

#!/usr/bin/env bash
# mux.sh -- final assembly: Remotion video (silent H.264) + voice-over (+ optional music bed) -> MP4.
#
#   mux.sh --video in.mp4 --voice voice.mp3 --out final.mp4 [--lead 2.0] [--music bed.wav] [--music-db -24] [--lufs -14]
#
# * voice-over is delayed by --lead seconds (the intro is silent), music bed is trimmed to the video and set to --music-db
# * two-pass loudnorm to --lufs integrated (true peak -1.5 dBTP, LRA 11)
# * video stream is copied (no re-encode); audio AAC-LC 192 kb/s 48 kHz stereo; moov atom first (+faststart)
set -euo pipefail
LEAD=0; MUSIC=""; MUSIC_DB=-24; LUFS=-14; TP=-1.5
while [ $# -gt 0 ]; do case "$1" in
  --video) VIDEO="$2"; shift 2;; --voice) VOICE="$2"; shift 2;; --out) OUT="$2"; shift 2;;
  --lead) LEAD="$2"; shift 2;; --music) MUSIC="$2"; shift 2;; --music-db) MUSIC_DB="$2"; shift 2;; --lufs) LUFS="$2"; shift 2;;
  *) echo "unknown arg $1" >&2; exit 2;; esac; done
: "${VIDEO:?--video}" "${VOICE:?--voice}" "${OUT:?--out}"
DUR="$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$VIDEO")"
LEAD_MS="$(awk -v l="$LEAD" 'BEGIN{printf "%d", l*1000}')"

# audio graph: [voice delayed] (+ [music trimmed, attenuated]) -> mix -> pad/trim to video length
if [ -n "$MUSIC" ]; then
  INPUTS=(-i "$VOICE" -stream_loop -1 -i "$MUSIC")
  GRAPH="[@V@]adelay=${LEAD_MS}:all=1,aresample=48000,aformat=channel_layouts=stereo[v];[@M@]aresample=48000,aformat=channel_layouts=stereo,volume=${MUSIC_DB}dB,atrim=0:${DUR},afade=t=in:d=1.5,afade=t=out:st=$(awk -v d="$DUR" 'BEGIN{printf "%.2f", d-2}'):d=2[m];[v][m]amix=inputs=2:duration=first:normalize=0,apad"
else
  INPUTS=(-i "$VOICE")
  GRAPH="[@V@]adelay=${LEAD_MS}:all=1,aresample=48000,aformat=channel_layouts=stereo,apad"
fi
# stream indexes: pass 1 has no video input (voice=0, music=1); pass 2 has the video first (voice=1, music=2)
G1="${GRAPH//@V@/0:a}"; G1="${G1//@M@/1:a}"
G2="${GRAPH//@V@/1:a}"; G2="${G2//@M@/2:a}"

# pass 1: measure
MEAS="$(ffmpeg -hide_banner -nostats -t "$DUR" "${INPUTS[@]}" -filter_complex "${G1},atrim=0:${DUR},loudnorm=I=${LUFS}:TP=${TP}:LRA=11:print_format=json" -f null - 2>&1 | sed -n '/^{/,/^}/p')"
j() { printf '%s' "$MEAS" | sed -n "s/.*\"$1\" *: *\"\([^\"]*\)\".*/\1/p"; }
M_I="$(j input_i)"; M_TP="$(j input_tp)"; M_LRA="$(j input_lra)"; M_TH="$(j input_thresh)"; M_OFF="$(j target_offset)"
echo "[mux] measured: I=${M_I} LUFS  TP=${M_TP} dBTP  LRA=${M_LRA}  -> target ${LUFS} LUFS" >&2

# pass 2: apply (linear) and mux
ffmpeg -hide_banner -loglevel error -y -i "$VIDEO" "${INPUTS[@]}" \
  -filter_complex "${G2},atrim=0:${DUR},loudnorm=I=${LUFS}:TP=${TP}:LRA=11:measured_I=${M_I}:measured_TP=${M_TP}:measured_LRA=${M_LRA}:measured_thresh=${M_TH}:offset=${M_OFF}:linear=true:print_format=summary[a]" \
  -map 0:v:0 -map "[a]" -c:v copy -c:a aac -b:a 192k -ar 48000 -ac 2 -t "$DUR" -movflags +faststart "$OUT"
echo "[mux] wrote $OUT" >&2

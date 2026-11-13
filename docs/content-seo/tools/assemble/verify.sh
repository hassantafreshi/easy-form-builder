#!/usr/bin/env bash
# verify.sh file.mp4 -- check the deliverable: H.264 + AAC, yuv420p, faststart, integrated loudness, A/V duration match.
set -euo pipefail
F="$1"
v() { ffprobe -v error -select_streams "$1" -show_entries "stream=$2" -of default=noprint_wrappers=1:nokey=1 "$F"; }
VC="$(v v:0 codec_name)"; PF="$(v v:0 pix_fmt)"; W="$(v v:0 width)"; H="$(v v:0 height)"; FPS="$(v v:0 r_frame_rate)"; PROF="$(v v:0 profile)"
AC="$(v a:0 codec_name)"; SR="$(v a:0 sample_rate)"; CH="$(v a:0 channels)"
VD="$(v v:0 duration)"; AD="$(v a:0 duration)"; FD="$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$F")"
# faststart = 'moov' box appears before 'mdat' among the top-level boxes
FS="$(python3 - "$F" <<'PY'
import sys,struct
f=open(sys.argv[1],'rb'); pos=0; order=[]
f.seek(0,2); size=f.tell()
while pos<size and len(order)<12:
    f.seek(pos); h=f.read(8)
    if len(h)<8: break
    l,t=struct.unpack('>I4s',h); t=t.decode('latin1')
    if l==1: l=struct.unpack('>Q',f.read(8))[0]
    order.append(t); pos+= l if l else size
print('yes' if 'moov' in order and 'mdat' in order and order.index('moov')<order.index('mdat') else 'NO', '('+','.join(order)+')')
PY
)"
LOUD="$(ffmpeg -hide_banner -nostats -i "$F" -af ebur128=peak=true -f null - 2>&1 | awk '/Summary:/{s=1} s&&/I:/{i=$2} s&&/Peak:/{p=$2} END{print i" LUFS, true peak "p" dBFS"}')"
echo "file      : $F ($(du -h "$F" | cut -f1))"
echo "video     : $VC $PROF $PF (range ${RANGE:-unset}) ${W}x${H} @ $FPS, ${VD}s"
echo "audio     : $AC ${SR}Hz ${CH}ch, ${AD}s"
echo "container : ${FD}s, faststart=$FS"
echo "loudness  : $LOUD"
RANGE="$(v v:0 color_range)"
ok=1; [ "$VC" = h264 ] || ok=0; [ "$PF" = yuv420p ] || ok=0; [ "$AC" = aac ] || ok=0; case "$FS" in yes*) ;; *) ok=0;; esac
if [ $ok = 1 ]; then echo "RESULT    : PASS"; else echo "RESULT    : FAIL"; exit 1; fi

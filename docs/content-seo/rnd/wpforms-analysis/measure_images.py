# Pixel size, ratio, bytes, frames and dominant colours of downloaded WPForms images. Usage: python3 -W ignore -I measure_images.py WORKDIR
# Needs WORKDIR/selection2.json (list of {local, src, page}) and the files under WORKDIR/img/. Writes WORKDIR/measure.json.
import json,os,sys,collections,colorsys
from PIL import Image
base=sys.argv[1]
items=json.load(open(base+'/selection2.json'))
rows=[]
def hexc(c): return '#%02x%02x%02x'%c[:3]
for x in [i for i in items if 'local' in i]:
    p=base+'/'+x['local']
    if not os.path.exists(p): continue
    try: im=Image.open(p)
    except Exception as e: print('ERR',p,e); continue
    fmt=im.format; w,h=im.size; n=getattr(im,'n_frames',1)
    size=os.path.getsize(p)
    imr=im.convert('RGB')
    # downscale for color analysis
    sm=imr.copy(); sm.thumbnail((400,400))
    px=list(sm.getdata())
    # background: mean of corners
    bg=collections.Counter(px).most_common(1)[0][0]
    lum=lambda c:(0.299*c[0]+0.587*c[1]+0.114*c[2])
    # accent: saturated, non-grey pixels clusters (quantized)
    q=collections.Counter()
    for c in px:
        r,g,b=c; mx,mn=max(c),min(c)
        if mx-mn>90 and mx>120:
            q[(r//24*24,g//24*24,b//24*24)]+=1
    acc=[(hexc(k),v) for k,v in q.most_common(4)]
    # top rows brightness variation => chrome?
    top=imr.crop((0,0,w,min(40,h)))
    topcols=len(set(top.resize((w//4 or 1,10)).getdata()))
    # transparency
    alpha = im.mode in ('RGBA','LA','P') and 'transparency' in im.info or im.mode=='RGBA'
    rows.append(dict(file=x['local'].split('/')[-1][:60],page=x['page'][:34],fmt=fmt,w=w,h=h,ar=round(w/h,2),kb=round(size/1024,1),frames=n,mode=im.mode,bg=hexc(bg),bg_lum=round(lum(bg)),accents=acc[:3],year=x['src'].split('/uploads/')[1][:4]))
json.dump(rows,open(base+'/measure.json','w'),indent=1)
for r in rows: print(r['file'][:44].ljust(44),r['fmt'],f"{r['w']}x{r['h']}",r['ar'],f"{r['kb']}KB",'fr',r['frames'],r['bg'],r['accents'][:2],r['year'])

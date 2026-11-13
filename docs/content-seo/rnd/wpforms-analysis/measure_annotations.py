# Finds red annotation marks (R>200, G<70, B<70), counts connected components and estimates colour/stroke. Needs numpy and scipy.
# Usage: python3 -W ignore -I measure_annotations.py WORKDIR   (same inputs as measure_images.py; writes WORKDIR/annot.json)
import json,os,sys,collections
from PIL import Image
import numpy as np
from scipy import ndimage
base=sys.argv[1]
items=[i for i in json.load(open(base+'/selection2.json')) if 'local' in i and not i['local'].endswith('.gif')]
res=[]
for x in items:
    im=Image.open(base+'/'+x['local']).convert('RGB'); a=np.asarray(im).astype(int)
    R,G,B=a[...,0],a[...,1],a[...,2]
    red=(R>200)&(G<70)&(B<70)
    n=int(red.sum())
    lab,k=ndimage.label(red)
    sizes=ndimage.sum(red,lab,range(1,k+1)) if k else []
    big=[s for s in sizes if s>150]
    # stroke width estimate via distance transform of largest comp
    sw=None; col=None; bbox=None
    if big:
        j=int(np.argmax(sizes))+1
        m=lab==j
        dt=ndimage.distance_transform_edt(m)
        sw=round(float(np.percentile(dt[m],90)*2),1)
        ys,xs=np.where(m); bbox=(int(xs.min()),int(ys.min()),int(xs.max()),int(ys.max()))
        c=a[m]; col='#%02x%02x%02x'%tuple(int(v) for v in np.median(c,axis=0))
        # is it a rectangle? fill ratio of bbox
        bw,bh=bbox[2]-bbox[0]+1,bbox[3]-bbox[1]+1
        fill=float(m.sum())/(bw*bh)
    else: fill=None
    res.append(dict(file=x['local'].split('/')[-1][:55],w=im.width,h=im.height,red_px=n,red_pct=round(100*n/(im.width*im.height),3),comps=len(big),sw=sw,col=col,fill=round(fill,2) if fill else None,bbox=bbox))
json.dump(res,open(base+'/annot.json','w'),indent=1)
for r in res: print(r['file'].ljust(55),r['w'],r['red_pct'],'comps',r['comps'],'sw',r['sw'],r['col'],'fill',r['fill'])
has=[r for r in res if r['comps']>0]
print('images with red annotation comps:',len(has),'of',len(res))
print('stroke widths (90th pct of dt*2):',sorted(r['sw'] for r in has if r['sw']))
print('colours:',collections.Counter(r['col'] for r in has).most_common(8))

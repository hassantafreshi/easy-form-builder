import sys,re,json,urllib.request,html as H
url=sys.argv[1]
req=urllib.request.Request(url+("&" if "?" in url else "?")+"nocache=r47",headers={"User-Agent":"Mozilla/5.0 efb-verify"})
x=urllib.request.urlopen(req,timeout=60).read().decode()
print("title:",H.unescape(re.search(r"<title>(.*?)</title>",x,re.S).group(1).strip()))
for n in ("description","og:description","og:image","og:title"):
    m=re.search(r'<meta (?:name|property)="%s" content="([^"]*)"'%n,x); print(n+":",m and H.unescape(m.group(1))[:170])
print("canonical:",re.findall(r'rel="canonical" href="([^"]+)"',x)); print("hreflang:",re.findall(r'hreflang="([^"]+)" href="([^"]+)"',x)[:4])
print("h1:",[H.unescape(re.sub('<[^>]+>','',h)).strip() for h in re.findall(r"<h1[^>]*>(.*?)</h1>",x,re.S)])
for b in re.findall(r'<script[^>]*application/ld\+json[^>]*>(.*?)</script>',x,re.S):
    j=json.loads(b); g=j.get('@graph',[j]) if isinstance(j,dict) else j
    for o in g:
        t=o.get('@type')
        if t in ('Article','BlogPosting','VideoObject','Person','BreadcrumbList'):
            keep={k:o[k] for k in ('headline','name','author','datePublished','dateModified','uploadDate','embedUrl','thumbnailUrl','url','image') if k in o}
            print(" LD",t,json.dumps(keep,ensure_ascii=False)[:420])
body=x
imgs=re.findall(r'<img[^>]+src="([^"]+stripe-payment-form-wordpress[^"]+)"[^>]*>',body); print("step imgs:",len(set(imgs)))
print("alts missing:",len([i for i in re.findall(r'<img[^>]*wp-image-\d+[^>]*>',body) if 'alt=""' in i]))
print("youtube new:",body.count("AkQqXhGgffs")>0,"old video:",body.count("tCTeO1-FAi8"))
t=H.unescape(re.sub(r'<[^>]+>',' ',body))
print("pro-claims:",re.findall(r'[^.]{0,60}(?:part of the Pro|Pro feature|nur in der Pro|النسخة Pro)[^.]{0,40}',t)[:3])
print("author box:",re.findall(r'Hassan Tafreshi',x)[:1], "| admin byline:", len(re.findall(r'>\s*admin\s*<',x)))

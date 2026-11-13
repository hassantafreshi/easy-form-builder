# Per-page anatomy metrics for the WPForms sample. Usage: python3 -I analyze_pages.py WORKDIR
# Reads WORKDIR/html/doc_*.html and blog_*.html (downloaded with curl, untrusted), writes WORKDIR/analysis.json and prints a summary.
# Heuristics: body = from <div class="entry-content"> to the entry footer; words/links/images counted in the body only.
import re,sys,html,json,os,glob
from urllib.parse import urlparse
BASE=sys.argv[1]
def txt(s): return html.unescape(re.sub(r'\s+',' ',re.sub(r'<[^>]+>',' ',s))).strip()
def clean(b):
    b=re.sub(r'<noscript>.*?</noscript>','',b,flags=re.S)
    b=re.sub(r'<svg.*?</svg>','',b,flags=re.S)
    b=re.sub(r'<script.*?</script>','',b,flags=re.S)
    b=re.sub(r'<style.*?</style>','',b,flags=re.S)
    return b
rows=[]
for p in sorted(glob.glob(BASE+'/html/doc_*.html')+glob.glob(BASE+'/html/blog_*.html')):
    h=open(p,encoding='utf8').read()
    kind='doc' if '/doc_' in p else 'blog'
    r={'file':os.path.basename(p),'kind':kind}
    r['title_tag']=html.unescape(re.search(r'<title>(.*?)</title>',h,re.S).group(1).strip())
    m=re.search(r'<meta name="description" content="(.*?)"',h,re.S)
    r['meta']=html.unescape(m.group(1)) if m else ''
    r['h1']=txt(re.search(r'<h1[^>]*>(.*?)</h1>',h,re.S).group(1))
    r['pub']=(re.search(r'article:published_time" content="([^"]+)"',h) or [None,''])[1]
    r['mod']=(re.search(r'article:modified_time" content="([^"]+)"',h) or [None,''])[1]
    m=re.search(r'<span class="post-date">(.*?)</span>',h,re.S)
    r['date_display']=txt(m.group(1)) if m else ''
    m=re.search(r'class="last-modified">(.*?)</div>',h,re.S)
    r['last_modified_footer']=txt(m.group(1)) if m else ''
    m=re.search(r'twitter:data2" content="([^"]+)"',h)
    r['read_time']=m.group(1) if m else ''
    authors=re.findall(r'<span class="wpf-author-name" itemprop="name">(.*?)</span>',h)
    r['authors']=list(dict.fromkeys(authors))[:3]
    r['reviewed']= 'wpf-reviewer' in h
    r['disclosure']='post-disclosure-link' in h
    # content
    i=h.find('<div class="entry-content')
    ends=[x for x in (h.find('<div class="entry-footer',i),h.find('post-disclosure-section',i),h.find('</article>',i)) if x>0]
    j=min(ends) if ends else len(h)
    body=clean(h[i:j])
    # drop tool bar
    body=re.sub(r'<div class="entry-tools">.*?(?=<p class="wp-block-paragraph"|<p>|<div class="wp-block|<h2)','',body,count=1,flags=re.S)
    r['body_len']=len(body)
    r['words']=len(txt(body).split())
    # headings
    hs=re.findall(r'<(h[1-6])[^>]*>(.*?)</\1>',body,re.S)
    r['h2']=sum(1 for t,_ in hs if t=='h2'); r['h3']=sum(1 for t,_ in hs if t=='h3'); r['h4']=sum(1 for t,_ in hs if t=='h4'); r['h5']=sum(1 for t,_ in hs if t=='h5')
    r['headings']=[(t,txt(x)[:80]) for t,x in hs if txt(x) not in ('AI Summary',)]
    # step numbering
    r['step_headings']=sum(1 for t,x in hs if re.match(r'\s*(step\s*\d+|\d+[\.\):])',txt(x),re.I))
    r['ol']=len(re.findall(r'<ol',body)); r['ul']=len(re.findall(r'<ul',body))
    r['tables']=len(re.findall(r'<table',body))
    # images
    imgs=re.findall(r'<img [^>]*>',body)
    r['imgs']=len(imgs)
    r['pictures']=len(re.findall(r'<picture',body))
    r['gifs']=len([1 for x in imgs if re.search(r'\.gif',x) and 'data:image/gif' not in x.split('src=')[1][:30]]) 
    r['gif_urls']=re.findall(r'(?:data-src|src)="(https?://[^"]+\.gif)"',body)
    r['img_noalt']=sum(1 for x in imgs if not re.search(r'alt="[^"]+"',x))
    r['alts']=[html.unescape(re.search(r'alt="([^"]*)"',x).group(1)) for x in imgs if re.search(r'alt="',x)]
    r['figcaptions']=len(re.findall(r'<figcaption',body))
    r['lightbox']=len(re.findall(r'lightbox|data-fancybox|wp-lightbox',body))
    # callouts
    r['alertbox']=len(re.findall(r'wp-block-wpforms-site-blocks-alert-box',body))
    r['alert_classes']=sorted(set(re.findall(r'wp-block-wpforms-site-blocks-alert-box([^"]*)"',body)))
    callout_labels=re.findall(r'<strong>\s*((?:Note|Notes|Pro Tip|Pro tip|Tip|Tips|Important|Warning|Caution|Heads up|Heads-up|Reminder|Bonus|Quick Tip|Bonus Tip|Pro Tips|Note for developers|Developer Note|Troubleshooting|Requirements|Before you start|Before You Begin|Tip for developers)[^<]{0,25}?)</strong>',body,re.I)
    from collections import Counter
    r['callout_labels']=dict(Counter(x.strip().rstrip(':').lower() for x in callout_labels))
    # plan flags
    r['plan_mentions']={k:len(re.findall(k,txt(body))) for k in ['Pro','Plus','Elite','Basic','Lite','Agency','addon','Addon']}
    # first 100 words
    r['intro_first_p']=txt(re.search(r'<p[^>]*>(.*?)</p>',body,re.S).group(1)) if re.search(r'<p[^>]*>(.*?)</p>',body,re.S) else ''
    # intro length: words before first h2 (excluding toc)
    k=body.find('<h2')
    pre=body[:k] if k>0 else body
    pre_nt=re.sub(r'<div class="wp-block-aioseo-table-of-contents">.*?</ul></div>','',pre,flags=re.S)
    r['intro_words']=len(txt(pre_nt).split())
    r['toc']=bool(re.search(r'wp-block-aioseo-table-of-contents',body)) or bool(re.search(r'(?i)table of contents|in this article',body))
    r['toc_items']=len(re.findall(r'aioseo-toc-item',body))
    r['toc_pos_chars']=body.find('wp-block-aioseo-table-of-contents')
    # video
    r['yt']=len(re.findall(r'youtube\.com/embed|youtube-nocookie',body))
    r['video_tag']=len(re.findall(r'<video',body))
    r['video_before_first_h2']= bool(re.search(r'youtube\.com/embed|<video',pre))
    ytm=re.findall(r'title="([^"]*)"[^>]*data-src="https://www.youtube.com/embed/',body)
    r['yt_titles']=ytm
    # links
    links=re.findall(r'<a [^>]*href="([^"#][^"]*)"',body)
    internal=[l for l in links if 'wpforms.com' in l or l.startswith('/')]
    r['links_total']=len(links); r['links_internal']=len(internal); r['links_external']=len(links)-len(internal)
    r['links_docs']=len([l for l in internal if '/docs/' in l])
    r['links_per_100w']=round(100*len(internal)/max(1,r['words']),2)
    r['ext_domains']=sorted(set(urlparse(l).netloc for l in links if l not in internal and l.startswith('http')))[:8]
    # FAQ
    r['faq_heading']=[txt(x) for t,x in hs if re.search(r'(?i)frequently asked|faq|common questions',txt(x))]
    # related
    r['related_heading']=[txt(x) for t,x in hs if re.search(r'(?i)related|next steps|more examples|what.s next|see also|learn more|further reading',txt(x))]
    r['thats_it']=bool(re.search(r"That(?:’|&#8217;|')s it!",body+h[j:j+0])) or bool(re.search(r"That&#8217;s it!",body))
    r['next_cta']=bool(re.search(r'(?i)Next,\s*(would|are|do)',body))
    r['final_cta']=bool(re.search(r'(?i)(Ready to build|Create Your .{0,30} Form Now|Get Started with WPForms|get WPForms|Ready to)',txt(body[-3000:])))
    # jsonld
    lds=re.findall(r'<script type="application/ld\+json"[^>]*>(.*?)</script>',h,re.S)
    types=[]
    for l in lds:
        try:
            d=json.loads(l)
        except Exception as e:
            continue
        def walk(o):
            if isinstance(o,dict):
                t=o.get('@type')
                if t: types.extend(t if isinstance(t,list) else [t])
                for v in o.values(): walk(v)
            elif isinstance(o,list):
                for v in o: walk(v)
        walk(d)
    from collections import Counter
    r['jsonld_types']=dict(Counter(types))
    # TOC/anchors
    r['has_after_h1_summary']='wpf-ai-summarize' in h
    r['breadcrumb_html']='breadcrumb' in h.lower()
    r['wp_block_classes']=sorted(set(re.findall(r'class="(wp-block-[a-z\-]+)',body)))
    r['figure_classes']=dict(Counter(re.findall(r'<figure class="([^"]*)"',body)))
    r['code_blocks']=len(re.findall(r'<pre',body))
    r['kbd']=len(re.findall(r'<kbd|<code',body))
    rows.append(r)
json.dump(rows,open(BASE+'/analysis.json','w'),indent=1,ensure_ascii=False)
for r in rows:
    print(r['file'][:48].ljust(48),r['words'],'w','imgs',r['imgs'],'h2/3/4',r['h2'],r['h3'],r['h4'],'alert',r['alertbox'],'yt',r['yt'],'intro',r['intro_words'],'toc',r['toc_items'],'steps',r['step_headings'],'ln',r['links_internal'],r['links_per_100w'],'ld',list(r['jsonld_types']))

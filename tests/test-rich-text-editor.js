/**
 * F10-C: the client allow-list of the visitor's rich text (EFBRichCore in public/assets/js/rich-text-efb.js), used
 * by the editor (typing, paste) and by every display (efbRichSafeHtml in response-viewer-efb.js).
 *
 *  - the spec's vector: <p onclick=x>a</p><script>b</script><a href="javascript:x">c</a><span style="x">d</span>
 *    gives <p>a</p>cd (script content dropped, the javascript: link loses its tag, span and style gone);
 *  - hostile vectors: event attributes, style / class / id / data-*, svg / math / iframe / object / embed, img and
 *    srcset, entity-encoded and mixed-case javascript: / vbscript: / data: links, a split tag, an unclosed comment,
 *    an attribute with > inside, nested anchors, <a> with a quoteless href … never leave a tag or attribute out of
 *    the list (checked on every output: only <p> <br> <strong> <b> <em> <i> <u> <ul> <ol> <li> <blockquote> <h3>
 *    <h4> and <a href="http(s)|mailto">);
 *  - Word paste: o:p, MsoNormal classes, conditional comments, <style> and spans keep the words and lists;
 *  - display mode adds rel="nofollow ugc noopener" target="_blank" to links; text is escaped (< > & " ');
 *  - plain(): paragraphs, items and <br> as lines; entities read; plainLength() counts code points (Persian too);
 *  - efbRichSafeHtml without EFBRichCore falls back to escaped plain text.
 *
 * Run: node tests/test-rich-text-editor.js
 */
'use strict';
const path = require('path');
const fs = require('fs');
const vm = require('vm');
const C = require(path.join(__dirname, '..', 'public', 'assets', 'js', 'rich-text-efb.js'));
let pass = 0, fail = 0;
function t(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + e + '\n  actual   ' + a); }
}
/** Every tag of the output is on the list, with no attribute but an http(s) / mailto href (and rel / target in display mode). */
function onlyAllowed(html, display) {
  const tags = html.match(/<[^>]*>/g) || [];
  return tags.every((tag) => {
    if (/^<\/(p|strong|b|em|i|u|ul|ol|li|blockquote|h3|h4|a)>$/.test(tag)) return true;
    if (/^<(p|br|strong|b|em|i|u|ul|ol|li|blockquote|h3|h4)>$/.test(tag)) return true;
    const m = /^<a href="([^"]*)"( rel="nofollow ugc noopener" target="_blank")?>$/.exec(tag);
    return !!m && /^(https?:\/\/|mailto:)/i.test(m[1].replace(/&amp;/g, '&')) && (!!m[2] === !!display);
  });
}

t('the spec vector', C.clean('<p onclick=x>a</p><script>b</script><a href="javascript:x">c</a><span style="x">d</span>'), '<p>a</p>cd');
const hostile = [
  '<img src=x onerror=alert(1)>', '<svg><script>alert(1)</script></svg>', '<svg onload=alert(1)>', '<math><mi xlink:href="javascript:alert(1)">x</mi></math>',
  '<iframe src="javascript:alert(1)"></iframe>', '<object data="x.swf"></object><embed src=x>', '<a href="JaVaScRiPt:alert(1)">x</a>', '<a href="&#106;avascript:alert(1)">x</a>',
  '<a href="java\tscript:alert(1)">x</a>', '<a href=" javascript:alert(1)">x</a>', '<a href="vbscript:msgbox(1)">x</a>', '<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>',
  '<a href="https://ok.test" onclick="evil()">x</a>', '<a href=https://ok.test/path?a=1&b=2>q</a>', '<p style="background:url(javascript:alert(1))" class="c" id="i" data-x="1">s</p>',
  '<b>a<i>b</b>c</i>', '<a href="https://a.test"><a href="https://b.test">nested</a></a>', '<!-- <script>alert(1)</script> -->x', '<!-- unclosed <img src=x onerror=alert(1)>',
  '<p title="a>b" onmouseover="x">t</p>', '<scr<script>ipt>alert(1)</script>', '<<img src=x onerror=alert(1)>>', '<form><input onfocus=alert(1) autofocus></form>', '<details open ontoggle=alert(1)>',
  '<a href="mailto:a@b.test">mail</a><a href="//evil.test">rel</a>', '<style>p{color:red}</style><h1>T</h1><h5>U</h5><div>V</div>', '<template><img src=x onerror=1></template>z',
  '&lt;script&gt;alert(1)&lt;/script&gt;', '<p>a & b < c > d " e \' f</p>', '<li>loose</li><ul><li>one<li>two</ul>',
];
let allOk = true;
const bad = [];
hostile.forEach((h) => { const o1 = C.clean(h); const o2 = C.clean(h, { display: true }); if (!onlyAllowed(o1, false) || !onlyAllowed(o2, true) || /on[a-z]+=|javascript:|vbscript:|data:text/i.test(o1 + o2)) { allOk = false; bad.push([h, o1, o2]); } });
t(`no hostile vector leaves a tag or attribute out of the list (${hostile.length} vectors, editor and display)`, allOk, true);
if (!allOk) console.log(JSON.stringify(bad, null, 1));
t('script content is dropped, not kept as text', C.clean('<svg><script>alert(1)</script></svg>ok'), 'ok');
t('a link keeps an http(s) address (& kept as &amp;)', C.clean('<a href=https://ok.test/path?a=1&b=2>q</a>'), '<a href="https://ok.test/path?a=1&amp;b=2">q</a>');
t('display mode: rel="nofollow ugc noopener" target="_blank"', C.clean('<a href="https://ok.test">x</a>', { display: true }), '<a href="https://ok.test" rel="nofollow ugc noopener" target="_blank">x</a>');
t('a protocol-relative or encoded javascript: link loses its tag, the text stays', C.clean('<a href="//evil.test">r</a><a href="&#106;avascript:x">j</a>'), 'rj');
t('nested anchors: the inner one loses its tag', C.clean('<a href="https://a.test"><a href="https://b.test">n</a></a>'), '<a href="https://a.test">n</a>');
t('text is escaped', C.clean('<p>a & b < c > d " e \' f</p>'), '<p>a &amp; b &lt; c &gt; d &quot; e &#039; f</p>');
t('escaped markup stays text', C.clean('&lt;script&gt;alert(1)&lt;/script&gt;'), '&lt;script&gt;alert(1)&lt;/script&gt;');
t('headings map to h3 / h4, div to p, style content dropped', C.clean('<style>p{color:red}</style><h1>T</h1><h5>U</h5><div>V</div>'), '<h3>T</h3><h4>U</h4><p>V</p>');
t('misnested tags are closed in order', C.clean('<b>a<i>b</b>c</i>'), '<b>a<i>b</i></b>c');
const word = `<html xmlns:o="urn:schemas-microsoft-com:office:office"><head><style>@page{}</style></head><body><!--[if gte mso 9]><xml><o:OfficeDocumentSettings/></xml><![endif]-->
<p class=MsoNormal style='margin:0'><b><span style='font-size:14pt;font-family:"Arial"'>Title</span></b><o:p></o:p></p>
<p class=MsoListParagraph style='mso-list:l0'><span style='mso-list:Ignore'>·<span style='font:7pt'>&nbsp; </span></span>Item <i>one</i></p>
<ul><li class=MsoNormal>Bullet</li></ul></body></html>`;
const w = C.clean(word);
t('Word paste: the words and the list stay; o:p, classes, styles, conditional comments and <style> go', onlyAllowed(w, false) && /<b>Title<\/b>/.test(w) && /Item <i>one<\/i>/.test(w) && /<ul><li>Bullet<\/li><\/ul>/.test(w) && !/mso|Office|@page|xml/i.test(w), true);
t('plain(): paragraphs, items and <br> are lines; entities read', C.plain('<p>One &amp; two</p><ul><li>a</li><li>b</li></ul>x<br>y'), 'One & two\na\nb\nx\ny');
t('plainLength() counts code points (Persian, emoji)', C.plainLength('<b>سلام</b> 😀'), 6);
t('plainLength() of 11 digits in bold is 11 (mlen counts the text)', C.plainLength('<b>12345678901</b>'), 11);
t('an empty editor (<p><br></p>) cleans to an empty answer in plain text', C.plainLength(C.clean('<p><br></p>')), 0);
t('safeHref: http(s) / mailto only', [C.safeHref('https://a.test'), C.safeHref('mailto:x@y.test'), C.safeHref('ftp://a'), C.safeHref('javascript:x'), C.safeHref(' https://a.test')], ['https://a.test', 'mailto:x@y.test', '', '', 'https://a.test']);

// efbRichSafeHtml (response-viewer-efb.js): with and without the core.
const src = fs.readFileSync(path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js', 'response-viewer-efb.js'), 'utf8');
const fnSrc = src.slice(src.indexOf('function escHtmlEfb'), src.indexOf('function escResponseTreeEfb'));
const ctxNo = { window: {} };
vm.runInNewContext(fnSrc + '; this.f = efbRichSafeHtml;', ctxNo);
t('viewer without rich-text-efb.js: escaped plain text, lines kept', ctxNo.f('<p>a <img src=x onerror=alert(1)></p><p>b&amp;c</p>'), 'a<br>b&amp;c');
const ctxYes = { window: { EFBRichCore: C } };
vm.runInNewContext(fnSrc + '; this.f = efbRichSafeHtml;', ctxYes);
t('viewer with the core: the allow-list in display mode', ctxYes.f('<p onclick=x>a</p><a href="https://ok.test">l</a><script>z</script>'), '<p>a</p><a href="https://ok.test" rel="nofollow ugc noopener" target="_blank">l</a>');

console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);

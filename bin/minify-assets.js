#!/usr/bin/env node
/*
 * REL-04 / PERF-01: writes the minified twin of each asset next to it (x.js -> x.min.js, x.css -> x.min.css) in a
 * release folder, for _Public::asset_efb(), which serves the twin when it is there and not older than the source.
 * The sources stay in the zip (SCRIPT_DEBUG serves them, and they are the readable code).
 *
 *   node bin/minify-assets.js <tools node_modules> <plugin folder> <file relative to it>...
 *
 * terser (JS) and csso (CSS) are loaded from <tools node_modules> (bin/build-zip.sh installs pinned versions there,
 * outside the repository). JS: compress and mangle local names only (global functions keep their names: pages and
 * other scripts call them), licence comments kept, output checked by parsing it again. CSS: csso without
 * restructuring (rules keep their order and meaning). Prints one line per file and a total; exits 1 on any failure.
 */
'use strict';
const fs = require('fs');
const path = require('path');

const [tools, root, ...files] = process.argv.slice(2);
if (!tools || !root || !files.length) {
  console.error('usage: node bin/minify-assets.js <tools node_modules> <plugin folder> <file>...');
  process.exit(2);
}
const need = (name) => require(path.join(path.resolve(tools), name));
const terser = need('terser');
const csso = need('csso');

const twinOf = (rel) => rel.replace(/\.(js|css)$/, '.min.$1');

(async () => {
  let failed = 0;
  let before = 0;
  let after = 0;
  for (const rel of files) {
    const src = path.join(root, rel);
    const out = path.join(root, twinOf(rel));
    try {
      if (!/\.(js|css)$/.test(rel) || /\.min\.(js|css)$/.test(rel)) throw new Error('not a .js / .css source');
      const code = fs.readFileSync(src, 'utf8');
      let min;
      if (rel.endsWith('.js')) {
        const r = await terser.minify(code, {
          compress: { passes: 2 },
          mangle: true, // toplevel stays false: global names are part of the page's contract
          format: { comments: /^!|@license|@preserve/i },
          safari10: true,
        });
        if (!r || typeof r.code !== 'string' || r.code === '') throw new Error('terser returned nothing');
        min = r.code;
        new Function(min); // eslint-disable-line no-new-func -- a parse check of the output, never called
      } else {
        const r = csso.minify(code, { restructure: false, comments: 'exclamation' });
        min = r.css;
        if (code.trim() !== '' && min.trim() === '') throw new Error('csso returned nothing');
      }
      fs.writeFileSync(out, min.endsWith('\n') ? min : min + '\n');
      const a = Buffer.byteLength(code);
      const b = fs.statSync(out).size;
      before += a;
      after += b;
      console.log(`  ${twinOf(rel)}  ${a} -> ${b} bytes`);
    } catch (e) {
      failed++;
      console.error(`  FAIL ${rel}: ${e && e.message ? e.message : e}`);
    }
  }
  console.log(`minified ${files.length - failed} of ${files.length} assets: ${before} -> ${after} bytes`);
  process.exit(failed ? 1 : 0);
})();

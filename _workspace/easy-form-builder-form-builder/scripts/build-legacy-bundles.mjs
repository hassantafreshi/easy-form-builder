#!/usr/bin/env node
/**
 * Concatenates the legacy-snapshot files into dist/efb-form-builder.legacy.js
 * and dist/efb-form-builder.legacy.css, in the verified load order recorded
 * in dist/efb-form-builder.manifest.json (built by build-manifest.mjs - run
 * that first). This is a backup/comparison/rollback artifact, NOT a
 * replacement for the modular src/ tree, and NOT a build step the legacy
 * plugin itself needs - WordPress keeps loading the individual files from
 * their original plugin paths exactly as before.
 *
 * External dependencies these bundles do NOT include (loaded separately by
 * WordPress core / a CDN, per docs/CSS_SCOPE_REPORT.md and docs/SOURCE_MAP.md):
 * jQuery (WP core 'jquery' handle), wp-pointer (WP core), Google Fonts
 * Roboto (remote), countries-js (CDN unless the Offline add-on is active,
 * in which case vendor/offline/json/countries.js - see manifest entry).
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const manifest = JSON.parse(readFileSync(path.join(ROOT, 'dist', 'efb-form-builder.manifest.json'), 'utf8'));

function buildBundle(type, outFile, commentStyle) {
  const files = manifest.files
    .filter((f) => f.type === type && f.load_order !== null)
    .sort((a, b) => a.load_order - b.load_order);

  const parts = [
    commentStyle.open(`efb-form-builder.legacy.${type === 'js' ? 'js' : 'css'} - generated ${manifest.generated_at} from dev4@${manifest.source_commit}. DO NOT EDIT - regenerate via scripts/build-legacy-bundles.mjs. See dist/efb-form-builder.manifest.json for per-file metadata and docs/CSS_SCOPE_REPORT.md / docs/SOURCE_MAP.md for external (non-bundled) dependencies.`),
  ];
  for (const f of files) {
    const abs = path.join(ROOT, f.extracted_path);
    parts.push(commentStyle.open(`--- ${f.original_path} (load_order ${f.load_order}, sha256:${f.hash.replace('sha256:', '').slice(0, 12)}...) ---`));
    parts.push(readFileSync(abs, 'utf8'));
  }
  mkdirSync(path.dirname(outFile), { recursive: true });
  writeFileSync(outFile, parts.join('\n'));
  console.log(`Wrote ${outFile} (${files.length} files, ${parts.join('\n').length} bytes)`);
}

const jsComment = { open: (s) => `/* ${s} */` };
const cssComment = { open: (s) => `/* ${s} */` };

buildBundle('js', path.join(ROOT, 'dist', 'efb-form-builder.legacy.js'), jsComment);
buildBundle('css', path.join(ROOT, 'dist', 'efb-form-builder.legacy.css'), cssComment);

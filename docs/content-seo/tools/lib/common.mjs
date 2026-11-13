// Shared helpers for the Node tools (capture, pipeline). Plain ESM, no dependencies.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/**
 * Playwright is pre-installed globally in the cloud container (NODE_PATH) together with a matching Chromium.
 * The repo's own node_modules may hold a different Playwright whose browser build is NOT installed, so we pick
 * the first candidate whose Chromium executable actually exists.
 */
export function loadPlaywright() {
  const here = createRequire(import.meta.url);
  const tried = [];
  for (const spec of ['/usr/local/lib/node_modules_global/playwright', '/opt/node22/lib/node_modules/playwright', 'playwright']) {
    try {
      const pw = here(spec);
      const exe = pw.chromium.executablePath();
      if (exe && fs.existsSync(exe)) return pw;
      tried.push(`${spec}: browser missing (${exe})`);
    } catch (e) { tried.push(`${spec}: ${String(e.message).split('\n')[0]}`); }
  }
  throw new Error('no usable playwright+chromium found:\n  ' + tried.join('\n  '));
}

export const EFB_WORK = process.env.EFB_WORK || path.join(process.env.TMPDIR || os.tmpdir(), 'efb-tutorial');

/** Parse the `KEY=value` file written by wp-env/setup.sh (values are shell-quoted with printf %q). */
export function readAdminEnv() {
  const env = { WP_ADMIN_USER: process.env.WP_ADMIN_USER, WP_ADMIN_PASS: process.env.WP_ADMIN_PASS, WP_URL: process.env.WP_URL };
  const file = process.env.EFB_ADMIN_ENV || path.join(EFB_WORK, 'secrets', 'wp-admin.env');
  if ((!env.WP_ADMIN_USER || !env.WP_ADMIN_PASS) && fs.existsSync(file)) {
    for (const line of fs.readFileSync(file, 'utf8').split('\n')) {
      const m = line.match(/^([A-Z_]+)=(.*)$/);
      if (m && !env[m[1]]) env[m[1]] = m[2].replace(/^'(.*)'$/, '$1').replace(/\\(.)/g, '$1');
    }
  }
  if (!env.WP_ADMIN_USER || !env.WP_ADMIN_PASS) throw new Error(`no admin credentials (looked in ${file})`);
  env.WP_URL = env.WP_URL || `http://localhost:${process.env.WP_PORT || 8080}`;
  return env;
}

export function parseArgs(argv, defaults = {}) {
  const out = { ...defaults };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (!a.startsWith('--')) continue;
    const key = a.slice(2);
    const next = argv[i + 1];
    if (next === undefined || next.startsWith('--')) out[key] = true; else { out[key] = next; i++; }
  }
  return out;
}

/** Run fn up to `tries` times; the last error is rethrown. */
export async function withRetry(label, tries, fn) {
  let last;
  for (let i = 1; i <= tries; i++) {
    try { return await fn(i); } catch (e) { last = e; console.warn(`[retry] ${label} attempt ${i}/${tries} failed: ${String(e.message).split('\n')[0]}`); await sleep(800 * i); }
  }
  throw last;
}

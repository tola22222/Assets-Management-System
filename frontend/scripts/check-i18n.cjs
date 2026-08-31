#!/usr/bin/env node
/**
 * Fails if any t('...') key used in the app is missing from en.json or km.json,
 * or if the two locales have drifted apart.
 *
 * A key present in en.json but absent from km.json does not throw at runtime —
 * vue-i18n falls back to rendering the raw key, so a Khmer user sees the literal
 * string "stock.title" on the page. That is invisible in English testing, which
 * is why this runs in CI rather than relying on review.
 *
 * Usage:  npm run check:i18n
 */
const fs = require('fs');
const path = require('path');

const SRC = path.join(__dirname, '..', 'src');
const I18N = path.join(SRC, 'i18n');

function walk(dir, acc = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(p, acc);
    else if (/\.(vue|js)$/.test(entry.name) && !p.startsWith(I18N)) acc.push(p);
  }
  return acc;
}

const en = JSON.parse(fs.readFileSync(path.join(I18N, 'en.json'), 'utf8'));
const km = JSON.parse(fs.readFileSync(path.join(I18N, 'km.json'), 'utf8'));

const get = (obj, dotted) =>
  dotted.split('.').reduce((acc, k) => (acc && acc[k] !== undefined ? acc[k] : undefined), obj);

// The lookbehind keeps this from matching get(, post(, createElement( etc.
const KEY_RE = /(?<![A-Za-z0-9_$.])t\(\s*['"]([A-Za-z0-9_.]+)['"]\s*[,)]/g;

const used = new Map();
for (const file of walk(SRC)) {
  const contents = fs.readFileSync(file, 'utf8');
  for (const m of contents.matchAll(KEY_RE)) {
    if (!used.has(m[1])) used.set(m[1], new Set());
    used.get(m[1]).add(path.relative(path.join(__dirname, '..'), file).split(path.sep).join('/'));
  }
}

const problems = [];
for (const [key, files] of used) {
  const where = [...files].join(', ');
  const e = get(en, key);
  const k = get(km, key);
  if (e === undefined) problems.push(`missing from en.json: ${key}  (${where})`);
  else if (typeof e === 'object') problems.push(`resolves to an object, not a string: ${key}  (${where})`);
  if (k === undefined) problems.push(`missing from km.json: ${key}  (${where})`);
}

// Locale drift in either direction.
function leaves(obj, trail = [], acc = []) {
  for (const [k, v] of Object.entries(obj)) {
    if (v && typeof v === 'object' && !Array.isArray(v)) leaves(v, [...trail, k], acc);
    else acc.push([...trail, k].join('.'));
  }
  return acc;
}
const enLeaves = new Set(leaves(en));
const kmLeaves = new Set(leaves(km));
for (const k of enLeaves) if (!kmLeaves.has(k)) problems.push(`in en.json but not km.json: ${k}`);
for (const k of kmLeaves) if (!enLeaves.has(k)) problems.push(`in km.json but not en.json: ${k}`);

// Messages that vue-i18n cannot compile.
//
// '@' opens a linked message (@:key / @.lower:key). A bare '@' — an email
// address in a placeholder, say — throws "Invalid linked format" when the
// message is first rendered, which takes down the whole component's render,
// not just that one string. It is invisible until someone opens that exact
// page, so it is checked here. Write a literal '@' as {'@'}.
function messageProblems(obj, locale, trail = [], acc = []) {
  for (const [k, v] of Object.entries(obj)) {
    if (v && typeof v === 'object' && !Array.isArray(v)) {
      messageProblems(v, locale, [...trail, k], acc);
      continue;
    }
    if (typeof v !== 'string') continue;

    // Drop {'...'} literal blocks first — an escaped @ inside one is fine.
    const bare = v.replace(/\{\s*'[^']*'\s*\}/g, '');
    if (/@(?![:.])/.test(bare)) {
      acc.push(
        `unescaped "@" breaks vue-i18n in ${locale}.json: ${[...trail, k].join('.')} = ${JSON.stringify(v)}` +
          `  — write it as {'@'}`
      );
    }
  }
  return acc;
}
problems.push(...messageProblems(en, 'en'), ...messageProblems(km, 'km'));

if (problems.length) {
  console.error(`i18n check FAILED - ${problems.length} problem(s):\n`);
  problems.forEach((p) => console.error('  ' + p));
  console.error('\nAdd the missing key to BOTH src/i18n/en.json and src/i18n/km.json.');
  process.exit(1);
}

console.log(`i18n check passed - ${used.size} keys used, ${enLeaves.size} defined, en/km in sync.`);

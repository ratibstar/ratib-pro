'use strict';
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '..');
const cssPath = path.join(root, 'public/assets/vendor/fontawesome/6.5.2/css/all.min.css');
const iconsPath = path.join(__dirname, '_fa-icons-used.txt');
const outPath = path.join(root, 'public/assets/vendor/fontawesome/6.5.2/css/shell.min.css');

const css = fs.readFileSync(cssPath, 'utf8');
const icons = fs
  .readFileSync(iconsPath, 'utf8')
  .split(/\r?\n/)
  .map((s) => s.trim())
  .filter(Boolean);

const need = new Set(icons);
['fa-chevron-down', 'fa-bars', 'fa-xmark', 'fa-times', 'fa-sign-out-alt', 'fa-sign-out'].forEach((x) =>
  need.add(x)
);

// Alias map: PHP uses older names that FA still defines as aliases in compound selectors.
const alias = {
  'fa-cloud-upload-alt': 'fa-cloud-arrow-up',
  'fa-sign-out-alt': 'fa-right-from-bracket',
};

const rules = [];
const missing = [];
const seen = new Set();

function extractRuleFor(name) {
  const needle = '.' + name + ':before';
  let idx = css.indexOf(needle);
  if (idx < 0) return null;
  // Walk back to start of selector group (after previous } or start)
  let start = idx;
  while (start > 0 && css[start - 1] !== '}') start--;
  const end = css.indexOf('}', idx);
  if (end < 0) return null;
  return css.slice(start, end + 1);
}

for (const name of need) {
  let rule = extractRuleFor(name);
  if (!rule && alias[name]) rule = extractRuleFor(alias[name]);
  if (!rule) {
    missing.push(name);
    continue;
  }
  if (seen.has(rule)) continue;
  seen.add(rule);
  rules.push(rule);
}

const base =
  '@font-face{font-family:"Font Awesome 6 Free";font-style:normal;font-weight:900;font-display:swap;src:url("../webfonts/fa-solid-900.woff2") format("woff2")}' +
  '.fa,.fas,.fa-solid{-moz-osx-font-smoothing:grayscale;-webkit-font-smoothing:antialiased;display:inline-block;font-style:normal;font-variant:normal;line-height:1;text-rendering:auto;font-family:"Font Awesome 6 Free";font-weight:900}' +
  '.fa-fw{text-align:center;width:1.25em}';

const out = base + rules.join('');
fs.writeFileSync(outPath, out);
console.log(JSON.stringify({ icons: need.size, rules: rules.length, bytes: out.length, missing }, null, 2));

'use strict';
/**
 * Fix11 — FA glyph rules without @font-face.
 * Shell (shell.min.css) owns the solid @font-face with font-display:swap.
 * Loading all.min.css re-registered faces with font-display:block + brands/regular woff2.
 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '..');
const cssPath = path.join(root, 'public/assets/vendor/fontawesome/6.5.2/css/all.min.css');
const outPath = path.join(root, 'public/assets/vendor/fontawesome/6.5.2/css/glyphs.min.css');

let css = fs.readFileSync(cssPath, 'utf8');
while (css.includes('@font-face')) {
  const i = css.indexOf('@font-face');
  let depth = 0;
  let j = i;
  for (; j < css.length; j++) {
    if (css[j] === '{') depth++;
    else if (css[j] === '}') {
      depth--;
      if (depth === 0) {
        j++;
        break;
      }
    }
  }
  css = css.slice(0, i) + css.slice(j);
}
css = css.replace(/\/\*# sourceMappingURL=[\s\S]*?\*\//g, '').trim();
const header =
  '/* Fix11: glyph rules only — font faces live in shell.min.css (display:swap). */\n';
fs.writeFileSync(outPath, header + css);
console.log(
  JSON.stringify(
    {
      bytes: Buffer.byteLength(header + css),
      fontFaceLeft: (css.match(/@font-face/g) || []).length,
      hasWebfontsUrl: /webfonts\//.test(css),
    },
    null,
    2
  )
);

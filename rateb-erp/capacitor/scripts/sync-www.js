/**
 * Sync ERP public web assets into capacitor/www for native packaging.
 * Reuses Offline SDK + PWA shell; does not copy POS-only trees as the app root.
 */
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const erpPublic = path.resolve(root, '..', 'public');
const www = path.join(root, 'www');

function rmrf(dir) {
  if (fs.existsSync(dir)) {
    fs.rmSync(dir, { recursive: true, force: true });
  }
}

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function copyFile(src, dest) {
  ensureDir(path.dirname(dest));
  fs.copyFileSync(src, dest);
}

function copyDir(src, dest) {
  if (!fs.existsSync(src)) {
    return;
  }
  ensureDir(dest);
  for (const entry of fs.readdirSync(src, { withFileTypes: true })) {
    const from = path.join(src, entry.name);
    const to = path.join(dest, entry.name);
    if (entry.isDirectory()) {
      copyDir(from, to);
    } else {
      copyFile(from, to);
    }
  }
}

rmrf(www);
ensureDir(www);

const files = [
  'offline-shell.html',
  'manifest.webmanifest',
  'rateb-offline-sw.js',
  'favicon.ico',
];

for (const f of files) {
  const src = path.join(erpPublic, f);
  if (fs.existsSync(src)) {
    copyFile(src, path.join(www, f));
  }
}

copyDir(path.join(erpPublic, 'assets', 'offline'), path.join(www, 'assets', 'offline'));
copyDir(path.join(erpPublic, 'assets', 'pwa'), path.join(www, 'assets', 'pwa'));

const indexHtml = `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0f1117">
  <link rel="manifest" href="./manifest.webmanifest">
  <title>RATEB ERP</title>
  <style>
    body{margin:0;font-family:system-ui,sans-serif;background:#0f1117;color:#e8eaed;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:1.5rem}
    a{color:#8ab4ff}
  </style>
</head>
<body>
  <main>
    <h1>RATEB ERP</h1>
    <p>Native shell ready. Point <code>server.url</code> at your ERP origin for live sessions, or open the offline shell.</p>
    <p><a href="./offline-shell.html">Open offline shell</a></p>
  </main>
  <script src="./assets/offline/rateb-offline.js" defer></script>
  <script type="module">
    import { App } from 'https://cdn.jsdelivr.net/npm/@capacitor/app@6/+esm';
    import { Capacitor } from 'https://cdn.jsdelivr.net/npm/@capacitor/core@6/+esm';
    window.RatebCapacitor = { App, Capacitor };
    App.addListener('appUrlOpen', (event) => {
      window.dispatchEvent(new CustomEvent('rateb:deeplink', { detail: event }));
    });
  </script>
</body>
</html>
`;

fs.writeFileSync(path.join(www, 'index.html'), indexHtml, 'utf8');
console.log('Synced capacitor/www from rateb-erp/public (offline + PWA assets)');

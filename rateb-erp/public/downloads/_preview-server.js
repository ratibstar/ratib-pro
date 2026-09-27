const http = require('http');
const fs = require('fs');
const path = require('path');

const root = __dirname;
const port = 8765;
const apkName = 'rateb-erp-android-release.apk';
const mime = {
  '.apk': 'application/vnd.android.package-archive',
  '.html': 'text/html; charset=utf-8',
};

const page = `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RATEB ERP Android</title>
  <style>
    body{font-family:system-ui,sans-serif;background:#0f1117;color:#e8eaed;padding:2rem;max-width:28rem;margin:auto}
    a{display:block;background:#3b82f6;color:#fff;text-align:center;padding:1rem;border-radius:12px;text-decoration:none;font-weight:700;margin:1rem 0}
    .alt{background:#1f2937}
    p{opacity:.85;line-height:1.7}
    small{opacity:.6}
    code{background:#1f2937;padding:.1rem .35rem;border-radius:4px}
  </style>
</head>
<body>
  <h1>RATEB ERP</h1>
  <p>إصدار <b>Release</b> موقّع · v1.0.1 (2) · ~5.1 MB</p>
  <a href="/${apkName}">تحميل وتثبيت APK</a>
  <p>1) اسمح بالتثبيت من هذا المصدر<br>
     2) افتح تطبيق <b>RATEB ERP</b><br>
     3) يفتح شاشة دخول الإنتاج</p>
  <a class="alt" href="https://rateb.sa/rateb-erp/public/admin">أو افتح Admin من المتصفح</a>
  <p><small>الجوال على نفس الواي فاي مع الكمبيوتر</small></p>
</body>
</html>`;

http
  .createServer((req, res) => {
    const urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
    if (urlPath === '/' || urlPath === '/index.html') {
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      return res.end(page);
    }
    const file = path.normalize(path.join(root, urlPath));
    if (!file.startsWith(root) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) {
      res.writeHead(404);
      return res.end('not found');
    }
    const ext = path.extname(file);
    res.writeHead(200, {
      'Content-Type': mime[ext] || 'application/octet-stream',
      'Content-Length': fs.statSync(file).size,
    });
    fs.createReadStream(file).pipe(res);
  })
  .listen(port, '0.0.0.0', () => {
    console.log('READY http://0.0.0.0:' + port);
  });

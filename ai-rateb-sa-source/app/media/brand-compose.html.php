<?php
declare(strict_types=1);
/**
 * Programmatic brand overlay for RATEB AI ads.
 * Uses the official site wordmark (RATEB + gradient AI) and Tajawal for Arabic RTL shaping.
 * Query: ?bg=<https-url>&w=768&h=1344&headline=...&brand_ar=...
 */
$bg = (string) ($_GET['bg'] ?? '');
$w = max(512, min(2048, (int) ($_GET['w'] ?? 768)));
$h = max(512, min(2048, (int) ($_GET['h'] ?? 1344)));
$headline = trim((string) ($_GET['headline'] ?? 'من الفكرة إلى المحتوى بالذكاء الاصطناعي'));
$brandAr = trim((string) ($_GET['brand_ar'] ?? 'رَتِّب AI'));
if ($headline === '') {
    $headline = 'من الفكرة إلى المحتوى بالذكاء الاصطناعي';
}
if ($brandAr === '') {
    $brandAr = 'رَتِّب AI';
}
if ($bg === '' || (!str_starts_with($bg, 'http://') && !str_starts_with($bg, 'https://') && !str_starts_with($bg, 'file:') && !str_starts_with($bg, 'data:'))) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'bg required';
    exit;
}
$bgEsc = htmlspecialchars($bg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$headlineEsc = htmlspecialchars($headline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$brandArEsc = htmlspecialchars($brandAr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>RATEB AI brand compose</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@500;700;800&family=Manrope:wght@700;800&display=swap">
<style>
  html, body { margin: 0; padding: 0; background: #05080f; width: <?= (int) $w ?>px; height: <?= (int) $h ?>px; overflow: hidden; }
  .stage {
    position: relative;
    width: <?= (int) $w ?>px;
    height: <?= (int) $h ?>px;
    overflow: hidden;
    background: #05080f;
  }
  .bg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .veil-top {
    position: absolute; left: 0; right: 0; top: 0; height: 34%;
    background: linear-gradient(180deg, rgba(5,12,28,.88) 0%, rgba(5,12,28,.42) 58%, rgba(5,12,28,0) 100%);
    pointer-events: none;
  }
  .veil-bottom {
    position: absolute; left: 0; right: 0; bottom: 0; height: 28%;
    background: linear-gradient(0deg, rgba(5,12,28,.9) 0%, rgba(5,12,28,.35) 62%, rgba(5,12,28,0) 100%);
    pointer-events: none;
  }
  .brand {
    position: absolute; top: 5%; left: 6%; right: 6%;
    text-align: center; z-index: 2;
  }
  /* Official RATEB AI site wordmark (matches app/nav.php + studio logo CSS). */
  .logo-mark {
    margin: 0;
    font-family: Manrope, Tajawal, system-ui, sans-serif;
    font-weight: 800;
    font-size: <?= (int) round($w * 0.092) ?>px;
    letter-spacing: -0.02em;
    line-height: 1;
    color: #f8fbff;
    text-decoration: none;
    white-space: nowrap;
  }
  .logo-mark span {
    background: linear-gradient(90deg, #c4b5fd, #5eead4);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
  }
  .brand-ar {
    margin: 12px 0 0;
    font-family: Tajawal, "Segoe UI", Tahoma, sans-serif;
    font-weight: 800;
    font-size: <?= (int) round($w * 0.072) ?>px;
    line-height: 1.15;
    color: #ecfeff;
    text-shadow: 0 2px 18px rgba(0, 180, 216, .35), 0 1px 2px rgba(0,0,0,.55);
  }
  .brand-en {
    margin: 8px 0 0;
    font-family: Manrope, Tajawal, sans-serif;
    font-weight: 700;
    font-size: <?= (int) round($w * 0.036) ?>px;
    letter-spacing: 0.16em;
    color: #7ee7f2;
    text-shadow: 0 1px 10px rgba(0,0,0,.45);
  }
  .headline {
    position: absolute; top: 24%; left: 7%; right: 7%;
    text-align: center; z-index: 2;
    margin: 0;
    padding: 14px 16px;
    border-radius: 18px;
    border: 1px solid rgba(94, 234, 212, .35);
    background: rgba(8, 28, 48, .42);
    font-family: Tajawal, "Segoe UI", Tahoma, sans-serif;
    font-weight: 700;
    font-size: <?= (int) round($w * 0.048) ?>px;
    line-height: 1.5;
    color: #eef8fc;
    text-shadow: 0 2px 14px rgba(0,0,0,.55);
  }
  .accent-bar {
    position: absolute; left: 18%; right: 18%; top: 21.5%; height: 3px; z-index: 2;
    border-radius: 999px;
    background: linear-gradient(90deg, #6d28d9, #0f766e, #22d3ee);
    opacity: .9;
  }
</style>
</head>
<body>
  <div class="stage" id="stage">
    <img class="bg" src="<?= $bgEsc ?>" alt="">
    <div class="veil-top"></div>
    <div class="veil-bottom"></div>
    <div class="brand">
      <p class="logo-mark">RATEB <span>AI</span></p>
      <p class="brand-ar"><?= $brandArEsc ?></p>
      <p class="brand-en">RATEB AI</p>
    </div>
    <div class="accent-bar" aria-hidden="true"></div>
    <p class="headline"><?= $headlineEsc ?></p>
  </div>
</body>
</html>

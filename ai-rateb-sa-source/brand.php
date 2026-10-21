<?php
declare(strict_types=1);

require_once __DIR__ . '/app/brand/library.php';
require_once __DIR__ . '/app/nav.php';
$brand = brand_owned();
$error = (string) ($_GET['brand_error'] ?? '');
if (!in_array($error, ['size', 'type', 'upload'], true)) {
    $error = '';
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — Brand kit</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=nav42">
</head>
<body class="studio page-brand">
<?php rateb_site_header('/brand.php', !empty($_SESSION['user_id'])); ?>
<main class="wrap">
  <section class="panel">
    <span class="eyebrow" data-i18n="brand_kit">BRAND KIT</span>
    <h1 data-i18n="brand_kit_title">Brand kit</h1>
    <p class="muted" data-i18n="brand_lead">These details are optional context for future campaign copy.</p>
    <?php if ($error !== ''): ?><p class="alert" data-i18n="media_error_<?= e($error) ?>">The file could not be saved.</p><?php endif; ?>
    <?php if (($_GET['saved'] ?? '') === '1'): ?><p class="muted" data-i18n="brand_saved">Brand kit saved.</p><?php endif; ?>
    <form method="post" action="/app/brand/save.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="grid two">
        <label><span data-i18n="brand_name">Brand name</span><input name="brand_name" value="<?= e($brand['brand_name']) ?>"></label>
        <label><span data-i18n="brand_tone">Tone of voice</span><input name="tone" value="<?= e($brand['tone']) ?>"></label>
        <label><span data-i18n="brand_contact">Contact information</span><input name="contact" value="<?= e($brand['contact']) ?>"></label>
        <label><span data-i18n="brand_language">Preferred language</span>
          <select name="preferred_language">
            <option value="en" <?= ($brand['preferred_language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
            <option value="ar" <?= ($brand['preferred_language'] ?? '') === 'ar' ? 'selected' : '' ?>>العربية</option>
          </select>
        </label>
        <label><span data-i18n="brand_primary">Primary color</span><input name="primary_color" value="<?= e($brand['primary_color']) ?>"></label>
        <label><span data-i18n="brand_secondary">Secondary color</span><input name="secondary_color" value="<?= e($brand['secondary_color']) ?>"></label>
        <label><span data-i18n="brand_logo">Logo</span><input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif"></label>
      </div>
      <?php if (!empty($brand['logo_stored'])): ?>
        <p><img class="brand-logo" src="/app/brand/logo.php" alt=""></p>
      <?php endif; ?>
      <button class="primary" type="submit" data-i18n="save_brand">Save brand kit</button>
    </form>
  </section>
</main>
<?php rateb_auth_dialog('/brand.php'); ?>
<script src="/public/assets/js/app.js?v=nav40"></script>
</body>
</html>

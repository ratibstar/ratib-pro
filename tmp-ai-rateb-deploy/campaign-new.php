<?php
require_once __DIR__ . '/app/bootstrap.php';
require_login();
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — New Campaign</title>
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body>
<header class="top">
  <a class="brand" href="/dashboard.php">RATEB AI</a>
  <nav>
    <a href="/dashboard.php" data-i18n="dashboard">Dashboard</a>
    <a href="/logout.php" data-i18n="logout">Logout</a>
    <button type="button" id="lang" class="icon">عربي</button>
    <button type="button" id="theme" class="icon">☾</button>
  </nav>
</header>
<main class="wrap">
  <section class="panel">
    <span class="eyebrow" data-i18n="new_campaign_eyebrow">NEW CAMPAIGN</span>
    <h1 data-i18n="campaign_form_title">Campaign details</h1>
    <form method="post" action="/campaign-save.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="grid two">
        <label><span data-i18n="campaign_title">Campaign title *</span><input name="title" required></label>
        <label><span data-i18n="product_required">Product / Service *</span><input name="product_name" required></label>
        <label><span data-i18n="description_required">Description *</span><textarea name="description" required></textarea></label>
        <label><span data-i18n="price">Price</span><input name="price"></label>
        <label><span data-i18n="target_required">Target customer *</span><input name="target_customer" required></label>
      </div>
      <button class="primary" type="submit" data-i18n="save_campaign">Save Campaign</button>
    </form>
  </section>
</main>
<script src="/public/assets/js/app.js"></script>
</body>
</html>

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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=ink1">
</head>
<body class="studio page-new">
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
    <p class="muted" data-i18n="form_lead">Add the campaign details. You can generate content after saving.</p>
    <form method="post" action="/campaign-save.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <fieldset class="form-section">
        <legend data-i18n="section_basics">Basics</legend>
        <div class="grid two">
          <label><span data-i18n="campaign_title">Campaign title *</span><input name="title" required></label>
          <label><span data-i18n="product_required">Product / Service *</span><input name="product_name" required></label>
          <label class="wide"><span data-i18n="description_required">Description *</span><textarea name="description" required></textarea></label>
        </div>
      </fieldset>
      <fieldset class="form-section">
        <legend data-i18n="section_offer">Audience and offer</legend>
        <div class="grid two">
          <label><span data-i18n="target_required">Target customer *</span><input name="target_customer" required></label>
          <label><span data-i18n="objective">Objective</span><input name="objective"></label>
          <label><span data-i18n="price">Price</span><input name="price"></label>
          <label><span data-i18n="budget">Budget</span><input name="budget"></label>
        </div>
      </fieldset>
      <fieldset class="form-section">
        <legend data-i18n="section_schedule">Schedule</legend>
        <div class="grid two">
          <label><span data-i18n="start_date">Start date</span><input type="date" name="start_date"></label>
          <label><span data-i18n="end_date">End date</span><input type="date" name="end_date"></label>
          <label><span data-i18n="col_status">Status</span>
            <select name="status">
              <option value="draft" selected data-i18n="status_draft">Draft</option>
              <option value="in_progress" data-i18n="status_in_progress">In Progress</option>
              <option value="ready" data-i18n="status_ready">Ready</option>
              <option value="completed" data-i18n="status_completed">Completed</option>
            </select>
          </label>
        </div>
      </fieldset>
      <button class="primary" type="submit" data-i18n="save_campaign">Save Campaign</button>
    </form>
  </section>
</main>
<script src="/public/assets/js/app.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/app/bootstrap.php';
require_login();
$id = (int) ($_GET['id'] ?? 0);
$s = db()->prepare('SELECT * FROM campaigns WHERE id=? AND user_id=?');
$s->execute([$id, $_SESSION['user_id']]);
$campaign = $s->fetch();
if (!$campaign) {
    http_response_code(404);
    exit('Campaign not found.');
}
$o = db()->prepare('SELECT * FROM campaign_outputs WHERE campaign_id=? ORDER BY id');
$o->execute([$id]);
$outputs = $o->fetchAll();
$knownTypes = [
    'strategy',
    'ad_copy',
    'social_posts',
    'whatsapp',
    'product_description',
    'video_ideas',
    'voiceover',
    'content_plan_7_days',
];
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — Campaign</title>
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body>
<header class="top">
  <a class="brand" href="/dashboard.php">RATEB AI</a>
  <nav>
    <a href="/dashboard.php" data-i18n="dashboard">Dashboard</a>
    <a href="/campaign-new.php" data-i18n="new_campaign">New Campaign</a>
    <a href="/logout.php" data-i18n="logout">Logout</a>
    <button type="button" id="lang" class="icon">عربي</button>
    <button type="button" id="theme" class="icon">☾</button>
  </nav>
</header>
<main class="wrap">
  <section class="panel">
    <span class="eyebrow" data-i18n="campaign_details">CAMPAIGN DETAILS</span>
    <h1><?= e($campaign['title']) ?></h1>
    <div class="grid two">
      <div><b data-i18n="product">Product / Service</b><p><?= e($campaign['product_name']) ?></p></div>
      <div><b data-i18n="price">Price</b><p><?= e($campaign['price']) ?></p></div>
      <div><b data-i18n="target">Target customer</b><p><?= e($campaign['target_customer']) ?></p></div>
      <div><b data-i18n="description">Description</b><p><?= nl2br(e($campaign['description'])) ?></p></div>
    </div>
    <form id="generateForm">
      <input type="hidden" name="campaign_id" value="<?= $id ?>">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="lang" id="gen-lang" value="en">
      <button class="primary" id="generate" type="submit" data-i18n="generate">Generate AI Campaign</button>
    </form>
    <p id="status" class="muted"></p>
  </section>
  <section class="panel">
    <h2 data-i18n="results">AI Results</h2>
    <?php if (!$outputs): ?>
      <p class="muted" data-i18n="no_results">No AI results yet. Generate the campaign above.</p>
    <?php else: ?>
      <?php foreach ($outputs as $r): ?>
        <?php
        $type = (string) $r['output_type'];
        $label = ucwords(str_replace('_', ' ', $type));
        ?>
        <article class="result">
          <h3<?php if (in_array($type, $knownTypes, true)): ?> data-i18n="type_<?= e($type) ?>"<?php endif; ?>><?= e($label) ?></h3>
          <div><?= nl2br(e($r['content'])) ?></div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>
<script src="/public/assets/js/app.js"></script>
</body>
</html>

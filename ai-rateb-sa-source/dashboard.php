<?php
require __DIR__ . '/config/bootstrap.php';
require_login();

$user = current_user($pdo);
if (!$user) {
    header('Location: /logout.php');
    exit;
}
$stmt = $pdo->prepare('SELECT id, title, product_name, status, created_at FROM campaigns WHERE user_id = ? ORDER BY id DESC LIMIT 20');
$stmt->execute([$user['id']]);
$campaigns = $stmt->fetchAll();
$covers = [];
if ($campaigns) {
    $ids = [];
    foreach ($campaigns as $row) {
        $ids[] = (int) $row['id'];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $coverStmt = $pdo->prepare('SELECT id, campaign_id FROM campaign_media WHERE user_id = ? AND kind = ? AND campaign_id IN (' . $marks . ') ORDER BY id DESC');
    $coverStmt->execute(array_merge([(int) $user['id'], 'image'], $ids));
    foreach ($coverStmt->fetchAll() as $row) {
        $campaignId = (int) $row['campaign_id'];
        if (!isset($covers[$campaignId])) {
            $covers[$campaignId] = (int) $row['id'];
        }
    }
}
$summary = ['draft' => 0, 'in_progress' => 0, 'ready' => 0, 'completed' => 0];
foreach ($campaigns as $row) {
    $key = (string) $row['status'];
    if (!isset($summary[$key])) {
        $key = 'draft';
    }
    $summary[$key]++;
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — Dashboard</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body class="studio">
<header class="topbar">
  <div class="brand">RATEB <span>AI</span></div>
  <nav>
    <a href="/dashboard.php" data-i18n="dashboard">Dashboard</a>
    <a href="/campaign-new.php" data-i18n="new_campaign">New Campaign</a>
    <a href="/brand.php" data-i18n="brand_kit">Brand kit</a>
    <a href="/logout.php" data-i18n="logout">Logout</a>
    <button type="button" id="lang" class="icon">عربي</button>
    <button type="button" id="theme" class="icon">☾</button>
  </nav>
</header>
<main class="container studio-page">
  <section class="studio-hero">
    <div class="studio-hero-copy">
      <p class="eyebrow" data-i18n="workspace">AI CAMPAIGN WORKSPACE</p>
      <h1 data-i18n="studio_headline">Turn your idea into a complete marketing campaign with AI</h1>
      <p class="studio-lead" data-i18n="studio_lead">Strategy, ad copy, social posts, images, Saudi Arabic voice, and a content plan — in one creative studio.</p>
      <p class="studio-hello"><span data-i18n="welcome">Welcome</span>, <?= e($user['name']) ?></p>
      <a class="button studio-cta" href="/campaign-new.php" data-i18n="studio_cta">Start a new campaign</a>
    </div>
    <div>
      <p class="flow-label" data-i18n="studio_flow_label">How a campaign comes together</p>
      <ol class="studio-flow">
        <li class="flow-step" data-i18n="flow_idea">Idea</li>
        <li class="flow-step" data-i18n="flow_copy">Copy</li>
        <li class="flow-step" data-i18n="flow_images">Images</li>
        <li class="flow-step" data-i18n="flow_voice">Voice</li>
        <li class="flow-step" data-i18n="flow_video">Video</li>
        <li class="flow-step" data-i18n="flow_ready">Ready campaign</li>
      </ol>
    </div>
  </section>

  <section class="home-section" id="samples">
    <div class="section-head"><h2 data-i18n="sample_title">Examples you can view and hear</h2></div>
    <h3 data-i18n="sample_photos">Pictures</h3>
    <div class="sample-grid">
      <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-perfume.jpg"><img src="/public/assets/demo/demo-perfume.jpg" alt=""><span data-i18n="video_product">Product film</span></article>
      <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-hospitality.jpg"><img src="/public/assets/demo/demo-hospitality.jpg" alt=""><span data-i18n="video_social">Hospitality film</span></article>
      <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-social.jpg"><img src="/public/assets/demo/demo-social.jpg" alt=""><span data-i18n="format_instagram">Instagram</span></article>
      <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-evening.jpg"><img src="/public/assets/demo/demo-evening.jpg" alt=""><span data-i18n="sample_product">Hospitality set</span></article>
      <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-story.jpg"><img src="/public/assets/demo/demo-story.jpg" alt=""><span data-i18n="format_stories">Stories</span></article>
      <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="image" data-src="/public/assets/demo/demo-campaign.jpg"><img src="/public/assets/demo/demo-campaign.jpg" alt=""><span data-i18n="show_strategy_title">AI campaign strategy</span></article>
    </div>
    <h3 data-i18n="sample_videos">Videos</h3>
    <div class="sample-grid sample-videos">
      <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-product.mp4"><img src="/public/assets/demo/demo-perfume.jpg" alt=""><span class="mini-play"></span><span data-i18n="video_product">Product film</span></article>
      <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-social.mp4"><img src="/public/assets/demo/demo-hospitality.jpg" alt=""><span class="mini-play"></span><span data-i18n="video_social">Hospitality film</span></article>
      <article class="sample-card demo-hit" tabindex="0" role="button" data-kind="video" data-src="/public/assets/demo/reel-story.mp4"><img src="/public/assets/demo/demo-story.jpg" alt=""><span class="mini-play"></span><span data-i18n="video_story">Vertical story</span></article>
    </div>
    <h3 data-i18n="sample_voices">Voices</h3>
    <div class="voice-row">
      <article class="voice-card demo-hit" tabindex="0" role="button" data-kind="audio" data-src="/public/assets/demo/voice-calm.mp3"><span class="mini-play"></span><b data-i18n="voice_calm">Calm voice</b></article>
      <article class="voice-card demo-hit" tabindex="0" role="button" data-kind="audio" data-src="/public/assets/demo/voice-warm.mp3"><span class="mini-play"></span><b data-i18n="voice_warm">Warm voice</b></article>
      <article class="voice-card demo-hit" tabindex="0" role="button" data-kind="audio" data-src="/public/assets/demo/voice-clear.mp3"><span class="mini-play"></span><b data-i18n="voice_clear">Clear voice</b></article>
      <article class="voice-card demo-hit" tabindex="0" role="button" data-kind="audio" data-src="/public/assets/demo/voice-deep.mp3"><span class="mini-play"></span><b data-i18n="voice_deep">Deep voice</b></article>
    </div>
  </section>

  <div class="dash-kpis studio-stats">
    <?php foreach ($summary as $key => $count): ?>
      <article class="studio-stat">
        <span class="badge status-<?= e($key) ?>" data-i18n="status_<?= e($key) ?>"><?= e($key) ?></span>
        <strong><?= (int) $count ?></strong>
      </article>
    <?php endforeach; ?>
  </div>

  <section class="studio-block">
    <div class="section-head"><h2 data-i18n="showcase_label">What you can create</h2></div>
    <div class="showcase">
      <a class="showcase-card show-strategy" href="/campaign-new.php">
        <span class="show-mark">01</span>
        <h3 data-i18n="show_strategy_title">AI campaign strategy</h3>
        <p data-i18n="show_strategy_body">A clear plan for the offer, audience, and message.</p>
      </a>
      <a class="showcase-card show-copy" href="/campaign-new.php">
        <span class="show-mark">02</span>
        <h3 data-i18n="show_copy_title">Ad copy</h3>
        <p data-i18n="show_copy_body">Ready lines for ads, landing pages, and offers.</p>
      </a>
      <a class="showcase-card show-social" href="/campaign-new.php">
        <span class="show-mark">03</span>
        <h3 data-i18n="show_social_title">Social posts</h3>
        <p data-i18n="show_social_body">Posts shaped for the platforms your customers use.</p>
      </a>
      <a class="showcase-card show-images" href="/campaign-new.php">
        <span class="show-mark">04</span>
        <h3 data-i18n="show_images_title">AI images</h3>
        <p data-i18n="show_images_body">Campaign visuals generated for this brand.</p>
      </a>
      <a class="showcase-card show-voice" href="/campaign-new.php">
        <span class="show-mark">05</span>
        <h3 data-i18n="show_voice_title">Saudi Arabic voice</h3>
        <p data-i18n="show_voice_body">A natural Saudi Arabic voice-over from your script.</p>
      </a>
      <a class="showcase-card show-video" href="/campaign-new.php">
        <span class="show-mark">06</span>
        <h3 data-i18n="show_video_title">Video ideas</h3>
        <p data-i18n="show_video_body">Shot ideas and scripts. Video rendering stays unavailable.</p>
      </a>
      <a class="showcase-card show-planner" href="/campaign-new.php">
        <span class="show-mark">07</span>
        <h3 data-i18n="show_planner_title">Content planner</h3>
        <p data-i18n="show_planner_body">Dates, drafts, and approvals for what goes live next.</p>
      </a>
    </div>
  </section>

  <section class="studio-block">
    <div class="section-head">
      <h2 data-i18n="your_campaigns">Your campaigns</h2>
      <a class="button quiet" href="/campaign-new.php" data-i18n="new_campaign_button">+ New Campaign</a>
    </div>
    <?php if (!$campaigns): ?>
      <div class="empty studio-empty">
        <h3 data-i18n="no_campaigns">No campaigns yet</h3>
        <p class="muted" data-i18n="no_campaigns_lead">Create your first campaign to get started.</p>
        <a class="button studio-cta" href="/campaign-new.php" data-i18n="create_campaign">Create Campaign</a>
      </div>
    <?php else: ?>
      <div class="campaign-board">
        <?php foreach ($campaigns as $campaign): ?>
          <?php
          $campaignId = (int) $campaign['id'];
          $statusKey = in_array((string) $campaign['status'], campaign_status_values(), true) ? (string) $campaign['status'] : 'draft';
          $tone = ($campaignId % 4) + 1;
          $initial = function_exists('mb_substr') ? mb_substr((string) $campaign['title'], 0, 1, 'UTF-8') : substr((string) $campaign['title'], 0, 1);
          ?>
          <a class="campaign-tile" href="/campaign.php?id=<?= $campaignId ?>">
            <div class="campaign-cover tone-<?= $tone ?>">
              <?php if (isset($covers[$campaignId])): ?>
                <img src="/app/media/file.php?id=<?= (int) $covers[$campaignId] ?>" alt="">
              <?php else: ?>
                <span class="cover-initial"><?= e($initial) ?></span>
              <?php endif; ?>
            </div>
            <div class="campaign-tile-body">
              <span class="badge status-<?= e($statusKey) ?>" data-i18n="status_<?= e($statusKey) ?>"><?= e($campaign['status']) ?></span>
              <h3><?= e($campaign['title']) ?></h3>
              <p><?= e($campaign['product_name']) ?></p>
              <time><?= e($campaign['created_at']) ?></time>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<dialog class="demo-viewer" id="demo-viewer">
  <form method="dialog" class="demo-bar">
    <p data-i18n="demo_example">Example</p>
    <button type="submit" class="icon" data-i18n="demo_close">Close</button>
  </form>
  <div id="demo-stage"></div>
</dialog>
<script src="/public/assets/js/app.js"></script>
</body>
</html>

<?php
require __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/app/plans.php';
require __DIR__ . '/app/nav.php';

$user = current_user($pdo);
$campaigns = [];
if ($user) {
$stmt = $pdo->prepare('SELECT id, title, product_name, status, created_at FROM campaigns WHERE user_id = ? ORDER BY id DESC LIMIT 20');
$stmt->execute([$user['id']]);
$campaigns = $stmt->fetchAll();
} else {
    $user = ['id' => 0, 'name' => ''];
}
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
<link rel="stylesheet" href="/public/assets/css/app.css?v=scroll1">
</head>
<body class="studio page-dash">
<?php rateb_site_header('/dashboard.php', !empty($_SESSION['user_id'])); ?>
<?php
$wallet = !empty($_SESSION['user_id']) ? rateb_account_credits($pdo, (int) $_SESSION['user_id']) : ['package_type' => 'free', 'credits_balance' => 0];
$planNames = ['free' => 'الباقة التجريبية', 'starter' => 'باقة الانطلاق', 'pro' => 'الباقة الاحترافية'];
?>
<p class="muted" style="margin:12px 36px">الباقة: <?= e($planNames[$wallet['package_type']] ?? $wallet['package_type']) ?> · الرصيد: <?= (int) $wallet['credits_balance'] ?></p>
<main class="container studio-page">
  <?php if (($_GET['deleted'] ?? '') === '1'): ?>
    <p class="muted" data-i18n="campaign_deleted">Campaign deleted.</p>
  <?php elseif (($_GET['delete_error'] ?? '') === '1'): ?>
    <p class="alert" data-i18n="delete_files_failed">The campaign record was removed, but some of its files could not be deleted.</p>
  <?php endif; ?>
  <section class="studio-hero">
    <div class="studio-hero-copy">
      <p class="eyebrow" data-i18n="workspace">AI CAMPAIGN WORKSPACE</p>
      <h1 data-i18n="studio_headline">Turn your idea into a complete marketing campaign with AI</h1>
      <p class="studio-lead" data-i18n="studio_lead">Strategy, ad copy, social posts, images, Saudi Arabic voice, and a content plan — in one creative studio.</p>
      <?php if ($user['name'] !== ''): ?><p class="studio-hello"><span data-i18n="welcome">Welcome</span>, <?= e($user['name']) ?></p><?php endif; ?>
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

  <section class="studio-block" id="library">
    <div class="section-head">
      <h2 data-i18n="your_campaigns">Your campaigns</h2>
      <a class="button quiet" href="/campaign-new.php" data-i18n="new_campaign_button">+ New Campaign</a>
    </div>
    <?php if (($_GET['copy_error'] ?? '') === '1'): ?>
      <p class="alert" data-i18n="copy_failed">The campaign could not be copied.</p>
    <?php endif; ?>
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
          <article class="campaign-tile">
            <a class="campaign-cover tone-<?= $tone ?>" href="/campaign.php?id=<?= $campaignId ?>">
              <?php if (isset($covers[$campaignId])): ?>
                <img src="/app/media/file.php?id=<?= (int) $covers[$campaignId] ?>" alt="">
              <?php else: ?>
                <span class="cover-initial"><?= e($initial) ?></span>
              <?php endif; ?>
            </a>
            <div class="campaign-tile-body">
              <span class="badge status-<?= e($statusKey) ?>" data-i18n="status_<?= e($statusKey) ?>"><?= e($statusKey) ?></span>
              <h3><a href="/campaign.php?id=<?= $campaignId ?>"><?= e($campaign['title']) ?></a></h3>
              <p><?= e($campaign['product_name']) ?></p>
              <time><?= e(substr((string) $campaign['created_at'], 0, 10)) ?></time>
              <div class="tile-actions">
                <a class="quiet" href="/campaign.php?id=<?= $campaignId ?>" data-i18n="open_campaign">Open</a>
                <form method="post" action="/campaign-copy.php">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                  <button type="submit" class="quiet" data-i18n="duplicate_campaign">Duplicate</button>
                </form>
                <form method="post" action="/campaign-delete.php" onsubmit="return confirm(document.documentElement.lang==='ar'?'حذف هذه الحملة نهائياً؟':'Delete this campaign permanently?')">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                  <button type="submit" class="quiet danger-text" data-i18n="delete">Delete</button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
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
    <div class="voice-library" data-voice-library>
      <div class="voice-filters">
        <input type="search" data-voice-search placeholder="ابحث بالاسم">
        <select data-voice-country><option value="">اختر الدولة</option></select>
        <select data-voice-dialect disabled><option value="">اختر اللهجة</option></select>
      </div>
      <p class="muted" data-voice-note></p>
      <div class="voice-picks" data-voice-picks></div>
      <audio data-voice-player controls preload="none" hidden></audio>
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

</main>
<dialog class="auth-modal" id="pricingModal">
  <button type="button" class="auth-x" data-pricing-close aria-label="Close">×</button>
  <div class="auth-card">
    <h2>باقتك</h2>
    <p><?= e($planNames[$wallet['package_type']] ?? $wallet['package_type']) ?></p>
    <p class="muted">الرصيد المتبقي: <?= (int) $wallet['credits_balance'] ?></p>
    <div class="price-grid">
      <article class="price-card"><p class="eyebrow">الباقة التجريبية</p><p class="price-amount"><b>0</b> <span>ر.س</span></p><p>50 نقطة</p><a class="button" href="/?pricing=1#pricing">الباقة الحالية</a></article>
      <article class="price-card is-featured"><p class="eyebrow">باقة الانطلاق</p><p class="price-amount"><b>99</b> <span>ر.س / شهر</span></p><p>500 نقطة</p><a class="button" href="/app/payment/checkout.php?package=starter">ترقية الباقة</a></article>
      <article class="price-card"><p class="eyebrow">الباقة الاحترافية</p><p class="price-amount"><b>249</b> <span>ر.س / شهر</span></p><p>2500 نقطة</p><a class="button" href="/app/payment/checkout.php?package=pro">ترقية الباقة</a></article>
    </div>
  </div>
</dialog>
<dialog class="demo-viewer" id="demo-viewer">
  <form method="dialog" class="demo-bar">
    <p data-i18n="demo_example">Example</p>
    <button type="submit" class="icon" data-i18n="demo_close">Close</button>
  </form>
  <div id="demo-stage"></div>
</dialog>
<?php rateb_auth_dialog('/dashboard.php'); ?>
<script>
document.querySelectorAll('[data-pricing-open]').forEach(function (node) {
  node.addEventListener('click', function () {
    var modal = document.getElementById('pricingModal');
    if (modal && modal.showModal) modal.showModal();
  });
});
document.querySelectorAll('[data-pricing-close]').forEach(function (node) {
  node.addEventListener('click', function () {
    var modal = document.getElementById('pricingModal');
    if (modal) modal.close();
  });
});
</script>
<script src="/public/assets/js/app.js?v=price3"></script>
</body>
</html>

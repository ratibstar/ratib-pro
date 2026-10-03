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
$m = db()->prepare('SELECT * FROM campaign_media WHERE campaign_id=? AND user_id=? ORDER BY id DESC');
$m->execute([$id, $_SESSION['user_id']]);
$media = $m->fetchAll();
$mediaError = (string) ($_GET['media_error'] ?? '');
if (!in_array($mediaError, ['size', 'type', 'upload'], true)) {
    $mediaError = '';
}
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
      <p class="muted" data-i18n="text_available">Text: available</p>
      <button class="primary" id="generate" type="submit" data-i18n="generate">Generate AI Campaign</button>
    </form>
    <p id="status" class="muted"></p>
  </section>
  <section class="panel" id="media">
    <h2 data-i18n="media_gallery">Media gallery</h2>
    <div class="media-states">
      <div>
        <p class="muted" data-i18n="image_available">Image: available</p>
        <form id="imageForm">
          <input type="hidden" name="campaign_id" value="<?= $id ?>">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <button class="primary" id="generate-image" type="submit" data-i18n="generate_image">Generate image</button>
        </form>
        <p id="image-status" class="muted"></p>
      </div>
      <div>
        <button type="button" class="icon" disabled data-i18n="provider_required">Provider required</button>
        <p class="muted" data-i18n="video_provider_required">AI video generation requires a video provider. No free video API is available, and the current Groq credential has no video model.</p>
      </div>
      <div>
        <p class="muted" data-i18n="voice_available">Voice: available</p>
        <form id="voiceForm">
          <input type="hidden" name="campaign_id" value="<?= $id ?>">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="lang" id="voice-lang" value="en">
          <button class="primary" id="voice" type="submit" data-i18n="generate_voice">Generate voice</button>
        </form>
        <p id="voice-status" class="muted"></p>
      </div>
    </div>
    <?php if ($mediaError !== ''): ?>
      <p class="alert" data-i18n="media_error_<?= e($mediaError) ?>">The file could not be saved.</p>
    <?php endif; ?>
    <div class="media-uploads">
      <form method="post" action="/app/media/upload.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="campaign_id" value="<?= $id ?>">
        <input type="hidden" name="kind" value="image">
        <label><span data-i18n="upload_image">Upload image</span>
          <input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif" required>
        </label>
        <button class="primary" type="submit" data-i18n="upload">Upload</button>
      </form>
      <form method="post" action="/app/media/upload.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="campaign_id" value="<?= $id ?>">
        <input type="hidden" name="kind" value="video">
        <label><span data-i18n="upload_video">Upload video</span>
          <input type="file" name="file" accept="video/mp4,video/webm" required>
        </label>
        <button class="primary" type="submit" data-i18n="upload">Upload</button>
      </form>
      <form method="post" action="/app/media/upload.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="campaign_id" value="<?= $id ?>">
        <input type="hidden" name="kind" value="audio">
        <label><span data-i18n="upload_audio">Upload audio</span>
          <input type="file" name="file" accept="audio/mpeg,audio/wav,audio/ogg,audio/webm,audio/mp4" required>
        </label>
        <button class="primary" type="submit" data-i18n="upload">Upload</button>
      </form>
    </div>
    <?php if (!$media): ?>
      <p class="muted" data-i18n="no_media">No media yet.</p>
    <?php else: ?>
      <div class="media-grid">
        <?php foreach ($media as $item): ?>
          <?php
          $mediaId = (int) $item['id'];
          $kind = (string) $item['kind'];
          $fileUrl = '/app/media/file.php?id=' . $mediaId;
          ?>
          <article class="media-card">
            <?php if ($kind === 'image'): ?>
              <img src="<?= e($fileUrl) ?>" alt="">
            <?php elseif ($kind === 'video'): ?>
              <video controls preload="metadata" src="<?= e($fileUrl) ?>"></video>
            <?php elseif ($kind === 'audio'): ?>
              <audio controls preload="metadata" src="<?= e($fileUrl) ?>"></audio>
            <?php endif; ?>
            <p><?= e($item['original_name']) ?></p>
            <div class="media-actions">
              <a class="button" href="<?= e($fileUrl) ?>&amp;download=1" data-i18n="download">Download</a>
              <form method="post" action="/app/media/delete.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $mediaId ?>">
                <button type="submit" data-i18n="delete">Delete</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
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

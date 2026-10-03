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
$items = db()->prepare('SELECT * FROM campaign_items WHERE campaign_id=? AND user_id=? ORDER BY item_date IS NULL, item_date, id');
$items->execute([$id, $_SESSION['user_id']]);
$items = $items->fetchAll();
$variations = db()->prepare('SELECT * FROM campaign_variations WHERE campaign_id=? AND user_id=? ORDER BY id DESC');
$variations->execute([$id, $_SESSION['user_id']]);
$variationGroups = [];
foreach ($variations->fetchAll() as $variation) {
    $variationGroups[(string) $variation['label']][] = $variation;
}
$status = campaign_one_of((string) ($campaign['status'] ?? 'draft'), campaign_status_values(), 'draft');
$progress = ['draft' => 25, 'in_progress' => 50, 'ready' => 75, 'completed' => 100][$status];
$strategy = '';
$textOutputs = [];
foreach ($outputs as $row) {
    if ((string) $row['output_type'] === 'strategy' && $strategy === '') {
        $strategy = (string) $row['content'];
    } else {
        $textOutputs[] = $row;
    }
}
$coverUrl = '';
foreach ($media as $mediaItem) {
    if ((string) $mediaItem['kind'] === 'image') {
        $coverUrl = '/app/media/file.php?id=' . (int) $mediaItem['id'];
        break;
    }
}
$tone = ($id % 4) + 1;
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — Campaign</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body class="studio">
<header class="top">
  <a class="brand" href="/dashboard.php">RATEB AI</a>
  <nav>
    <a href="/dashboard.php" data-i18n="dashboard">Dashboard</a>
    <a href="/campaign-new.php" data-i18n="new_campaign">New Campaign</a>
    <a href="/brand.php" data-i18n="brand_kit">Brand kit</a>
    <a href="/logout.php" data-i18n="logout">Logout</a>
    <button type="button" id="lang" class="icon">عربي</button>
    <button type="button" id="theme" class="icon">☾</button>
  </nav>
</header>
<main class="wrap workspace">
  <nav class="tabs" role="tablist">
    <button type="button" class="tab is-active" data-tab="overview" role="tab" aria-selected="true" data-i18n="tab_overview">Overview</button>
    <button type="button" class="tab" data-tab="ai" role="tab" aria-selected="false" data-i18n="tab_ai">AI Content</button>
    <button type="button" class="tab" data-tab="media" role="tab" aria-selected="false" data-i18n="tab_media">Media</button>
    <button type="button" class="tab" data-tab="planner" role="tab" aria-selected="false" data-i18n="tab_planner">Planner</button>
    <button type="button" class="tab" data-tab="variations" role="tab" aria-selected="false" data-i18n="tab_variations">Variations</button>
    <button type="button" class="tab" data-tab="brand" role="tab" aria-selected="false" data-i18n="tab_brand">Brand</button>
  </nav>
  <section class="panel is-active" id="overview" data-panel="overview">
    <div class="studio-hero campaign-hero">
      <div class="campaign-cover tone-<?= (int) $tone ?>">
        <?php if ($coverUrl !== ''): ?><img src="<?= e($coverUrl) ?>" alt=""><?php endif; ?>
      </div>
      <div class="studio-hero-copy">
        <div class="workspace-head">
          <div>
            <span class="eyebrow" data-i18n="campaign_details">CAMPAIGN DETAILS</span>
            <h1><?= e($campaign['title']) ?></h1>
            <span class="badge status-<?= e($status) ?>" data-i18n="status_<?= e($status) ?>"><?= e($status) ?></span>
          </div>
          <div class="action-row">
            <button type="button" class="primary" data-open="ai" data-i18n="open_ai">Open AI content</button>
            <button type="button" class="quiet" data-open="media" data-i18n="tab_media">Media</button>
            <a class="button quiet" href="/brand.php" data-i18n="open_brand">Open brand kit</a>
          </div>
        </div>
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
    </div>
    <div class="showcase">
      <button type="button" class="showcase-card show-strategy" data-open="ai"><span class="show-mark">01</span><h3 data-i18n="show_strategy_title">AI campaign strategy</h3><p data-i18n="show_strategy_body">A clear plan for the offer, audience, and message.</p></button>
      <button type="button" class="showcase-card show-copy" data-open="ai"><span class="show-mark">02</span><h3 data-i18n="show_copy_title">Ad copy</h3><p data-i18n="show_copy_body">Ready lines for ads, landing pages, and offers.</p></button>
      <button type="button" class="showcase-card show-social" data-open="ai"><span class="show-mark">03</span><h3 data-i18n="show_social_title">Social posts</h3><p data-i18n="show_social_body">Posts shaped for the platforms your customers use.</p></button>
      <button type="button" class="showcase-card show-images" data-open="media"><span class="show-mark">04</span><h3 data-i18n="show_images_title">AI images</h3><p data-i18n="show_images_body">Campaign visuals generated for this brand.</p></button>
      <button type="button" class="showcase-card show-voice" data-open="media"><span class="show-mark">05</span><h3 data-i18n="show_voice_title">Saudi Arabic voice</h3><p data-i18n="show_voice_body">A natural Saudi Arabic voice-over from your script.</p></button>
      <button type="button" class="showcase-card show-video" data-open="ai"><span class="show-mark">06</span><h3 data-i18n="show_video_title">Video ideas</h3><p data-i18n="show_video_body">Shot ideas and scripts. Video rendering stays unavailable.</p></button>
      <button type="button" class="showcase-card show-planner" data-open="planner"><span class="show-mark">07</span><h3 data-i18n="show_planner_title">Content planner</h3><p data-i18n="show_planner_body">Dates, drafts, and approvals for what goes live next.</p></button>
    </div>
    <div class="progress progress-<?= (int) $progress ?>" role="progressbar" aria-valuenow="<?= (int) $progress ?>" aria-valuemin="0" aria-valuemax="100"><span></span></div>
    <p class="muted"><span data-i18n="progress">Progress</span> <?= (int) $progress ?>%</p>
    <div class="kpi-grid">
      <article class="kpi"><span data-i18n="product">Product / Service</span><strong><?= e($campaign['product_name']) ?></strong></article>
      <article class="kpi"><span data-i18n="price">Price</span><strong><?= trim((string) $campaign['price']) !== '' ? e($campaign['price']) : '<span class="muted" data-i18n="not_set">Not set</span>' ?></strong></article>
      <article class="kpi"><span data-i18n="budget">Budget</span><strong><?php if (trim((string) ($campaign['budget'] ?? '')) === ''): ?><span class="muted" data-i18n="not_set">Not set</span><?php else: ?><?= e($campaign['budget']) ?><?php endif; ?></strong></article>
      <article class="kpi"><span data-i18n="dates">Dates</span><strong><?php if (empty($campaign['start_date']) && empty($campaign['end_date'])): ?><span class="muted" data-i18n="not_set">Not set</span><?php else: ?><?= e($campaign['start_date'] ?? '') ?> – <?= e($campaign['end_date'] ?? '') ?><?php endif; ?></strong></article>
      <article class="kpi"><span data-i18n="kpi_outputs">AI outputs</span><strong><?= count($outputs) ?></strong></article>
      <article class="kpi"><span data-i18n="kpi_media">Media files</span><strong><?= count($media) ?></strong></article>
      <article class="kpi"><span data-i18n="kpi_items">Planner items</span><strong><?= count($items) ?></strong></article>
      <article class="kpi"><span data-i18n="kpi_variations">Variations</span><strong><?= count($variationGroups) ?></strong></article>
    </div>
    <div class="summary-grid">
      <div><b data-i18n="target">Target customer</b><p><?= e($campaign['target_customer']) ?></p></div>
      <div><b data-i18n="objective">Objective</b><p><?php if (trim((string) ($campaign['objective'] ?? '')) === ''): ?><span class="muted" data-i18n="not_set">Not set</span><?php else: ?><?= e($campaign['objective']) ?><?php endif; ?></p></div>
      <div class="wide"><b data-i18n="description">Description</b><p><?= nl2br(e($campaign['description'])) ?></p></div>
    </div>
    <h2 data-i18n="campaign_workspace">Campaign workspace</h2>
    <form method="post" action="/campaign-update.php" id="details">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="campaign_id" value="<?= $id ?>">
      <div class="grid two">
        <label><span data-i18n="campaign_title">Campaign title *</span><input name="title" value="<?= e($campaign['title']) ?>" required></label>
        <label><span data-i18n="product_required">Product / Service *</span><input name="product_name" value="<?= e($campaign['product_name']) ?>" required></label>
        <label><span data-i18n="objective">Objective</span><input name="objective" value="<?= e($campaign['objective'] ?? '') ?>"></label>
        <label><span data-i18n="target_required">Target customer *</span><input name="target_customer" value="<?= e($campaign['target_customer']) ?>" required></label>
        <label><span data-i18n="budget">Budget</span><input name="budget" value="<?= e($campaign['budget'] ?? '') ?>"></label>
        <label><span data-i18n="price">Price</span><input name="price" value="<?= e($campaign['price']) ?>"></label>
        <label><span data-i18n="start_date">Start date</span><input type="date" name="start_date" value="<?= e($campaign['start_date'] ?? '') ?>"></label>
        <label><span data-i18n="end_date">End date</span><input type="date" name="end_date" value="<?= e($campaign['end_date'] ?? '') ?>"></label>
        <label><span data-i18n="col_status">Status</span>
          <select name="status">
            <?php foreach (campaign_status_values() as $option): ?>
              <option value="<?= e($option) ?>" data-i18n="status_<?= e($option) ?>" <?= $option === $status ? 'selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label><span data-i18n="description_required">Description *</span><textarea name="description" required><?= e($campaign['description']) ?></textarea></label>
      </div>
      <button class="primary" type="submit" data-i18n="save_changes">Save changes</button>
    </form>
  </section>
  <section class="panel" id="strategy" data-panel="ai">
    <h2 data-i18n="strategy_heading">AI-generated strategy</h2>
    <?php if ($strategy === ''): ?>
      <p class="empty" data-i18n="no_strategy">No strategy yet. Generate the campaign below.</p>
    <?php else: ?>
      <div><?= nl2br(e($strategy)) ?></div>
    <?php endif; ?>
    <form id="generateForm">
      <input type="hidden" name="campaign_id" value="<?= $id ?>">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="lang" id="gen-lang" value="en">
      <p class="muted" data-i18n="text_available">Text: available</p>
      <button class="primary" id="generate" type="submit" data-i18n="generate">Generate AI Campaign</button>
    </form>
    <p id="status" class="muted"></p>
  </section>
  <section class="panel" id="media" data-panel="media">
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
      <p class="empty" data-i18n="no_media">No media yet.</p>
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
              <button type="submit" class="danger" data-i18n="delete">Delete</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <section class="panel" id="copy" data-panel="ai">
    <h2 data-i18n="results">AI Results</h2>
    <?php if (!$textOutputs): ?>
      <p class="empty" data-i18n="no_results">No AI results yet. Generate the campaign above.</p>
    <?php else: ?>
      <?php foreach ($textOutputs as $r): ?>
        <?php
        $type = (string) $r['output_type'];
        $label = ucwords(str_replace('_', ' ', $type));
        ?>
        <article class="result">
          <h3<?php if (in_array($type, $knownTypes, true)): ?> data-i18n="type_<?= e($type) ?>"<?php endif; ?>><?= e($label) ?></h3>
          <div><?= nl2br(e($r['content'])) ?></div>
          <form method="post" action="/app/content/save.php" class="assign-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="campaign_id" value="<?= $id ?>">
            <input type="hidden" name="title" value="<?= e($label) ?>">
            <input type="hidden" name="body" value="<?= e($r['content']) ?>">
            <input type="hidden" name="planner_status" value="draft">
            <input type="hidden" name="approval_status" value="draft">
            <label><span data-i18n="assign_date">Assign to date</span><input type="date" name="item_date"></label>
            <button type="submit" class="quiet" data-i18n="add_to_calendar">Add to calendar</button>
          </form>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
  <section class="panel" id="planner" data-panel="planner">
    <h2 data-i18n="content_planner">Content planner</h2>
    <form method="post" action="/app/content/save.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="campaign_id" value="<?= $id ?>">
      <div class="grid two">
        <label><span data-i18n="item_title">Content title</span><input name="title" required></label>
        <label><span data-i18n="assign_date">Assign to date</span><input type="date" name="item_date"></label>
        <label><span data-i18n="item_body">Content</span><textarea name="body" required></textarea></label>
        <label><span data-i18n="planner_status">Planner status</span>
          <select name="planner_status">
            <?php foreach (planner_status_values() as $option): ?>
              <option value="<?= e($option) ?>" data-i18n="planner_<?= e($option) ?>"><?= e($option) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label><span data-i18n="approval_status">Approval</span>
          <select name="approval_status">
            <?php foreach (approval_status_values() as $option): ?>
              <option value="<?= e($option) ?>" data-i18n="approval_<?= e($option) ?>"><?= e($option) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <button class="primary" type="submit" data-i18n="add_content">Add content</button>
    </form>
    <?php if (!$items): ?>
      <p class="empty" data-i18n="no_items">No content items yet.</p>
    <?php else: ?>
      <?php foreach ($items as $item): ?>
        <form method="post" action="/app/content/save.php" class="result">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="campaign_id" value="<?= $id ?>">
          <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
          <div class="grid two">
            <label><span data-i18n="item_title">Content title</span><input name="title" value="<?= e($item['title']) ?>" required></label>
            <label><span data-i18n="assign_date">Assign to date</span><input type="date" name="item_date" value="<?= e($item['item_date'] ?? '') ?>"></label>
            <label><span data-i18n="item_body">Content</span><textarea name="body" required><?= e($item['body']) ?></textarea></label>
            <label><span data-i18n="planner_status">Planner status</span>
              <select name="planner_status">
                <?php foreach (planner_status_values() as $option): ?>
                  <option value="<?= e($option) ?>" data-i18n="planner_<?= e($option) ?>" <?= $option === $item['planner_status'] ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label><span data-i18n="approval_status">Approval</span>
              <select name="approval_status">
                <?php foreach (approval_status_values() as $option): ?>
                  <option value="<?= e($option) ?>" data-i18n="approval_<?= e($option) ?>" <?= $option === $item['approval_status'] ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <button class="primary" type="submit" data-i18n="save_changes">Save changes</button>
          <button type="submit" name="action" value="delete" class="danger" data-i18n="delete">Delete</button>
        </form>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
  <section class="panel" id="variations" data-panel="variations">
    <h2 data-i18n="variations">Content variations</h2>
    <div class="action-row">
      <form method="post" action="/app/content/variation-save.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="campaign_id" value="<?= $id ?>">
        <button type="submit" class="quiet" data-i18n="save_variation">Save current copy as a variation</button>
      </form>
      <form id="variationForm">
        <input type="hidden" name="campaign_id" value="<?= $id ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="lang" value="en">
        <button class="primary" id="generate-variation" type="submit" data-i18n="generate_variation">Generate another variation</button>
      </form>
    </div>
    <p id="variation-status" class="muted"></p>
    <?php if (!$variationGroups): ?>
      <p class="empty" data-i18n="no_variations">No saved variations yet.</p>
    <?php else: ?>
      <?php foreach ($variationGroups as $label => $group): ?>
        <article class="result">
          <h3><?= e($label) ?></h3>
          <?php foreach ($group as $row): ?>
            <?php $type = (string) $row['output_type']; ?>
            <h4<?php if (in_array($type, $knownTypes, true)): ?> data-i18n="type_<?= e($type) ?>"<?php endif; ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></h4>
            <div><?= nl2br(e($row['content'])) ?></div>
          <?php endforeach; ?>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
  <section class="panel" id="brand" data-panel="brand">
    <h2 data-i18n="brand_kit_title">Brand kit</h2>
    <p class="muted" data-i18n="brand_panel_lead">Brand name, logo, colors, language, and tone are saved in the brand kit and used as optional context for later copy.</p>
    <a class="button" href="/brand.php" data-i18n="open_brand">Open brand kit</a>
  </section>
</main>
<script src="/public/assets/js/app.js"></script>
</body>
</html>

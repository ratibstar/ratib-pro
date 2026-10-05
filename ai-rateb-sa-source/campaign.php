<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/plans.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$s = db()->prepare('SELECT * FROM campaigns WHERE id=? AND user_id=?');
$s->execute([$id, $userId]);
$campaign = $s->fetch();
if (!$campaign) {
    http_response_code(404);
    exit(ui_language() === 'ar' ? 'الحملة غير موجودة.' : 'Campaign not found.');
}
$briefStmt = db()->prepare('SELECT * FROM campaign_briefs WHERE campaign_id = ? AND user_id = ? LIMIT 1');
$briefStmt->execute([$id, $userId]);
$brief = $briefStmt->fetch() ?: [];
$o = db()->prepare('SELECT * FROM campaign_outputs WHERE campaign_id=? ORDER BY id');
$o->execute([$id]);
$outputs = $o->fetchAll();
$m = db()->prepare('SELECT * FROM campaign_media WHERE campaign_id=? AND user_id=? ORDER BY id DESC');
$m->execute([$id, $userId]);
$media = $m->fetchAll();
$arabic = ui_language() === 'ar';
$campaignLanguage = (string) ($brief['campaign_language'] ?? 'auto');
$status = campaign_one_of((string) ($campaign['status'] ?? 'draft'), campaign_status_values(), 'draft');
$statusLabels = $arabic
    ? ['draft' => 'مسودة', 'in_progress' => 'قيد التنفيذ', 'ready' => 'جاهزة', 'completed' => 'مكتملة']
    : ['draft' => 'Draft', 'in_progress' => 'In progress', 'ready' => 'Ready', 'completed' => 'Completed'];
$langLabels = $arabic
    ? ['ar' => 'العربية', 'en' => 'الإنجليزية', 'bilingual' => 'العربية والإنجليزية', 'auto' => 'خل RATEB يختار']
    : ['ar' => 'Arabic', 'en' => 'English', 'bilingual' => 'Arabic and English', 'auto' => 'Let RATEB choose'];
$copyLabels = $arabic
    ? [
        'headline' => 'العنوان',
        'ad_copy' => 'الإعلان الرئيسي',
        'short_ad' => 'الإعلان القصير',
        'social_posts' => 'منشور التواصل',
        'whatsapp' => 'رسالة واتساب',
        'call_to_action' => 'الدعوة إلى الإجراء',
        'product_description' => 'وصف المنتج',
    ]
    : [
        'headline' => 'Headline',
        'ad_copy' => 'Main advertisement',
        'short_ad' => 'Short advertisement',
        'social_posts' => 'Social post',
        'whatsapp' => 'WhatsApp message',
        'call_to_action' => 'Call to action',
        'product_description' => 'Product description',
    ];
$strategyLabels = $arabic
    ? [
        'positioning' => 'الفكرة الرئيسية',
        'core_message' => 'الرسالة الأساسية',
        'audience' => 'الجمهور',
        'channels' => 'القنوات المقترحة',
        'tone' => 'النبرة',
        'creative_direction' => 'الاتجاه الإبداعي',
        'call_to_action' => 'الدعوة إلى الإجراء',
    ]
    : [
        'positioning' => 'Positioning',
        'core_message' => 'Core message',
        'audience' => 'Audience',
        'channels' => 'Suggested channels',
        'tone' => 'Tone',
        'creative_direction' => 'Creative direction',
        'call_to_action' => 'Call to action',
    ];
$strategyRow = null;
$copyRows = [];
foreach ($outputs as $row) {
    $type = (string) $row['output_type'];
    if ($type === 'strategy' && $strategyRow === null) {
        $strategyRow = $row;
    } elseif (in_array($type, rateb_copy_types(), true)) {
        $copyRows[$type] = $row;
    }
}
$strategyDoc = $strategyRow ? rateb_strategy_document((string) $strategyRow['content']) : null;
$strategyApproved = $strategyDoc !== null && (string) ($strategyRow['approval_status'] ?? '') === 'approved';
$copyApproved = false;
$copyStarted = $copyRows !== [];
foreach ($copyRows as $row) {
    if ((string) ($row['approval_status'] ?? '') === 'approved') {
        $copyApproved = true;
    }
}
$imageCount = 0;
$videoCount = 0;
foreach ($media as $mediaItem) {
    if ((string) $mediaItem['kind'] === 'image') {
        $imageCount++;
    } elseif ((string) $mediaItem['kind'] === 'video') {
        $videoCount++;
    }
}
$product = trim((string) ($brief['product'] ?? $campaign['product_name'] ?? ''));
$idea = trim((string) ($brief['description'] ?? $campaign['description'] ?? ''));
$objective = trim((string) ($brief['objective'] ?? $campaign['objective'] ?? ''));
$audience = trim((string) ($brief['audience'] ?? $campaign['target_customer'] ?? ''));
$location = trim((string) ($brief['location'] ?? ''));
$channels = trim((string) ($brief['channels'] ?? ''));
$tone = trim((string) ($brief['brand_tone'] ?? ''));
$ideaDone = $product !== '' || $idea !== '';
$doneUnits = ($ideaDone ? 1 : 0) + ($strategyApproved ? 1 : 0) + ($copyApproved ? 1 : 0);
$progress = (int) round($doneUnits * 100 / 7);
$stages = [
    'idea' => ['no' => '01', 'state' => $ideaDone ? 'done' : 'open'],
    'strategy' => ['no' => '02', 'state' => $strategyApproved ? 'done' : ($strategyDoc ? 'progress' : 'open')],
    'copy' => ['no' => '03', 'state' => $copyApproved ? 'done' : ($copyStarted ? 'progress' : ($strategyApproved ? 'open' : 'next'))],
    'images' => ['no' => '04', 'state' => 'next'],
    'voice' => ['no' => '05', 'state' => 'next'],
    'video' => ['no' => '06', 'state' => 'locked'],
    'ready' => ['no' => '07', 'state' => 'next'],
];
$stageNames = $arabic
    ? ['idea' => 'الفكرة', 'strategy' => 'الاستراتيجية', 'copy' => 'النص', 'images' => 'الصور', 'voice' => 'الصوت', 'video' => 'الفيديو', 'ready' => 'الحملة الجاهزة']
    : ['idea' => 'Idea', 'strategy' => 'Strategy', 'copy' => 'Copy', 'images' => 'Images', 'voice' => 'Voice', 'video' => 'Video', 'ready' => 'Ready campaign'];
$stateNames = $arabic
    ? ['done' => 'مكتملة', 'progress' => 'قيد الإعداد', 'open' => 'جاهزة للبدء', 'next' => 'لاحقًا', 'locked' => 'غير متاحة']
    : ['done' => 'Done', 'progress' => 'In progress', 'open' => 'Ready to start', 'next' => 'Later', 'locked' => 'Not available'];
$stateKeys = ['done' => 'state_done', 'progress' => 'state_progress', 'open' => 'state_open', 'next' => 'state_next', 'locked' => 'state_locked'];
$briefRows = [];
if ($product !== '') {
    $briefRows[] = ['brief_product', $arabic ? 'المنتج' : 'Product', $product];
}
if ($idea !== '') {
    $briefRows[] = ['brief_idea', $arabic ? 'الفكرة' : 'Idea', $idea];
}
if ($objective !== '') {
    $briefRows[] = ['brief_objective', $arabic ? 'الهدف' : 'Objective', $objective];
}
if ($audience !== '') {
    $briefRows[] = ['brief_audience', $arabic ? 'الجمهور' : 'Audience', $audience];
}
if ($location !== '') {
    $briefRows[] = ['brief_location', $arabic ? 'الموقع' : 'Location', $location];
}
if ($channels !== '') {
    $briefRows[] = ['brief_channel', $arabic ? 'القنوات' : 'Channels', $channels];
}
$briefRows[] = ['brief_language', $arabic ? 'لغة الحملة' : 'Campaign language', $langLabels[$campaignLanguage] ?? $langLabels['auto']];
if ($tone !== '') {
    $briefRows[] = ['brief_tone', $arabic ? 'النبرة' : 'Tone', $tone];
}
$mediaLabel = '';
if ($imageCount > 0 || $videoCount > 0) {
    $bits = [];
    if ($imageCount > 0) {
        $bits[] = $imageCount . ' ' . ($arabic ? ($imageCount === 1 ? 'صورة' : 'صور') : ($imageCount === 1 ? 'image' : 'images'));
    }
    if ($videoCount > 0) {
        $bits[] = $videoCount . ' ' . ($arabic ? ($videoCount === 1 ? 'فيديو' : 'فيديوهات') : ($videoCount === 1 ? 'video' : 'videos'));
    }
    $mediaLabel = implode($arabic ? ' و' : ' and ', $bits);
    if (!$arabic) {
        $mediaLabel = implode(' and ', $bits);
    }
}
$csrf = e(csrf_token());
?>
<!doctype html>
<html lang="<?= $arabic ? 'ar' : 'en' ?>" dir="<?= $arabic ? 'rtl' : 'ltr' ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — <?= $arabic ? 'الحملة' : 'Campaign' ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=act2">
</head>
<body class="studio page-campaign">
<header class="top">
  <a class="brand" href="/dashboard.php">RATEB AI</a>
  <nav>
    <a href="/dashboard.php" data-i18n="dashboard"><?= $arabic ? 'لوحة التحكم' : 'Dashboard' ?></a>
    <a href="/campaign-new.php" data-i18n="new_campaign"><?= $arabic ? 'حملة جديدة' : 'New campaign' ?></a>
    <a href="/brand.php" data-i18n="brand_kit"><?= $arabic ? 'هوية العلامة' : 'Brand kit' ?></a>
    <a href="/logout.php" data-i18n="logout"><?= $arabic ? 'خروج' : 'Logout' ?></a>
    <button type="button" id="lang" class="icon"><?= $arabic ? 'English' : 'عربي' ?></button>
    <button type="button" id="theme" class="icon">☾</button>
  </nav>
</header>
<main class="wrap workspace">
  <section class="workspace-top">
    <p class="eyebrow" data-i18n="campaign_workspace"><?= $arabic ? 'مساحة الحملة' : 'Campaign workspace' ?></p>
    <div class="workspace-head">
      <div>
        <h1><?= e((string) $campaign['title']) ?></h1>
        <span class="badge status-<?= e($status) ?>" data-i18n="status_<?= e($status) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
      </div>
    </div>
    <div class="progress" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" data-progress="<?= $progress ?>"><span style="width:<?= $progress ?>%"></span></div>
    <p class="muted"><span data-i18n="progress"><?= $arabic ? 'التقدم' : 'Progress' ?></span> <span id="progress-value"><?= $progress ?>%</span> · <?= $doneUnits ?> / 7</p>
  </section>
  <ol class="build-stages">
    <?php foreach ($stages as $key => $stage): ?>
      <li class="is-<?= e($stage['state']) ?>" data-stage="<?= e($key) ?>" data-state="<?= e($stage['state']) ?>">
        <button type="button" class="stage-tab<?= $key === 'idea' ? ' is-active' : '' ?>" data-tab="<?= e($key) ?>" aria-selected="<?= $key === 'idea' ? 'true' : 'false' ?>">
          <span><?= e($stage['no']) ?></span>
          <b data-i18n="stage_<?= e($key) ?>"><?= e($stageNames[$key]) ?></b>
          <small data-i18n="<?= e($stateKeys[$stage['state']]) ?>"><?= e($stateNames[$stage['state']]) ?></small>
        </button>
        <?php if ($key === 'strategy' && $ideaDone && $strategyDoc === null): ?>
          <form class="stage-action" data-ai="1" data-status="stage-status" data-busy="generating_strategy">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="campaign_id" value="<?= $id ?>">
            <input type="hidden" name="stage" value="strategy">
            <input type="hidden" name="confirm" value="0">
            <button class="primary stage-go" type="submit" data-i18n="start_strategy"><?= $arabic ? 'ابدأ الاستراتيجية' : 'Start Strategy' ?></button>
          </form>
        <?php elseif ($key === 'strategy' && $strategyDoc !== null && !$strategyApproved): ?>
          <form class="stage-action" method="post" action="/app/content/approve.php">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="campaign_id" value="<?= $id ?>">
            <input type="hidden" name="kind" value="strategy">
            <button class="primary stage-go" type="submit" data-i18n="approve_strategy"><?= $arabic ? 'اعتماد الاستراتيجية' : 'Approve Strategy' ?></button>
          </form>
          <form class="stage-action" data-ai="1" data-status="stage-status" data-busy="generating_strategy">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="campaign_id" value="<?= $id ?>">
            <input type="hidden" name="stage" value="strategy">
            <input type="hidden" name="confirm" value="0">
            <button class="quiet stage-go" type="submit" data-i18n="regenerate"><?= $arabic ? 'إعادة توليد' : 'Regenerate' ?></button>
          </form>
        <?php elseif ($key === 'strategy' && $strategyApproved): ?>
          <form class="stage-action" data-ai="1" data-status="stage-status" data-busy="generating_strategy" data-locked="1">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="campaign_id" value="<?= $id ?>">
            <input type="hidden" name="stage" value="strategy">
            <input type="hidden" name="confirm" value="0">
            <button class="quiet stage-go" type="submit" data-i18n="regenerate"><?= $arabic ? 'إعادة توليد' : 'Regenerate' ?></button>
          </form>
        <?php elseif ($key === 'copy' && $strategyApproved && !$copyStarted): ?>
          <form class="stage-action" data-ai="1" data-status="stage-status" data-busy="generating_copy">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="campaign_id" value="<?= $id ?>">
            <input type="hidden" name="stage" value="copy">
            <input type="hidden" name="confirm" value="0">
            <button class="primary stage-go" type="submit" data-i18n="start_copy"><?= $arabic ? 'ابدأ كتابة الإعلان' : 'Generate Copy' ?></button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
  <p id="stage-status" class="stage-status"></p>

  <section class="panel is-active" id="idea" data-panel="idea">
    <h2 data-i18n="brief_title"><?= $arabic ? 'ملخص الحملة' : 'Campaign brief' ?></h2>
    <dl class="brief-list workspace-brief">
      <?php foreach ($briefRows as $row): ?>
        <div><dt data-i18n="<?= e($row[0]) ?>"><?= e($row[1]) ?></dt><dd><?php if ($row[0] === 'brief_language'): ?><span data-i18n="<?= e(['ar' => 'lang_ar', 'en' => 'lang_en', 'bilingual' => 'lang_bilingual', 'auto' => 'lang_auto'][$campaignLanguage] ?? 'lang_auto') ?>"><?= e($row[2]) ?></span><?php else: ?><?= e($row[2]) ?><?php endif; ?></dd></div>
      <?php endforeach; ?>
      <?php if ($mediaLabel !== ''): ?>
        <div><dt data-i18n="brief_files"><?= $arabic ? 'المواد' : 'Media' ?></dt><dd><?= e($mediaLabel) ?></dd></div>
      <?php endif; ?>
    </dl>
    <form id="idea-edit" class="idea-edit" data-ai="1" data-status="edit-status" data-busy="updating_idea">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="campaign_id" value="<?= $id ?>">
      <input type="hidden" name="stage" value="edit">
      <label>
        <span data-i18n="edit_lead"><?= $arabic ? 'عدّل الفكرة بكلامك' : 'Edit the idea in your own words' ?></span>
        <textarea name="note" rows="3" data-i18n-placeholder="edit_placeholder" placeholder="<?= $arabic ? 'مثال: غيّر الموقع إلى الرياض وجدة' : 'Example: change the location to Jeddah' ?>"></textarea>
      </label>
      <p class="muted" data-i18n="edit_example"><?= $arabic ? 'مثال: أبغى أستهدف الشركات الصغيرة والمتوسطة أيضاً، أو خل الإعلان أكثر حماساً.' : 'Example: also target small and medium businesses, or make the ad more enthusiastic.' ?></p>
      <button class="primary" type="submit" data-i18n="update_idea"><?= $arabic ? 'حدّث الفكرة' : 'Update the idea' ?></button>
    </form>
    <p id="edit-status" class="muted"></p>
    <?php if ($media): ?>
      <div class="media-grid compact-media">
        <?php foreach ($media as $item): ?>
          <?php $kind = (string) $item['kind']; ?>
          <article class="media-card">
            <p><?= e((string) $item['original_name']) ?></p>
            <p class="muted"><?= e($arabic ? ($kind === 'video' ? 'فيديو' : ($kind === 'audio' ? 'صوت' : 'صورة')) : ucfirst($kind)) ?></p>
            <form method="post" action="/app/media/delete.php">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
              <button type="submit" class="quiet" data-i18n="delete"><?= $arabic ? 'حذف' : 'Delete' ?></button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (($_GET['delete_error'] ?? '') === '1'): ?>
      <p class="alert" data-i18n="delete_failed"><?= $arabic ? 'تعذر حذف الحملة.' : 'The campaign could not be deleted.' ?></p>
    <?php endif; ?>
    <details class="delete-campaign">
      <summary data-i18n="delete_campaign"><?= $arabic ? 'حذف الحملة' : 'Delete campaign' ?></summary>
      <p data-i18n="delete_warning"><?= $arabic ? 'هذا يحذف الحملة ونصها وملفاتها نهائياً.' : 'This permanently removes the campaign, its text, and its files.' ?></p>
      <form method="post" action="/campaign-delete.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="campaign_id" value="<?= $id ?>">
        <button class="danger" type="submit" data-i18n="delete_confirm_button"><?= $arabic ? 'احذف نهائياً' : 'Delete permanently' ?></button>
      </form>
    </details>
  </section>

  <section class="panel" id="strategy" data-panel="strategy">
    <h2 data-i18n="strategy_title"><?= $arabic ? 'استراتيجية الحملة' : 'Campaign strategy' ?></h2>
    <?php if ($strategyDoc === null): ?>
      <p class="empty" data-i18n="no_strategy"><?= $arabic ? 'ما فيه استراتيجية بعد. ابنِها من ملخص الحملة.' : 'No strategy yet. Build it from the campaign brief.' ?></p>
    <?php else: ?>
      <article class="strategy-card">
        <?php if ($strategyApproved): ?><p class="badge" data-i18n="strategy_approved"><?= $arabic ? 'معتمدة' : 'Approved' ?></p><?php endif; ?>
        <?php foreach (rateb_strategy_keys() as $key): ?>
          <?php $parts = rateb_field_parts($strategyDoc[$key] ?? null); if ($parts === []) { continue; } ?>
          <h3 data-i18n="strategy_<?= e($key) ?>"><?= e($strategyLabels[$key]) ?></h3>
          <?php foreach ($parts as $part): ?>
            <?php if ($part['code'] !== ''): ?>
              <span class="eyebrow" data-i18n="<?= $part['code'] === 'ar' ? 'lang_ar' : 'lang_en' ?>"><?= e($part['code'] === 'ar' ? ($arabic ? 'العربية' : 'Arabic') : ($arabic ? 'الإنجليزية' : 'English')) ?></span>
            <?php endif; ?>
            <p><?= nl2br(e($part['text'])) ?></p>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </article>
    <?php endif; ?>
    <div class="action-row">
      <?php if ($strategyDoc !== null && !$strategyApproved): ?>
        <form method="post" action="/app/content/approve.php">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="campaign_id" value="<?= $id ?>">
          <input type="hidden" name="kind" value="strategy">
          <button class="primary" type="submit" data-i18n="approve_strategy"><?= $arabic ? 'اعتماد الاستراتيجية' : 'Approve Strategy' ?></button>
        </form>
      <?php endif; ?>
      <form id="strategy-form" data-ai="1" data-status="strategy-status" data-busy="generating_strategy" data-locked="<?= $strategyApproved ? '1' : '0' ?>">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="campaign_id" value="<?= $id ?>">
        <input type="hidden" name="stage" value="strategy">
        <input type="hidden" name="confirm" value="0">
        <button class="<?= $strategyDoc ? 'quiet' : 'primary' ?>" type="submit" data-i18n="<?= $strategyDoc ? 'regenerate' : 'start_strategy' ?>"><?= $strategyDoc ? ($arabic ? 'إعادة توليد' : 'Regenerate') : ($arabic ? 'ابدأ الاستراتيجية' : 'Start Strategy') ?></button>
      </form>
    </div>
    <p id="strategy-status" class="muted"></p>
  </section>

  <section class="panel" id="copy" data-panel="copy">
    <h2 data-i18n="copy_title"><?= $arabic ? 'نصوص الحملة' : 'Campaign copy' ?></h2>
    <?php if (!$strategyApproved): ?>
      <p class="empty" data-i18n="copy_locked"><?= $arabic ? 'اعتمد الاستراتيجية أولًا، ثم يكتب RATEB النصوص.' : 'Approve the strategy first, then RATEB writes the copy.' ?></p>
    <?php else: ?>
      <?php if ($copyRows === []): ?>
        <p class="empty" data-i18n="no_copy"><?= $arabic ? 'النصوص جاهزة للكتابة من الاستراتيجية المعتمدة.' : 'Copy is ready to write from the approved strategy.' ?></p>
      <?php endif; ?>
      <?php foreach (rateb_copy_types() as $type): ?>
        <?php if (!isset($copyRows[$type])) { continue; } ?>
        <?php $row = $copyRows[$type]; $approved = (string) $row['approval_status'] === 'approved'; $copyId = 'copy-' . (int) $row['id']; ?>
        <article class="copy-card" data-output-id="<?= (int) $row['id'] ?>" data-output-type="<?= e($type) ?>" data-approved="<?= $approved ? '1' : '0' ?>">
          <h3 data-i18n="type_<?= e($type) ?>"><?= e($copyLabels[$type] ?? $type) ?></h3>
          <div id="<?= e($copyId) ?>" class="copy-body">
            <?php foreach (rateb_field_parts((string) $row['content']) as $part): ?>
              <?php if ($part['code'] !== ''): ?><span class="eyebrow" data-i18n="<?= $part['code'] === 'ar' ? 'lang_ar' : 'lang_en' ?>"><?= e($part['code'] === 'ar' ? ($arabic ? 'العربية' : 'Arabic') : ($arabic ? 'الإنجليزية' : 'English')) ?></span><?php endif; ?>
              <p><?= nl2br(e($part['text'])) ?></p>
            <?php endforeach; ?>
          </div>
          <p class="badge" data-i18n="<?= $approved ? 'copy_approved' : 'copy_draft' ?>"><?= $approved ? ($arabic ? 'معتمد' : 'Approved') : ($arabic ? 'مسودة' : 'Draft') ?></p>
          <div class="action-row">
            <button type="button" class="quiet" data-copy="<?= e($copyId) ?>" data-i18n="copy_button"><?= $arabic ? 'نسخ' : 'Copy' ?></button>
            <form data-ai="1" data-status="copy-status" data-busy="generating_copy" data-locked="<?= $approved ? '1' : '0' ?>">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="campaign_id" value="<?= $id ?>">
              <input type="hidden" name="stage" value="copy">
              <input type="hidden" name="output_type" value="<?= e($type) ?>">
              <input type="hidden" name="confirm" value="0">
              <button class="quiet" type="submit" data-i18n="regenerate"><?= $arabic ? 'إعادة توليد' : 'Regenerate' ?></button>
            </form>
            <?php if (!$approved): ?>
              <form method="post" action="/app/content/approve.php">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="campaign_id" value="<?= $id ?>">
                <input type="hidden" name="output_id" value="<?= (int) $row['id'] ?>">
                <button class="primary" type="submit" data-i18n="approve_output"><?= $arabic ? 'اعتماد' : 'Approve' ?></button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
      <form id="copy-form" data-ai="1" data-status="copy-status" data-busy="generating_copy" data-locked="<?= $copyApproved ? '1' : '0' ?>">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="campaign_id" value="<?= $id ?>">
        <input type="hidden" name="stage" value="copy">
        <input type="hidden" name="confirm" value="0">
        <button class="primary" type="submit" data-i18n="<?= $copyRows ? 'regenerate_copy' : 'start_copy' ?>"><?= $copyRows ? ($arabic ? 'إعادة توليد' : 'Regenerate') : ($arabic ? 'ابدأ كتابة الإعلان' : 'Generate Copy') ?></button>
      </form>
      <p id="copy-status" class="muted"></p>
    <?php endif; ?>
  </section>

  <section class="panel" id="images" data-panel="images">
    <h2 data-i18n="stage_images"><?= $arabic ? 'الصور' : 'Images' ?></h2>
    <p class="empty" data-i18n="stage_images_next"><?= $arabic ? 'مرحلة الصور لاحقًا. لا توجد صور حملة مولَّدة هنا.' : 'The image stage comes later. No generated campaign images are shown here.' ?></p>
  </section>
  <section class="panel" id="voice" data-panel="voice">
    <h2 data-i18n="stage_voice"><?= $arabic ? 'الصوت' : 'Voice' ?></h2>
    <p class="empty" data-i18n="stage_voice_next"><?= $arabic ? 'مرحلة الصوت لاحقًا.' : 'The voice stage comes later.' ?></p>
  </section>
  <section class="panel" id="video" data-panel="video">
    <h2 data-i18n="stage_video"><?= $arabic ? 'الفيديو' : 'Video' ?></h2>
    <p class="empty" data-i18n="stage_video_locked"><?= $arabic ? 'توليد الفيديو غير متاح في هذه المرحلة.' : 'Video generation is not available in this stage.' ?></p>
  </section>
  <section class="panel" id="ready" data-panel="ready">
    <h2 data-i18n="stage_ready"><?= $arabic ? 'الحملة الجاهزة' : 'Ready campaign' ?></h2>
    <p class="empty" data-i18n="stage_ready_next"><?= $arabic ? 'الحملة الجاهزة تأتي بعد اكتمال المراحل السابقة.' : 'The ready campaign comes after the earlier stages are complete.' ?></p>
  </section>
</main>
<script src="/public/assets/js/app.js?v=act2"></script>
</body>
</html>

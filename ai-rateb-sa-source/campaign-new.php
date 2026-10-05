<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/plans.php';
require_once __DIR__ . '/app/media/library.php';
require_once __DIR__ . '/app/vision.php';
require_once __DIR__ . '/app/idea.php';
require_login();

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['draft'])) {
    $path = rateb_draft_path($userId, (string) $_GET['draft']);
    if ($path === null) {
        http_response_code(404);
        exit('Not found.');
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'webm' => 'video/webm'];
    if (!isset($types[$ext])) {
        http_response_code(404);
        exit('Not found.');
    }
    header('Content-Type: ' . $types[$ext]);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

$draft = is_array($_SESSION['campaign_draft'] ?? null) ? $_SESSION['campaign_draft'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $step = (string) ($_POST['step'] ?? '');
    if ($step === 'understand') {
        $idea = trim((string) ($_POST['idea'] ?? ''));
        $draft['language'] = rateb_valid_language((string) ($_POST['campaign_language'] ?? 'auto'));
        $draft['error'] = '';
        $keep = [];
        foreach ((array) ($_POST['keep'] ?? []) as $name) {
            if (rateb_draft_path($userId, (string) $name) !== null) {
                $keep[] = (string) $name;
            }
        }
        $kept = [];
        foreach ((array) ($draft['files'] ?? []) as $file) {
            if (in_array((string) ($file['stored'] ?? ''), $keep, true)) {
                $kept[] = $file;
            }
        }
        rateb_draft_clear($userId, $keep);
        if ($idea === '') {
            $draft['idea'] = '';
            $draft['files'] = $kept;
            $draft['ready'] = false;
            $draft['error'] = 'idea_required';
            $_SESSION['campaign_draft'] = $draft;
            header('Location: /campaign-new.php');
            exit;
        }
        if (mb_strlen($idea) > 2000) {
            $draft['idea'] = mb_substr($idea, 0, 2000);
            $draft['files'] = $kept;
            $draft['ready'] = false;
            $draft['error'] = 'idea_long';
            $_SESSION['campaign_draft'] = $draft;
            header('Location: /campaign-new.php');
            exit;
        }
        $draft['idea'] = $idea;
        $accepted = rateb_idea_accept_files($userId, rateb_user_plan(db(), $userId), $kept);
        $draft['files'] = $accepted['files'];
        if ($accepted['error'] !== '') {
            $draft['ready'] = false;
            $draft['error'] = $accepted['error'];
            $_SESSION['campaign_draft'] = $draft;
            header('Location: /campaign-new.php');
            exit;
        }
        $draft['extract'] = rateb_read_idea($idea);
        $draft['ready'] = trim((string) ($draft['extract']['product'] ?? '')) !== '';
        $_SESSION['campaign_draft'] = $draft;
        header('Location: /campaign-new.php?view=' . ($draft['ready'] ? 'confirm' : 'ask'));
        exit;
    }
    if ($step === 'answer') {
        $product = rateb_idea_clean((string) ($_POST['product'] ?? ''));
        if ($product === '' || trim((string) ($draft['idea'] ?? '')) === '') {
            $draft['error'] = 'ask_required';
            $_SESSION['campaign_draft'] = $draft;
            header('Location: /campaign-new.php?view=ask');
            exit;
        }
        if (!is_array($draft['extract'] ?? null)) {
            $draft['extract'] = rateb_read_idea((string) $draft['idea']);
        }
        $draft['extract']['product'] = $product;
        $draft['ready'] = true;
        $draft['error'] = '';
        $_SESSION['campaign_draft'] = $draft;
        header('Location: /campaign-new.php?view=confirm');
        exit;
    }
    if ($step === 'drop') {
        $stored = (string) ($_POST['stored'] ?? '');
        $files = [];
        foreach ((array) ($draft['files'] ?? []) as $file) {
            if ((string) ($file['stored'] ?? '') === $stored) {
                $path = rateb_draft_path($userId, $stored);
                if ($path !== null) {
                    @unlink($path);
                }
                continue;
            }
            $files[] = $file;
        }
        $draft['files'] = $files;
        $_SESSION['campaign_draft'] = $draft;
        header('Location: ' . (!empty($draft['ready']) ? '/campaign-new.php?view=confirm' : '/campaign-new.php'));
        exit;
    }
    if ($step === 'revise') {
        $draft['ready'] = false;
        $draft['error'] = '';
        $_SESSION['campaign_draft'] = $draft;
        header('Location: /campaign-new.php');
        exit;
    }
    if ($step === 'confirm') {
        try {
            $campaignId = rateb_idea_commit($userId, $draft);
            header('Location: /campaign.php?id=' . $campaignId);
            exit;
        } catch (Throwable $error) {
            $draft['error'] = 'save_failed';
            $_SESSION['campaign_draft'] = $draft;
            header('Location: /campaign-new.php?view=confirm');
            exit;
        }
    }
    header('Location: /campaign-new.php');
    exit;
}

$view = (string) ($_GET['view'] ?? 'idea');
if ($view === 'confirm' && empty($draft['ready'])) {
    $view = trim((string) ($draft['idea'] ?? '')) !== '' ? 'ask' : 'idea';
}
if ($view === 'ask' && !empty($draft['ready'])) {
    $view = 'confirm';
}
if (!in_array($view, ['idea', 'ask', 'confirm'], true)) {
    $view = 'idea';
}
$error = (string) ($draft['error'] ?? '');
if ($error !== '') {
    unset($_SESSION['campaign_draft']['error']);
}
$arabic = ui_language() === 'ar';
$language = rateb_valid_language((string) ($draft['language'] ?? 'auto'));
$extract = is_array($draft['extract'] ?? null) ? $draft['extract'] : [];
$files = is_array($draft['files'] ?? null) ? $draft['files'] : [];
$imageCount = 0;
$videoCount = 0;
$hasVideo = false;
foreach ($files as $file) {
    if (($file['kind'] ?? '') === 'video') {
        $videoCount++;
        $hasVideo = true;
    } else {
        $imageCount++;
    }
}
$langKey = ['ar' => 'lang_ar', 'en' => 'lang_en', 'bilingual' => 'lang_bilingual', 'auto' => 'lang_auto'][$language];
$errorKeys = ['idea_required', 'idea_long', 'file_type', 'file_size', 'video_one', 'video_long', 'video_unknown', 'plan_images', 'plan_videos', 'ask_required', 'save_failed'];
?>
<!doctype html>
<html lang="<?= $arabic ? 'ar' : 'en' ?>" dir="<?= $arabic ? 'rtl' : 'ltr' ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=idea1">
</head>
<body class="studio page-new">
<header class="top">
  <a class="brand" href="/dashboard.php">RATEB AI</a>
  <nav>
    <a href="/dashboard.php" data-i18n="dashboard"><?= $arabic ? 'لوحة التحكم' : 'Dashboard' ?></a>
    <a href="/logout.php" data-i18n="logout"><?= $arabic ? 'تسجيل الخروج' : 'Logout' ?></a>
    <button type="button" id="lang" class="icon"><?= $arabic ? 'English' : 'عربي' ?></button>
    <button type="button" id="theme" class="icon">☾</button>
  </nav>
</header>
<main class="wrap idea-shell">
<?php if ($view === 'ask'): ?>
  <section class="card idea-card">
    <h1 data-i18n="ask_product"><?= $arabic ? 'وش المنتج أو الخدمة؟' : 'What is the product or service?' ?></h1>
    <p class="muted" data-i18n="ask_lead"><?= $arabic ? 'باقي هالمعلومة فقط.' : 'Only this detail is still missing.' ?></p>
    <?php if (in_array($error, $errorKeys, true)): ?><p class="idea-error" data-i18n="<?= e($error) ?>"><?= e(rateb_idea_message($error)) ?></p><?php endif; ?>
    <form method="post" action="/campaign-new.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="step" value="answer">
      <textarea name="product" required maxlength="160"></textarea>
      <button class="primary idea-go" type="submit" data-i18n="ask_continue"><?= $arabic ? 'متابعة' : 'Continue' ?></button>
    </form>
  </section>
<?php elseif ($view === 'confirm'): ?>
  <section class="card idea-card brief-card">
    <h1 data-i18n="understood_title"><?= $arabic ? 'فهمت فكرتك 👌' : 'I understood your idea' ?></h1>
    <?php if (in_array($error, $errorKeys, true)): ?><p class="idea-error" data-i18n="<?= e($error) ?>"><?= e(rateb_idea_message($error)) ?></p><?php endif; ?>
    <dl class="brief-list">
      <div><dt data-i18n="brief_product"><?= $arabic ? 'المنتج' : 'Product' ?></dt><dd><?= e((string) ($extract['product'] ?? '')) ?></dd></div>
      <?php if (trim((string) ($extract['location'] ?? '')) !== ''): ?>
        <div><dt data-i18n="brief_location"><?= $arabic ? 'الموقع' : 'Location' ?></dt><dd><?= e((string) $extract['location']) ?></dd></div>
      <?php endif; ?>
      <?php if (trim((string) ($extract['objective'] ?? '')) !== ''): ?>
        <div><dt data-i18n="brief_objective"><?= $arabic ? 'الهدف' : 'Goal' ?></dt><dd><?= e((string) $extract['objective']) ?></dd></div>
      <?php endif; ?>
      <?php if (!empty($extract['channels'])): ?>
        <div><dt data-i18n="<?= count((array) $extract['channels']) > 1 ? 'brief_channels' : 'brief_channel' ?>"><?= $arabic ? (count((array) $extract['channels']) > 1 ? 'القنوات' : 'القناة') : (count((array) $extract['channels']) > 1 ? 'Channels' : 'Channel') ?></dt><dd><?= e(implode(' · ', (array) $extract['channels'])) ?></dd></div>
      <?php endif; ?>
      <div><dt data-i18n="brief_audience"><?= $arabic ? 'الجمهور' : 'Audience' ?></dt><dd><?php if (trim((string) ($extract['audience'] ?? '')) !== ''): ?><?= e((string) $extract['audience']) ?><?php else: ?><span data-i18n="audience_unknown"><?= $arabic ? 'غير محدد' : 'Not specified' ?></span><?php endif; ?></dd></div>
      <?php if (trim((string) ($extract['brand_tone'] ?? '')) !== ''): ?>
        <div><dt data-i18n="brief_tone"><?= $arabic ? 'نبرة العلامة' : 'Brand tone' ?></dt><dd><?= e((string) $extract['brand_tone']) ?></dd></div>
      <?php endif; ?>
      <div><dt data-i18n="brief_language"><?= $arabic ? 'اللغة' : 'Language' ?></dt><dd><span data-i18n="<?= e($langKey) ?>"><?= $arabic ? ['ar' => 'العربية', 'en' => 'الإنجليزية', 'bilingual' => 'العربية والإنجليزية', 'auto' => 'خل RATEB يختار'][$language] : ['ar' => 'Arabic', 'en' => 'English', 'bilingual' => 'Arabic and English', 'auto' => 'Let RATEB choose'][$language] ?></span></dd></div>
      <?php if ($imageCount > 0 || $videoCount > 0): ?>
        <div><dt data-i18n="brief_files"><?= $arabic ? 'المواد المرفقة' : 'Attached files' ?></dt><dd><?php if ($imageCount > 0): ?><?= $imageCount ?> <span data-i18n="files_photos"><?= $arabic ? 'صور' : 'photos' ?></span><?php endif; ?><?php if ($imageCount > 0 && $videoCount > 0): ?> <span data-i18n="files_and"><?= $arabic ? 'و' : 'and' ?></span> <?php endif; ?><?php if ($videoCount > 0): ?><span data-i18n="files_video"><?= $arabic ? 'فيديو' : 'a video' ?></span><?php endif; ?></dd></div>
      <?php endif; ?>
    </dl>
    <?php foreach ($files as $file): ?>
      <?php if (($file['analysis'] ?? '') === 'ready' && trim((string) ($file['summary'] ?? '')) !== ''): ?>
        <p><?= e((string) $file['summary']) ?></p>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php
      $imagesPending = false;
      foreach ($files as $file) {
          if (($file['kind'] ?? '') !== 'video' && ($file['analysis'] ?? '') !== 'ready') {
              $imagesPending = true;
          }
      }
    ?>
    <?php if ($imagesPending): ?><p class="muted" data-i18n="images_not_analyzed"><?= $arabic ? 'ما تم تحليل الصور.' : 'The photos were not analyzed.' ?></p><?php endif; ?>
    <?php if ($videoCount > 0): ?><p class="muted" data-i18n="video_not_analyzed"><?= $arabic ? 'ما تم تحليل الفيديو.' : 'The video was not analyzed.' ?></p><?php endif; ?>
    <div class="pick-grid">
      <?php foreach ($files as $file): ?>
        <figure class="pick">
          <?php if (($file['kind'] ?? '') === 'video'): ?>
            <video src="/campaign-new.php?draft=<?= e((string) $file['stored']) ?>" controls></video>
          <?php else: ?>
            <img src="/campaign-new.php?draft=<?= e((string) $file['stored']) ?>" alt="">
          <?php endif; ?>
          <figcaption><?= e((string) ($file['name'] ?? '')) ?></figcaption>
          <form method="post" action="/campaign-new.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="step" value="drop">
            <input type="hidden" name="stored" value="<?= e((string) $file['stored']) ?>">
            <button type="submit" class="quiet" data-i18n="remove_file"><?= $arabic ? 'حذف' : 'Remove' ?></button>
          </form>
        </figure>
      <?php endforeach; ?>
    </div>
    <div class="idea-actions">
      <form method="post" action="/campaign-new.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="step" value="confirm">
        <button class="primary idea-go" type="submit" data-i18n="confirm_start"><?= $arabic ? '✨ اعتمد وابدأ' : '✨ Confirm and start' ?></button>
      </form>
      <form method="post" action="/campaign-new.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="step" value="revise">
        <button type="submit" class="quiet" data-i18n="revise_idea"><?= $arabic ? 'تعديل' : 'Edit' ?></button>
      </form>
    </div>
  </section>
<?php else: ?>
  <section class="card idea-card">
    <h1 data-i18n="idea_title"><?= $arabic ? 'وش تبي تسوّق اليوم؟' : 'What do you want to market today?' ?></h1>
    <p class="muted" data-i18n="idea_lead"><?= $arabic ? 'اكتب فكرتك بطريقتك...' : 'Describe the idea in your own words.' ?></p>
    <?php if (in_array($error, $errorKeys, true)): ?><p class="idea-error" data-i18n="<?= e($error) ?>"><?= e(rateb_idea_message($error)) ?></p><?php endif; ?>
    <form id="idea-form" method="post" action="/campaign-new.php" enctype="multipart/form-data"<?= $hasVideo ? ' data-has-video="1"' : '' ?>>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="step" value="understand">
      <textarea class="idea-box" name="idea" required maxlength="2000" data-i18n-placeholder="idea_placeholder" placeholder="<?= $arabic ? 'مثال: عندي عطر جديد وأبغى أطلقه في الرياض وأزيد مبيعات الواتساب.' : 'Example: I have a new fragrance and I want to launch it in Riyadh and increase WhatsApp sales.' ?>"><?= e((string) ($draft['idea'] ?? '')) ?></textarea>
      <div class="pick-grid" id="idea-server-files">
        <?php foreach ($files as $file): ?>
          <figure class="pick">
            <?php if (($file['kind'] ?? '') === 'video'): ?>
              <video src="/campaign-new.php?draft=<?= e((string) $file['stored']) ?>" muted playsinline></video>
            <?php else: ?>
              <img src="/campaign-new.php?draft=<?= e((string) $file['stored']) ?>" alt="">
            <?php endif; ?>
            <figcaption><?= e((string) ($file['name'] ?? '')) ?></figcaption>
            <input type="hidden" name="keep[]" value="<?= e((string) $file['stored']) ?>">
          </figure>
        <?php endforeach; ?>
      </div>
      <div class="pick-grid" id="idea-picks"></div>
      <div class="progress" id="upload-progress" hidden><span id="upload-bar"></span></div>
      <div class="idea-tools">
        <label class="attach">
          <input id="idea-files" type="file" name="files[]" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" multiple>
          <span data-i18n="idea_attach"><?= $arabic ? '＋ أضف صورًا أو فيديو' : '＋ Add photos or a video' ?></span>
        </label>
        <p class="muted" data-i18n="idea_optional"><?= $arabic ? 'المرفقات اختيارية. تقدر تكمل بدون ملفات.' : 'Attachments are optional. You can continue without files.' ?></p>
      </div>
      <div class="lang-block">
        <p class="lang-title" data-i18n="campaign_language"><?= $arabic ? 'لغة الحملة' : 'Campaign language' ?></p>
        <p class="muted" data-i18n="language_note"><?= $arabic ? 'لغة الحملة مستقلة عن لغة الصفحة.' : 'Campaign language is separate from the page language.' ?></p>
        <div class="lang-choices">
          <?php foreach (['ar' => 'lang_ar', 'en' => 'lang_en', 'bilingual' => 'lang_bilingual', 'auto' => 'lang_auto'] as $value => $key): ?>
            <label class="lang-choice">
              <input type="radio" name="campaign_language" value="<?= e($value) ?>"<?= $language === $value ? ' checked' : '' ?>>
              <span data-i18n="<?= e($key) ?>"><?php
                $labels = $arabic
                    ? ['lang_ar' => 'العربية', 'lang_en' => 'الإنجليزية', 'lang_bilingual' => 'العربية والإنجليزية', 'lang_auto' => 'خل RATEB يختار']
                    : ['lang_ar' => 'Arabic', 'lang_en' => 'English', 'lang_bilingual' => 'Arabic and English', 'lang_auto' => 'Let RATEB choose'];
                echo e($labels[$key]);
              ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="primary idea-go" type="submit" data-i18n="idea_submit"><?= $arabic ? '✨ ابنِ حملتي' : '✨ Build my campaign' ?></button>
    </form>
    <?php if ($files !== []): ?>
      <div class="server-removes">
        <?php foreach ($files as $file): ?>
          <form method="post" action="/campaign-new.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="step" value="drop">
            <input type="hidden" name="stored" value="<?= e((string) $file['stored']) ?>">
            <button type="submit" class="quiet"><span data-i18n="remove_file"><?= $arabic ? 'حذف' : 'Remove' ?></span> <?= e((string) ($file['name'] ?? '')) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
</main>
<script src="/public/assets/js/app.js?v=idea1"></script>
</body>
</html>

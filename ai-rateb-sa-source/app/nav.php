<?php
declare(strict_types=1);

function rateb_site_header(string $here, bool $loggedIn, bool $popup = false): void
{
    $workspace = [
        ['/dashboard', 'dashboard', 'Dashboard'],
        ['/campaign-new.php', 'new_campaign', 'New Campaign'],
    ];
    if ($loggedIn) {
        $workspace[] = ['/brand.php', 'brand_kit', 'Brand kit'];
    }
    $groups = [
        $workspace,
        [
            ['/#workflow', 'nav_how', 'How it works'],
            ['/#samples', 'nav_samples', 'Examples'],
            ['/?pricing=1#pricing', 'nav_pricing', 'Pricing'],
            ['/#create', 'nav_create', 'What it creates'],
        ],
    ];
    $credits = null;
    if ($loggedIn && function_exists('rateb_account_credits') && !empty($_SESSION['user_id'])) {
        $pdo = function_exists('db') ? db() : ($GLOBALS['pdo'] ?? null);
        if ($pdo instanceof PDO) {
            $credits = (int) rateb_account_credits($pdo, (int) $_SESSION['user_id'])['credits_balance'];
        }
    }
    echo '<header class="site-bar"><div class="site-shell">';
    echo '<button type="button" class="nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="site-nav" aria-label="Menu"><span></span><span></span><span></span></button>';
    echo '<nav class="site-nav" id="site-nav">';
    foreach ($groups as $index => $group) {
        if ($index > 0) {
            echo '<span class="nav-sep" aria-hidden="true"></span>';
        }
        foreach ($group as $item) {
            $class = $item[0] === $here ? ' class="is-here" aria-current="page"' : '';
            if ($loggedIn && ($here === '/dashboard' || $here === '/dashboard.php') && $item[1] === 'nav_pricing') {
                echo '<button type="button" data-pricing-open data-i18n="nav_pricing">Pricing</button>';
                continue;
            }
            if ($loggedIn && $item[1] === 'brand_kit') {
                echo '<button type="button" data-brand-open data-i18n="brand_kit">Brand kit</button>';
                continue;
            }
            if ($loggedIn && $item[1] === 'new_campaign') {
                echo '<button type="button" data-campaign-open data-i18n="new_campaign">New Campaign</button>';
                continue;
            }
            echo '<a' . $class . ' href="' . e($item[0]) . '" data-i18n="' . e($item[1]) . '">' . e($item[2]) . '</a>';
        }
    }
    echo '</nav>';
    echo '<a class="logo" href="/">RATEB <span>AI</span></a>';
    echo '<div class="edge-auth">';
    if ($credits !== null) {
        $chipTag = ($here === '/dashboard' || $here === '/dashboard.php') ? 'button type="button" data-pricing-open' : 'a href="/?pricing=1#pricing"';
        echo '<' . $chipTag . ' class="credit-chip" title="Credits"><span aria-hidden="true">⚡</span>' . $credits . '</' . strtok($chipTag, ' ') . '>';
    }
    echo '<button type="button" id="lang" class="icon">AR</button>';
    echo '<button type="button" id="theme" class="icon">☾</button>';
    if ($loggedIn) {
        echo '<button type="button" class="account-menu-toggle" data-account-open data-i18n="nav_account">My account</button>';
    } else {
        echo '<button type="button" class="login" data-auth="login" data-i18n="login">Login</button>';
        echo '<button type="button" class="register" data-auth="register" data-i18n="create_account">Create Account</button>';
    }
    echo '</div></div></header>';
    if ($loggedIn) {
        rateb_account_dialog($here);
        rateb_checkout_dialog();
        rateb_brand_dialog();
        rateb_campaign_dialog();
    }
}

function rateb_brand_dialog(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    require_once __DIR__ . '/brand/library.php';
    $ar = ui_language() === 'ar';
    $brand = brand_owned();
    $error = (string) ($_GET['brand_error'] ?? '');
    if (!in_array($error, ['size', 'type', 'upload'], true)) {
        $error = '';
    }
    $open = isset($_GET['brand']) && (string) $_GET['brand'] === '1';
    $saved = isset($_GET['saved']) && (string) $_GET['saved'] === '1' && $open;
    echo '<dialog class="studio-popup brand-modal" id="brand-modal"' . ($open ? ' data-brand-autoopen="1"' : '') . '>';
    echo '<button type="button" class="auth-x" data-brand-close aria-label="Close">×</button>';
    echo '<div class="studio-popup-card">';
    echo '<h2 data-i18n="brand_kit_title">' . ($ar ? 'هوية العلامة' : 'Brand kit') . '</h2>';
    echo '<p class="muted studio-popup-lead">' . ($ar ? 'كل حملة لاحقة تقرأ هذه الذاكرة.' : 'Later campaigns read this memory.') . '</p>';
    if ($saved) {
        echo '<p class="notice" data-i18n="brand_saved">' . ($ar ? 'تم حفظ هوية العلامة.' : 'Brand kit saved.') . '</p>';
    }
    if ($error !== '') {
        echo '<p class="notice is-error">' . ($ar ? 'تعذر حفظ الملف.' : 'The file could not be saved.') . '</p>';
    }
    $hints = brand_field_suggestions($ar);
    $pickOrType = $ar ? 'اختر من القائمة أو اكتب يدوياً' : 'Pick a suggestion or type your own';
    echo '<form method="post" action="/app/brand/save.php" enctype="multipart/form-data" class="studio-popup-form">';
    echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    echo '<input type="hidden" name="modal" value="1">';
    echo '<p class="muted tiny studio-popup-hint">' . e($pickOrType) . '</p>';
    echo '<div class="studio-popup-grid">';
    echo '<label><span data-i18n="brand_name">' . ($ar ? 'اسم العلامة' : 'Brand name') . '</span><input name="brand_name" value="' . e((string) $brand['brand_name']) . '"></label>';
    echo '<label><span data-i18n="brand_tone">' . ($ar ? 'نبرة الخطاب' : 'Tone of voice') . '</span><input name="tone" list="brand-tone-list" value="' . e((string) $brand['tone']) . '" placeholder="' . e($pickOrType) . '"></label>';
    echo '<label><span>' . ($ar ? 'الجمهور' : 'Audience') . '</span><input name="audience" list="brand-audience-list" value="' . e((string) ($brand['audience'] ?? '')) . '" placeholder="' . e($pickOrType) . '"></label>';
    echo '<label><span>' . ($ar ? 'العرض الحالي' : 'Current offer') . '</span><input name="offer_line" list="brand-offer-list" value="' . e((string) ($brand['offer_line'] ?? '')) . '" placeholder="' . e($pickOrType) . '"></label>';
    echo '<label><span>' . ($ar ? 'ممنوع ذكره' : 'Do not mention') . '</span><input name="forbidden" list="brand-forbidden-list" value="' . e((string) ($brand['forbidden'] ?? '')) . '" placeholder="' . e($pickOrType) . '"></label>';
    echo '<label><span>' . ($ar ? 'اللهجة' : 'Dialect') . '</span><select name="dialect">';
    foreach (['' => ($ar ? 'بدون تحديد' : 'Unspecified'), 'najdi' => ($ar ? 'نجدية' : 'Najdi'), 'hijazi' => ($ar ? 'حجازية' : 'Hijazi'), 'khaleeji' => ($ar ? 'خليجية' : 'Khaleeji'), 'fusha' => ($ar ? 'فصحى إعلانية' : 'Ad Fusha')] as $code => $label) {
        $sel = (($brand['dialect'] ?? '') === $code) ? ' selected' : '';
        echo '<option value="' . e((string) $code) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select></label>';
    echo '<label><span data-i18n="brand_contact">' . ($ar ? 'بيانات التواصل' : 'Contact') . '</span><input name="contact" value="' . e((string) $brand['contact']) . '" placeholder="' . e($ar ? 'جوال / واتساب / موقع' : 'Phone / WhatsApp / website') . '"></label>';
    echo '<label><span data-i18n="brand_language">' . ($ar ? 'اللغة المفضلة' : 'Preferred language') . '</span><select name="preferred_language">';
    echo '<option value="ar"' . ((($brand['preferred_language'] ?? '') === 'ar') ? ' selected' : '') . '>العربية</option>';
    echo '<option value="en"' . ((($brand['preferred_language'] ?? '') === 'en') ? ' selected' : '') . '>English</option>';
    echo '</select></label>';
    $primary = e((string) $brand['primary_color']);
    $secondary = e((string) $brand['secondary_color']);
    echo '<label><span data-i18n="brand_primary">' . ($ar ? 'اللون الأساسي' : 'Primary color') . '</span><span class="brand-color-row"><input type="color" value="' . $primary . '" data-brand-color-for="brand-primary-hex"><input id="brand-primary-hex" name="primary_color" list="brand-color-list" value="' . $primary . '"></span></label>';
    echo '<label><span data-i18n="brand_secondary">' . ($ar ? 'اللون الثانوي' : 'Secondary color') . '</span><span class="brand-color-row"><input type="color" value="' . $secondary . '" data-brand-color-for="brand-secondary-hex"><input id="brand-secondary-hex" name="secondary_color" list="brand-color-list" value="' . $secondary . '"></span></label>';
    echo '<label class="studio-popup-span"><span data-i18n="brand_logo">' . ($ar ? 'الشعار' : 'Logo') . '</span><input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif"></label>';
    echo '</div>';
    echo '<datalist id="brand-color-list"><option value="#0f766e"></option><option value="#6d28d9"></option><option value="#111827"></option><option value="#b45309"></option><option value="#be123c"></option><option value="#0369a1"></option></datalist>';
    echo '<datalist id="brand-tone-list">';
    foreach ($hints['tone'] as $opt) {
        echo '<option value="' . e($opt) . '"></option>';
    }
    echo '</datalist><datalist id="brand-audience-list">';
    foreach ($hints['audience'] as $opt) {
        echo '<option value="' . e($opt) . '"></option>';
    }
    echo '</datalist><datalist id="brand-offer-list">';
    foreach ($hints['offer_line'] as $opt) {
        echo '<option value="' . e($opt) . '"></option>';
    }
    echo '</datalist><datalist id="brand-forbidden-list">';
    foreach ($hints['forbidden'] as $opt) {
        echo '<option value="' . e($opt) . '"></option>';
    }
    echo '</datalist>';
    if (!empty($brand['logo_stored'])) {
        echo '<p class="studio-popup-logo"><img class="brand-logo" src="/app/brand/logo.php" alt=""></p>';
    }
    echo '<button class="button studio-cta" type="submit" data-i18n="save_brand">' . ($ar ? 'حفظ هوية العلامة' : 'Save brand kit') . '</button>';
    echo '</form></div></dialog>';
}

function rateb_campaign_dialog(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $ar = ui_language() === 'ar';
    $open = isset($_GET['campaign']) && (string) $_GET['campaign'] === '1';
    $language = 'auto';
    echo '<dialog class="studio-popup campaign-modal" id="campaign-modal"' . ($open ? ' data-campaign-autoopen="1"' : '') . '>';
    echo '<ol class="campaign-wizard-steps" aria-label="' . ($ar ? 'خطوات إنشاء الحملة' : 'Campaign creation steps') . '">';
    $modalSteps = $ar
        ? ['الفكرة', 'الفهم', 'التوضيح', 'الاعتماد']
        : ['Idea', 'Understanding', 'Clarify', 'Confirm'];
    foreach ($modalSteps as $i => $label) {
        $state = $i === 0 ? 'is-current' : 'is-todo';
        echo '<li class="' . $state . '"><span class="step-num">' . ($i + 1) . '</span><span class="step-label">' . e($label) . '</span></li>';
    }
    echo '</ol>';
    echo '<div class="studio-popup-card">';
    echo '<button type="button" class="auth-x" data-campaign-close aria-label="Close">×</button>';
    echo '<h2 data-i18n="idea_title">' . ($ar ? 'وش تبي تسوّق اليوم؟' : 'What do you want to market today?') . '</h2>';
    echo '<p class="muted studio-popup-lead" data-i18n="idea_lead">' . ($ar ? 'اكتب فكرتك بطريقتك...' : 'Describe the idea in your own words.') . '</p>';
    echo '<form id="idea-form-modal" method="post" action="/campaign-new.php" enctype="multipart/form-data" class="studio-popup-form">';
    echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    echo '<input type="hidden" name="step" value="understand">';
    echo '<textarea class="idea-box" name="idea" required maxlength="2000" data-i18n-placeholder="idea_placeholder" placeholder="' . e($ar ? 'مثال: عندي عطر جديد وأبغى أطلقه في الرياض وأزيد مبيعات الواتساب.' : 'Example: I have a new fragrance and I want to launch it in Riyadh and increase WhatsApp sales.') . '"></textarea>';
    echo '<label class="goal-pick"><span>' . ($ar ? 'هدف الحملة' : 'Campaign goal') . '</span><select name="objective">';
    $goals = [
        '' => $ar ? 'خل RATEB يفهم الهدف من النص' : 'Let RATEB read the goal from the text',
        'conversion' => $ar ? 'زيادة مبيعات / تحويلات' : 'Sales / conversions',
        'awareness' => $ar ? 'زيادة وعي / انتشار' : 'Awareness / reach',
        'traffic' => $ar ? 'جلب زوار للموقع' : 'Website traffic',
        'leads' => $ar ? 'جمع بيانات عملاء محتملين' : 'Leads',
    ];
    foreach ($goals as $value => $label) {
        echo '<option value="' . e((string) $value) . '">' . e($label) . '</option>';
    }
    echo '</select></label>';
    echo '<div class="pick-grid" id="idea-picks-modal"></div>';
    echo '<div class="idea-tools"><label class="attach"><input id="idea-files-modal" type="file" name="files[]" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" multiple><span data-i18n="idea_attach">' . ($ar ? '＋ أضف صورًا أو فيديو' : '＋ Add photos or a video') . '</span></label>';
    echo '<p class="muted" data-i18n="idea_optional">' . ($ar ? 'المرفقات اختيارية.' : 'Attachments are optional.') . '</p></div>';
    echo '<div class="lang-block"><p class="lang-title" data-i18n="campaign_language">' . ($ar ? 'لغة الحملة' : 'Campaign language') . '</p>';
    echo '<div class="lang-choices">';
    foreach (['ar' => ($ar ? 'العربية' : 'Arabic'), 'en' => ($ar ? 'الإنجليزية' : 'English'), 'bilingual' => ($ar ? 'العربية والإنجليزية' : 'Arabic and English'), 'auto' => ($ar ? 'خل RATEB يختار' : 'Let RATEB choose')] as $value => $label) {
        $checked = $language === $value ? ' checked' : '';
        echo '<label class="lang-choice"><input type="radio" name="campaign_language" value="' . e($value) . '"' . $checked . '><span>' . e($label) . '</span></label>';
    }
    echo '</div></div>';
    echo '<button class="primary idea-go" type="submit" data-i18n="idea_submit">' . ($ar ? '✨ ابنِ حملتي' : '✨ Build my campaign') . '</button>';
    echo '</form></div></dialog>';
}

function rateb_checkout_dialog(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    require_once __DIR__ . '/plans.php';
    $pdo = function_exists('db') ? db() : ($GLOBALS['pdo'] ?? null);
    if (!$pdo instanceof PDO) {
        return;
    }
    $ar = ui_language() === 'ar';
    $cfgPath = dirname(__DIR__) . '/config/ai.php';
    $aiCfg = is_file($cfgPath) ? require $cfgPath : [];
    $checkoutReady = rateb_moyasar_configured(is_array($aiCfg) ? $aiCfg : []);
    $map = [];
    foreach (['starter', 'pro'] as $code) {
        $pkg = rateb_paid_package($code, $pdo);
        if ($pkg === null) {
            continue;
        }
        $map[$code] = [
            'name' => $ar ? (string) $pkg['name_ar'] : (string) $pkg['name_en'],
            'price' => rateb_format_sar_price((float) $pkg['price_sar']),
            'credits' => (int) $pkg['credits'],
        ];
    }
    $auto = (string) ($_GET['checkout'] ?? '');
    if ($auto === '' || !isset($map[$auto])) {
        $auto = '';
    }
    $packagesJson = htmlspecialchars((string) json_encode($map, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    $confirmLabel = $ar ? 'تأكيد الشراء عبر ميسر' : 'Confirm purchase with Moyasar';
    $confirmDisabled = $ar ? 'تأكيد الشراء' : 'Confirm purchase';
    $alert = $ar ? 'الدفع الإلكتروني غير متاح مؤقتًا.' : 'Online payment is temporarily unavailable.';
    $back = $ar ? 'رجوع إلى الباقات' : 'Back to packages';
    $payVia = $ar ? 'الدفع عبر ميسر' : 'Payments by Moyasar';
    $creditsWord = $ar ? 'نقطة' : 'credits';
    echo '<dialog class="checkout-modal" id="checkoutModal" data-packages="' . $packagesJson . '" data-credits-word="' . e($creditsWord) . '"' . ($auto !== '' ? ' data-checkout-autoopen="' . e($auto) . '"' : '') . '>';
    echo '<button type="button" class="auth-x" data-checkout-close aria-label="Close">×</button>';
    echo '<div class="checkout-modal-card">';
    echo '<p class="eyebrow">' . e($payVia) . '</p>';
    echo '<h2 id="checkout-title"></h2>';
    echo '<p class="price-amount"><b id="checkout-price">0</b> <span>' . ($ar ? 'ر.س' : 'SAR') . '</span></p>';
    echo '<p id="checkout-credits">0 ' . e($creditsWord) . '</p>';
    if (!$checkoutReady) {
        echo '<p class="alert">' . e($alert) . '</p>';
        echo '<button class="button" type="button" disabled>' . e($confirmDisabled) . '</button>';
    } else {
        echo '<form method="post" action="/app/payment/checkout.php" id="checkout-form">';
        echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
        echo '<input type="hidden" name="package" id="checkout-package" value="">';
        echo '<input type="hidden" name="campaign" value="0">';
        echo '<button class="button" type="submit">' . e($confirmLabel) . '</button>';
        echo '</form>';
    }
    echo '<button type="button" class="checkout-back" data-checkout-back>' . e($back) . '</button>';
    echo '</div></dialog>';
}

function rateb_account_dialog(string $here = ''): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $pdo = function_exists('db') ? db() : ($GLOBALS['pdo'] ?? null);
    if (!$pdo instanceof PDO) {
        return;
    }
    $userId = (int) $_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }

    $arabic = function_exists('ui_language') && ui_language() === 'ar';
    $hasPassword = trim((string) ($user['password_hash'] ?? '')) !== '';
    $flash = (string) ($_GET['ok'] ?? '');
    $error = (string) ($_GET['error'] ?? '');
    $returnPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? $here, PHP_URL_PATH) ?: ($here !== '' ? $here : '/dashboard'));
    if ($returnPath === '' || $returnPath[0] !== '/') {
        $returnPath = '/dashboard';
    }
    $wallet = function_exists('rateb_account_credits')
        ? rateb_account_credits($pdo, $userId)
        : ['package_type' => 'free', 'credits_balance' => 0];
    $open = isset($_GET['account']) && (string) $_GET['account'] === '1';

    echo '<dialog class="account-modal" id="account-modal"' . ($open ? ' data-account-autoopen="1"' : '') . '>';
    echo '<button type="button" class="auth-x" data-account-close aria-label="Close">×</button>';
    echo '<div class="account-modal-card">';
    echo '<h2 data-i18n="nav_account">' . ($arabic ? 'حسابي' : 'My account') . '</h2>';
    echo '<p class="muted account-modal-meta">' . ($arabic ? 'الباقة:' : 'Plan:') . ' ' . e((string) $wallet['package_type'])
        . ' · ' . ($arabic ? 'الرصيد:' : 'Credits:') . ' ' . (int) $wallet['credits_balance'] . '</p>';

    if ($flash === 'profile') {
        echo '<p class="notice" data-i18n="account_saved">' . ($arabic ? 'تم حفظ الاسم.' : 'Name saved.') . '</p>';
    }
    if ($flash === 'password') {
        echo '<p class="notice" data-i18n="account_password_saved">' . ($arabic ? 'تم تحديث كلمة المرور.' : 'Password updated.') . '</p>';
    }
    if ($error === 'name') {
        echo '<p class="notice is-error" data-i18n="account_name_invalid">' . ($arabic ? 'اكتب اسماً صالحاً.' : 'Enter a valid name.') . '</p>';
    }
    if ($error === 'password_current') {
        echo '<p class="notice is-error" data-i18n="account_password_current">' . ($arabic ? 'كلمة المرور الحالية غير صحيحة.' : 'Current password is incorrect.') . '</p>';
    }
    if ($error === 'password_short') {
        echo '<p class="notice is-error" data-i18n="account_password_short">' . ($arabic ? 'كلمة المرور الجديدة تحتاج 8 أحرف على الأقل.' : 'New password needs at least 8 characters.') . '</p>';
    }
    if ($error === 'password_match') {
        echo '<p class="notice is-error" data-i18n="account_password_match">' . ($arabic ? 'تأكيد كلمة المرور غير متطابق.' : 'Password confirmation does not match.') . '</p>';
    }
    if ($error === 'password_missing') {
        echo '<p class="notice is-error" data-i18n="account_password_missing">' . ($arabic ? 'هذا الحساب بلا كلمة مرور محلية. عيّن كلمة مرور جديدة مباشرة.' : 'This account has no local password yet. Set a new password directly.') . '</p>';
    }
    if ($error === 'csrf' || $error === 'save') {
        echo '<p class="notice is-error" data-i18n="account_save_failed">' . ($arabic ? 'تعذر الحفظ. حاول مرة أخرى.' : 'Could not save. Try again.') . '</p>';
    }

    echo '<form method="post" action="/app/account/save.php" class="account-modal-form" data-account-profile>';
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    echo '<input type="hidden" name="action" value="profile">';
    echo '<input type="hidden" name="return" value="' . e($returnPath) . '">';
    echo '<input type="hidden" name="modal" value="1">';
    echo '<label><span data-i18n="name">' . ($arabic ? 'الاسم' : 'Name') . '</span>';
    echo '<input name="name" required maxlength="120" value="' . e((string) $user['name']) . '"></label>';
    echo '<label><span data-i18n="email">' . ($arabic ? 'البريد' : 'Email') . '</span>';
    echo '<input type="email" value="' . e((string) $user['email']) . '" readonly></label>';
    echo '<p class="muted tiny" data-i18n="account_email_note">' . ($arabic ? 'لتغيير البريد تواصل مع الدعم من صفحة التواصل.' : 'To change email, contact support from the contact page.') . '</p>';
    echo '<button class="button studio-cta" type="submit" data-i18n="account_save_name">' . ($arabic ? 'حفظ الاسم' : 'Save name') . '</button>';
    echo '</form>';

    echo '<hr class="account-modal-rule">';
    echo '<h3 data-i18n="account_password_title">' . ($arabic ? 'كلمة المرور' : 'Password') . '</h3>';
    echo '<form method="post" action="/app/account/save.php" class="account-modal-form" data-account-password>';
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    echo '<input type="hidden" name="action" value="password">';
    echo '<input type="hidden" name="return" value="' . e($returnPath) . '">';
    echo '<input type="hidden" name="modal" value="1">';
    if ($hasPassword) {
        echo '<label><span data-i18n="account_current_password">' . ($arabic ? 'كلمة المرور الحالية' : 'Current password') . '</span>';
        echo '<input type="password" name="current_password" required autocomplete="current-password"></label>';
    } else {
        echo '<p class="muted tiny" data-i18n="account_set_password_lead">' . ($arabic ? 'حسابك دخل عبر مزود خارجي. يمكنك تعيين كلمة مرور للدخول بالبريد.' : 'You signed in with an external provider. You can set a password for email login.') . '</p>';
    }
    echo '<label><span data-i18n="account_new_password">' . ($arabic ? 'كلمة المرور الجديدة' : 'New password') . '</span>';
    echo '<input type="password" name="new_password" minlength="8" required autocomplete="new-password"></label>';
    echo '<label><span data-i18n="account_confirm_password">' . ($arabic ? 'تأكيد كلمة المرور' : 'Confirm password') . '</span>';
    echo '<input type="password" name="confirm_password" minlength="8" required autocomplete="new-password"></label>';
    echo '<button class="button primary" type="submit" data-i18n="account_save_password">' . ($arabic ? 'تحديث كلمة المرور' : 'Update password') . '</button>';
    echo '</form>';
    echo '<a class="account-modal-logout" href="/logout.php" data-i18n="logout">' . ($arabic ? 'تسجيل الخروج' : 'Logout') . '</a>';
    echo '</div></dialog>';
}

function rateb_auth_dialog(string $next): void
{
    $googleClient = (string) ((require __DIR__ . '/../config/google.php')['client_id'] ?? '');
    $next = e($next);
    $token = e(csrf_token());
    echo '<dialog class="auth-modal" id="auth-modal" data-google-client="' . e($googleClient) . '">';
    echo '<button type="button" class="auth-x" data-auth-close aria-label="Close">×</button>';
    echo '<form class="auth-card" id="auth-login" action="/login" method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . $token . '"><input type="hidden" name="auth_modal" value="1"><input type="hidden" name="next" value="' . $next . '">';
    echo '<h2 data-i18n="welcome_back">Welcome back</h2><p class="muted" data-i18n="login_lead">Login to your RATEB AI Campaign workspace.</p><p class="alert" data-auth-error hidden></p>';
    echo rateb_social_buttons();
    echo '<p class="auth-or" data-i18n="auth_or">or</p><label data-i18n="email">Email</label><input type="text" name="email" required autocomplete="username"><label data-i18n="password">Password</label><input type="password" name="password" required autocomplete="current-password"><button type="submit" data-i18n="login_button">Login</button>';
    echo '<p class="footer-link"><a href="/forgot-password.php" data-i18n="forgot_password">Forgot password?</a></p>';
    echo '<p class="footer-link"><span data-i18n="no_account">Don\'t have an account?</span> <button type="button" data-auth="register" data-i18n="create_one">Create one</button></p></form>';
    echo '<form class="auth-card" id="auth-register" action="/register" method="post" hidden>';
    echo '<input type="hidden" name="csrf_token" value="' . $token . '"><input type="hidden" name="auth_modal" value="1"><input type="hidden" name="next" value="' . $next . '">';
    echo '<h2 data-i18n="create_title">Create your account</h2><p class="muted" data-i18n="register_lead">Start creating AI-powered marketing campaigns.</p><p class="alert" data-auth-error hidden></p>';
    echo rateb_social_buttons();
    echo '<p class="auth-or" data-i18n="auth_or">or</p><label data-i18n="name">Name</label><input name="name" required autocomplete="name"><label data-i18n="email">Email</label><input type="email" name="email" required autocomplete="email"><label data-i18n="password">Password</label><input type="password" name="password" minlength="8" required autocomplete="new-password"><button type="submit" data-i18n="create_button">Create Account</button>';
    echo '<p class="footer-link"><span data-i18n="have_account">Already have an account?</span> <button type="button" data-auth="login" data-i18n="login">Login</button></p></form></dialog>';
    if ($googleClient !== '') {
        echo '<script src="https://accounts.google.com/gsi/client" async defer></script>';
    }
}

function rateb_social_buttons(): string
{
    $google = '<svg viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 12 24 12c3.1 0 5.8 1.2 8 3.1l5.7-5.7C34.2 6.1 29.4 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 10-2 13.6-5.2l-6.3-5.3C29.3 35.1 26.8 36 24 36c-5.3 0-9.7-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3.2-3.5 5.7-6.6 7.1l6.3 5.3C37.4 38.4 44 33 44 24c0-1.2-.1-2.3-.4-3.5z"/></svg>';
    $github = '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.6 2 12.2c0 4.5 2.9 8.3 6.9 9.6.5.1.7-.2.7-.5v-1.7c-2.8.6-3.4-1.2-3.4-1.2-.4-1.1-1.1-1.4-1.1-1.4-.9-.6.1-.6.1-.6 1 .1 1.5 1 1.5 1 .9 1.6 2.4 1.1 3 .9.1-.7.4-1.1.6-1.4-2.2-.3-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.7 0 0 .8-.3 2.8 1a9.4 9.4 0 0 1 5 0c2-1.3 2.8-1 2.8-1 .5 1.4.2 2.4.1 2.7.6.7 1 1.6 1 2.7 0 3.9-2.3 4.7-4.6 5 .4.3.7.9.7 1.9v2.8c0 .3.2.6.7.5 4-1.3 6.9-5.1 6.9-9.6C22 6.6 17.5 2 12 2z"/></svg>';
    return '<div class="social-auth"><button type="button" class="social-btn" data-provider="google"><span class="social-mark" aria-hidden="true">' . $google . '</span><span data-i18n="continue_google">Continue with Google</span><span></span></button><button type="button" class="social-btn" data-provider="github"><span class="social-mark" aria-hidden="true">' . $github . '</span><span data-i18n="continue_github">Continue with GitHub</span><span></span></button></div>';
}

function rateb_support_inbox(): string
{
    require_once __DIR__ . '/notify/mail.php';
    $from = trim((string) (rateb_mail_config()['from'] ?? ''));
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return $from;
    }
    return 'support@ai.rateb.sa';
}

function rateb_site_footer(string $here = ''): void
{
    $links = [
        ['/contact', 'nav_contact', 'Contact'],
        ['/privacy', 'nav_privacy', 'Privacy'],
        ['/terms', 'nav_terms', 'Terms'],
        ['/?pricing=1#pricing', 'nav_pricing', 'Pricing'],
    ];
    echo '<footer class="site-footer"><div class="site-shell footer-shell">';
    echo '<p class="footer-brand">RATEB <span>AI</span></p>';
    echo '<nav class="footer-nav" aria-label="Legal">';
    foreach ($links as $item) {
        $class = $item[0] === $here ? ' class="is-here" aria-current="page"' : '';
        echo '<a' . $class . ' href="' . e($item[0]) . '" data-i18n="' . e($item[1]) . '">' . e($item[2]) . '</a>';
    }
    echo '</nav>';
    echo '<p class="footer-copy muted" data-i18n="footer_copy">© RATEB AI. Campaign workspace for Saudi marketers.</p>';
    echo '</div></footer>';
}

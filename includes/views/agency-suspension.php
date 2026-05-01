<?php
/**
 * Suspension page view.
 *
 * @var array<string,string> $viewData
 */
if (!isset($viewData) || !is_array($viewData)) {
    http_response_code(500);
    echo 'View data missing.';
    return;
}
extract($viewData, EXTR_SKIP);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agency Access Suspended</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($homeCssUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($suspensionCssUrl, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="agency-suspension-page">
    <header class="header">
        <div class="header-left">
            <a href="tel:+966599863868" class="phone"><i class="fas fa-phone-alt"></i> +966 59 986 3868</a>
            <a href="<?php echo htmlspecialchars($siteBaseUrl . '/pages/home.php#contact', ENT_QUOTES, 'UTF-8'); ?>">Contact Us</a>
            <a href="https://wa.me/966599863868" target="_blank" rel="noopener noreferrer" class="live-status" title="Chat on WhatsApp">
                <div class="live-dots"><span></span><span></span><span></span></div>
                <span>Live via WhatsApp</span>
            </a>
        </div>
        <div class="header-center">
            <a href="<?php echo htmlspecialchars($siteBaseUrl . '/pages/home.php', ENT_QUOTES, 'UTF-8'); ?>" class="logo">
                <img src="<?php echo htmlspecialchars($siteBaseUrl . '/assets/ratib-logo.svg?v=3', ENT_QUOTES, 'UTF-8'); ?>" alt="Ratib Company">
            </a>
            <div class="tagline">RATIB — Recruitment Automation &amp; Tracking Intelligence Base</div>
        </div>
        <div class="header-right">
            <a href="<?php echo htmlspecialchars($siteBaseUrl . '/pages/home.php', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link">Home</a>
            <a href="<?php echo htmlspecialchars($siteBaseUrl . '/pages/home.php#programs', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link active">Our Programs <span class="badge-nav">Important</span></a>
            <a href="<?php echo htmlspecialchars($siteBaseUrl . '/pages/home.php?open=register', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link">Register</a>
            <a href="<?php echo htmlspecialchars($siteBaseUrl . '/pages/customer-portal.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn-client"><i class="fas fa-user"></i> Customer Portal</a>
        </div>
    </header>

    <main class="suspension-shell">
        <section class="suspension-card">
            <?php echo $activationNoticeHtml; ?>
            <div class="hero-row">
                <div class="alert-icon" aria-hidden="true">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <div>
                    <span class="state-badge">ACCESS RESTRICTED</span>
                    <h1>Agency Access Suspended</h1>
                    <p>This agency has been temporarily suspended due to non-payment. You can reactivate instantly or request an extension.</p>
                    <p class="agency-name">Agency: <?php echo $safeAgencyDescriptor; ?></p>
                </div>
            </div>

            <div class="info-grid">
                <section class="info-panel">
                    <h2>Agency Information</h2>
                    <div class="info-row"><span class="info-label">Agency name</span><span class="info-value"><?php echo $safeAgencyDescriptor; ?></span></div>
                    <div class="info-row"><span class="info-label">Agency ID</span><span class="info-value"><?php echo $safeAgencyIdDisplay; ?></span></div>
                    <div class="info-row"><span class="info-label">Current host</span><span class="info-value"><?php echo $safeCurrentHost; ?></span></div>
                    <div class="info-row"><span class="info-label">Support channel</span><span class="info-value"><?php echo $safeSupportEmail; ?></span></div>
                </section>

                <section class="info-panel">
                    <h2>Current Status</h2>
                    <div class="info-row"><span class="info-label">Status</span><span class="info-value badge-danger">Suspended</span></div>
                    <div class="info-row"><span class="info-label">Reason</span><span class="info-value"><?php echo $safeReasonLabel; ?></span></div>
                    <div class="info-row"><span class="info-label">Suspended since</span><span class="info-value"><?php echo $safeSuspendedSince; ?></span></div>
                    <div class="info-row"><span class="info-label">Grace period</span><span class="info-value"><?php echo $safeGracePeriod; ?></span></div>
                    <?php echo $reasonCodeRow; ?>
                </section>
            </div>

            <p class="state-message"><?php echo $safeMessage; ?></p>

            <div class="action-group">
                <a data-loading-btn class="action-btn primary" href="<?php echo $safeRenewHref; ?>">Reactivate Now / Mark as Paid</a>
                <a data-loading-btn class="action-btn secondary" href="<?php echo $safeRequestExtensionHref; ?>">Requesting 1 week activation</a>
                <a data-loading-btn class="action-btn ghost" href="<?php echo $safeContactHref; ?>">Contact Support</a>
            </div>

            <div class="extension-panel" id="extension-options">
                <h3>Quick extension request examples</h3>
                <p>One-time activation option (7 days). If not paid, suspension returns automatically.</p>
                <div class="extension-list">
                    <?php echo $weekPillsHtml; ?>
                </div>
            </div>
        </section>
    </main>

    <script src="<?php echo htmlspecialchars($suspensionJsUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>

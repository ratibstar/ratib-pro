<?php
/**
 * Public marketing footer — pill links match header nav (SVG glyphs + semantic colors).
 *
 * Expects: $baseUrl, $ratibHome, $ratibPhoneDigits, $ratibPhoneRaw (from ratib-home-public-nav-bootstrap.php).
 * Optional $ratibHomeNavHrefPrefix: when set (e.g. partner-portal-login), hash links target home.php#… .
 *
 * SVG symbols reference #ratib-ng-* from the header sprite (same document).
 *
 * @var string $baseUrl
 * @var array<string,mixed> $ratibHome
 */
$ratibFooterPrefix = isset($ratibHomeNavHrefPrefix) ? (string) $ratibHomeNavHrefPrefix : '';
if (!function_exists('ratib_mega_nav_resolve_href')) {
    $resolveMain = __DIR__ . '/ratib-mega-nav-resolve.php';
    if (is_file($resolveMain)) {
        require_once $resolveMain;
    } else {
        require_once __DIR__ . '/ratib-mega-nav-resolve.fallback.php';
    }
}
$ratibFooterNav = static function (string $hrefKey) use ($baseUrl, $ratibFooterPrefix): string {
    return ratib_mega_nav_resolve_href($hrefKey, $baseUrl, $ratibFooterPrefix);
};

$ratibFooterPricingLabel = trim((string) ($ratibHome['home.footer.link.platform.pricing'] ?? ''));
if ($ratibFooterPricingLabel === '') {
    $ratibFooterPricingLabel = trim((string) ($ratibHome['home.nav.pricing'] ?? ''));
}
if ($ratibFooterPricingLabel === '') {
    $ratibFooterPricingLabel = (string) ($ratibHome['home.nav.programs'] ?? '');
}

$ratibGlyph = function (string $symbolId): string {
    return '<span class="ratib-nav__icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#' . htmlspecialchars($symbolId, ENT_QUOTES, 'UTF-8') . '"/></svg></span>';
};
?>
    <footer class="ratib-footer-enterprise" id="contact">
        <div class="ratib-container ratib-footer-enterprise__grid">
            <div class="ratib-footer-enterprise__brand">
                <a href="<?php echo htmlspecialchars(function_exists('ratib_public_marketing_home_url') ? ratib_public_marketing_home_url($baseUrl) : $baseUrl . '/pages/home.php', ENT_QUOTES, 'UTF-8'); ?>" class="ratib-footer-enterprise__logo">
                    <img src="<?php echo htmlspecialchars($baseUrl . '/assets/ratib-logo.svg?v=6'); ?>" alt="RATEB" width="112" height="32">
                </a>
                <p><?php echo htmlspecialchars($ratibHome['home.footer.brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.platform'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul class="ratib-footer-enterprise__link-list">
                    <li>
                        <a href="<?php echo htmlspecialchars($ratibFooterNav('platform'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-platform'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.footer.link.platform.overview'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratibFooterNav('domains'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-domains'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.nav.domains'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars(ratib_public_nav_tour_href($baseUrl, $ratibFooterPrefix, $ratibNavProductTourHref), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-video'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibNavProductTourLabel, ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratibFooterNav('features'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-features'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars(trim((string) ($ratibHome['home.nav.product'] ?? '')) ?: (string) ($ratibHome['home.nav.features'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratibFooterNav('programs'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-programs'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibFooterPricingLabel, ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratibFooterNav('agencies'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-agencies'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars(trim((string) ($ratibHome['home.nav.partners'] ?? '')) ?: (string) ($ratibHome['home.nav.agencies'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratibFooterNav('contact'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-contact'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.nav.contact'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.company'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul class="ratib-footer-enterprise__link-list">
                    <li>
                        <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/profile/#company-profile', ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link ratib-footer-link--about ratib-nav__go-profile" data-ratib-profile-nav="1" data-ratib-go-profile="1"><?php echo $ratibGlyph('ratib-ng-solutions'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars(trim((string) ($ratibHome['home.footer.link.company.about'] ?? '')) ?: 'Company profile', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratibFooterNav('solutions'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-solutions'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.footer.link.solutions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratibFooterNav('how_it_works'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-agency'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.nav.how_it_works'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratibFooterNav('operational'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-operational'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.nav.operational'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars(ratib_public_nav_tour_href($baseUrl, $ratibFooterPrefix, '#video'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-video'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.footer.link.demo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($baseUrl . '/pages/customer-portal.php'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link ratib-footer-link--customer-portal"><?php echo $ratibGlyph('ratib-ng-partner'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.footer.link.company.customer_portal'] ?? 'Customer portal', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.support'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul class="ratib-footer-enterprise__link-list">
                    <li>
                        <a href="<?php echo htmlspecialchars($baseUrl . '/pages/login.php'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link ratib-footer-link--support-tickets"><?php echo $ratibGlyph('ratib-ng-contact'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.footer.link.support.tickets'] ?? 'Support tickets', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="https://wa.me/<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="ratib-nav__link ratib-footer-enterprise__nav-link ratib-footer-link--whatsapp"><span class="ratib-nav__icon ratib-nav__icon--fa" aria-hidden="true"><i class="fab fa-whatsapp"></i></span><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.footer.link.support.whatsapp'] ?? 'WhatsApp', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="tel:+<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" dir="ltr" class="ratib-nav__link ratib-footer-enterprise__nav-link ratib-footer-link--phone"><span class="ratib-nav__icon ratib-nav__icon--fa" aria-hidden="true"><i class="fas fa-phone-alt"></i></span><span class="ratib-nav__label ratib-topbar__phone-text"><?php echo htmlspecialchars($ratibPhoneRaw, ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                </ul>
            </div>
            <div class="ratib-footer-col ratib-footer-col--enterprise">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.enterprise'] ?? 'Enterprise', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul class="ratib-footer-enterprise__link-list">
                    <li>
                        <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/security-compliance/', ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link ratib-footer-link--security"><?php echo $ratibGlyph('ratib-ng-operational'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars(trim((string) ($ratibHome['home.footer.link.enterprise.security'] ?? '')) ?: 'Security & Compliance', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/architecture/', ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link ratib-footer-link--architecture"><?php echo $ratibGlyph('ratib-ng-platform'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars(trim((string) ($ratibHome['home.footer.link.enterprise.architecture'] ?? '')) ?: 'Architecture', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/procurement-legal/', ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link ratib-footer-link--procurement"><?php echo $ratibGlyph('ratib-ng-programs'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars(trim((string) ($ratibHome['home.footer.link.enterprise.procurement'] ?? '')) ?: 'Procurement & Legal', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratibFooterNav('operational'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-operational'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars(trim((string) ($ratibHome['home.footer.link.enterprise.ops_sla'] ?? '')) ?: 'Operations & SLA', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.legal'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul class="ratib-footer-enterprise__link-list">
                    <li>
                        <a href="<?php echo htmlspecialchars($ratibFooterNav('register'), ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-footer-enterprise__nav-link"><?php echo $ratibGlyph('ratib-ng-programs'); ?><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.footer.link.service_registration'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="mailto:info@out.ratib.sa" class="ratib-nav__link ratib-footer-enterprise__nav-link ratib-footer-link--mailto"><?php echo $ratibGlyph('ratib-ng-contact'); ?><span class="ratib-nav__label">info@out.ratib.sa</span></a>
                    </li>
                </ul>
            </div>
            <div class="ratib-footer-col ratib-footer-enterprise__infra">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.infra'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <p class="ratib-footer-enterprise__infra-copy"><?php echo htmlspecialchars($ratibHome['home.footer.infra.copy'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="ratib-footer-social">
                    <a href="https://wa.me/<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="mailto:info@out.ratib.sa" aria-label="Email"><i class="fas fa-envelope"></i></a>
                </div>
                <div class="footer-subscribe ratib-footer-newsletter">
                    <label class="ratib-footer-newsletter__label" for="footerEmail"><?php echo htmlspecialchars($ratibHome['home.footer.newsletter.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="email" placeholder="<?php echo htmlspecialchars($ratibHome['home.footer.newsletter.placeholder'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" id="footerEmail" name="footer_email" autocomplete="email" aria-label="Email for newsletter">
                    <button type="button" class="btn-sub" id="footerSubscribe"><?php echo htmlspecialchars($ratibHome['home.footer.newsletter.button'] ?? '', ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
            </div>
        </div>
        <div class="ratib-footer-system-strip">
            <div class="ratib-container ratib-footer-system-strip__inner">
                <span class="ratib-footer-system-strip__item"><span class="ratib-mono-tag">uptime</span> <?php echo htmlspecialchars($ratibHome['home.footer.strip.1'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="ratib-footer-system-strip__item"><span class="ratib-mono-tag">requests</span> <?php echo htmlspecialchars($ratibHome['home.footer.strip.2'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="ratib-footer-system-strip__item"><span class="ratib-mono-tag">events</span> <?php echo htmlspecialchars($ratibHome['home.footer.strip.3'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="ratib-footer-enterprise__bottom">
            <div class="ratib-container ratib-footer-enterprise__bottom-inner">
                <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($ratibHome['home.footer.copyright_suffix'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="ratib-footer-enterprise__loc"><i class="fas fa-location-dot" aria-hidden="true"></i> <?php echo htmlspecialchars($ratibHome['home.footer.location'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </footer>

<?php
/**
 * Public marketing footer — pill links match header nav (SVG glyphs + semantic colors).
 *
 * Expects: $baseUrl, $ratebHome, $ratebPhoneDigits, $ratebPhoneRaw (from rateb-home-public-nav-bootstrap.php).
 * Optional $ratebHomeNavHrefPrefix: when set (e.g. partner-portal-login), hash links target home.php#… .
 *
 * SVG symbols reference #rateb-ng-* from the header sprite (same document).
 *
 * @var string $baseUrl
 * @var array<string,mixed> $ratebHome
 */
$ratebFooterPrefix = isset($ratebHomeNavHrefPrefix) ? (string) $ratebHomeNavHrefPrefix : '';
if (!function_exists('rateb_mega_nav_resolve_href')) {
    $resolveMain = __DIR__ . '/rateb-mega-nav-resolve.php';
    if (is_file($resolveMain)) {
        require_once $resolveMain;
    } else {
        require_once __DIR__ . '/rateb-mega-nav-resolve.fallback.php';
    }
}
$ratebFooterNav = static function (string $hrefKey) use ($baseUrl, $ratebFooterPrefix): string {
    return rateb_mega_nav_resolve_href($hrefKey, $baseUrl, $ratebFooterPrefix);
};

$ratebFooterPricingLabel = trim((string) ($ratebHome['home.footer.link.platform.pricing'] ?? ''));
if ($ratebFooterPricingLabel === '') {
    $ratebFooterPricingLabel = trim((string) ($ratebHome['home.nav.pricing'] ?? ''));
}
if ($ratebFooterPricingLabel === '') {
    $ratebFooterPricingLabel = (string) ($ratebHome['home.nav.programs'] ?? '');
}

$ratebGlyph = function (string $symbolId): string {
    return '<span class="rateb-nav__icon" aria-hidden="true"><svg class="rateb-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#' . htmlspecialchars($symbolId, ENT_QUOTES, 'UTF-8') . '"/></svg></span>';
};
?>
    <footer class="rateb-footer-enterprise" id="contact">
        <div class="rateb-container rateb-footer-enterprise__grid">
            <div class="rateb-footer-enterprise__brand">
                <a href="<?php echo htmlspecialchars(function_exists('rateb_public_marketing_home_url') ? rateb_public_marketing_home_url($baseUrl) : $baseUrl . '/pages/home.php', ENT_QUOTES, 'UTF-8'); ?>" class="rateb-footer-enterprise__logo">
                    <img src="<?php echo htmlspecialchars($baseUrl . '/assets/rateb-logo.svg?v=6'); ?>" alt="RATEB" width="112" height="32">
                </a>
                <p><?php echo htmlspecialchars($ratebHome['home.footer.brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="rateb-footer-col">
                <h4><?php echo htmlspecialchars($ratebHome['home.footer.col.platform'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul class="rateb-footer-enterprise__link-list">
                    <li>
                        <a href="<?php echo htmlspecialchars($ratebFooterNav('platform'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-platform'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebHome['home.footer.link.platform.overview'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratebFooterNav('domains'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-domains'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebHome['home.nav.domains'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars(rateb_public_nav_tour_href($baseUrl, $ratebFooterPrefix, $ratebNavProductTourHref), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-video'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebNavProductTourLabel, ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratebFooterNav('features'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-features'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars(trim((string) ($ratebHome['home.nav.product'] ?? '')) ?: (string) ($ratebHome['home.nav.features'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratebFooterNav('programs'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-programs'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebFooterPricingLabel, ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratebFooterNav('agencies'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-agencies'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars(trim((string) ($ratebHome['home.nav.partners'] ?? '')) ?: (string) ($ratebHome['home.nav.agencies'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratebFooterNav('contact'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-contact'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebHome['home.nav.contact'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                </ul>
            </div>
            <div class="rateb-footer-col">
                <h4><?php echo htmlspecialchars($ratebHome['home.footer.col.company'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul class="rateb-footer-enterprise__link-list">
                    <li>
                        <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/profile/#company-profile', ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link rateb-footer-link--about rateb-nav__go-profile" data-rateb-profile-nav="1" data-rateb-go-profile="1"><?php echo $ratebGlyph('rateb-ng-solutions'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars(trim((string) ($ratebHome['home.footer.link.company.about'] ?? '')) ?: 'Company profile', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratebFooterNav('solutions'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-solutions'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebHome['home.footer.link.solutions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratebFooterNav('how_it_works'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-agency'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebHome['home.nav.how_it_works'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratebFooterNav('operational'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-operational'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebHome['home.nav.operational'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars(rateb_public_nav_tour_href($baseUrl, $ratebFooterPrefix, '#video'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-video'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebHome['home.footer.link.demo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($baseUrl . '/pages/customer-portal.php'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link rateb-footer-link--customer-portal"><?php echo $ratebGlyph('rateb-ng-partner'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebHome['home.footer.link.company.customer_portal'] ?? 'Customer portal', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                </ul>
            </div>
            <div class="rateb-footer-col">
                <h4><?php echo htmlspecialchars($ratebHome['home.footer.col.support'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul class="rateb-footer-enterprise__link-list">
                    <li>
                        <a href="<?php echo htmlspecialchars($baseUrl . '/pages/login.php'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link rateb-footer-link--support-tickets"><?php echo $ratebGlyph('rateb-ng-contact'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebHome['home.footer.link.support.tickets'] ?? 'Support tickets', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="https://wa.me/<?php echo htmlspecialchars($ratebPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="rateb-nav__link rateb-footer-enterprise__nav-link rateb-footer-link--whatsapp"><span class="rateb-nav__icon rateb-nav__icon--fa" aria-hidden="true"><i class="fab fa-whatsapp"></i></span><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebHome['home.footer.link.support.whatsapp'] ?? 'WhatsApp', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="tel:+<?php echo htmlspecialchars($ratebPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" dir="ltr" class="rateb-nav__link rateb-footer-enterprise__nav-link rateb-footer-link--phone"><span class="rateb-nav__icon rateb-nav__icon--fa" aria-hidden="true"><i class="fas fa-phone-alt"></i></span><span class="rateb-nav__label rateb-topbar__phone-text"><?php echo htmlspecialchars($ratebPhoneRaw, ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                </ul>
            </div>
            <div class="rateb-footer-col rateb-footer-col--enterprise">
                <h4><?php echo htmlspecialchars($ratebHome['home.footer.col.enterprise'] ?? 'Enterprise', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul class="rateb-footer-enterprise__link-list">
                    <li>
                        <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/enterprise-trust/', ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-operational'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars(trim((string) ($ratebHome['home.footer.link.enterprise.trust'] ?? '')) ?: 'Enterprise Trust Center', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/security-compliance/', ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link rateb-footer-link--security"><?php echo $ratebGlyph('rateb-ng-operational'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars(trim((string) ($ratebHome['home.footer.link.enterprise.security'] ?? '')) ?: 'Security & Compliance', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/government-workforce-operations/', ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-programs'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars(trim((string) ($ratebHome['home.footer.link.enterprise.gov'] ?? '')) ?: 'Government & Programs', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/architecture/', ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link rateb-footer-link--architecture"><?php echo $ratebGlyph('rateb-ng-platform'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars(trim((string) ($ratebHome['home.footer.link.enterprise.architecture'] ?? '')) ?: 'Architecture', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/procurement-legal/', ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link rateb-footer-link--procurement"><?php echo $ratebGlyph('rateb-ng-programs'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars(trim((string) ($ratebHome['home.footer.link.enterprise.procurement'] ?? '')) ?: 'Procurement & Legal', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/enterprise-pack/', ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-platform'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars(trim((string) ($ratebHome['home.footer.link.enterprise.pack'] ?? '')) ?: 'Document Packs', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($ratebFooterNav('operational'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-operational'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars(trim((string) ($ratebHome['home.footer.link.enterprise.ops_sla'] ?? '')) ?: 'Operations & SLA', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                </ul>
            </div>
            <div class="rateb-footer-col">
                <h4><?php echo htmlspecialchars($ratebHome['home.footer.col.legal'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul class="rateb-footer-enterprise__link-list">
                    <li>
                        <a href="<?php echo htmlspecialchars($ratebFooterNav('register'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__link rateb-footer-enterprise__nav-link"><?php echo $ratebGlyph('rateb-ng-programs'); ?><span class="rateb-nav__label"><?php echo htmlspecialchars($ratebHome['home.footer.link.service_registration'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                    </li>
                    <li>
                        <a href="mailto:info@rateb.sa" class="rateb-nav__link rateb-footer-enterprise__nav-link rateb-footer-link--mailto"><?php echo $ratebGlyph('rateb-ng-contact'); ?><span class="rateb-nav__label">info@rateb.sa</span></a>
                    </li>
                </ul>
            </div>
            <div class="rateb-footer-col rateb-footer-enterprise__infra">
                <h4><?php echo htmlspecialchars($ratebHome['home.footer.col.infra'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <p class="rateb-footer-enterprise__infra-copy"><?php echo htmlspecialchars($ratebHome['home.footer.infra.copy'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="rateb-footer-social">
                    <a href="https://wa.me/<?php echo htmlspecialchars($ratebPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="mailto:info@rateb.sa" aria-label="Email"><i class="fas fa-envelope"></i></a>
                </div>
                <div class="footer-subscribe rateb-footer-newsletter">
                    <label class="rateb-footer-newsletter__label" for="footerEmail"><?php echo htmlspecialchars($ratebHome['home.footer.newsletter.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></label>
                    <input type="email" placeholder="<?php echo htmlspecialchars($ratebHome['home.footer.newsletter.placeholder'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" id="footerEmail" name="footer_email" autocomplete="email" aria-label="Email for newsletter">
                    <button type="button" class="btn-sub" id="footerSubscribe"><?php echo htmlspecialchars($ratebHome['home.footer.newsletter.button'] ?? '', ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
            </div>
        </div>
        <div class="rateb-footer-system-strip">
            <div class="rateb-container rateb-footer-system-strip__inner">
                <span class="rateb-footer-system-strip__item"><span class="rateb-mono-tag">uptime</span> <?php echo htmlspecialchars($ratebHome['home.footer.strip.1'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="rateb-footer-system-strip__item"><span class="rateb-mono-tag">requests</span> <?php echo htmlspecialchars($ratebHome['home.footer.strip.2'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="rateb-footer-system-strip__item"><span class="rateb-mono-tag">events</span> <?php echo htmlspecialchars($ratebHome['home.footer.strip.3'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="rateb-footer-enterprise__bottom">
            <div class="rateb-container rateb-footer-enterprise__bottom-inner">
                <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($ratebHome['home.footer.copyright_suffix'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="rateb-footer-enterprise__loc"><i class="fas fa-location-dot" aria-hidden="true"></i> <?php echo htmlspecialchars($ratebHome['home.footer.location'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </footer>

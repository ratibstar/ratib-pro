<?php
/**
 * Public marketing footer — same markup as pages/home.php.
 *
 * Expects: $baseUrl, $ratibHome, $ratibPhoneDigits, $ratibPhoneRaw (from ratib-home-public-nav-bootstrap.php).
 * Optional $ratibHomeNavHrefPrefix: when set (e.g. partner-portal-login), hash links target home.php#… .
 *
 * @var string $baseUrl
 * @var array<string,mixed> $ratibHome
 */
$ratibFooterPrefix = isset($ratibHomeNavHrefPrefix) ? (string) $ratibHomeNavHrefPrefix : '';
?>
    <footer class="ratib-footer-enterprise" id="contact">
        <div class="ratib-container ratib-footer-enterprise__grid">
            <div class="ratib-footer-enterprise__brand">
                <a href="<?php echo htmlspecialchars($baseUrl . '/pages/home.php'); ?>" class="ratib-footer-enterprise__logo">
                    <img src="<?php echo htmlspecialchars($baseUrl . '/assets/ratib-logo.svg?v=3'); ?>" alt="RATIB" width="112" height="32">
                </a>
                <p><?php echo htmlspecialchars($ratibHome['home.footer.brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.platform'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#platform', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.footer.link.platform.overview'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#how-it-works', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.nav.how_it_works'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#features', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.nav.features'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#tracking', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.nav.tracking'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#operational', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.footer.link.platform.ops_visibility'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#programs', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(trim((string) ($ratibHome['home.footer.link.platform.pricing'] ?? '') ?: (string) ($ratibHome['home.nav.programs'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#api', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.footer.link.platform.apis'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.company'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#solutions', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.footer.link.solutions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#agencies', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.nav.agencies'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#operational', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.nav.operational'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#video', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.footer.link.demo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($baseUrl . '/pages/customer-portal.php'); ?>"><?php echo htmlspecialchars($ratibHome['home.footer.link.company.customer_portal'] ?? 'Customer portal', ENT_QUOTES, 'UTF-8'); ?></a></li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.support'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($baseUrl . '/pages/login.php'); ?>"><?php echo htmlspecialchars($ratibHome['home.footer.link.support.tickets'] ?? 'Support tickets', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="https://wa.me/<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($ratibHome['home.footer.link.support.whatsapp'] ?? 'WhatsApp', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="tel:+<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" dir="ltr"><span class="ratib-topbar__phone-text"><?php echo htmlspecialchars($ratibPhoneRaw, ENT_QUOTES, 'UTF-8'); ?></span></a></li>
                </ul>
            </div>
            <div class="ratib-footer-col">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.legal'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($ratibFooterPrefix . '#register', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ratibHome['home.footer.link.service_registration'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="mailto:ratibsrar@gmail.com">ratibsrar@gmail.com</a></li>
                </ul>
            </div>
            <div class="ratib-footer-col ratib-footer-enterprise__infra">
                <h4><?php echo htmlspecialchars($ratibHome['home.footer.col.infra'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                <p class="ratib-footer-enterprise__infra-copy"><?php echo htmlspecialchars($ratibHome['home.footer.infra.copy'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="ratib-footer-social">
                    <a href="https://wa.me/<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="mailto:ratibsrar@gmail.com" aria-label="Email"><i class="fas fa-envelope"></i></a>
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

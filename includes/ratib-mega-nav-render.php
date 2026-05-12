<?php
/**
 * Renders mega navigation markup for includes/ratib-home-public-chrome-top.php.
 *
 * @param string $baseUrl Site root URL (no trailing slash)
 * @param string $navPrefix Prefix for home hash links (e.g. '' or full home.php URL)
 */
function ratib_mega_nav_render(string $baseUrl, string $navPrefix): void
{
    require_once __DIR__ . '/ratib-mega-nav-config.php';

    $home = rtrim($baseUrl, '/') . '/pages/home.php';
    $h = static function (string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    };

    $resolve = static function (string $key) use ($baseUrl, $navPrefix, $home): string {
        switch ($key) {
            case 'marketplace':
                return rtrim($baseUrl, '/') . '/modules/infrastructure-marketplace/Views/marketplace/index.php';
            /** Domains product hub: marketplace page with search + catalog (deep-link). */
            case 'marketplace_domains':
                return rtrim($baseUrl, '/') . '/modules/infrastructure-marketplace/Views/marketplace/index.php?focus=domains#infra-domain-search';
            case 'infra_status':
                return rtrim($baseUrl, '/') . '/modules/infrastructure-marketplace/Views/client/services.php';
            case 'domain_search':
                return rtrim($baseUrl, '/') . '/modules/infrastructure-marketplace/Views/marketplace/index.php?focus=domains#infra-domain-search';
            case 'contact':
                return $navPrefix !== '' ? $navPrefix . '#contact' : $home . '#contact';
            case 'solutions':
                return $navPrefix !== '' ? $navPrefix . '#solutions' : $home . '#solutions';
            case 'programs':
                return $navPrefix !== '' ? $navPrefix . '#programs' : $home . '#programs';
            case 'features':
                return $navPrefix !== '' ? $navPrefix . '#features' : $home . '#features';
            case 'program_previews':
                return $navPrefix !== '' ? $navPrefix . '#program-previews' : $home . '#program-previews';
            case 'operational':
                return $navPrefix !== '' ? $navPrefix . '#operational' : $home . '#operational';
            case 'api':
                return $navPrefix !== '' ? $navPrefix . '#api' : $home . '#api';
            case 'agencies':
                return $navPrefix !== '' ? $navPrefix . '#agencies' : $home . '#agencies';
            case 'platform':
                return $navPrefix !== '' ? $navPrefix . '#platform' : $home . '#platform';
            case 'register':
                return $navPrefix !== '' ? $navPrefix . '#register' : $home . '#register';
            case 'customer_portal':
                return rtrim($baseUrl, '/') . '/pages/customer-portal.php';
            default:
                return $home;
        }
    };

    $items = ratib_mega_nav_config();
    ?>
<div class="ratib-mega-nav" id="ratibMegaNavRoot" data-ratib-mega-nav="1" aria-label="Products and services">
    <ul class="ratib-mega-nav__list" role="list">
        <?php foreach ($items as $entry): ?>
            <?php
            $type = (string) ($entry['type'] ?? 'link');
            if ($type === 'signin'):
                $href = $resolve('customer_portal');
                ?>
        <li class="ratib-mega-nav__li ratib-mega-nav__li--signin" role="listitem">
            <a class="ratib-mega-nav__signin" href="<?php echo $h($href); ?>"><i class="fas fa-right-to-bracket" aria-hidden="true"></i><span><?php echo $h((string) ($entry['label'] ?? 'Sign In')); ?></span></a>
        </li>
                <?php
                continue;
            endif;
            if ($type === 'link'):
                $hk = (string) ($entry['href_key'] ?? 'platform');
                $href = $resolve($hk);
                $label = (string) ($entry['label'] ?? '');
                $desc = (string) ($entry['desc'] ?? '');
                ?>
        <li class="ratib-mega-nav__li ratib-mega-nav__li--flat" role="listitem">
            <a class="ratib-mega-nav__flat" href="<?php echo $h($href); ?>">
                <span class="ratib-mega-nav__flat-label"><?php echo $h($label); ?></span>
                <?php if ($desc !== ''): ?><span class="ratib-mega-nav__flat-desc"><?php echo $h($desc); ?></span><?php endif; ?>
            </a>
        </li>
                <?php
                continue;
            endif;
            if ($type !== 'mega') {
                continue;
            }
            $mid = (string) ($entry['id'] ?? '');
            $label = (string) ($entry['label'] ?? '');
            $panelId = (string) ($entry['panel_id'] ?? 'ratib-mega-panel-' . $mid);
            $tid = 'ratib-mega-trigger-' . $mid;
            $columns = $entry['columns'] ?? [];
            if (!is_array($columns)) {
                $columns = [];
            }
            ?>
        <li class="ratib-mega-nav__li ratib-mega-nav__li--mega" role="listitem" data-ratib-mega-id="<?php echo $h($mid); ?>">
            <button type="button"
                class="ratib-mega-nav__trigger"
                id="<?php echo $h($tid); ?>"
                aria-expanded="false"
                aria-haspopup="true"
                aria-controls="<?php echo $h($panelId); ?>"
                data-ratib-mega-trigger="<?php echo $h($mid); ?>">
                <span class="ratib-mega-nav__trigger-label"><?php echo $h($label); ?></span>
                <i class="fas fa-chevron-down ratib-mega-nav__chev" aria-hidden="true"></i>
            </button>
            <div class="ratib-mega-nav__panel" id="<?php echo $h($panelId); ?>" role="region" aria-labelledby="<?php echo $h($tid); ?>" hidden>
                <div class="ratib-mega-nav__panel-inner ratib-container">
                    <div class="ratib-mega-nav__grid">
                        <?php foreach ($columns as $col): ?>
                            <?php
                            if (!is_array($col)) {
                                continue;
                            }
                            $cheading = (string) ($col['heading'] ?? '');
                            $citems = $col['items'] ?? [];
                            if (!is_array($citems)) {
                                $citems = [];
                            }
                            ?>
                        <div class="ratib-mega-nav__column">
                            <?php if ($cheading !== ''): ?>
                            <p class="ratib-mega-nav__col-heading"><?php echo $h($cheading); ?></p>
                            <?php endif; ?>
                            <ul class="ratib-mega-nav__cards" role="list">
                                <?php foreach ($citems as $it): ?>
                                    <?php
                                    if (!is_array($it)) {
                                        continue;
                                    }
                                    $hk = (string) ($it['href_key'] ?? 'platform');
                                    $ihref = $resolve($hk);
                                    $icon = (string) ($it['icon'] ?? 'fa-circle');
                                    $title = (string) ($it['title'] ?? '');
                                    $desc = (string) ($it['desc'] ?? '');
                                    ?>
                                <li role="listitem">
                                    <a class="ratib-mega-nav__card" href="<?php echo $h($ihref); ?>">
                                        <span class="ratib-mega-nav__card-icon" aria-hidden="true"><i class="fas <?php echo $h($icon); ?>"></i></span>
                                        <span class="ratib-mega-nav__card-body">
                                            <span class="ratib-mega-nav__card-title"><?php echo $h($title); ?></span>
                                            <?php if ($desc !== ''): ?><span class="ratib-mega-nav__card-desc"><?php echo $h($desc); ?></span><?php endif; ?>
                                        </span>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php
}

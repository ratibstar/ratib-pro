<?php
/**
 * Renders mega navigation markup for includes/rateb-home-public-chrome-top.php.
 *
 * @param string $baseUrl Site root URL (no trailing slash)
 * @param string $navPrefix Prefix for home hash links (e.g. '' or full home.php URL)
 */
function rateb_mega_nav_render(string $baseUrl, string $navPrefix): void
{
    require_once __DIR__ . '/rateb-mega-nav-config.php';
    if (!function_exists('rateb_mega_nav_resolve_href')) {
        $resolveMain = __DIR__ . '/rateb-mega-nav-resolve.php';
        if (is_file($resolveMain)) {
            require_once $resolveMain;
        } else {
            require_once __DIR__ . '/rateb-mega-nav-resolve.fallback.php';
        }
    }

    $h = static function (string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    };

    $resolvePrefix = !empty($GLOBALS['rateb_public_nav_on_marketing_home']) ? '' : $navPrefix;
    $resolve = static function (string $key) use ($baseUrl, $resolvePrefix): string {
        return rateb_mega_nav_resolve_href($key, $baseUrl, $resolvePrefix);
    };

    $items = rateb_mega_nav_config();
    ?>
<div class="rateb-mega-nav" id="ratebMegaNavRoot" data-rateb-mega-nav="1" aria-label="Products and services">
    <ul class="rateb-mega-nav__list" role="list">
        <?php foreach ($items as $entry): ?>
            <?php
            $type = (string) ($entry['type'] ?? 'link');
            if ($type === 'signin'):
                $href = $resolve('customer_portal');
                ?>
        <li class="rateb-mega-nav__li rateb-mega-nav__li--signin" role="listitem">
            <a class="rateb-mega-nav__signin" href="<?php echo $h($href); ?>"><i class="fas fa-right-to-bracket" aria-hidden="true"></i><span><?php echo $h((string) ($entry['label'] ?? 'Sign In')); ?></span></a>
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
        <li class="rateb-mega-nav__li rateb-mega-nav__li--flat" role="listitem">
            <a class="rateb-mega-nav__flat" href="<?php echo $h($href); ?>">
                <span class="rateb-mega-nav__flat-label"><?php echo $h($label); ?></span>
                <?php if ($desc !== ''): ?><span class="rateb-mega-nav__flat-desc"><?php echo $h($desc); ?></span><?php endif; ?>
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
            $panelId = (string) ($entry['panel_id'] ?? 'rateb-mega-panel-' . $mid);
            $tid = 'rateb-mega-trigger-' . $mid;
            $columns = $entry['columns'] ?? [];
            if (!is_array($columns)) {
                $columns = [];
            }
            ?>
        <li class="rateb-mega-nav__li rateb-mega-nav__li--mega" role="listitem" data-rateb-mega-id="<?php echo $h($mid); ?>">
            <button type="button" class="rateb-mega-nav__trigger" id="<?php echo $h($tid); ?>" aria-expanded="false" aria-haspopup="true" aria-controls="<?php echo $h($panelId); ?>" data-rateb-mega-trigger="<?php echo $h($mid); ?>">
                <span class="rateb-mega-nav__trigger-label"><?php echo $h($label); ?></span>
                <i class="fas fa-chevron-down rateb-mega-nav__chev" aria-hidden="true"></i>
            </button>
            <div class="rateb-mega-nav__panel" id="<?php echo $h($panelId); ?>" role="region" aria-labelledby="<?php echo $h($tid); ?>" hidden>
                <div class="rateb-mega-nav__panel-inner rateb-container">
                    <div class="rateb-mega-nav__grid">
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
                        <div class="rateb-mega-nav__column">
                            <?php if ($cheading !== ''): ?>
                            <p class="rateb-mega-nav__col-heading"><?php echo $h($cheading); ?></p>
                            <?php endif; ?>
                            <ul class="rateb-mega-nav__cards" role="list">
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
                                    <a class="rateb-mega-nav__card" href="<?php echo $h($ihref); ?>">
                                        <span class="rateb-mega-nav__card-icon" aria-hidden="true"><i class="fas <?php echo $h($icon); ?>"></i></span>
                                        <span class="rateb-mega-nav__card-body">
                                            <span class="rateb-mega-nav__card-title"><?php echo $h($title); ?></span>
                                            <?php if ($desc !== ''): ?><span class="rateb-mega-nav__card-desc"><?php echo $h($desc); ?></span><?php endif; ?>
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

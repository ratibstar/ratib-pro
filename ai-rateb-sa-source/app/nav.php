<?php
declare(strict_types=1);

function rateb_site_header(string $here, bool $loggedIn, bool $popup = false): void
{
    $items = [
        ['/dashboard.php', 'dashboard', 'Dashboard'],
        ['/campaign-new.php', 'new_campaign', 'New Campaign'],
        ['/brand.php', 'brand_kit', 'Brand kit'],
        ['/#workflow', 'nav_how', 'How it works'],
        ['/#samples', 'nav_samples', 'Examples'],
        ['/#create', 'nav_create', 'What it creates'],
    ];
    echo '<header class="site-bar">';
    echo '<nav class="site-nav">';
    foreach ($items as $item) {
        $class = $item[0] === $here ? ' class="is-here"' : '';
        echo '<a' . $class . ' href="' . e($item[0]) . '" data-i18n="' . e($item[1]) . '">' . e($item[2]) . '</a>';
    }
    echo '</nav>';
    echo '<a class="logo" href="/">RATEB <span>AI</span></a>';
    echo '<div class="edge-auth">';
    echo '<button type="button" id="lang" class="icon">AR</button>';
    echo '<button type="button" id="theme" class="icon">☾</button>';
    if ($loggedIn) {
        echo '<a class="bar-logout" href="/logout.php" data-i18n="logout">Logout</a>';
    } else {
        if ($popup) {
            echo '<button type="button" class="login" data-auth="login" data-i18n="login">Login</button>';
            echo '<button type="button" class="register" data-auth="register" data-i18n="create_account">Create Account</button>';
        } else {
            echo '<a class="login" href="/login.php" data-i18n="login">Login</a>';
            echo '<a class="register" href="/register.php" data-i18n="create_account">Create Account</a>';
        }
    }
    echo '</div></header>';
}

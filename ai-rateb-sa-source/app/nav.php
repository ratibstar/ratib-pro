<?php
declare(strict_types=1);

function rateb_site_header(string $here, bool $loggedIn, bool $popup = false): void
{
    $groups = [
        [
            ['/dashboard.php', 'dashboard', 'Dashboard'],
            ['/campaign-new.php', 'new_campaign', 'New Campaign'],
            ['/brand.php', 'brand_kit', 'Brand kit'],
        ],
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
            if ($loggedIn && $here === '/dashboard.php' && $item[1] === 'nav_pricing') {
                echo '<button type="button" data-pricing-open data-i18n="nav_pricing">Pricing</button>';
                continue;
            }
            echo '<a' . $class . ' href="' . e($item[0]) . '" data-i18n="' . e($item[1]) . '">' . e($item[2]) . '</a>';
        }
    }
    echo '</nav>';
    echo '<a class="logo" href="/">RATEB <span>AI</span></a>';
    echo '<div class="edge-auth">';
    if ($credits !== null) {
        $chipTag = $here === '/dashboard.php' ? 'button type="button" data-pricing-open' : 'a href="/?pricing=1#pricing"';
        echo '<' . $chipTag . ' class="credit-chip" title="Credits"><span aria-hidden="true">⚡</span>' . $credits . '</' . strtok($chipTag, ' ') . '>';
    }
    echo '<button type="button" id="lang" class="icon">AR</button>';
    echo '<button type="button" id="theme" class="icon">☾</button>';
    if ($loggedIn) {
        echo '<a class="bar-logout" href="/logout.php" data-i18n="logout">Logout</a>';
    } else {
        echo '<button type="button" class="login" data-auth="login" data-i18n="login">Login</button>';
        echo '<button type="button" class="register" data-auth="register" data-i18n="create_account">Create Account</button>';
    }
    echo '</div></div></header>';
}

function rateb_auth_dialog(string $next): void
{
    $googleClient = (string) ((require __DIR__ . '/../config/google.php')['client_id'] ?? '');
    $next = e($next);
    $token = e(csrf_token());
    echo '<dialog class="auth-modal" id="auth-modal" data-google-client="' . e($googleClient) . '">';
    echo '<button type="button" class="auth-x" data-auth-close aria-label="Close">×</button>';
    echo '<form class="auth-card" id="auth-login" action="/login.php" method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . $token . '"><input type="hidden" name="auth_modal" value="1"><input type="hidden" name="next" value="' . $next . '">';
    echo '<h2 data-i18n="welcome_back">Welcome back</h2><p class="muted" data-i18n="login_lead">Login to your RATEB AI Campaign workspace.</p><p class="alert" data-auth-error hidden></p>';
    echo rateb_social_buttons();
    echo '<p class="auth-or" data-i18n="auth_or">or</p><label data-i18n="email">Email</label><input type="email" name="email" required autocomplete="email"><label data-i18n="password">Password</label><input type="password" name="password" required autocomplete="current-password"><button type="submit" data-i18n="login_button">Login</button>';
    echo '<p class="footer-link"><a href="/forgot-password.php" data-i18n="forgot_password">Forgot password?</a></p>';
    echo '<p class="footer-link"><span data-i18n="no_account">Don\'t have an account?</span> <button type="button" data-auth="register" data-i18n="create_one">Create one</button></p></form>';
    echo '<form class="auth-card" id="auth-register" action="/register.php" method="post" hidden>';
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

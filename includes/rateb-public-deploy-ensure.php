<?php
/**
 * Materialize missing public includes (legacy home.php hard require_once on trust/proof files).
 * Safe to require from rateb-home-public-nav-bootstrap.php — does not load bootstrap itself.
 *
 * SERVER CHECK: If this file is deployed, line 2 comment reads FILE_VERSION_RATEB_DEPLOY_ENSURE.
 */
declare(strict_types=1);
// FILE_VERSION_RATEB_DEPLOY_ENSURE_20260518

if (!function_exists('rateb_public_materialize_include')) {
    /**
     * @param list<string> $alsoMaterialize
     */
    function rateb_public_materialize_include(string $basename, array $alsoMaterialize = []): void
    {
        $dir = __DIR__;
        $target = $dir . '/' . $basename;
        if (!is_file($target)) {
            $dist = $dir . '/' . preg_replace('/\.php$/', '.dist.php', $basename);
            if (is_file($dist)) {
                @copy($dist, $target);
            }
        }
        foreach ($alsoMaterialize as $extra) {
            $extra = trim($extra);
            if ($extra === '') {
                continue;
            }
            $extraTarget = $dir . '/' . $extra;
            if (!is_file($extraTarget)) {
                $extraDist = $dir . '/' . preg_replace('/\.php$/', '.dist.php', $extra);
                if (is_file($extraDist)) {
                    @copy($extraDist, $extraTarget);
                }
            }
        }
        if (is_file($target)) {
            return;
        }
        rateb_public_write_include_stub($basename);
    }

    function rateb_public_write_include_stub(string $basename): void
    {
        $target = __DIR__ . '/' . $basename;
        if (is_file($target)) {
            return;
        }
        if ($basename === 'rateb-enterprise-trust-home.php') {
            $stub = <<<'PHP'
<?php
declare(strict_types=1);
if (!function_exists('rateb_enterprise_mailto')) {
    function rateb_enterprise_mailto(string $subject): string
    {
        return 'mailto:info@rateb.sa?subject=' . rawurlencode($subject);
    }
}
if (!function_exists('rateb_enterprise_trust_render_home')) {
    function rateb_enterprise_trust_render_home(array $ratebHome, string $baseUrl): void {}
    function rateb_enterprise_trust_render_hero_strip(array $ratebHome): void {}
}
PHP;
        } elseif ($basename === 'rateb-operational-proof-render.php') {
            $stub = <<<'PHP'
<?php
declare(strict_types=1);
if (!function_exists('rateb_operational_proof_render')) {
    function rateb_operational_proof_render(string $baseUrl, ?array $copy = null, array $show = []): void {}
}
PHP;
        } else {
            return;
        }
        @file_put_contents($target, $stub);
        @chmod($target, 0644);
    }
}

rateb_public_materialize_include('rateb-enterprise-trust-home.php');
rateb_public_materialize_include('rateb-operational-proof-render.php', ['rateb-operational-proof-data.php']);

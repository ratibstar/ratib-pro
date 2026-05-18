<?php
/**
 * Materialize missing public includes on live hosts (legacy home.php hard require_once).
 * Runs from ratib-home-public-nav-bootstrap.php before pages/home.php line 336.
 */
declare(strict_types=1);

if (!function_exists('ratib_public_materialize_include')) {
    /**
     * @param list<string> $alsoMaterialize Additional basenames required by the primary file
     */
    function ratib_public_materialize_include(string $basename, array $alsoMaterialize = []): void
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
        ratib_public_write_include_stub($basename);
    }

    function ratib_public_write_include_stub(string $basename): void
    {
        $target = __DIR__ . '/' . $basename;
        if (is_file($target)) {
            return;
        }
        $stub = '';
        if ($basename === 'ratib-enterprise-trust-home.php') {
            $stub = <<<'PHP'
<?php
declare(strict_types=1);
if (!function_exists('ratib_enterprise_trust_render_home')) {
    function ratib_enterprise_trust_render_home(array $ratibHome, string $baseUrl): void {}
    function ratib_enterprise_trust_render_hero_strip(array $ratibHome): void {}
}
PHP;
        } elseif ($basename === 'ratib-operational-proof-render.php') {
            $stub = <<<'PHP'
<?php
declare(strict_types=1);
if (!function_exists('ratib_operational_proof_render')) {
    function ratib_operational_proof_render(string $baseUrl, ?array $copy = null, array $show = []): void {}
}
PHP;
        } else {
            return;
        }
        @file_put_contents($target, $stub);
        @chmod($target, 0644);
    }
}

ratib_public_materialize_include('ratib-enterprise-trust-home.php');
ratib_public_materialize_include('ratib-operational-proof-render.php', ['ratib-operational-proof-data.php']);

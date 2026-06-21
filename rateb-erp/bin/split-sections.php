<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$f = $root . '/includes/marketing-unified/sections-body.php';
$c = file_get_contents($f);
$p = strpos($c, '<section class="pricing-section');
if ($p === false) {
    fwrite(STDERR, "marker not found\n");
    exit(1);
}
$head = substr($c, 0, $p);
$tail = '<?php' . "\n/** Commerce: pricing + register + final CTA */\n" . substr($c, $p);
$headOut = '<?php' . "\n/** Deep marketing sections (platform → API) */\n" . substr($head, strlen('<?php'));

file_put_contents($root . '/includes/marketing-unified/sections-deep.php', $headOut);
file_put_contents($root . '/includes/marketing-unified/sections-commerce.php', $tail);
echo "split ok\n";

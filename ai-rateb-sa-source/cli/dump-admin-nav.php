<?php
declare(strict_types=1);

require dirname(__DIR__) . '/admin/auth.php';

$actor = admin_actor_from_owner(['id' => 1, 'email' => 'owner@test', 'name' => 'Owner', 'role' => 'owner']);
foreach (['ar', 'en'] as $lang) {
    echo "=== {$lang} ===\n";
    foreach (admin_nav_groups_for_actor($actor, $lang) as $group => $links) {
        echo $group . "\n";
        foreach ($links as $href => $label) {
            echo "  {$href} => {$label}\n";
        }
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/ai/tts-pronunciation.php';
require_once __DIR__ . '/../app/ai/easyvoice.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

check(
    rateb_tts_normalize_pronunciation('مرحباً في RATEB AI اليوم') === 'مرحباً في رَتِّب إيه آي اليوم',
    'RATEB AI → رَتِّب إيه آي'
);
check(
    rateb_tts_normalize_pronunciation('منصة RATEB') === 'منصة رَتِّب',
    'RATEB → رَتِّب'
);
check(
    rateb_tts_normalize_pronunciation('منصة رتب') === 'منصة رَتِّب',
    'رتب → رَتِّب'
);
check(
    rateb_tts_normalize_pronunciation('ذكاء AI') === 'ذكاء إيه آي',
    'AI → إيه آي'
);
check(
    rateb_tts_normalize_pronunciation('MAIL') === 'MAIL',
    'AI substring not replaced inside MAIL'
);

$prepared = rateb_easyvoice_prepare_delivery_input('مرحباً بكم. منصة ذكية.');
check(
    $prepared === 'مرحباً بكم. منصة ذكية.' && !str_contains($prepared, '<break'),
    'Arabic delivery prep does not inject break markup (media 274 spoke tags aloud)'
);

$ad = rateb_easyvoice_ad_delivery_defaults();
check(
    abs((float) $ad['speed'] - 1.2) < 0.001
        && ($ad['ev_tone'] ?? '') === 'bright'
        && abs((float) ($ad['ev_volume_db'] ?? 0) - 1.5) < 0.001,
    'ad delivery defaults use supported speed/tone/volume'
);

exit($failures === 0 ? 0 : 1);

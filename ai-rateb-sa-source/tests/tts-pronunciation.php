<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/ai/tts-pronunciation.php';

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
    'RATEB AI phrase replaced case-sensitively as whole brand'
);
check(
    rateb_tts_normalize_pronunciation('welcome to rateb ai now') === 'welcome to رَتِّب إيه آي now',
    'RATEB AI matched case-insensitively'
);
check(
    rateb_tts_normalize_pronunciation('منصة RATEB للتسويق') === 'منصة رَتِّب للتسويق',
    'standalone RATEB replaced'
);
check(
    rateb_tts_normalize_pronunciation('منصة رتب للتسويق') === 'منصة رَتِّب للتسويق',
    'Arabic رتب replaced with vocalized form'
);
check(
    rateb_tts_normalize_pronunciation('ذكاء AI للأعمال') === 'ذكاء إيه آي للأعمال',
    'standalone AI replaced'
);
check(
    rateb_tts_normalize_pronunciation('MAIL and FAIR') === 'MAIL and FAIR',
    'AI substring inside Latin words not replaced'
);
check(
    rateb_tts_normalize_pronunciation('RATEB AI.') === 'رَتِّب إيه آي.',
    'punctuation after brand preserved'
);
check(
    rateb_tts_normalize_pronunciation('مرحباً بكم في رَتِّب إيه آي، منصة ذكية لأتمتة أعمالكم.')
        === 'مرحباً بكم في رَتِّب إيه آي، منصة ذكية لأتمتة أعمالكم.',
    'already-normalized script is idempotent'
);

$custom = rateb_tts_normalize_pronunciation('Hello CRM', [
    ['match' => 'CRM', 'replace' => 'سي آر إم'],
]);
check($custom === 'Hello سي آر إم', 'custom dictionary argument works');

exit($failures === 0 ? 0 : 1);

<?php
declare(strict_types=1);

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

$prev = getenv('EASYVOICE_API_KEY');
putenv('EASYVOICE_API_KEY');
check(rateb_easyvoice_configured() === false, 'missing key is not configured');
putenv('EASYVOICE_API_KEY=not-a-key');
check(rateb_easyvoice_configured() === false, 'non-ev_ prefix rejected');
putenv('EASYVOICE_API_KEY=ev_test_placeholder_only');
check(rateb_easyvoice_configured() === true, 'ev_ prefix accepted');
check(rateb_easyvoice_is_free_voice('ar_m1') && rateb_easyvoice_is_free_voice('ar_f1'), 'free Arabic voices allowed');
check(!rateb_easyvoice_is_free_voice('ar_m2'), 'paid Arabic voice blocked by free helper');
$found = rateb_easyvoice_find('ar_m1');
check(is_array($found) && ($found['provider'] ?? '') === 'easyvoice', 'find returns EasyVoice voice');
check(rateb_easyvoice_find('alloy') === null, 'OpenAI-named voices not treated as free EasyVoice Arabic');
$voices = rateb_easyvoice_free_arabic_voices();
check(count($voices) === 2, 'exactly two free Arabic catalog voices');

if ($prev === false) {
    putenv('EASYVOICE_API_KEY');
} else {
    putenv('EASYVOICE_API_KEY=' . $prev);
}

exit($failures === 0 ? 0 : 1);

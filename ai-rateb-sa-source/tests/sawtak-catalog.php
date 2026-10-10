<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/ai/sawtak.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

putenv('SAWTAK_API_KEY');
check(rateb_sawtak_configured() === false, 'missing credentials');

$pages = [
    ['object' => 'list', 'data' => [
        ['id' => 'a', 'name' => 'A', 'status' => 'ready', 'labels' => ['dialect' => 'saudi-najdi', 'gender' => 'female', 'use_case' => 'advertisement'], 'preview_url' => 'https://example/a.wav', 'sharing' => ['status' => 'public']],
        ['id' => 'a', 'name' => 'Duplicate', 'status' => 'ready', 'labels' => ['dialect' => 'eg-cairene', 'gender' => 'male']],
    ], 'has_more' => true, 'next_cursor' => 'c1'],
    ['object' => 'list', 'data' => [
        ['id' => 'b', 'name' => 'B', 'status' => 'ready', 'labels' => ['dialect' => 'eg-cairene', 'gender' => 'male', 'use_case' => 'narration'], 'sharing' => ['status' => 'public']],
    ], 'has_more' => false],
];
$calls = 0;
$result = rateb_sawtak_collect(static function (string $query) use (&$pages, &$calls): array {
    $calls++;
    if ($calls === 1) {
        return $pages[0];
    }
    if (!str_contains($query, 'after=c1')) {
        return ['object' => 'error'];
    }
    return $pages[1];
});
check($result['ok'] && $result['pages'] === 2 && $result['complete'], 'pagination follows next_cursor');
check(count($result['voices']) === 2 && $result['voices'][0]['name'] === 'A', 'duplicate voice ids collapse');
$dialects = array_values(array_unique(array_filter(array_column($result['voices'], 'dialect'))));
check($dialects === ['saudi-najdi', 'eg-cairene'], 'dialect filter values come from labels');
check($result['voices'][1]['preview_url'] === null, 'missing preview stays empty');
$stuck = rateb_sawtak_collect(static function (): array {
    return ['object' => 'list', 'data' => [['id' => 'c', 'name' => 'C', 'status' => 'ready', 'labels' => []]], 'has_more' => true];
});
check($stuck['ok'] && $stuck['complete'] === false && count($stuck['voices']) === 1, 'missing cursor stops without looping');
check(!array_key_exists('language', $result['voices'][0]), 'language is not invented');

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL\n";
exit($failures === 0 ? 0 : 1);

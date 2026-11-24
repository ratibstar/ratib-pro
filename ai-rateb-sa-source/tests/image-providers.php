<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/ai/image-providers.php';

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$order = rateb_image_provider_order();
check($order !== [] && in_array('horde', $order, true), 'provider order includes horde');
check(in_array('pollinations', $order, true) || in_array('cloudflare', $order, true), 'a sync free provider is in the default order');
check(!in_array('openai', $order, true), 'openai is not in the default provider order');
$syncOrder = rateb_image_sync_provider_order();
check(!in_array('horde', $syncOrder, true), 'sync order excludes async horde');
check(!in_array('openai', $syncOrder, true), 'sync order excludes openai (provider 4 is post-Horde)');

check(rateb_image_provider_mime("\xFF\xD8\xFF\xE0test") === 'image/jpeg', 'jpeg magic is detected');
check(rateb_image_provider_mime("\x89PNG\r\n\x1a\nxxxx") === 'image/png', 'png magic is detected');
check(rateb_image_provider_mime('hello') === null, 'non-image bytes are rejected');

check(function_exists('rateb_image_generate_sync'), 'sync generate helper exists');
check(function_exists('rateb_image_cf_configured'), 'cloudflare config helper exists');
check(function_exists('rateb_image_provider_classify_failure'), 'failure classifier exists');
check(function_exists('rateb_image_openai_eligible'), 'openai eligibility helper exists');
check(function_exists('rateb_image_openai_try'), 'openai try helper exists');
check(rateb_image_openai_size(768, 768) === '1024x1024', 'openai square size maps to 1024x1024');
check(rateb_image_openai_size(768, 1024) === '1024x1536', 'openai portrait size maps to 1024x1536');

check(rateb_image_provider_classify_failure('http_429', 'neurons exhausted') === 'retryable', '429/quota is retryable failover');
check(rateb_image_provider_classify_failure('not_configured') === 'config', 'missing credentials are config-skip');
check(rateb_image_provider_classify_failure('http_401') === 'config', '401 is config-skip');
check(rateb_image_provider_classify_failure('http_500') === 'retryable', '5xx is retryable');
check(rateb_image_provider_classify_failure('timeout') === 'retryable', 'timeout is retryable');
check(rateb_image_provider_classify_failure('invalid_prompt') === 'fatal', 'invalid prompt is fatal');
check(rateb_image_provider_classify_failure('pollinations_busy') === 'retryable', 'pollinations busy is retryable');

$fakeJpeg = "\xFF\xD8\xFF\xE0" . str_repeat('A', 200);
$cfThenPolli = rateb_image_generate_sync('campaign prompt', 512, 512, [
    'cloudflare' => static fn(): array => ['ok' => false, 'error' => 'http_429', 'detail' => 'neurons', 'http' => 429],
    'pollinations' => static fn(): array => ['ok' => true, 'bytes' => $fakeJpeg, 'mime' => 'image/jpeg', 'model' => 'pollinations:default', 'http' => 200],
]);
check(!empty($cfThenPolli['ok']) && ($cfThenPolli['provider'] ?? '') === 'pollinations', 'failover: CF 429 then Pollinations success');
check(count($cfThenPolli['attempts'] ?? []) === 2, 'failover: exactly one attempt per sync provider');
check(($cfThenPolli['attempts'][0]['provider'] ?? '') === 'cloudflare' && ($cfThenPolli['attempts'][0]['ok'] ?? true) === false, 'failover: first attempt is CF failure');
check(($cfThenPolli['attempts'][1]['provider'] ?? '') === 'pollinations' && !empty($cfThenPolli['attempts'][1]['ok']), 'failover: second attempt is Pollinations ok');

$configSkip = rateb_image_generate_sync('campaign prompt', 512, 512, [
    'cloudflare' => static fn(): array => ['ok' => false, 'error' => 'not_configured'],
    'pollinations' => static fn(): array => ['ok' => true, 'bytes' => $fakeJpeg, 'mime' => 'image/jpeg', 'model' => 'pollinations:default', 'http' => 200],
]);
check(!empty($configSkip['ok']) && ($configSkip['provider'] ?? '') === 'pollinations', 'config skip moves to next provider');

$fatalStops = rateb_image_generate_sync('bad prompt', 512, 512, [
    'cloudflare' => static fn(): array => ['ok' => false, 'error' => 'invalid_prompt', 'detail' => 'unsupported request'],
    'pollinations' => static fn(): array => ['ok' => true, 'bytes' => $fakeJpeg, 'mime' => 'image/jpeg', 'model' => 'pollinations:default', 'http' => 200],
]);
check(empty($fatalStops['ok']) && ($fatalStops['error_class'] ?? '') === 'fatal', 'fatal prompt does not switch providers');
check(count($fatalStops['attempts'] ?? []) === 1, 'fatal prompt stops after first provider');

$allFail = rateb_image_generate_sync('campaign prompt', 512, 512, [
    'cloudflare' => static fn(): array => ['ok' => false, 'error' => 'http_429', 'http' => 429],
    'pollinations' => static fn(): array => ['ok' => false, 'error' => 'pollinations_busy', 'http' => 402],
]);
check(empty($allFail['ok']) && ($allFail['error'] ?? '') === 'sync_providers_failed', 'all sync providers can fail closed');
check(count($allFail['attempts'] ?? []) === 2, 'all-fail still records one attempt each');

$samePrompt = 'exact campaign subject grilled burger family dining';
$captured = [];
rateb_image_generate_sync($samePrompt, 512, 512, [
    'cloudflare' => static function (string $p) use (&$captured): array {
        $captured[] = $p;
        return ['ok' => false, 'error' => 'http_503', 'http' => 503];
    },
    'pollinations' => static function (string $p) use (&$captured, $fakeJpeg): array {
        $captured[] = $p;
        return ['ok' => true, 'bytes' => $fakeJpeg, 'mime' => 'image/jpeg', 'model' => 'pollinations:default', 'http' => 200];
    },
]);
check(count($captured) === 2 && $captured[0] === $samePrompt && $captured[1] === $samePrompt, 'failover preserves the same campaign prompt');

$san = rateb_image_provider_sanitize_detail('Bearer FAKESECRET_e2f3g4h5i6j8l9m0n1 neurons sk-proj-ABCDEFGHIJKLMNOPQRSTUVWXYZ123456');
check(!str_contains($san, 'sk_live_secret_token_abcdef1234567890'), 'sanitizer redacts secret-like tokens');
check(!str_contains($san, 'sk-proj-ABCDEFGHIJKLMNOPQRSTUVWXYZ123456'), 'sanitizer redacts OpenAI sk- keys');

// OpenAI gate: disabled by default / unconfigured → never eligible.
$prevKey = getenv('OPENAI_API_KEY');
$prevTest = getenv('RATEB_IMAGE_OPENAI_TEST');
$prevUser = getenv('RATEB_IMAGE_OPENAI_TEST_USER_ID');
putenv('OPENAI_API_KEY');
putenv('RATEB_IMAGE_OPENAI_TEST');
putenv('RATEB_IMAGE_OPENAI_TEST_USER_ID');
check(!rateb_image_openai_configured(), 'openai is unconfigured without OPENAI_API_KEY');
check(!rateb_image_openai_eligible(66, 'free'), 'openai ineligible when key missing');

putenv('OPENAI_API_KEY=sk-test-offline-only-not-a-real-secret-0123456789');
putenv('RATEB_IMAGE_OPENAI_TEST');
putenv('RATEB_IMAGE_OPENAI_TEST_USER_ID=66');
check(rateb_image_openai_configured(), 'openai configured when OPENAI_API_KEY is set');
check(!rateb_image_openai_eligible(66, 'free'), 'openai stays disabled until RATEB_IMAGE_OPENAI_TEST=1');

putenv('RATEB_IMAGE_OPENAI_TEST=1');
check(rateb_image_openai_eligible(66, 'free'), 'openai eligible for authorized FREE test user');
check(!rateb_image_openai_eligible(66, 'pro'), 'openai blocked for paid plans');
check(!rateb_image_openai_eligible(99, 'free'), 'openai blocked for other user ids');

$latch = rateb_image_openai_test_latch_path();
@unlink($latch);
check(rateb_image_openai_consume_test_slot(), 'openai test latch can be claimed once');
check(!rateb_image_openai_consume_test_slot(), 'openai test latch rejects a second claim');
@unlink($latch);

// Default sync path must not invoke openai even when listed after horde in env order.
putenv('RATEB_IMAGE_PROVIDERS=cloudflare,pollinations,horde,openai');
$syncNoOpenAi = rateb_image_generate_sync('campaign prompt', 512, 512, [
    'cloudflare' => static fn(): array => ['ok' => false, 'error' => 'http_429', 'http' => 429],
    'pollinations' => static fn(): array => ['ok' => true, 'bytes' => $fakeJpeg, 'mime' => 'image/jpeg', 'model' => 'pollinations:default', 'http' => 200],
    'openai' => static fn(): array => ['ok' => true, 'bytes' => $fakeJpeg, 'mime' => 'image/jpeg', 'model' => 'should-not-run', 'http' => 200],
]);
check(!empty($syncNoOpenAi['ok']) && ($syncNoOpenAi['provider'] ?? '') === 'pollinations', 'sync failover still ends at Pollinations before OpenAI');
$openaiAttempted = false;
foreach (($syncNoOpenAi['attempts'] ?? []) as $a) {
    if (($a['provider'] ?? '') === 'openai') {
        $openaiAttempted = true;
    }
}
check(!$openaiAttempted, 'openai is never called from sync generate');

// Restore env for other tests in the same process.
if ($prevKey === false) {
    putenv('OPENAI_API_KEY');
} else {
    putenv('OPENAI_API_KEY=' . $prevKey);
}
if ($prevTest === false) {
    putenv('RATEB_IMAGE_OPENAI_TEST');
} else {
    putenv('RATEB_IMAGE_OPENAI_TEST=' . $prevTest);
}
if ($prevUser === false) {
    putenv('RATEB_IMAGE_OPENAI_TEST_USER_ID');
} else {
    putenv('RATEB_IMAGE_OPENAI_TEST_USER_ID=' . $prevUser);
}
putenv('RATEB_IMAGE_PROVIDERS');

echo $failures === 0 ? 'RESULT=PASS' . PHP_EOL : 'RESULT=FAIL failures=' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);

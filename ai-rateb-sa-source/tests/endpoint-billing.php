<?php
declare(strict_types=1);

/*
 * Endpoint-flow billing tests for app/ai/image.php and app/ai/voice.php. Each request runs
 * the real endpoint in a subprocess (tests/support/endpoint-runner.php) with an in-memory
 * ledger and scripted provider replies, then checks when credits moved relative to the
 * provider calls.
 */

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

const USER = 15;
const CAMPAIGN = 7;
const JOB = 'a1b2c3d4-e5f6-4711-8899-aabbccddeeff';
const HORDE = 'https://aihorde.net/api/v2/';
const SAWTAK = 'https://api.sawtakarabi.ai/v1/audio/speech';
const IMAGE_URL = 'https://bucket.r2.cloudflarestorage.com/out.png';
const PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

$work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rateb-endpoint-work-' . bin2hex(random_bytes(6));
mkdir($work, 0700, true);
register_shutdown_function(static function () use ($work): void {
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($work);
});

function job_dir(): string
{
    global $work;
    $dir = $work . DIRECTORY_SEPARATOR . 'rateb-endpoint-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);
    return $dir;
}

/** @return array<string,mixed> */
function run_endpoint(array $scenario, string $jobDir): array
{
    global $work;
    $file = $work . DIRECTORY_SEPARATOR . 'scenario-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($file, json_encode($scenario, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    putenv('RATEB_IMAGE_JOB_DIR=' . $jobDir);
    $process = proc_open([PHP_BINARY, __DIR__ . '/support/endpoint-runner.php', $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    putenv('RATEB_IMAGE_JOB_DIR');
    $at = strrpos((string) $stdout, '@@ENDPOINT_RESULT@@');
    $result = $at === false ? null : json_decode(substr((string) $stdout, $at + 19), true);
    if (!is_array($result) || $result['error'] !== '' || $code !== 0) {
        check(false, 'endpoint runner completed (exit ' . $code . '): ' . trim((string) $stderr . ' ' . substr((string) $stdout, 0, 400)));
        return ['http' => 0, 'json' => [], 'balances' => [], 'events' => [], 'next_id' => 0, 'calls' => [], 'unexpected' => [['runner' => 'failed']], 'stores' => 0, 'writes' => [], 'session' => []];
    }
    $result['json'] = json_decode((string) $result['body'], true) ?? [];
    return $result;
}

function balance(array $result): ?int
{
    return isset($result['balances'][USER]) ? (int) $result['balances'][USER] : null;
}

/** @return array<int,array<string,mixed>> */
function rows(array $result, string $operation): array
{
    return array_values(array_filter($result['events'], static fn (array $row): bool => $row['operation'] === $operation));
}

/** @return array<int,array<string,mixed>> */
function calls(array $result, string $method, string $prefix = ''): array
{
    return array_values(array_filter($result['calls'], static fn (array $call): bool => $call['method'] === $method && str_starts_with((string) $call['url'], $prefix)));
}

function carry(array $scenario, array $result): array
{
    $scenario['balances'] = $result['balances'];
    $scenario['events'] = $result['events'];
    $scenario['next_id'] = $result['next_id'];
    $scenario['session'] = $result['session'];
    return $scenario;
}

function plan(int $imageLimit = 10, int $active = 1): array
{
    return ['subscription_id' => 1, 'plan_id' => 2, 'code' => 'pro', 'name_ar' => 'احترافي', 'name_en' => 'Pro', 'price_sar' => 99, 'video_limit' => 5, 'video_max_seconds' => 30, 'image_limit' => $imageLimit, 'watermark' => 0, 'is_active' => $active];
}

function base(string $endpoint, int $balance): array
{
    return [
        'endpoint' => $endpoint,
        'balances' => [USER => $balance],
        'events' => [],
        'next_id' => 1,
        'plan' => plan(),
        'session' => ['user_id' => USER, 'csrf_token' => 'test-csrf'],
        'campaign' => ['id' => CAMPAIGN, 'user_id' => USER, 'product_name' => 'Dates gift box'],
        'speech' => 'ar',
        'provider' => [],
    ];
}

function image_request(int $balance, array $provider): array
{
    $strategy = array_fill_keys(['positioning', 'core_message', 'audience', 'channels', 'tone', 'creative_direction', 'call_to_action'], 'Premium Saudi dates for family gifting');
    $scenario = base('image', $balance);
    $scenario['post'] = ['csrf' => 'test-csrf', 'campaign_id' => (string) CAMPAIGN, 'ratio' => '1:1'];
    $scenario['reads'] = [
        ['sql' => 'SELECT output_type, content, approval_status FROM campaign_outputs WHERE campaign_id = ? ORDER BY id DESC', 'rows' => [
            ['output_type' => 'headline', 'content' => 'Fresh dates for Ramadan', 'approval_status' => 'approved'],
            ['output_type' => 'strategy', 'content' => json_encode($strategy), 'approval_status' => 'approved'],
        ]],
        ['sql' => 'SELECT brand_name, tone, primary_color, secondary_color FROM brand_kits WHERE user_id = ?', 'rows' => []],
    ];
    $scenario['provider'] = $provider;
    return $scenario;
}

function poll(array $scenario, array $result, array $provider, string $jobId = JOB): array
{
    $next = carry($scenario, $result);
    $next['post']['job_id'] = $jobId;
    $next['provider'] = $provider;
    return $next;
}

function models(int $count = 3): array
{
    return ['method' => 'GET', 'url' => HORDE . 'status/models', 'http' => 200, 'body' => json_encode([['name' => 'Deliberate', 'count' => $count]])];
}

function submit(int $http = 202, string $id = JOB): array
{
    return ['method' => 'POST', 'url' => HORDE . 'generate/async', 'http' => $http, 'body' => json_encode(['id' => $id])];
}

function check_reply(array $state): array
{
    return ['method' => 'GET', 'url' => HORDE . 'generate/check/' . JOB, 'http' => 200, 'body' => json_encode($state)];
}

function done_replies(string $imageUrl = IMAGE_URL): array
{
    return [
        check_reply(['done' => true]),
        ['method' => 'GET', 'url' => HORDE . 'generate/status/' . JOB, 'http' => 200, 'body' => json_encode(['generations' => [['img' => $imageUrl, 'model' => 'Deliberate']]])],
        ['method' => 'GET', 'url' => IMAGE_URL, 'http' => 200, 'body_b64' => PNG_B64],
    ];
}

/** Starts a fresh image job that is charged and pending at the provider. */
function pending_image(): array
{
    $dir = job_dir();
    $scenario = image_request(100, [models(), submit()]);
    return [$dir, $scenario, run_endpoint($scenario, $dir)];
}

function job_store(string $dir): array
{
    $raw = (string) @file_get_contents($dir . DIRECTORY_SEPARATOR . 'user-' . USER . '.json');
    return json_decode($raw, true)['jobs'] ?? [];
}

// Image: a new request charges before submitting and leaves one pending job.
[$dir, $start, $pending] = pending_image();
$reservation = rows($pending, 'image');
check($pending['http'] === 200 && ($pending['json']['pending'] ?? false) === true && ($pending['json']['job_id'] ?? '') === JOB, 'image: a new request returns the pending provider job');
check(balance($pending) === 90 && count(rows($pending, 'ai_image')) === 1, 'image: a new request charges 10 credits once');
check(count($reservation) === 1 && $reservation[0]['status'] === 'reserved' && (int) $reservation[0]['credits_deducted'] === 10, 'image: the charge is recorded on the open reservation');
$posts = calls($pending, 'POST', HORDE . 'generate/async');
check(count($posts) === 1 && $posts[0]['balance'] === 90, 'image: the credits are taken before the provider submit');
check(calls($pending, 'GET', HORDE . 'status/models')[0]['balance'] === 100, 'image: the worker check happens before any charge');
check(isset($pending['session']['ai_image_jobs'][JOB]) && count(job_store($dir)) === 1 && job_store($dir)[0]['phase'] === 'pending', 'image: the job is bound to the session and the job store');
check($pending['unexpected'] === [], 'image: no unexpected provider calls on a new request');

// Image: polling a job that is still running never charges again.
$poll = run_endpoint(poll($start, $pending, [check_reply(['done' => false, 'wait_time' => 7])]), $dir);
check($poll['http'] === 200 && ($poll['json']['pending'] ?? false) === true && ($poll['json']['wait'] ?? 0) === 7, 'image poll: a running job stays pending');
check(balance($poll) === 90 && $poll['events'] == $pending['events'] && $poll['next_id'] === $pending['next_id'], 'image poll: polling adds no charge and no reservation');
check(calls($poll, 'POST') === [] && $poll['unexpected'] === [], 'image poll: polling does not submit a new job');
$poll2 = run_endpoint(poll($start, $poll, [check_reply(['done' => false, 'wait_time' => 3])]), $dir);
check(balance($poll2) === 90 && $poll2['events'] == $pending['events'], 'image poll: a second poll still adds no charge');

// Image: a repeat new request while the job runs resumes it instead of charging.
$again = carry($start, $pending);
$again['provider'] = [check_reply(['done' => false, 'wait_time' => 5])];
$again = run_endpoint($again, $dir);
check(($again['json']['job_id'] ?? '') === JOB && balance($again) === 90 && $again['events'] == $pending['events'], 'image: a repeat request while pending resumes the job without a second charge');
check(calls($again, 'POST') === [] && $again['unexpected'] === [], 'image: a repeat request while pending submits nothing');

// Image: a finished job is stored and the reservation completes without a refund.
$done = run_endpoint(poll($start, $poll, done_replies()), $dir);
$reservation = rows($done, 'image');
check($done['http'] === 200 && ($done['json']['ok'] ?? false) === true && $done['stores'] === 1, 'image poll: a finished job is stored');
check(balance($done) === 90 && $reservation[0]['status'] === 'completed' && rows($done, 'ai_image_refund') === [], 'image poll: completion keeps the single charge and refunds nothing');
check(job_store($dir) === [] && !isset($done['session']['ai_image_jobs'][JOB]), 'image poll: completion clears the job');
$replay = run_endpoint(poll($start, $done, []), $dir);
check($replay['http'] === 404 && balance($replay) === 90 && $replay['events'] == $done['events'] && $replay['calls'] === [], 'image poll: replaying a finished job neither charges nor refunds');

// Image: a result on an untrusted host is refunded exactly once.
[$dir, $start, $pending] = pending_image();
$bad = run_endpoint(poll($start, $pending, array_slice(done_replies('https://evil.example/out.png'), 0, 2)), $dir);
check($bad['http'] === 502 && balance($bad) === 100 && count(rows($bad, 'ai_image_refund')) === 1 && rows($bad, 'image')[0]['status'] === 'failed', 'image poll: an untrusted result host refunds the charge');
check($bad['stores'] === 0 && job_store($dir) === [], 'image poll: an untrusted result is not stored and the job is cleared');
$replay = run_endpoint(poll($start, $bad, []), $dir);
check($replay['http'] === 404 && balance($replay) === 100 && count(rows($replay, 'ai_image_refund')) === 1, 'image poll: replaying a refunded job does not refund twice');

// Image: a provider fault while polling is refunded.
[$dir, $start, $pending] = pending_image();
$faulted = run_endpoint(poll($start, $pending, [check_reply(['faulted' => true])]), $dir);
check($faulted['http'] === 502 && balance($faulted) === 100 && count(rows($faulted, 'ai_image_refund')) === 1, 'image poll: a faulted provider job refunds the charge');

// Image: a storage failure after the provider finished is refunded.
[$dir, $start, $pending] = pending_image();
$scenario = poll($start, $pending, done_replies());
$scenario['storage_fails'] = true;
$stored = run_endpoint($scenario, $dir);
check($stored['http'] === 500 && ($stored['json']['code'] ?? '') === 'image_storage', 'image poll: a storage failure reports image_storage');
check(balance($stored) === 100 && count(rows($stored, 'ai_image_refund')) === 1 && rows($stored, 'image')[0]['status'] === 'failed', 'image poll: a storage failure refunds the charge once');

// Image: an expired job is refunded once and reported as a timeout.
[$dir, $start, $pending] = pending_image();
$jobs = job_store($dir);
$jobs[0]['created'] = time() - 1000;
file_put_contents($dir . DIRECTORY_SEPARATOR . 'user-' . USER . '.json', json_encode(['jobs' => $jobs]));
$pending['session']['ai_image_jobs'][JOB]['created'] = time() - 1000;
$expired = run_endpoint(poll($start, $pending, []), $dir);
check($expired['http'] === 504 && ($expired['json']['code'] ?? '') === 'image_timeout' && $expired['calls'] === [], 'image poll: an expired job times out without calling the provider');
check(balance($expired) === 100 && count(rows($expired, 'ai_image_refund')) === 1 && rows($expired, 'image')[0]['status'] === 'failed', 'image poll: an expired job refunds the charge once');

// Image: a failed submit is refunded, after the charge was taken.
$dir = job_dir();
$failed = run_endpoint(image_request(100, [models(), submit(500)]), $dir);
check($failed['http'] === 500 && calls($failed, 'POST')[0]['balance'] === 90, 'image: a failed submit was attempted after the charge');
check(balance($failed) === 100 && count(rows($failed, 'ai_image_refund')) === 1 && rows($failed, 'image')[0]['status'] === 'failed' && job_store($dir) === [], 'image: a failed submit refunds the charge and clears the claim');

// Image: an invalid provider job id is refunded.
$dir = job_dir();
$invalid = run_endpoint(image_request(100, [models(), submit(202, 'not-a-job')]), $dir);
check($invalid['http'] === 502 && balance($invalid) === 100 && count(rows($invalid, 'ai_image_refund')) === 1 && job_store($dir) === [], 'image: an invalid provider job id refunds the charge');

// Image: no workers means no charge and no submit.
$dir = job_dir();
$idle = run_endpoint(image_request(100, [models(0)]), $dir);
check($idle['http'] === 503 && ($idle['json']['code'] ?? '') === 'image_workers', 'image: no workers reports image_workers');
check(balance($idle) === 100 && rows($idle, 'ai_image') === [] && calls($idle, 'POST') === [] && rows($idle, 'image')[0]['status'] === 'failed', 'image: no workers charges nothing, submits nothing, and closes the reservation');

// Image: a low balance stops before the provider submit.
$dir = job_dir();
$poor = run_endpoint(image_request(5, [models()]), $dir);
check($poor['http'] === 402 && ($poor['json']['code'] ?? '') === 'credits', 'image: a low balance returns a credit stop');
check(balance($poor) === 5 && rows($poor, 'ai_image') === [] && calls($poor, 'POST') === [] && rows($poor, 'image')[0]['status'] === 'failed' && job_store($dir) === [], 'image: a low balance charges nothing, submits nothing, and closes the reservation');

// Image: the plan limit is enforced before any provider call or charge.
$dir = job_dir();
$scenario = image_request(100, [models(), submit()]);
$scenario['plan'] = plan(1);
$scenario['events'] = [1 => ['user_id' => USER, 'campaign_id' => CAMPAIGN, 'operation' => 'image', 'status' => 'completed', 'credits_deducted' => 10, 'age' => 0]];
$scenario['next_id'] = 2;
$limited = run_endpoint($scenario, $dir);
check($limited['http'] === 402 && ($limited['json']['code'] ?? '') === 'plan_limit' && $limited['calls'] === [] && balance($limited) === 100 && count($limited['events']) === 1, 'image: the plan limit denies the request before any charge or provider call');

// Voice.
function riff(): string
{
    return 'RIFF' . pack('V', 44) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16) . 'data' . pack('V', 8) . str_repeat("\0", 8);
}

function voice_request(int $balance, array $reply): array
{
    $scenario = base('voice', $balance);
    $scenario['post'] = ['csrf' => 'test-csrf', 'campaign_id' => (string) CAMPAIGN, 'tone' => 'corporate', 'voice' => 'test-voice'];
    $scenario['voice_script'] = 'تمور سعودية فاخرة هدية للعائلة في رمضان';
    $scenario['writable'] = [
        "DELETE FROM campaign_outputs WHERE campaign_id = ? AND output_type = 'voice_script'",
        "INSERT INTO campaign_outputs (campaign_id, output_type, content, approval_status) VALUES (?, 'voice_script', ?, 'draft')",
    ];
    $scenario['provider'] = [['method' => 'POST', 'url' => SAWTAK] + $reply];
    return $scenario;
}

function audio(): array
{
    return ['http' => 200, 'body_b64' => base64_encode(riff())];
}

$voice = run_endpoint(voice_request(100, audio()), job_dir());
$reservation = rows($voice, 'voice');
check($voice['http'] === 200 && ($voice['json']['ok'] ?? false) === true && $voice['stores'] === 1, 'voice: a successful request stores the audio');
check(balance($voice) === 85 && count(rows($voice, 'saudi_voice')) === 1 && rows($voice, 'saudi_voice_refund') === [], 'voice: success charges 15 credits once and refunds nothing');
check(count($reservation) === 1 && $reservation[0]['status'] === 'completed' && (int) $reservation[0]['credits_deducted'] === 15, 'voice: the reservation completes with the 15-credit charge recorded');
check(count($voice['calls']) === 1 && $voice['calls'][0]['balance'] === 85 && $voice['unexpected'] === [], 'voice: the credits are taken before the provider call');
check(count($voice['writes']) === 2, 'voice: the spoken script is saved after success');

$down = run_endpoint(voice_request(100, ['http' => 500, 'body' => json_encode(['error' => ['code' => 'server_error']])]), job_dir());
check($down['http'] === 502 && $down['calls'][0]['balance'] === 85, 'voice: a provider failure happens after the charge');
check(balance($down) === 100 && count(rows($down, 'saudi_voice_refund')) === 1 && rows($down, 'voice')[0]['status'] === 'failed' && $down['stores'] === 0 && $down['writes'] === [], 'voice: a provider failure refunds once and saves nothing');

$terms = run_endpoint(voice_request(100, ['http' => 403, 'body' => json_encode(['error' => ['code' => 'model_terms_required']])]), job_dir());
check($terms['http'] === 403 && ($terms['json']['code'] ?? '') === 'voice_terms' && balance($terms) === 100 && count(rows($terms, 'saudi_voice_refund')) === 1, 'voice: a terms refusal refunds the charge');

$scenario = voice_request(100, audio());
$scenario['storage_fails'] = true;
$lost = run_endpoint($scenario, job_dir());
check($lost['http'] === 500 && balance($lost) === 100 && count(rows($lost, 'saudi_voice_refund')) === 1 && rows($lost, 'voice')[0]['status'] === 'failed' && $lost['writes'] === [], 'voice: a storage failure refunds once and saves no script');

$poor = run_endpoint(voice_request(10, audio()), job_dir());
check($poor['http'] === 402 && ($poor['json']['code'] ?? '') === 'credits' && $poor['calls'] === [], 'voice: a low balance stops before the provider call');
check(balance($poor) === 10 && rows($poor, 'saudi_voice') === [] && rows($poor, 'voice')[0]['status'] === 'failed', 'voice: a low balance charges nothing and closes the reservation');

$scenario = voice_request(85, audio());
$scenario['events'] = [1 => ['user_id' => USER, 'campaign_id' => CAMPAIGN, 'operation' => 'voice', 'status' => 'reserved', 'credits_deducted' => 15, 'age' => 601]];
$scenario['next_id'] = 2;
$swept = run_endpoint($scenario, job_dir());
check($swept['events'][1]['status'] === 'failed' && count(rows($swept, 'saudi_voice_refund')) === 1, 'voice: a stale charged reservation is swept and refunded once');
check($swept['http'] === 200 && balance($swept) === 85 && $swept['calls'][0]['balance'] === 85, 'voice: the sweep refund lands before the new charge, which is still taken once');

$scenario['events'][1]['age'] = 100;
$fresh = run_endpoint($scenario, job_dir());
check($fresh['events'][1]['status'] === 'reserved' && rows($fresh, 'saudi_voice_refund') === [] && balance($fresh) === 70, 'voice: a recent charged reservation is not swept');

const CF_ACCOUNT = '0123456789abcdef0123456789abcdef';
const CF_URL = 'https://api.cloudflare.com/client/v4/accounts/' . CF_ACCOUNT . '/ai/run/@cf/black-forest-labs/flux-1-schnell';

function cloudflare_request(int $balance, array $provider, array $ai): array
{
    $scenario = image_request($balance, $provider);
    $scenario['ai'] = $ai;
    return $scenario;
}

$hordeOnly = pending_image();
check(calls($hordeOnly[2], 'GET', HORDE) !== [] && calls($hordeOnly[2], 'POST', 'https://api.cloudflare.com/') === [], 'image: the default provider still calls only AI Horde');

$missing = run_endpoint(cloudflare_request(100, [], ['image_provider' => 'cloudflare', 'cloudflare_account_id' => '', 'cloudflare_api_token' => '']), job_dir());
check($missing['http'] === 503 && ($missing['json']['code'] ?? '') === 'image_workers' && $missing['calls'] === [] && $missing['unexpected'] === [], 'image: missing Cloudflare credentials make no provider call');
check(balance($missing) === 100 && rows($missing, 'image') === [] && rows($missing, 'ai_image') === [], 'image: missing Cloudflare credentials reserve nothing');

$quotaAi = ['image_provider' => 'cloudflare', 'cloudflare_account_id' => CF_ACCOUNT, 'cloudflare_api_token' => 'test-token'];
$quota = run_endpoint(cloudflare_request(100, [['method' => 'POST', 'url' => CF_URL, 'http' => 429, 'body' => '{"success":false}']], $quotaAi), job_dir());
check($quota['http'] === 429 && count($quota['calls']) === 1 && $quota['calls'][0]['url'] === CF_URL && $quota['unexpected'] === [], 'image: a Cloudflare quota response calls that endpoint once');
check(balance($quota) === 100 && count(rows($quota, 'ai_image_refund')) === 1 && rows($quota, 'image')[0]['status'] === 'failed', 'image: a Cloudflare quota response refunds the reservation once');

$saved = run_endpoint(cloudflare_request(100, [['method' => 'POST', 'url' => CF_URL, 'http' => 200, 'body_b64' => PNG_B64]], $quotaAi), job_dir());
check($saved['http'] === 200 && ($saved['json']['ok'] ?? false) === true && $saved['stores'] === 1 && ($saved['json']['model'] ?? '') === '@cf/black-forest-labs/flux-1-schnell', 'image: a Cloudflare raw image is stored');
check(balance($saved) === 90 && rows($saved, 'image')[0]['status'] === 'completed' && rows($saved, 'ai_image_refund') === [] && count($saved['calls']) === 1, 'image: a Cloudflare raw success settles the charge once');

$envelope = json_encode(['success' => true, 'result' => ['image' => PNG_B64]], JSON_THROW_ON_ERROR);
$jsonSaved = run_endpoint(cloudflare_request(100, [['method' => 'POST', 'url' => CF_URL, 'http' => 200, 'body' => $envelope]], $quotaAi), job_dir());
check($jsonSaved['http'] === 200 && ($jsonSaved['json']['ok'] ?? false) === true && ($jsonSaved['json']['mime'] ?? '') === 'image/png' && $jsonSaved['stores'] === 1, 'image: a Cloudflare JSON base64 image is decoded and stored');
check(balance($jsonSaved) === 90 && rows($jsonSaved, 'ai_image_refund') === [] && count($jsonSaved['calls']) === 1, 'image: a Cloudflare JSON success settles the charge once');

$notImage = run_endpoint(cloudflare_request(100, [['method' => 'POST', 'url' => CF_URL, 'http' => 200, 'body' => '{"success":true,"result":{"image":"bm90LWFuLWltYWdl"}}']], $quotaAi), job_dir());
check($notImage['http'] === 502 && $notImage['stores'] === 0 && count($notImage['calls']) === 1 && $notImage['unexpected'] === [], 'image: a Cloudflare JSON body that is not an image is not stored');
check(balance($notImage) === 100 && count(rows($notImage, 'ai_image_refund')) === 1, 'image: an invalid Cloudflare image refunds once');

echo $failures === 0 ? 'RESULT=PASS' : 'RESULT=FAIL failures=' . $failures, PHP_EOL;
exit($failures === 0 ? 0 : 1);

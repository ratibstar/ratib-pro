<?php
declare(strict_types=1);

/*
 * Runs one billing endpoint (app/ai/image.php or app/ai/voice.php) for one request, with
 * the test doubles in endpoint-doubles.php, and prints the resulting ledger state.
 * Usage: php endpoint-runner.php <scenario.json>
 *
 * It never loads app/bootstrap.php, config/database.php, or config/ai.php, so it cannot
 * reach a real database or provider.
 */

const RUNNER_MARKER = '@@ENDPOINT_RESULT@@';

function runner_refuse(string $why): never
{
    fwrite(STDERR, 'REFUSED: ' . $why . PHP_EOL);
    exit(2);
}

$scenario = json_decode((string) @file_get_contents((string) ($argv[1] ?? '')), true);
if (!is_array($scenario) || !in_array($scenario['endpoint'] ?? '', ['image', 'voice'], true)) {
    runner_refuse('a scenario file with endpoint image or voice is required.');
}
$jobDir = (string) getenv('RATEB_IMAGE_JOB_DIR');
if ($jobDir === '' || !is_dir($jobDir) || !str_starts_with(basename($jobDir), 'rateb-endpoint-test-')) {
    runner_refuse('RATEB_IMAGE_JOB_DIR must be a dedicated rateb-endpoint-test-* directory.');
}

$app = dirname(__DIR__, 2) . '/app';
require_once $app . '/plans.php';
require_once $app . '/media/library.php';
require_once __DIR__ . '/fake-ledger.php';
if (!class_exists('finfo')) {
    define('FILEINFO_MIME_TYPE', 16);
    final class finfo
    {
        public function __construct(int $flags = 0)
        {
        }

        public function buffer(string $bytes): string
        {
            return match (true) {
                str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'image/png',
                str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
                default => 'application/octet-stream',
            };
        }
    }
}
if (!function_exists('ui_language')) {
    function ui_language(): string
    {
        return 'en';
    }
}
require_once __DIR__ . '/endpoint-doubles.php';
if (function_exists('db')) {
    runner_refuse('a global db() exists, so the production bootstrap may be loaded.');
}

$source = (string) file_get_contents($app . '/ai/' . $scenario['endpoint'] . '.php');
$header = "<?php\ndeclare(strict_types=1);\n";
$source = str_replace("\r\n", "\n", $source);
if (!str_starts_with($source, $header)) {
    runner_refuse('the endpoint no longer starts with the expected header.');
}
$body = substr($source, strlen($header));
$body = preg_replace('/^require_once __DIR__ \. \'[^\']+\';\n/m', '', $body, -1, $requires) ?? '';
$body = str_replace("require __DIR__ . '/../../config/ai.php'", 'ai_config()', $body, $configs);
if ($requires !== 4 || $configs < 1 || str_contains($body, '__DIR__') || str_contains($body, '__FILE__') || preg_match('/^[ \t]*(require|include)(_once)?\b/m', $body) === 1) {
    runner_refuse('the endpoint includes changed; update the runner before trusting its results.');
}

$ledger = new FakeLedger();
$ledger->balances = array_map('intval', $scenario['balances'] ?? []);
foreach ($scenario['events'] ?? [] as $id => $row) {
    $ledger->events[(int) $id] = $row;
}
$ledger->nextId = (int) ($scenario['next_id'] ?? 1);
$ledger->plan = $scenario['plan'] ?? null;
foreach ($scenario['reads'] ?? [] as $read) {
    $ledger->reads[FakeLedger::normalize((string) $read['sql'])] = $read['rows'];
}
$ledger->writable = array_map([FakeLedger::class, 'normalize'], $scenario['writable'] ?? []);
RatebEndpointTest\Harness::$db = $ledger;
RatebEndpointTest\Harness::$scenario = $scenario;

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = $scenario['post'] ?? [];
$_SESSION = $scenario['session'] ?? [];

register_shutdown_function(static function () use ($ledger): void {
    $output = (string) ob_get_clean();
    $error = error_get_last();
    echo RUNNER_MARKER, json_encode([
        'http' => (int) (http_response_code() ?: 200),
        'body' => $output,
        'balances' => $ledger->balances,
        'events' => $ledger->events,
        'next_id' => $ledger->nextId,
        'calls' => RatebEndpointTest\Harness::$calls,
        'unexpected' => RatebEndpointTest\Harness::$unexpected,
        'stores' => RatebEndpointTest\Harness::$stores,
        'writes' => $ledger->writes,
        'session' => $_SESSION,
        'error' => $error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) ? $error['message'] : '',
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), PHP_EOL;
});

ob_start();
eval("declare(strict_types=1);\nnamespace RatebEndpointTest;\nuse RuntimeException;\nuse Throwable;\nuse finfo;\n" . $body);

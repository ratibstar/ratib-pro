<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\SystemSetting;

/**
 * Read-only diagnostic collector for the ERP email subsystem.
 *
 * No configuration changes. No database writes. Optional test log only.
 */
final class MailDiagnosticsService
{
    /** @return array<string, mixed> */
    public function collect(): array
    {
        $cfgWithSources = (new MailConfigService())->resolveWithSources();
        $cfg = $cfgWithSources['config'];
        $sources = $cfgWithSources['sources'];

        return [
            'feature_flag' => $this->featureFlagState(),
            'config' => $cfg,
            'sources' => $sources,
            'ready' => $cfg['host'] !== '' && $cfg['user'] !== '' && $cfg['pass'] !== '',
            'smtp' => $this->smtpConnectivity($cfg),
            'queue' => $this->queueStats(),
            'cron' => $this->cronStatus(),
            'errors' => $this->todaysEmailErrors(),
            'test' => null,
        ];
    }

    /** @return array<string, mixed> */
    public function featureFlagState(): array
    {
        return [
            'enabled' => function_exists('rateb_email_diagnostics_accessible') && rateb_email_diagnostics_accessible(),
            'flag_on' => function_exists('rateb_email_diagnostics_flag_enabled') && rateb_email_diagnostics_flag_enabled(),
        ];
    }

    /**
     * Perform a read-only SMTP handshake and return every response code.
     *
     * @param array<string, mixed> $cfg
     * @return array<string, mixed>
     */
    public function smtpConnectivity(array $cfg): array
    {
        $host = trim((string) ($cfg['host'] ?? ''));
        $port = (int) ($cfg['port'] ?? 587);
        $encryption = strtolower(trim((string) ($cfg['encryption'] ?? 'tls')));
        $user = trim((string) ($cfg['user'] ?? ''));
        $pass = (string) ($cfg['pass'] ?? '');
        $fromEmail = trim((string) ($cfg['from_email'] ?? 'info@rateb.sa'));

        $result = [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'dns' => ['ok' => false, 'records' => []],
            'connect' => ['ok' => false, 'code' => '', 'error' => ''],
            'ehlo' => ['ok' => false, 'code' => '', 'response' => ''],
            'starttls' => ['ok' => false, 'code' => '', 'response' => ''],
            'auth' => ['ok' => false, 'code' => '', 'response' => ''],
            'auth_attempted' => false,
            'tls' => ['ok' => false, 'error' => ''],
        ];

        if ($host === '') {
            $result['connect']['error'] = __('mail_error_connect_unknown');
            return $result;
        }

        $result['dns'] = $this->resolveHostDns($host);

        $remote = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : 'tcp://' . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $fp = @stream_socket_client($remote, $errno, $errstr, 25, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) {
            $result['connect']['error'] = trim($errstr) !== '' ? $errstr : __('mail_error_connect_unknown');
            $result['connect']['code'] = (string) $errno;
            return $result;
        }
        stream_set_timeout($fp, 25);
        $result['connect']['ok'] = true;

        $read = static function () use ($fp): string {
            $data = '';
            while ($line = fgets($fp, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };

        $greeting = $read();
        $result['connect']['response'] = $greeting;
        $result['connect']['code'] = self::firstCode($greeting);

        $ehloHost = $this->ehloHostname($host, $fromEmail);
        fwrite($fp, 'EHLO ' . $ehloHost . "\r\n");
        $ehlo = $read();
        $result['ehlo']['response'] = $ehlo;
        $result['ehlo']['code'] = self::firstCode($ehlo);
        $result['ehlo']['ok'] = strpos($ehlo, '250') === 0;

        if ($encryption === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
            fwrite($fp, "STARTTLS\r\n");
            $tlsResp = $read();
            $result['starttls']['response'] = $tlsResp;
            $result['starttls']['code'] = self::firstCode($tlsResp);
            $result['starttls']['ok'] = strpos($tlsResp, '220') === 0;

            if ($result['starttls']['ok']) {
                $crypto = @stream_socket_enable_crypto($fp, true, $this->tlsCryptoMethod());
                $result['tls']['ok'] = $crypto === true;
                if (!$result['tls']['ok']) {
                    $result['tls']['error'] = __('mail_error_tls', ['host' => $host, 'port' => (string) $port]);
                    fclose($fp);
                    return $result;
                }
                fwrite($fp, 'EHLO ' . $ehloHost . "\r\n");
                $read();
            }
        }

        if ($user !== '' && $pass !== '') {
            $result['auth_attempted'] = true;
            fwrite($fp, "AUTH LOGIN\r\n");
            $authReq = $read();
            fwrite($fp, base64_encode($user) . "\r\n");
            $userResp = $read();
            fwrite($fp, base64_encode($pass) . "\r\n");
            $auth = $read();
            $result['auth']['response'] = $auth;
            $result['auth']['code'] = self::firstCode($auth);
            $result['auth']['ok'] = strpos($auth, '235') === 0;
        }

        fwrite($fp, "QUIT\r\n");
        fclose($fp);

        return $result;
    }

    /** @return array<string, mixed> */
    public function queueStats(): array
    {
        $db = Database::connection();
        $stats = [
            'pending' => 0,
            'failed' => 0,
            'sent_today' => 0,
            'dead_letter' => 0,
            'oldest_pending' => null,
            'last_failed' => null,
        ];

        try {
            $stats['pending'] = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_notification_queue WHERE status = 'pending'")->fetch()['c'] ?? 0);
            $stats['failed'] = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_notification_queue WHERE status = 'failed'")->fetch()['c'] ?? 0);
            $stats['dead_letter'] = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_notification_queue WHERE dead_letter_at IS NOT NULL")->fetch()['c'] ?? 0);
            $stats['sent_today'] = (int) ($db->query("SELECT COUNT(*) AS c FROM rateb_notification_queue WHERE status = 'sent' AND sent_at >= CURDATE()")->fetch()['c'] ?? 0);

            $oldest = $db->query("SELECT MIN(created_at) AS t FROM rateb_notification_queue WHERE status = 'pending'")->fetch();
            if (!empty($oldest['t'])) {
                $stats['oldest_pending'] = (string) $oldest['t'];
            }

            $lastFailed = $db->query("SELECT * FROM rateb_notification_queue WHERE status = 'failed' ORDER BY id DESC LIMIT 1")->fetch();
            if ($lastFailed) {
                $stats['last_failed'] = [
                    'id' => (int) ($lastFailed['id'] ?? 0),
                    'recipient' => (string) ($lastFailed['recipient'] ?? ''),
                    'subject' => (string) ($lastFailed['subject'] ?? ''),
                    'attempt_count' => (int) ($lastFailed['attempt_count'] ?? 0),
                    'created_at' => (string) ($lastFailed['created_at'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /** @return array<string, mixed> */
    public function cronStatus(): array
    {
        $db = Database::connection();
        $status = [
            'last_run' => null,
            'next_expected' => null,
            'healthy' => false,
            'delayed_minutes' => null,
        ];

        try {
            $row = $db->query("SELECT * FROM rateb_cron_health WHERE job_name = 'erp-cron' ORDER BY last_run_at DESC LIMIT 1")->fetch();
            if ($row) {
                $status['last_run'] = (string) ($row['last_run_at'] ?? '');
                $status['next_expected'] = (string) ($row['next_expected_at'] ?? '');
                if ($status['next_expected'] !== '') {
                    $status['healthy'] = (string) ($row['next_expected_at'] ?? '') >= date('Y-m-d H:i:s');
                    $status['delayed_minutes'] = $status['healthy'] ? 0 : $this->minutesDiff((string) $row['next_expected_at'], date('Y-m-d H:i:s'));
                }
            }
        } catch (\Throwable $e) {
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    /** @return list<array<string, mixed>> */
    public function todaysEmailErrors(int $limit = 20): array
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $file = $root . '/storage/logs/erp-' . date('Y-m-d') . '.log';
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $keywords = ['smtp_auth', 'smtp_connect', 'smtp_tls', 'smtp_rcpt', 'smtp_data', 'smtp_not_configured', 'via_localhost', 'Email send failed', 'QueueWorker', 'SMTP connect failed'];
        $errors = [];
        $lines = array_reverse(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            $message = (string) ($decoded['message'] ?? '');
            $context = (array) ($decoded['context'] ?? []);
            $haystack = strtolower($message . ' ' . json_encode($context));
            foreach ($keywords as $kw) {
                if (stripos($haystack, $kw) !== false) {
                    $errors[] = [
                        'time' => (string) ($decoded['time'] ?? ''),
                        'level' => (string) ($decoded['level'] ?? ''),
                        'message' => $message,
                        'context' => $context,
                    ];
                    break;
                }
            }
            if (count($errors) >= $limit) {
                break;
            }
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    public function runTestEmail(string $to = 'info@rateb.sa'): array
    {
        $to = trim($to);
        if ($to === '' || !\Rateb\App\Helpers\Str::isValidEmail($to)) {
            return ['ok' => false, 'error' => __('mail_test_invalid')];
        }
        MailConfigService::setRepairEnabled(false);
        try {
            return (new MailTestService())->sendTest($to);
        } finally {
            MailConfigService::setRepairEnabled(true);
        }
    }

    /**
     * Overall PASS/FAIL based on collected evidence.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function overall(array $data): array
    {
        $ready = !empty($data['ready']);
        $smtp = (array) ($data['smtp'] ?? []);
        $cron = (array) ($data['cron'] ?? []);
        $queue = (array) ($data['queue'] ?? []);
        $test = is_array($data['test'] ?? null) ? $data['test'] : null;

        $connectOk = !empty($smtp['connect']['ok']);
        $authOk = !empty($smtp['auth']['ok']);
        $cronHealthy = !empty($cron['healthy']);
        $queueBackedUp = (int) ($queue['failed'] ?? 0) > 20 || (int) ($queue['pending'] ?? 0) > 100;

        $checks = [
            'config_ready' => $ready,
            'smtp_connect' => $connectOk,
            'smtp_auth' => $authOk,
            'cron_healthy' => $cronHealthy,
            'queue_healthy' => !$queueBackedUp,
            'test_send' => $test !== null && ($test['level'] ?? '') === 'success',
        ];

        $pass = $ready && $connectOk && $authOk && $cronHealthy && !$queueBackedUp;

        if (!$pass && $test !== null && ($test['level'] ?? '') === 'success') {
            // Test send succeeded even though some checks failed: still FAIL for automation, but warn.
        }

        return [
            'status' => $pass ? 'PASS' : 'FAIL',
            'checks' => $checks,
        ];
    }

    /** @return array<string, mixed> */
    private function resolveHostDns(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA | DNS_MX);
        if ($records === false) {
            $records = [];
        }
        return [
            'ok' => $records !== [],
            'records' => array_slice($records, 0, 5),
        ];
    }

    private function ehloHostname(string $smtpHost, string $fromEmail): string
    {
        $smtpHost = strtolower(trim($smtpHost));
        if ($smtpHost !== '' && !in_array($smtpHost, ['localhost', '127.0.0.1', '::1'], true) && str_contains($smtpHost, '.')) {
            return $smtpHost;
        }
        $domain = strtolower(\Rateb\App\Helpers\Str::emailDomain($fromEmail));
        if ($domain !== '') {
            return 'mail.' . $domain;
        }
        return 'mail.rateb.sa';
    }

    private function tlsCryptoMethod(): int
    {
        $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        return $method;
    }

    private static function firstCode(string $response): string
    {
        $trimmed = trim($response);
        if ($trimmed === '') {
            return '';
        }
        return substr($trimmed, 0, 3);
    }

    private function minutesDiff(string $later, string $earlier): int
    {
        try {
            $a = new \DateTime($later);
            $b = new \DateTime($earlier);
            return (int) abs(($a->getTimestamp() - $b->getTimestamp()) / 60);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

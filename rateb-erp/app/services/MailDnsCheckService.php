<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/** Checks public DNS records required for Gmail/Outlook delivery. */
final class MailDnsCheckService
{
    /** @var list<string> */
    private const DKIM_SELECTORS = ['x', 'default', 'mail', 'da', 'selector1', 'k1', 'dkim'];

    /** @var array<string, list<array<string, mixed>>> */
    private array $dnsAnswerCache = [];

    /** @return array{domain:string,spf:array{ok:bool,detail:string,count:int},dkim:array{ok:bool,detail:string,selector:?string},dmarc:array{ok:bool,detail:string},mx:array{ok:bool,detail:string},ptr:array{ok:bool,detail:string},port25:array{ok:bool,detail:string,skipped:bool},warnings:list<string>,ready_for_external:bool,recommendations:array<string,mixed>} */
    public function checkCached(string $domain = 'rateb.sa', bool $refresh = false): array
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            $domain = 'rateb.sa';
        }
        $sessionKey = 'rateb_mail_dns_' . md5($domain);
        $fileKey = sys_get_temp_dir() . '/rateb_mail_dns_' . md5($domain) . '.json';

        if (!$refresh) {
            $raw = \Rateb\App\Core\SessionManager::get($sessionKey);
            if (is_array($raw) && is_array($raw['data'] ?? null) && (int) ($raw['exp'] ?? 0) > time()) {
                return $raw['data'];
            }
            if (is_file($fileKey)) {
                $mtime = (int) @filemtime($fileKey);
                if ($mtime > 0 && (time() - $mtime) < 3600) {
                    $decoded = json_decode((string) @file_get_contents($fileKey), true);
                    if (is_array($decoded) && isset($decoded['domain'])) {
                        \Rateb\App\Core\SessionManager::set($sessionKey, ['exp' => time() + 600, 'data' => $decoded]);
                        return $decoded;
                    }
                }
            }
        }

        // Hard wall-clock budget — never hold a PHP-FPM worker for DNS forever.
        $data = $this->checkFast($domain);
        \Rateb\App\Core\SessionManager::set($sessionKey, ['exp' => time() + 600, 'data' => $data]);
        @file_put_contents($fileKey, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $data;
    }

    /**
     * Fast DNS panel path: SPF/MX/DMARC + first DKIM hit + port 25 probe.
     * Port 25 probe uses a 2 s timeout to avoid hanging shared-host PHP workers.
     *
     * @return array{domain:string,spf:array{ok:bool,detail:string,count:int},dkim:array{ok:bool,detail:string,selector:?string},dmarc:array{ok:bool,detail:string},mx:array{ok:bool,detail:string},ptr:array{ok:bool,detail:string},port25:array{ok:bool,detail:string,skipped:bool},warnings:list<string>,ready_for_external:bool,recommendations:array<string,mixed>}
     */
    public function checkFast(string $domain = 'rateb.sa'): array
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            $domain = 'rateb.sa';
        }
        $mailCfg = new MailConfigService();
        $smtpHost = trim((string) ($mailCfg->resolve()['host'] ?? ''));
        $usesRelay = $mailCfg->isSmtpRelayHost($smtpHost);

        $spf = $this->checkSpf($domain);
        $dkim = $this->checkDkimFast($domain);
        $dmarc = $this->checkDmarc($domain);
        $mx = $this->checkMx($domain);
        $ptr = ['ok' => true, 'detail' => 'skipped (fast check)'];
        $port25 = array_merge($this->checkPort25Outbound(), ['skipped' => false]);

        $warnings = [];
        if (($spf['count'] ?? 0) > 1) {
            $warnings[] = __('mail_dns_warn_spf_duplicate');
        }
        if (!$dmarc['ok']) {
            $warnings[] = __('mail_dns_warn_dmarc');
        }
        if (!$port25['ok']) {
            $warnings[] = __('mail_port25_blocked_hint');
        }

        $dnsOk = $spf['ok'] && $dkim['ok'] && $mx['ok'];
        return [
            'domain' => $domain,
            'spf' => $spf,
            'dkim' => $dkim,
            'dmarc' => $dmarc,
            'mx' => $mx,
            'ptr' => $ptr,
            'port25' => $port25,
            'smtp_host' => $smtpHost,
            'smtp_relay' => $usesRelay,
            'warnings' => $warnings,
            'ready_for_external' => $dnsOk && $port25['ok'],
            'recommendations' => $this->recommendedRecords($domain, $spf['ok'], $dkim['ok'], $usesRelay),
        ];
    }

    /** @return array{domain:string,spf:array{ok:bool,detail:string,count:int},dkim:array{ok:bool,detail:string,selector:?string},dmarc:array{ok:bool,detail:string},mx:array{ok:bool,detail:string},ptr:array{ok:bool,detail:string},port25:array{ok:bool,detail:string,skipped:bool},warnings:list<string>,ready_for_external:bool,recommendations:array<string,mixed>} */
    public function check(string $domain = 'rateb.sa'): array
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            $domain = 'rateb.sa';
        }
        $mailCfg = new MailConfigService();
        $smtpHost = trim((string) ($mailCfg->resolve()['host'] ?? ''));
        $usesRelay = $mailCfg->isSmtpRelayHost($smtpHost);

        $spf = $this->checkSpf($domain);
        $dkim = $this->checkDkim($domain);
        $dmarc = $this->checkDmarc($domain);
        $mx = $this->checkMx($domain);
        $ptr = $this->checkPtr($domain, $mx['detail'] ?? '');
        $port25 = array_merge($this->checkPort25Outbound(), ['skipped' => false]);

        $warnings = [];
        if (($spf['count'] ?? 0) > 1) {
            $warnings[] = __('mail_dns_warn_spf_duplicate');
        }
        if (!$ptr['ok']) {
            $warnings[] = __('mail_dns_warn_ptr', ['detail' => $ptr['detail']]);
        }
        if (!$dmarc['ok']) {
            $warnings[] = __('mail_dns_warn_dmarc');
        }
        if (!$port25['ok'] && empty($port25['skipped'])) {
            $warnings[] = __('mail_port25_blocked_hint');
        }

        $dnsOk = $spf['ok'] && $dkim['ok'] && $mx['ok'] && $ptr['ok'];
        return [
            'domain' => $domain,
            'spf' => $spf,
            'dkim' => $dkim,
            'dmarc' => $dmarc,
            'mx' => $mx,
            'ptr' => $ptr,
            'port25' => $port25,
            'smtp_host' => $smtpHost,
            'smtp_relay' => $usesRelay,
            'warnings' => $warnings,
            'ready_for_external' => $dnsOk && $port25['ok'],
            'recommendations' => $this->recommendedRecords($domain, $spf['ok'], $dkim['ok'], $usesRelay),
        ];
    }

    /**
     * @return array{spf:array{host:string,type:string,value:string},dmarc:array{host:string,type:string,value:string},dkim_note:string}
     */
    public function recommendedRecords(string $domain = 'rateb.sa', ?bool $spfOk = null, ?bool $dkimOk = null, bool $usesRelay = false): array
    {
        $domain = strtolower(trim($domain)) ?: 'rateb.sa';
        $spfValue = $usesRelay
            ? 'v=spf1 a mx include:mail.' . $domain . ' include:sendgrid.net ~all'
            : 'v=spf1 a mx include:mail.' . $domain . ' ~all';
        return [
            'spf' => [
                'host' => '@',
                'type' => 'TXT',
                'value' => $spfValue,
                'needed' => $spfOk !== true,
            ],
            'dmarc' => [
                'host' => '_dmarc',
                'type' => 'TXT',
                'value' => 'v=DMARC1; p=none; rua=mailto:info@' . $domain,
                'needed' => true,
            ],
            'dkim_note' => __('mail_dns_dkim_da_copy'),
            'dkim_needed' => $dkimOk !== true,
        ];
    }

    /** @return array{ok:bool,detail:string,count:int} */
    private function checkSpf(string $domain): array
    {
        $spfRecords = [];
        foreach ($this->txtRecords($domain) as $txt) {
            if (stripos($txt, 'v=spf1') !== false) {
                $spfRecords[] = $txt;
            }
        }
        if ($spfRecords === []) {
            return ['ok' => false, 'detail' => __('mail_dns_spf_missing'), 'count' => 0];
        }
        if (count($spfRecords) > 1) {
            return [
                'ok' => false,
                'detail' => __('mail_dns_spf_duplicate', ['count' => (string) count($spfRecords)]),
                'count' => count($spfRecords),
            ];
        }
        return ['ok' => true, 'detail' => $this->clip($spfRecords[0]), 'count' => 1];
    }

    /** @return array{ok:bool,detail:string} */
    private function checkPtr(string $domain, string $mxDetail): array
    {
        $mailHost = 'mail.' . $domain;
        $mxParts = array_map('trim', explode(',', $mxDetail));
        foreach ($mxParts as $part) {
            if ($part !== '' && str_contains($part, '.')) {
                $mailHost = rtrim($part, '.');
                break;
            }
        }
        $ip = $this->resolveIpv4($mailHost);
        if ($ip === '') {
            return ['ok' => false, 'detail' => __('mail_dns_ptr_missing')];
        }
        $rev = implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa';
        $ptrHost = '';
        foreach ($this->dnsAnswers($rev, 12) as $row) {
            $target = rtrim(trim((string) ($row['data'] ?? '')), '.');
            if ($target !== '') {
                $ptrHost = $target;
                break;
            }
        }
        if ($ptrHost === '') {
            return ['ok' => false, 'detail' => __('mail_dns_ptr_missing')];
        }
        $expected = strtolower($mailHost);
        $ptrLower = strtolower($ptrHost);
        if ($ptrLower === $expected || str_ends_with($ptrLower, '.' . $domain)) {
            return ['ok' => true, 'detail' => $ptrHost];
        }
        return ['ok' => false, 'detail' => $ptrHost . ' → ' . __('mail_dns_ptr_expected', ['host' => $mailHost])];
    }

    /** @return array{ok:bool,detail:string,local_ok:bool,outbound_ok:bool} */
    private function checkPort25Outbound(): array
    {
        $localHost = '';
        foreach (['127.0.0.1', 'localhost', 'mail.rateb.sa'] as $host) {
            $fp = @stream_socket_client('tcp://' . $host . ':25', $errno, $errstr, 3);
            if (is_resource($fp)) {
                fclose($fp);
                $localHost = $host . ':25';
                break;
            }
        }
        $localOk = $localHost !== '';

        $outboundHost = '';
        foreach (['gmail-smtp-in.l.google.com', 'alt1.gmail-smtp-in.l.google.com'] as $host) {
            $fp = @stream_socket_client('tcp://' . $host . ':25', $errno, $errstr, 5);
            if (is_resource($fp)) {
                fclose($fp);
                $outboundHost = $host . ':25';
                break;
            }
        }
        $outboundOk = $outboundHost !== '';

        if ($outboundOk) {
            return [
                'ok' => true,
                'detail' => __('mail_port25_active', ['host' => $outboundHost]),
                'local_ok' => $localOk,
                'outbound_ok' => true,
            ];
        }
        if ($localOk) {
            return [
                'ok' => false,
                'detail' => __('mail_port25_local_only', ['host' => $localHost]),
                'local_ok' => true,
                'outbound_ok' => false,
            ];
        }
        return [
            'ok' => false,
            'detail' => __('mail_port25_blocked_detail'),
            'local_ok' => false,
            'outbound_ok' => false,
        ];
    }

    /** @return array{ok:bool,detail:string,selector:?string} */
    private function checkDkimFast(string $domain): array
    {
        foreach (['x', 'default'] as $selector) {
            $host = $selector . '._domainkey.' . $domain;
            foreach ($this->txtRecords($host) as $txt) {
                if (stripos($txt, 'v=DKIM1') !== false || stripos($txt, 'k=rsa') !== false) {
                    return ['ok' => true, 'detail' => $selector . '._domainkey', 'selector' => $selector];
                }
            }
        }
        return ['ok' => false, 'detail' => __('mail_dns_dkim_missing'), 'selector' => null];
    }

    /** @return array{ok:bool,detail:string,selector:?string} */
    private function checkDkim(string $domain): array
    {
        $fast = $this->checkDkimFast($domain);
        if ($fast['ok']) {
            return $fast;
        }
        foreach (self::DKIM_SELECTORS as $selector) {
            if ($selector === 'x' || $selector === 'default') {
                continue;
            }
            $host = $selector . '._domainkey.' . $domain;
            foreach ($this->txtRecords($host) as $txt) {
                if (stripos($txt, 'v=DKIM1') !== false || stripos($txt, 'k=rsa') !== false) {
                    return ['ok' => true, 'detail' => $selector . '._domainkey', 'selector' => $selector];
                }
            }
        }
        return ['ok' => false, 'detail' => __('mail_dns_dkim_missing'), 'selector' => null];
    }

    /** @return array{ok:bool,detail:string} */
    private function checkDmarc(string $domain): array
    {
        foreach ($this->txtRecords('_dmarc.' . $domain) as $txt) {
            if (stripos($txt, 'v=DMARC1') !== false) {
                return ['ok' => true, 'detail' => $this->clip($txt)];
            }
        }
        return ['ok' => false, 'detail' => __('mail_dns_dmarc_missing')];
    }

    /** @return array{ok:bool,detail:string} */
    private function checkMx(string $domain): array
    {
        $parts = $this->mxHosts($domain);
        return [
            'ok' => $parts !== [],
            'detail' => $parts !== [] ? implode(', ', $parts) : __('mail_dns_mx_missing'),
        ];
    }

    /** @return list<string> */
    private function mxHosts(string $domain): array
    {
        $parts = [];
        foreach ($this->dnsAnswers($domain, 15) as $row) {
            $data = trim((string) ($row['data'] ?? ''), " \t\".");
            if ($data === '') {
                continue;
            }
            if (preg_match('/^\d+\s+(.+)$/', $data, $m)) {
                $parts[] = rtrim($m[1], '.');
            } else {
                $parts[] = rtrim($data, '.');
            }
        }
        if ($parts !== []) {
            return array_values(array_unique($parts));
        }
        // Never call dns_get_record() — can hang PHP-FPM with no timeout on shared hosts.
        return [];
    }

    /** @return list<string> */
    private function txtRecords(string $host): array
    {
        $out = [];
        foreach ($this->dnsAnswers($host, 16) as $row) {
            $txt = $this->decodeTxt((string) ($row['data'] ?? ''));
            if ($txt !== '') {
                $out[] = $txt;
            }
        }
        return $out;
    }

    private function resolveIpv4(string $host): string
    {
        foreach ($this->dnsAnswers($host, 1) as $row) {
            $ip = trim((string) ($row['data'] ?? ''));
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }
        return '';
    }

    /** @return list<array<string, mixed>> */
    private function dnsAnswers(string $name, int $type): array
    {
        $cacheKey = strtolower($name) . '|' . $type;
        if (isset($this->dnsAnswerCache[$cacheKey])) {
            return $this->dnsAnswerCache[$cacheKey];
        }
        $url = 'https://dns.google/resolve?name=' . rawurlencode($name) . '&type=' . $type;
        $ctx = stream_context_create(['http' => [
            'timeout' => 1.2,
            'header' => "Accept: application/dns-json\r\n",
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            $this->dnsAnswerCache[$cacheKey] = [];
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || (int) ($data['Status'] ?? 1) !== 0) {
            $this->dnsAnswerCache[$cacheKey] = [];
            return [];
        }
        $answers = $data['Answer'] ?? [];
        $this->dnsAnswerCache[$cacheKey] = is_array($answers) ? $answers : [];
        return $this->dnsAnswerCache[$cacheKey];
    }

    private function decodeTxt(string $data): string
    {
        $data = trim($data);
        if ($data === '') {
            return '';
        }
        if ($data[0] === '"' && substr($data, -1) === '"') {
            return stripcslashes(substr($data, 1, -1));
        }
        return $data;
    }

    private function clip(string $text, int $max = 120): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? $text;
        if (strlen($text) <= $max) {
            return $text;
        }
        return substr($text, 0, $max - 3) . '...';
    }
}

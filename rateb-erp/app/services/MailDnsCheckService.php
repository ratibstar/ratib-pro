<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/** Checks public DNS records required for Gmail/Outlook delivery. */
final class MailDnsCheckService
{
    /** @var list<string> */
    private const DKIM_SELECTORS = ['x', 'default', 'mail', 'da', 'selector1', 'k1', 'dkim'];

    /** @return array{domain:string,spf:array{ok:bool,detail:string,count:int},dkim:array{ok:bool,detail:string,selector:?string},dmarc:array{ok:bool,detail:string},mx:array{ok:bool,detail:string},ptr:array{ok:bool,detail:string},warnings:list<string>,ready_for_external:bool} */
    public function check(string $domain = 'rateb.sa'): array
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            $domain = 'rateb.sa';
        }
        $spf = $this->checkSpf($domain);
        $dkim = $this->checkDkim($domain);
        $dmarc = $this->checkDmarc($domain);
        $mx = $this->checkMx($domain);
        $ptr = $this->checkPtr($domain, $mx['detail'] ?? '');
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
        return [
            'domain' => $domain,
            'spf' => $spf,
            'dkim' => $dkim,
            'dmarc' => $dmarc,
            'mx' => $mx,
            'ptr' => $ptr,
            'warnings' => $warnings,
            'ready_for_external' => $spf['ok'] && $dkim['ok'] && $mx['ok'] && $ptr['ok'],
            'recommendations' => $this->recommendedRecords($domain, $spf['ok'], $dkim['ok']),
        ];
    }

    /**
     * @return array{spf:array{host:string,type:string,value:string},dmarc:array{host:string,type:string,value:string},dkim_note:string}
     */
    public function recommendedRecords(string $domain = 'rateb.sa', ?bool $spfOk = null, ?bool $dkimOk = null): array
    {
        $domain = strtolower(trim($domain)) ?: 'rateb.sa';
        return [
            'spf' => [
                'host' => '@',
                'type' => 'TXT',
                'value' => 'v=spf1 a mx include:mail.' . $domain . ' ~all',
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
        $ips = @gethostbynamel($mailHost);
        if (!is_array($ips) || $ips === []) {
            return ['ok' => false, 'detail' => __('mail_dns_ptr_missing')];
        }
        $ip = $ips[0];
        $rev = implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa';
        $ptrHost = '';
        $records = @dns_get_record($rev, DNS_PTR);
        if (is_array($records)) {
            foreach ($records as $row) {
                $target = rtrim((string) ($row['target'] ?? ''), '.');
                if ($target !== '') {
                    $ptrHost = $target;
                    break;
                }
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

    /** @return array{ok:bool,detail:string,selector:?string} */
    private function checkDkim(string $domain): array
    {
        foreach (self::DKIM_SELECTORS as $selector) {
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
        $records = @dns_get_record($domain, DNS_MX);
        if (!is_array($records)) {
            return [];
        }
        foreach ($records as $row) {
            $host = (string) ($row['target'] ?? '');
            if ($host !== '') {
                $parts[] = $host;
            }
        }
        return array_values(array_unique($parts));
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
        if ($out !== []) {
            return $out;
        }
        $records = @dns_get_record($host, DNS_TXT);
        if (!is_array($records)) {
            return [];
        }
        foreach ($records as $row) {
            $txt = (string) ($row['txt'] ?? '');
            if ($txt !== '') {
                $out[] = $txt;
            }
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function dnsAnswers(string $name, int $type): array
    {
        $url = 'https://dns.google/resolve?name=' . rawurlencode($name) . '&type=' . $type;
        $ctx = stream_context_create(['http' => ['timeout' => 6, 'header' => "Accept: application/dns-json\r\n"]]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || (int) ($data['Status'] ?? 1) !== 0) {
            return [];
        }
        $answers = $data['Answer'] ?? [];
        return is_array($answers) ? $answers : [];
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

<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/** Checks public DNS records required for Gmail/Outlook delivery. */
final class MailDnsCheckService
{
    /** @var list<string> */
    private const DKIM_SELECTORS = ['x', 'default', 'mail', 'da', 'selector1', 'k1', 'dkim'];

    /** @return array{domain:string,spf:array{ok:bool,detail:string},dkim:array{ok:bool,detail:string,selector:?string},dmarc:array{ok:bool,detail:string},mx:array{ok:bool,detail:string},ready_for_external:bool} */
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
        return [
            'domain' => $domain,
            'spf' => $spf,
            'dkim' => $dkim,
            'dmarc' => $dmarc,
            'mx' => $mx,
            'ready_for_external' => $spf['ok'] && $dkim['ok'] && $mx['ok'],
        ];
    }

    /** @return array{ok:bool,detail:string} */
    private function checkSpf(string $domain): array
    {
        $records = $this->txtRecords($domain);
        foreach ($records as $txt) {
            if (stripos($txt, 'v=spf1') !== false) {
                return ['ok' => true, 'detail' => $this->clip($txt)];
            }
        }
        return ['ok' => false, 'detail' => __('mail_dns_spf_missing')];
    }

    /** @return array{ok:bool,detail:string,selector:?string} */
    private function checkDkim(string $domain): array
    {
        foreach (self::DKIM_SELECTORS as $selector) {
            $host = $selector . '._domainkey.' . $domain;
            $records = $this->txtRecords($host);
            foreach ($records as $txt) {
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
        $records = $this->txtRecords('_dmarc.' . $domain);
        foreach ($records as $txt) {
            if (stripos($txt, 'v=DMARC1') !== false) {
                return ['ok' => true, 'detail' => $this->clip($txt)];
            }
        }
        return ['ok' => false, 'detail' => __('mail_dns_dmarc_missing')];
    }

    /** @return array{ok:bool,detail:string} */
    private function checkMx(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_MX);
        if (!is_array($records) || $records === []) {
            return ['ok' => false, 'detail' => __('mail_dns_mx_missing')];
        }
        $parts = [];
        foreach ($records as $row) {
            $host = (string) ($row['target'] ?? '');
            if ($host !== '') {
                $parts[] = $host;
            }
        }
        return ['ok' => $parts !== [], 'detail' => $parts !== [] ? implode(', ', $parts) : __('mail_dns_mx_missing')];
    }

    /** @return list<string> */
    private function txtRecords(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT);
        if (!is_array($records)) {
            return [];
        }
        $out = [];
        foreach ($records as $row) {
            $txt = (string) ($row['txt'] ?? '');
            if ($txt !== '') {
                $out[] = $txt;
            }
        }
        return $out;
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

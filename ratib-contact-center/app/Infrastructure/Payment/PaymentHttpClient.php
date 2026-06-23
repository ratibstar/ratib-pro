<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Payment;

final class PaymentHttpClient
{
    /** @param array<string, string> $headers @return array{status:int,body:string,json:?array} */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => self::formatHeaders($headers),
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new \RuntimeException('HTTP request failed: ' . $err);
        }
        $json = json_decode($response, true);
        return ['status' => $status, 'body' => $response, 'json' => is_array($json) ? $json : null];
    }

    /** @param array<string, string> $headers @return list<string> */
    private static function formatHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $out[] = $k . ': ' . $v;
        }
        return $out;
    }
}

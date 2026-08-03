<?php
declare(strict_types=1);

namespace Rateb\App\Payment\Gateways;

final class MoyasarHttpClient
{
    private const API_BASE = 'https://api.moyasar.com/v1';

    public function __construct(
        private readonly int $timeoutSeconds = 30,
    ) {
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string, json: array<string, mixed>|null}
     */
    public function request(string $method, string $path, string $secretKey, ?string $body = null, array $headers = []): array
    {
        $url = str_starts_with($path, 'http') ? $path : self::API_BASE . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => '', 'json' => null];
        }

        $headerLines = array_merge([
            'Authorization: Basic ' . base64_encode($secretKey . ':'),
            'Accept: application/json',
        ], $headers);

        if ($body !== null) {
            $headerLines[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeoutSeconds),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $bodyStr = is_string($responseBody) ? $responseBody : '';
        $json = json_decode($bodyStr, true);

        return [
            'status' => $status,
            'body' => $bodyStr,
            'json' => is_array($json) ? $json : null,
        ];
    }
}

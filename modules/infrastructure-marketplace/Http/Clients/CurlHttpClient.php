<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Http\Clients;

use RATEB\InfrastructureMarketplace\Http\Contracts\HttpClientInterface;
use RATEB\InfrastructureMarketplace\Http\Contracts\HttpResponse;

final class CurlHttpClient implements HttpClientInterface
{
    private int $timeoutSeconds;

    public function __construct(int $timeoutSeconds = 20) {
        $this->timeoutSeconds = $timeoutSeconds;
    }


    public function get(string $url, array $headers = [], array $query = []): HttpResponse
    {
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $this->request('GET', $url, $headers, null);
    }

    public function post(string $url, array $headers = [], array $jsonBody = []): HttpResponse
    {
        return $this->request('POST', $url, $headers, $jsonBody);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $jsonBody
     */
    private function request(string $method, string $url, array $headers, ?array $jsonBody): HttpResponse
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize HTTP client.');
        }

        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }
        if ($jsonBody !== null) {
            $headerLines[] = 'Content-Type: application/json';
        }

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $len = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            },
        ]);

        if ($jsonBody !== null) {
            $payload = json_encode($jsonBody, JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                throw new \RuntimeException('Failed to encode HTTP JSON payload.');
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $rawBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($rawBody === false) {
            throw new \RuntimeException('HTTP request failed: ' . $error);
        }

        $decoded = json_decode($rawBody, true);
        $json = is_array($decoded) ? $decoded : null;

        return new HttpResponse($status, $responseHeaders, $rawBody, $json);
    }
}


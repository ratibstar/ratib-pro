<?php
declare(strict_types=1);

interface RatebVideoProvider
{
    public function name(): string;

    public function configured(): bool;

    /** @return array{task_id:string} */
    public function generateFromImage(string $imageDataUri, string $prompt, int $seconds, string $ratio): array;

    /** @return array{task_id:string} */
    public function generateFromText(string $prompt, int $seconds, string $ratio): array;

    /** @return array{task_id:string} */
    public function generateFromVideo(string $videoUri, string $prompt, int $seconds, string $ratio): array;

    /** @return array{status:string,url:string,error:string} */
    public function getTaskStatus(string $taskId): array;

    public function downloadResult(string $url): string;
}

function rateb_video_provider(): RatebVideoProvider
{
    return new RunwayVideoProvider();
}

final class RunwayVideoProvider implements RatebVideoProvider
{
    public function name(): string
    {
        return 'runway';
    }

    public function configured(): bool
    {
        return trim((string) getenv('RUNWAY_API_KEY')) !== '';
    }

    public function generateFromImage(string $imageDataUri, string $prompt, int $seconds, string $ratio): array
    {
        return $this->create('/v1/image_to_video', [
            'model' => 'gen4.5',
            'promptImage' => $imageDataUri,
            'promptText' => $prompt,
            'ratio' => $ratio,
            'duration' => $seconds,
        ]);
    }

    public function generateFromText(string $prompt, int $seconds, string $ratio): array
    {
        return $this->create('/v1/text_to_video', [
            'model' => 'gen4.5',
            'promptText' => $prompt,
            'ratio' => $ratio,
            'duration' => $seconds,
        ]);
    }

    public function generateFromVideo(string $videoUri, string $prompt, int $seconds, string $ratio): array
    {
        return $this->create('/v1/video_to_video', [
            'model' => 'gen4_aleph',
            'videoUri' => $videoUri,
            'promptText' => $prompt,
        ]);
    }

    public function getTaskStatus(string $taskId): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{8,80}$/', $taskId) !== 1) {
            return ['status' => 'failed', 'url' => '', 'error' => 'rejected'];
        }
        $response = $this->request('GET', '/v1/tasks/' . rawurlencode($taskId), null);
        $status = strtoupper((string) ($response['status'] ?? ''));
        if (in_array($status, ['PENDING', 'THROTTLED'], true)) {
            return ['status' => 'queued', 'url' => '', 'error' => ''];
        }
        if ($status === 'RUNNING') {
            return ['status' => 'processing', 'url' => '', 'error' => ''];
        }
        if ($status === 'SUCCEEDED') {
            $output = $response['output'] ?? [];
            $url = is_array($output) ? (string) ($output[0] ?? '') : '';
            if ($url === '' || !str_starts_with($url, 'https://')) {
                return ['status' => 'failed', 'url' => '', 'error' => 'provider'];
            }
            return ['status' => 'completed', 'url' => $url, 'error' => ''];
        }
        return ['status' => 'failed', 'url' => '', 'error' => 'provider'];
    }

    public function downloadResult(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('rejected');
        }
        if (preg_match('/(^|\.)(runwayml\.com|runway\.com|cloudfront\.net)$/', $host) !== 1) {
            throw new RuntimeException('rejected');
        }
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if (!is_string($body) || $code < 200 || $code >= 300 || !str_contains(substr($body, 0, 32), 'ftyp')) {
            throw new RuntimeException('provider');
        }
        if (strlen($body) < 1024 || strlen($body) > 80 * 1024 * 1024) {
            throw new RuntimeException('provider');
        }
        return $body;
    }

    /** @param array<string,mixed> $payload @return array{task_id:string} */
    private function create(string $path, array $payload): array
    {
        $response = $this->request('POST', $path, $payload);
        $id = (string) ($response['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('provider');
        }
        return ['task_id' => $id];
    }

    /** @param array<string,mixed>|null $payload @return array<string,mixed> */
    private function request(string $method, string $path, ?array $payload): array
    {
        $key = trim((string) getenv('RUNWAY_API_KEY'));
        if ($key === '') {
            throw new RuntimeException('not_configured');
        }
        $handle = curl_init('https://api.dev.runwayml.com' . $path);
        $headers = [
            'Authorization: Bearer ' . $key,
            'X-Runway-Version: 2024-11-06',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($code === 401 || $code === 403) {
            throw new RuntimeException('auth');
        }
        if ($code === 400 || $code === 422) {
            throw new RuntimeException('rejected');
        }
        if (!is_string($body) || $code < 200 || $code >= 300) {
            throw new RuntimeException('provider');
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}

<?php
declare(strict_types=1);

/**
 * Online free-candidate video adapters.
 * Generation methods must only be called after FREE_AVAILABLE verification.
 */

/**
 * Public site origin used when staging images for providers that cannot accept data URIs.
 */
function rateb_video_public_origin(): string
{
    $env = trim((string) getenv('RATEB_PUBLIC_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '' && preg_match('/^[a-z0-9.-]+$/i', $host) === 1) {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        return ($https ? 'https://' : 'http://') . $host;
    }
    return 'https://ai.rateb.sa';
}

/**
 * Agnes Flash reference mode only accepts https:// image URLs.
 * data:image URIs from the RATEB engine are staged under public/assets/video-refs/.
 */
function rateb_video_agnes_https_image(string $src): string
{
    $src = trim($src);
    if (str_starts_with($src, 'https://')) {
        return $src;
    }
    if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $src, $match)) {
        throw new RuntimeException('rejected_image');
    }
    $comma = strpos($src, ',');
    if ($comma === false) {
        throw new RuntimeException('rejected_image');
    }
    $bin = base64_decode(substr($src, $comma + 1), true);
    if ($bin === false || strlen($bin) < 64 || strlen($bin) > 8_000_000) {
        throw new RuntimeException('rejected_image');
    }
    $ext = strtolower($match[1]);
    if ($ext === 'jpg') {
        $ext = 'jpeg';
    }
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    $dir = dirname(__DIR__, 2) . '/public/assets/video-refs';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('storage');
    }
    // Drop staged refs older than 48h (best-effort; ignore failures).
    $cutoff = time() - 172800;
    foreach (glob($dir . '/*.{jpg,png,webp}', GLOB_BRACE) ?: [] as $old) {
        $mtime = @filemtime($old);
        if (is_int($mtime) && $mtime < $cutoff) {
            @unlink($old);
        }
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $path = $dir . '/' . $name;
    if (file_put_contents($path, $bin, LOCK_EX) === false) {
        throw new RuntimeException('storage');
    }
    @chmod($path, 0644);
    return rateb_video_public_origin() . '/public/assets/video-refs/' . $name;
}

final class AgnesVideoProvider implements RatebVideoProvider
{
    public function name(): string
    {
        return 'agnes';
    }

    public function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function modelId(): string
    {
        // agnes-video-v2.0 retired 2026-09-25; Flash is the documented Angel $0 candidate.
        $model = trim((string) rateb_video_env('RATEB_AGNES_VIDEO_MODEL'));
        return $model !== '' ? $model : 'agnes-video-2.5-flash';
    }

    /** @return array{ok:bool,error?:string,http?:int} */
    public function probeAccount(): array
    {
        // Non-generation auth probe: invalid create payload should fail validation before billing.
        // Prefer a GET-style check; Agnes documents no balance endpoint, so auth with a safe models-less HEAD/GET on base.
        $key = $this->apiKey();
        if ($key === '') {
            return ['ok' => false, 'error' => 'not_configured'];
        }
        $handle = curl_init('https://apihub.agnes-ai.com/v1/models');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($code === 401 || $code === 403) {
            return ['ok' => false, 'error' => 'auth', 'http' => $code];
        }
        // 200 OK or 404 (no public models list) still proves transport/auth path without creating video.
        if ($code === 200 || $code === 404 || $code === 405) {
            return ['ok' => true, 'http' => $code];
        }
        if (!is_string($body)) {
            return ['ok' => false, 'error' => 'provider', 'http' => $code];
        }
        // Some gateways return 400 on /models; treat non-auth failures as connectivity ok if not 401/402/403.
        if ($code === 400 || $code === 422) {
            return ['ok' => true, 'http' => $code];
        }
        if ($code === 402) {
            return ['ok' => false, 'error' => 'rejected_credit', 'http' => $code];
        }
        return ['ok' => false, 'error' => 'provider', 'http' => $code];
    }

    public function generateFromImage(string $imageDataUri, string $prompt, int $seconds, string $ratio): array
    {
        // Agnes reference mode requires fetchable HTTPS URLs (not data: URIs).
        return $this->generateFromReferences([rateb_video_agnes_https_image($imageDataUri)], $prompt, $seconds, $ratio);
    }

    /**
     * Reference-mode generation with one or more publicly reachable HTTPS image URLs.
     * Flash allows at most 5 reference images; data URIs are staged to a public HTTPS path.
     *
     * @param list<string> $httpsImageUrls
     * @return array{task_id:string}
     */
    public function generateFromReferences(array $httpsImageUrls, string $prompt, int $seconds, string $ratio): array
    {
        $seconds = max(4, min(12, $seconds));
        $images = [];
        foreach ($httpsImageUrls as $url) {
            $url = rateb_video_agnes_https_image(trim((string) $url));
            $images[] = $url;
            if (count($images) >= 5) {
                break;
            }
        }
        if ($images === []) {
            throw new RuntimeException('rejected_image');
        }
        return $this->create([
            'model' => $this->modelId(),
            'prompt' => mb_substr($prompt, 0, 1500),
            'mode' => 'reference',
            'seconds' => (string) $seconds,
            'size' => '720P',
            'aspect_ratio' => $this->aspect($ratio),
            'images' => $images,
            'n' => 1,
        ]);
    }

    public function generateFromText(string $prompt, int $seconds, string $ratio): array
    {
        $seconds = max(4, min(12, $seconds));
        return $this->create([
            'model' => $this->modelId(),
            'prompt' => mb_substr($prompt, 0, 1500),
            'mode' => 'text',
            'seconds' => (string) $seconds,
            'size' => '720P',
            'aspect_ratio' => $this->aspect($ratio),
            'n' => 1,
        ]);
    }

    public function generateFromVideo(string $videoUri, string $prompt, int $seconds, string $ratio): array
    {
        throw new RuntimeException('rejected_model');
    }

    public function getTaskStatus(string $taskId): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{6,120}$/', $taskId) !== 1) {
            return ['status' => 'failed', 'url' => '', 'error' => 'rejected'];
        }
        $url = 'https://apihub.agnes-ai.com/agnesapi?video_id=' . rawurlencode($taskId)
            . '&model_name=' . rawurlencode($this->modelId());
        $response = $this->request('GET', $url, null, true);
        $status = strtolower((string) ($response['status'] ?? ''));
        if (in_array($status, ['queued', 'pending', 'processing', 'in_progress'], true)) {
            return ['status' => $status === 'queued' || $status === 'pending' ? 'queued' : 'processing', 'url' => '', 'error' => ''];
        }
        if ($status === 'completed') {
            $out = (string) ($response['url'] ?? '');
            if ($out === '' || !str_starts_with($out, 'https://')) {
                return ['status' => 'failed', 'url' => '', 'error' => 'provider'];
            }
            return ['status' => 'completed', 'url' => $out, 'error' => ''];
        }
        return ['status' => 'failed', 'url' => '', 'error' => 'provider'];
    }

    public function downloadResult(string $url): string
    {
        return rateb_video_free_download($url, ['agnes-ai.com', 'agnes.ai']);
    }

    /** @param array<string,mixed> $payload @return array{task_id:string} */
    private function create(array $payload): array
    {
        $response = $this->request('POST', 'https://apihub.agnes-ai.com/v1/videos', $payload, false);
        $id = (string) ($response['video_id'] ?? $response['id'] ?? $response['task_id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('provider');
        }
        return ['task_id' => $id];
    }

    /** @param array<string,mixed>|null $payload @return array<string,mixed> */
    private function request(string $method, string $url, ?array $payload, bool $absoluteUrl): array
    {
        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('not_configured');
        }
        $handle = curl_init($absoluteUrl ? $url : $url);
        $headers = ['Authorization: Bearer ' . $key];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
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
        if ($code === 402 || $code === 429) {
            throw new RuntimeException($code === 429 ? 'http_429' : 'rejected_credit');
        }
        if ($code === 400 || $code === 422) {
            $blob = is_string($body) ? strtolower($body) : '';
            if (rateb_video_free_error_is_quota('rejected', $blob)) {
                throw new RuntimeException('rejected_credit');
            }
            throw new RuntimeException(str_contains($blob, 'image') ? 'rejected_image' : 'rejected');
        }
        if ($code === 503) {
            $blob = is_string($body) ? strtolower($body) : '';
            // Official Agnes capacity signal — not a RATEB billing failure.
            if (str_contains($blob, 'video_queue_full') || str_contains($blob, 'queue is full')) {
                throw new RuntimeException('http_429');
            }
        }
        if (!is_string($body) || $code < 200 || $code >= 300) {
            throw new RuntimeException('provider');
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function apiKey(): string
    {
        return trim((string) rateb_video_env('AGNES_API_KEY'));
    }

    private function aspect(string $ratio): string
    {
        $ratio = trim($ratio);
        return match ($ratio) {
            '9:16', '720:1280' => '9:16',
            '1:1', '720:720' => '1:1',
            default => '16:9',
        };
    }
}

final class ReplicateVideoProvider implements RatebVideoProvider
{
    public function name(): string
    {
        return 'replicate';
    }

    public function configured(): bool
    {
        return $this->apiToken() !== '';
    }

    public function modelId(): string
    {
        $model = trim((string) rateb_video_env('RATEB_REPLICATE_VIDEO_MODEL'));
        return $model !== '' ? $model : 'minimax/video-01';
    }

    /** @return array{ok:bool,error?:string,http?:int,username?:string} */
    public function probeAccount(): array
    {
        $token = $this->apiToken();
        if ($token === '') {
            return ['ok' => false, 'error' => 'not_configured'];
        }
        $handle = curl_init('https://api.replicate.com/v1/account');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($code === 401 || $code === 403) {
            return ['ok' => false, 'error' => 'auth', 'http' => $code];
        }
        if (!is_string($body) || $code < 200 || $code >= 300) {
            return ['ok' => false, 'error' => 'provider', 'http' => $code];
        }
        $decoded = json_decode($body, true);
        return [
            'ok' => true,
            'http' => $code,
            'username' => is_array($decoded) ? (string) ($decoded['username'] ?? '') : '',
        ];
    }

    public function generateFromImage(string $imageDataUri, string $prompt, int $seconds, string $ratio): array
    {
        // minimax/video-01 produces ~6s clips; Free plan allows 5 — request default model behavior.
        $input = [
            'prompt' => mb_substr($prompt, 0, 1500),
        ];
        if (str_starts_with($imageDataUri, 'data:image/') || str_starts_with($imageDataUri, 'https://')) {
            $input['first_frame_image'] = $imageDataUri;
        } else {
            throw new RuntimeException('rejected_image');
        }
        return $this->create($input);
    }

    public function generateFromText(string $prompt, int $seconds, string $ratio): array
    {
        return $this->create(['prompt' => mb_substr($prompt, 0, 1500)]);
    }

    public function generateFromVideo(string $videoUri, string $prompt, int $seconds, string $ratio): array
    {
        throw new RuntimeException('rejected_model');
    }

    public function getTaskStatus(string $taskId): array
    {
        if (preg_match('/^[A-Za-z0-9_\/.-]{6,200}$/', $taskId) !== 1) {
            return ['status' => 'failed', 'url' => '', 'error' => 'rejected'];
        }
        $response = $this->request('GET', 'https://api.replicate.com/v1/predictions/' . rawurlencode($taskId), null);
        $status = strtolower((string) ($response['status'] ?? ''));
        if (in_array($status, ['starting', 'processing'], true)) {
            return ['status' => $status === 'starting' ? 'queued' : 'processing', 'url' => '', 'error' => ''];
        }
        if ($status === 'succeeded') {
            $output = $response['output'] ?? null;
            $url = '';
            if (is_string($output) && str_starts_with($output, 'https://')) {
                $url = $output;
            } elseif (is_array($output)) {
                $first = $output[0] ?? '';
                $url = is_string($first) ? $first : '';
            }
            if ($url === '' || !str_starts_with($url, 'https://')) {
                return ['status' => 'failed', 'url' => '', 'error' => 'provider'];
            }
            return ['status' => 'completed', 'url' => $url, 'error' => ''];
        }
        $err = strtolower((string) json_encode($response['error'] ?? ''));
        if (rateb_video_free_error_is_quota('provider', $err)) {
            return ['status' => 'failed', 'url' => '', 'error' => 'rejected_credit'];
        }
        return ['status' => 'failed', 'url' => '', 'error' => 'provider'];
    }

    public function downloadResult(string $url): string
    {
        return rateb_video_free_download($url, ['replicate.delivery', 'replicate.com', 'replicate.media']);
    }

    /** @param array<string,mixed> $input @return array{task_id:string} */
    private function create(array $input): array
    {
        $response = $this->request('POST', 'https://api.replicate.com/v1/models/' . $this->modelId() . '/predictions', [
            'input' => $input,
        ]);
        // Prefer versions API shape if model path create fails — handled by caller errors.
        $id = (string) ($response['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('provider');
        }
        return ['task_id' => $id];
    }

    /** @param array<string,mixed>|null $payload @return array<string,mixed> */
    private function request(string $method, string $url, ?array $payload): array
    {
        $token = $this->apiToken();
        if ($token === '') {
            throw new RuntimeException('not_configured');
        }
        $handle = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Prefer: wait=0',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
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
        if ($code === 402 || $code === 429) {
            throw new RuntimeException($code === 429 ? 'http_429' : 'rejected_credit');
        }
        if (!is_string($body) || $code < 200 || $code >= 300) {
            $blob = is_string($body) ? strtolower($body) : '';
            if (rateb_video_free_error_is_quota('provider', $blob)) {
                throw new RuntimeException('rejected_credit');
            }
            throw new RuntimeException('provider');
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function apiToken(): string
    {
        $token = trim((string) rateb_video_env('REPLICATE_API_TOKEN'));
        if ($token !== '') {
            return $token;
        }
        return trim((string) rateb_video_env('REPLICATE_API_KEY'));
    }
}

final class RunwareVideoProvider implements RatebVideoProvider
{
    public function name(): string
    {
        return 'runware';
    }

    public function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function modelId(): string
    {
        $model = trim((string) rateb_video_env('RATEB_RUNWARE_VIDEO_MODEL'));
        return $model !== '' ? $model : 'pixverse:1@7';
    }

    /** @return array{ok:bool,error?:string,http?:int,balance?:float} */
    public function probeAccount(): array
    {
        $key = $this->apiKey();
        if ($key === '') {
            return ['ok' => false, 'error' => 'not_configured'];
        }
        $payload = json_encode([[
            'taskType' => 'accountManagement',
            'taskUUID' => $this->uuid(),
            'operation' => 'getDetails',
        ]], JSON_UNESCAPED_UNICODE);
        $handle = curl_init('https://api.runware.ai/v1');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($code === 401 || $code === 403) {
            return ['ok' => false, 'error' => 'auth', 'http' => $code];
        }
        if (!is_string($body) || $code < 200 || $code >= 300) {
            return ['ok' => false, 'error' => 'provider', 'http' => $code];
        }
        $decoded = json_decode($body, true);
        $row = is_array($decoded['data'][0] ?? null) ? $decoded['data'][0] : null;
        if (!is_array($row) || !isset($row['balance'])) {
            return ['ok' => false, 'error' => 'missing_balance', 'http' => $code];
        }
        return ['ok' => true, 'http' => $code, 'balance' => (float) $row['balance']];
    }

    public function generateFromImage(string $imageDataUri, string $prompt, int $seconds, string $ratio): array
    {
        $seconds = max(5, min(10, $seconds > 0 ? $seconds : 5));
        $taskUUID = $this->uuid();
        $payload = [[
            'taskType' => 'videoInference',
            'taskUUID' => $taskUUID,
            'model' => $this->modelId(),
            'positivePrompt' => mb_substr($prompt, 0, 1500),
            'duration' => $seconds,
            'resolution' => '720p',
            'includeCost' => true,
            'inputs' => [
                'frameImages' => [
                    ['image' => $imageDataUri, 'frame' => 'first'],
                ],
            ],
        ]];
        $response = $this->request($payload);
        $row = is_array($response['data'][0] ?? null) ? $response['data'][0] : null;
        if (!is_array($row)) {
            throw new RuntimeException('provider');
        }
        $cost = $row['cost'] ?? null;
        if (is_numeric($cost) && (float) $cost > 0) {
            // Hard stop: never accept a billable Runware video under free-only rules.
            throw new RuntimeException('rejected_credit');
        }
        $videoUrl = (string) ($row['videoURL'] ?? $row['videoUrl'] ?? '');
        if ($videoUrl !== '' && str_starts_with($videoUrl, 'https://')) {
            // Sync completion — encode URL into task id for status short-circuit.
            return ['task_id' => 'done:' . rawurlencode($videoUrl)];
        }
        return ['task_id' => $taskUUID];
    }

    public function generateFromText(string $prompt, int $seconds, string $ratio): array
    {
        throw new RuntimeException('rejected_model');
    }

    public function generateFromVideo(string $videoUri, string $prompt, int $seconds, string $ratio): array
    {
        throw new RuntimeException('rejected_model');
    }

    public function getTaskStatus(string $taskId): array
    {
        if (str_starts_with($taskId, 'done:')) {
            $url = rawurldecode(substr($taskId, 5));
            if (!str_starts_with($url, 'https://')) {
                return ['status' => 'failed', 'url' => '', 'error' => 'provider'];
            }
            return ['status' => 'completed', 'url' => $url, 'error' => ''];
        }
        if (preg_match('/^[A-Za-z0-9-]{8,80}$/', $taskId) !== 1) {
            return ['status' => 'failed', 'url' => '', 'error' => 'rejected'];
        }
        // Poll getResponse for async tasks.
        $payload = [[
            'taskType' => 'getResponse',
            'taskUUID' => $taskId,
        ]];
        try {
            $response = $this->request($payload);
        } catch (RuntimeException $e) {
            return ['status' => 'failed', 'url' => '', 'error' => $e->getMessage()];
        }
        $row = is_array($response['data'][0] ?? null) ? $response['data'][0] : null;
        if (!is_array($row)) {
            return ['status' => 'processing', 'url' => '', 'error' => ''];
        }
        $status = strtolower((string) ($row['status'] ?? ''));
        $videoUrl = (string) ($row['videoURL'] ?? $row['videoUrl'] ?? '');
        if ($videoUrl !== '' && str_starts_with($videoUrl, 'https://')) {
            $cost = $row['cost'] ?? null;
            if (is_numeric($cost) && (float) $cost > 0) {
                return ['status' => 'failed', 'url' => '', 'error' => 'rejected_credit'];
            }
            return ['status' => 'completed', 'url' => $videoUrl, 'error' => ''];
        }
        if (in_array($status, ['error', 'failed'], true)) {
            return ['status' => 'failed', 'url' => '', 'error' => 'provider'];
        }
        return ['status' => 'processing', 'url' => '', 'error' => ''];
    }

    public function downloadResult(string $url): string
    {
        return rateb_video_free_download($url, ['runware.ai']);
    }

    /** @param list<array<string,mixed>> $payload @return array<string,mixed> */
    private function request(array $payload): array
    {
        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('not_configured');
        }
        $handle = curl_init('https://api.runware.ai/v1');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($code === 401 || $code === 403) {
            throw new RuntimeException('auth');
        }
        if ($code === 402 || $code === 429) {
            throw new RuntimeException($code === 429 ? 'http_429' : 'rejected_credit');
        }
        if (!is_string($body) || $code < 200 || $code >= 300) {
            $blob = is_string($body) ? strtolower($body) : '';
            if (rateb_video_free_error_is_quota('provider', $blob)) {
                throw new RuntimeException('rejected_credit');
            }
            throw new RuntimeException('provider');
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function apiKey(): string
    {
        return trim((string) rateb_video_env('RUNWARE_API_KEY'));
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

/**
 * @param list<string> $allowedSuffixes
 */
function rateb_video_free_download(string $url, array $allowedSuffixes): string
{
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (($parts['scheme'] ?? '') !== 'https' || $host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
        throw new RuntimeException('rejected');
    }
    $okHost = false;
    foreach ($allowedSuffixes as $suffix) {
        $suffix = strtolower($suffix);
        if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
            $okHost = true;
            break;
        }
    }
    if (!$okHost) {
        throw new RuntimeException('rejected');
    }
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 90,
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

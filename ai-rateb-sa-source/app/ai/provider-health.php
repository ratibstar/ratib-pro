<?php
declare(strict_types=1);

/** @return array{ok:bool,label:string,detail:string} */
function rateb_health_probe_wan(string $url, string $token): array
{
    $url = rtrim(trim($url), '/');
    if ($url === '' || $token === '') {
        return ['ok' => false, 'label' => 'not_configured', 'detail' => 'missing url/token'];
    }
    $target = $url . (str_contains($url, '/health') ? '' : '/health');
    $ch = curl_init($target);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http >= 200 && $http < 400) {
        return ['ok' => true, 'label' => 'reachable', 'detail' => 'HTTP ' . $http];
    }
    return ['ok' => false, 'label' => 'unreachable', 'detail' => 'HTTP ' . $http . ($body !== false && $body !== '' ? '' : ' (no body)')];
}

/** @return list<array{operation:string,last_at:?string,count_24h:int}> */
function rateb_health_usage_24h(PDO $pdo): array
{
    $ops = ['text_campaign', 'ai_image', 'saudi_voice', 'video_storyboard'];
    $out = [];
    foreach ($ops as $op) {
        $stmt = $pdo->prepare("SELECT MAX(created_at) AS last_at, COUNT(*) AS c FROM usage_events WHERE operation = ? AND status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([$op]);
        $row = $stmt->fetch() ?: [];
        $out[] = [
            'operation' => $op,
            'last_at' => $row['last_at'] ?? null,
            'count_24h' => (int) ($row['c'] ?? 0),
        ];
    }
    return $out;
}

function rateb_health_video_summary(PDO $pdo): array
{
    $lastOk = $pdo->query("SELECT MAX(updated_at) FROM video_jobs WHERE status = 'completed'")->fetchColumn();
    $lastErr = $pdo->query("SELECT error_code, MAX(updated_at) AS t FROM video_jobs WHERE error_code <> '' GROUP BY error_code ORDER BY t DESC LIMIT 1")->fetch();
    return [
        'last_completed' => $lastOk !== false ? (string) $lastOk : null,
        'last_error_code' => $lastErr ? (string) $lastErr['error_code'] : null,
        'last_error_at' => $lastErr ? (string) $lastErr['t'] : null,
    ];
}
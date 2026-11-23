<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/plans.php';

$userId = (int) ($argv[1] ?? 66);
$campaignId = (int) ($argv[2] ?? 80);
$jobFile = '/home/admin/.campaign-media/image-jobs/user-' . $userId . '.json';
$pdo = db();

echo "user={$userId} campaign={$campaignId}\n";
$raw = is_file($jobFile) ? (string) file_get_contents($jobFile) : '';
echo "jobFile={$jobFile}\n{$raw}\n";

$data = json_decode($raw, true);
$jobs = is_array($data['jobs'] ?? null) ? $data['jobs'] : [];
foreach ($jobs as $job) {
    $jobId = (string) ($job['job_id'] ?? '');
    $usageId = (int) ($job['usage_id'] ?? 0);
    if ($jobId !== '') {
        $ch = curl_init('https://aihorde.net/api/v2/generate/status/' . rawurlencode($jobId));
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = (string) curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "cancel job {$jobId} http={$http} body=" . substr($body, 0, 200) . PHP_EOL;
    }
    if ($usageId > 0) {
        $ok = rateb_reservation_settle($pdo, $usageId, $userId, 'failed', 'ai_image');
        echo "settle usage {$usageId} refund=" . ($ok ? 'yes' : 'no') . PHP_EOL;
    }
}

if (is_file($jobFile)) {
    file_put_contents($jobFile, json_encode(['jobs' => []], JSON_UNESCAPED_UNICODE));
    echo "cleared job file\n";
}

$bal = $pdo->prepare('SELECT credits_balance FROM users WHERE id = ?');
$bal->execute([$userId]);
echo 'credits_balance=' . $bal->fetchColumn() . PHP_EOL;

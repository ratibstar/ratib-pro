<?php
declare(strict_types=1);

$ai = require dirname(__DIR__) . '/config/ai.php';
$key = trim((string) ($ai['horde_api_key'] ?? ''));
echo 'horde_key_present=' . ($key !== '' ? 'yes' : 'no') . PHP_EOL;
echo 'horde_key_len=' . strlen($key) . PHP_EOL;
echo 'horde_key_anon=' . ($key === '' || $key === '0000000000' ? 'yes' : 'no') . PHP_EOL;

$ch = curl_init('https://aihorde.net/api/v2/status/models?type=image');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'apikey: ' . ($key !== '' ? $key : '0000000000'),
        'Client-Agent: rateb-ai-campaign:1.0:ai.rateb.sa',
    ],
]);
$body = (string) curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "models_http={$http}\n";
$list = json_decode($body, true);
if (!is_array($list)) {
    echo "models_invalid\n";
    exit(1);
}
usort($list, static fn($a, $b) => ((int) ($b['count'] ?? 0)) <=> ((int) ($a['count'] ?? 0)));
foreach (array_slice($list, 0, 12) as $row) {
    if (!is_array($row)) {
        continue;
    }
    echo ($row['name'] ?? '?') . ' count=' . (int) ($row['count'] ?? 0) . PHP_EOL;
}

$want = [
    'AbsoluteReality',
    'Edge Of Realism',
    'Realistic Vision',
    'Dreamshaper',
    'Deliberate',
    'Deliberate 3.0',
    'stable_diffusion',
    "ICBINP - I Can't Believe It's Not Photography",
    'majicMIX realistic',
];
echo "=== preferred counts ===\n";
foreach ($want as $name) {
    $c = 0;
    foreach ($list as $row) {
        if (is_array($row) && (string) ($row['name'] ?? '') === $name) {
            $c = (int) ($row['count'] ?? 0);
            break;
        }
    }
    echo $name . '=' . $c . PHP_EOL;
}

$jobFile = '/home/admin/.campaign-media/image-jobs/user-66.json';
echo 'job66=' . (is_file($jobFile) ? (string) file_get_contents($jobFile) : 'missing') . PHP_EOL;

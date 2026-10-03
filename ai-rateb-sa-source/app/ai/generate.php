<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }
$csrf = (string) ($_POST['csrf'] ?? $_POST['csrf_token'] ?? '');
$expected = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrf'] ?? '');
if ($expected === '' || !hash_equals($expected, $csrf)) { http_response_code(419); echo json_encode(['error'=>'Invalid request']); exit; }

$id = (int)($_POST['campaign_id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM campaigns WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$campaign = $stmt->fetch();
if (!$campaign) { http_response_code(404); echo json_encode(['error'=>'Campaign not found']); exit; }

$cfg = require __DIR__ . '/../../config/ai.php';
if (!$cfg['api_key']) {
    http_response_code(503);
    echo json_encode(['error'=>'AI API key is not configured. Set RATEB_AI_API_KEY on the server.']);
    exit;
}

$lang = $_POST['lang'] ?? 'en';
if ($lang !== 'ar' && $lang !== 'en') {
    $lang = 'en';
}
$languageInstruction = $lang === 'ar'
    ? 'Write every JSON value in Arabic.'
    : 'Write every JSON value in English.';
$prompt = "Create a complete marketing campaign. Return valid JSON with English keys only: strategy, ad_copy, social_posts, whatsapp, product_description, video_ideas, voiceover, content_plan_7_days. {$languageInstruction} Campaign title: {$campaign['title']}. Product/service: {$campaign['product_name']}. Description: {$campaign['description']}. Price: {$campaign['price']}. Target customer: {$campaign['target_customer']}." . campaign_brand_context(db(), (int) $_SESSION['user_id'], $campaign) . " Write useful marketing content. Do not include markdown fences.";

$payload = json_encode([
    'model'=>$cfg['model'],
    'messages'=>[
        ['role'=>'system','content'=>'You are RATEB AI, a professional marketing campaign generator. Return JSON only.'],
        ['role'=>'user','content'=>$prompt]
    ],
    'temperature'=>0.7,
    'response_format'=>['type'=>'json_object']
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(rtrim($cfg['base_url'],'/') . '/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST=>true,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$cfg['api_key']],
    CURLOPT_POSTFIELDS=>$payload,
    CURLOPT_TIMEOUT=>90,
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
if ($err || $code < 200 || $code >= 300) {
    http_response_code(502);
    echo json_encode(['error'=>'AI provider request failed.']);
    exit;
}
$data = json_decode($raw, true);
$content = $data['choices'][0]['message']['content'] ?? '';
$result = json_decode($content, true);
if (!is_array($result) && preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $content, $fenced) === 1) {
    $result = json_decode($fenced[1], true);
}
if (!is_array($result) && preg_match('/(\{.*\})/s', $content, $braced) === 1) {
    $result = json_decode($braced[1], true);
}
if (is_array($result) && count($result) === 1) {
    $only = reset($result);
    if (is_array($only) && isset($only['strategy'])) {
        $result = $only;
    }
}
if (!is_array($result)) $result = ['strategy'=>$content];

db()->prepare("DELETE FROM campaign_outputs WHERE campaign_id = ?")->execute([$id]);
$ins = db()->prepare("INSERT INTO campaign_outputs (campaign_id, output_type, content) VALUES (?, ?, ?)");
foreach ($result as $type=>$value) {
    $ins->execute([$id, $type, is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)]);
}
db()->prepare("UPDATE campaigns SET status = IF(status = 'draft', 'in_progress', status), updated_at=NOW() WHERE id=?")->execute([$id]);
echo json_encode(['ok'=>true,'redirect'=>'/campaign.php?id='.$id], JSON_UNESCAPED_UNICODE);

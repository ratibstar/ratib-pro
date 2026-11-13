<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();

$campaignId = (int) ($_POST['campaign_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$owned = db()->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
$owned->execute([$campaignId, $userId]);
$campaign = $owned->fetch();
if (!$campaign) {
    http_response_code(404);
    exit('Campaign not found.');
}
$decision = rateb_output_language(db(), $campaign);
$speech = $decision['speech'] === 'en' ? 'en' : 'ar';
$kind = (string) ($_POST['kind'] ?? '');

if ($kind === 'copy') {
    $outputId = (int) ($_POST['output_id'] ?? 0);
    $text = trim((string) ($_POST['text'] ?? ''));
    if ($text === '') {
        header('Location: /campaign.php?id=' . $campaignId . '#copy');
        exit;
    }
    $text = mb_substr($text, 0, 4000);
    $row = db()->prepare('SELECT content, approval_status FROM campaign_outputs WHERE id = ? AND campaign_id = ?');
    $row->execute([$outputId, $campaignId]);
    $current = $row->fetch();
    if (!$current) {
        http_response_code(404);
        exit('Not found.');
    }
    $content = rateb_replace_speech_text((string) $current['content'], $text, $speech);
    rateb_save_edited_output($current, $outputId, $campaignId, $userId, $content);
    header('Location: /campaign.php?id=' . $campaignId . '#copy');
    exit;
}

if ($kind === 'strategy') {
    $row = db()->prepare("SELECT id, content, approval_status FROM campaign_outputs WHERE campaign_id = ? AND output_type = 'strategy' ORDER BY id DESC LIMIT 1");
    $row->execute([$campaignId]);
    $current = $row->fetch();
    if (!$current) {
        header('Location: /campaign.php?id=' . $campaignId . '#strategy');
        exit;
    }
    $document = json_decode((string) $current['content'], true);
    if (!is_array($document)) {
        $document = [];
    }
    $fields = $_POST['field'] ?? [];
    if (!is_array($fields)) {
        $fields = [];
    }
    foreach (rateb_strategy_keys() as $key) {
        $text = trim((string) ($fields[$key] ?? ''));
        if ($text === '') {
            continue;
        }
        $document[$key] = rateb_replace_speech_text(
            is_string($document[$key] ?? null) ? (string) $document[$key] : json_encode($document[$key] ?? '', JSON_UNESCAPED_UNICODE),
            mb_substr($text, 0, 2000),
            $speech
        );
        $decoded = json_decode((string) $document[$key], true);
        $document[$key] = is_array($decoded) ? $decoded : $document[$key];
    }
    rateb_save_edited_output($current, (int) $current['id'], $campaignId, $userId, (string) json_encode($document, JSON_UNESCAPED_UNICODE));
    header('Location: /campaign.php?id=' . $campaignId . '#strategy');
    exit;
}

header('Location: /campaign.php?id=' . $campaignId);
exit;

/** @param array<string,mixed> $current */
function rateb_save_edited_output(array $current, int $outputId, int $campaignId, int $userId, string $content): void
{
    $state = rateb_edited_output_state((string) $current['content'], $content, (string) ($current['approval_status'] ?? 'draft'));
    db()->prepare('UPDATE campaign_outputs SET content = ?, approval_status = ? WHERE id = ? AND campaign_id = ?')
        ->execute([$content, $state['status'], $outputId, $campaignId]);
    if ($state['invalidate_ready']) {
        db()->prepare("UPDATE campaigns SET status = 'in_progress', updated_at = NOW() WHERE id = ? AND user_id = ? AND status IN ('ready', 'completed')")
            ->execute([$campaignId, $userId]);
    }
}

function rateb_replace_speech_text(string $content, string $text, string $speech): string
{
    $decoded = json_decode($content, true);
    if (is_array($decoded) && (array_key_exists('ar', $decoded) || array_key_exists('en', $decoded))) {
        $decoded[$speech] = $text;
        $decoded['ar'] = (string) ($decoded['ar'] ?? '');
        $decoded['en'] = (string) ($decoded['en'] ?? '');
        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }
    return $text;
}

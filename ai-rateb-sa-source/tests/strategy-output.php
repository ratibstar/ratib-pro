<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/plans.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$keys = rateb_strategy_keys();
$schema = rateb_output_schema($keys, 'ar');
check(($schema['schema']['additionalProperties'] ?? true) === false, 'Arabic strategy schema rejects extra fields');
check(($schema['schema']['required'] ?? []) === $keys, 'Arabic strategy schema requires every strategy field');
check(($schema['schema']['properties']['positioning']['type'] ?? '') === 'string', 'Arabic strategy values stay plain strings');

$bilingual = rateb_output_schema($keys, 'bilingual');
check(
    ($bilingual['schema']['properties']['core_message']['required'] ?? []) === ['ar', 'en'],
    'bilingual strategy values require Arabic and English'
);

$plain = [
    'positioning' => 'برنامج واضح لشركات الاستقدام',
    'core_message' => 'رسالة مباشرة للشركات',
    'audience' => 'مديرو شركات الاستقدام',
    'channels' => 'واتساب والبريد',
    'tone' => 'رسمي وواضح',
    'creative_direction' => 'صور مكتبية هادئة',
    'call_to_action' => 'اطلب عرضًا الآن',
];
check(rateb_strategy_valid(rateb_prepare_output($plain, 'ar', $keys), 'ar'), 'plain Arabic strategy is accepted');

$shaped = [
    'strategy' => [
        'positioning' => ['text' => 'برنامج واضح لشركات الاستقدام'],
        'coreMessage' => 'رسالة مباشرة للشركات',
        'audience' => ['مديرو شركات الاستقدام'],
        'channels' => ['واتساب', 'البريد'],
        'tone' => ['ar' => 'رسمي وواضح', 'en' => 'Formal'],
        'creativeDirection' => 'صور مكتبية هادئة',
        'callToAction' => 'اطلب عرضًا الآن',
    ],
];
$prepared = rateb_prepare_output($shaped, 'ar', $keys);
check(rateb_strategy_valid($prepared, 'ar'), 'nested camelCase strategy is accepted');
check($prepared['tone'] === 'رسمي وواضح', 'Arabic campaign keeps the Arabic tone');
check($prepared['channels'] === "واتساب\n\nالبريد", 'channel list stays the supplied names');
check(!str_contains((string) $prepared['tone'], 'Formal'), 'Arabic campaign does not keep the English tone');

$partial = rateb_prepare_output(['positioning' => 'نص عربي كاف للحقل'], 'ar', $keys);
check(!rateb_strategy_valid($partial, 'ar'), 'missing strategy fields stay invalid');
check(!array_key_exists('audience', $partial) || !rateb_value_fits($partial['audience'] ?? null, 'ar'), 'missing audience is not invented');

$english = $plain;
$english['channels'] = 'Instagram, Snapchat, TikTok';
check(!rateb_strategy_valid(rateb_prepare_output($english, 'ar', $keys), 'ar'), 'English-only channel list is still rejected for Arabic');
$mixed = $plain;
$mixed['channels'] = 'واتساب و Instagram';
$mixed['tone'] = 'رسمي';
check(rateb_output_ready(rateb_prepare_output($mixed, 'ar', $keys), 'ar', $keys), 'Arabic strategy with a channel name is saved');
check(!rateb_output_ready(rateb_prepare_output($english, 'ar', $keys), 'ar', $keys), 'an English strategy is not saved for an Arabic campaign');

$wrapped = rateb_json_object("Here is the draft:\n```json\n" . json_encode(['positioning' => 'نص'], JSON_UNESCAPED_UNICODE) . "\n```");
check(is_array($wrapped) && ($wrapped['positioning'] ?? '') === 'نص', 'fenced model JSON is readable');

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL\n";
exit($failures === 0 ? 0 : 1);

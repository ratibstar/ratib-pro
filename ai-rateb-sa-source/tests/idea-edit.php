<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/idea.php';

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$description = 'RATEB AI منصة ذكية لإنشاء وإدارة الحملات التسويقية. تساعد المستخدمين على إعداد محتوى الحملات، وإنشاء الصور الإعلانية بالذكاء الاصطناعي، وتنظيم الحملات وإدارتها من خلال منصة واحدة. تستهدف أصحاب المشاريع والشركات والمسوقين الذين يريدون تبسيط أعمالهم التسويقية وتسريع إنتاج المحتوى.';
$direct = rateb_edit_changes($description, []);
check(is_array($direct) && ($direct['description'] ?? '') === rateb_idea_description_clean($description), 'a complete Arabic campaign description is saved directly');
check(count($direct ?? []) === 1, 'a direct description changes no unstated field');

$command = rateb_edit_changes('غيّر الفكرة إلى منصة تنشئ صوراً للحملات', []);
check(($command['description'] ?? '') === 'منصة تنشئ صوراً للحملات', 'the explicit change command still works');

$location = rateb_edit_changes('غيّر الموقع إلى جدة', []);
check(($location['location'] ?? '') === 'جدة', 'specific field edits still work');

check(rateb_edit_changes('ما عجبني عدله', []) === null, 'an unclear comment is not stored as campaign facts');
check(rateb_edit_changes('', []) === [], 'an empty edit remains empty');

echo $failures === 0 ? 'RESULT=PASS' : 'RESULT=FAIL (' . $failures . ')', PHP_EOL;
exit($failures === 0 ? 0 : 1);

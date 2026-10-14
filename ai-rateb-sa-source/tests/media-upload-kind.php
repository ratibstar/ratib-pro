<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/media/library.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

check(media_upload_kind('', 'video') === 'video', 'a video-card upload is treated as video');
check(media_upload_kind('video', 'video') === 'video', 'an explicit video kind stays video');
check(media_upload_kind('', 'images') === 'image', 'an image-card upload is treated as an image');
check(media_upload_kind('image', '') === 'image', 'an explicit image kind does not need a card');
check(media_upload_kind('', '') === '', 'a missing kind is still rejected');

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL\n";
exit($failures === 0 ? 0 : 1);

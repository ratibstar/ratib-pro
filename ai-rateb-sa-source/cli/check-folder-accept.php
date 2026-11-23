<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/plans.php';
$facts = ['product' => 'RATEB AI منصة تسويق حملات إعلان محتوى صور'];
$caption = 'a pile of folders with a blank page coming out of it on a desk';
echo 'printed=' . (rateb_image_caption_has_printed_desk_materials($caption) ? '1' : '0') . PHP_EOL;
echo 'reject=' . (rateb_image_caption_hard_reject($facts, $caption) ?? 'null') . PHP_EOL;
echo 'accept=' . (rateb_image_caption_hard_accept($facts, $caption) ? '1' : '0') . PHP_EOL;

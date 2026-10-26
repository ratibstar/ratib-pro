<?php
declare(strict_types=1);

/*
 * Real-ffmpeg helpers for the video tests: render through the engine's own command builders,
 * then inspect the MP4 that came out. Needs app/video/runtime.php and GD.
 */

/**
 * Two zoompan clips from a GD still, composed with the optional PNG watermark and WAV voice.
 *
 * @return array{file:string,detail:string}
 */
function vt_render(string $ffmpeg, string $dir, bool $watermark, bool $audio): array
{
    $image = imagecreatetruecolor(360, 640);
    imagefill($image, 0, 0, imagecolorallocate($image, 15, 118, 110));
    imagefilledrectangle($image, 80, 200, 280, 440, imagecolorallocate($image, 150, 110, 40));
    $still = $dir . '/vt-still.jpg';
    imagejpeg($image, $still, 85);
    imagedestroy($image);
    $clips = [];
    foreach ([0, 1] as $index) {
        $clip = $dir . '/vt-clip-' . $index . '.mp4';
        if (!rateb_video_run_ffmpeg(rateb_video_clip_command($ffmpeg, $still, $index, 25, $clip) . ' 2> ' . escapeshellarg($dir . '/vt-clip.err'), 60)) {
            return ['file' => '', 'detail' => 'clip render failed: ' . trim((string) @file_get_contents($dir . '/vt-clip.err'))];
        }
        $clips[] = $clip;
    }
    $list = $dir . '/vt-list.txt';
    file_put_contents($list, rateb_video_concat_list($clips));
    $voice = '';
    if ($audio) {
        $voice = $dir . '/vt-voice.wav';
        file_put_contents($voice, vt_wav(2));
    }
    $mark = '';
    if ($watermark) {
        $mark = $dir . '/vt-mark.png';
        if (!rateb_video_watermark_png($mark)) {
            return ['file' => '', 'detail' => 'the watermark PNG could not be drawn'];
        }
    }
    $out = $dir . '/vt-final-' . ($watermark ? 'mark' : 'plain') . '-' . ($audio ? 'voice' : 'silent') . '.mp4';
    if (!rateb_video_run_ffmpeg(rateb_video_compose_command($ffmpeg, $list, $voice, $mark, $out) . ' 2> ' . escapeshellarg($dir . '/vt-compose.err'), 120)) {
        return ['file' => '', 'detail' => 'compose failed: ' . trim((string) @file_get_contents($dir . '/vt-compose.err'))];
    }
    return ['file' => $out, 'detail' => 'ok'];
}

/** A mono 8 kHz 16-bit PCM WAV with a 440 Hz tone. */
function vt_wav(int $seconds): string
{
    $rate = 8000;
    $samples = '';
    for ($i = 0; $i < $rate * $seconds; $i++) {
        $samples .= pack('v', ((int) (sin(2 * M_PI * 440 * $i / $rate) * 8000)) & 0xFFFF);
    }
    return 'RIFF' . pack('V', 36 + strlen($samples)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16) . 'data' . pack('V', strlen($samples)) . $samples;
}

/**
 * What the MP4 really contains: H.264 video, AAC audio, whether every stream decodes, and how
 * many bright pixels a decoded frame has inside the watermark's rectangle (-1 if unreadable).
 *
 * @return array{video:string,audio:string,decodes:string,mark_pixels:int}
 */
function vt_inspect(string $ffmpeg, string $mp4, string $dir): array
{
    $bytes = is_file($mp4) ? (string) file_get_contents($mp4) : '';
    $bin = escapeshellarg($ffmpeg);
    $result = [
        'video' => str_contains(substr($bytes, 0, 32), 'ftyp') && str_contains($bytes, 'avc1') ? 'h264' : 'none',
        'audio' => str_contains($bytes, 'mp4a') ? 'aac' : 'none',
        'decodes' => 'no',
        'mark_pixels' => -1,
    ];
    $maps = ' -map 0:v:0' . ($result['audio'] === 'aac' ? ' -map 0:a:0' : '');
    if ($bytes !== '' && rateb_video_run_ffmpeg($bin . ' -nostdin -v error -i ' . escapeshellarg($mp4) . $maps . ' -f null -', 60)) {
        $result['decodes'] = 'yes';
    }
    $sizer = $dir . '/vt-size.png';
    $size = rateb_video_watermark_png($sizer) ? getimagesize($sizer) : false;
    @unlink($sizer);
    if ($bytes === '' || $size === false) {
        return $result;
    }
    [$width, $height] = $size;
    $raw = $dir . '/vt-corner.gray';
    $crop = 'crop=' . $width . ':' . $height . ':iw-' . ($width + 24) . ':ih-' . ($height + 24) . ',format=gray';
    if (rateb_video_run_ffmpeg($bin . ' -nostdin -v error -y -ss 0.5 -i ' . escapeshellarg($mp4) . ' -frames:v 1 -vf ' . escapeshellarg($crop) . ' -f rawvideo ' . escapeshellarg($raw), 60)) {
        $pixels = (string) @file_get_contents($raw);
        if (strlen($pixels) === $width * $height) {
            $result['mark_pixels'] = count(array_filter(unpack('C*', $pixels) ?: [], static fn (int $value): bool => $value > 200));
        }
    }
    @unlink($raw);
    return $result;
}

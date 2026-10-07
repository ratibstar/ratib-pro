<?php
declare(strict_types=1);

function rateb_vision_provider(): ?string
{
    $name = trim((string) getenv('RATEB_VISION_PROVIDER'));
    return $name !== '' ? $name : null;
}

function rateb_vision_analyze(string $bytes, string $mime): ?array
{
    if (rateb_vision_provider() === null || $bytes === '' || $mime === '') {
        return null;
    }
    return null;
}

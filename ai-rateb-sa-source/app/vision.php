<?php
declare(strict_types=1);

function rateb_vision_provider(): ?string
{
    return null;
}

function rateb_vision_analyze(string $bytes, string $mime): ?array
{
    if (rateb_vision_provider() === null || $bytes === '' || $mime === '') {
        return null;
    }
    return null;
}

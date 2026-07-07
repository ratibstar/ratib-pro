<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var string $csrf */

$payload = (new \Rateb\App\Pos\Services\PosRegisterBootstrapService())
    ->lightPayload(is_array($context ?? null) ? $context : [], $csrf ?? '');

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

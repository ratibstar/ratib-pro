<?php
declare(strict_types=1);

function rateb_moyasar_hook_token(): string
{
    return trim((string) getenv('MOYASAR_WEBHOOK_TOKEN'));
}

function rateb_moyasar_callback_url(string $secret): string
{
    $base = 'https://ai.rateb.sa/app/payment/webhook.php';
    $hook = rateb_moyasar_hook_token();
    if ($hook !== '') {
        return $base . '?hook=' . rawurlencode($hook);
    }
    return $base . '?token=' . rawurlencode($secret);
}

function rateb_moyasar_callback_authorized(string $secret, string $hook, string $legacyProvided, string $hookProvided): bool
{
    if ($hook !== '') {
        return $hookProvided !== '' && hash_equals($hook, $hookProvided);
    }
    return $secret !== '' && $legacyProvided !== '' && hash_equals($secret, $legacyProvided);
}

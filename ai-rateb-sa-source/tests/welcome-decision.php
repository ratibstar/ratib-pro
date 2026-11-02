<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/welcome.php';

function assert_decision(string $expected, array $signals): void
{
    $actual = rateb_welcome_decision($signals);
    if ($actual !== $expected) {
        fwrite(STDERR, "expected {$expected} got {$actual} for " . json_encode($signals) . "\n");
        exit(1);
    }
}

assert_decision('grant', ['device_grants' => 0, 'device_signups' => 0, 'device_issued' => false]);
assert_decision('grant', ['device_grants' => 1, 'device_signups' => 0, 'device_issued' => false]);
assert_decision('review', ['device_grants' => 2, 'device_signups' => 0, 'device_issued' => false]);
assert_decision('grant', ['device_grants' => 0, 'device_signups' => 1, 'device_issued' => false]);
assert_decision('review', ['device_grants' => 0, 'device_signups' => 2, 'device_issued' => false]);
assert_decision('review', ['device_grants' => 0, 'device_signups' => 0, 'device_issued' => true]);
assert_decision('duplicate', ['email_granted' => true, 'device_grants' => 0, 'device_signups' => 0]);

$secret = getenv('RATEB_WELCOME_HMAC_SECRET');
putenv('RATEB_WELCOME_HMAC_SECRET');
try {
    rateb_welcome_email_hash('person@example.com');
    fwrite(STDERR, "missing HMAC secret did not fail\n");
    exit(1);
} catch (RuntimeException) {
}
if (is_string($secret) && $secret !== '') {
    putenv('RATEB_WELCOME_HMAC_SECRET=' . $secret);
}
putenv('RATEB_WELCOME_HMAC_SECRET=test-secret');
$left = rateb_welcome_email_hash(' Person@Example.com ');
$right = rateb_welcome_email_hash('person@example.com');
if ($left !== $right || $left === rateb_welcome_legacy_email_hash('person@example.com')) {
    fwrite(STDERR, "HMAC normalization failed\n");
    exit(1);
}

echo "welcome decision ok\n";

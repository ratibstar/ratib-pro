<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/plans.php';
require_once __DIR__ . '/support/fake-ledger.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

function db(): PDO
{
    return $GLOBALS['refund_pdo'];
}

$pdo = new FakeLedger();
$GLOBALS['refund_pdo'] = $pdo;
$pdo->balances[7] = 25;
$charge = rateb_credit_charge($pdo, 7, 'text_campaign', 3);
check($charge > 0 && $pdo->balances[7] === 20, 'a text charge takes 5 credits once');
check(rateb_refund_credits(7, 'text_campaign', 3) === true && $pdo->balances[7] === 25, 'one failed text operation refunds that charge once');
check(rateb_refund_credits(7, 'text_campaign', 3) === false && $pdo->balances[7] === 25, 'a second refund attempt does not increase the balance');
check(count($pdo->rows('text_campaign_refund')) === 1, 'exactly one refund event is stored');

$before = $pdo->balances[7];
$first = rateb_credit_charge($pdo, 7, 'text_campaign', 3);
$second = rateb_credit_charge($pdo, 7, 'text_campaign', 3);
check($first > 0 && $second > 0 && $first !== $second && $pdo->balances[7] === $before - 10, 'two charges for the same user stay separate');
check(rateb_refund_credits(7, 'text_campaign', 3) === true && $pdo->balances[7] === $before - 5, 'only the newest charge is refunded');
check(rateb_refund_credits(7, 'text_campaign', 3) === false && $pdo->balances[7] === $before - 5, 'repeating the newest refund does not refund the older charge');

$pdo->balances[8] = 40;
$image = rateb_credit_charge($pdo, 8, 'ai_image', 9);
check($image > 0 && $pdo->balances[8] === 30, 'an image charge still costs 10');
check(rateb_refund_credits(8, 'text_campaign', 9) === false && $pdo->balances[8] === 30, 'a text refund does not touch a different feature charge');

$racing = new FakeLedger();
$GLOBALS['refund_pdo'] = $racing;
$racing->balances[7] = 20;
$paid = rateb_credit_charge($racing, 7, 'text_campaign', 3);
$racing->events[90] = ['user_id' => 7, 'campaign_id' => 3, 'operation' => 'text_campaign_refund', 'status' => 'completed', 'credits_deducted' => 0, 'age' => 0, 'idempotency_key' => 'text_campaign_charge:' . $paid . ':refund'];
check(rateb_refund_credits(7, 'text_campaign', 3) === false && $racing->balances[7] === 15, 'a refund key already written by a concurrent attempt is not applied again');

echo $failures === 0 ? "OK\n" : "FAILED {$failures}\n";
exit($failures === 0 ? 0 : 1);

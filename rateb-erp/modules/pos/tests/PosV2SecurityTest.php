<?php

declare(strict_types=1);

/**
 * POS V2 security verification (CSRF + Bearer cashier identity).
 *
 * Run: php modules/pos/tests/run-security-tests.php
 */

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Controllers\PosBaseController;
use Rateb\App\Pos\Repositories\V2\Adapters\ErpCashierAdapter;

require_once __DIR__ . '/pos-v2-test-bootstrap.php';

final class PosV2SecurityTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testCsrfValidTokenAccepted();
        $this->testCsrfInvalidTokenRejected();
        $this->testBearerHeaderDetected();
        $this->testApiCashierUsesTenantContext();
        $this->testSessionCashierPrecedenceOverApi();

        return $this->results;
    }

    private function testCsrfValidTokenAccepted(): void
    {
        $_SESSION = [];
        $token = Csrf::token();
        $ok = Csrf::validate($token);
        $this->record('csrf accepts valid session token', $ok, 'expected hash_equals pass');
    }

    private function testCsrfInvalidTokenRejected(): void
    {
        $_SESSION = [];
        Csrf::token();
        $ok = !Csrf::validate('not-a-valid-token');
        $this->record('csrf rejects invalid token', $ok, 'expected validation failure');
    }

    private function testBearerHeaderDetected(): void
    {
        $probe = new class extends PosBaseController {
            public function bearer(): bool
            {
                return $this->isBearerApiRequest();
            }
        };

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-token-123';
        $withBearer = $probe->bearer();
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $withoutBearer = $probe->bearer();

        $ok = $withBearer && !$withoutBearer;
        $this->record('bearer authorization header detected', $ok, 'expected Bearer skip for CSRF routes');
    }

    private function testApiCashierUsesTenantContext(): void
    {
        $_SESSION = [];
        TenantContext::setApiUserId(4242);
        $adapter = new ErpCashierAdapter();
        $ok = $adapter->userId() === 4242;
        TenantContext::setApiUserId(null);
        $this->record('bearer cashier user_id from tenant context', $ok, 'expected api user id without session');
    }

    private function testSessionCashierPrecedenceOverApi(): void
    {
        SessionManager::start();
        $_SESSION['rateb_user_id'] = 77;
        TenantContext::setApiUserId(4242);
        $adapter = new ErpCashierAdapter();
        $ok = $adapter->userId() === 77;
        unset($_SESSION['rateb_user_id']);
        TenantContext::setApiUserId(null);
        $this->record('session cashier takes precedence over bearer context', $ok, 'expected session user id');
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}

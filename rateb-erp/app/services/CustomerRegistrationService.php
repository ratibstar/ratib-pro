<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;
use Rateb\App\Models\Plan;
use Rateb\App\Models\Subscription;
use Rateb\App\Models\User;

final class CustomerRegistrationService
{
    private const TRIAL_DAYS = 14;
    private const DEFAULT_PLAN_SLUG = 'starter';

    /** @return array{company_id:int,user_id:int} */
    public function register(
        string $companyName,
        string $contactName,
        string $email,
        string $password,
        string $phone = ''
    ): array {
        $userModel = new User();
        if ($userModel->findByEmail($email) !== null) {
            throw new \RuntimeException(__('cms_email_taken'));
        }

        $plan = (new Plan())->queryOne(
            'SELECT * FROM rateb_plans WHERE slug = :slug AND is_active = 1 LIMIT 1',
            ['slug' => self::DEFAULT_PLAN_SLUG]
        );
        if ($plan === null) {
            $plan = (new Plan())->queryOne(
                'SELECT * FROM rateb_plans WHERE is_active = 1 ORDER BY price_monthly ASC LIMIT 1'
            );
        }
        if ($plan === null) {
            throw new \RuntimeException(__('cms_register_unavailable'));
        }

        $slug = $this->uniqueCompanySlug($companyName, $email);
        $planId = (int) $plan['id'];
        $modules = $plan['modules'] ?? json_encode(
            PlanLimitService::defaultModules(),
            JSON_UNESCAPED_UNICODE
        );

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $companyId = (new Company())->create([
                'name' => $companyName,
                'slug' => $slug,
                'email' => $email,
                'phone' => $phone,
                'status' => 'active',
                'plan_id' => $planId,
                'user_limit' => (int) ($plan['max_users'] ?? 5),
                'storage_limit_mb' => (int) ($plan['max_storage_mb'] ?? 512),
                'modules' => $modules,
            ]);

            (new Subscription())->create([
                'company_id' => $companyId,
                'plan_id' => $planId,
                'status' => 'trial',
                'billing_cycle' => 'monthly',
                'amount' => (float) ($plan['price_monthly'] ?? 0),
                'starts_at' => date('Y-m-d'),
                'ends_at' => date('Y-m-d', strtotime('+' . self::TRIAL_DAYS . ' days')),
                'auto_renew' => 0,
            ]);

            $userId = (new User())->create([
                'company_id' => $companyId,
                'name' => $contactName,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => $phone,
                'is_super_admin' => 0,
                'status' => 'active',
                'locale' => function_exists('rateb_locale') ? rateb_locale() : 'ar',
            ]);

            $roleRow = $userModel->queryOne(
                "SELECT id FROM rateb_roles WHERE slug = 'company-full-access' LIMIT 1"
            );
            if ($roleRow) {
                (new AuthorizationService())->assignRole($userId, (int) $roleRow['id']);
            }

            (new BarcodeLoginService())->ensureUserBarcode($userId);

            $db->commit();
            return ['company_id' => $companyId, 'user_id' => $userId];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function uniqueCompanySlug(string $name, string $email): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-'));
        if ($slug === '' || strlen($slug) < 2) {
            $local = strstr($email, '@', true) ?: 'company';
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $local), '-'));
        }
        if ($slug === '') {
            $slug = 'company';
        }

        $companyModel = new Company();
        $base = $slug;
        $i = 0;
        while ($companyModel->findBySlug($slug) !== null) {
            $i++;
            $slug = $base . '-' . $i;
        }
        return $slug;
    }
}

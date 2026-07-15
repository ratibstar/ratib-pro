<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Core\Database;
use Rateb\App\Services\AuditService;

/**
 * Phase WEBSITE-04 — Visual form builder + CRM lead routing (tenant-scoped).
 */
final class WebsiteFormService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return list<array<string, mixed>> */
    public function listForms(): array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_website_forms WHERE {$where} ORDER BY id DESC",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['s'] = $slug;

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_website_forms WHERE {$where} AND slug = :s AND is_active = 1 LIMIT 1",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $id;
        $row = $this->repo->fetchOne(
            "SELECT * FROM rateb_website_forms WHERE {$where} AND id = :id LIMIT 1",
            $params
        );
        $this->repo->assertRowCompany($row, 'website_form');

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function fieldsForForm(int $formId): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['fid'] = $formId;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_website_form_fields WHERE {$where} AND form_id = :fid ORDER BY sort_order ASC, id ASC",
            $params
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $fields
     */
    public function saveForm(array $data, array $fields, ?int $id = null): int
    {
        $cid = $this->repo->companyId();
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', strtolower(trim((string) ($data['slug'] ?? 'form')))) ?: 'form';
        $payload = [
            'slug' => $slug,
            'name_en' => trim((string) ($data['name_en'] ?? $slug)),
            'name_ar' => trim((string) ($data['name_ar'] ?? '')),
            'success_message_en' => trim((string) ($data['success_message_en'] ?? '')),
            'success_message_ar' => trim((string) ($data['success_message_ar'] ?? '')),
            'crm_enabled' => !empty($data['crm_enabled']) ? 1 : 0,
            'crm_source_code' => trim((string) ($data['crm_source_code'] ?? 'website_form')) ?: 'website_form',
            'notify_email' => trim((string) ($data['notify_email'] ?? '')) ?: null,
            'is_active' => !isset($data['is_active']) || !empty($data['is_active']) ? 1 : 0,
        ];
        $db = Database::connection();
        if ($id !== null && $id > 0) {
            $existing = $this->find($id);
            if ($existing === null) {
                throw new \RuntimeException('Form not found');
            }
            $stmt = $db->prepare(
                'UPDATE rateb_website_forms SET slug=:slug, name_en=:name_en, name_ar=:name_ar,
                 success_message_en=:success_message_en, success_message_ar=:success_message_ar,
                 crm_enabled=:crm_enabled, crm_source_code=:crm_source_code, notify_email=:notify_email,
                 is_active=:is_active WHERE id=:id AND company_id=:company_id'
            );
            $payload['id'] = $id;
            $payload['company_id'] = $cid;
            $stmt->execute($payload);
            $formId = $id;
            $db->prepare('DELETE FROM rateb_website_form_fields WHERE form_id = :fid AND company_id = :cid')
                ->execute(['fid' => $formId, 'cid' => $cid]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO rateb_website_forms (company_id, slug, name_en, name_ar, success_message_en, success_message_ar,
                 crm_enabled, crm_source_code, notify_email, is_active)
                 VALUES (:company_id, :slug, :name_en, :name_ar, :success_message_en, :success_message_ar,
                 :crm_enabled, :crm_source_code, :notify_email, :is_active)'
            );
            $payload['company_id'] = $cid;
            $stmt->execute($payload);
            $formId = (int) $db->lastInsertId();
        }
        $ins = $db->prepare(
            'INSERT INTO rateb_website_form_fields
             (company_id, form_id, field_key, field_type, label_en, label_ar, placeholder_en, placeholder_ar,
              options_json, is_required, validation_json, sort_order)
             VALUES (:company_id, :form_id, :field_key, :field_type, :label_en, :label_ar, :placeholder_en, :placeholder_ar,
              :options_json, :is_required, :validation_json, :sort_order)'
        );
        foreach (array_values($fields) as $i => $field) {
            if (!is_array($field)) {
                continue;
            }
            $fkey = preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim((string) ($field['field_key'] ?? 'field_' . $i)))) ?: ('field_' . $i);
            $opts = $field['options_json'] ?? $field['options'] ?? null;
            if (is_array($opts)) {
                $opts = json_encode($opts, JSON_UNESCAPED_UNICODE);
            }
            $val = $field['validation_json'] ?? $field['validation'] ?? null;
            if (is_array($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
            $ins->execute([
                'company_id' => $cid,
                'form_id' => $formId,
                'field_key' => $fkey,
                'field_type' => (string) ($field['field_type'] ?? 'text'),
                'label_en' => (string) ($field['label_en'] ?? $fkey),
                'label_ar' => (string) ($field['label_ar'] ?? ''),
                'placeholder_en' => (string) ($field['placeholder_en'] ?? ''),
                'placeholder_ar' => (string) ($field['placeholder_ar'] ?? ''),
                'options_json' => $opts,
                'is_required' => !empty($field['is_required']) ? 1 : 0,
                'validation_json' => $val,
                'sort_order' => (int) ($field['sort_order'] ?? $i),
            ]);
        }
        (new AuditService())->log('website_form_save', 'website_form', $formId, ['company_id' => $cid]);

        return $formId;
    }

    /**
     * @param array<string, mixed> $input fields[key] => value
     * @return array{ok:bool,message?:string,crm_lead_id?:int}
     */
    public function submit(string $slug, array $input, ?string $ip = null): array
    {
        $form = $this->findBySlug($slug);
        if ($form === null) {
            return ['ok' => false, 'message' => 'Form not found'];
        }
        $formId = (int) $form['id'];
        $fields = $this->fieldsForForm($formId);
        $payload = [];
        foreach ($fields as $field) {
            $key = (string) ($field['field_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $value = $input[$key] ?? '';
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }
            $value = trim((string) $value);
            if (!empty($field['is_required']) && $value === '') {
                return ['ok' => false, 'message' => 'Required: ' . $key];
            }
            $ftype = (string) ($field['field_type'] ?? 'text');
            if ($ftype === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'message' => 'Invalid email'];
            }
            $payload[$key] = $value;
        }
        $cid = $this->repo->companyId();
        $crmLeadId = null;
        if (!empty($form['crm_enabled'])) {
            $crmLeadId = $this->routeToCrm($form, $payload);
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->repo->execute(
            'INSERT INTO rateb_website_form_submissions (company_id, form_id, payload_json, crm_lead_id, ip_address)
             VALUES (:cid, :fid, :payload, :crm, :ip)',
            [
                'cid' => $cid,
                'fid' => $formId,
                'payload' => $json,
                'crm' => $crmLeadId,
                'ip' => $ip,
            ]
        );

        return ['ok' => true, 'crm_lead_id' => $crmLeadId ?? 0];
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, string> $payload
     */
    private function routeToCrm(array $form, array $payload): ?int
    {
        try {
            if (!class_exists(\Rateb\App\Services\LeadService::class)) {
                return null;
            }
            // Ensure ERP tenant company matches website tenant for CRM writes.
            \Rateb\App\Core\TenantContext::setCompanyId($this->repo->companyId());
            $title = (string) ($payload['subject'] ?? $payload['title'] ?? $form['name_en'] ?? 'Website lead');
            $result = (new \Rateb\App\Services\LeadService())->create([
                'title' => $title !== '' ? $title : 'Website lead',
                'contact_name' => (string) ($payload['name'] ?? $payload['full_name'] ?? $payload['contact_name'] ?? ''),
                'email' => (string) ($payload['email'] ?? ''),
                'phone' => (string) ($payload['phone'] ?? $payload['mobile'] ?? ''),
                'notes' => 'Source: ' . (string) ($form['crm_source_code'] ?? 'website_form') . "\n"
                    . (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            return isset($result['id']) ? (int) $result['id'] : null;
        } catch (\Throwable $e) {
            error_log('WebsiteFormService CRM route: ' . $e->getMessage());

            return null;
        }
    }
}

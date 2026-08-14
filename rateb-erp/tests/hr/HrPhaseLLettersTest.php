<?php

declare(strict_types=1);

/**
 * Phase L — Letters + Employee Documents (source / structural gates).
 *
 * Run: php tests/hr/run-hr-phase-l-tests.php
 */
final class HrPhaseLLettersTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $svc = $this->file('/app/services/HrLetterIssueService.php');
        $pdf = $this->file('/app/Lib/HrLetterPdf/HrLetterPdfRenderer.php');
        $mig = $this->file('/migrations/251_hr_phase_l_letters.sql');
        $ops = $this->file('/routes/modules/ops.php');
        $menu = $this->file('/config/hr-menu.php');
        $svc360 = $this->file('/app/services/HrEmployee360Service.php');
        $tab360 = $this->file('/views/company/hr/employees/360-tab.php');
        $docs = $this->file('/app/services/DocumentService.php');
        $ctrl = $this->file('/app/controllers/Company/HrExtendedControllers.php');
        $lookup = $this->file('/app/services/FormLookupService.php');

        $this->record(
            'L0 additive migration document_id/issued_*',
            str_contains($mig, 'document_id')
            && str_contains($mig, 'issued_at')
            && !preg_match('/\bDROP\b/i', $mig)
        );

        $this->record(
            'L1 letter types salary/employment/experience/EOS',
            str_contains($svc, 'salary_certificate')
            && str_contains($svc, 'employment_certificate')
            && str_contains($svc, 'experience_letter')
            && str_contains($svc, 'end_of_service')
            && str_contains($lookup, 'employment_certificate')
        );

        $this->record(
            'L2 request reuses rateb_hr_employee_requests (no parallel SoT)',
            str_contains($svc, 'rateb_hr_employee_requests')
            && !str_contains($svc, 'CREATE TABLE')
            && !preg_match('/\bclass\s+LetterRequest2\b/', $svc)
        );

        $this->record(
            'L3 issue only after approved; Matrix/Oversight unchanged',
            str_contains($svc, "!== 'approved'")
            && str_contains($svc, 'function issue')
            && !str_contains($svc, 'ApprovalEngine')
            && !str_contains($svc, 'WorkflowService2')
        );

        $this->record(
            'L4 Arabic PDF renderer present',
            str_contains($pdf, 'class HrLetterPdfRenderer')
            && str_contains($pdf, 'Identity-H')
            && is_file(RATEB_ROOT . '/app/Lib/HrLetterPdf/fonts/NotoNaskhArabic-Regular.ttf')
            && is_file(RATEB_ROOT . '/app/Lib/HrLetterPdf/TtfCmap.php')
            && str_contains($svc, 'HrLetterPdfRenderer')
        );

        $this->record(
            'L5 store via DocumentService rateb_documents',
            str_contains($svc, 'storeGeneratedBytes')
            && str_contains($docs, 'function storeGeneratedBytes')
            && str_contains($svc, "'hr_employees'")
            && !str_contains($svc, 'rateb_dms_')
        );

        $this->record(
            'L6 download authorization company + employee ownership',
            str_contains($svc, 'function download')
            && str_contains($svc, 'entity_id')
            && str_contains($svc, 'access_denied')
            && str_contains($svc, 'hr_letter_download')
        );

        $this->record(
            'L7 RBAC routes hr/letters under hr-leaves',
            str_contains($ops, 'hr/letters')
            && str_contains($ops, 'HrLettersController')
            && str_contains($ops, 'letters/{id}/issue')
            && str_contains($ops, 'letters/{id}/download')
            && str_contains($menu, 'hr/letters')
        );

        $this->record(
            'L8 audit issue/reissue/download + request create',
            str_contains($svc, 'hr_letter_issue')
            && str_contains($svc, 'hr_letter_reissue')
            && str_contains($svc, 'hr_letter_download')
            && str_contains($ctrl, 'hr_letter_request_create')
        );

        $this->record(
            'L9 Employee 360 letters download/issue wired',
            str_contains($svc360, 'pdf_available')
            && str_contains($svc360, 'download_url')
            && str_contains($svc360, "pdf_deferred' => false")
            && str_contains($tab360, 'hr_letter_download')
            && !str_contains($tab360, 'hr_360_letter_pdf_deferred')
        );

        $this->record(
            'L10 boundaries: no payroll/accounting/ESS/mobile rewrite',
            !str_contains($svc, 'approvePayroll')
            && !str_contains($svc, 'AccountingService')
            && !str_contains($svc, 'HrEss')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-k-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-j-tests.php')
        );

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function file(string $rel): string
    {
        $path = RATEB_ROOT . $rel;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}

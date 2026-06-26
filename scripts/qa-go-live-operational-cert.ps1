# RATIB ERP v1.0 — Operational Go-Live Certification (read-only + token-gated backup)
param([string]$Site = 'https://rateb.sa')

$ErrorActionPreference = 'Stop'
$base = "$Site/rateb-erp/public"
$report = [ordered]@{
    startedAt = (Get-Date).ToString('o')
    site = $Site
    steps = @{}
    stopped = $false
    stopReason = $null
}

function Get-Csrf($html) {
    if ($html -match 'name="_csrf"\s+value="([^"]+)"') { return $matches[1] }
    return ''
}

# --- STEP 4: Infrastructure (HTTP, read-only) ---
$infra = @{}
try {
    $health = Invoke-WebRequest -Uri "$base/erp-health.php" -UseBasicParsing
    $infra.health = @{ status = $health.StatusCode; body = ($health.Content.Trim()) }
} catch { $infra.health = @{ error = $_.Exception.Message } }

try {
    $ping = Invoke-WebRequest -Uri "$base/ping.php" -UseBasicParsing
    $infra.ping = @{ status = $ping.StatusCode; body = ($ping.Content.Substring(0, [Math]::Min(200, $ping.Content.Length))) }
} catch { $infra.ping = @{ error = $_.Exception.Message } }

try {
    $cert = Invoke-WebRequest -Uri "$base/erp-security-cert.php?enterprise=1" -UseBasicParsing
    $certJson = $cert.Content | ConvertFrom-Json
    $infra.enterprise_cert = @{
        status = $cert.StatusCode
        suite_passed = $certJson.enterprise_suite.passed
        suite_failed = $certJson.enterprise_suite.failed
        suite_total = $certJson.enterprise_suite.total
    }
} catch { $infra.enterprise_cert = @{ error = $_.Exception.Message } }

try {
    $login = Invoke-WebRequest -Uri "$base/login" -UseBasicParsing
    $infra.security_headers = @{
        csp = [bool]$login.Headers['Content-Security-Policy']
        xfo = [string]$login.Headers['X-Frame-Options']
        xcto = [string]$login.Headers['X-Content-Type-Options']
        hsts = [bool]$login.Headers['Strict-Transport-Security']
    }
    $infra.robots = @{ url = "$Site/site/robots.txt" }
    $rob = Invoke-WebRequest -Uri "$Site/site/robots.txt" -UseBasicParsing
    $infra.robots.status = $rob.StatusCode
} catch { $infra.security_headers = @{ error = $_.Exception.Message } }

try {
    $build = Invoke-WebRequest -Uri "$base/ratib-erp-build.txt" -UseBasicParsing
    $infra.build_marker = $build.Content.Trim()
} catch { $infra.build_marker = 'unavailable' }

$report.steps['4_infrastructure'] = $infra

# --- STEP 5: Production readiness (super-admin session, read-only) ---
$readiness = @{}
$sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$lp = Invoke-WebRequest -Uri "$base/login" -WebSession $sess -UseBasicParsing
$lr = Invoke-WebRequest -Uri "$base/login" -Method POST -WebSession $sess -Body @{
    email = 'admin@rateb.sa'; password = if ($env:RATEB_QA_PASSWORD) { $env:RATEB_QA_PASSWORD } else { 'password' }
    _csrf = (Get-Csrf $lp.Content)
} -UseBasicParsing -MaximumRedirection 10

if ($lr.Content -match 'too_many|محاولات') {
    $report.stopped = $true
    $report.stopReason = 'Rate limited on super-admin login — wait 15 minutes'
    $readiness.login = @{ ok = $false; rateLimited = $true }
} elseif (-not (($lr.BaseResponse.ResponseUri -match '/admin') -or ($lr.Content -match 'rateb-widget'))) {
    $report.stopped = $true
    $report.stopReason = 'Super-admin login failed'
    $readiness.login = @{ ok = $false }
} else {
    $readiness.login = @{ ok = $true; uri = [string]$lr.BaseResponse.ResponseUri }
    $routes = [ordered]@{
        dashboard = "$base/admin"
        settings = "$base/admin/settings"
        roles = "$base/admin/roles"
        permissions = "$base/admin/permissions"
        companies = "$base/admin/companies"
        branches = "$base/admin/ops/branches"
        billing_invoices = "$base/admin/invoices"
        hr = "$base/admin/hr"
        customers = "$base/admin/customers"
        reports = "$base/admin/reports"
        notifications = "$base/admin/notifications"
        automation = "$base/admin/automation-health"
        queue = "$base/admin/queue-monitor"
        audit = "$base/admin/audit-logs"
        portal = "$base/site/portal"
        api = "$base/api/v1"
        login_activity = "$base/admin/login-activity"
    }
    foreach ($k in $routes.Keys) {
        try {
            $r = Invoke-WebRequest -Uri $routes[$k] -WebSession $sess -UseBasicParsing -MaximumRedirection 10
            $ok = ($r.StatusCode -eq 200) -and ($r.Content -notmatch 'Class &quot;[^&]+&quot; not found')
            $readiness[$k] = @{ ok = $ok; status = $r.StatusCode }
        } catch {
            $code = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode } else { 0 }
            $readiness[$k] = @{ ok = $false; status = $code; error = $_.Exception.Message }
        }
    }
}
$report.steps['5_readiness'] = $readiness

# --- STEP 6: Reset dry-run via security cert ---
try {
    $resetProbe = Invoke-WebRequest -Uri "$base/erp-security-cert.php?enterprise=1&reset_dry_run=1" -UseBasicParsing
    $resetJson = $resetProbe.Content | ConvertFrom-Json
    $resetReport = $resetJson.reset_dry_run.report
    $report.steps['6_reset_dry_run'] = @{
        ok = $null -ne $resetReport
        database = $resetReport.database
        tables_to_truncate = ($resetReport.tables.PSObject.Properties | Measure-Object).Count
        preserved_super_admins = @($resetReport.users.preserved_super_admins | ForEach-Object { $_.email })
        non_admin_users_to_delete = $resetReport.users.deleted_non_admin
    }
} catch {
    $report.steps['6_reset_dry_run'] = @{ ok = $false; error = $_.Exception.Message }
}

# --- STEP 1 & 3: Backup via token endpoint ---
$token = $env:RATEB_ERP_MIGRATE_TOKEN
if (-not $token) {
    $report.steps['1_backup'] = @{ status = 'BLOCKED'; reason = 'RATEB_ERP_MIGRATE_TOKEN not available in agent environment' }
    $report.steps['3_backup_verify'] = @{ status = 'BLOCKED'; reason = 'Requires server-side backup file path' }
    $report.steps['2_restore'] = @{ status = 'BLOCKED'; reason = 'Requires server CLI + temporary database' }
} else {
    try {
        $backupStart = Get-Date
        $resp = Invoke-WebRequest -Uri "$base/enterprise-cert-run.php" -Method POST -WebSession $sess -Body 'action=backup' -ContentType 'application/x-www-form-urlencoded' -Headers @{ 'X-Rateb-Migrate-Token' = $token } -UseBasicParsing -TimeoutSec 900
        $backupJson = $resp.Content | ConvertFrom-Json
        $duration = ((Get-Date) - $backupStart).TotalSeconds
        $output = [string]$backupJson.result.output
        $fileMatch = [regex]::Match($output, 'Backup:\s*(.+\.sql\.gz)')
        $report.steps['1_backup'] = @{
            ok = [bool]$backupJson.ok
            exit_code = $backupJson.result.exit_code
            duration_sec = [math]::Round($duration, 1)
            file = if ($fileMatch.Success) { $fileMatch.Groups[1].Value.Trim() } else { $null }
            output_tail = if ($output.Length -gt 500) { $output.Substring($output.Length - 500) } else { $output }
        }
        if (-not $backupJson.ok) {
            $report.stopped = $true
            $report.stopReason = 'Backup failed — exit code non-zero'
        }
    } catch {
        $report.stopped = $true
        $report.stopReason = "Backup request failed: $($_.Exception.Message)"
        $report.steps['1_backup'] = @{ ok = $false; error = $_.Exception.Message }
    }
}

$report.finishedAt = (Get-Date).ToString('o')
$outPath = Join-Path (Split-Path $PSScriptRoot) "rateb-erp\docs\GA\go-live-operational-cert-$(Get-Date -Format 'yyyyMMdd-HHmmss').json"
$report | ConvertTo-Json -Depth 10 | Set-Content -Path $outPath -Encoding UTF8
Write-Output "REPORT=$outPath"
$report | ConvertTo-Json -Depth 8

# RATEB Mobile — one REAL test per portal role (company, worker, agency)
# Run: powershell -ExecutionPolicy Bypass -File scripts\run-real-test-all-roles.ps1
$ErrorActionPreference = 'Continue'
$base = 'https://out.ratib.sa/api/mobile'

function Step($name, $ok, $detail = '') {
    if ($ok) {
        Write-Host "  [PASS] $name" -ForegroundColor Green
        if ($detail) { Write-Host "         $detail" -ForegroundColor DarkGray }
        return $true
    }
    Write-Host "  [FAIL] $name" -ForegroundColor Red
    if ($detail) { Write-Host "         $detail" -ForegroundColor Yellow }
    return $false
}

function Read-SecurePassword($prompt) {
    $secure = Read-Host $prompt -AsSecureString
    $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }
}

function Test-RoleLogin {
    param(
        [string]$Label,
        [string]$ExpectedRole,
        [string]$Email,
        [string]$Password
    )

    Write-Host "`n========== $Label (expect role: $ExpectedRole) ==========" -ForegroundColor Cyan

    $escaped = $Password.Replace('\', '\\').Replace('"', '\"')
    $loginJson = "{`"email`":`"$Email`",`"password`":`"$escaped`"}"
    $loginRaw = curl.exe -sS -w "|%{http_code}" -X POST "$base/login.php" `
        -H "Content-Type: application/json" --data-raw $loginJson 2>&1
    $parts = $loginRaw -split '\|'
    $body = $parts[0]
    $code = $parts[-1]
    $login = $null
    try { $login = $body | ConvertFrom-Json } catch {}

    $okLogin = Step "Login as $Email" ($code -eq '200' -and $login.success -eq $true) "HTTP $code role=$($login.role)"
    if (-not $okLogin) { return $false }

    $roleOk = Step "Role is $ExpectedRole" ($login.role -eq $ExpectedRole) "got $($login.role)"
    if (-not $roleOk) {
        Write-Host "         Tip: admin -> company; agency partner portal -> agency; worker role user -> worker" -ForegroundColor DarkYellow
    }

    $token = [string]$login.token
    $prof = curl.exe -sS -w "|%{http_code}" -H "Authorization: Bearer $token" "$base/profile.php" 2>&1
    Step 'Profile API' ($prof -match '\|200$' -and $prof -match '"success":true') ''

    $dashUrl = switch ($login.role) {
        'worker' { "$base/worker-dashboard.php" }
        'agency' { "$base/agency-pipeline.php" }
        default  { "$base/company-workers.php" }
    }
    $dash = curl.exe -sS -w "|%{http_code}" -H "Authorization: Bearer $token" $dashUrl 2>&1
    Step "Dashboard API ($($login.role))" ($dash -match '\|200$' -and $dash -match '"success":true') $dashUrl

    $genJson = "{`"user_id`":$($login.user_id),`"ttl_seconds`":600}"
    $genRaw = curl.exe -sS -X POST "$base/qr-generate.php" -H "Authorization: Bearer $token" `
        -H "Content-Type: application/json" --data-raw $genJson 2>&1
    $gen = $null
    try { $gen = $genRaw | ConvertFrom-Json } catch {}
    $qrOk = Step 'QR generate' ($gen.success -eq $true) ''
    if ($qrOk) {
        $payload = [string]$gen.data.qr_payload
        $qrEsc = $payload.Replace('\', '\\').Replace('"', '\"')
        $qrLoginJson = "{`"qr_payload`":`"$qrEsc`"}"
        $qrLoginRaw = curl.exe -sS -w "|%{http_code}" -X POST "$base/qr-login.php" `
            -H "Content-Type: application/json" --data-raw $qrLoginJson 2>&1
        Step 'QR login (one-time)' ($qrLoginRaw -match '\|200$' -and $qrLoginRaw -match '"success":true') 'Payload consumed — use System Settings badge for app scan'
    }

    return ($okLogin -and $roleOk)
}

Write-Host "`nRATEB Mobile — ONE REAL TEST PER ROLE" -ForegroundColor Cyan
Write-Host "API: $base`n" -ForegroundColor DarkGray

$h = curl.exe -sS "$base/health.php" 2>&1
if (-not ($h -match '"success":true')) {
    Write-Host "Health check failed. Stop here.`n$h" -ForegroundColor Red
    exit 1
}
Write-Host "[PASS] Health API" -ForegroundColor Green

Write-Host @"

You need 3 accounts (or skip a role with Enter on email):

  COMPANY  — usually admin / company staff  -> Company Portal
  WORKER   — user whose role name includes worker -> Worker Portal
  AGENCY   — partner agency with portal enabled   -> Agency Portal

"@ -ForegroundColor White

$results = @{}

Write-Host "--- 1/3 COMPANY ---" -ForegroundColor Yellow
$cEmail = Read-Host "Company email (Enter = admin)"
if ([string]::IsNullOrWhiteSpace($cEmail)) { $cEmail = 'admin' }
$cPass = Read-SecurePassword "Password for $cEmail"
$results.company = Test-RoleLogin -Label 'COMPANY' -ExpectedRole 'company' -Email $cEmail -Password $cPass

Write-Host "--- 2/3 WORKER ---" -ForegroundColor Yellow
$wEmail = Read-Host "Worker email (Enter to skip)"
if (-not [string]::IsNullOrWhiteSpace($wEmail)) {
    $wPass = Read-SecurePassword "Password for $wEmail"
    $results.worker = Test-RoleLogin -Label 'WORKER' -ExpectedRole 'worker' -Email $wEmail -Password $wPass
} else {
    Write-Host "  [SKIP] Worker — no email entered" -ForegroundColor DarkYellow
    $results.worker = $null
}

Write-Host "--- 3/3 AGENCY ---" -ForegroundColor Yellow
$aEmail = Read-Host "Agency email or partner ID (Enter to skip)"
if (-not [string]::IsNullOrWhiteSpace($aEmail)) {
    $aPass = Read-SecurePassword "Password for $aEmail"
    $results.agency = Test-RoleLogin -Label 'AGENCY' -ExpectedRole 'agency' -Email $aEmail -Password $aPass
} else {
    Write-Host "  [SKIP] Agency — no email entered" -ForegroundColor DarkYellow
    $results.agency = $null
}

Write-Host "`n========== SUMMARY ==========" -ForegroundColor Cyan
foreach ($key in @('company','worker','agency')) {
    $v = $results[$key]
    if ($null -eq $v) {
        Write-Host "  $key : SKIPPED" -ForegroundColor DarkYellow
    } elseif ($v) {
        Write-Host "  $key : PASS" -ForegroundColor Green
    } else {
        Write-Host "  $key : FAIL" -ForegroundColor Red
    }
}

Write-Host @"

FLUTTER APP (http://127.0.0.1:8090) — repeat each role in the UI:
  1. Sign out if logged in
  2. Sign in with the same email/password
  3. Confirm portal title: Company / Worker / Agency Portal
  4. Optional QR: out.ratib.sa -> System Settings -> user -> Workforce QR -> scan on phone

"@ -ForegroundColor White

# RATEB Mobile — REAL production test (API + optional QR)
# Run: powershell -ExecutionPolicy Bypass -File scripts\run-real-test.ps1
$ErrorActionPreference = 'Continue'
$base = 'https://out.ratib.sa/api/mobile'
$pass = 0
$fail = 0

function Step($name, $ok, $detail = '') {
    if ($ok) {
        Write-Host "[PASS] $name" -ForegroundColor Green
        if ($detail) { Write-Host "       $detail" -ForegroundColor DarkGray }
        $script:pass++
    } else {
        Write-Host "[FAIL] $name" -ForegroundColor Red
        if ($detail) { Write-Host "       $detail" -ForegroundColor Yellow }
        $script:fail++
    }
}

Write-Host "`n========== RATEB MOBILE REAL TEST ==========" -ForegroundColor Cyan
Write-Host "Target: $base`n" -ForegroundColor DarkGray

# --- Automated (no password) ---
$h = curl.exe -sS "$base/health.php" 2>&1
Step 'Health API' ($h -match '"success":true') $h

$p = curl.exe -sS -w "|%{http_code}" -H "Authorization: Bearer aaa.bbb.ccc" "$base/profile.php" 2>&1
Step 'JWT configured (401 not 503)' ($p -match 'Unauthorized' -and $p -notmatch '503|config_error') ($p -replace '\|.*','')

$d = curl.exe -sS -w "|%{http_code}" "$base/debug-env.php" 2>&1
Step 'Debug endpoint removed (404)' ($d -match '\|404$|Not Found') ''

$badLogin = curl.exe -sS -w "|%{http_code}" -X POST "$base/login.php" -H "Content-Type: application/json" --data-raw '{"email":"__no_such_user__","password":"wrong"}' 2>&1
Step 'Bad login returns 401' ($badLogin -match '401|invalid') ($badLogin -replace '\|.*','')

# --- Interactive: real login ---
Write-Host "`n--- REAL LOGIN (production admin) ---" -ForegroundColor Cyan
$email = Read-Host "Email (Enter = admin)"
if ([string]::IsNullOrWhiteSpace($email)) { $email = 'admin' }
$secure = Read-Host "Password for $email" -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try { $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) }
finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }

$escaped = $password.Replace('\', '\\').Replace('"', '\"')
$loginJson = "{`"email`":`"$email`",`"password`":`"$escaped`"}"
$loginRaw = curl.exe -sS -w "|%{http_code}" -X POST "$base/login.php" -H "Content-Type: application/json" --data-raw $loginJson 2>&1
$loginParts = $loginRaw -split '\|'
$loginBody = $loginParts[0]
$loginCode = $loginParts[-1]
$login = $null
try { $login = $loginBody | ConvertFrom-Json } catch {}

Step "Login as $email" ($loginCode -eq '200' -and $login.success -eq $true) "role=$($login.role) user_id=$($login.user_id)"

if (-not $login.success) {
    Write-Host "`nLogin failed — fix password or stop here.`n" -ForegroundColor Red
    exit 1
}

$token = [string]$login.token

# Profile with real token
$prof = curl.exe -sS -w "|%{http_code}" -H "Authorization: Bearer $token" "$base/profile.php" 2>&1
Step 'Profile with real token (200)' ($prof -match '\|200$' -and $prof -match '"success":true') ''

# Role dashboard
$dashUrl = switch ($login.role) {
    'worker'  { "$base/worker-dashboard.php" }
    'agency'  { "$base/agency-pipeline.php" }
    default   { "$base/company-workers.php" }
}
$dash = curl.exe -sS -w "|%{http_code}" -H "Authorization: Bearer $token" $dashUrl 2>&1
Step "Dashboard API ($($login.role))" ($dash -match '\|200$' -and $dash -match '"success":true') $dashUrl

# QR generate + login (full chain)
Write-Host "`n--- QR FULL CHAIN ---" -ForegroundColor Cyan
$genJson = "{`"user_id`":$($login.user_id),`"ttl_seconds`":600}"
$genRaw = curl.exe -sS -X POST "$base/qr-generate.php" -H "Authorization: Bearer $token" -H "Content-Type: application/json" --data-raw $genJson 2>&1
$gen = $genRaw | ConvertFrom-Json
Step 'QR generate' ($gen.success -eq $true) ("payload length=" + [string]$gen.data.qr_payload.Length)

if ($gen.success) {
    $qrPayload = [string]$gen.data.qr_payload
    $qrEsc = $qrPayload.Replace('\', '\\').Replace('"', '\"')
    $qrLoginJson = "{`"qr_payload`":`"$qrEsc`"}"
    $qrLoginRaw = curl.exe -sS -w "|%{http_code}" -X POST "$base/qr-login.php" -H "Content-Type: application/json" --data-raw $qrLoginJson 2>&1
    Step 'QR login (API)' ($qrLoginRaw -match '\|200$' -and $qrLoginRaw -match '"success":true') 'One-time use — OK if tested once'

    $encoded = [uri]::EscapeDataString($qrPayload)
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=$encoded"
    Write-Host "`nQR for PHONE camera scan (open full screen on PC):" -ForegroundColor Yellow
    Write-Host $qrUrl
    try { Start-Process $qrUrl } catch {}
}

# Summary
Write-Host "`n========== RESULT ==========" -ForegroundColor Cyan
Write-Host "PASS: $pass   FAIL: $fail" -ForegroundColor $(if ($fail -eq 0) { 'Green' } else { 'Yellow' })

if ($fail -eq 0) {
    Write-Host @"

ALL API TESTS PASSED — production is real.

FLUTTER APP (do now):
  1. flutter run -d chrome   (or android for camera QR)
  2. Login: same email/password you just used
  3. Confirm portal matches role: $($login.role)
  4. For camera QR: scan the QR image that opened in browser

"@ -ForegroundColor Green
} else {
    Write-Host "Fix FAIL items above before pilot.`n" -ForegroundColor Yellow
}

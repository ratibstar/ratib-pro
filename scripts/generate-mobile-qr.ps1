# Generate a fresh QR payload for RATEB Mobile web testing (paste into Login with QR).
param(
    [string]$Email = 'admin',
    [int]$TtlSeconds = 600
)

$base = 'https://out.ratib.sa/api/mobile'
$secure = Read-Host "Password for $Email" -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
}

$escaped = $password.Replace('\', '\\').Replace('"', '\"')
$loginJson = "{`"email`":`"$Email`",`"password`":`"$escaped`"}"

Write-Host "`nLogging in..." -ForegroundColor Cyan
$loginRaw = curl.exe -sS -X POST "$base/login.php" -H "Content-Type: application/json" --data-raw $loginJson
$login = $loginRaw | ConvertFrom-Json
if (-not $login.success) {
    Write-Host "Login failed: $($login.message)" -ForegroundColor Red
    Write-Host $loginRaw
    exit 1
}

Write-Host "OK — user_id $($login.user_id), role $($login.role)" -ForegroundColor Green

Write-Host "Generating QR..." -ForegroundColor Cyan
$genJson = "{`"user_id`":$($login.user_id),`"ttl_seconds`":$TtlSeconds}"
$genRaw = curl.exe -sS -X POST "$base/qr-generate.php" -H "Authorization: Bearer $($login.token)" -H "Content-Type: application/json" --data-raw $genJson
$gen = $genRaw | ConvertFrom-Json
if (-not $gen.success) {
    Write-Host "QR generate failed: $($gen.message)" -ForegroundColor Red
    Write-Host $genRaw
    exit 1
}

$payload = [string]$gen.data.qr_payload
Write-Host "`n=== COPY THIS LINE (starts with RATEBMOBQR:) ===" -ForegroundColor Yellow
Write-Host $payload
Write-Host "=== Valid for $($gen.data.expires_in) seconds — one use only ===`n" -ForegroundColor Yellow

try {
    Set-Clipboard -Value $payload
    Write-Host "Copied to clipboard." -ForegroundColor Green
} catch {
    Write-Host "Copy the line above manually." -ForegroundColor Yellow
}

$encoded = [uri]::EscapeDataString($payload)
$qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=$encoded"
Write-Host "Scan this QR on your phone (open in browser, full screen):" -ForegroundColor Cyan
Write-Host $qrImageUrl
try {
    Start-Process $qrImageUrl
} catch {
    Write-Host "Open the URL above on your PC screen for the phone to scan." -ForegroundColor Yellow
}

Write-Host @"
PHONE (camera scan — no paste):
  1. Install/run app on Android:  cd rateb_mobile && flutter run -d android
  2. Logout → Login with QR → allow camera
  3. Point phone at the QR on your PC screen

OR use main RATEB badge (RATIBLOGIN QR):
  Log in at https://out.ratib.sa → open employee login badge for a user
  (pages/user-login-barcode.php) → scan with mobile app

WEB: paste only (no camera). Use clipboard steps below if needed.
"@

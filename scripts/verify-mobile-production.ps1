# RATEB Mobile production verify — run after MOBILE_AUTH_SECRET is in server .env + PHP restart
$ErrorActionPreference = 'Continue'
$base = 'https://rateb.sa/api/mobile'

Write-Host "`n=== RATEB Mobile production verify ===`n" -ForegroundColor Cyan

Write-Host "1. Profile with fake Bearer (want 401, not 503)..."
$r1 = curl.exe -sS -w "`n%{http_code}" -H "Authorization: Bearer aaa.bbb.ccc" "$base/profile.php" 2>&1
Write-Host $r1
if ($r1 -match 'HTTP:401|401$|"Unauthorized"') { Write-Host "   PASS" -ForegroundColor Green }
elseif ($r1 -match '503|config_error') { Write-Host "   FAIL — secret still missing. Check server .env + PHP restart." -ForegroundColor Red }
else { Write-Host "   CHECK output above" -ForegroundColor Yellow }

Write-Host "`n2. Health..."
curl.exe -sS "$base/health.php"
Write-Host "`n`nDone. If step 1 PASS, mobile JWT auth is configured.`n" -ForegroundColor Cyan

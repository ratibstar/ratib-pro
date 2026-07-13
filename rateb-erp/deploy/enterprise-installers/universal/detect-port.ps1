# Phase D.3 — first free port from candidates
param(
  [int[]]$Candidates = @(80,443,8080,8088,8099)
)
function Test-PortFree([int]$Port) {
  $inUse = Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue |
    Where-Object { $_.LocalPort -eq $Port }
  return -not $inUse
}
foreach ($p in $Candidates) {
  if (Test-PortFree $p) { Write-Output $p; return }
}
for ($p = 8100; $p -le 8199; $p++) {
  if (Test-PortFree $p) { Write-Output $p; return }
}
throw 'No free HTTP port'

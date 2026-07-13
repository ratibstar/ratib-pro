# Phase D.3 — Windows platform detection
$os = Get-CimInstance Win32_OperatingSystem
$arch = $env:PROCESSOR_ARCHITECTURE
$archNorm = if ($arch -eq 'AMD64') { 'x64' } elseif ($arch -eq 'ARM64') { 'arm64' } else { $arch }
$ver = [version]$os.Version
$ok = ($ver.Major -ge 10) -and ($archNorm -in @('x64','arm64'))
[pscustomobject]@{
  os_caption = $os.Caption
  os_version = $os.Version
  arch = $arch
  arch_norm = $archNorm
  init = 'windows-service'
  supported = $ok
} | ConvertTo-Json -Compress
if (-not $ok) { throw "Unsupported Windows (need Win10+/Server2019+ x64|ARM64)" }

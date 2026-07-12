param(
  [string]$Php,
  [string]$WorkDir,
  [string]$DocRoot,
  [string]$Router,
  [string]$HostAddr,
  [int]$Port
)
$ErrorActionPreference = 'Continue'
$argsList = @('-S', "$HostAddr`:$Port", '-t', $DocRoot, $Router)
$p = Start-Process -FilePath $Php -ArgumentList $argsList -WorkingDirectory $WorkDir -PassThru -WindowStyle Hidden
Start-Sleep -Seconds 2
function Code([string]$Url) {
  return (& curl.exe -s -o NUL -w '%{http_code}' $Url)
}
$login = Code "http://$HostAddr`:$Port/login"
$dash = Code "http://$HostAddr`:$Port/admin/ops/branch-dashboard"
$pos = Code "http://$HostAddr`:$Port/admin/ops/pos/dashboard"
if ($p -and !$p.HasExited) { Stop-Process -Id $p.Id -Force -ErrorAction SilentlyContinue }
Write-Output "LOGIN=$login"
Write-Output "DASH=$dash"
Write-Output "POS=$pos"

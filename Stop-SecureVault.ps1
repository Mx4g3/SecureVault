$ErrorActionPreference = 'Stop'
$runtime = Join-Path $PSScriptRoot '.runtime'
foreach ($service in @('php','mysql')) {
    $pidFile = Join-Path $runtime ($service + '.pid')
    if (!(Test-Path -LiteralPath $pidFile)) { continue }
    $serviceId = [int](Get-Content -LiteralPath $pidFile)
    $process = Get-CimInstance Win32_Process -Filter "ProcessId=$serviceId" -ErrorAction SilentlyContinue
    if ($process -and $process.CommandLine -and $process.CommandLine.Contains($PSScriptRoot) -and $process.Name -in @('php.exe','mysqld.exe')) {
        if ($service -eq 'mysql') {
            $settings = Get-Content -LiteralPath (Join-Path $runtime 'settings.json') -Raw | ConvertFrom-Json
            $admin = Join-Path (Split-Path $process.ExecutablePath) 'mysqladmin.exe'
            & $admin '--host=127.0.0.1' '--port=3308' '--user=root' "--password=$($settings.root_password)" shutdown
        } else { Stop-Process -Id $serviceId }
    }
}
Write-Host 'SecureVault local services stopped.'

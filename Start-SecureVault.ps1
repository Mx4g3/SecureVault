param([string]$XamppPath = 'C:\xampp', [switch]$NoBrowser)
$ErrorActionPreference = 'Stop'
$appRoot = $PSScriptRoot
$runtime = Join-Path $appRoot '.runtime'
$php = Join-Path $XamppPath 'php\php.exe'
$mysql = Join-Path $XamppPath 'mysql\bin\mysqld.exe'
if (!(Test-Path -LiteralPath $php) -or !(Test-Path -LiteralPath $mysql)) { throw 'XAMPP was not found. Run with -XamppPath pointing to your XAMPP installation.' }
New-Item -ItemType Directory -Path $runtime -Force | Out-Null
$settingsPath = Join-Path $runtime 'settings.json'
if (!(Test-Path -LiteralPath $settingsPath)) {
    @{ root_password = ([guid]::NewGuid().ToString('N')); app_password = ([guid]::NewGuid().ToString('N')) } | ConvertTo-Json | Set-Content -LiteralPath $settingsPath
}
$settings = Get-Content -LiteralPath $settingsPath -Raw | ConvertFrom-Json
$dataPath = Join-Path $runtime 'mysql'
if (!(Test-Path -LiteralPath (Join-Path $dataPath 'my.ini'))) {
    & (Join-Path $XamppPath 'mysql\bin\mysql_install_db.exe') "--datadir=$dataPath" '--port=3308' "--password=$($settings.root_password)"
    if ($LASTEXITCODE -ne 0) { throw 'Local database initialization failed.' }
}
function Test-LocalPort([int]$Port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try { $client.Connect('127.0.0.1', $Port); return $true } catch { return $false } finally { $client.Dispose() }
}
if (!(Test-LocalPort 3308)) {
    $dbArgs = @('--no-defaults', ('--basedir="' + (Join-Path $XamppPath 'mysql') + '"'), ('--datadir="' + $dataPath + '"'), '--port=3308', '--bind-address=127.0.0.1', '--default-time-zone=+00:00', '--console')
    $dbProcess = Start-Process -FilePath $mysql -ArgumentList $dbArgs -PassThru -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime 'mysql.stdout.log') -RedirectStandardError (Join-Path $runtime 'mysql.stderr.log')
    $dbProcess.Id | Set-Content -LiteralPath (Join-Path $runtime 'mysql.pid')
    for ($attempt=0; $attempt -lt 30 -and !(Test-LocalPort 3308); $attempt++) { Start-Sleep -Milliseconds 300 }
}
& $php (Join-Path $appRoot 'tools\setup-local.php')
if ($LASTEXITCODE -ne 0) { throw 'Database setup failed. Check whether port 3308 belongs to another database.' }
if (!(Test-LocalPort 8085)) {
    $uploadTemp = Join-Path $runtime 'uploads-tmp'
    New-Item -ItemType Directory -Force -Path $uploadTemp | Out-Null
    $zipArgs = @()
    if ((& $php -r 'echo extension_loaded("zip") ? "yes" : "no";') -ne 'yes') { $zipArgs = @('-d', 'extension=zip') }
    $webArgs = $zipArgs + @('-d', 'upload_max_filesize=10M', '-d', 'post_max_size=12M', '-d', ('upload_tmp_dir="' + $uploadTemp + '"'), '-S', '127.0.0.1:8085', '-t', ('"' + (Join-Path $appRoot 'securevault\frontend') + '"'), ('"' + (Join-Path $appRoot 'tools\router.php') + '"'))
    $webProcess = Start-Process -FilePath $php -ArgumentList $webArgs -PassThru -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime 'php.stdout.log') -RedirectStandardError (Join-Path $runtime 'php.stderr.log')
    $webProcess.Id | Set-Content -LiteralPath (Join-Path $runtime 'php.pid')
    Start-Sleep -Milliseconds 500
}
Write-Host 'SecureVault is ready at http://127.0.0.1:8085'
if (!$NoBrowser) { Start-Process 'http://127.0.0.1:8085' }

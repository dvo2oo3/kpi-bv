# Chay server local. Bam chuot phai > Run with PowerShell, hoac trong terminal VS Code: .\chay.ps1
# CSDL + script nam trong thu muc _local (ben canh file nay) nen khong bi Windows don.
$LOCAL = Join-Path $PSScriptRoot '_local'
$HT    = Join-Path $PSScriptRoot 'htdocs'
$env:QLBV_DSN = "sqlite:$LOCAL\demo.sqlite"

if (-not (Test-Path "$LOCAL\demo.sqlite")) {
    Write-Host "Chua co CSDL. Chay .\reset.ps1 truoc roi chay lai .\chay.ps1" -ForegroundColor Yellow
    exit 1
}

# Tat server cu neu con (giai phong cong 8080)
Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Milliseconds 500

Write-Host "Server: http://127.0.0.1:8080   (Ctrl+C de dung — DE CUA SO NAY MO thi web moi chay)" -ForegroundColor Green
php -d extension=php_pdo_sqlite -S 127.0.0.1:8080 -t $HT

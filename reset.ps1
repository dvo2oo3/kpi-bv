# Reset: dung lai CSDL sach (danh muc + du lieu mau Nhi/TN). KHONG bat server.
# Reset xong chay .\chay.ps1 de bat server.
$ErrorActionPreference = 'Stop'
$LOCAL = Join-Path $PSScriptRoot '_local'
Set-Location $LOCAL
$env:QLBV_DSN = "sqlite:$LOCAL\demo.sqlite"

Write-Host "[1/5] Tat server cu (neu co)..." -ForegroundColor Cyan
Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Milliseconds 500

Write-Host "[2/5] Dung lai CSDL tu schema..." -ForegroundColor Cyan
Remove-Item "$LOCAL\demo.sqlite" -ErrorAction SilentlyContinue
php -d extension=php_pdo_sqlite -r "(new PDO(getenv('QLBV_DSN')))->exec(file_get_contents('schema_sqlite.sql'));"

Write-Host "[3/5] Nap tai khoan mau..." -ForegroundColor Cyan
php -d extension=php_pdo_sqlite seed_demo.php

Write-Host "[4/5] Nap danh muc chi tieu mac dinh..." -ForegroundColor Cyan
php -d extension=php_pdo_sqlite nap_dm_cli.php

Write-Host "[5/5] Nap so lieu that Nhi + Truyen nhiem..." -ForegroundColor Cyan
php -d extension=php_pdo_sqlite seed_full.php

Write-Host ""
Write-Host "XONG. Gio chay:  .\chay.ps1  de bat server." -ForegroundColor Green
Write-Host "Dang nhap: dev/devbvns2026 | admin/adminbvns2026 | bs.nhi/nhibvns2026" -ForegroundColor Green

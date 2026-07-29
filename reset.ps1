# Reset: dung lai CSDL sach (xoa khoa tam, nap lai danh muc + du lieu mau Nhi/TN).
# KHONG bat server. Reset xong chay .\chay.ps1 de bat server.
# Bam chuot phai > Run with PowerShell, hoac chay: .\reset.ps1
$ErrorActionPreference = 'Stop'
$SP = 'C:\Users\duong\AppData\Local\Temp\claude\D--PM-BAN-GIAO--HOAN-ph-n-m-m\c4bcaad3-ad4a-400d-b320-db54de048a2a\scratchpad'
Set-Location $SP
$env:QLBV_DSN = "sqlite:$SP\demo.sqlite"

Write-Host "[1/5] Tat server cu (neu co)..." -ForegroundColor Cyan
Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Milliseconds 500

Write-Host "[2/5] Dung lai CSDL tu schema..." -ForegroundColor Cyan
Remove-Item "$SP\demo.sqlite" -ErrorAction SilentlyContinue
php -d extension=php_pdo_sqlite -r "(new PDO(getenv('QLBV_DSN')))->exec(file_get_contents('schema_sqlite.sql'));"

Write-Host "[3/5] Nap tai khoan mau..." -ForegroundColor Cyan
php -d extension=php_pdo_sqlite seed_demo.php

Write-Host "[4/5] Nap danh muc chi tieu mac dinh..." -ForegroundColor Cyan
php -d extension=php_pdo_sqlite nap_dm_cli.php

Write-Host "[5/5] Nap so lieu that Nhi + Truyen nhiem..." -ForegroundColor Cyan
php -d extension=php_pdo_sqlite seed_full.php

Write-Host ""
Write-Host "XONG. Gio chay:  .\chay.ps1  de bat server." -ForegroundColor Green
Write-Host "Dang nhap: dev/devbvns2026 | khth/khthbvns2026 | bs.nhi/nhibvns2026" -ForegroundColor Green

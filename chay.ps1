# Chay server (dung hang ngay). Bam chuot phai > Run with PowerShell, hoac chay: .\chay.ps1
$SP = 'C:\Users\duong\AppData\Local\Temp\claude\D--PM-BAN-GIAO--HOAN-ph-n-m-m\c4bcaad3-ad4a-400d-b320-db54de048a2a\scratchpad'
$HT = 'D:\my code\quan_ly_bv\htdocs'
$env:QLBV_DSN = "sqlite:$SP\demo.sqlite"

if (-not (Test-Path "$SP\demo.sqlite")) {
    Write-Host "Chua co CSDL. Chay reset.ps1 truoc." -ForegroundColor Yellow
    exit 1
}

# Tat server cu neu con (giai phong cong 8080)
Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Milliseconds 500

Write-Host "Server: http://127.0.0.1:8080  (Ctrl+C de dung)" -ForegroundColor Green
php -d extension=php_pdo_sqlite -S 127.0.0.1:8080 -t $HT

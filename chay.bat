@echo off
chcp 65001 >nul
title QLBV - Server 8080 (DE CUA SO NAY MO thi web moi chay)
cd /d "%~dp0"

if not exist "%~dp0_local\demo.sqlite" (
  echo Chua co CSDL. Hay chay reset.ps1 truoc roi mo lai file nay.
  pause
  exit /b 1
)

set "QLBV_DSN=sqlite:%~dp0_local\demo.sqlite"
echo Server: http://127.0.0.1:8080
echo DE CUA SO NAY MO thi web moi chay. Dong cua so nay = tat server.
echo.
php -d extension=php_pdo_sqlite -S 127.0.0.1:8080 -t "%~dp0htdocs"
pause

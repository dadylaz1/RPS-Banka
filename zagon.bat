@echo off
setlocal

set "APP_DIR=%~dp0RPS 19.10 banka spletna stram"
if not defined BENKOBANKA_DB_HOST set "BENKOBANKA_DB_HOST=127.0.0.1"
if not defined BENKOBANKA_DB_NAME set "BENKOBANKA_DB_NAME=benkobanka"
if not defined BENKOBANKA_DB_USER set "BENKOBANKA_DB_USER=root"
if not defined BENKOBANKA_DB_PASSWORD set "BENKOBANKA_DB_PASSWORD="

where php >nul 2>nul
if not errorlevel 1 (
    php -d extension=php_pdo_mysql.dll -S 127.0.0.1:8000 -t "%APP_DIR%"
    exit /b %errorlevel%
)

if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -d extension=php_pdo_mysql.dll -S 127.0.0.1:8000 -t "%APP_DIR%"
    exit /b %errorlevel%
)

echo PHP ni najden. Namestite PHP ali XAMPP in poskusite znova.
pause
exit /b 1

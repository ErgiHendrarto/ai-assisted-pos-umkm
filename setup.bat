@echo off
setlocal enabledelayedexpansion

echo ==================================================
echo  AI-Assisted POS - Local Windows Setup
echo ==================================================
echo.

where php >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP is not found in PATH. Please install PHP 8.3+ ^(e.g. XAMPP^) and add it to PATH.
    exit /b 1
)

where composer >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Composer is not found in PATH. Please install Composer from https://getcomposer.org/
    exit /b 1
)

where npm >nul 2>&1
if errorlevel 1 (
    echo [ERROR] npm is not found in PATH. Please install Node.js 20+ from https://nodejs.org/
    exit /b 1
)

echo [1/5] Installing PHP dependencies via Composer...
call composer install
if errorlevel 1 (
    echo [ERROR] composer install failed.
    exit /b 1
)

echo.
if not exist ".env" (
    echo [2/5] Creating .env from .env.example...
    copy ".env.example" ".env" >nul
) else (
    echo [2/5] .env already exists - keeping your existing configuration.
)

echo.
findstr /B /C:"APP_KEY=base64:" ".env" >nul 2>&1
if errorlevel 1 (
    echo [3/5] Generating Laravel application key...
    call php artisan key:generate
) else (
    echo [3/5] APP_KEY is already set - skipping key:generate.
)

echo.
echo [4/5] Installing Node.js dependencies and building frontend assets...
call npm install
if errorlevel 1 (
    echo [ERROR] npm install failed.
    exit /b 1
)
call npm run build
if errorlevel 1 (
    echo [ERROR] npm run build failed.
    exit /b 1
)

echo.
if not exist "public\storage" (
    echo [5/5] Creating storage symlink...
    call php artisan storage:link
) else (
    echo [5/5] Storage symlink already exists.
)

echo.
echo ==================================================
echo  Setup dependencies and assets complete!
echo ==================================================
echo.
echo Next steps:
echo   1. Start MySQL ^(e.g. XAMPP Control Panel^) and create database: ai_pos
echo      ^(Or set DB_CONNECTION=sqlite in .env and create database\database.sqlite^)
echo   2. Run migrations and seed demo data:
echo        php artisan migrate
echo        php artisan db:seed
echo   3. Start the server:
echo        php artisan serve
echo   4. Open http://127.0.0.1:8000 and sign in with:
echo        Admin:   admin@pos.test   / password
echo        Cashier: cashier1@pos.test / password
echo.
endlocal

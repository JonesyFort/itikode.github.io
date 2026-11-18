@echo off
title ITiKODE server (phone / network mode)
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (
  echo PHP was not found. Install it first: see README.md, Part 2.
  pause
  exit /b 1
)
if not exist "data\itikode.db" php setup.php
echo.
echo Find this computer's IPv4 address below, then open  http://THAT-ADDRESS:8000  on your phone
echo (same Wi-Fi). Only use this on a network you trust.
echo.
ipconfig | findstr /i "IPv4"
echo.
echo Keep this window open. Press Ctrl+C to stop.
echo.
php -S 0.0.0.0:8000 router.php
pause

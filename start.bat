@echo off
title ITiKODE server
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (
  echo PHP was not found. Install it first: see README.md, Part 2.
  echo.
  pause
  exit /b 1
)
if not exist "data\itikode.db" (
  echo Creating the database...
  php setup.php
)
echo.
echo ITiKODE is running at  http://localhost:8000
echo Keep this window open. Press Ctrl+C to stop.
echo.
start "" "http://localhost:8000"
php -S localhost:8000 router.php
pause

@echo off
title Install Welfare Korat Scheduler
color 0B

echo.
echo ============================================================
echo  Install Laravel Schedule Task to Windows Task Scheduler
echo ============================================================
echo.

REM Check Administrator
net session >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Must run as Administrator!
    echo.
    echo How: Right-click this file ^> "Run as administrator"
    echo.
    pause
    exit /b 1
)

set "TASK_NAME=LaravelWelfareKoratSchedule"
set "BAT_PATH=C:\xampp\GitHub\welfare-korat2026\schedule-runner.bat"

echo [1/3] Removing old task if exists...
schtasks /delete /tn "%TASK_NAME%" /f >nul 2>&1

echo [2/3] Creating scheduled task (runs every 1 minute)...
schtasks /create ^
    /tn "%TASK_NAME%" ^
    /tr "\"%BAT_PATH%\"" ^
    /sc minute /mo 1 ^
    /ru "%USERNAME%" ^
    /rl HIGHEST ^
    /f

if errorlevel 1 (
    color 0C
    echo.
    echo [ERROR] Failed to create task
    pause
    exit /b 1
)

echo [3/3] Verifying task...
echo.
color 0A
echo ============================================================
echo  SUCCESS! Task "%TASK_NAME%" installed
echo ============================================================
echo.
schtasks /query /tn "%TASK_NAME%" /fo LIST | findstr /C:"TaskName" /C:"Status" /C:"Next Run"
echo.
echo ============================================================
echo  Task will run every 1 minute
echo  Laravel will check and run any scheduled jobs that are due
echo.
echo  Scheduled jobs:
echo    - 3-Day Report:      every 3 days at 08:00
echo    - Daily snapshot:    every day at 16:30
echo    - Weekly bottleneck: every Monday at 06:00
echo    - Notify stuck:      every day at 08:00
echo.
echo  Log file:
echo    C:\xampp\GitHub\welfare-korat2026\storage\logs\schedule.log
echo.
echo  To remove this task later:
echo    schtasks /delete /tn "%TASK_NAME%" /f
echo ============================================================
echo.
pause

@echo off
REM ────────────────────────────────────────────────────────────
REM  Laravel Schedule Runner สำหรับ Windows Task Scheduler
REM  ใช้เรียก artisan schedule:run ทุก 1 นาที
REM
REM  Task Scheduler จะ trigger ไฟล์นี้ทุก 1 นาที → Laravel จะตรวจว่า
REM  มี scheduled task ใดที่ครบรอบเวลา → run task นั้น ๆ
REM ────────────────────────────────────────────────────────────

cd /d "C:\xampp\GitHub\welfare-korat2026"
"C:\xampp\php8.3\php.exe" artisan schedule:run >> "storage\logs\schedule.log" 2>&1

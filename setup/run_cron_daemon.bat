@echo off
cd /d %~dp0
title MINVEN PRO - Cron Daemon Auto Reset Stok
echo Starting Cron Daemon...
echo.
php cron_daemon.php
pause
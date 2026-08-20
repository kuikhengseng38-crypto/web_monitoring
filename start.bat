@echo off
cd /d "%~dp0"
echo Starting website monitor...
echo Open: http://localhost:8000/
echo Auto-check runs in the background. No extra window needed.
echo Keep this window open for the website.
echo.
php -r "require 'includes/daemon.php'; monitor_daemon_ensure();"
php -S localhost:8000

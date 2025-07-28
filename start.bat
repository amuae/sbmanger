@echo off
echo Starting SB Manager...
echo Make sure nginx and PHP are installed and configured properly

REM Start PHP built-in server (optional, for testing)
REM php -S localhost:8000

echo.
echo To use with nginx:
echo 1. Copy nginx.conf to your nginx config directory
echo 2. Restart nginx
echo 3. Make sure PHP-FPM is running on port 9000
echo 4. Open http://localhost in your browser
echo.
echo Default login: admin / admin123
echo.
pause

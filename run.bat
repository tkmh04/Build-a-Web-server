@echo off
echo.
echo ============================================
echo   WEB SERVER DEMO - RUN WITH SQLite
echo ============================================
echo.
echo Step 1: Start PHP Server
echo.
echo Running on: http://localhost:8000
echo.
cd /d "%~dp0"
php -S localhost:8000
echo.
echo Demo Credentials:
echo - User: user, Pass: pass
echo - User: admin, Pass: admin123
echo - User: test, Pass: test123
echo.
pause

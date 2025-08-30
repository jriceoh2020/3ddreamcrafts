@echo off
REM Deployment script for 3DDreamCrafts database (Windows)
REM Run this script on the target server with SQLite3 installed

set DB_FILE=craftsite.db
set SCRIPT_DIR=%~dp0

echo 3DDreamCrafts Database Deployment
echo =================================

REM Check if SQLite3 is available
sqlite3 -version >nul 2>&1
if errorlevel 1 (
    echo Error: sqlite3 command not found. Please install SQLite3.
    pause
    exit /b 1
)

REM Remove existing database if it exists
if exist "%SCRIPT_DIR%%DB_FILE%" (
    echo Removing existing database...
    del "%SCRIPT_DIR%%DB_FILE%"
)

echo Creating new database...

REM Create database and run schema
sqlite3 "%SCRIPT_DIR%%DB_FILE%" < "%SCRIPT_DIR%schema.sql"
if errorlevel 1 (
    echo Error creating database schema!
    pause
    exit /b 1
)

echo Database schema created successfully!

REM Insert sample data
sqlite3 "%SCRIPT_DIR%%DB_FILE%" < "%SCRIPT_DIR%sample_data.sql"
if errorlevel 1 (
    echo Error inserting sample data!
    pause
    exit /b 1
)

echo Sample data inserted successfully!
echo.
echo Database deployment complete!
echo Database file: %SCRIPT_DIR%%DB_FILE%
echo.
echo Default admin credentials:
echo Username: admin
echo Password: admin123
echo.
echo IMPORTANT: Change the default password after first login!
pause
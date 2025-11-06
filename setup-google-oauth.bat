@echo off
echo === PHP Gift Registry - Google OAuth Setup ===
echo.

REM Check if composer is installed
where composer >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo Error: Composer is not installed. Please install Composer first.
    echo Visit: https://getcomposer.org/download/
    pause
    exit /b 1
)

REM Install Google Client Library
echo Installing Google Client Library...
cd /d "%~dp0src"
composer install

if %ERRORLEVEL% NEQ 0 (
    echo Error: Failed to install Google Client Library
    pause
    exit /b 1
)

echo.
echo ✅ Google Client Library installed successfully!
echo.

REM Check if .env file exists
if not exist "src\.env" (
    echo Creating .env file from template...
    copy "src\.env.example" "src\.env"
    echo ✅ .env file created
) else (
    echo ℹ️  .env file already exists
)

echo.
echo === Setup Instructions ===
echo.
echo 1. Go to the Google Cloud Console: https://console.cloud.google.com/
echo 2. Create a new project or select an existing one
echo 3. Enable the Google+ API
echo 4. Create OAuth 2.0 credentials:
echo    - Application type: Web application
echo    - Authorized redirect URIs: http://your-domain.com/oauth/google/callback.php
echo.
echo 5. Update your .env file with the following settings:
echo    GOOGLE_OAUTH_ENABLED=1
echo    GOOGLE_CLIENT_ID=your_client_id_here
echo    GOOGLE_CLIENT_SECRET=your_client_secret_here
echo    GOOGLE_REDIRECT_URI=http://your-domain.com/oauth/google/callback.php
echo.
echo 6. Run the database migration to add Google OAuth support:
echo    mysql -u your_user -p your_database ^< sql/google-oauth-migration.sql
echo.
echo 🎉 Setup complete! Google OAuth is ready to use.
echo.
pause
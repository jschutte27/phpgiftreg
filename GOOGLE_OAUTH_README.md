# Google OAuth Integration for PHP Gift Registry

This document describes how to set up and use Google OAuth authentication in the PHP Gift Registry application.

## Features

- **Single Sign-On**: Users can log in using their Google accounts
- **Account Linking**: Existing users can link their Google accounts
- **Auto-Registration**: New users can be automatically created (configurable)
- **Security**: OAuth users don't need to manage passwords locally

## Setup Instructions

### 1. Install Dependencies

Run the setup script to install the required Google Client Library:

**Windows:**
```bash
setup-google-oauth.bat
```

**Linux/Mac:**
```bash
chmod +x setup-google-oauth.sh
./setup-google-oauth.sh
```

Or manually with Composer:
```bash
composer install
```

### 2. Google Cloud Console Setup

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the **Google+ API** (or People API)
4. Go to "Credentials" and click "Create Credentials" → "OAuth 2.0 Client IDs"
5. Choose "Web application" as the application type
6. Add your authorized redirect URI:
   ```
   http://your-domain.com/oauth/google/callback.php
   ```
7. Copy the Client ID and Client Secret

### 3. Environment Configuration

Update your `.env` file with the Google OAuth settings:

```bash
# Google OAuth Settings
GOOGLE_OAUTH_ENABLED=1
GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
GOOGLE_REDIRECT_URI=http://your-domain.com/oauth/google/callback.php
```

### 4. Database Migration

Run the database migration to add Google OAuth support:

```sql
-- For existing installations
mysql -u your_user -p your_database < sql/2.1.0-to-2.2.0.sql
```

For new installations, the OAuth fields are already included in the main database schema.

## Configuration Options

| Setting | Description | Default |
|---------|-------------|---------|
| `GOOGLE_OAUTH_ENABLED` | Enable/disable Google OAuth | `0` (disabled) |
| `GOOGLE_CLIENT_ID` | Your Google OAuth Client ID | (empty) |
| `GOOGLE_CLIENT_SECRET` | Your Google OAuth Client Secret | (empty) |
| `GOOGLE_REDIRECT_URI` | OAuth callback URL | (empty) |
| `NEWUSER_REQUIRES_APPROVAL` | Whether new OAuth users need approval | `1` (requires approval) |

## How It Works

### Login Flow

1. User clicks "Sign in with Google" on the login page
2. User is redirected to Google's authentication page
3. After successful authentication, Google redirects to `/oauth/google/callback.php`
4. The callback handler:
   - Verifies the OAuth token
   - Retrieves user information from Google
   - Finds or creates a local user account
   - Creates a session and logs the user in

### Account Linking

- If a user with the same email already exists, the Google account is automatically linked
- Existing users can continue using password login or switch to Google OAuth

## Security Features

- **Secure Sessions**: OAuth users get the same secure session handling as regular users
- **No Password Storage**: OAuth users don't have passwords stored locally
- **Email Verification**: Google accounts are pre-verified
- **Session Regeneration**: Session IDs are regenerated on login for security

## User Experience

### For OAuth Users

- Password change section is hidden in profile (Google manages passwords)
- Can still update profile information (name, preferences, etc.)
- Login status shows they're using Google authentication

### For Regular Users

- Can still use username/password login
- Can link a Google account later (if email matches)
- Full password management features remain available

## Troubleshooting

### Common Issues

1. **"Google OAuth library not installed"**
   - Run `composer install` in the project root
   - Check that `vendor/autoload.php` exists

2. **"Google OAuth is not enabled"**
   - Set `GOOGLE_OAUTH_ENABLED=1` in your `.env` file
   - Verify all Google OAuth settings are configured

3. **"Invalid redirect URI"**
   - Ensure the redirect URI in Google Console matches your `.env` setting
   - Check for HTTP vs HTTPS mismatches

4. **"Account creation disabled"**
   - Set `NEWUSER_REQUIRES_APPROVAL=0` to allow auto-registration
   - Or manually approve users in the admin panel

### Debug Mode

To enable detailed OAuth logging, check your web server error logs. The application logs detailed information about the OAuth flow.

## File Structure

```
sql/
├── create-phpgiftregdb.sql        # Main database schema
├── google-oauth-migration.sql     # Google OAuth migration
└── [other migration files]        # Version upgrade scripts
```

## API Dependencies

- **Google Client Library**: `google/apiclient` v2.15+
- **PHP**: 7.4+
- **MySQL**: 5.7+ (for JSON field support)

## License

This Google OAuth integration follows the same license as the main PHP Gift Registry application (GNU GPL v2).
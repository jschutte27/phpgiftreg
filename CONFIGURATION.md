# Configuration Guide

This document explains all the environment variables that can be used to configure the PHP Gift Registry application.

## Database Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_CONNECTION_STRING` | `mysql:host=localhost;dbname=giftreg` | PDO connection string for database |
| `DB_USERNAME` | `giftreg` | Database username |
| `DB_PASSWORD` | `changeme_default_password` | Database password |
| `TABLE_PREFIX` | _(empty)_ | Prefix for all database tables |

## Application Behavior

| Variable | Default | Description |
|----------|---------|-------------|
| `EVENT_THRESHOLD` | `60` | Days before event to trigger notifications |
| `SHOP_REQUIRES_APPROVAL` | `1` | Require approval for shopping requests (0=auto-approve, 1=require approval) |
| `NEWUSER_REQUIRES_APPROVAL` | `1` | Require admin approval for new users (0=auto-approve, 1=require approval) |
| `ANONYMOUS_PURCHASING` | `0` | Hide purchaser identity (0=show names, 1=anonymous) |
| `ITEMS_PER_PAGE` | `10` | Number of items displayed per page |
| `SHOW_OWN_EVENTS` | `1` | Show user's own events on home page (0=hide, 1=show) |

## Email Settings

| Variable | Default | Description |
|----------|---------|-------------|
| `EMAIL_FROM` | `webmaster@{SERVER_NAME}` | Email From header |
| `EMAIL_REPLY_TO` | `noreply@{SERVER_NAME}` | Email Reply-To header |
| `EMAIL_XMAILER` | `PHP/{version}` | Email X-Mailer header |

## User Interface

| Variable | Default | Description |
|----------|---------|-------------|
| `SHOW_HELPTEXT` | `0` | Show help text throughout the application (0=hide, 1=show) |
| `CONFIRM_ITEM_DELETES` | `0` | Require JavaScript confirmation for item deletion (0=no, 1=yes) |
| `ALLOW_MULTIPLES` | `1` | Allow multiple quantities of items (0=single only, 1=allow multiples) |
| `CURRENCY_SYMBOL` | `$` | Symbol to display before prices |
| `DATE_FORMAT` | `m/d/Y` | Date format for display (PHP date format) |
| `HIDE_ZERO_PRICE` | `1` | Hide prices when they are $0.00 (0=show, 1=hide) |

## Security Settings

| Variable | Default | Description |
|----------|---------|-------------|
| `PASSWORD_LENGTH` | `8` | Length of generated passwords |
| `PASSWORD_HASHER` | `BCRYPT` | Password hashing method (BCRYPT, SHA1, MD5, or empty for plain text) |
| `SESSION_TIMEOUT` | `3600` | Session timeout in seconds (3600 = 1 hour, 0 = no timeout) |

## File Upload Settings

| Variable | Default | Description |
|----------|---------|-------------|
| `ALLOW_IMAGES` | `1` | Allow image uploads (0=disabled, 1=enabled) |
| `IMAGE_SUBDIR` | `item_images` | Subdirectory for storing uploaded images |

## Notification Settings

| Variable | Default | Description |
|----------|---------|-------------|
| `NOTIFY_THRESHOLD_MINUTES` | `60` | Minutes between subscription notifications |

## Currency Symbol Options

You can use any of these values for `CURRENCY_SYMBOL`:

- `$` - US Dollar (default)
- `&#163;` - British Pound (£)
- `&#165;` - Japanese Yen (¥)
- `&#8364;` - Euro (€)
- `&euro;` - Euro (alternative)
- Any other currency symbol or text

## Date Format Options

The `DATE_FORMAT` variable uses PHP's date format. Common formats:

- `m/d/Y` - 12/25/2023 (US format, default)
- `d/m/Y` - 25/12/2023 (European format)
- `Y-m-d` - 2023-12-25 (ISO format)
- `F j, Y` - December 25, 2023 (Long format)

## Password Hashing Options

| Value | Description | Security Level |
|-------|-------------|----------------|
| `BCRYPT` | Recommended modern hashing | **High** (Recommended) |
| `SHA1` | Legacy SHA1 hashing | Medium |
| `MD5` | Legacy MD5 hashing | **Low** (Not recommended) |
| _(empty)_ | Plain text storage | **None** (Never use in production) |

**Important:** Changing the password hasher will require all users to reset their passwords.

## Session Timeout Options

The `SESSION_TIMEOUT` variable controls how long user sessions remain active:

| Value | Description | Use Case |
|-------|-------------|----------|
| `0` | No timeout (sessions never expire) | **Not recommended** for security |
| `900` | 15 minutes | High security environments |
| `1800` | 30 minutes | Moderate security (recommended for admin users) |
| `3600` | 1 hour | Default, balanced security/usability |
| `7200` | 2 hours | Relaxed security for convenience |
| `86400` | 24 hours | Very relaxed (not recommended for production) |

**Note:** Sessions are also automatically regenerated every 5 minutes for additional security.

## Boolean Values

For all boolean configuration options, use:
- `1` for true/enabled/yes
- `0` for false/disabled/no

## Environment File Setup

1. Copy `.env.example` to `.env`
2. Edit `.env` with your specific values
3. Ensure `.env` is in your `.gitignore` file
4. Never commit actual credentials to version control

## Production Recommendations

### Security-First Settings:
```bash
# Security
NEWUSER_REQUIRES_APPROVAL=1
SHOP_REQUIRES_APPROVAL=1
PASSWORD_HASHER=BCRYPT
PASSWORD_LENGTH=12
SESSION_TIMEOUT=1800

# Email (use your actual domain)
EMAIL_FROM=noreply@yourdomain.com
EMAIL_REPLY_TO=support@yourdomain.com

# Database (use strong credentials)
DB_PASSWORD=very_strong_random_password_here
```

### Performance Settings:
```bash
# Reduce server load
ITEMS_PER_PAGE=20
NOTIFY_THRESHOLD_MINUTES=120
SHOW_HELPTEXT=0
```

### User Experience Settings:
```bash
# Better UX
CONFIRM_ITEM_DELETES=1
SHOW_OWN_EVENTS=1
ALLOW_MULTIPLES=1
HIDE_ZERO_PRICE=1
```

## Troubleshooting

### Common Issues:

1. **Email not working**: Check `EMAIL_FROM` and `EMAIL_REPLY_TO` settings
2. **Currency not displaying**: Verify `CURRENCY_SYMBOL` is properly encoded
3. **Database connection failed**: Check `DB_CONNECTION_STRING`, `DB_USERNAME`, and `DB_PASSWORD`
4. **Images not uploading**: Verify `ALLOW_IMAGES=1` and `IMAGE_SUBDIR` exists and is writable

### Validation:

The application will use default values if environment variables are not set, so you can gradually migrate settings to environment variables.

## Migration from Hardcoded Config

If you're upgrading from a version with hardcoded configuration:

1. Review your current `config.php` values
2. Set corresponding environment variables in `.env`
3. Test thoroughly in a development environment
4. Deploy with new environment-based configuration

The application will fall back to sensible defaults for any missing environment variables.
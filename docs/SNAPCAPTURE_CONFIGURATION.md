# SnapCapture API Configuration

This document explains how to configure the SnapCapture API key for the screenshot functionality.

## API Key Configuration Priority

The plugin checks for the API key in the following order:

1. **WordPress Constant** (Recommended for production)
2. **Environment Variable** (Alternative for server configuration)
3. **.env.snapcapture File** (Development fallback)

## Method 1: WordPress Constant (Recommended)

Add the following to your `wp-config.php` file:

```php
define('SNAPCAPTURE_API_KEY', 'your-rapidapi-key-here');
```

### Benefits
- ✅ Standard WordPress configuration pattern
- ✅ Works in all WordPress environments
- ✅ Secure (wp-config.php should not be in web root)
- ✅ Easy to manage with WordPress deployment tools

### Where to Add

Add the constant **before** the line that says:
```php
/* That's all, stop editing! Happy publishing. */
```

### Example

```php
// ... other WordPress configuration ...

define( 'DB_COLLATE', '' );

// SnapCapture API Configuration
define('SNAPCAPTURE_API_KEY', 'abc123xyz456yourrapidapikey789');

/* That's all, stop editing! Happy publishing. */
```

## Method 2: Environment Variable

Set the environment variable on your server:

### Apache (.htaccess or httpd.conf)
```apache
SetEnv SNAPCAPTURE_API_KEY "your-rapidapi-key-here"
```

### Nginx (fastcgi_params or server block)
```nginx
fastcgi_param SNAPCAPTURE_API_KEY "your-rapidapi-key-here";
```

### Docker
```yaml
environment:
  - SNAPCAPTURE_API_KEY=your-rapidapi-key-here
```

### System Environment
```bash
export SNAPCAPTURE_API_KEY="your-rapidapi-key-here"
```

## Method 3: .env.snapcapture File (Development Only)

Create a file named `.env.snapcapture` in the plugin root directory:

```ini
SNAPCAPTURE_API_KEY=your-rapidapi-key-here
```

### ⚠️ Important
- This file is excluded from version control (.gitignore)
- **Do NOT use this method in production**
- Only for local development/testing

## Getting Your API Key

1. Sign up for RapidAPI: https://rapidapi.com/
2. Subscribe to SnapCapture API: https://rapidapi.com/snapcapture1/api/snapcapture1
3. Copy your API key from the RapidAPI dashboard
4. Add it using one of the methods above

## Verifying Configuration

After adding your API key, check the WordPress error log for:

```
TP Link Shortener: Using SNAPCAPTURE_API_KEY from WordPress constant
```

Or check the plugin log:
```bash
tail -f logs/snapcapture.log
```

## Troubleshooting

### "SNAPCAPTURE_API_KEY not configured" Error

This means the plugin couldn't find your API key. Check:

1. ✅ API key is defined in wp-config.php
2. ✅ Constant name is exactly `SNAPCAPTURE_API_KEY` (case-sensitive)
3. ✅ wp-config.php syntax is correct (no typos)
4. ✅ Web server was restarted after adding environment variable

### Screenshot Capture Fails

Check the logs for detailed error messages:

```bash
# Plugin logs
tail -n 100 logs/snapcapture.log

# WordPress error logs
tail -n 100 wp-content/debug.log
```

Common issues:
- Invalid API key → Check your RapidAPI subscription
- Rate limit exceeded → Upgrade your RapidAPI plan
- Network timeout → Check server firewall/outbound connections

## Security Best Practices

1. ✅ **Never commit API keys to version control**
2. ✅ **Use WordPress constants in production**
3. ✅ **Restrict wp-config.php file permissions** (chmod 600)
4. ✅ **Keep wp-config.php outside web root** if possible
5. ✅ **Rotate API keys regularly**
6. ✅ **Monitor API usage** on RapidAPI dashboard

## Example Configuration

### Development (Local)
```php
// wp-config.php
define('SNAPCAPTURE_API_KEY', 'dev-api-key-123');
```

### Staging
```php
// wp-config.php
define('SNAPCAPTURE_API_KEY', 'staging-api-key-456');
```

### Production
```php
// wp-config.php
define('SNAPCAPTURE_API_KEY', 'prod-api-key-789');
```

## Support

If you encounter issues:

1. Check the logs: `logs/snapcapture.log`
2. Enable WordPress debug mode in wp-config.php:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
3. Review error logs in `wp-content/debug.log`
4. Contact support with relevant log excerpts

## Related Documentation

- [SnapCapture Logs](../logs/README.md) - Log file documentation
- [SnapCapture API Documentation](https://rapidapi.com/snapcapture1/api/snapcapture1/details)
- [WordPress wp-config.php](https://wordpress.org/documentation/article/editing-wp-config-php/)

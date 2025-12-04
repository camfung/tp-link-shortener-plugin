# SnapCapture Logs

This directory contains logs for the SnapCapture screenshot functionality.

## Log Files

- **snapcapture.log** - Main log file for screenshot capture operations

## Log Levels

Logs are written with the following levels:
- `DEBUG` - Detailed debugging information
- `INFO` - General informational messages
- `WARNING` - Warning messages
- `ERROR` - Error messages

## Log Format

Each log entry includes:
- Timestamp (Y-m-d H:i:s format)
- Log level
- Message
- Context data (JSON format)

Example:
```
[2025-12-03 10:30:45] [INFO] Capturing screenshot {"url":"https://example.com","format":"jpeg","returnJson":true}
[2025-12-03 10:30:46] [DEBUG] Received response {"http_code":200,"body_length":12345,"headers":["content-type","x-cache-hit"]}
[2025-12-03 10:30:46] [INFO] Screenshot captured successfully {"cached":false,"response_time_ms":1234,"content_type":"image/jpeg"}
```

## Viewing Logs

### View last 50 lines:
```bash
tail -n 50 logs/snapcapture.log
```

### View in real-time:
```bash
tail -f logs/snapcapture.log
```

### Search for errors:
```bash
grep ERROR logs/snapcapture.log
```

### Search by URL:
```bash
grep "https://example.com" logs/snapcapture.log
```

## Log Rotation

The log file is not automatically rotated. To prevent it from growing too large:

1. Manually clear the log:
```bash
> logs/snapcapture.log
```

2. Or archive and rotate:
```bash
mv logs/snapcapture.log logs/snapcapture.log.$(date +%Y%m%d)
touch logs/snapcapture.log
chmod 644 logs/snapcapture.log
```

## Troubleshooting

### Log file not being created
- Check that the `logs/` directory exists and is writable
- Check that the plugin directory is writable by the web server
- Check WordPress error logs for permission errors

### No logs appearing
- Verify that logging is enabled in the Logger initialization
- Check that the log level is set appropriately (DEBUG shows all logs)
- Ensure the SnapCapture client is being initialized with the logger

### Permission issues
```bash
chmod 755 logs/
chmod 644 logs/snapcapture.log
```

## Privacy & Security

- Log files may contain URLs being screenshotted
- Log files are excluded from version control (.gitignore)
- Ensure log files are not publicly accessible via web server
- Consider implementing log rotation to manage file size

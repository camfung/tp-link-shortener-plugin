# Logging System

## Overview

The plugin logs API activity (link creation, updates, fingerprint searches, etc.) to rotating log files split into **10-minute windows**. Logs can be read directly from the server filesystem or via a protected REST API endpoint.

## Log Files

### Location

Log files are written to:

```
wp-content/plugins/tp-debug-YYYY-MM-DD-HHMM.log
```

### Naming Convention

Each file covers a 10-minute window. The `HHMM` portion is always rounded down to the nearest 10-minute mark.

| Time of log entry | File written to                    | Window covers   |
|--------------------|------------------------------------|-----------------|
| 14:32:15           | `tp-debug-2026-02-08-1430.log`     | 14:30 - 14:39   |
| 14:40:00           | `tp-debug-2026-02-08-1440.log`     | 14:40 - 14:49   |
| 09:05:33           | `tp-debug-2026-02-08-0900.log`     | 09:00 - 09:09   |
| 23:59:59           | `tp-debug-2026-02-08-2350.log`     | 23:50 - 23:59   |

### Log Entry Format

Each line is timestamped:

```
[2026-02-08 14:32:15] === CREATE LINK REQUEST START ===
[2026-02-08 14:32:15] Request received: {"destination":"https://example.com",...}
[2026-02-08 14:32:15] API CLIENT: Sending POST request to API...
```

Lines from `TP_API_Handler` have no prefix. Lines from `TrafficPortalApiClient` are prefixed with `API CLIENT:`.

### Cleanup

Log files are not automatically deleted. You can safely remove old files from the server:

```bash
# Delete log files older than 7 days
find wp-content/plugins/ -name "tp-debug-*.log" -mtime +7 -delete
```

## REST API

### Endpoint

```
GET /wp-json/tp-link-shortener/v1/logs
```

### Authentication

Requires an `x-api-key` header matching the `TP_LOGS_API_KEY` constant defined in `wp-config.php`.

#### Setup

Add to `wp-config.php`:

```php
define('TP_LOGS_API_KEY', 'your-secret-key-here');
```

If `TP_LOGS_API_KEY` is not defined, the endpoint will always return `401`.

### Query Parameters

| Parameter | Type | Default | Description                                          |
|-----------|------|---------|------------------------------------------------------|
| `windows` | int  | `1`     | Number of 10-minute windows to return (1-144). `1` = current window only, `3` = current + previous 2 windows (30 min of logs). Max 144 = 24 hours. |

### Example Requests

```bash
# Get current window's logs
curl -H "x-api-key: your-secret-key-here" \
  "https://yoursite.com/wp-json/tp-link-shortener/v1/logs"

# Get last 30 minutes of logs (3 windows)
curl -H "x-api-key: your-secret-key-here" \
  "https://yoursite.com/wp-json/tp-link-shortener/v1/logs?windows=3"

# Get last hour of logs
curl -H "x-api-key: your-secret-key-here" \
  "https://yoursite.com/wp-json/tp-link-shortener/v1/logs?windows=6"
```

### Response Format

```json
{
  "windows": [
    {
      "window": "2026-02-08-1430",
      "entries": [
        "[2026-02-08 14:32:15] === CREATE LINK REQUEST START ===",
        "[2026-02-08 14:32:15] Request received: {...}",
        "[2026-02-08 14:32:15] SUCCESS - Link created successfully"
      ]
    },
    {
      "window": "2026-02-08-1420",
      "entries": []
    }
  ],
  "total_entries": 3,
  "current_window": "2026-02-08-1430"
}
```

| Field            | Description                                            |
|------------------|--------------------------------------------------------|
| `windows`        | Array of window objects, most recent first             |
| `windows[].window`  | Window label (`YYYY-MM-DD-HHMM`)                  |
| `windows[].entries` | Array of log line strings (empty array if no file) |
| `total_entries`  | Total number of log lines across all returned windows  |
| `current_window` | The label of the currently active window               |

### Error Responses

| Status | Cause                                    |
|--------|------------------------------------------|
| `401`  | Missing or invalid `x-api-key` header    |
| `400`  | Invalid `windows` parameter (not 1-144)  |

## Architecture

The logging is implemented across these files:

- **`includes/class-tp-logs-api.php`** - `tp_get_log_file_path()` helper function and `TP_Logs_API` REST class
- **`includes/class-tp-api-handler.php`** - `log_to_file()` method (plugin-level API logging)
- **`includes/TrafficPortal/TrafficPortalApiClient.php`** - `log_to_file()` method (HTTP client logging)

Both `log_to_file()` methods call `tp_get_log_file_path()` to determine which file to write to.

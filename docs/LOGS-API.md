# Logs REST API

REST endpoint for reading plugin log files remotely.

## Endpoint

```
GET /wp-json/tp-link-shortener/v1/logs
```

## Authentication

Requires the `LOGS_API_KEY` constant to be defined in `wp-config.php`:

```php
define('LOGS_API_KEY', 'your-logs-api-key');
```

Pass it via the `X-API-Key` header:

```
X-API-Key: your-logs-api-key
```

This is separate from the main `API_KEY` used by the TrafficPortal client. Requests without a valid key receive a `403 Forbidden` response.

## Parameters

| Parameter | Type   | Default | Description |
|-----------|--------|---------|-------------|
| `log`     | string | `debug` | Log file to read: `debug`, `snapcapture`, or `wp` |
| `mode`    | string | `tail`  | `head` reads from the start, `tail` reads from the end |
| `n`       | int    | `50`    | Number of lines to return (1–5000) |

### Log files

| Value         | File                                         |
|---------------|----------------------------------------------|
| `debug`       | `wp-content/plugins/tp-update-debug.log`     |
| `snapcapture` | `tp-link-shortener-plugin/logs/snapcapture.log` |
| `wp`          | `wp-content/debug.log`                       |

## Examples

**Last 100 lines of the plugin debug log:**
```bash
curl -H "X-API-Key: YOUR_KEY" \
  "https://yoursite.com/wp-json/tp-link-shortener/v1/logs?log=debug&mode=tail&n=100"
```

**First 20 lines of the WordPress debug log:**
```bash
curl -H "X-API-Key: YOUR_KEY" \
  "https://yoursite.com/wp-json/tp-link-shortener/v1/logs?log=wp&mode=head&n=20"
```

**Last 50 lines of the SnapCapture log (defaults):**
```bash
curl -H "X-API-Key: YOUR_KEY" \
  "https://yoursite.com/wp-json/tp-link-shortener/v1/logs?log=snapcapture"
```

## Response

```json
{
  "log": "debug",
  "file": "tp-update-debug.log",
  "mode": "tail",
  "n": 100,
  "total": 842,
  "lines": [
    "[2026-02-15 14:32:01] === CREATE LINK REQUEST START ===",
    "[2026-02-15 14:32:01] Request received: {\"destination\":\"https://example.com\"}"
  ]
}
```

| Field   | Type     | Description |
|---------|----------|-------------|
| `log`   | string   | Log name requested |
| `file`  | string   | Basename of the log file |
| `mode`  | string   | `head` or `tail` |
| `n`     | int      | Number of lines requested |
| `total` | int      | Total lines in the file |
| `lines` | string[] | The returned log lines |

## Error Responses

| Status | Condition |
|--------|-----------|
| 403    | Missing or invalid API key |
| 400    | Invalid log name |
| 404    | Log file does not exist |
| 500    | Could not read the log file |

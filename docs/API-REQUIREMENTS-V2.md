# API Requirements: Usage Dashboard v2.1+

This document is a **requirements handoff** for the backend team. It specifies the API changes needed so the frontend can replace mock data with real data in future versions.

The frontend usage dashboard (v2.0) is fully built and working. It currently uses mock data for the clicks vs QR scans breakdown (a 70/30 split applied client-side via `splitHits()`). This document specifies what the API needs to return so that mock can be removed.

---

## 1. Current State (Baseline)

### Primary Endpoint

```
GET /user-activity-summary/{uid}?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
```

**Auth:** `x-api-key` header

**Current response shape:**

```json
{
  "message": "Activity summary for period",
  "success": true,
  "source": [
    {
      "date": "2026-02-20",
      "totalHits": 150,
      "hitCost": 0.15,
      "balance": 9.85
    }
  ]
}
```

**Fields per daily record:**

| Field | Type | Description |
|-------|------|-------------|
| `date` | string | ISO date `YYYY-MM-DD` |
| `totalHits` | int | Total redirects that day |
| `hitCost` | float | Total cost charged that day |
| `balance` | float | Running account balance at end of day |

### Additional Endpoints (Exist but Undocumented)

| Endpoint | Notes |
|----------|-------|
| `GET /user-activity-summary/{uid}/by-link?start_date=X&end_date=Y` | Per-link breakdown. Response shape not fully documented. |
| `GET /user-activity-summary/{uid}/by-source?start_date=X&end_date=Y` | Per-source breakdown. May already contain click vs QR data. |

### WordPress Proxy

The frontend never calls the API directly. All requests go through:

```
POST /wp-admin/admin-ajax.php?action=tp_get_usage_summary
```

The WordPress AJAX handler (`ajax_get_usage_summary` in `class-tp-api-handler.php`) calls `TrafficPortalApiClient::getUserActivitySummary()`, validates/reshapes the response, and returns it to the browser.

---

## 2. Requirement 1: Real Clicks vs QR Scans Breakdown

**Priority:** HIGH -- this is the primary v2.1 requirement.

### Problem

The API returns `totalHits` but does not break it down into clicks (direct URL visits) vs QR scans. The frontend currently applies a mock 70/30 split client-side via `splitHits()`.

### Required Changes

Add `clicks` (int) and `qrScans` (int) fields to each daily record in the `source` array.

**Constraint:** `clicks + qrScans === totalHits` for every record.

### Proposed Response Shape

```json
{
  "message": "Activity summary for period",
  "success": true,
  "source": [
    {
      "date": "2026-02-20",
      "totalHits": 150,
      "clicks": 105,
      "qrScans": 45,
      "hitCost": 0.15,
      "balance": 9.85
    }
  ]
}
```

### Suggested Approach

Explore the existing `/user-activity-summary/{uid}/by-source` endpoint. It may already contain source-level breakdown data that can be aggregated into clicks vs QR scans. If so, the summary endpoint could derive the split from the same underlying data.

If the `by-source` endpoint does not contain this data, the hit-tracking pipeline would need to record the source type (click vs QR) at ingestion time.

---

## 3. Requirement 2: Other Services Data

**Priority:** LOW -- deferred past v2.0.

### Problem

The dashboard was designed with a future "Other Services" column for non-redirect charges (domain renewals, wallet top-ups, premium features). No API endpoint currently returns this data.

### Required Shape

Per-charge records:

```json
{
  "date": "2026-02-15",
  "description": "Domain renewal - example.com",
  "amount": 12.99
}
```

### Delivery Options

- **Option A:** New key in the existing summary response: `"otherServices": [...]`
- **Option B:** New endpoint: `GET /user-other-services/{uid}?start_date=X&end_date=Y`

Either approach works for the frontend. Option A is simpler if the data set is small.

### Examples of "Other Services"

- Domain renewals
- Wallet top-up fees
- Premium feature charges
- Account maintenance fees

---

## 4. Requirement 3: Wallet Transaction History

**Priority:** LOW -- deferred past v2.0.

### Problem

The current API returns a daily `balance` (running balance at end of day) but no individual transaction events. For a future wallet page or expanded dashboard, the frontend needs discrete transaction records.

### Required Shape

```json
{
  "date": "2026-02-10",
  "type": "credit",
  "amount": 50.00,
  "description": "Wallet top-up via Stripe",
  "balance_after": 59.85
}
```

**`type` values:** `"credit"` (money in) or `"debit"` (money out).

### Suggested Endpoint

```
GET /wallet-transactions/{uid}?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
```

Auth: same `x-api-key` header pattern as the existing endpoints.

---

## 5. Frontend Integration Notes

When new fields are added to the API, the following files need corresponding updates on the WordPress side.

### Server-Side (PHP)

| File | What to Update |
|------|---------------|
| `includes/TrafficPortal/TrafficPortalApiClient.php` | `getUserActivitySummary()` method -- no changes needed unless the endpoint URL changes. |
| `includes/class-tp-api-handler.php` | `validate_usage_summary_response()` method -- must pass through new fields (`clicks`, `qrScans`) in the `$days[]` array. Currently only whitelists `date`, `totalHits`, `hitCost`, `balance`. |

**Example change in `validate_usage_summary_response()`:**

```php
$days[] = [
    'date'      => sanitize_text_field($record['date']),
    'totalHits' => (int) $record['totalHits'],
    'clicks'    => (int) ($record['clicks'] ?? 0),
    'qrScans'   => (int) ($record['qrScans'] ?? 0),
    'hitCost'   => (float) ($record['hitCost'] ?? 0),
    'balance'   => (float) ($record['balance'] ?? 0),
];
```

### Client-Side (JS)

| File | What to Update |
|------|---------------|
| `assets/js/usage-dashboard.js` | Remove `splitHits()` mock function. Read `clicks` and `qrScans` directly from the API response data instead. |

---

## 6. Migration Path

### Phase 1: v2.0 (Current)

- Frontend uses mock 70/30 split via `splitHits()`.
- API returns `totalHits` only -- no breakdown.
- Other Services and Wallet Transactions not displayed.

### Phase 2: v2.1

- API adds `clicks` and `qrScans` to daily summary records.
- Frontend removes `splitHits()` and reads real data.
- `validate_usage_summary_response()` updated to pass new fields.

### Phase 3: v2.2+

- Other Services endpoint/fields added.
- Wallet Transaction endpoint added.
- Frontend enables deferred UI sections.

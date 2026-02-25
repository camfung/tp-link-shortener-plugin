# API Change Request: Add `destination` and `clicks` Sort Fields

**Related Ticket:** TP-105 — Sorting by column returns an error on Client Links table
**Reported:** Feb 15, 2026
**Priority:** High — users see errors when clicking sortable column headers

## Summary

The `GET /items/user/{uid}` endpoint currently only supports sorting by `updated_at`, `created_at`, and `tpKey`. The Client Links UI exposes `destination` and `clicks` as sortable columns, but attempts to sort by these fields return a 400 error.

## Current Behavior

**API allowed sort fields:** `updated_at`, `created_at`, `tpKey`

**Client Links UI sortable columns:**
- `tpKey` — works
- `destination` — returns 400 error
- `clicks` — returns 400 error
- `created_at` — works

**Error returned:**
```
Invalid sort. Use one of: updated_at, tpKey with asc/desc.
```

## Requested Change

Add two sort fields to `GET /items/user/{uid}`:

| Field | Type | Sort Behavior |
|-------|------|---------------|
| `destination` | string | Alphabetical sort on the destination URL |
| `clicks` | integer | Numeric sort on usage total (qr + regular) |

### Expected API Behavior

```bash
# Sort by destination ascending
GET /items/user/{uid}?page=1&page_size=50&sort=destination:asc

# Sort by clicks descending (most clicked first)
GET /items/user/{uid}?page=1&page_size=50&sort=clicks:desc
```

Response format should remain unchanged — same `source` array structure with `usage` object when `include_usage=true`.

## Context

- Serge asked (Feb 15): "are we calling API every time? Why not keep data in memory?" — answer: yes, every sort triggers an API call since data is paginated server-side. Client-side sorting would only sort the current page, not the full dataset.
- The WordPress plugin's API handler (`class-tp-api-handler.php`) already validates and allows `destination` and `clicks` as sort fields.
- The `TrafficPortalApiClient.php` client-side validation blocks these fields before the request reaches the API.

## Plugin Changes Required After API Update

Once the API supports these fields, update one line in `TrafficPortalApiClient.php:661`:

```php
// Current:
$allowedSortFields = ['updated_at', 'created_at', 'tpKey'];

// After API update:
$allowedSortFields = ['updated_at', 'created_at', 'tpKey', 'destination', 'clicks'];
```

## Verification

Failing tests are already in place at:
`tests/Unit/TrafficPortal/SortFieldConsistencyTest.php`

Three tests will pass once the client validation is updated:
- `testSortByDestinationDoesNotThrowValidationException`
- `testSortByClicksDoesNotThrowValidationException`
- `testAllHandlerSortFieldsAcceptedByApiClient`

## Database Considerations

- `destination` sort maps to the `destination` column in `tp_map` — should be straightforward index-based sort
- `clicks` sort requires sorting by the aggregated usage total — may need a subquery or join on `usage_records` with count, depending on implementation

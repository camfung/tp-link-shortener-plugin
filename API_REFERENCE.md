# TP API Reference

Complete API documentation for the Bloomland Traffic Portal API.

**Last Updated:** January 20, 2026
**Version:** 1.6.0

---

## What's New

### January 20, 2026 - v1.6.0

**New Endpoints:**
- ✅ `DELETE /items/by-fingerprint/{fingerprint}/disable` - Disable anonymous link to allow creating a new one

### January 14, 2026 - v1.5.0

**New Documentation:**
- ✅ `POST /items` - Create Map Record (with `expires_at` support)
- ✅ `GET /items/by-fingerprint/{fingerprint}` - Search by fingerprint with QR/regular usage stats

**Features:**
- ✅ `expires_at` field on create - Set link expiry at creation time
- ✅ Usage statistics on fingerprint search - Returns `total`, `qr`, and `regular` click counts

### December 30, 2025 - v1.4.0

**Updates:**
- ✅ `PUT /items/{mid}` - `tpKey` is now updatable (change short URL keys)
- ✅ `PUT /items/{mid}` - `uid` now accepts negative values
- ✅ Added Map Record Management API documentation

### November 3, 2025 - v1.3.0 🚀 **Traffic Source Tracking**

**New Endpoints:**
- ✨ `GET /user-activity-summary/{uid}/by-source` - Get usage breakdown by traffic source (QR codes, YouTube, etc.)

**Major Updates:**
- ✅ **All APIs now use `usage_records` as primary data source** (migration from `tp_log` complete)
- ✅ Traffic source tracking implemented (QR codes, social media, campaigns, etc.)
- ✅ Enhanced `usage_records` with full analytics data (IP, location, headers, response)
- ✅ Query parameter tracking via `?qr=1`, `?youtube=campaign_id`, etc.
- ✅ NULL-safe handling for legacy data without new fields

**Database Schema:**
- 📊 New table: `traffic_sources` - Define trackable sources
- 📊 New table: `usage_record_sources` - Link usage to sources (many-to-many)
- 📊 Enhanced: `usage_records` now includes mid, request_uri, request_ip, location, request_header (JSON), response (JSON)

**Migration Status:**
- Phase 1: Dual-write implementation ✓ DEPLOYED
- Phase 2: Migration to usage_records ✓ **COMPLETE**
- Phase 3: Traffic source tracking ✓ **DEPLOYED**

### October 20, 2025 - v1.2.0

**New Endpoints:**
- ✨ `GET /user-activity-summary/{uid}/by-link` - Get usage aggregated per link/keyword per day

**Updates:**
- ✅ Fixed `usage_records` INSERT bug
- ✅ Implemented dual-write to `tp_log` AND `usage_records` for all redirects
- ✅ Updated by-link endpoint to show per-day breakdown (not just totals)

See [`MIGRATION_GUIDE.md`](MIGRATION_GUIDE.md) for full migration details.

---

## Base URL

**Development:** `https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev`

---

## Quick Links

**Map Records (Short URLs):**
- [Create Map Record](#create-map-record) - Create short URL with optional expiry
- [Search by Fingerprint](#search-by-fingerprint) - Find anonymous user links with usage stats
- [Disable Anonymous Link](#disable-anonymous-link) - Delete anonymous link to create a new one
- [Update Map Record](#update-map-record) - Update existing short URL

**User Activity:**
- [User Activity Summary](#user-activity-summary-api) - Daily aggregated data
- [User Activity By Link](#user-activity-by-link-api) - Usage per link/keyword
- [User Activity By Source](#user-activity-by-source-api) - Usage per traffic source

**Product Management:**
- [Products API](#products-api) - Manage products and pricing
- [User Products API](#user-products-api) - Assign products to users

**Billing:**
- [Usage Records API](#usage-records-api) - Billable events
- [Payment Records API](#payment-records-api) - Payment transactions

**Traffic Sources:**
- [Traffic Source Tracking](#traffic-source-tracking) - How to track QR codes, social media, etc.

**Short Code Generation:**
- [Generate Short Code](#generate-short-code) - AI/NLP-powered short code suggestions

**Wallet:**
- [Wallet API](#wallet-api) - TeraWallet balance and transactions

**Debug Tools:**
- [Schema Inspector API](#schema-inspector-api) - Query database schema
- [Testing](#testing) - Test scripts and examples
- [Monitoring](#monitoring) - CloudWatch logs

---

## User Activity Summary API

Retrieves daily aggregated activity data including hit counts, costs, and running balance.

### Endpoint

```
GET /user-activity-summary/{uid}
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uid` | integer | Yes | User ID to retrieve activity for |

### Query Parameters

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| `start_date` | string | No | Filter from this date (YYYY-MM-DD) | `2025-07-01` |
| `end_date` | string | No | Filter to this date (YYYY-MM-DD) | `2025-07-31` |

### Response Format

```json
{
  "message": "Activity summary retrieved",
  "success": true,
  "source": [
    {
      "date": "2025-07-30",
      "totalHits": 5,
      "hitCost": -0.5,
      "balance": -0.5
    }
  ]
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `date` | string | Date in YYYY-MM-DD format |
| `totalHits` | integer | Number of redirects/hits for this date |
| `hitCost` | float | Cost of hits (negative value) |
| `balance` | float | Running balance (cumulative sum of costs and payments) |

### Example Usage

```bash
# Basic request
curl "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/user-activity-summary/125"

# With date range
curl "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/user-activity-summary/125?start_date=2025-08-01&end_date=2025-08-31"
```

```javascript
// JavaScript/React example
const response = await fetch(
  `https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/user-activity-summary/${userId}`
);
const data = await response.json();
if (data.success) {
  console.log('Activity records:', data.source);
}
```

---

## User Activity By Link API

Get usage data aggregated per link/keyword **per day** for a specific user. Shows daily breakdown of which links were used and their costs.

### Endpoint

```
GET /user-activity-summary/{uid}/by-link
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uid` | integer | Yes | User ID to retrieve link usage for |

### Query Parameters

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| `start_date` | string | No | Filter from this date (YYYY-MM-DD) | `2025-07-01` |
| `end_date` | string | No | Filter to this date (YYYY-MM-DD) | `2025-07-31` |

### Response Format

```json
{
  "message": "Usage by link per day retrieved",
  "success": true,
  "source": [
    {
      "date": "2025-08-19",
      "mid": 20,
      "keyword": null,
      "destination": null,
      "hits": 8791,
      "cost": -87.91
    },
    {
      "date": "2025-08-19",
      "mid": 14143,
      "keyword": "PERF_bab870b3-59f9-42b1-964a-a66cdad26ca8",
      "destination": "https://httpbin.org/status/200",
      "hits": 4,
      "cost": -0.04
    },
    {
      "date": "2025-08-11",
      "mid": 20,
      "keyword": null,
      "destination": null,
      "hits": 8,
      "cost": -0.08
    }
  ]
}
```

**Notes:**
- Results are sorted by `date` DESC (most recent first), then by `hits` DESC (most popular first)
- Each record represents usage for a specific link on a specific day
- Same `mid` can appear multiple times (once per day it was used)
- Links with `null` keyword/destination were deleted but still have hit history
- Costs are calculated using user's active product pricing or $0.10/hit default

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `date` | string | Date in YYYY-MM-DD format |
| `mid` | integer | Map ID (link identifier) |
| `keyword` | string \| null | Short URL keyword/key (null if link deleted from tp_map) |
| `destination` | string \| null | Destination URL (null if link deleted from tp_map) |
| `hits` | integer | Number of clicks for this link on this date |
| `cost` | float | Cost for this link on this date (negative value) |

**Note:** `keyword` and `destination` may be `null` if the link (mid) no longer exists in the `tp_map` table. Hit counts and costs are still accurate.

### Example Usage

```bash
# Get all link usage for user 125
curl "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/user-activity-summary/125/by-link"

# Get link usage for specific date range
curl "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/user-activity-summary/125/by-link?start_date=2025-08-01&end_date=2025-08-31"
```

```javascript
// JavaScript/React example - Get usage per link per day
const response = await fetch(
  `https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/user-activity-summary/${userId}/by-link`
);
const data = await response.json();
if (data.success) {
  console.log('Usage by link per day:', data.source);

  // Group by date
  const byDate = {};
  data.source.forEach(record => {
    if (!byDate[record.date]) byDate[record.date] = [];
    byDate[record.date].push(record);
  });

  // Display
  Object.keys(byDate).forEach(date => {
    console.log(`\n${date}:`);
    byDate[date].forEach(link => {
      console.log(`  ${link.keyword || `mid:${link.mid}`}: ${link.hits} hits, $${Math.abs(link.cost).toFixed(2)}`);
    });
  });
}
```

**Use Cases:**
- Show which links are most popular
- Display usage breakdown in a table or chart
- Calculate cost per link for billing
- Identify high-traffic links for optimization
- Show keyword usage breakdown in dashboard

**Real-World Example:**

```javascript
// Fetch link usage per day and aggregate by link
const response = await fetch(
  `https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/user-activity-summary/125/by-link?start_date=2025-08-01&end_date=2025-08-31`
);
const data = await response.json();

// Aggregate totals per link (across all days)
const linkTotals = {};
data.source.forEach(record => {
  const key = record.mid;
  if (!linkTotals[key]) {
    linkTotals[key] = {
      mid: record.mid,
      keyword: record.keyword,
      destination: record.destination,
      totalHits: 0,
      totalCost: 0,
      days: []
    };
  }
  linkTotals[key].totalHits += record.hits;
  linkTotals[key].totalCost += record.cost;
  linkTotals[key].days.push({ date: record.date, hits: record.hits });
});

// Get top 5 links by hits
const topLinks = Object.values(linkTotals)
  .filter(link => link.keyword !== null)
  .sort((a, b) => b.totalHits - a.totalHits)
  .slice(0, 5);

// Display
topLinks.forEach((link, index) => {
  console.log(`${index + 1}. ${link.keyword}: ${link.totalHits} clicks → ${link.destination}`);
  console.log(`   Cost: $${Math.abs(link.totalCost).toFixed(2)}`);
  console.log(`   Active on ${link.days.length} days`);
});
```

**Output:**
```
1. PERF_bab870b3-59f9-42b1-964a-a66cdad26ca8: 4 clicks → https://httpbin.org/status/200
   Cost: $0.04
   Active on 1 days
2. PERF_3_1755604230: 2 clicks → https://example.com/test-803
   Cost: $0.02
   Active on 1 days
```

---

## User Activity By Source API

Get usage data aggregated per traffic source **per day** for a specific user. Shows daily breakdown of which traffic sources (QR codes, YouTube, social media, etc.) were used.

### Endpoint

```
GET /user-activity-summary/{uid}/by-source
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `uid` | integer | Yes | User ID to retrieve source usage for |

### Query Parameters

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| `start_date` | string | No | Filter from this date (YYYY-MM-DD) | `2025-07-01` |
| `end_date` | string | No | Filter to this date (YYYY-MM-DD) | `2025-07-31` |

### Response Format

```json
{
  "message": "Usage by traffic source per day retrieved",
  "success": true,
  "source": [
    {
      "date": "2025-11-03",
      "source_id": 1,
      "source_name": "QR Code",
      "query_param_key": "qr",
      "hits": 150,
      "cost": -1.50
    },
    {
      "date": "2025-11-03",
      "source_id": 2,
      "source_name": "YouTube",
      "query_param_key": "youtube",
      "hits": 45,
      "cost": -0.45
    },
    {
      "date": "2025-11-02",
      "source_id": 1,
      "source_name": "QR Code",
      "query_param_key": "qr",
      "hits": 200,
      "cost": -2.00
    }
  ]
}
```

**Notes:**
- Results are sorted by `date` DESC (most recent first), then by `hits` DESC (most popular first)
- Each record represents usage for a specific traffic source on a specific day
- Same source can appear multiple times (once per day it was used)
- Only shows sources that were actually tracked (via query parameters)
- Records only appear if links were accessed with `?qr=1`, `?youtube=campaign_id`, etc.

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `date` | string | Date in YYYY-MM-DD format |
| `source_id` | integer | Traffic source ID |
| `source_name` | string | Display name (e.g., "QR Code", "YouTube") |
| `query_param_key` | string | Query parameter key (e.g., "qr", "youtube") |
| `hits` | integer | Number of clicks from this source on this date |
| `cost` | float | Cost for this source on this date (negative value) |

### Example Usage

```bash
# Get all traffic source usage for user 125
curl "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/user-activity-summary/125/by-source"

# Get traffic source usage for specific date range
curl "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/user-activity-summary/125/by-source?start_date=2025-11-01&end_date=2025-11-30"
```

```javascript
// JavaScript/React example - Get usage per source per day
const response = await fetch(
  `https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/user-activity-summary/${userId}/by-source`
);
const data = await response.json();
if (data.success) {
  console.log('Usage by source per day:', data.source);

  // Group by source
  const bySource = {};
  data.source.forEach(record => {
    const key = record.source_name;
    if (!bySource[key]) {
      bySource[key] = { totalHits: 0, totalCost: 0, days: [] };
    }
    bySource[key].totalHits += record.hits;
    bySource[key].totalCost += record.cost;
    bySource[key].days.push({ date: record.date, hits: record.hits });
  });

  // Display summary
  Object.keys(bySource).forEach(source => {
    console.log(`${source}: ${bySource[source].totalHits} total hits, $${Math.abs(bySource[source].totalCost).toFixed(2)}`);
  });
}
```

**Use Cases:**
- Track which QR codes are most effective
- Measure social media campaign performance
- Compare traffic sources (QR vs YouTube vs Email)
- Identify best-performing marketing channels
- Calculate ROI per traffic source

**How Traffic Sources Work:**

Traffic sources are tracked via query parameters on your short links:

```bash
# QR code campaign
https://yourlink.com/promo?qr=1

# YouTube campaign
https://yourlink.com/promo?youtube=summer_campaign_2025

# Multiple sources
https://yourlink.com/promo?qr=1&campaign=newsletter_oct
```

When users click these links, the system automatically:
1. Detects the query parameters (`qr`, `youtube`, etc.)
2. Links them to defined traffic sources in the `traffic_sources` table
3. Records the association in `usage_record_sources`
4. Makes data available via this API

See [Traffic Source Tracking](#traffic-source-tracking) section for setup details.

---

## Products API

Manage product definitions and pricing.

### List Products

```
GET /products
```

Returns all products in the database.

### Get Product

```
GET /products/{pid}
```

Returns details for a specific product.

### Create Product

```
POST /products
```

**Body:**
```json
{
  "name": "Link Clicks",
  "description": "Pay per link click/redirect",
  "type": "usage-based",
  "price": 0.10,
  "active": true
}
```

**Product Types:**
- `usage-based`: Pay per use (per click)
- `subscription`: Monthly/yearly recurring fee
- `one-time`: One-time purchase
- `tiered`: Volume-based pricing

### Update Product

```
PUT /products/{pid}
```

**Body:**
```json
{
  "price": 0.15,
  "active": true
}
```

---

## User Products API

Manage product assignments to users.

### List User's Products

```
GET /user-products/user/{uid}
```

Returns all products assigned to a user.

### Assign Product to User

```
POST /user-products
```

**Body:**
```json
{
  "uid": 125,
  "pid": 5,
  "status": "active"
}
```

**Status values:** `active`, `inactive`, `expired`, `cancelled`

### Update User Product

```
PUT /user-products/{upid}
```

**Body:**
```json
{
  "status": "inactive"
}
```

---

## Usage Records API

Manual API for creating/reading billable usage events.

### Create Usage Record

```
POST /usage-records
```

**Body:**
```json
{
  "upid": 3,
  "datetime": "2025-10-19 10:00:00",
  "event": "redirect:mid=123"
}
```

### Get Usage Records

```
GET /usage-records/{urid}
```

Returns usage records for a specific record ID.

---

## Payment Records API

Track payment transactions.

### Create Payment Record

```
POST /payment-records
```

**Body:**
```json
{
  "upid": 3,
  "amount": 100.00,
  "datetime": "2025-10-19 10:00:00"
}
```

### List Payment Records

```
GET /payment-records/user/{uid}
```

Returns all payment records for a user.

---

## Traffic Source Tracking

Track where your traffic comes from using query parameters (QR codes, social media, email campaigns, etc.).

### Overview

The Traffic Source Tracking system allows you to automatically track which marketing channels drive traffic to your links. By adding simple query parameters to your short URLs, you can measure the effectiveness of different campaigns.

### How It Works

**1. Add query parameters to your short links:**

```bash
# QR code on a poster
https://yourlink.com/promo?qr=poster_downtown

# YouTube video description
https://yourlink.com/promo?youtube=product_launch_2025

# Email newsletter
https://yourlink.com/promo?email=newsletter_november

# Multiple sources (track both QR and campaign)
https://yourlink.com/promo?qr=1&campaign=black_friday
```

**2. System automatically tracks the source:**

When someone clicks the link, the system:
- Detects the query parameters (`qr`, `youtube`, `email`, etc.)
- Looks up the corresponding traffic source in the `traffic_sources` table
- Creates a record in `usage_record_sources` linking the click to the source
- Makes data available via the [By Source API](#user-activity-by-source-api)

**3. View reports:**

```bash
# Get breakdown by traffic source
curl "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/user-activity-summary/125/by-source"
```

### Available Traffic Sources

**System-Defined Sources** (currently available):

| Source Name | Query Parameter | Example Usage |
|-------------|-----------------|---------------|
| QR Code | `qr` | `?qr=1` or `?qr=poster_123` |

**Future Sources** (can be added to `traffic_sources` table):

| Source Name | Query Parameter | Example Usage |
|-------------|-----------------|---------------|
| YouTube | `youtube` | `?youtube=campaign_id` |
| Instagram | `ig` | `?ig=story_link` |
| Facebook | `fb` | `?fb=ad_campaign` |
| Email | `email` | `?email=newsletter_oct` |
| SMS | `sms` | `?sms=promo_text` |
| Campaign | `campaign` | `?campaign=summer_2025` |

### Database Schema

**traffic_sources** - Defines available sources:
```sql
CREATE TABLE traffic_sources (
    source_id INT PRIMARY KEY,
    source_name VARCHAR(100),      -- "QR Code", "YouTube", etc.
    query_param_key VARCHAR(50),   -- "qr", "youtube", etc.
    uid INT NULL,                  -- NULL = system, INT = user-defined
    active BOOLEAN DEFAULT 1
);
```

**usage_record_sources** - Links clicks to sources:
```sql
CREATE TABLE usage_record_sources (
    id BIGINT PRIMARY KEY,
    urid BIGINT,                   -- FK to usage_records.urid
    source_id INT,                 -- FK to traffic_sources.source_id
    param_value VARCHAR(255)       -- Actual value: "1", "campaign_abc", etc.
);
```

### Adding New Traffic Sources

To add new traffic sources, insert into the `traffic_sources` table:

```sql
-- Add YouTube tracking
INSERT INTO traffic_sources (source_name, query_param_key, uid, active)
VALUES ('YouTube', 'youtube', NULL, 1);

-- Add Instagram tracking
INSERT INTO traffic_sources (source_name, query_param_key, uid, active)
VALUES ('Instagram', 'ig', NULL, 1);

-- Add custom campaign tracking for user 125
INSERT INTO traffic_sources (source_name, query_param_key, uid, active)
VALUES ('My Campaign', 'mycampaign', 125, 1);
```

### Query Examples

**Get QR code performance:**
```sql
SELECT
    DATE(ur.datetime) as date,
    COUNT(DISTINCT ur.urid) as qr_scans,
    COUNT(DISTINCT ur.urid) * 0.01 as cost
FROM usage_records ur
JOIN usage_record_sources urs ON ur.urid = urs.urid
JOIN traffic_sources ts ON urs.source_id = ts.source_id
WHERE ts.query_param_key = 'qr'
  AND ur.upid IN (SELECT upid FROM user_products WHERE uid = 125)
GROUP BY DATE(ur.datetime)
ORDER BY date DESC;
```

**Compare all traffic sources:**
```sql
SELECT
    ts.source_name,
    COUNT(DISTINCT ur.urid) as total_clicks,
    COUNT(DISTINCT ur.urid) * 0.01 as total_cost
FROM usage_records ur
JOIN usage_record_sources urs ON ur.urid = urs.urid
JOIN traffic_sources ts ON urs.source_id = ts.source_id
JOIN user_products up ON ur.upid = up.upid
WHERE up.uid = 125
GROUP BY ts.source_id, ts.source_name
ORDER BY total_clicks DESC;
```

### Use Cases

**Marketing Campaign Tracking:**
- Track which QR codes on posters/flyers get the most scans
- Measure effectiveness of YouTube vs Instagram campaigns
- Compare email newsletter vs SMS campaigns
- Calculate ROI per marketing channel

**A/B Testing:**
```bash
# QR code version A (red poster)
https://yourlink.com/promo?qr=version_a

# QR code version B (blue poster)
https://yourlink.com/promo?qr=version_b
```

**Multi-Channel Attribution:**
```bash
# User sees YouTube ad, then clicks email link
https://yourlink.com/promo?youtube=1&email=followup
```

### Best Practices

1. **Use descriptive parameter values:**
   - ✅ `?qr=poster_downtown_nov2025`
   - ❌ `?qr=1`

2. **Be consistent with naming:**
   - ✅ `?campaign=black_friday_2025`
   - ❌ `?campaign=BF25` (unclear)

3. **Track multiple sources when relevant:**
   - `?qr=1&campaign=holiday_sale` (QR code in holiday campaign)

4. **Use lowercase parameters:**
   - ✅ `?qr=1` (matches database)
   - ❌ `?QR=1` (won't match)

### NULL Handling

- Old usage records (before Nov 3, 2025) won't have traffic source data
- APIs handle NULL values gracefully
- By-source endpoint only shows records with sources (empty array if none)
- Legacy data without sources doesn't appear in source reports

---

## Map Record Management API

Manage link mappings (tp_map records).

### Create Map Record

```
POST /items
```

Create a new short URL/link mapping.

**Request Body (base64 encoded JSON):**

```json
{
  "uid": 123,
  "tpKey": "mylink",
  "domain": "example.com",
  "destination": "https://example.com/landing",
  "type": "url",
  "is_set": 0,
  "status": "active",
  "tags": "promo,sale",
  "notes": "My link",
  "settings": "{}",
  "expires_at": "2025-12-31 23:59:59",
  "fingerprint": "a1b2c3d4e5f6..."
}
```

**Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `uid` | integer | Yes | User ID (-1 for anonymous users) |
| `tpKey` | string | Yes | Short URL key (alphanumeric, max 255 chars) |
| `domain` | string | Yes | Domain for the short link |
| `destination` | string | Yes | Target URL (http/https/ftp) |
| `type` | string | Yes | Record type (e.g., "url") |
| `is_set` | integer | Yes | 0 or 1 |
| `status` | string | Yes | Record status |
| `tags` | string | Yes | Comma-separated tags |
| `notes` | string | Yes | Notes/description |
| `settings` | string | Yes | JSON settings string |
| `expires_at` | string/null | No | Expiry datetime (ISO format). Default: NULL for registered users, 24 hours for anonymous |
| `fingerprint` | string | No* | Browser fingerprint (32-64 hex chars). *Required for anonymous users (uid=-1) |

**Response:**

```json
{
  "message": "Record Created",
  "success": true,
  "source": {
    "mid": 14205,
    "uid": 123,
    "tpKey": "mylink",
    "domain": "example.com",
    "destination": "https://example.com/landing",
    "expires_at": "2025-12-31 23:59:59",
    ...
  }
}
```

**Notes:**
- Anonymous users (`uid=-1`) can only create 1 link per fingerprint (returns 429 if exceeded)
- Anonymous links default to 24-hour expiry unless specified
- See [Anonymous User Fingerprint Limit](ANONYMOUS_USER_FINGERPRINT_LIMIT.md) for details

---

### Search by Fingerprint

```
GET /items/by-fingerprint/{fingerprint}
```

Search for links created by a specific browser fingerprint. Returns usage statistics for each link.

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `fingerprint` | string | Yes | Browser fingerprint (32-64 hex chars) |

**Response:**

```json
{
  "message": "Found 1 records for fingerprint a1b2c3...",
  "success": true,
  "source": {
    "fingerprint": "a1b2c3d4e5f6...",
    "count": 1,
    "records": [
      {
        "mid": 14205,
        "tpKey": "mylink",
        "domain": "dev.trfc.link",
        "destination": "https://example.com",
        "status": "active",
        "expires_at": "2025-12-31 23:59:59",
        "created_by_fingerprint": "a1b2c3d4e5f6...",
        "updated_at": "2025-01-10 12:00:00",
        "usage": {
          "total": 150,
          "qr": 45,
          "regular": 105
        }
      }
    ]
  }
}
```

**Usage Statistics:**

| Field | Type | Description |
|-------|------|-------------|
| `total` | integer | Total clicks/visits to this link |
| `qr` | integer | Visits from QR code scans (detected via `?qr=` query parameter) |
| `regular` | integer | Direct/regular visits (total - qr) |

---

### Disable Anonymous Link

```
DELETE /items/by-fingerprint/{fingerprint}/disable
```

Disable (delete) an anonymous user's link by fingerprint. This allows the user to create a new link.

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `fingerprint` | string | Yes | Browser fingerprint (32-64 hex chars) |

**Success Response (200):**

```json
{
  "message": "Anonymous link disabled successfully. You can now create a new link.",
  "success": true,
  "source": {
    "mid": 14205,
    "tpKey": "mylink",
    "domain": "dev.trfc.link"
  }
}
```

**Error Responses:**

| Status | Message |
|--------|---------|
| 400 | Invalid fingerprint format. Must be 32-64 hexadecimal characters. |
| 404 | No anonymous link found for this fingerprint. |

---

### Update Map Record

```
PUT /items/{mid}
```

Update an existing link mapping record.

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `mid` | integer | Yes | Map record ID to update |

**Request Body (base64 encoded JSON):**

```json
{
  "uid": 123,
  "tpKey": "newkey",
  "domain": "example.com",
  "destination": "https://example.com/landing",
  "status": "active",
  "is_set": 0,
  "tags": "promo,sale",
  "notes": "Updated link",
  "settings": "{}",
  "expires_at": "2025-12-31 23:59:59"
}
```

**Updatable Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `uid` | integer | Yes | User ID (negative values allowed) |
| `tpKey` | string | No | Short URL key (alphanumeric only, max 255 chars) - only updated if provided |
| `domain` | string | Yes | Domain for the short link |
| `destination` | string | Yes | Target URL |
| `status` | string | Yes | Record status |
| `is_set` | integer | Yes | 0 or 1 |
| `tags` | string | Yes | Comma-separated tags |
| `notes` | string | Yes | Notes/description |
| `settings` | string | Yes | JSON settings string |
| `expires_at` | string/null | No | Expiry datetime (ISO format) or null to remove |

**Response:**

```json
{
  "message": "Record Updated",
  "success": true,
  "source": {
    "uid": 123,
    "tpKey": "newkey",
    "domain": "example.com",
    "destination": "https://example.com/landing",
    "status": "active",
    "is_set": 0,
    "tags": "promo,sale",
    "notes": "Updated link",
    "settings": "{}"
  }
}
```

**Example - Change the short URL key:**

```bash
# Create the JSON payload
PAYLOAD='{"uid":123,"tpKey":"newkey","domain":"example.com","destination":"https://example.com/landing","status":"active","is_set":0,"tags":"","notes":"","settings":""}'

# Base64 encode and send
curl -X PUT "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/items/456" \
  -H "Content-Type: application/json" \
  -d "$(echo -n "$PAYLOAD" | base64)"
```

**Validation Rules:**

- `tpKey`: Alphanumeric only (`^[a-zA-Z0-9]+$`), max 255 characters
- `uid`: Any integer (positive or negative)
- `destination`: Valid URL (http/https/ftp)
- `domain`: Valid domain format or empty string
- `is_set`: Must be 0 or 1

**Notes:**
- Changes are logged to `tp_history` table for audit trail
- If `tpKey` is not provided, the existing key is preserved
- If `expires_at` is not provided, the existing value is preserved
- Set `expires_at` to `null` to remove expiry

---

## Schema Inspector API

Debug tool for querying database schema and sample data.

### Describe Table Schema

```
GET /schema-inspector/describe/{table_name}
```

Returns column definitions for a table.

**Example:**
```bash
curl "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/schema-inspector/describe/tp_log"
```

### Get Sample Data

```
GET /schema-inspector/sample/{table_name}?limit={n}
```

Returns sample records from a table.

**Example:**
```bash
curl "https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev/schema-inspector/sample/tp_log?limit=5"
```

**Supported Tables:**
- `tp_log`, `tp_user`, `tp_map`, `tp_set`
- `payment_records`, `usage_records`, `user_products`, `products`

---

## Error Responses

All endpoints return errors in this format:

```json
{
  "message": "Error description",
  "success": false,
  "source": null
}
```

**Common HTTP Status Codes:**
- `200`: Success
- `400`: Bad request (missing required fields)
- `404`: Resource not found
- `502`: Server error (database connection, query failure)

---

## Rate Limiting

- **Timeout**: 15 seconds per request (Lambda default)
- **No explicit rate limiting**: API Gateway standard limits apply
- **Recommended**: Cache results on frontend for 5-10 minutes

---

## Monitoring

### CloudWatch Logs

**View logs for specific functions:**
```bash
# User Activity Summary
aws logs tail /aws/lambda/dev-GetUserActivitySummaryFunction --follow

# User Activity By Link
aws logs tail /aws/lambda/dev-GetUserActivityByLinkFunction --follow

# User Activity By Source (NEW)
aws logs tail /aws/lambda/dev-GetUserActivityBySourceFunction --follow

# Redirect functions (check usage tracking)
aws logs tail /aws/lambda/dev-RedirectToURLFunction --follow
```

**Common log patterns:**

*User Activity Endpoints:*
- `Activity summary retrieved` - Success
- `Usage by link per day retrieved` - Success
- `Usage by traffic source per day retrieved` - Success (new)
- `Using product pricing: {name} at ${price}/hit` - Product pricing used
- `No usage-based product found` - Using default pricing
- `Error Message:` - Error occurred

*Redirect Functions (usage_records writes):*
- `Logged usage record for uid=X, mid=Y, upid=Z, urid=ABC` - Full analytics logged ✓
- `Logged traffic source: qr=1 for urid=ABC` - Traffic source tracked ✓
- `No active usage-based product found for uid=X` - User has no product
- `Exception logging usage record:` - Write failed (redirect still succeeds)
- `Failed to log traffic sources:` - Source tracking failed (non-critical)

---

## Testing

### Test Script

```bash
#!/bin/bash
API_BASE="https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev"

echo "=== Test 1: User Activity Summary ==="
curl -s "$API_BASE/user-activity-summary/125" | python3 -m json.tool
echo ""

echo "=== Test 2: Usage By Link ==="
curl -s "$API_BASE/user-activity-summary/125/by-link" | python3 -m json.tool
echo ""

echo "=== Test 3: Usage By Link (Date Filtered) ==="
curl -s "$API_BASE/user-activity-summary/125/by-link?start_date=2025-08-01&end_date=2025-08-31" | python3 -m json.tool
echo ""

echo "=== Test 4: Usage By Source ==="
curl -s "$API_BASE/user-activity-summary/125/by-source" | python3 -m json.tool
echo ""

echo "=== Test 5: List Products ==="
curl -s "$API_BASE/products" | python3 -m json.tool
echo ""

echo "=== Test 6: Schema Inspector ==="
curl -s "$API_BASE/schema-inspector/describe/usage_records" | python3 -m json.tool
echo ""
```

### Test Users

- **UID 125**: Has activity data (8,833+ records)
- **UID 1**: No activity data (returns empty array)

---

## Implementation Details

### How Activity Summary Works

```
1. Query user's active usage-based product → get price
2. Count daily hits from usage_records table (via upid → user_products → uid)
3. Calculate hit costs = totalHits × price
4. Get payment records from payment_records table
5. Calculate running balance = Σ(payments + costs)
6. Return daily aggregated records ordered by date
```

### How Traffic Source Tracking Works

```
1. User clicks: https://link.com/promo?qr=1&campaign=summer
2. Redirect function extracts query parameters
3. Log to tp_log (legacy audit trail)
4. Log to usage_records with full analytics (IP, headers, response, mid)
5. Query traffic_sources for active sources matching params ('qr', 'campaign')
6. Create records in usage_record_sources linking urid to source_id
7. Data available immediately via by-source API
```

### Database Tables

| Table | Purpose |
|-------|---------|
| `usage_records` | **Primary billing table** - Full analytics (mid, IP, location, headers, response) |
| `usage_record_sources` | Links usage records to traffic sources (many-to-many junction) |
| `traffic_sources` | Defines trackable sources (QR, YouTube, etc.) |
| `user_products` | Links users to products (PK: upid) |
| `products` | Product pricing (type, price, active) |
| `payment_records` | Payment transactions (FK: upid) |
| `tp_log` | **Legacy table** - Still written for audit trail, no longer used for billing |
| `tp_map` | Link mappings (mid → keyword, destination) |

### Cost Calculation

- **Hit Cost** = Number of Hits × Product Price
- If no product found: defaults to $0.10 per hit
- Hit costs are negative (reduce balance)
- Payments are positive (increase balance)

### Balance Calculation

Running total calculated chronologically:
```
Day 1: Balance = Payment1 + HitCost1
Day 2: Balance = Day1Balance + Payment2 + HitCost2
Day 3: Balance = Day2Balance + Payment3 + HitCost3
```

---

## Future Enhancements

**Planned Features:**
- [ ] Add top 2 keywords per day to daily activity summary
- [ ] Reverse balance calculation (backward from today)
- [ ] Add pagination for large result sets
- [ ] Add authentication/authorization
- [ ] Support for tiered pricing
- [ ] CSV/Excel export functionality
- [ ] Caching layer (Redis/ElastiCache)
- [ ] User-defined custom traffic sources
- [ ] Traffic source management API (CRUD operations)
- [ ] Aggregate traffic source reports (totals across all dates)

**Completed:**
- ✅ Dual-write to usage_records (deployed)
- ✅ Migration to usage_records for billing (complete)
- ✅ Traffic source tracking system (deployed)
- ✅ Full analytics in usage_records (IP, location, headers, response)

---

## Generate Short Code

Generate meaningful, memorable short codes from webpage content. Three tiered endpoints with different speed/quality tradeoffs.

| Endpoint | Speed | Method | Best For |
|----------|-------|--------|----------|
| `POST /generate-short-code/fast` | ~500ms | Rule-based | Quick results |
| `POST /generate-short-code/smart` | ~800ms | AWS Comprehend NLP | Balance of speed/quality |
| `POST /generate-short-code/ai` | ~3-5s | Gemini AI | Best quality |

### Request (All Endpoints)

```json
{
  "url": "https://example.com/page",
  "domain": "trfc.link"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `url` | string | Yes | URL to analyze (must start with `http://` or `https://`) |
| `domain` | string | No | Domain to check short code availability against |

### Response

```json
{
  "message": "Short code generated successfully",
  "success": true,
  "source": {
    "short_code": "chocolatecake",
    "method": "rule-based",
    "was_modified": false,
    "candidates": ["chocolatecake", "bestchocolate", "cakerecipe"]
  }
}
```

### How It Works

1. Server fetches HTML from URL
2. Extracts title, meta description, body text
3. Generates short code using selected method (rules/NLP/AI)
4. Checks availability, appends numbers if taken (`code1`, `code2`...)
5. Returns first available code

**Fallback:** If no HTML metadata found, extracts keywords from URL path.

### Error Responses

| Status | Message |
|--------|---------|
| 400 | Missing required field: url |
| 400 | Invalid URL format. Must start with http:// or https:// |
| 400 | Failed to fetch URL: HTTP Error 404 |
| 500 | Could not find available short code |

---

## Wallet API

TeraWallet integration for managing user wallet balances.

> **Note:** `wpUserId` is the WordPress user ID, not the LinkSmarty `uid`.

### Get Balance

```
GET /wallet/{wpUserId}/balance
```

**Response:**
```json
{
  "message": "Balance retrieved",
  "success": true,
  "source": {
    "balance": "6.50",
    "wpUserId": "125"
  }
}
```

### Get Transactions

```
GET /wallet/{wpUserId}/transactions
```

**Response:**
```json
{
  "message": "Transactions retrieved",
  "success": true,
  "source": {
    "transactions": [
      {
        "transaction_id": "6",
        "type": "debit",
        "amount": "3.50000000",
        "balance": "6.50000000",
        "currency": "CAD",
        "details": "Usage charge",
        "date": "2025-12-03 11:51:47"
      }
    ]
  }
}
```

### Credit Wallet

```
POST /wallet/{wpUserId}/credit
```

```json
{
  "amount": 10.00,
  "details": "Deposit from payment"
}
```

### Debit Wallet

```
POST /wallet/{wpUserId}/debit
```

```json
{
  "amount": 3.50,
  "details": "Usage charge for November"
}
```

### Error Responses

| Status | Message |
|--------|---------|
| 400 | Missing WordPress user ID |
| 400 | Amount must be a positive number |
| 502 | Wallet not found for this user |

---

## Deployment Information

**Current Version:** v1.6.0 (January 20, 2026)

**Stack:**
- Name: `dev-linksmarty`
- Region: `ca-central-1`
- API Base: `https://ce7jzbocq1.execute-api.ca-central-1.amazonaws.com/dev`

**Recent Changes:**
- DELETE /items/by-fingerprint/{fingerprint}/disable - disable anonymous link
- Generate Short Code endpoints consolidated into this doc
- POST /items now documented with `expires_at` support
- GET /items/by-fingerprint returns QR/regular usage statistics

**Related Documentation:**
- [Admin Guide](ADMIN_GUIDE.md) - Product setup and troubleshooting
- [Migration Guide](MIGRATION_GUIDE.md) - Complete migration plan and tp_log → usage_records migration details
- [Migration README](../migrations/README.md) - Traffic sources migration instructions

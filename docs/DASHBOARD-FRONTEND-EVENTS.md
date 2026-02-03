# Dashboard-Frontend Event Communication

This document explains how the Dashboard and Frontend JavaScript modules communicate using jQuery custom events.

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Events](#events)
4. [Data Structures](#data-structures)
5. [Implementation Details](#implementation-details)
6. [Usage Flow](#usage-flow)
7. [Troubleshooting](#troubleshooting)

## Overview

The Dashboard (`dashboard.js`) and Frontend (`frontend.js`) modules communicate using jQuery custom events triggered on the `document` object. This allows loose coupling between components that may or may not coexist on the same page.

The primary use case is enabling users to click on a link in the dashboard table to edit it in the shortener form.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│              Dashboard (dashboard.js)                        │
│  • Displays paginated table of user's links                 │
│  • Stores fetched items in state.items array                │
│  • Emits events when user clicks row or edit button         │
└─────────────────────┬───────────────────────────────────────┘
                      │
        ┌─────────────┴─────────────┐
        │                           │
   Row Click              Edit Button Click
   (not on buttons)       (.tp-edit-btn)
        │                           │
        └─────────────┬─────────────┘
                      │
                      ▼
              emitEditItem(mid)
                      │
                      │ $(document).trigger('tp:editItem', [item])
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                    Document Object                           │
│               (jQuery Event Bus)                             │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      │ $(document).on('tp:editItem', handler)
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│              Frontend (frontend.js)                          │
│  • Listens for edit events (logged-in users only)           │
│  • Populates form with received item data                   │
│  • Switches to update mode                                  │
└─────────────────────────────────────────────────────────────┘
```

## Events

### `tp:editItem`

**Purpose**: Requests the frontend form to load an existing link for editing.

**Direction**: Dashboard → Frontend

**Triggers**:
- Clicking anywhere on a table row (except buttons/links)
- Clicking the edit button (pencil icon) in the actions column

**Emitter Location**: `assets/js/dashboard.js` - `emitEditItem()` function

**Listener Location**: `assets/js/frontend.js:489`

**Payload**: A single `item` object containing the link data.

**Example**:
```javascript
// Emitting (dashboard.js)
$(document).trigger('tp:editItem', [item]);

// Listening (frontend.js)
$(document).on('tp:editItem', this.handleDashboardEditItem.bind(this));
```

## Data Structures

### Item Object

The `item` object passed with `tp:editItem` contains the following fields:

| Field | Type | Description |
|-------|------|-------------|
| `mid` | `number` | Unique map item identifier |
| `domain` | `string` | Domain for the short link (e.g., "example.com") |
| `tpKey` | `string` | The short code/key (e.g., "abc123") |
| `destination` | `string` | The full destination URL |
| `notes` | `string` | Optional notes/description for the link |
| `created_at` | `string` | ISO timestamp of creation date |
| `expires_at` | `string\|null` | ISO timestamp of expiry date (if set) |
| `usage` | `object\|null` | Usage statistics object |

### Usage Object

If present, the `usage` object contains:

| Field | Type | Description |
|-------|------|-------------|
| `total` | `number` | Total number of clicks/scans |
| `qr` | `number` | Number of QR code scans |
| `regular` | `number` | Number of direct link clicks |

### Example Item

```javascript
{
    mid: 42,
    domain: "short.link",
    tpKey: "abc123",
    destination: "https://example.com/very-long-url",
    notes: "Marketing campaign Q1",
    created_at: "2025-01-15T10:30:00Z",
    expires_at: null,
    usage: {
        total: 150,
        qr: 45,
        regular: 105
    }
}
```

## Implementation Details

### Dashboard Side (Emitter)

**File**: `assets/js/dashboard.js`

The dashboard stores fetched items in `state.items` for later lookup:

```javascript
// Store items when API response is received
state.items = response.data.source;
```

Two click handlers trigger the edit event:

```javascript
// Edit button clicks (delegated)
$tbody.on('click', '.tp-edit-btn', function(e) {
    e.preventDefault();
    const mid = parseInt($(this).data('mid'));
    emitEditItem(mid);
});

// Row clicks (delegated)
$tbody.on('click', 'tr', function(e) {
    // Ignore clicks on interactive elements (buttons, links)
    const $target = $(e.target);
    if ($target.closest('a, button, .tp-action-btn').length) {
        return;
    }

    const mid = parseInt($(this).data('mid'));
    emitEditItem(mid);
});
```

Both handlers delegate to a shared `emitEditItem` function:

```javascript
/**
 * Emit edit item event for frontend form to consume
 * @param {number} mid - Map item ID
 */
function emitEditItem(mid) {
    const item = state.items.find(function(i) {
        return i.mid === mid;
    });

    if (!item) {
        console.error('Dashboard: Item not found for mid:', mid);
        return;
    }

    // Emit custom event with item data for frontend to consume
    $(document).trigger('tp:editItem', [item]);

    // Scroll to form if it exists on the page
    const $form = $('#tp-shortener-form');
    if ($form.length) {
        $('html, body').animate({
            scrollTop: $form.offset().top - 100
        }, 500);
    }
}
```

### Frontend Side (Listener)

**File**: `assets/js/frontend.js`

The listener is only bound for logged-in users:

```javascript
// Lines 487-491
if (tpAjax.isLoggedIn) {
    $(document).on('tp:editItem', this.handleDashboardEditItem.bind(this));
    TPDebug.log('init', 'Dashboard edit item event listener bound');
}
```

The handler transforms the item and delegates to `displayExistingLink`:

```javascript
// Lines 2129-2155
handleDashboardEditItem: function(event, item) {
    if (!item) {
        TPDebug.error('ui', 'No item data received');
        return;
    }

    // Transform dashboard item to match expected record format
    const record = {
        mid: item.mid,
        domain: item.domain,
        tpKey: item.tpKey,
        destination: item.destination,
        usage: item.usage || { qr: 0, regular: 0 },
        expires_at: item.expires_at || null,
        notes: item.notes || ''
    };

    // Use existing method to populate the form
    this.displayExistingLink(record);
}
```

## Usage Flow

```
1. User views Dashboard
   └─> dashboard.js loads and fetches user's links via AJAX

2. API returns paginated items
   └─> Items stored in state.items array
   └─> Table rendered with data-mid attributes on rows and edit buttons

3. User clicks to edit (either method):
   ├─> Option A: Click anywhere on a table row (not on buttons/links)
   │   └─> Row click handler extracts mid from row's data-mid attribute
   └─> Option B: Click the edit button (pencil icon)
       └─> Edit button handler extracts mid from button's data-mid attribute

4. emitEditItem(mid) called
   └─> Item looked up from state.items by mid
   └─> Event emitted: $(document).trigger('tp:editItem', [item])

5. Dashboard scrolls page to form
   └─> $('html, body').animate({ scrollTop: ... })

6. Frontend receives event
   └─> handleDashboardEditItem(event, item) called

7. Item data transformed
   └─> Record object created with expected format

8. Form populated
   └─> displayExistingLink(record) called
   └─> Destination field filled
   └─> Result section shown with short URL
   └─> QR code generated
   └─> Screenshot captured
   └─> Usage stats displayed
   └─> Form switches to "update" mode
```

## Troubleshooting

### Event Not Being Received

**Symptom**: Clicking dashboard rows doesn't populate the form

**Checks**:

1. Verify user is logged in (listener only binds for logged-in users):
   ```javascript
   console.log(tpAjax.isLoggedIn);  // Should be true
   ```

2. Check if listener is bound:
   ```javascript
   // In browser console
   $._data(document, 'events');  // Look for 'tp:editItem'
   ```

3. Verify event is being emitted (check console for debug logs):
   ```
   Dashboard: Row clicked for mid: 42
   Dashboard: Found item: {...}
   Dashboard: Emitting tp:editItem event
   ```

4. Ensure frontend.js is loaded on the page with the dashboard

### Click Not Triggering Event

**Symptom**: Row or edit button clicks are ignored

**Checks**:

1. For row clicks: Ensure you're not clicking on a button, link, or action element
2. Check that the row has a `data-mid` attribute:
   ```javascript
   $('tr').data('mid');  // Should return a number
   ```

3. Check that edit buttons have `data-mid` attributes:
   ```javascript
   $('.tp-edit-btn').data('mid');  // Should return a number
   ```

4. Verify items are stored in state:
   ```javascript
   // Add console.log in dashboard.js to debug
   console.log('Items in state:', state.items);
   ```

### Form Not Populating Correctly

**Symptom**: Event received but form doesn't update properly

**Checks**:

1. Enable TPDebug for ui logging:
   ```javascript
   localStorage.setItem('tpDebug:ui', 'on');
   ```

2. Check browser console for transformation logs:
   ```
   === DASHBOARD EDIT ITEM EVENT ===
   Item received: {...}
   Transformed record: {...}
   ```

3. Verify `displayExistingLink` method exists and is functioning

### Scroll Not Working

**Symptom**: Page doesn't scroll to form after clicking row

**Checks**:

1. Verify the form element exists:
   ```javascript
   $('#tp-shortener-form').length;  // Should be 1
   ```

2. Check if another script is interfering with scroll behavior

## Adding New Events

To add additional dashboard-to-frontend events:

1. **Define the event name** with `tp:` prefix (e.g., `tp:deleteItem`)

2. **Emit from dashboard.js**:
   ```javascript
   $(document).trigger('tp:deleteItem', [{ mid: itemId }]);
   ```

3. **Listen in frontend.js** (inside `bindEvents`):
   ```javascript
   if (tpAjax.isLoggedIn) {
       $(document).on('tp:deleteItem', this.handleDeleteItem.bind(this));
   }
   ```

4. **Implement handler**:
   ```javascript
   handleDeleteItem: function(event, data) {
       // Handle the event
   }
   ```

## Version History

- **1.0.0** (2025-01-20): Initial implementation with `tp:editItem` event

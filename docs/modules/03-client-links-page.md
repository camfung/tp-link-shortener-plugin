# Module: Client Links Page

> **Audience:** Non-technical, high-level thinker. Big-picture first. This document explains what the Client Links Page is and how it fits into the plugin, without requiring you to read any code.

---

## 1. One-Line Summary

The Client Links Page is the logged-in user's personal control room for browsing, searching, editing, and tracking the performance of every short link they own.

---

## 2. What It Does (Plain English)

Think of this page as the "My Links" dashboard inside the plugin. Once a user has signed in, this is where they go to see all the short links they have created in one organized list. They can scroll through them, search by keyword, filter by whether a link is active or paused, and sort the table by different columns.

Each row shows the short link itself, where it points to, and how many times it has been used. A small bar chart at the top of the page shows how those links have been performing over a chosen date range, so the user can spot at a glance which links are getting attention.

The page is also where day-to-day link housekeeping happens. From this single screen, a user can create a brand new link, edit an existing one, copy a link to the clipboard, generate a printable QR code, view a record of every change ever made to a link, or temporarily disable a link without deleting it.

The end result is a single place where a user can answer three questions: "What links do I have?", "How are they doing?", and "What do I need to change?"

---

## 3. Why It Exists (The Business Reason)

Without this page, a user could create short links but would have no way to see them again, measure them, or manage them. They would lose track of what they made, have no way to fix a typo in a destination URL, and no way to learn which links are valuable. This module is what turns the plugin from a one-shot link-making tool into an ongoing link-management product that people return to.

---

## 4. How It Fits Into The Bigger Picture

The Client Links Page sits in the middle of the plugin's user-facing experience. It is a frontend module — it lives on a public WordPress page that the site owner has chosen to host it on — but it leans on the plugin's backend to fetch data from the Traffic Portal service.

It does not create links by itself. Instead, when the user clicks "Add a link", it opens the existing link-creation form in a popup window. When the user clicks a row to edit it, the same form is reused. So this module sits on top of the link-creation module rather than duplicating it.

```
       [User's Browser]
              │
              ▼
   [Client Links Page]
              │
   ┌──────────┼──────────────────────┐
   │          │                      │
   ▼          ▼                      ▼
[Link form  [Plugin backend]   [QR Code helper]
 popup]           │
                  ▼
          [Traffic Portal API]
          (the source of truth
           for links and stats)
```

Upstream: the link-creation form feeds new links into the same list. Downstream: this page is what actually displays them and lets them be managed.

---

## 5. Key Concepts (Glossary)

- **Short link** — A short web address (for example `dev.trfc.link/summer-sale`) that quietly forwards visitors to a longer destination URL.
- **Shortcode (also called a "plugin tag")** — A small piece of text like `[tp_client_links]` that a site editor pastes into a WordPress page to make this whole module appear there.
- **QR code** — A square barcode that a smartphone camera can scan to instantly open a short link. Useful on posters, flyers, and packaging.
- **Domain group** — A header row in the table that gathers together every link that lives under the same short-link domain (for example all `dev.trfc.link/...` links sit under one "dev.trfc.link" group).
- **Active vs. Disabled** — An active link redirects visitors as expected. A disabled link is paused: the row is dimmed and visitors who try to use it will not be sent anywhere. Disabling is a soft pause, not a delete.
- **Change history** — A timeline of every modification ever made to a single link (created, edited, enabled, disabled), so the user can see who changed what and when.
- **Skeleton loader** — The greyed-out shimmering rows shown while real data is being fetched, so the page never looks blank.

---

## 6. The Main User Journey

This page only does anything for users who are already signed in. A signed-out visitor sees nothing.

1. The signed-in user lands on the page that hosts this module.
2. The page paints a placeholder "skeleton" table while it fetches the user's links in the background.
3. Once the data arrives, the skeleton is replaced by the real table. The user sees their links, grouped by domain, with click and QR-scan counts on each row. A bar chart at the top compares the performance of those links across the last 30 days by default.
4. The user can change the date range using the calendar pickers and press the small check-mark button to refresh the chart and counts for a different time window.
5. The user can type into the search box to narrow the list, pick "Active" or "Disabled" from the status dropdown to filter, or click any column header to sort the rows.
6. To work on a single link, the user clicks the row. The link-creation form opens in a popup, pre-filled with that link's details, ready for editing.
7. To create a brand new link, the user clicks the green "Add a link" button. The same popup opens, but blank.
8. To copy a short link, the user clicks the small copy icon on the row. A green "Copied!" tooltip flashes briefly to confirm.
9. To get a QR code, the user clicks the QR icon on the row. A dialog appears showing the QR code, with buttons to download it as an image, copy it to the clipboard, or open the underlying link.
10. To pause a link, the user flips its status toggle. The page asks for confirmation before disabling, then dims the row.
11. To audit changes, the user clicks the history icon on a row. A small panel slides up listing every action ever taken on that link in plain English ("created", "updated", "disabled"), with timestamps.
12. On a phone, the table reshapes itself into stacked cards instead of a wide grid, the chart collapses behind a "Show Chart" button, and modals slide up from the bottom of the screen like a native app sheet.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| Entry point | `includes/class-tp-client-links-shortcode.php` | Registers the `[tp_client_links]` shortcode and loads all the styles, scripts, and configuration the page needs. |
| Page layout (HTML template) | `templates/client-links-template.php` | Defines the visible structure: header bar, chart area, table, popups for editing, history, and QR codes. |
| Frontend logic | `assets/js/client-links.js` | Runs in the browser, fetches the link data, renders the table and chart, and reacts to clicks, sorting, searching, and editing. |
| Page styles | `assets/css/client-links.css` | Controls the visual design: colours, table layout, modal popups, mobile responsive behaviour, and skeleton loading animation. |
| Shared QR helper | `assets/js/qr-utils.js` | A small utility for generating, downloading, and copying QR codes that this page reuses. |
| Shared base styles | `assets/css/frontend.css` | Plugin-wide design tokens (colours, fonts) that this page inherits from. |
| UX critique notes | `docs/client-links-ui-critique.md` | A separate document listing known UI rough edges for future improvement. |

---

## 8. External Connections

- **Traffic Portal API (via the plugin backend)** — The actual list of short links, their destinations, and their click/scan statistics live in Traffic Portal. This page asks the plugin's own backend to fetch and return that data; it never talks to Traffic Portal directly.
- **Bootstrap (CDN-hosted CSS framework)** — Used for general layout helpers and form styling.
- **Font Awesome (CDN-hosted icon set)** — Provides the small icons (link, calendar, copy, QR, history, etc.) used throughout the page.
- **Chart.js (CDN-hosted charting library)** — Draws the bar chart of link performance.
- **QRCode.js (CDN-hosted QR library)** — Generates the QR code images shown in the QR popup.
- **Browser clipboard** — Used when the user clicks a copy button to put a short link or QR image onto the system clipboard.

---

## 9. Configuration & Settings

Most of the dials live on the shortcode itself, when a site editor pastes it onto a WordPress page.

- **Shortcode attributes** —
  - `page_size` controls how many rows are shown per page in the table (defaults to the plugin-wide dashboard page size).
  - `show_search` turns the search box on or off.
  - `show_filters` turns the active/disabled status filter on or off.
  - Example: `[tp_client_links page_size="25" show_search="true" show_filters="false"]`
- **Plugin-wide settings (WordPress admin)** — The short-link domain and the global default dashboard page size are read from the plugin's settings, so a site admin can change them in one place and this page picks them up.
- **Default date range** — The chart and stats default to the last 30 days. There are no admin settings to change this default; the user adjusts it on the page itself.
- **Login URL** — Where users get sent if their session expires while the page is open. Defaults to `/login/` on the host site.

---

## 10. Failure Modes (What Can Go Wrong)

- **The user is not logged in** → The page renders nothing at all. The shortcode is effectively invisible to anonymous visitors.
- **The user's session expires while the page is open** → The next action that needs the server (loading data, toggling status, viewing history) detects the expired session and redirects the user to the login page.
- **The Traffic Portal service is unreachable or returns an error** → A red error banner appears with a "Retry" button, and the table is hidden until the user retries successfully.
- **The user has no links yet** → A friendly empty-state message appears instead of an empty table, encouraging them to create their first link.
- **The data is still loading** → A shimmering skeleton table is shown so the page never appears blank or broken.
- **The user accidentally tries to disable a link** → A confirmation dialog asks them to confirm before the change is sent. Cancelling flips the toggle back.
- **The chart library fails to load** → The chart silently does not render, but the table and all management features still work.
- **The QR code dialog fails to copy to the clipboard** → The error is logged silently in the browser console; the user can still download the QR or open the link.

---

## 11. Related Modules

- [Link Shortener Form](./01-link-shortener-form.md) — The popup that opens when "Add a link" or any row is clicked is this module reused inside a dialog.
- [Usage Analytics Dashboard](./04-usage-analytics-dashboard.md) — A separate analytics-focused page that aggregates traffic across all of a user's links, while this module focuses on per-link management.
- [Admin Settings](./05-admin-settings.md) — Where the short-link domain and default page size shown on this page are configured.

---

## 12. Notes For The Curious

- The table groups rows by short-link domain, so if a user has links across two different domains (for example `dev.trfc.link` and `trfc.link`), each gets its own labelled section with a per-domain totals strip.
- Sorting by "Link" or "Last updated" happens on the server (the Traffic Portal API does the work). Sorting by "Clicks" or "Destination" happens in the browser, because the API does not currently support sorting by those columns.
- The same link-creation form is shared between the standalone shortener page and this page's "Add" / "Edit" popup. The page hides that form on initial load and only borrows it into the popup when needed, then politely puts it back when the popup closes.
- On mobile, the chart is hidden by default and replaced with a compact stats bar showing total clicks and QR scans. A tap on "Show Chart" expands the full chart, designed to keep small screens uncluttered.
- The module deliberately uses a "soft disable" model rather than deletion. Pausing a link preserves history and statistics, which matters for printed materials (flyers, posters) where the QR code is already in the wild.
- A separate UX critique document exists for this page (`docs/client-links-ui-critique.md`) listing known rough edges — for example, raw short codes can be hard to read at a glance, and the date picker layout is heavier than it needs to be. These are improvement opportunities, not bugs.

---

_Document version: 1.0 — Last updated: 2026-04-26_

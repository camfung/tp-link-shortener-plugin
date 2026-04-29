# Module: Personal Dashboard

> **Audience:** Non-technical, big-picture stakeholder. This document describes what the Personal Dashboard does, why it exists, and how it fits into the wider plugin — without requiring you to read any code.

---

## 1. One-Line Summary

The Personal Dashboard is the signed-in user's "control panel" — a private page that lists every short link they have made and lets them search, edit, copy, or generate a QR code for any of them.

---

## 2. What It Does (Plain English)

Once a user has created a few short links, they need a place to come back and manage them. The Personal Dashboard is that place. It shows the user a tidy table of their own links, grouped by website domain, with usage counts, creation dates, and quick-action buttons. Think of it like the "My Orders" page in an online shop — except the items being listed are short links instead of purchases.

The dashboard is only visible to logged-in users. If a visitor lands on the dashboard page without signing in, they see a polite "please log in" message and nothing else. This keeps each user's links private to them.

A user walks away from the dashboard having done one of four common tasks: found a specific link by searching for it, copied a link to share elsewhere, printed or downloaded a QR code for a link, or made a small edit to a link (such as changing where it points to). The dashboard does not create links from scratch on its own — it opens a pop-up window that contains the regular link-creation form for that.

Success looks like this: the user logs in, lands on the dashboard, instantly sees their links, finds the one they want, and either copies it, edits it, or grabs its QR code in two or three clicks.

---

## 3. Why It Exists (The Business Reason)

Without a dashboard, users have no way to find a short link they made last week — they would have to remember the exact short URL or recreate it from scratch. The dashboard turns the plugin from a one-shot link-creation tool into a real account-based service that users come back to. It is the main reason a user would log in rather than just use the public form anonymously.

---

## 4. How It Fits Into The Bigger Picture

The Personal Dashboard sits in the **frontend layer** of the plugin — it is what the signed-in user sees on the page. It does not create links itself; instead, it leans on the public link-creation form (which is reused inside a pop-up window) for any add-or-edit work, and it asks the server's link-listing endpoint for the current user's list of links.

Here is how data and clicks flow:

```
            [Signed-in WordPress User]
                       │
                       ▼
        [Page with the dashboard shortcode]
                       │
                       ▼
              [Personal Dashboard]
                /            \
               /              \
              ▼                ▼
    [Server: list-my-links]   [Add/Edit Pop-up]
              │                       │
              ▼                       ▼
       [Traffic Portal API]    [Public Link Form]
                                       │
                                       ▼
                              [Server: create/update link]
                                       │
                                       ▼
                              [Traffic Portal API]
```

**Upstream (what feeds in):** the WordPress login system tells the dashboard who the user is, and the server-side link-listing endpoint feeds it the user's links.

**Downstream (what it feeds):** the dashboard hands control to the public link form (in pop-up form) when the user wants to add or edit a link, and it talks to the QR-code helper module to render QR images on demand.

---

## 5. Key Concepts (Glossary)

- **Shortcode** (also called a "plugin tag") — A small piece of text like `[tp_link_dashboard]` that an admin pastes into a WordPress page. When the page loads, the plugin replaces that tag with the actual dashboard.
- **Short link** — A short URL like `bloom.land/abc123` that, when clicked, sends the visitor to a longer original URL.
- **Keyword** — The unique part at the end of a short link (the `abc123` in the example above). Sometimes called the "key".
- **Domain group** — A heading row in the dashboard table that bundles together every short link that lives under the same website. For example, all `bloom.land/...` links sit under one heading; all `tp.link/...` links sit under another.
- **QR code** — A square pattern of black-and-white dots that, when scanned by a phone camera, opens the short link. Useful for printed flyers, posters, business cards.
- **Modal** — A pop-up window that appears in the centre of the screen, dimming the page behind it. The dashboard uses one for adding and editing links.
- **Pagination** — The "Page 1, 2, 3..." controls that appear when a user has more links than fit on one screen.

---

## 6. The Main User Journey

### Logged-in user opens the dashboard

1. The user visits the page where the dashboard has been embedded (typically `/dashboard/` or similar).
2. The dashboard greets them with a "Loading..." placeholder while it goes to fetch their links from the server.
3. The server returns the first page of the user's links — for example, the first ten — along with the total count.
4. The dashboard fills in the table, grouping links by their website domain. Each domain has its own header row that shows totals (how many times links under that domain have been used, including how many of those uses came from QR scans).
5. The user can now search by typing into the search box, filter by status (Active / Disabled), change the sort order, or click the page numbers to jump through their list.
6. Hovering over a link reveals two small icons next to it: a copy icon and a QR icon.

### User copies a short link

1. The user hovers over a row.
2. They click the copy icon. The link is now on their clipboard, and a small "Copied!" toast appears briefly to confirm.

### User views and downloads a QR code

1. The user clicks the QR icon next to a link.
2. A pop-up appears showing the QR code, the link itself, and three buttons: Download, Open Link, and Copy to Clipboard.
3. The user clicks Download to save the QR code as an image they can print or share.

### User edits an existing link

1. The user clicks anywhere on a link's row (or the small edit pencil).
2. The Add/Edit pop-up opens, and the link's details are pre-filled inside it.
3. The user changes whatever they want — destination URL, expiry, description — and saves.
4. The dashboard refreshes to show the updated information.

### User adds a brand-new link from the dashboard

1. The user clicks the blue "Add a link" button at the top.
2. The Add/Edit pop-up opens with an empty form.
3. The user fills it in and submits.
4. The dashboard refreshes and the new link now appears in the list.

### Anonymous visitor (not logged in)

1. The visitor arrives at the dashboard page.
2. They see a single message: "Please log in to view your links."
3. Nothing else loads — no table, no buttons, no link data.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| Shortcode entry point | `includes/class-tp-dashboard-shortcode.php` | Registers the `[tp_link_dashboard]` shortcode and loads all the styles and scripts the dashboard needs. |
| HTML template | `templates/dashboard-template.php` | The actual page layout — the search bar, filters, the empty table skeleton, the edit pop-up, and the QR pop-up. |
| Frontend behavior | `assets/js/dashboard.js` | The browser-side logic that fetches the user's links, renders rows, handles search/sort/pagination, and wires up every button click. |
| Visual styling | `assets/css/dashboard.css` | All the colours, spacing, hover effects, skeleton loaders, and pop-up styling specific to the dashboard. |
| QR helper | `assets/js/qr-utils.js` | Shared helper that draws QR code images and handles downloading and copying them. |
| Companion form (reused) | `assets/js/frontend.js` | The public link-creation form, which the dashboard hijacks into its pop-up window for add and edit flows. |

---

## 8. External Connections

- **Traffic Portal API** — The server-side link-listing endpoint the dashboard calls is itself a thin wrapper around the Traffic Portal API. The dashboard does not call this API directly; it goes through the plugin's own server endpoint.
- **Bootstrap (CDN)** — The dashboard uses Bootstrap 5 for its table, buttons, and pagination styling, loaded from a public content-delivery network.
- **Font Awesome (CDN)** — Loaded from a public content-delivery network for the icons (search, copy, QR, refresh, etc.).
- **QRCode.js (CDN)** — A small JavaScript library that draws QR codes inside the QR pop-up, also loaded from a public content-delivery network.
- **Browser clipboard** — Used when the user clicks the copy icon. No data leaves the browser.

---

## 9. Configuration & Settings

- **Shortcode attributes** — The site admin can tune the dashboard when they paste the tag into a page:
  - `page_size` — How many links to show per page before pagination kicks in (defaults to the global plugin setting, typically 10).
  - `show_search` — `true` or `false` to show or hide the search box.
  - `show_filters` — `true` or `false` to show or hide the status and sort dropdowns.
  Example: `[tp_link_dashboard page_size="25" show_filters="false"]`.
- **WordPress admin settings** — The default page size lives under the plugin's admin settings page and is used when the shortcode does not set its own.
- **Login requirement** — Hard-coded: only signed-in users see the dashboard. There is no admin toggle to make it public.

---

## 10. Failure Modes (What Can Go Wrong)

- **The user is not logged in** → The dashboard hides itself entirely and shows a "Please log in" message. This is by design, not an error.
- **The server is down or the API call fails** → A red error banner appears with a "Retry" button. The user can keep clicking retry without leaving the page.
- **The user has zero links** → A friendly empty state appears with a "Create your first short link to get started!" message.
- **A user types a search that matches nothing** → The empty state appears for that search; clearing the search restores the full list.
- **The Traffic Portal service is slow** → A skeleton placeholder (grey shimmering rows) is shown while waiting, so the page does not look frozen.
- **The Bootstrap, Font Awesome, or QRCode.js content-delivery networks are blocked** → Some icons or styling may render imperfectly, but the core text and links still work.
- **The user clicks "Copy" but the browser denies clipboard access** → The copy silently fails; the toast confirmation does not appear. (This is rare and usually only happens on insecure connections.)

---

## 11. Related Modules

- **Public Link Shortener Form** — The dashboard reuses this form inside its pop-up window for both add and edit flows. The two modules talk to each other through a shared event system.
- **Server-Side Link Listing Endpoint** — The dashboard's data source. It looks up only the current user's links from the Traffic Portal.
- **QR Code Utilities** — Shared helper used by both the dashboard and the public form to draw, download, and copy QR images.
- **Usage Dashboard** — A separate, related dashboard focused on click and scan analytics rather than link management.

(Module-specific doc files for the above will be linked here once they exist in `docs/modules/`.)

---

## 12. Notes For The Curious

- The dashboard was originally built as a "proof of concept" (the source files still mention POC in their comments) but has since become a permanent, central feature.
- Rather than building its own add/edit form, the dashboard lifts the public link-creation form off the page and physically moves it into the pop-up — then puts it back when the pop-up closes. This means there is only one form to maintain.
- Links are grouped by domain in the table because most heavy users have links across several brand domains, and seeing per-domain totals (especially QR-scan totals) is more useful than one giant flat list.
- The "Recently Updated" sort is the default because users overwhelmingly come back to edit something they just made.
- Search is debounced — the dashboard waits a fraction of a second after the user stops typing before asking the server, so a long word does not trigger one server call per keystroke.

---

_Document version: 1.0 — Last updated: 2026-04-26_

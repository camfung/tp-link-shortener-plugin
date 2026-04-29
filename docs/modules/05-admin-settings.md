# Module: Admin Settings Panel

## 1. One-Line Summary

This module is the WordPress admin control panel where a site administrator turns plugin features on or off and tunes how the link shortener behaves.

---

## 2. What It Does (Plain English)

Every plugin has a few choices that only the site owner should make: things like "should we generate QR codes for every link?" or "how often should the dashboard refresh?" This module is the page where those choices get made. It lives inside the WordPress admin area, behind a menu item called "Link Shortener," and it gives the administrator a friendly form with checkboxes, dropdowns, and number fields instead of asking them to edit code.

The audience here is narrow but important: only a site administrator with full management permissions can see or change these settings. Regular visitors and signed-in members never see this page. Once the admin saves their choices, the rest of the plugin reads those choices and behaves accordingly. The dashboard, the link creation form, and the QR code feature all check this panel before deciding what to show.

Success looks like this: the admin opens the page, ticks a few boxes, picks a number from a dropdown, clicks "Save Settings," and walks away. From that moment on, the entire site reflects their preferences without anyone touching a line of code.

---

## 3. Why It Exists (The Business Reason)

Without this panel, every change to plugin behavior would require a developer to edit configuration files on the server. That makes the plugin slow to adapt and expensive to maintain. The settings panel turns the plugin into something a non-technical site owner can run themselves: switch features on for a launch, dial back polling to save server resources, or adjust how many links show per page based on user feedback. It is the difference between a tool you own and a tool you have to keep paying someone to operate.

---

## 4. How It Fits Into The Bigger Picture

The settings panel is the configuration hub. It does not create links, show dashboards, or talk to the outside world. Its only job is to save the administrator's preferences into the WordPress database, where every other module can read them.

```
[Administrator's Browser]
            │
            ▼
[Admin Settings Panel]  ──saves choices──►  [WordPress Options Database]
                                                       │
                                                       │ (read by)
                                                       ▼
                              ┌────────────────────────┴────────────────────────┐
                              ▼                  ▼                  ▼            ▼
                    [Link Shortener Form] [User Dashboard] [QR Code Module] [API Handler]
```

Think of it as the thermostat in a building. The thermostat does not heat or cool anything itself — it just records what temperature you want, and every other system in the building reacts to that setting.

**Upstream:** Nothing. The administrator is the only input.
**Downstream:** Almost every other module in the plugin reads at least one setting from this panel.

---

## 5. Key Concepts (Glossary)

- **WordPress Options** — A built-in WordPress storage area for plugin settings. Think of it as a key-value notebook that survives plugin updates and reboots.
- **Settings Page** — The dedicated screen inside the WordPress admin area where the form lives. Reached via the left-hand menu.
- **Capability Check** — A WordPress security gate that confirms the current user is allowed to manage site settings. Without the right permission, the page simply does not appear.
- **Sanitization** — The process of cleaning up whatever the admin types into a form so that bad data (like a polling interval of "minus seven seconds") never gets saved.
- **Polling Interval** — How often the dashboard quietly checks the server for updated link statistics. Shorter means fresher numbers but more server work.
- **Page Size** — The number of rows shown per page on the user-facing dashboard, so long lists of links stay manageable.

---

## 6. The Main User Journey

1. The administrator logs into the WordPress admin area.
2. They click the "Link Shortener" item in the main left-hand menu.
3. WordPress confirms they have permission to manage settings; if not, the page refuses to load.
4. The settings page renders, showing a main form on the left and a small information sidebar on the right.
5. The sidebar reminds the admin which constant to add to the configuration file for the API key, and shows the shortcodes they can paste into pages.
6. The admin works through the form, ticking checkboxes (AI-powered codes, QR codes, screenshots, expiry timer) and adjusting numeric fields (polling interval, dashboard page size).
7. They click "Save Settings."
8. The panel cleans up the values, rejects anything out of range, and stores the rest in the WordPress database.
9. A green confirmation banner appears at the top of the page.
10. From that moment on, every other module in the plugin reads the new values and behaves accordingly.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| Settings page logic | `includes/class-tp-admin-settings.php` | Builds the menu item, registers each setting, and renders the form. |
| Plugin context (defaults and helpers) | `includes/class-tp-link-shortener.php` | Hooks the settings class into WordPress and exposes helper methods other modules use to read the saved values. |
| API key reminder text | `includes/class-tp-admin-settings.php` (sidebar) | Tells the admin which constant to add to the WordPress configuration file. |
| Saved values storage | WordPress core `wp_options` database table | The actual key-value notebook that holds the saved choices. |

---

## 8. External Connections

This module is fully self-contained and does not talk to anything outside the WordPress site. It only reads from and writes to the WordPress database on the same server. No third-party services, no outbound calls, no browser storage.

---

## 9. Configuration & Settings

This module **is** the configuration surface for the plugin. The available controls are:

- **AI-Powered Short Codes (Gemini)** — Checkbox. When on, new links use a smart-suggestion service for memorable codes; when off, codes are random.
- **Enable QR Code Generation** — Checkbox. Turns the QR code feature on or off everywhere it appears.
- **Enable Screenshot Capture** — Checkbox. Turns destination-URL preview screenshots on or off. Requires a separate screenshot service key in the configuration file.
- **Enable Expiry Timer Display** — Checkbox. Controls whether anonymous (not-logged-in) users see a countdown showing when their trial link expires.
- **Usage Stats Polling Interval** — Number field, 1 to 60 seconds. How often the dashboard quietly refreshes click and scan counts.
- **Dashboard Page Size** — Dropdown, choices of 5, 10, 25, 50, or 100. Default number of links per page on the user dashboard.

There is also a **`wp-config.php` constant** the admin must set outside this panel — the API key — because it is too sensitive to live in the database. The sidebar of the settings page reminds them how to do this.

---

## 10. Failure Modes (What Can Go Wrong)

- **The user is not an administrator** → The page silently refuses to render; no error, just nothing to see.
- **The admin types a polling interval below 1 or above 60** → The value is automatically clamped back into the allowed range before saving.
- **The admin somehow submits an unexpected page size** → The system falls back to the default (10) instead of saving an invalid number.
- **The admin enables Screenshot Capture but never sets the screenshot service key** → The toggle is saved, but the screenshot feature itself will not work; the admin discovers this only when they try to use it.
- **The admin enables AI-Powered Short Codes but the AI service is offline** → The plugin quietly falls back to random codes; nothing breaks, but the smart-suggestion feature does not appear.
- **The WordPress options database is corrupted** → Every setting reverts to its default, which is a safe state but may surprise the admin.

---

## 11. Related Modules

- [Link Shortener Form](./01-link-shortener-form.md) — Reads the AI-powered codes, QR code, screenshot, and expiry timer toggles to decide what to show.
- [Personal Dashboard](./02-personal-dashboard.md) — Reads the polling interval and page size settings to control its refresh rate and pagination.
- [Client Links Page](./03-client-links-page.md) — Honors the QR code toggle and the default page size when rendering the per-link management table.
- [Traffic Portal API Client](./06-traffic-portal-api-client.md) — Reads the API key constant referenced in the settings sidebar to authenticate every call.

---

## 12. Notes For The Curious

- The panel uses the standard WordPress Settings API, which means it inherits the familiar look and feel of every other WordPress settings page — admins do not need to learn a new interface.
- The most sensitive value (the API key) deliberately lives outside this panel in the WordPress configuration file, so it never sits in the database where it could be exposed by a backup leak.
- Every numeric input has a sanitizer that quietly fixes bad values, so the panel is hard to break even with messy input.
- The page has a two-column layout on desktop and collapses to a single column on tablets and phones.
- Future-friendly: adding a new toggle is a small, repeatable change, which is why the panel has grown over time as the plugin has gained features.

---

_Document version: 1.0 — Last updated: 2026-04-26_

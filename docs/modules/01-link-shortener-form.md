# Module: Link Shortener Form

---

## 1. One-Line Summary

This module is the public-facing form where a visitor pastes a long URL and walks away with a short link, a QR code, and a preview screenshot.

---

## 2. What It Does (Plain English)

This module is the front door of the whole plugin. It is the box that shows up on a WordPress page when an editor places the plugin's tag on it. A visitor types or pastes a long, ugly web address into one field, and the form turns it into a short, tidy address that is easier to share, print, or scan.

While the visitor is typing, the form quietly checks the address in the background. It makes sure the link actually works, warns about redirects, and offers to fix small mistakes. Once the address looks good, it suggests a memorable nickname for the short link (a "magic keyword"), shows a QR code that anyone can scan with a phone camera, and even produces a small preview image of the destination page so the visitor can confirm they are pointing at the right thing.

It serves two audiences at once. Anonymous visitors get a quick, free, time-limited short link, plus a gentle nudge to register. Logged-in members get a richer experience: their links don't expire, they can edit them later, and they can pick a custom keyword for the URL.

Success looks simple. The visitor leaves the form with a short web address they can copy with one click, a QR code they can download or share, and the confidence that the link will actually work when someone clicks it.

---

## 3. Why It Exists (The Business Reason)

The form is the plugin's storefront. It is the only place where a brand-new visitor can experience the product without signing up. If we deleted it tomorrow, the rest of the plugin (the backend, the dashboard, the admin settings) would still exist, but no one would have a reason to use any of it. This is also where free users are converted into registered users: every step of the form quietly invites the visitor to create an account so that their links don't expire.

---

## 4. How It Fits Into The Bigger Picture

The form is the plugin's **frontend** layer. It is what the visitor's browser actually sees and touches. It does not store anything itself — it relies on backend services to do the real work, and on outside helpers to dress up the result.

```
                [Visitor's Browser]
                         │
                         ▼
              ┌─────────────────────┐
              │ Link Shortener Form │   ◄── this module
              └─────────────────────┘
                         │
        ┌────────────────┼─────────────────┐
        ▼                ▼                 ▼
  [URL Validator]  [Plugin Backend]  [QR Generator]
  (checks link    (creates short    (draws QR
   is real and     link, suggests   code in
   reachable)      keywords,        browser)
                   captures
                   screenshot)
                         │
                         ▼
                 [Traffic Portal API]
                 (stores the short
                  link in the
                  central database)
```

**Upstream:** The form depends on the plugin's main bootstrap step to load its JavaScript and CSS. The shortcode tag on a page is the "doorbell" that triggers the form to appear.

**Downstream:** Once a link is created, the form hands the visitor's data to the plugin's backend, which talks to the Traffic Portal API. After the link is saved, the form triggers the QR code helper, the screenshot helper, and (for logged-in users) coordinates with the Dashboard module so an edit there populates the form here.

---

## 5. Key Concepts (Glossary)

- **Shortcode** (also called a "plugin tag") — a small piece of text like `[tp_link_shortener]` that an editor pastes into a WordPress page; WordPress sees it and replaces it with the form when the page loads.
- **Short link** — the tidy URL the form produces, made up of a short domain plus a "keyword" (the part after the slash).
- **Magic Keyword** — the human-friendly nickname for a short link (the part the user can choose, like `summer-sale`). Logged-in users can pick their own; the system can also suggest one.
- **QR code** — the square black-and-white pattern that a phone camera can scan to open the short link without typing.
- **Browser fingerprint** — an invisible signature derived from a browser's quirks (screen size, fonts, timezone, etc.) used to recognize an anonymous visitor on return without requiring a login.
- **Returning visitor** — someone who already created a free link from this same browser; the form recognizes them and shows their existing link instead of an empty form.
- **Validation** — the background check that confirms a typed-in URL really exists and can be reached, before any short link is created.
- **Update mode** — once a link has been created or loaded, the form switches gears: the same fields now edit that link instead of creating a new one.

---

## 6. The Main User Journey

There are three flavors of journey through this form. They all start at the same place.

### Journey A: Anonymous visitor creating a brand-new short link

1. The visitor lands on a page where the plugin's tag has been placed. The form appears with a single big input that says "Paste or type long link to simplify it."
2. The visitor types or pastes a long URL. They can also click the small clipboard icon to paste from their device's clipboard.
3. As they type, the form smooths out the input — for example, it adds `https://` if the visitor forgot it, and silently trims out characters that are not allowed in a web address.
4. After a brief pause in typing, the form checks the URL in the background. A small spinner appears, then a green check ("URL is valid"), a yellow warning (e.g. "Permanent redirect detected"), or a red error (e.g. "This website doesn't exist").
5. Once the URL passes the check, a second field slides into view: the Magic Keyword box. The form also reaches out for a suggested keyword and shows it. The visitor can accept it, click the lightbulb icon to see another suggestion, or type their own.
6. The visitor clicks the save icon. A spinner appears with the message "Creating your short link…"
7. The form receives the new short link from the backend. It displays the short URL with a copy button next to it, draws a QR code, and starts capturing a small screenshot of the destination page.
8. A "Try it now" message appears, plus a soft reminder that says "Save the link and it never expires," which links to the registration page.
9. A countdown timer starts ticking down on the screenshot, showing how long the free link will live before it expires.
10. The form then switches into update mode, so the same fields can edit this newly created link rather than make a new one.

### Journey B: Anonymous visitor returning later

1. The visitor reloads the page on the same device they used before.
2. The form starts up, generates a browser fingerprint behind the scenes, and asks the backend, "Have I seen this person before?"
3. If a non-expired link exists for that fingerprint, the form skips the empty state. It pre-fills the destination, displays the existing short link, regenerates the QR code, and starts the expiry countdown again.
4. The form is locked into update mode. The visitor can still edit, but cannot create a new free link until the old one expires (or they register).

### Journey C: Logged-in user

1. The form looks the same, but the Magic Keyword field is always available, and there is no expiry timer.
2. The user can paste a URL, get a suggested keyword (or type their own), and save the link.
3. After creation, the form switches into update mode for that link. There is no countdown, and no fingerprint check is performed.
4. If the user clicks "Edit" on a link in their Dashboard, the Dashboard module sends a signal to the form, which loads that link's details into its fields and switches to update mode.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| Shortcode entry point | `includes/class-tp-shortcode.php` | Registers the `[tp_link_shortener]` tag with WordPress and renders the form HTML when the tag appears on a page. |
| Form HTML template | `templates/shortcode-template.php` | The actual structure of the form: inputs, buttons, the result panel, the QR area, and the screenshot panel. |
| Form behavior | `assets/js/frontend.js` | All the in-browser logic: typing, pasting, suggesting keywords, submitting, switching between create and update modes, and polling for usage stats. |
| URL validation library | `assets/js/url-validator.js` | The background checker that confirms a typed URL really works, handles redirects, and reports errors in plain English. |
| QR code helper | `assets/js/qr-utils.js` | Draws the QR code, downloads it as a PNG, or copies it to the clipboard. |
| Look and feel | `assets/css/frontend.css` | The colors, spacing, animations, and responsive layout for phones, tablets, and desktops. |

---

## 8. External Connections

- **Traffic Portal API** — Indirectly. The form never speaks to the Traffic Portal API directly; it speaks to the plugin's own backend, which speaks to Traffic Portal on its behalf. This keeps the API key off the visitor's browser.
- **FingerprintJS** — A small JavaScript library bundled with the plugin (`assets/js/fingerprintjs-v4-iife.min.js`). It produces the anonymous browser fingerprint that lets the form recognize returning visitors without requiring a login.
- **QRCode.js** — The library that draws the QR code on the page. Loaded from the plugin's own assets.
- **Browser Clipboard API** — The browser's built-in feature that lets the form paste from, and copy to, the clipboard with a single click.
- **Browser localStorage** — A small slot of storage in the browser where the form keeps two kinds of things: feature debug toggles (for developers) and a marker remembering a returning visitor's previous link.

---

## 9. Configuration & Settings

- **WordPress admin settings** — The plugin's settings page (reached via the top-level _Link Shortener_ item in the WordPress admin sidebar) controls whether custom keywords are restricted to logged-in users, the default short-link domain, the default user ID for API requests, whether QR codes are generated, whether screenshots are captured, and whether the expiry countdown is shown.
- **Shortcode attributes** — The tag accepts an optional `domain` attribute, e.g. `[tp_link_shortener domain="trfc.link"]`, to override the default short-link domain on a single page.
- **`wp-config.php` constants** — The Traffic Portal API key and the API endpoint URL are defined here, but the form itself never reads them directly; the backend does.
- **localStorage debug flags** — Developers can flip on detailed console logging by setting flags like `tpDebug:validation`, `tpDebug:submit`, `tpDebug:fingerprint`, or `tpDebug:all` in their browser's localStorage. These do nothing in production for normal users.
- **Polling interval** — How often the form refreshes its "scanned" and "clicked" usage counters is configurable in the admin and defaults to once every five seconds.

---

## 10. Failure Modes (What Can Go Wrong)

- **The visitor pastes an invalid URL** → The form's background check catches it, the input border turns red, and a friendly error message appears (e.g. "This website address doesn't exist. Please check for typos."). The save button stays disabled until the URL is fixed.
- **The destination website is down or unreachable** → The validation check fails with a message like "Unable to connect to this website. The server may be down." The visitor can still try a different URL.
- **The destination has an invalid SSL certificate** → The form falls back to HTTP, shows a yellow warning explaining the change, and updates the URL in the input field so the visitor knows what was modified.
- **The clipboard is blocked or unsupported** → The clipboard paste button is disabled and a hover-tip explains the situation. The visitor can still paste manually with their keyboard.
- **The browser fingerprint helper fails to load** → The form continues to work for new visitors, but cannot recognize returning anonymous visitors. It falls back to showing an empty form.
- **An anonymous visitor exceeds the free-link rate limit** → The save attempt is rejected with a clear message and a list of benefits to creating an account (unlimited links, analytics, custom domains, etc.).
- **The QR code library fails to draw** → The short link is still shown and copyable; only the QR area is empty.
- **The screenshot service is slow or fails** → A spinner stays visible in the screenshot panel; the rest of the form is unaffected.
- **The free link's countdown reaches zero while the page is open** → The countdown shows "Expired" and an error message appears; the visitor must reload to start over.

---

## 11. Related Modules

- [Traffic Portal API Client](./06-traffic-portal-api-client.md) — Receives the form's submissions and talks to the Traffic Portal API on its behalf. Without it, the form has nowhere to send data.
- [Personal Dashboard](./02-personal-dashboard.md) — A separate area for logged-in users that lists all their links. When the user clicks "Edit" there, the dashboard sends a signal that this form picks up and uses to load the link into its fields.
- [Client Links Page](./03-client-links-page.md) — The customer-facing list-and-manage view that opens this same form inside a popup for editing.
- [Admin Settings](./05-admin-settings.md) — Where an administrator decides whether custom keywords are premium-only, what the default domain is, and whether QR codes, screenshots, and expiry timers are enabled — all of which change how the form behaves.

---

## 12. Notes For The Curious

- The form was designed to feel "alive" — almost everything happens as the visitor types, not after they hit submit. This includes URL checking, keyword suggestion, and showing the custom-keyword field only when it makes sense.
- Keyword suggestions come from a tiered AI service. The form first asks for a fast suggestion, then quietly fetches better suggestions in the background and weaves them into the carousel that appears when you click the lightbulb repeatedly.
- The "returning visitor" recognition uses a browser fingerprint rather than a cookie. This means clearing cookies does not clear the visitor's free link — they have to use a different browser or device for a fresh start.
- The form has a hidden "Proof of Concept" panel for testing the AI keyword tiers. It only appears for users who have set a localStorage flag, so it never shows up for ordinary visitors.
- All visible text is wrapped for translation, so this form can be re-skinned in another language without touching the code.
- Future plans include a richer "premium" check (instead of just "is this user logged in?") and a real expiry-enforcement system rather than the current trial-style countdown.

---

_Document version: 1.0 — Last updated: 2026-04-26_

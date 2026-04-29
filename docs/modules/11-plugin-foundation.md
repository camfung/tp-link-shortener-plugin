# Module: Plugin Foundation

> **Audience:** Non-technical, high-level thinker. This document explains the plumbing that makes the plugin work — the parts you never see but everything depends on.

---

## 1. One-Line Summary

The Plugin Foundation is the wiring and plumbing of the plugin — the boot-up code, internal switchboard, and asset loader that lets every other feature exist.

---

## 2. What It Does (Plain English)

Think of the plugin like a house. Before you can have a kitchen, a bathroom, or a working light switch, you need foundations, framing, electrical wiring, and water lines. The Plugin Foundation is exactly that — the invisible structural layer that makes everything else possible.

When WordPress starts up, it goes hunting for installed plugins and turns them on. The Foundation is the first thing it touches. It introduces the plugin to WordPress, sets up some basic settings the first time it is activated, and gets the plugin's internal building blocks ready to do their jobs.

It also acts as a switchboard. When the visitor's browser sends a request — for example, "create me a short link" or "show me my recent links" — the Foundation receives that request, checks who is asking, and routes it to the correct piece of code that knows how to answer.

Finally, it acts as a smart asset loader. Modern websites pull in stylesheets and JavaScript files to look pretty and feel interactive. Loading those files on every single page would slow the whole site down, so the Foundation only loads them on the specific pages where the plugin is actually being used.

The people who interact with this module are not really end users — they are the *other modules in the plugin*. The Foundation is what holds them together. End users feel its effect indirectly: a page that loads quickly, buttons that work, and forms that submit cleanly.

---

## 3. Why It Exists (The Business Reason)

Without the Foundation, the plugin is just a pile of disconnected files. Nothing would start up, nothing would talk to the browser, and nothing would know which pages need styling. If we deleted this module tomorrow, the entire plugin would simply fail to load — every feature, every form, every dashboard would vanish from the site. It exists because every working product needs a starting point and a backbone.

---

## 4. How It Fits Into The Bigger Picture

The Foundation sits at the very bottom of the plugin's stack. Everything else depends on it; it depends on nothing inside the plugin (only on WordPress itself).

```
                    [WordPress Core]
                          │
                          ▼
                  [Plugin Foundation]
                          │
        ┌─────────────────┼──────────────────┐
        ▼                 ▼                  ▼
  [Shortcodes]      [Admin Settings]    [Domain Clients]
  (forms users      (configuration       (Traffic Portal,
   see on pages)     pages)               Wallet, AI, etc.)
        │
        ▼
  [Browser CSS/JS]
  (loaded by the
   asset loader)
```

- **Upstream** — WordPress itself. WordPress decides "now is the time to start plugins" and the Foundation listens for that signal.
- **Downstream** — Every other module: shortcodes, admin settings, the API clients, the asset files. They all rely on the Foundation being up and running.

---

## 5. Key Concepts (Glossary)

- **Entry point** — The single file WordPress reads first when it loads the plugin. Like a house's front door — there is exactly one, and everything else is reached from it.
- **Bootstrap** — The act of starting a system up and getting all its parts ready to work. "Booting up" is the same idea.
- **Autoloader** — A behind-the-scenes helper that automatically finds and loads the right code file the moment another piece of code asks for it. Saves the developer from having to list every file by hand.
- **AJAX request** — A quiet message the browser sends to the server in the background, without reloading the page. When you click a button and something updates instantly without a page flicker, that is AJAX.
- **AJAX endpoint** — A specific named "address" inside the plugin that knows how to handle a particular kind of background request, such as "create a link" or "fetch my wallet balance."
- **Enqueue** — WordPress jargon for "add this stylesheet or script to the queue of things to load on this page." The Foundation enqueues only what is needed, only where it is needed.
- **Activation hook** — A one-time setup routine that runs the very first time an admin turns the plugin on. Used to create database tables and pick sensible defaults.

---

## 6. The Main User Journey

The Foundation does not have a single "user" — it has three distinct roles. Here are the three journeys it supports.

### Journey A: WordPress starts up and loads the plugin

1. WordPress finishes its own start-up routine.
2. WordPress finds the plugin's entry file and reads it.
3. The entry file announces the plugin to WordPress and prepares some basic constants (version number, file paths, and so on).
4. The Foundation turns on its autoloader so the rest of the code can be found on demand.
5. Each major component of the plugin (shortcodes, admin settings, API switchboard, asset loader) is created and parked in memory, ready to act.
6. The plugin is now fully alive and waiting for visitors or admins to do something.

### Journey B: An admin activates the plugin for the first time

1. The admin clicks "Activate" on the WordPress plugins screen.
2. The Foundation runs its one-time setup routine.
3. Sensible default settings are written into WordPress (default domain, default user ID, default toggles).
4. A small database table is created to track changes to short links over time.
5. The plugin is now ready and the admin can start using the settings page.

### Journey C: A visitor's browser sends a background request

1. A visitor on a page does something — for example, clicking "Create" on the short-link form.
2. The browser quietly sends a message to the server, asking the plugin to do the work.
3. The Foundation's switchboard receives the message and confirms the request is genuine (not a forged or hostile attempt).
4. The switchboard hands the message to the right specialist piece of code (link creation, screenshot capture, wallet top-up, etc.).
5. The specialist returns an answer.
6. The switchboard packages the answer and sends it back to the browser, which updates the page on the spot.

### Journey D: A page loads that uses the plugin

1. A visitor loads a WordPress page.
2. The Foundation's asset loader checks whether that page actually contains one of the plugin's features.
3. If it does, the loader queues up the needed stylesheets and scripts (Bootstrap styling, QR code generator, fingerprint library, the plugin's own front-end script, etc.).
4. If it does not, the loader skips loading anything — keeping the page fast.
5. The browser receives only the assets it actually needs.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| Plugin entry point | `tp-link-shortener.php` | The file WordPress opens first; it announces the plugin, sets up defaults on activation, and hands off to the main class. |
| Main plugin class | `includes/class-tp-link-shortener.php` | Wires together every component and holds shared helpers like "what's the API key" and "what domain are we using." |
| Autoloader | `includes/autoload.php` | Automatically finds and loads code files for the plugin's various internal libraries on demand. |
| AJAX switchboard | `includes/class-tp-api-handler.php` | Receives all background requests from the browser and routes them to the correct handler. |
| Asset loader | `includes/class-tp-assets.php` | Decides which stylesheets and scripts to load on each page so unused pages stay light. |

---

## 8. External Connections

The Foundation itself does not talk to outside services. Its job is to set the stage so that other modules can. Two minor exceptions are worth mentioning, because they are visible in the asset loader:

- **Public CDNs (Bootstrap, Font Awesome, QR code library)** — The asset loader pulls a few well-known styling and utility libraries from public content-delivery networks instead of bundling them with the plugin. This keeps the plugin small.
- **WordPress AJAX endpoint** — The switchboard is reached through WordPress's standard background-request URL. This is internal to the WordPress site, not a third-party service.

Everything else (Traffic Portal, the wallet, the AI shortcode generator) is handled by other modules that the Foundation simply delegates to.

---

## 9. Configuration & Settings

The Foundation itself has very few knobs — most settings belong to other modules. The handful that live here are:

- **`wp-config.php` constants** — `API_KEY` (required, the Traffic Portal API key) and `TP_API_ENDPOINT` (optional, overrides the default API URL). These are intentionally kept out of the database for security.
- **Plugin version constant** — A single internal number used to "cache-bust" stylesheets and scripts when the plugin is updated.
- **Default options created on activation** — Default domain (`dev.trfc.link`), default user ID, and a default "premium-only" toggle set to off. Admins can change any of these later from the Settings page.

There is no Foundation-specific admin page. Admins configure the plugin through the top-level *Link Shortener* item in the WordPress admin sidebar, which is owned by the Admin Settings module.

---

## 10. Failure Modes (What Can Go Wrong)

- **The API key is missing from `wp-config.php`** → The plugin still loads, but features that talk to Traffic Portal will fail with a clear error. A note is written to the WordPress error log.
- **WordPress version is too old** → WordPress refuses to activate the plugin and shows a message about the minimum required version.
- **A required code file is missing or corrupted** → The plugin will fail to load and WordPress will show a fatal error on the plugins page; reinstalling the plugin fixes this.
- **A background browser request arrives without a valid security token** → The switchboard rejects the request and returns an error. This is by design — it stops outsiders from forging requests.
- **A page uses the plugin's features but the asset loader does not recognise the shortcode** → Styles or scripts may not load and the form will look unstyled or behave oddly. This usually means a typo in the shortcode name on the page.
- **The activation routine cannot create the history table** → The plugin will still work for everyday use, but the link-history dashboard will appear empty because there is nowhere to record events.

---

## 11. Related Modules

- [Admin Settings](./05-admin-settings.md) — Owns the WordPress admin configuration page that the Foundation hooks into.
- [Link Shortener Form](./01-link-shortener-form.md), [Personal Dashboard](./02-personal-dashboard.md), [Client Links Page](./03-client-links-page.md), [Usage Analytics Dashboard](./04-usage-analytics-dashboard.md) — Front-end shortcodes that the Foundation instantiates and wires up.
- [Traffic Portal API Client](./06-traffic-portal-api-client.md) — The library the Foundation's autoloader is responsible for finding when other code asks for it.

---

## 12. Notes For The Curious

- This module was the very first one built — it was Milestone 1 in the original development plan. The plugin grew outward from here.
- The autoloader uses an industry-standard convention called PSR-4. The short version: it lets developers organise code into namespaces (think of folders for code) and trust that the right file will load when needed, without writing a long list of imports anywhere.
- The asset loader is deliberately defensive about *where* it loads things. Loading scripts site-wide is a common cause of slow WordPress sites; this plugin only loads its assets on pages that actually use it.
- The switchboard registers separate "logged-in" and "logged-out" entry points for each background request. Sensitive actions like wallet operations will refuse to answer logged-out callers, even if the address is technically reachable.
- The Foundation also exposes a small REST API (used for things like log collection from the browser). REST is a slightly more modern style of background request than AJAX; the plugin uses both and the switchboard handles them side by side.

---

_Document version: 1.0 — Last updated: 2026-04-26_

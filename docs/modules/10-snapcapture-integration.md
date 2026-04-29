# Module: SnapCapture Integration

> **Documentation Template** — This file follows the canonical layout for module documents.
>
> **Audience:** Non-technical, high-level thinker. Big-picture first.

---

## 1. One-Line Summary

This module is the plugin's "photographer" — it asks an outside service to take a picture of any webpage so we can show a preview of it.

---

## 2. What It Does (Plain English)

When a user creates a short link, they want to know that the destination URL really is what they think it is. A bare web address is hard to recognize at a glance — but a thumbnail image of the page is instantly recognizable. This module solves that recognition problem.

It works like an automated photographer. The plugin hands it a web address. It travels out to a third-party service called SnapCapture, asks that service to visit the page, take a screenshot, and send the image back. The image then comes back into the plugin so the rest of the system can save it, display it as a preview tile, or attach it to a short link card.

The people who benefit are the end users of the link shortener. They never talk to this module directly — they just see a picture next to the short links they create. Behind the scenes, other parts of the plugin call this module whenever they need a fresh preview image. Success looks like a clean preview thumbnail appearing within a few seconds of pasting a URL.

---

## 3. Why It Exists (The Business Reason)

Without this module, short links would be naked text. People would have to trust the URL or click through to verify the destination — a small but real friction. Visual previews make links feel trustworthy, recognizable, and shareable, which is the whole reason people use a link shortener in the first place. Removing this module would make the dashboard feel barren and would make link verification harder for end users and admins.

---

## 4. How It Fits Into The Bigger Picture

This module is an **integration layer** — it sits between the plugin and an external service. It is not a frontend (no buttons or screens) and it is not the core link-creation logic. It is a specialist that the rest of the plugin calls when it needs a screenshot.

```
[Link Shortener Form / Dashboard]
              │
              │ "give me a screenshot of this URL"
              ▼
[SnapCapture Integration Module]   ◄────  [Logger writes diagnostic file]
              │
              │ HTTPS request with API key
              ▼
[SnapCapture Service on RapidAPI]
              │
              │ returns image bytes
              ▼
[Image flows back to caller, then to the user's screen]
```

**Upstream (what calls into it):** other parts of the plugin — for example, the link creation flow and the WordPress admin AJAX endpoint that the browser hits when it wants a preview.

**Downstream (what it depends on):** the SnapCapture service, which is hosted on RapidAPI. The module also depends on a working internet connection from the WordPress server.

---

## 5. Key Concepts (Glossary)

- **SnapCapture** — The third-party screenshot service the plugin pays for and calls. Think of it as a remote photographer-on-demand.
- **RapidAPI** — The marketplace that hosts SnapCapture and handles billing and access. The plugin's API key is issued by RapidAPI.
- **API Key** — A secret password that proves the plugin is allowed to use SnapCapture. Without it, every request gets rejected.
- **Screenshot Request** — A small bundle of instructions ("here is the URL, please use desktop size, please return JPEG") sent to the service.
- **Screenshot Response** — What comes back: the image bytes plus a few extras like whether the result was served from cache and how fast it returned.
- **Cache hit** — When SnapCapture has recently taken a picture of the same URL and returns the saved copy instantly instead of taking a new one. Cheaper and faster.
- **Logger** — A small diary-keeper that writes a line to a file every time something interesting or wrong happens, so a developer can trace problems later.

---

## 6. The Main User Journey

The end user never interacts with this module directly. The journey is one the system performs on the user's behalf.

1. The user pastes a long URL into the link shortener form (or opens the dashboard with existing links).
2. The plugin decides it needs a preview image for that URL.
3. The plugin hands the URL to this module along with a few preferences (desktop view, JPEG image).
4. The module checks that an API key is configured. If not, it stops and reports a clear error.
5. The module bundles the request and sends it over HTTPS to the SnapCapture service.
6. SnapCapture either takes a fresh screenshot of the destination page or returns a cached one it already has.
7. The image comes back as either raw image bytes or a JSON envelope containing the image encoded as text.
8. The module unpacks the response, double-checks it looks valid, and notes whether it was a fresh or cached capture.
9. The image is handed back to whoever asked for it. The Logger records the round trip — useful when something goes wrong.
10. The user sees the preview thumbnail appear next to their short link.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| Main client | `includes/SnapCapture/SnapCaptureClient.php` | The front desk that other code calls to request a screenshot. |
| Diagnostic logger | `includes/SnapCapture/Logger.php` | Writes a dated, leveled log file so problems can be traced later. |
| Request shape | `includes/SnapCapture/DTO/ScreenshotRequest.php` | The form the plugin fills out before sending — URL, format, dimensions. |
| Response shape | `includes/SnapCapture/DTO/ScreenshotResponse.php` | The packaged result — image bytes, cache flag, response time. |
| Network plumbing | `includes/SnapCapture/Http/` | The actual machinery that sends HTTPS requests; includes a real and a mock implementation for tests. |
| Error catalog | `includes/SnapCapture/Exception/` | A family of named errors (auth, validation, network, generic) so callers can react appropriately. |
| Internal README | `includes/SnapCapture/README.md` | Developer-facing notes for maintaining this module. |
| Configuration guide | `docs/SNAPCAPTURE_CONFIGURATION.md` | Where admins learn how to install the API key. |
| Endpoint guide | `docs/SCREENSHOT_API.md` | Describes the WordPress AJAX endpoint that exposes this module to the browser. |

---

## 8. External Connections

- **SnapCapture (via RapidAPI)** — The screenshot service that does the actual page rendering and capture. All real work happens here.
- **The local filesystem** — The Logger writes diagnostic notes into a log file on the WordPress server.
- **WordPress error log** — Errors are mirrored here for convenience so admins do not need to hunt for the dedicated log file.

The module does not talk to the WordPress database directly and does not call any other external services.

---

## 9. Configuration & Settings

- **WordPress constant (recommended for production)** — Adding `SNAPCAPTURE_API_KEY` to `wp-config.php` is the standard, secure way to install the key.
- **Server environment variable** — `SNAPCAPTURE_API_KEY` can be set at the operating system or web server level. Useful for Docker and CI environments.
- **`.env.snapcapture` file (development only)** — A small file at the project root for local dev work, deliberately excluded from version control.
- **Request timeout** — The number of seconds to wait before giving up on a screenshot, with a sensible default. Adjustable when the module is wired up.
- **Logger settings** — Logging can be turned on or off, and the verbosity dialed between debug, info, warning, and error.

There are no WordPress admin settings screens for this module today; configuration is intentionally code-and-environment level.

---

## 10. Failure Modes (What Can Go Wrong)

- **No API key is installed** → The module refuses to send a request and the caller sees a "service not configured" error.
- **The API key is wrong or expired** → The service rejects the request and the user sees an authentication error; admins should renew or reissue the key.
- **The user supplies an invalid URL** → The service replies with a validation error and no screenshot is produced.
- **The plugin's RapidAPI plan has hit its rate limit** → Requests temporarily fail with a clear "rate limit exceeded" message until the limit resets or the plan is upgraded.
- **The SnapCapture service is slow or down** → A network or server error is raised, the failure is logged, and the caller can retry later.
- **The destination website blocks automated visitors** → The screenshot may come back blank or the service may error; trying a different URL usually reveals whether the issue is the target site.
- **The response is malformed** → The module detects that the data is not a valid image or JSON envelope and raises a clear error rather than returning garbage.

---

## 11. Related Modules

- [Link Shortener Form](./01-link-shortener-form.md) — The form that creates short links is the most common upstream caller; whenever a new link is created, a preview screenshot is typically requested through this module.

Other modules that consume screenshots (such as the dashboard preview tiles and the AJAX endpoint that the browser calls) are documented elsewhere; they all funnel through this single integration point.

---

## 12. Notes For The Curious

- The network plumbing was deliberately split into an interface plus a real implementation and a mock implementation. That split means tests can run without ever touching the real SnapCapture service or spending API credits.
- The module supports both binary and JSON response styles. Binary is faster and lighter; JSON is friendlier when the result needs to travel through a browser, because the image is already encoded as text.
- The Logger is optional. The module works fine without it, but in production it is wired up so that any odd behavior (slow responses, auth failures, malformed payloads) leaves a trail.
- A small "ping" capability exists for health checks. It is not currently surfaced in the UI but could be used by an admin diagnostics screen in the future.
- The API key resolution intentionally checks WordPress constants first, then environment variables, then a dotfile — a layered approach that favors production-safe configuration while still being friendly for local development.

---

_Document version: 1.0 — Last updated: 2026-04-26_

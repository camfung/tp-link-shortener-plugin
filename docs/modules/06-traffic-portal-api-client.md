# Module: Traffic Portal API Client

---

## 1. One-Line Summary

This module is the dispatcher that carries every short-link request from the WordPress site to the Traffic Portal cloud service and brings the answer back.

---

## 2. What It Does (Plain English)

When a visitor pastes a long URL into the plugin and asks for a short link, something has to actually create that short link. The short link does not live inside WordPress — it lives in a cloud service called the Traffic Portal. This module is the part of the plugin that knows how to speak to that cloud service.

Think of it as a translator and courier rolled into one. Other parts of the plugin (the form on the homepage, the user dashboard, the admin tools) hand it a plain-English request like "please create a short link for this URL" or "please give me this user's recent links." The module wraps that request in the exact format the cloud service expects, sends it over the internet, waits for a reply, checks that the reply makes sense, and hands a clean answer back to whoever asked.

End users never see this module directly. It is invisible to them. Only other modules inside the plugin call into it. Success, from the module's point of view, looks like this: every request to the cloud either comes back with a clean, understood answer, or comes back with a clear, named error that the rest of the plugin can react to gracefully.

---

## 3. Why It Exists (The Business Reason)

Without this module, the plugin would have no way to actually create short links — it would just be an empty form. Every short link sold, every click counted, and every dashboard view depends on a healthy conversation with the Traffic Portal cloud, and this module is the only place in the plugin that knows how to have that conversation. Centralising it here also means the cloud service can change its address, its rules, or its error messages, and only this one module needs to be updated.

---

## 4. How It Fits Into The Bigger Picture

This is a backend integration layer. It sits between the rest of the WordPress plugin and the outside world. Every other module that needs something "real" from the cloud has to go through it.

```
[Homepage Form] ──┐
[User Dashboard] ─┤
[Admin Tools]    ─┼──►  [Traffic Portal API Client]  ──►  [Traffic Portal Cloud Service]
[Background Jobs]─┤                                                    │
                  │                                                    │
                  └◄──── (clean result or named error) ◄───────────────┘
```

Upstream (callers): the public link-creation form, the logged-in user dashboard, the admin bulk tools, and various background jobs all call into this module.

Downstream (what it talks to): the Traffic Portal cloud API, hosted on Amazon Web Services. That is the only outside system it speaks to.

It depends on nothing else inside the plugin to do its job — it is intentionally self-contained — but almost everything in the plugin depends on it.

---

## 5. Key Concepts (Glossary)

- **Short link** (also called a "masked record") — The product the plugin creates: a tiny URL that redirects to a much longer one. The cloud service stores these.
- **Traffic Portal** — The external cloud service that owns and serves the short links. The plugin is a customer of this service.
- **API key** — A long secret password the plugin sends with every request so the cloud knows the request came from a trusted source.
- **UID** — The numeric ID the cloud uses for a user. The plugin holds on to this so it can ask for "this user's links" later.
- **Fingerprint** — A unique signature for an anonymous browser, used so a visitor who has not logged in can still find their previous short link.
- **Request / response** — A request is the question the plugin asks the cloud. A response is the answer that comes back.
- **Pagination** — Asking for results in pages (e.g. 50 at a time) instead of all at once. Used for the user's link history.
- **DTO** (data transfer object) — A tidy package of data with a fixed shape; this module uses DTOs so the rest of the plugin gets predictable answers instead of raw, messy cloud output.

---

## 6. The Main User Journey

Because this module is invisible, the journeys here are really "what happens behind the scenes when another module asks for something."

### Journey A: Creating a new short link

1. Another module (for example, the homepage form handler) hands this module a request that says "create a short link for this destination URL, on behalf of this user."
2. The module packages the request in the exact format the cloud expects, including the secret API key.
3. It sends the request over the internet to the cloud.
4. The cloud either creates the link and replies with the new short code, or replies with an error.
5. The module checks the reply. On success, it tidies the answer into a clean package and returns it. On failure, it raises a named error (for example, "authentication failed" or "rate limit exceeded") so the caller can show a useful message.

### Journey B: Looking up an existing link

1. Another module hands over a short code and a user ID and asks "tell me about this link."
2. The module asks the cloud for that record.
3. If the link exists, the module returns the details. If it does not exist, it returns an empty answer (rather than an error) so the caller can decide what to do.

### Journey C: Listing a user's links for the dashboard

1. The dashboard asks for "page 2 of this user's links, sorted by most recently updated, including click counts."
2. The module checks that the page number and page size make sense before bothering the cloud.
3. It sends the request, including filters and search terms if any were supplied.
4. The cloud replies with a page of results plus information about how many pages exist in total.
5. The module returns a tidy paginated package the dashboard can render directly.

### Journey D: Finding an anonymous visitor's previous link

1. The plugin sends the visitor's browser fingerprint to the module.
2. The module asks the cloud "do you have any links tied to this fingerprint?"
3. The cloud returns any matches, along with click statistics. The module hands those back.

### Journey E: Making sure a WordPress user exists in the cloud

1. When a WordPress user first interacts with the plugin, another module asks this one to "make sure this WordPress user exists on the cloud side."
2. The module sends the WordPress user ID to the cloud. The cloud either creates a new account or returns the existing one.
3. The module returns the cloud's user record so the rest of the plugin can store the cloud-side ID.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| Main client (the dispatcher) | `includes/TrafficPortal/TrafficPortalApiClient.php` | The single class that owns every conversation with the cloud service. |
| Request and response packages | `includes/TrafficPortal/DTO/` | A small folder of "tidy package" definitions for create requests, create responses, list pages, fingerprint matches, and individual link records. |
| Named error types | `includes/TrafficPortal/Exception/` | Each kind of failure (authentication, validation, rate limit, network, page-not-found, generic API error) has its own dedicated error so callers can react precisely. |
| HTTP plumbing layer | `includes/TrafficPortal/Http/` | A thin wrapper around the underlying network calls, with a real implementation for production and a fake one used for automated tests. |
| Cloud API contract reference | `API_REFERENCE.md` | The human-readable cheat sheet describing every endpoint the cloud offers — used by developers when extending this module. |
| Project-level integration notes | `PROJECT_CONTEXT.md` | Higher-level notes about how the plugin connects to the cloud, including environment URLs and historical changes. |

---

## 8. External Connections

- **Traffic Portal API (cloud service)** — The only external system this module talks to. Hosted on Amazon Web Services in the Canada Central region. Used to create, fetch, list, search, and update short links, to record and read usage data, and to register WordPress users on the cloud side.
- **WordPress filesystem (debug log)** — The module writes a verbose timeline of every request to a debug log file inside the WordPress content directory, which helps engineers diagnose problems in production without exposing details to end users.

It does not talk to any database directly, does not talk to the browser, and does not talk to any other third-party service.

---

## 9. Configuration & Settings

The module is configured by the rest of the plugin when it is created. The two most important settings are:

- **API endpoint URL** — Where the cloud service lives. The default points to the development environment; an admin can switch this to a staging or production URL through the plugin's main settings screen.
- **API key** — The secret password sent with every request. Stored in the WordPress admin settings and never displayed in full to end users.
- **Request timeout** — How long, in seconds, to wait for the cloud to reply before giving up. Defaults to 30 seconds and can be tuned by developers if a slow network is causing problems.

There are no shortcode attributes or browser-side flags that affect this module — it is a pure backend service. Developers can also swap in a fake HTTP layer when running automated tests, but that is invisible to anyone using the plugin normally.

---

## 10. Failure Modes (What Can Go Wrong)

- **The API key is missing or wrong** -> The cloud refuses the request, the module raises an authentication error, and the caller can show "your account is not configured correctly."
- **The cloud rejects the data as invalid** (for example, a duplicate short code or a malformed URL) -> The module raises a validation error so the caller can highlight the bad field for the user.
- **Too many requests are sent in a short time** -> The cloud responds with a rate-limit error, which the module surfaces so the plugin can ask the user to slow down.
- **The internet connection drops or the cloud is slow to respond** -> The module raises a network error and the caller can offer "try again in a moment."
- **The cloud replies with something that is not valid JSON** -> The module raises a generic API error rather than crashing, protecting the WordPress site from a bad response.
- **The user asks for page 99 of their links but only has 3 pages** -> The module raises a "page not found" error so the dashboard can fall back to the last real page.
- **The looked-up short link does not exist** -> The module returns an empty answer (not an error) so the caller can show a clean "not found" view.
- **The cloud has an internal server error** -> The module raises a generic API error including the cloud's HTTP status code so engineers can investigate from the debug log.

---

## 11. Related Modules

- [Link Shortener Form](./01-link-shortener-form.md) — The public form module that calls this client to create new short links for visitors.
- [Personal Dashboard](./02-personal-dashboard.md) — The logged-in user view that calls this client to list, edit, and analyse a user's existing links.
- [Client Links Page](./03-client-links-page.md) — Bulk operations such as updating expiry dates and toggling active status rely on this client's endpoints.
- [Admin Settings](./05-admin-settings.md) — Owns the API endpoint URL and API key that this client reads at startup.
- [Usage Analytics Dashboard](./04-usage-analytics-dashboard.md) — Reads daily and per-link usage figures through this client.

---

## 12. Notes For The Curious

- The module was originally a single create-link endpoint and grew, one operation at a time, as new plugin features were added — it now covers create, fetch, list, update, bulk update, fingerprint search, user activity, and user provisioning.
- It writes a deeply detailed debug log on every call, including a masked version of the API key, the caller's IP address, and the full request and response bodies. That log has been invaluable for diagnosing production issues that are hard to reproduce.
- The cloud service originally required a per-user token alongside the API key. That requirement was removed, simplifying every call this module makes.
- The newer endpoints (paginated link lists, user activity) use a cleaner internal HTTP layer that can be swapped out for testing. The older endpoints still call the network directly. Migrating the older calls to the cleaner layer is a known cleanup task.
- The module deliberately uses one named error type per kind of failure rather than a single generic error. That decision lets every caller pick the right user-facing message without having to inspect status codes themselves.

---

_Document version: 1.0 — Last updated: 2026-04-26_

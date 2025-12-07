**Plan**

1) **Backend intro-mode support**
- `includes/class-tp-api-handler.php`: Add intro creation endpoint/path with 24h TTL, status `intro`, and activate immediately after validation. Support disabling current intro record when key/destination changes. Return structured payload (short_url, key, expiration, counters=0).
- `includes/class-tp-link-shortener.php`: Expose config helpers (intro TTL, domains) if needed.
- Collision handling: try alternate domain or suffix when AI code exists.

2) **AI keyword surfacing/regeneration**
- `includes/class-tp-api-handler.php`: Expose AJAX endpoints for AI keyword generation (Gemini) and collision-safe suggestion.
- `assets/js/frontend.js`: Replace lightbulb random generation with AJAX call to AI generator; wire suggestion into key field.

3) **IntroForm UX (anonymous)**
- `templates/shortcode-template.php` (or dedicated intro template): Add single destination field + paste icon initial state; add areas for validation messages, suggested key field (initially hidden), short link display, QR, thumbnail, countdown, counters, and “TRY IT NOW…” message.
- `assets/js/frontend.js`: On successful validation (URLValidator success), auto-submit intro create (AJAX) to build record, then reveal key/link/QR/thumbnail/countdown/counters. Add auto-add protocol on paste/typing and spam guard (debounce/length limits).
- Handle warnings: block temp redirects for guests; warn on permanent redirects with replacement suggestion.

4) **Key/destination edits with confirmation**
- `assets/js/frontend.js`: On key/destination change post-creation, prompt confirmation (“disable current link?”); on confirm, call backend to deactivate prior intro, then revalidate and recreate; update link/QR/thumbnail.

5) **Counters & QR interactions**
- `assets/js/frontend.js`: Hook link click and QR scan tracking (AJAX counter endpoint if available or at least local increments); show counters; show “SAVE…” button after first click/scan replaces “TRY…”.

6) **Returning visitor flow**
- `assets/js/frontend.js` + `window.TPStorageService`: Persist active intro data (key, destination, expiresAt). On load, revalidate with backend (new AJAX endpoint in `class-tp-api-handler.php`) to check status/expiry/availability and render appropriate state/messages.

7) **Validation & content-type rules**
- `assets/js/url-validator.js` + `frontend.js`: Enforce guest constraints (no temp redirects, protected links blocked, video/type block for guests), with border/message styling per spec. Ensure auto-submit after paste validation.

8) **Testing**
- PHP: Add unit coverage for new intro endpoints and AI fallback paths.
- JS: Add/extend tests around URLValidator rules (temp redirects blocked for guest), AI suggestion handler, returning visitor state transitions.

Order of execution: 1) backend intro endpoints & collision logic; 2) AI suggestion endpoint and wiring; 3) template/JS intro UI flow; 4) edit-confirm/disable flow; 5) counters/QR updates; 6) returning visitor revalidation; 7) validation rules refinements; 8) tests.

# Module: Short Code Generator

---

## 1. One-Line Summary

This module is the "name maker" — it produces the unique tag (like `abc123`) that becomes the tail end of every shortened link.

---

## 2. What It Does (Plain English)

Every short link the plugin produces looks something like `dev.trfc.link/abc123`. That last part — the `abc123` — has to be unique, easy to remember, and not already taken by another link. This module is the specialist that produces those tags.

Think of it as the printing press that prints the unique tags onto each new short link. Other parts of the plugin send it a long URL (for example, a recipe page or a YouTube video) and ask, "Give me a short, memorable tag for this." The module reaches out to a remote service that reads the page, picks meaningful words, checks that the resulting tag is not already in use, and hands back a clean, ready-to-use tag.

The people who use this module directly are not end users — they are other modules inside the plugin, mostly the parts that create new short links. The end user benefits indirectly: instead of getting a random string of gibberish, they get a tag that often relates to the content of the page they are shortening.

Success looks like a guaranteed-available, human-friendly tag returned in a fraction of a second, ready to be glued onto the domain to form the finished short link.

---

## 3. Why It Exists (The Business Reason)

Short links are only valuable if their tags are unique and easy to remember. Without this module, the plugin would have to either invent random-looking tags (hurting brand and recall) or write its own complex logic for fetching pages, extracting keywords, and avoiding collisions. Centralising that work in a dedicated service keeps the rest of the plugin simple and lets the team improve the tag-making logic in one place.

---

## 4. How It Fits Into The Bigger Picture

This module is a **backend service** that sits between the parts of the plugin that need a tag and the remote tag-generation API. It does not draw anything on the screen.

```
[Other module: "I need a tag for this URL"]
          │
          ▼
[Short Code Generator]  ───►  [Remote Tag-Making API]
          │                        (reads the page,
          │                         extracts keywords,
          │                         checks availability)
          ▼
[Other module: "Got my tag — building the full short link now"]
```

**Upstream callers** are typically the link-creation flows (the public form, the dashboard, the WordPress admin tools) — anywhere a new short link is being made.

**Downstream** is the Traffic Portal short-code generation API (a remote cloud service). This module is the only piece of the plugin that talks to that specific API.

---

## 5. Key Concepts (Glossary)

- **Short Code** (also called the "tag") — The unique part at the end of a short link, e.g. the `abc123` in `dev.trfc.link/abc123`.
- **Tier** — The "speed vs. quality" setting used when asking for a tag. Three tiers are available: Fast, Smart, and AI.
- **Generation Method** — The technique that actually produced the tag on the remote side. Three methods exist: rule-based keyword picking, NLP analysis, and a Gemini AI model.
- **Collision** — When the tag the system would have used is already taken by an existing link. The remote service automatically tries variations until it finds an available one.
- **Was Modified** — A flag returned with each tag that says "yes, your originally proposed tag was taken, so I tweaked it." Useful for telling the user that their tag is not the one the system first imagined.
- **Candidates** — A short list of runner-up tags the remote service considered. The plugin can show these as alternatives.

---

## 6. The Main User Journey

This module has only one journey because it is a service used by other modules.

1. Another module decides it needs a unique tag for a URL the user just supplied.
2. It hands this module the long URL plus, optionally, the domain the tag will live under (so the remote service can check availability against the right list).
3. This module picks a tier — Fast for instant results, Smart for slightly better quality, or AI for the most thoughtful tag — and sends a request to the remote tag-making service.
4. The remote service fetches the page, reads the title and key text, comes up with one or more candidate tags, and checks them against existing tags on that domain.
5. If the first choice is already taken, the remote service automatically tries variations (adding numbers on the end) until it finds a free one.
6. The remote service returns the chosen tag along with extra information: which method produced it, whether it had to be tweaked, and any runner-up candidates.
7. This module hands the result back to the calling module, which now knows exactly which tag to use when saving the new short link.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| Main client | `includes/ShortCode/GenerateShortCodeClient.php` | The entry point other modules call to ask for a tag. |
| Tier choices | `includes/ShortCode/GenerationTier.php` | Defines the three speed/quality tiers (Fast, Smart, AI). |
| Method labels | `includes/ShortCode/GenerationMethod.php` | Names the techniques the remote service can use to make a tag. |
| Request shape | `includes/ShortCode/DTO/GenerateShortCodeRequest.php` | Describes what gets sent to the remote service (URL plus optional domain). |
| Response shape | `includes/ShortCode/DTO/GenerateShortCodeResponse.php` | Describes what comes back (the tag plus extra information). |
| HTTP plumbing | `includes/ShortCode/Http/` | The low-level pipes that send the HTTPS request and read the reply. |
| Error types | `includes/ShortCode/Exception/` | The named errors used when something goes wrong (validation, network, API). |
| API reference | `GENERATE_SHORT_CODE_API.md` | Human-readable description of the remote API contract. |
| Bug history | `docs/BUG-shortcode-generation-failing.md` | Investigation notes on past failures of this service. |

---

## 8. External Connections

- **Traffic Portal Short Code API** (a cloud service, currently hosted on AWS in `ca-central-1`) — The remote service that does the actual work of reading the page, picking keywords, and reserving an available tag. This module sends an authenticated HTTPS request and parses the reply.
- **WordPress debug log file** — When the plugin is running inside WordPress, this module writes a step-by-step record of each request and response to the shared plugin debug log so developers can diagnose problems.

---

## 9. Configuration & Settings

- **API key** — The remote service requires an authentication key, which the plugin reads from a WordPress configuration constant (`API_KEY`). Without a valid key, every request is rejected.
- **Base URL** — The address of the remote service. It defaults to the development cloud endpoint but can be overridden when the module is created.
- **Timeout** — How long the module is willing to wait for the remote service before giving up. Defaults to 15 seconds.
- **Tier selection** — Each call can pick Fast, Smart, or AI. The default is AI when no choice is specified.

There is no WordPress admin screen for these options today; they are set in code or via configuration constants.

---

## 10. Failure Modes (What Can Go Wrong)

- **The API key is missing or wrong** → The remote service replies with a "Forbidden" message and no tag is produced. This was a real bug fixed in March 2026.
- **The user's URL is malformed** → The module refuses to send the request and reports a validation error before any network traffic happens.
- **The remote service is slow or unreachable** → The module gives up after the timeout and reports a network error so the calling module can decide whether to retry or show a message.
- **The remote service cannot find any available tag** → For a popular URL where many tags are already taken, the service may return "Could not find available short code." The calling module surfaces this as an error rather than silently substituting a random fallback.
- **The remote service replies with malformed data** → The module rejects the reply and reports an API error rather than passing junk back to the caller.

---

## 11. Related Modules

- API Handler — The main consumer of this module; whenever the plugin's AJAX endpoints need a tag, they delegate to the Short Code Generator. (See the corresponding doc once it exists.)
- Link Creation Flow — The public-facing form and dashboard create-link actions ultimately rely on this module to produce the tag that becomes part of the new short link.
- Logging / Debug Module — This module writes to the same shared debug log file that other parts of the plugin use, so its activity shows up alongside everything else when developers investigate issues.

---

## 12. Notes For The Curious

- The three tiers exist because each comes with a real cost and speed tradeoff: Fast is essentially free and finishes in about half a second, Smart costs a bit and uses Amazon's NLP service, and AI uses Google Gemini and produces the most creative tags but takes several seconds.
- Even when the remote service is the one doing the heavy lifting, this module still validates the URL locally first — that way an obviously broken request never wastes a network call.
- The "was modified" flag exists so the user interface can say something honest like "Your tag was taken — we used `chocolatecake2` instead." This was a deliberate design choice to avoid silent surprises.
- A historical bug had this module silently falling back to a random tag whenever the remote service failed. That fallback was removed so failures are now visible rather than hidden.
- The AI tier is wired up in the backend but, as of the last review, the frontend does not yet call it directly — a known follow-up for a future release.

---

_Document version: 1.0 — Last updated: 2026-04-26_

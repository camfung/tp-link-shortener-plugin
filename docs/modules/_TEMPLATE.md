# Module: {{Module Name}}

> **Documentation Template** — This file is the canonical layout every module document MUST follow. Fill every section. If a section truly does not apply to a module, keep the heading and write a short sentence explaining why.
>
> **Audience:** Non-technical, high-level thinker. Big-picture first. Explain like you're walking a smart executive through the system. Avoid jargon; when a technical term is unavoidable, define it the first time you use it. Use analogies. Keep paragraphs short.

---

## 1. One-Line Summary

A single sentence (under 25 words) that captures what this module does. If a stakeholder reads only this sentence, they should still understand the module's purpose.

> _Example: "This module is the public-facing form where visitors paste a long URL and get a short link plus a QR code."_

---

## 2. What It Does (Plain English)

Two to four short paragraphs answering:

- **What problem does this module solve?** Frame it from the user/business angle, not the code angle.
- **Who uses it?** End users? Admins? Other modules inside the plugin?
- **What does success look like?** What does a person walk away with after using this module?

No code in this section. No file paths. Just the story.

---

## 3. Why It Exists (The Business Reason)

One short paragraph. Why does this module exist instead of the project doing without it? What would break or be missing if we deleted it tomorrow?

This grounds the technical work in business value.

---

## 4. How It Fits Into The Bigger Picture

Describe this module's place in the overall plugin. Use one of these framings:

- **Upstream / Downstream** — What feeds INTO this module? What does it feed OUT to?
- **Layer** — Is this a frontend (what the user sees), backend (server logic), or integration (talking to outside services) module?
- **Dependency** — Which other modules does this one rely on? Which other modules rely on it?

Include a simple **text diagram** if it helps. Keep it readable in plain text:

```
[User's Browser]
      │
      ▼
[This Module]  ────►  [Other Module]
      │
      ▼
[External API]
```

Mermaid diagrams are fine if they render in the user's tooling, but a plain ASCII-style diagram is preferred for accessibility.

---

## 5. Key Concepts (Glossary)

A short bulleted list of 3–8 terms a reader needs to understand this module. Define each in one sentence, in plain language.

- **Term 1** — Plain-English definition.
- **Term 2** — Plain-English definition.

If a term has a technical name AND a friendly name, give both: _"**Shortcode** (also called a 'plugin tag') — a small piece of text like `[tp_link_shortener]` you paste into a WordPress page to make this module appear there."_

---

## 6. The Main User Journey

Walk through what happens, step by step, in plain English. Number the steps. No code. No function names.

1. The user does X.
2. Behind the scenes, the system does Y.
3. The user sees Z.
4. ...

If the module has multiple distinct journeys (e.g., "logged-in user" vs "anonymous visitor"), give each its own short numbered list under a sub-heading.

---

## 7. Where It Lives In The Code

A short table mapping conceptual pieces to actual files. The non-technical reader does not need to read these files — but pointing them out lets them ask developers about specific parts.

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| Entry point | `path/to/file.php` | Brief description |
| Frontend logic | `assets/js/something.js` | Brief description |
| Template | `templates/something-template.php` | Brief description |
| Styles | `assets/css/something.css` | Brief description |

Use **relative paths** from the plugin root.

---

## 8. External Connections

Does this module talk to anything outside the WordPress site? List each external system in one bullet:

- **Traffic Portal API** — Used to actually create short links.
- **Browser localStorage** — Stores the user's recent links.
- **(none)** — If the module is fully self-contained, say so.

---

## 9. Configuration & Settings

What knobs and dials exist? Where does an admin or developer change the behavior?

- **WordPress admin settings** — _top-level "Link Shortener" admin menu → ..._
- **`wp-config.php` constants** — `define('API_KEY', '...')`
- **localStorage flags** — `tpDebug:foo`, etc.
- **Shortcode attributes** — `[tp_link_shortener domain="..."]`

If the module has no configuration, say "This module has no user-configurable options."

---

## 10. Failure Modes (What Can Go Wrong)

A short bulleted list. For each failure, write one sentence in plain English.

- **The API key is missing or wrong** → The user sees an error message and no short link is created.
- **The user pastes an invalid URL** → The form refuses to submit and shows a hint.
- **(...)**

This section helps the reader build intuition about robustness without reading any code.

---

## 11. Related Modules

A bulleted list of other modules in this plugin that are tightly related. Link to their doc files using relative paths.

- [Module Name](./module-name.md) — One sentence on the relationship.
- [Module Name](./module-name.md) — One sentence on the relationship.

---

## 12. Notes For The Curious

Optional. Two to five bullets of "interesting things to know" — quirks, history, design decisions, future plans. Anything that helps a reader build a mental model. Keep it light.

- This module was originally built in Milestone 3.
- It uses a CDN-hosted JavaScript library for QR codes to keep the plugin lightweight.
- Future plan: replace the placeholder premium check with a real membership integration.

---

_Document version: 1.0 — Last updated: YYYY-MM-DD_

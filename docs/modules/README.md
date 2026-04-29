# Project Module Map

This folder contains a high-level, non-technical guide to every major module in the Traffic Portal Link Shortener plugin. Each module document follows the same structure (defined in [`_TEMPLATE.md`](./_TEMPLATE.md)) so you can compare them side-by-side and build a mental model of the system without reading any code.

## How to Read This

- **Start at the top.** The modules are ordered from most user-visible to most behind-the-scenes.
- **Every doc has the same 12 sections** — One-Line Summary, What It Does, Why It Exists, How It Fits, Key Concepts, Main User Journey, Where It Lives, External Connections, Configuration, Failure Modes, Related Modules, Notes For The Curious.
- **No code required.** These are written for big-picture thinkers, not developers.

## The Modules

### 1. What Users See

| # | Module | What it does in one line |
|---|---|---|
| 01 | [Link Shortener Form](./01-link-shortener-form.md) | The public form where a visitor pastes a long URL and gets a short link + QR code. |
| 02 | [Personal Dashboard](./02-personal-dashboard.md) | The logged-in user's home base for managing their own short links. |
| 03 | [Client Links Page](./03-client-links-page.md) | A public, branded list of links for a particular client. |
| 04 | [Usage Analytics Dashboard](./04-usage-analytics-dashboard.md) | Shows how links are performing and how the wallet is being spent. |

### 2. What Admins See

| # | Module | What it does in one line |
|---|---|---|
| 05 | [Admin Settings Panel](./05-admin-settings.md) | The WordPress admin screen for configuring the plugin. |

### 3. The Engines (Behind The Scenes)

| # | Module | What it does in one line |
|---|---|---|
| 06 | [Traffic Portal API Client](./06-traffic-portal-api-client.md) | The messenger that creates, fetches, and updates short links in the cloud. |
| 07 | [Short Code Generator](./07-short-code-generator.md) | The service that mints unique short codes (the "abc123" part of a URL). |
| 08 | [WooWallet Integration](./08-woowallet-integration.md) | Connects the plugin to WooCommerce's wallet for top-ups and payments. |
| 09 | [TerrWallet Integration](./09-terrwallet-integration.md) | The internal ledger that tracks every credit, debit, and balance. |
| 10 | [SnapCapture Integration](./10-snapcapture-integration.md) | The screenshot service that captures previews of destination pages. |

### 4. The Plumbing

| # | Module | What it does in one line |
|---|---|---|
| 11 | [Plugin Foundation](./11-plugin-foundation.md) | The wiring and entry points that hold everything else together. |
| 12 | [Testing Infrastructure](./12-testing-infrastructure.md) | The automated safety net that catches bugs before users do. |

## The Template

If you ever want to add a new module doc — or check that an existing one is complete — see [`_TEMPLATE.md`](./_TEMPLATE.md). Every module file must follow it section-for-section.

---

_Last updated: 2026-04-26_

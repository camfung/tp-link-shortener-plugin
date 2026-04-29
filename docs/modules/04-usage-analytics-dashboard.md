# Module: Usage Analytics Dashboard

> **Audience:** Non-technical, high-level thinker. Big-picture first.

---

## 1. One-Line Summary

This module is the customer's "account dashboard" — it shows how their short links are performing and how much they have spent or topped up over time.

---

## 2. What It Does (Plain English)

When a customer signs in and visits their dashboard, they want to answer three questions: _"How many people are using my links?"_, _"What is this costing me?"_, and _"How much money do I have left?"_ This module answers all three in one place.

The page shows a chart at the top (a visual line of daily traffic), a strip of summary cards (current wallet balance, total hits, total cost), and a detailed table below it. Each row of the table is a single day, with columns for hits, money spent that day (Debited), money added that day (Credited), and the running wallet balance.

Customers also use this page to top up their account. A big "Add Funds" button opens a wallet pop-up where they pick a preset amount (like $5, $10, $25) or type a custom amount, and they are taken to checkout. The same pop-up shows their recent wallet history.

A successful visit looks like this: the customer lands on the page, sees their numbers update from a brief loading skeleton to real data, browses or filters by date range, and either walks away informed or clicks Add Funds to refill.

---

## 3. Why It Exists (The Business Reason)

Without this dashboard, customers would have no way to see what they are paying for. They would not trust the platform, would not know when to top up, and would have to email support to get any of this information. This page is the single screen that turns a black-box billing system into a transparent, self-service product.

---

## 4. How It Fits Into The Bigger Picture

This is a **frontend dashboard module**. It is the place where many other parts of the plugin come together to be presented to the customer. It does not _create_ data — it only reads, merges, and displays.

```
   [Traffic Portal API]            [Wallet / Billing Service]
   (clicks & QR scans)             (top-ups, charges, balance)
            \                                /
             \                              /
              \                            /
               ▼                          ▼
        ┌───────────────────────────────────────┐
        │      Usage Analytics Dashboard         │
        │  (chart + summary cards + table)       │
        └───────────────────────────────────────┘
                          │
                          ▼
                 [The Customer's Browser]
```

- **Upstream (data feeds in):** the Traffic Portal usage data (clicks and scans per day) and the wallet/billing system (top-ups and balance).
- **Downstream (where data goes):** the customer's screen — and the wallet pop-up routes them onward to a checkout page when they choose to add funds.
- **Tightly related:** it sits right next to the Client Links dashboard. They share visual language and customers often jump between them.

---

## 5. Key Concepts (Glossary)

- **Hit** — A single use of a short link. Hits are split into two kinds: **clicks** (someone tapped the link) and **QR scans** (someone scanned the printed code).
- **Debited / Credited** — Plain accounting words. _Debited_ means money taken out of the wallet (the cost of hits that day). _Credited_ means money put in (a top-up that day).
- **Balance** — The customer's current wallet balance. Always shown from the live wallet, never reconstructed from daily rows, so it is always trusted.
- **Top-up** — When a customer adds money to their wallet. Top-ups appear in the Credited column on the day they happened.
- **Date Range** — The window of days the dashboard is currently showing. Defaults to the last 30 days; the customer can pick 7, 30, 90, or a custom range.
- **Skeleton** — The faint grey shimmering placeholder shown while real data is loading. It keeps the page from looking empty or broken during the wait.
- **Merge Adapter** — The piece of logic that combines two different data sources (daily traffic and wallet activity) into a single tidy daily list. Think of it as a librarian who interleaves two different ledgers by date.

---

## 6. The Main User Journey

1. The customer signs in and lands on the dashboard page. (If they are not signed in, they are redirected to the login page.)
2. The page shows a placeholder skeleton while it works in the background.
3. The system asks the Traffic Portal for the customer's daily hit numbers and asks the wallet system for any top-ups in the same period.
4. The merge adapter stitches those two streams together, day by day. A day with only traffic, a day with only a top-up, or a day with both — they all become one row.
5. The skeleton fades out and the real dashboard appears: chart on top, three summary cards under it, then the daily table.
6. The customer can change the date range using the preset buttons (7d, 30d, 90d) or pick custom dates. Each change reloads the data.
7. They can sort the table by date, hits, or debited amount.
8. If they want to top up, they click **Add Funds**. A wallet pop-up appears with their balance, their recent transaction history, preset amounts, and an option to enter a custom amount.
9. They pick an amount and click Add Funds inside the pop-up. The system creates a checkout session and either takes them to checkout or shows a status message while it processes.
10. After they top up, the new credit shows up on the appropriate day in the table the next time the page is loaded.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| Entry point (shortcode) | `includes/class-tp-usage-dashboard-shortcode.php` | Registers the page tag, gates non-logged-in users, and loads the page assets. |
| Page layout | `templates/usage-dashboard-template.php` | The HTML skeleton, the chart canvas, the table, and the wallet pop-up. |
| Browser logic | `assets/js/usage-dashboard.js` | Fetches data, draws the chart, renders the table, runs sorting, pagination, and the wallet pop-up. |
| Visual styling | `assets/css/usage-dashboard.css` | All the colours, spacing, skeleton animations, mobile layout, and pop-up styling. |
| Data merge logic | `includes/TerrWallet/UsageMergeAdapter.php` | Joins the daily traffic data with wallet top-ups into one unified daily list. |
| Backend AJAX handlers | `includes/class-tp-api-handler.php` | Server-side endpoints that the page calls for usage data, wallet balance, transactions, and top-up checkout. |

---

## 8. External Connections

- **Traffic Portal API** — Where the daily hit counts (clicks and QR scans) come from.
- **Wallet / billing service** — Where the live balance, transaction history, and top-up checkout sessions come from.
- **Stripe (indirectly, via the wallet service)** — The customer is sent to a Stripe-hosted checkout page when they confirm a top-up.
- **Chart.js (CDN)** — A small open-source charting library used to draw the line chart.
- **Bootstrap and Font Awesome (CDN)** — Visual building blocks (buttons, modal pop-up, icons).

---

## 9. Configuration & Settings

This module is mostly self-tuning. The only knobs are:

- **Shortcode attribute `days`** — When an admin places the dashboard on a page, they can set the default lookback window (e.g. show 7 days instead of 30 by default).
- **Date range presets in the page** — The customer can switch between 7, 30, 90, or a custom range. This is a runtime choice, not a saved setting.
- **Wallet preset amounts** — The $5 / $10 / $25 buttons in the top-up pop-up are defined in the page template; an admin would edit the template to change them.

There are no WordPress admin settings unique to this dashboard — it inherits the plugin's overall API key and wallet configuration.

---

## 10. Failure Modes (What Can Go Wrong)

- **The customer is not logged in** → They are bounced to the login page before the dashboard ever loads.
- **The Traffic Portal API is down or slow** → The skeleton stays visible, then an error card appears with a Retry button.
- **The wallet service is unreachable** → Hit data may still load, but the balance card shows "--" and the Credited column shows no top-ups.
- **There is no data for the selected range** → The table is replaced with a friendly empty state ("No usage data") instead of an empty grid.
- **A day has only a top-up and no traffic** → The merge adapter still creates a row for that day, marked as a top-up row, so customers can see when they added funds.
- **Floating-point drift in money math** → Avoided by always rounding to whole cents before display, so totals never end in odd half-pennies.
- **The customer hits Apply with bad custom dates** → The date inputs prevent picking a future end date, so the most common bad input is silently blocked.
- **Checkout takes too long** → A "Taking too long? Continue to checkout." link appears as a fallback.

---

## 11. Related Modules

- [Client Links Page](./03-client-links-page.md) — The sister page where customers see and manage their actual short links. The two pages share styling and customers often switch between them.
- [Link Shortener Form](./01-link-shortener-form.md) — Where new short links are created. Every link that appears in this dashboard's hit counts originated there.
- [WooWallet Integration](./08-woowallet-integration.md) and [TerrWallet Integration](./09-terrwallet-integration.md) — Together own the balance, the transaction history, and the top-up checkout flow that this dashboard surfaces.

---

## 12. Notes For The Curious

- The page deliberately uses a three-state pattern: **skeleton, error, content**. Only one of those three is on screen at any moment — this keeps the page from looking half-broken during slow networks.
- The balance shown in the summary card always comes from the live wallet, never recomputed from row data. That way an off-by-one in daily math can never make the customer's balance look wrong.
- The merge adapter is intentionally a "pure" piece of logic — it has no database, no network, no WordPress dependencies. That makes it easy to test and easy to reason about in isolation.
- Everything is in UTC under the hood to keep dates consistent across timezones, then formatted for the customer's locale at the last moment.
- The wallet pop-up is part of this module, not a separate one. It was put here because the most common reason to open the wallet is right after looking at the usage table.
- The Credited column was added recently to make top-ups visible in the same place as charges, instead of forcing customers to dig into a separate billing page.

---

_Document version: 1.0 — Last updated: 2026-04-26_

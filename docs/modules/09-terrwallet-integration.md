# Module: TerrWallet Integration

> **Audience:** Non-technical, high-level thinker. Big-picture first.

---

## 1. One-Line Summary

This module is the bridge to the user's wallet ledger — it reads the credits a user has been given and feeds them into the usage history shown on the dashboard.

---

## 2. What It Does (Plain English)

Every user of the plugin has a wallet — a small internal account that tracks how much credit they have, how much they have spent, and what they have been topped up with over time. Think of it like a prepaid coffee card: every time you visit, a few cents come off the card, and every time you reload it, the balance goes up.

This module is the part of the plugin that talks to that wallet. It does not run the wallet itself — that is handled by a separate system called TerrWallet. Instead, this module's job is to politely ask the wallet, "Please give me a list of all the credits and top-ups for this user between these two dates," and then translate the answer into a clean, predictable shape the rest of the plugin can use.

It also stitches that wallet information together with the user's link-usage history. If the user spent two cents on Monday and was credited ten cents on Tuesday, this module is what makes sure both events end up on the right rows of the dashboard table, on the right dates.

Success looks like a user opening their dashboard and seeing an honest, complete picture of their account: every charge, every top-up, and a running balance that adds up.

---

## 3. Why It Exists (The Business Reason)

Without this module, the dashboard could only show one half of the financial story — it could tell users how much they spent on short links, but it could not show top-ups, refunds, or any other credits. Users would see costs draining their balance with no visible source of replenishment, which would feel broken and untrustworthy. This module exists to make the money story complete.

---

## 4. How It Fits Into The Bigger Picture

This module is an **integration layer**. It sits between the outside wallet system and the plugin's own usage dashboard. It pulls data in, shapes it, and hands it off — it does not show anything to the user directly.

```
[Wallet System (WooCommerce Wallet plugin)]
            │
            │   (credits, top-ups, dates)
            ▼
[TerrWallet Integration]  ◄── this module
            │
            │   (clean, merged daily records)
            ▼
[Usage Dashboard]
            │
            ▼
     [What the user sees]
```

It depends on the **WooCommerce Wallet plugin** (`woo-wallet`, referred to internally as "TerrWallet") being installed on the WordPress site, or — as a fallback — on the WooCommerce Wallet REST API being available. It is depended on by the **Usage Dashboard** module, which uses its output to render the table of daily charges and credits.

---

## 5. Key Concepts (Glossary)

- **Wallet** — The user's running balance account. Holds the money they have available to spend on short-link usage.
- **Credit transaction** — A positive event on the wallet: a top-up, refund, promotional bonus, or any other money-in. This module only fetches credit transactions, not debits.
- **Debit** (also called "spend" or "charge") — A negative event on the wallet: money taken out to pay for usage. Debits are tracked separately by the usage system and merged in alongside credits.
- **Running balance** — The dollar amount left in the wallet at the end of each day, after all credits and debits for that day are applied.
- **Daily record** — One row in the dashboard table, representing everything that happened on a single calendar day (hits, costs, top-ups, ending balance).
- **Direct call vs. fallback API** — Two ways this module can reach the wallet. Direct call is fast and used during normal page loads. The fallback is a slower web-style request used when the fast path is unavailable (for example, during a scheduled background job).
- **Merge** — The act of joining two lists by date: usage events on one side, wallet credits on the other, lined up day by day so the dashboard can show one unified table.

---

## 6. The Main User Journey

The user does not interact with this module directly — they only see its results on the usage dashboard. Here is what happens behind the scenes when a user opens that dashboard:

1. The user opens their usage dashboard and picks a date range (for example, "the last 30 days").
2. The dashboard asks this module for all the wallet credits in that date range.
3. This module first tries the fast direct path: it asks the WooCommerce Wallet plugin in-process for the user's credit transactions.
4. If the fast path is not available (rare, usually during a background job), it falls back to a slower secure web request to the WooCommerce wallet API.
5. Either way, the raw answer comes back as a messy list of records with extra fields, formatting quirks, and dates as long timestamps.
6. The module cleans each record up: extracts just the calendar date, the amount, the description, and an ID, and discards the rest.
7. The module then merges this tidy list of credits with the usage events the dashboard already had. Days that appear in both lists are combined into one row. Days that only have credits get a row with zero usage. Days with multiple credits get them rolled together.
8. The dashboard receives the merged list and shows it to the user as a clean daily table with both costs and top-ups visible side by side.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| Main client | `includes/TerrWallet/TerrWalletClient.php` | Fetches the user's credit transactions, choosing the fast direct path or the slower API fallback. |
| Merge adapter | `includes/TerrWallet/UsageMergeAdapter.php` | Joins usage records and wallet credits together by date into one unified daily list. |
| Transaction shape | `includes/TerrWallet/DTO/WalletTransaction.php` | Describes the clean shape of a single credit transaction (date, amount, description, ID). |
| Error types | `includes/TerrWallet/Exception/` | Different categories of failure (wallet not installed, API error, generic error). |
| Bug investigation notes | _internal — available on request_ | Background reading on a known issue with how the running balance is calculated. |

---

## 8. External Connections

- **WooCommerce Wallet plugin (`woo-wallet`, internally codenamed "TerrWallet")** — The other WordPress plugin that actually owns and manages the wallet ledger. This module reads from it.
- **WooCommerce Wallet REST API** — A secondary, web-based way to reach the same wallet data. Used as a fallback when the direct path is not available.
- **WordPress user system** — Used to look up a user's email address, which the fallback API needs to identify the user.

---

## 9. Configuration & Settings

This module has minimal configuration, but two sensitive credentials must be present for the fallback path to work:

- **`wp-config.php` constants** — `TP_WC_CONSUMER_KEY` and `TP_WC_CONSUMER_SECRET`. These are the WooCommerce API credentials that authorize the fallback web request to the wallet. Without them, the fallback path cannot run; the direct path still works without them.
- **WooCommerce Wallet plugin install** — The `woo-wallet` plugin must be installed and active on the same WordPress site for the fast direct path to work.
- **WordPress admin settings** — None. This module has no UI of its own.

---

## 10. Failure Modes (What Can Go Wrong)

- **The WooCommerce Wallet plugin is not installed and no API credentials are configured** → The module raises a "wallet not installed" error and the dashboard cannot show top-up information.
- **The fallback API credentials are wrong or expired** → The module raises an API error and the dashboard cannot show top-ups.
- **A user ID cannot be matched to an email address** → The fallback path raises a generic error because the wallet API needs an email to look the user up.
- **A credit transaction lands on a different calendar day than the matching usage event** → The merge step creates two separate rows for what should be one day, and the dashboard shows the credit on a slightly off date.
- **The running balance shows the wrong intermediate values** → A known issue tracked internally; the math currently double-flips the sign of the daily cost, which causes the per-row balance to drift even though the final balance still looks roughly correct.
- **The user has no wallet activity in the date range** → No error; the module simply returns an empty list and the dashboard shows usage rows only.

---

## 11. Related Modules

- [Usage Analytics Dashboard](./04-usage-analytics-dashboard.md) — The dashboard that displays this module's merged daily records to the user.
- [Traffic Portal API Client](./06-traffic-portal-api-client.md) — Provides the usage side of the data (hits and costs) that gets merged with wallet credits.
- [WooWallet Integration](./08-woowallet-integration.md) — A sibling integration that handles the spend/debit side of the wallet (this module only handles credits).

---

## 12. Notes For The Curious

- This module only reads **credit** transactions from the wallet, not debits. Debits (the cost of using a short link) come from a different source — the Traffic Portal usage API — and are joined in by the merge step.
- The two-path design (fast direct call, slower API fallback) exists because some background jobs in WordPress run in a stripped-down environment where the direct call is not available. Rather than fail those jobs, the module quietly switches paths.
- The merge step is intentionally "stateless" — it has no database, no caching, no side effects. You can hand it the same input twice and get the same output. This makes it easy to test and reason about.
- Dates are deliberately reduced to just `YYYY-MM-DD` (no time of day) before merging. This keeps a top-up at 11:59 PM and a usage charge at 12:01 AM the next morning from confusingly landing on the same row.
- A subtle bug currently affects how the daily running balance is computed — tracked in our internal investigation notes. The bug does not change the totals, only the per-row intermediate balance, so most users never notice.

---

_Document version: 1.0 — Last updated: 2026-04-26_

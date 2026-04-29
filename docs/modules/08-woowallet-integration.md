# Module: WooWallet Integration

> **Audience:** Non-technical, high-level thinker. This module is the "money plumbing" of the plugin — it lets users top up a digital wallet through the website's online store and then quietly spends from that wallet when they create short links.

---

## 1. One-Line Summary

This module is the cashier and ledger that connects the online store's wallet system to the link shortener, so users can pay once and spend balance on short links over time.

---

## 2. What It Does (Plain English)

Think of this module as a **prepaid card system** sitting inside the website. Users add money to their card through the normal online store checkout — paying with Stripe, a credit card, or whatever the store accepts. From that point on, the plugin can quietly take small amounts off the card whenever the user does something that costs money, like generating a short link or unlocking a premium feature.

The module is the trusted middleman between two worlds: the online store (which already knows how to handle payments) and the link shortener (which just wants to know "does this user have enough money, and can I deduct a small charge?"). It speaks to the store's wallet system on the plugin's behalf, asks questions like "what's this user's balance?", and gives instructions like "please credit five dollars to this user."

End users do not interact with this module directly. They see a "Top Up Wallet" button in their dashboard, they pay through a normal-looking checkout page, and afterwards their balance simply goes up. Behind the curtain, this module is the part doing the asking and the bookkeeping.

Success means a user can fund their account once, then create dozens of links without ever being interrupted by a payment screen — the wallet just gets debited automatically.

---

## 3. Why It Exists (The Business Reason)

Charging a user a few cents every time they create a short link would be impractical — credit-card fees alone would eat the revenue, and users hate frequent payment prompts. A wallet system lets users pay in larger lump sums (say, twenty dollars at a time), then enjoy a friction-free experience. If we removed this module tomorrow, the plugin would have no way to charge for anything, and the entire premium / paid-link model would collapse.

---

## 4. How It Fits Into The Bigger Picture

This module is an **integration layer** — it sits between the WordPress site (and its WooCommerce store) and the rest of the link shortener plugin. It is not something the user sees; it is something the dashboard and link-creation flows quietly call into.

```
[User Dashboard]                          [Online Store Checkout]
       │                                          │
       │ asks "what's my balance?"                │ user pays via
       │ asks "show my transactions"              │ Stripe / card
       ▼                                          ▼
[WooWallet Integration Module]  ◄──────  [WooCommerce Wallet]
       │
       │ debits a few cents
       ▼
[Link Creation Flow]
```

- **Upstream (what feeds in):** the user's purchase activity from the WooCommerce store, plus the user's email address from WordPress.
- **Downstream (what flows out):** balance figures and transaction history shown on the user's usage dashboard, and small charges deducted whenever a paid action happens.
- **Depends on:** WooCommerce being installed on the site, plus a third-party WooCommerce Wallet plugin that exposes a wallet API.
- **Used by:** the usage dashboard module (to display balance), the link generation flow (to deduct cost), and any future premium features.

---

## 5. Key Concepts (Glossary)

- **Wallet** — A virtual prepaid balance held against the user's email address. Like a Starbucks card, but for the website.
- **Top-Up** — The act of adding money to the wallet, done through the normal online store checkout.
- **Credit / Debit** — Adding money to the wallet (credit) or taking money out (debit). These are the two basic operations the module performs.
- **Transaction** — A single entry in the wallet's history. Every top-up and every charge is a transaction.
- **Balance** — The current amount of money available in the wallet right now.
- **WooCommerce** — The online store software that lives alongside WordPress. It handles real-world payments.
- **WooCommerce Wallet** — A separate add-on for WooCommerce that turns it into a wallet system. This module talks to that add-on.
- **REST API** — The doorway through which this module sends questions and instructions to the wallet add-on. Think of it as a phone line between the plugin and the store.

---

## 6. The Main User Journey

### Journey A: A user tops up their wallet

1. The user opens their dashboard and clicks "Top Up Wallet."
2. They are taken to the website's normal checkout page, with a wallet top-up product already in their cart.
3. They pay with Stripe or a credit card, exactly like buying any other product.
4. The online store records the payment and credits the user's wallet automatically.
5. Back on the dashboard, when the page reloads, this module asks the store, "What is this user's wallet balance now?"
6. The new balance is displayed to the user.

### Journey B: A user creates a paid short link

1. The user fills out the link form and clicks "Register" to create a short link.
2. Before creating the link, the plugin asks this module, "Does this user have enough balance?"
3. This module checks with the store and reports back yes or no.
4. If yes, the link is created, and this module debits a small amount from the wallet, leaving a note like "short link generation."
5. The user sees their short link and a slightly reduced balance.

### Journey C: A user reviews their wallet history

1. The user opens the usage dashboard and clicks the wallet history icon.
2. The dashboard asks this module for the user's full transaction history.
3. This module fetches every credit and debit, page by page, from the store.
4. The dashboard displays each transaction in a table — top-ups, charges, dates, and amounts.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| The cashier (main entry point) | `includes/WooWallet/WooWalletClient.php` | Talks to the online store's wallet system and exposes simple actions like "get balance," "credit," and "debit." |
| Balance value object | `includes/WooWallet/DTO/WalletBalance.php` | A small data carrier holding a user's email and their current balance. |
| Transaction value object | `includes/WooWallet/DTO/WalletTransaction.php` | A small data carrier representing one entry in the wallet history (date, amount, type, note). |
| Network plumbing | `includes/WooWallet/Http/` | The behind-the-scenes wiring that makes secure calls over the internet to the store, plus a fake version used during testing. |
| Error types | `includes/WooWallet/Exception/` | A set of named error categories (authentication, validation, network, generic API) so other parts of the plugin can react appropriately when something goes wrong. |

---

## 8. External Connections

- **WooCommerce Wallet REST API** — The third-party WooCommerce add-on that actually stores wallet balances and transaction history. This module's entire job is to communicate with it.
- **WooCommerce Checkout (indirectly)** — When a user tops up, they pay through the store's checkout. This module does not handle that payment itself — it only sees the resulting balance change.
- **Plugin debug log file** — The module writes a step-by-step trail of every wallet conversation to a debug log so developers can troubleshoot when something looks wrong.

---

## 9. Configuration & Settings

This module needs three pieces of information to do its job:

- **Site URL** — The address of the WordPress site that hosts the wallet (provided when the plugin starts up).
- **Consumer Key & Consumer Secret** — A username-and-password pair that proves the plugin is allowed to talk to the wallet system. These are issued by WooCommerce's REST API settings and stored as configuration values in the plugin.
- **Request Timeout** — How long the module is willing to wait for an answer from the store before giving up (defaults to thirty seconds).

There are no end-user-facing options for this module. An administrator sets the credentials once during plugin setup and forgets about them.

---

## 10. Failure Modes (What Can Go Wrong)

- **The credentials are wrong or missing** → The store rejects the request and this module reports an authentication error; balance and history will not load on the dashboard.
- **The user's email is not recognised by the wallet system** → The module reports a validation error and the dashboard shows zero balance for that user.
- **The store is down or the network is flaky** → The module reports a network error; the user sees a friendly message and can retry.
- **The wallet add-on returns an unexpected response** → The module flags it as a generic API error and logs the details so a developer can investigate.
- **The user tries to create a paid link with no balance** → The module successfully reports the zero balance, and the link creation flow politely refuses and prompts the user to top up.
- **The store returns an enormous transaction list** → The module automatically pages through results in chunks of one hundred so nothing gets lost or times out.

---

## 11. Related Modules

- [Usage Analytics Dashboard](./04-usage-analytics-dashboard.md) — The main consumer of this module; it displays balance and transaction history to the user.
- [Link Shortener Form](./01-link-shortener-form.md) — Calls this module to debit a small amount whenever a paid short link is generated.
- [Admin Settings](./05-admin-settings.md) — Where an administrator stores the WooCommerce REST API credentials this module relies on.

---

## 12. Notes For The Curious

- The module is built so the wallet system can be swapped out later. The "network plumbing" sits behind a tidy doorway, which means a developer could in theory point this at a different wallet provider without rewriting the rest of the plugin.
- During automated testing, a fake version of the network plumbing is used so tests never actually reach out to a real store. This keeps the test suite fast and reliable.
- The module logs every conversation with the store — but it carefully scrubs the secret password out of the log line before writing it, so credentials never end up in a debug file.
- Both balance and transaction objects are **immutable** — once created they cannot be modified. This is a deliberate safety choice that makes accidental mistakes much harder.
- Future plan: surface a "low balance" notification to the user before they hit zero, so top-ups feel proactive rather than reactive.

---

_Document version: 1.0 — Last updated: 2026-04-26_

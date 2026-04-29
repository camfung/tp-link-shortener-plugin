# Module: Testing Infrastructure

---

## 1. One-Line Summary

This module is the plugin's automated quality team — a panel of robotic inspectors that check the plugin from every angle before it ever reaches a real user.

---

## 2. What It Does (Plain English)

Software changes constantly. Someone edits one corner of the plugin to fix a small bug, and without a safety net there is no easy way to know whether something on the other side of the plugin quietly broke. This module is that safety net.

Think of it as three teams of inspectors working together. The first team checks tiny pieces in isolation — does this single calculation give the right answer, does this one helper still behave when given strange inputs. The second team checks how those pieces talk to the real outside world, like the cloud service that creates short links. The third team is the most thorough: it actually opens a web browser, logs in as a real user, clicks buttons, fills in forms, and confirms the whole experience still works end-to-end.

The people who use this module are the developers and the project owner. End users never see it directly, but they benefit from it every single day — fewer bugs slip through, fewer outages happen, and reported issues get a permanent watchdog so they don't come back.

Success looks like this: every meaningful change to the plugin can be checked against hundreds of automated scenarios in minutes. If something breaks, the test suite shouts about it before the code is shipped. If a user reports a bug, a new inspector is hired who watches that exact bug forever, so it can never quietly return.

---

## 3. Why It Exists (The Business Reason)

Without automated tests, every release becomes a roll of the dice. Bugs that were already fixed creep back in, the cloud integration silently breaks when the contract shifts, and the only way to find out is when a paying customer complains. This module replaces "we hope it still works" with "we've already checked, twice, in a browser."

---

## 4. How It Fits Into The Bigger Picture

The testing infrastructure is a parallel universe that mirrors the real plugin. It does not run on the live site. It runs on a developer's laptop, or on a development environment, on demand.

```
                       [Developer makes a change]
                                 │
                                 ▼
                     [Testing Infrastructure]
                                 │
              ┌──────────────────┼──────────────────┐
              ▼                  ▼                  ▼
       [PHP Inspectors]   [JavaScript        [Browser Inspectors]
       (PHPUnit)          Inspectors]        (Playwright)
                          (Vitest)                  │
              │                  │                  │
              ▼                  ▼                  ▼
       [Plugin's PHP      [Plugin's JS       [A real browser
        code]             code]               + a dev WordPress
                                              site + the cloud]
                                 │
                                 ▼
                     [Pass / Fail report]
                                 │
                                 ▼
                  [Developer ships the change,
                   or fixes what broke]
```

This module sits **alongside** every other module in the plugin rather than feeding into one of them. It does not produce a feature for end users. It produces confidence for the people who maintain the plugin. It depends on every module it is testing, but no module depends on it at runtime.

---

## 5. Key Concepts (Glossary)

- **Test (also called a "spec" or "check")** — A short, automated script that pretends to use the plugin in one specific way and shouts if the result is wrong.
- **Unit test** — A test that checks one tiny piece of the plugin in complete isolation, with everything around it faked. Fast and very focused.
- **Integration test** — A test that lets two real pieces of the system talk to each other (for example, the plugin's code and the actual Traffic Portal cloud) to confirm the conversation still works.
- **End-to-end test (E2E)** — A test that drives a real web browser through the live plugin like a robot user. The most realistic but the slowest kind of test.
- **Regression test** — A test written specifically to catch a bug that was already reported and fixed once. Its job is to make sure the bug never sneaks back.
- **Stress test** — A test that piles on a lot of activity at once (creating many links, generating many clicks) to make sure the plugin holds up under heavier real-world conditions.
- **PHPUnit** — The tool used to run the PHP-side inspectors.
- **Vitest** — The tool used to run the JavaScript-side inspectors in a fake browser environment.
- **Playwright** — The tool used to drive a real browser for the end-to-end inspectors.
- **Fixture** — A pre-arranged starting point for a test (for example, "a logged-in user already on the dashboard"). Saves every test from setting up the same scene from scratch.
- **Mock** — A stand-in for a real outside service. Lets a test pretend the cloud answered without actually contacting it. Used to keep unit tests fast and predictable.

---

## 6. The Main User Journey

Since this module is for developers, the journey below is the developer's journey — what happens when they run the tests.

### Journey A: Quick check after a small code change

1. The developer makes a change to one file in the plugin.
2. They run the unit tests from their terminal.
3. The PHP inspectors and the JavaScript inspectors spin up in seconds, each running through hundreds of small scenarios with all outside services faked.
4. A pass/fail summary prints to the screen. If anything failed, the report points at the exact scenario that broke.
5. The developer fixes whatever broke (or confirms the change really is safe) and moves on.

### Journey B: Full confidence check before shipping

1. The developer runs the full test suite — unit, integration, and end-to-end.
2. The integration tests reach out to a real development instance of the Traffic Portal cloud and confirm the plugin still talks to it correctly.
3. The end-to-end tests open a real Chromium browser, log in to a development copy of the WordPress site, and walk through journeys like "create a short link," "view the usage dashboard," "edit a link's keyword."
4. Every regression test for every previously reported Jira bug also runs, making sure none of those bugs have crept back.
5. A consolidated report shows green across the board (or pinpoints exactly which scenario failed).
6. The developer ships with confidence — or, if something is red, fixes it first.

### Journey C: Stress-testing the system

1. The developer runs the stress pipeline script.
2. Stage one creates a configurable number of short links rapidly (default fifty).
3. Stage two generates simulated click traffic against those links.
4. Stage three opens the dashboard and verifies that all the activity shows up correctly.
5. After the run, the developer is offered a cleanup option that deletes the test links so the development environment stays tidy.

---

## 7. Where It Lives In The Code

| Piece | File / Folder | What it does in one sentence |
|---|---|---|
| PHP test runner config | `phpunit.xml` | Tells PHPUnit which folders contain tests and which environment values to use. |
| JS test runner config | `vitest.config.js` | Tells Vitest to use a fake browser environment and where the setup file is. |
| JS test setup | `tests/setup.js` | Pre-loads fake browser features (like local storage) before any JS test runs. |
| PHP unit tests | `tests/Unit/` | Inspectors that check small PHP pieces in isolation. |
| PHP integration tests | `tests/Integration/` | Inspectors that let the plugin's PHP code actually talk to the cloud service. |
| JS unit test (rate limit) | `tests/rate-limit.test.js` | Checks how the frontend reacts when the API says "too many requests." |
| Browser test fixtures | `tests/e2e/conftest.py` | Logs in once, hands every browser test a ready-to-use authenticated session. |
| Browser tests (main) | `tests/e2e/` | End-to-end browser inspectors for the public form, dashboard, and client links pages. |
| Bug regression suite | `tests/e2e/regression/` | One inspector per tracked Jira bug, each guarding against that bug's return. |
| Stress tests | `tests/e2e/stress/` | Three-stage tests that pile on real load and verify the dashboard holds up. |
| Stress orchestrator | `run_stress.sh` | The single command that runs the three stress stages in order and offers cleanup. |
| Browser test instructions | `tests/e2e/README.md` | How a developer sets up and runs the browser tests for the first time. |
| PHP test scripts | `composer.json` | Shortcut commands for running unit, integration, or all PHP tests. |
| JS test scripts | `package.json` | Shortcut commands for running JavaScript tests with or without coverage. |

---

## 8. External Connections

- **Traffic Portal cloud API** — The integration tests and the browser tests both reach out to a development instance of the cloud to make sure the plugin still talks to it correctly.
- **Development WordPress site** — The browser tests log in to a real development WordPress site (defaults to a dev domain) and click around it like a user.
- **Chromium browser** — Playwright drives a real headless Chromium browser for every end-to-end test.
- **Test data files** — The stress tests write a small JSON file per run so later stages know which links were created. These files are kept locally and ignored by version control.
- **Jira (indirectly)** — Each regression test is named after the Jira ticket it is guarding (for example, `test_tp94`), creating a traceable link between a reported bug and its permanent watchdog.

---

## 9. Configuration & Settings

The testing infrastructure is configured with environment variables and small config files rather than through the WordPress admin.

- **PHPUnit config** — `phpunit.xml` lists the test folders, the code that should be measured for coverage, and the environment values (cloud endpoint, API key, test user) every PHP test will see.
- **Browser test environment** — `tests/e2e/.env` (gitignored) holds the test user's email and password and any URL overrides. A separate `pytest.ini` defines two extra categories ("stress" and "regression_bugs") that are off by default and only run when explicitly requested.
- **Stress test knobs** — Environment variables on the orchestrator script control how many links to create, how many clicks per link, the delay between requests, and a unique run ID for clean isolation.
- **Vitest setup** — `tests/setup.js` pre-loads a fake local storage and silences noisy log output so JavaScript tests stay focused.
- **Default safety** — By design, stress tests and regression bug tests do **not** run when someone runs "all tests." They have to be asked for by name. This prevents a routine test run from accidentally hammering the dev cloud or relying on a bug-specific fixture.

---

## 10. Failure Modes (What Can Go Wrong)

- **The cloud API key is missing or invalid** → Integration tests are skipped or fail with an authentication error; unit tests still run fine because they fake the cloud.
- **The development WordPress site is offline** → Browser tests can't log in and time out, producing a clear "could not reach the page" failure.
- **A regression bug is genuinely back** → The matching regression inspector turns red, naming the Jira ticket — a strong signal that the fix has been undone.
- **The cloud API contract changes silently** → Integration tests light up red even though the plugin's own code didn't change, surfacing the upstream breakage early.
- **A test was written sloppily and is "flaky"** → It passes most of the time and randomly fails. This erodes trust in the suite, so flaky tests are treated as bugs in the test itself, not in the product.
- **A stress run is interrupted halfway** → The links it created remain in the development cloud; the cleanup helper script can be run later with the saved run ID to remove them.
- **A developer forgets to run the tests** → Bugs ship anyway. The tests only protect what someone actually asks them to check; running them is a discipline, not an automatic guarantee.

---

## 11. Related Modules

This module touches every other module by virtue of testing them. The most tightly coupled relationships are:

- [Traffic Portal API Client](./06-traffic-portal-api-client.md) — Heavily exercised by both unit and integration tests; most of the PHP test count lives here.
- [Short Code Generator](./07-short-code-generator.md) — Has dedicated unit and integration tests covering its keyword generation logic.
- [Link Shortener Form](./01-link-shortener-form.md) — Covered by browser tests that drive a real form submission, and by the JS rate-limit test.
- [Personal Dashboard](./02-personal-dashboard.md) — Covered by browser tests for fingerprint validation and dashboard rendering.
- [Client Links Page](./03-client-links-page.md) — The largest single subject of the browser test suite; sortable columns, modals, and toggles are all checked.
- [Usage Analytics Dashboard](./04-usage-analytics-dashboard.md) — Covered by extensive browser tests and is the verification target of the stress pipeline.
- [WooWallet Integration](./08-woowallet-integration.md) — Covered by dedicated unit tests for the running-balance algorithm and the usage merge adapter.

---

## 12. Notes For The Curious

- The PHP tests and the JavaScript tests run with completely different tools because they live in different worlds — PHP runs on the server, JavaScript runs in the browser. Each tool is the standard in its own ecosystem.
- The browser tests use Python (with Playwright) rather than JavaScript, which is unusual for a WordPress plugin but gives much cleaner, more readable test code.
- Regression tests are named after the Jira ticket they guard (for example, `test_tp71`, `test_tp94`). This naming convention turns the test folder into a living index of every notable bug the project has ever fixed.
- Stress tests are deliberately **excluded** from the default test run. They cost time and create real cloud data, so a developer has to opt in. The orchestrator script makes that opt-in a single command.
- The integration tests embed real (but development-environment) credentials directly in `phpunit.xml`. This is convenient for the dev cloud but is the kind of thing that should never point at production.
- Coverage measurement is set up only for three PHP folders right now (the SnapCapture, ShortCode, and TrafficPortal modules), reflecting where the test investment has been deepest so far.
- The pattern of "log in once, reuse the session" in the browser tests is a deliberate speed optimisation — without it, every single test would re-do the WordPress login dance and the suite would take many times longer to run.

---

_Document version: 1.0 — Last updated: 2026-04-26_

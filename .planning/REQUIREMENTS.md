# Requirements: Traffic Portal v2.3 — Stress Test and Bug Regression

**Defined:** 2026-03-22
**Core Value:** Validate plugin reliability through stress testing and regression coverage of all known Jira bugs

## v2.3 Requirements

Requirements for this milestone. Each maps to roadmap phases.

### Test Infrastructure

- [ ] **INFRA-01**: Test suite installs httpx, pytest-asyncio, and pytest-xdist as new dependencies
- [ ] **INFRA-02**: Conftest provides stress data fixture (session-scoped, writes/reads stress_data.json)
- [ ] **INFRA-03**: Pytest markers (@pytest.mark.stress, @pytest.mark.regression_bugs) exclude these tests from the default test run — they only execute when explicitly selected (e.g. pytest -m stress or pytest -m regression_bugs)
- [ ] **INFRA-04**: RUN_ID pattern isolates test data per run (unique prefix on link keywords)
- [ ] **INFRA-05**: Cleanup fixture deletes stress-created links after test suite completes

### Stress Test — Link Creation

- [ ] **STRESS-01**: Script creates 50 short links via Playwright UI using custom keywords (not shortcode generator)
- [ ] **STRESS-02**: Each link points to a valid URL (e.g. https://example.com) with unique keyword per RUN_ID
- [ ] **STRESS-03**: Created link data (keyword, URL, MID) is persisted to stress_data.json for downstream tests

### Stress Test — Usage Generation

- [ ] **USAGE-01**: Script sends HTTP requests to each of the 50 created links to generate usage records
- [ ] **USAGE-02**: Each link is hit multiple times (configurable, default 5+) to create measurable usage volume
- [ ] **USAGE-03**: Requests use httpx with rate limiting/backoff to avoid API throttling (429s)
- [ ] **USAGE-04**: Usage generation reads link data from stress_data.json (output of STRESS phase)

### Stress Test — Dashboard Verification

- [ ] **VERIFY-01**: Playwright test navigates to /usage-dashboard and verifies page loads with data
- [ ] **VERIFY-02**: Test confirms usage table shows records for the date range covering stress test activity
- [ ] **VERIFY-03**: Test confirms chart renders with non-zero data points for stress test period
- [ ] **VERIFY-04**: Test accounts for eventual consistency with retry/polling for usage data to appear

### Stress Test — Orchestration

- [ ] **ORCH-01**: Single entry point shell script chains all three stress stages sequentially with shared RUN_ID and fails fast on any stage error

### Bug Regression Suite

- [ ] **REG-01**: Regression test for TP-22 — empty and non-existent key default redirect (should redirect to trafficportal.com, not error/blank)
- [ ] **REG-02**: Regression test for TP-25 — custom device-based redirect issues (wrong redirect for mobile/QR)
- [ ] **REG-03**: Regression test for TP-29 — domain-related redirect issues (non-existent domain, existent domain without key, subdomain with key)
- [ ] **REG-04**: Regression test for TP-34 — redirect errors with Set
- [ ] **REG-05**: Regression test for TP-41 — domain name management bugs (GET /domains/info)
- [ ] **REG-06**: Regression test for TP-71 — link shortener uploading wrong destination (caching issue)
- [ ] **REG-07**: Regression test for TP-94 — MVP bugs and fixes (umbrella — decompose into specific test cases)

## v2.2 Requirements (Prior Milestone — Still Open)

### Wallet Client
- [ ] **WCLI-01**: Plugin fetches wallet credit transactions from TerrWallet API — Phase 9
- [ ] **WCLI-02**: TerrWallet API credentials configured via wp-config.php — Phase 9
- [ ] **WCLI-03**: Wallet client handles pagination — Phase 9
- [ ] **WCLI-04**: Wallet client uses direct PHP calls or rest_do_request() — Phase 9

### Data Merge
- [ ] **MERGE-01**: Wallet transactions merged with usage data by date — Phase 10
- [ ] **MERGE-02**: Multiple transactions per day aggregated — Phase 10
- [ ] **MERGE-03**: Wallet-only days appear with zero hits/cost — Phase 10
- [ ] **MERGE-04**: Date formats normalized — Phase 10

### Graceful Degradation
- [ ] **GRACE-01**: Dashboard works if TerrWallet API unavailable — Phase 11
- [ ] **GRACE-02**: Dashboard works if TerrWallet plugin deactivated — Phase 11

### Dashboard UI
- [ ] **UI-01**: Other Services column in usage table — Phase 12
- [ ] **UI-02**: Tooltip on Other Services amounts — Phase 12
- [ ] **UI-03**: Other Services summary card — Phase 12
- [ ] **UI-04**: Merged data via existing AJAX handler — Phase 11

### Testing
- [ ] **TEST-01**: Integration tests for wallet client — Phase 13
- [ ] **TEST-02**: Unit tests for merge adapter — Phase 13
- [ ] **TEST-03**: E2E tests for Other Services column — Phase 13

## Future Requirements

### Performance Benchmarking
- **PERF-01**: Track and report link creation time per link during stress test
- **PERF-02**: Track and report usage generation request latency distribution
- **PERF-03**: Compare stress test metrics across runs for regression detection

## Out of Scope

| Feature | Reason |
|---------|--------|
| TP-46 regression test | Infrastructure/IP issue, not a behavioral bug testable via E2E |
| Load testing (100+ concurrent users) | Stress test is 50 links sequential, not concurrent load simulation |
| Production environment testing | All tests run against dev/staging environment |
| Fixing the bugs found | This milestone writes tests only, not bug fixes |
| Shortcode generation tests | Known broken (500 errors) — stress test bypasses with custom keywords |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| INFRA-01 | Phase 14 | Pending |
| INFRA-02 | Phase 14 | Pending |
| INFRA-03 | Phase 14 | Pending |
| INFRA-04 | Phase 14 | Pending |
| INFRA-05 | Phase 14 | Pending |
| STRESS-01 | Phase 15 | Pending |
| STRESS-02 | Phase 15 | Pending |
| STRESS-03 | Phase 15 | Pending |
| USAGE-01 | Phase 15 | Pending |
| USAGE-02 | Phase 15 | Pending |
| USAGE-03 | Phase 15 | Pending |
| USAGE-04 | Phase 15 | Pending |
| VERIFY-01 | Phase 15 | Pending |
| VERIFY-02 | Phase 15 | Pending |
| VERIFY-03 | Phase 15 | Pending |
| VERIFY-04 | Phase 15 | Pending |
| ORCH-01 | Phase 15 | Pending |
| REG-01 | Phase 16 | Pending |
| REG-02 | Phase 16 | Pending |
| REG-03 | Phase 16 | Pending |
| REG-04 | Phase 16 | Pending |
| REG-05 | Phase 16 | Pending |
| REG-06 | Phase 16 | Pending |
| REG-07 | Phase 16 | Pending |

**Coverage:**
- v2.3 requirements: 23 total
- Mapped to phases: 23/23
- Unmapped: 0

---
*Requirements defined: 2026-03-22*
*Last updated: 2026-03-22 after roadmap creation*

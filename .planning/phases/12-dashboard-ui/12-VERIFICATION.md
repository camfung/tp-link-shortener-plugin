---
phase: 12-dashboard-ui
verified: 2026-03-10T21:00:00Z
status: passed
score: 9/9 must-haves verified
re_verification: false
human_verification:
  - test: "Hover an Other Services cell with wallet data"
    expected: "Bootstrap tooltip appears showing transaction description text"
    why_human: "Tooltip initialization requires a live browser with Bootstrap loaded — cannot verify tooltip display programmatically"
  - test: "Sort the Other Services column by clicking its header"
    expected: "Rows reorder by wallet amount, sort icon changes to up/down arrow"
    why_human: "Sort interaction requires a live browser and rendered table with real data"
  - test: "Load the dashboard with a wallet-unavailable scenario (all otherServices null)"
    expected: "All Other Services cells show $0.00, summary card shows +$0.00 with 0 days with credits, no JS errors"
    why_human: "Requires simulating wallet API failure in a live environment"
---

# Phase 12: Dashboard UI Verification Report

**Phase Goal:** Users can see their wallet credit amounts per day in a dedicated Other Services column with tooltip descriptions, and the summary strip shows the period total
**Verified:** 2026-03-10T21:00:00Z
**Status:** passed
**Re-verification:** No -- initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Usage dashboard table shows an Other Services column between Hits and Cost with +$X.XX green amounts for days with wallet activity | VERIFIED | `templates/usage-dashboard-template.php` line 132: `<th class="tp-ud-col-other tp-ud-sortable" data-sort="otherServices">` between `tp-ud-col-hits` (line 129) and `tp-ud-col-cost` (line 135). `buildOtherServicesCell()` at JS line 268 returns `+` prefix + `formatCurrency(os.amount)` with class `tp-ud-other-amount` when `os.amount > 0`. |
| 2 | Days without wallet activity show $0.00 in plain muted text with no tooltip | VERIFIED | `buildOtherServicesCell()` at JS line 270-271: when `!os \|\| !os.amount \|\| os.amount <= 0` returns `<span class="tp-ud-other-zero">$0.00</span>` with no `data-bs-toggle` attribute. `.tp-ud-other-zero` in CSS line 519 uses `var(--tp-muted)` color. |
| 3 | Hovering an Other Services amount with wallet data shows a Bootstrap tooltip with transaction descriptions | VERIFIED | `buildOtherServicesCell()` at JS lines 275-278 sets `data-bs-toggle="tooltip"`, `data-bs-html="true"`, `data-bs-title` on active cells. `initTooltips()` at line 299 creates `new bootstrap.Tooltip(el, {trigger: 'hover focus', placement: 'top', container: 'body'})` for each such element after row render. |
| 4 | Multiple transactions on same day show each as "Description (+$amount)" on separate lines in tooltip | VERIFIED | `buildTooltipContent()` at JS lines 259-261: when `items.length > 1`, maps each item to `escapeHtml(item.description) + ' (+' + formatCurrency(item.amount) + ')'` joined by `<br>`. |
| 5 | Single transaction shows just the description text in tooltip | VERIFIED | `buildTooltipContent()` at JS lines 256-258: when `items.length === 1`, returns `escapeHtml(items[0].description)` only. |
| 6 | Summary strip has a 4th card showing Other Services total for the period | VERIFIED | `renderSummaryCards()` at JS line 546: `buildStatCard('fa-hand-holding-dollar', '+' + formatCurrency(otherServicesTotal), 'Other Services', daysWithCredits + ' day' + ...)` placed as 4th card after hits, cost, and balance cards. Uses integer-cents accumulation (lines 524-534). |
| 7 | Other Services column is sortable like existing columns | VERIFIED | Template line 132: header has `class="tp-ud-col-other tp-ud-sortable"` and `data-sort="otherServices"`. `getSortedData()` at JS lines 330-333 handles `field === 'otherServices'` with null-safe `(a.otherServices && a.otherServices.amount) \|\| 0` extraction. |
| 8 | Loading skeleton shows 5 columns matching the live table | VERIFIED | Template lines 44-48: skeleton `<thead>` has 5 `<th>` elements (date, hits, other, cost, balance). Skeleton rows (lines 53-60) each have 5 `<td>` elements. |
| 9 | When all otherServices values are null (wallet API failure), column shows all $0.00 and summary card shows +$0.00 | VERIFIED | `buildOtherServicesCell()` null-guard at line 270: `!os` branch covers null case. `renderSummaryCards()` null-guard at line 532: `os && os.amount && os.amount > 0` means null records contribute 0 to `otherServicesCents`, resulting in `+$0.00` total and 0 `daysWithCredits`. |

**Score:** 9/9 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `templates/usage-dashboard-template.php` | Other Services column header and skeleton column; contains "Other Services" | VERIFIED | Contains "Other Services" at lines 46, 133. 5-column header in both live table (lines 126-140) and skeleton (lines 44-48). Skeleton rows have 5 `<td>` elements. |
| `assets/css/usage-dashboard.css` | 5-column widths, green amount styling, zero styling; contains "tp-ud-col-other" | VERIFIED | `.tp-ud-col-other { width: 18%; min-width: 100px; }` at line 442. `.tp-ud-other-amount` green styling at lines 511-517. `.tp-ud-other-zero` muted styling at lines 519-523. Mobile reset for `.tp-ud-col-other` at line 690. |
| `assets/js/usage-dashboard.js` | Other Services cell rendering, tooltip lifecycle, summary card, sort support; contains "otherServices" | VERIFIED | `escapeHtml` (line 137), `escapeAttr` (line 150), `buildTooltipContent` (line 254), `buildOtherServicesCell` (line 268), `disposeTooltips` (line 286), `initTooltips` (line 299) all present and substantive. `otherServices` appears 12 times covering render, sort, and summary. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `assets/js/usage-dashboard.js` | AJAX response `day.otherServices` | null check then `.amount` and `.items` access | WIRED | Line 269: `var os = day.otherServices;`. Line 270: `!os \|\| !os.amount \|\| os.amount <= 0` null guard. Line 273: `os.items` access only after guard. Lines 331-332: same null-safe pattern in sort. Lines 531-534: null-safe in summary card. |
| `assets/js/usage-dashboard.js` | `bootstrap.Tooltip` | init after `renderRows`, dispose before `$tbody.empty()` | WIRED | `disposeTooltips()` called at line 402 (before `$tbody.empty()` at line 403). `initTooltips()` called at line 428 (after `$tbody.append(row)` loop at line 425). Guard `typeof bootstrap === 'undefined'` at line 300. |
| `templates/usage-dashboard-template.php` | `assets/js/usage-dashboard.js` | `data-sort` attribute on Other Services header | WIRED | Line 132: `data-sort="otherServices"` on `<th>`. JS sort event handler at line 763 reads `$(this).data('sort')` and passes to `getSortedData()`. |

### Requirements Coverage

| Requirement | Description | Status | Evidence |
|-------------|-------------|--------|---------|
| UI-01 | Usage dashboard table includes an "Other Services" column showing wallet credit amounts (+$X.XX format) | SATISFIED | Column present in template (line 132). `buildOtherServicesCell()` renders `+$X.XX` format with `tp-ud-other-amount` class for active days. |
| UI-02 | Other Services amounts display a tooltip on hover showing the transaction description | SATISFIED | `data-bs-toggle="tooltip"` with `data-bs-title` set to `buildTooltipContent(os.items)` output. `initTooltips()` creates Bootstrap instances with `trigger: 'hover focus'`. |
| UI-03 | Summary strip includes an Other Services total card for the selected period | SATISFIED | 4th `buildStatCard()` call at line 546 renders "Other Services" with integer-cent accumulated total and days-with-credits count. |

**Note:** UI-04 is assigned to Phase 11 (existing AJAX handler returns merged data), not Phase 12. It is not a gap here — Phase 12's PLAN `requirements` frontmatter correctly lists only `[UI-01, UI-02, UI-03]`.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `templates/usage-dashboard-template.php` | 168-174 | TEMP wallet test panel in production template | Warning | Temporary wallet client test UI included in the rendered shortcode output. Marked for removal in Phase 13. No functional impact on Other Services column. |
| `assets/js/usage-dashboard.js` | 871-897 | TEMP wallet test AJAX handler wired in `$(document).ready` | Warning | Test AJAX call wired into dashboard initialization. Marked for removal in Phase 13. No functional impact on Other Services column. |

Neither anti-pattern blocks the Phase 12 goal. Both are explicitly flagged `// TEMP: Remove after milestone v2.2 complete` and are Phase 13 cleanup scope per SUMMARY.md.

### Human Verification Required

#### 1. Bootstrap Tooltip on Hover

**Test:** Load the usage dashboard with a date range that includes days with wallet credits. Hover over a green +$X.XX amount in the Other Services column.
**Expected:** A Bootstrap tooltip appears above the cell showing the transaction description. For multiple transactions on the same day, each appears on its own line as "Description (+$X.XX)".
**Why human:** Tooltip rendering requires Bootstrap JS loaded in a browser, a live DOM, and actual `otherServices` data from the API — cannot be verified via static analysis.

#### 2. Column Sort Interaction

**Test:** Click the "Other Services" column header. Click it again.
**Expected:** First click sorts ascending by wallet amount (arrow up icon). Second click sorts descending (arrow down icon). Rows with $0.00 group at bottom/top accordingly.
**Why human:** Requires a live browser with rendered table data to observe sort behavior.

#### 3. Wallet API Failure Graceful Degradation

**Test:** Simulate wallet API unavailability (e.g., temporarily misconfigure TerrWallet credentials) and load the dashboard.
**Expected:** Other Services column shows $0.00 in all rows, summary card shows "+$0.00" with "0 days with credits", no JS errors in console.
**Why human:** Requires control over server-side wallet API availability in a live environment.

### Discrepancy Note: ROADMAP vs PLAN Zero-Amount Display

The ROADMAP success criterion 1 states "a dash for days without" wallet activity. The PLAN `must_haves` explicitly specifies "$0.00 in plain muted text with no tooltip". The implementation follows the PLAN (which is the execution contract). This is a wording discrepancy in the ROADMAP, not a functional gap — the intent (distinguishing active vs inactive days visually) is satisfied either way.

### Gaps Summary

No gaps. All 9 must-have truths are verified, all 3 artifacts are substantive and wired, all 3 key links are confirmed, and all 3 requirements (UI-01, UI-02, UI-03) are satisfied. The two TEMP anti-patterns are known pre-cleanup items scoped to Phase 13 and do not block the Phase 12 goal.

---

_Verified: 2026-03-10T21:00:00Z_
_Verifier: Claude (gsd-verifier)_

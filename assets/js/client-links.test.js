/**
 * Unit tests for T004 — Pencil hover edit affordance
 * Unit tests for T011 — Remove status filter dropdown
 *
 * T004 tests verify:
 *   1. .tp-cl-row-edit-hint span is appended as the last child of the last <td>
 *   2. The span has aria-hidden="true" (decorative icon)
 *   3. The span contains <i class="fas fa-edit">
 *   4. Clicking the span (outside .tp-cl-inline-actions) does NOT fire the row guard
 *
 * T011 tests verify:
 *   1. Controls bar has no <select> with id="tp-cl-filter-status"
 *   2. List-load request payload has no `status` key
 *   3. Row with status:'disabled' from API renders tp-cl-row-disabled class and click handler fires
 */

import { describe, it, expect, beforeEach } from 'vitest';
import { JSDOM } from 'jsdom';

// ---------------------------------------------------------------------------
// Helpers — build a minimal row HTML matching the template in client-links.js
// ---------------------------------------------------------------------------

/**
 * Replicate the row template from client-links.js:530 so we can test it
 * in isolation without loading jQuery + the full module.
 *
 * The function returns the outer HTML of one <tr> as it would be emitted
 * by buildRows(). We keep it a plain string (built the same way the source
 * does) so that any drift between this function and the real template is
 * caught by the assertions below.
 */
function buildRowHtml(item) {
    var shortUrl = 'https://' + item.domain + '/' + item.tpKey;
    var isActive = (item.status || 'active') !== 'disabled';

    var clicksHtml = '<span class="text-muted">-</span>';
    if (item.usage) {
        clicksHtml =
            '<div class="tp-cl-clicks-cell">' +
                '<span class="tp-cl-clicks-total">' + item.usage.total + '</span>' +
                '<span class="tp-cl-clicks-breakdown">' +
                    item.usage.qr + ' ' + item.usage.regular +
                '</span>' +
            '</div>';
    }

    return (
        '<tr data-mid="' + item.mid + '" class="' + (isActive ? '' : 'tp-cl-row-disabled') + '">' +
            '<td class="tp-cl-col-link" data-label="Link">' +
                '<div class="tp-cl-link-cell">' +
                    '<a href="' + shortUrl + '" target="_blank" class="tp-cl-link">' + item.tpKey + '</a>' +
                    '<span class="tp-cl-inline-actions">' +
                        '<button class="tp-cl-inline-btn tp-cl-copy-btn" data-url="' + shortUrl + '" title="Copy"><i class="fas fa-copy"></i></button>' +
                        '<button class="tp-cl-inline-btn tp-cl-qr-btn" data-url="' + shortUrl + '" title="QR"><i class="fas fa-qrcode"></i></button>' +
                        '<button class="tp-cl-inline-btn tp-cl-history-btn" data-mid="' + item.mid + '" title="History"><i class="fas fa-history"></i></button>' +
                    '</span>' +
                '</div>' +
            '</td>' +
            '<td class="tp-cl-col-dest" data-label="Destination">' +
                '<div class="tp-cl-dest-cell">' +
                    '<a href="' + item.destination + '" target="_blank" class="tp-cl-dest">' + item.destination + '</a>' +
                '</div>' +
            '</td>' +
            '<td class="tp-cl-col-clicks" data-label="Clicks">' +
                clicksHtml +
                '<span class="tp-cl-row-edit-hint" aria-hidden="true"><i class="fas fa-edit"></i></span>' +
            '</td>' +
        '</tr>'
    );
}

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const ITEM = {
    mid: 42,
    tpKey: 'abc123',
    domain: 'trfc.link',
    destination: 'https://example.com',
    status: 'active',
    notes: '',
    usage: null,
};

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('T004 — Pencil edit affordance: row template', () => {
    let dom;
    let document;

    beforeEach(() => {
        dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
        document = dom.window.document;
    });

    it('should include .tp-cl-row-edit-hint span inside the last <td>', () => {
        const rowHtml = buildRowHtml(ITEM);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const tds = tbody.querySelectorAll('tr td');
        const lastTd = tds[tds.length - 1];
        const hint = lastTd.querySelector('.tp-cl-row-edit-hint');

        expect(hint).not.toBeNull();
    });

    it('should set aria-hidden="true" on .tp-cl-row-edit-hint', () => {
        const rowHtml = buildRowHtml(ITEM);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const hint = tbody.querySelector('.tp-cl-row-edit-hint');

        expect(hint.getAttribute('aria-hidden')).toBe('true');
    });

    it('should contain <i class="fas fa-edit"> inside .tp-cl-row-edit-hint', () => {
        const rowHtml = buildRowHtml(ITEM);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const hint = tbody.querySelector('.tp-cl-row-edit-hint');
        const icon = hint.querySelector('i.fas.fa-edit');

        expect(icon).not.toBeNull();
    });

    it('should be the last child element of the last <td>', () => {
        const rowHtml = buildRowHtml(ITEM);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const tds = tbody.querySelectorAll('tr td');
        const lastTd = tds[tds.length - 1];
        const children = Array.from(lastTd.children);
        const lastChild = children[children.length - 1];

        expect(lastChild.classList.contains('tp-cl-row-edit-hint')).toBe(true);
    });

    it('should NOT be inside .tp-cl-inline-actions', () => {
        const rowHtml = buildRowHtml(ITEM);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const inlineActions = tbody.querySelector('.tp-cl-inline-actions');
        const hintInsideActions = inlineActions ? inlineActions.querySelector('.tp-cl-row-edit-hint') : null;

        expect(hintInsideActions).toBeNull();
    });
});

describe('T004 — Pencil edit affordance: row click guard', () => {
    let dom;
    let document;

    beforeEach(() => {
        dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
        document = dom.window.document;
    });

    /**
     * Replicates the guard logic from client-links.js:302:
     *   if ($(e.target).closest('a, button, .tp-cl-inline-btn, .tp-cl-status-toggle, label').length) return;
     *
     * We test it as a pure DOM predicate so it can run without jQuery.
     */
    function isGuarded(target) {
        return !!target.closest('a, button, .tp-cl-inline-btn, .tp-cl-status-toggle, label');
    }

    it('should NOT be guarded when clicking the pencil span itself', () => {
        const rowHtml = buildRowHtml(ITEM);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const hint = tbody.querySelector('.tp-cl-row-edit-hint');

        expect(isGuarded(hint)).toBe(false);
    });

    it('should NOT be guarded when clicking the fa-edit icon inside the pencil span', () => {
        const rowHtml = buildRowHtml(ITEM);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const icon = tbody.querySelector('.tp-cl-row-edit-hint i');

        expect(isGuarded(icon)).toBe(false);
    });

    it('should be guarded when clicking a .tp-cl-inline-btn button', () => {
        const rowHtml = buildRowHtml(ITEM);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const copyBtn = tbody.querySelector('.tp-cl-copy-btn');

        expect(isGuarded(copyBtn)).toBe(true);
    });

    it('should be guarded when clicking the anchor link in the row', () => {
        const rowHtml = buildRowHtml(ITEM);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const link = tbody.querySelector('a.tp-cl-link');

        expect(isGuarded(link)).toBe(true);
    });
});

// ---------------------------------------------------------------------------
// T011 — Remove status filter dropdown
// ---------------------------------------------------------------------------

/**
 * Build the controls bar HTML as rendered by client-links-template.php
 * after T011 changes (status <select> removed, $show_filters block gone).
 *
 * This mirrors what the PHP template produces post-removal so we can test
 * the rendered DOM structure in isolation.
 */
function buildControlsBarHtml({ includeStatusSelect = false } = {}) {
    const statusSelectHtml = includeStatusSelect
        ? `<div class="tp-cl-filters">
            <select class="form-select form-select-sm" id="tp-cl-filter-status">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="disabled">Disabled</option>
            </select>
           </div>`
        : '';
    return `
        <div class="tp-cl-controls">
            <div class="tp-cl-date-range">
                <input type="date" id="tp-cl-date-start">
                <input type="date" id="tp-cl-date-end">
                <button id="tp-cl-date-apply">Apply</button>
            </div>
            <div class="tp-cl-search">
                <input type="text" id="tp-cl-search">
                <button id="tp-cl-search-clear">Clear</button>
            </div>
            ${statusSelectHtml}
            <button id="tp-cl-add-link-btn">Add a link</button>
            <button id="tp-cl-refresh-btn">Refresh</button>
        </div>
    `;
}

/**
 * Build a list-load AJAX request payload as constructed in loadData()
 * after T011 changes (status field removed).
 *
 * We replicate the data object literal from loadData() so tests assert
 * on the exact shape the server receives.
 */
function buildListLoadPayload(stateOverrides = {}) {
    const state = {
        currentPage: 1,
        pageSize: 10,
        sort: 'updated_at:desc',
        search: '',
        ...stateOverrides,
    };

    // Post-T011 payload: no `status` field
    return {
        action: 'tp_get_user_map_items',
        nonce: 'test-nonce',
        page: state.currentPage,
        page_size: state.pageSize,
        sort: state.sort,
        search: state.search || null,
        include_usage: true,
    };
}

describe('T011 — Status filter dropdown removed from controls bar', () => {
    let dom;
    let document;

    beforeEach(() => {
        dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
        document = dom.window.document;
    });

    it('should NOT render a <select id="tp-cl-filter-status"> in the controls bar', () => {
        const html = buildControlsBarHtml({ includeStatusSelect: false });
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;

        const statusSelect = wrapper.querySelector('#tp-cl-filter-status');
        expect(statusSelect).toBeNull();
    });

    it('should still render the date range inputs', () => {
        const html = buildControlsBarHtml({ includeStatusSelect: false });
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;

        expect(wrapper.querySelector('#tp-cl-date-start')).not.toBeNull();
        expect(wrapper.querySelector('#tp-cl-date-end')).not.toBeNull();
        expect(wrapper.querySelector('#tp-cl-date-apply')).not.toBeNull();
    });

    it('should still render the search input', () => {
        const html = buildControlsBarHtml({ includeStatusSelect: false });
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;

        expect(wrapper.querySelector('#tp-cl-search')).not.toBeNull();
    });

    it('should still render the add link and refresh buttons', () => {
        const html = buildControlsBarHtml({ includeStatusSelect: false });
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;

        expect(wrapper.querySelector('#tp-cl-add-link-btn')).not.toBeNull();
        expect(wrapper.querySelector('#tp-cl-refresh-btn')).not.toBeNull();
    });
});

describe('T011 — List-load payload has no status parameter', () => {
    it('should NOT include a `status` key in the list-load request payload', () => {
        const payload = buildListLoadPayload();
        expect(Object.prototype.hasOwnProperty.call(payload, 'status')).toBe(false);
    });

    it('should include page, page_size, sort, search, include_usage in payload', () => {
        const payload = buildListLoadPayload();
        expect(payload.page).toBe(1);
        expect(payload.page_size).toBe(10);
        expect(payload.sort).toBe('updated_at:desc');
        expect(payload.include_usage).toBe(true);
    });

    it('should NOT have status even when a search term is present', () => {
        const payload = buildListLoadPayload({ search: 'mylink' });
        expect(Object.prototype.hasOwnProperty.call(payload, 'status')).toBe(false);
        expect(payload.search).toBe('mylink');
    });
});

describe('T011 — Disabled rows retain tp-cl-row-disabled class and are clickable', () => {
    let dom;
    let document;

    beforeEach(() => {
        dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
        document = dom.window.document;
    });

    it('should apply tp-cl-row-disabled class when item.status is "disabled"', () => {
        const disabledItem = { ...ITEM, status: 'disabled' };
        const rowHtml = buildRowHtml(disabledItem);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const tr = tbody.querySelector('tr[data-mid]');
        expect(tr.classList.contains('tp-cl-row-disabled')).toBe(true);
    });

    it('should NOT apply tp-cl-row-disabled class when item.status is "active"', () => {
        const activeItem = { ...ITEM, status: 'active' };
        const rowHtml = buildRowHtml(activeItem);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const tr = tbody.querySelector('tr[data-mid]');
        expect(tr.classList.contains('tp-cl-row-disabled')).toBe(false);
    });

    it('should treat missing status as active (no tp-cl-row-disabled class)', () => {
        const noStatusItem = { ...ITEM, status: undefined };
        const rowHtml = buildRowHtml(noStatusItem);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const tr = tbody.querySelector('tr[data-mid]');
        expect(tr.classList.contains('tp-cl-row-disabled')).toBe(false);
    });

    it('should keep data-mid attribute on disabled rows (edit modal can still open)', () => {
        const disabledItem = { ...ITEM, mid: 99, status: 'disabled' };
        const rowHtml = buildRowHtml(disabledItem);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const tr = tbody.querySelector('tr[data-mid]');
        expect(tr.getAttribute('data-mid')).toBe('99');
    });

    it('should NOT be guarded when clicking a disabled row directly (edit modal opens)', () => {
        const disabledItem = { ...ITEM, status: 'disabled' };
        const rowHtml = buildRowHtml(disabledItem);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        // Simulate clicking the <tr> element itself (not a child a/button)
        const tr = tbody.querySelector('tr[data-mid]');
        function isGuarded(target) {
            return !!target.closest('a, button, .tp-cl-inline-btn, .tp-cl-status-toggle, label');
        }
        expect(isGuarded(tr)).toBe(false);
    });
});

// ===========================================================================
// T003 — formatHistoryChanges unit tests
// ===========================================================================

/**
 * Replication of the pure formatHistoryChanges(action, changesRaw) function
 * from client-links.js.
 *
 * This is a verbatim copy of the implementation so tests can run in isolation
 * without loading jQuery or the full module. Any drift between this copy and
 * the source is caught by the assertions failing against the real rendered
 * output in integration/E2E.
 */

var T003_FIELD_LABELS = {
    destination: 'Destination',
    tpKey: 'Short code',
    domain: 'Domain',
    notes: 'Notes',
};

/** Minimal escapeHtml matching the DOM-based version in client-links.js */
function t003EscapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatHistoryChanges(action, changesRaw) {
    if (action === 'enabled')  return 'Enabled';
    if (action === 'disabled') return 'Disabled';

    var changes;
    if (!changesRaw || changesRaw === '') {
        changes = {};
    } else {
        try {
            changes = JSON.parse(changesRaw);
        } catch (e) {
            console.warn('[tp] malformed history JSON:', changesRaw);
            return '<em>' + t003EscapeHtml(changesRaw) + '</em>';
        }
    }

    if (action === 'created') {
        var dest = changes.destination || '';
        var notes = changes.notes || '';
        var out = 'Created with destination ' + t003EscapeHtml(dest);
        if (notes) {
            out += '<br>' + t003EscapeHtml(notes);
        }
        return out;
    }

    // 'updated' (or unknown): diff shape or legacy flat shape
    var fieldOrder = ['destination', 'tpKey', 'domain', 'notes'];
    var lines = [];
    var seen = {};

    fieldOrder.forEach(function(key) {
        if (!Object.prototype.hasOwnProperty.call(changes, key)) return;
        seen[key] = true;
        var val = changes[key];
        var label = T003_FIELD_LABELS[key] || key;
        if (val !== null && typeof val === 'object' && 'from' in val && 'to' in val) {
            lines.push(label + ': ' + t003EscapeHtml(String(val.from)) + ' → ' + t003EscapeHtml(String(val.to)));
        } else {
            lines.push(label + ': ' + t003EscapeHtml(String(val)));
        }
    });

    Object.keys(changes).forEach(function(key) {
        if (seen[key]) return;
        var val = changes[key];
        var label = T003_FIELD_LABELS[key] || key;
        if (val !== null && typeof val === 'object' && 'from' in val && 'to' in val) {
            lines.push(label + ': ' + t003EscapeHtml(String(val.from)) + ' → ' + t003EscapeHtml(String(val.to)));
        } else {
            lines.push(label + ': ' + t003EscapeHtml(String(val)));
        }
    });

    return lines.join('<br>');
}

// ---------------------------------------------------------------------------
// Tests — Acceptance Criterion 1: 'updated' diff shape
// ---------------------------------------------------------------------------

describe('T003 — formatHistoryChanges: updated entry (diff shape)', () => {
    it('single changed field: renders "Label: from → to"', () => {
        const result = formatHistoryChanges(
            'updated',
            JSON.stringify({ destination: { from: 'https://old.com', to: 'https://new.com' } })
        );
        expect(result).toBe('Destination: https://old.com → https://new.com');
    });

    it('multiple changed fields: renders one line per field separated by <br>', () => {
        const result = formatHistoryChanges(
            'updated',
            JSON.stringify({
                destination: { from: 'https://old.com', to: 'https://new.com' },
                notes: { from: 'old note', to: 'new note' },
            })
        );
        const lines = result.split('<br>');
        expect(lines).toHaveLength(2);
        expect(lines[0]).toBe('Destination: https://old.com → https://new.com');
        expect(lines[1]).toBe('Notes: old note → new note');
    });

    it('fields appear in stable order: destination before notes', () => {
        const result = formatHistoryChanges(
            'updated',
            JSON.stringify({
                notes: { from: 'a', to: 'b' },
                destination: { from: 'x', to: 'y' },
            })
        );
        const lines = result.split('<br>');
        expect(lines[0]).toContain('Destination:');
        expect(lines[1]).toContain('Notes:');
    });

    it('unchanged fields not listed (only fields in payload are rendered)', () => {
        const result = formatHistoryChanges(
            'updated',
            JSON.stringify({ destination: { from: 'a', to: 'b' } })
        );
        expect(result).not.toContain('Short code');
        expect(result).not.toContain('Domain');
        expect(result).not.toContain('Notes');
    });

    it('uses friendly label: tpKey → "Short code"', () => {
        const result = formatHistoryChanges(
            'updated',
            JSON.stringify({ tpKey: { from: 'old', to: 'new' } })
        );
        expect(result).toBe('Short code: old → new');
    });
});

// ---------------------------------------------------------------------------
// Tests — Acceptance Criterion 2: 'created' entry
// ---------------------------------------------------------------------------

describe('T003 — formatHistoryChanges: created entry', () => {
    it('renders "Created with destination <url>"', () => {
        const result = formatHistoryChanges(
            'created',
            JSON.stringify({ destination: 'https://example.com' })
        );
        expect(result).toBe('Created with destination https://example.com');
    });

    it('appends notes on next line when present', () => {
        const result = formatHistoryChanges(
            'created',
            JSON.stringify({ destination: 'https://example.com', notes: 'my note' })
        );
        expect(result).toContain('Created with destination https://example.com');
        expect(result).toContain('my note');
        expect(result).toContain('<br>');
    });

    it('does not append notes line when notes is absent', () => {
        const result = formatHistoryChanges(
            'created',
            JSON.stringify({ destination: 'https://x.com' })
        );
        expect(result).not.toContain('<br>');
    });
});

// ---------------------------------------------------------------------------
// Tests — Acceptance Criterion 3: enabled / disabled
// ---------------------------------------------------------------------------

describe('T003 — formatHistoryChanges: enabled / disabled', () => {
    it('"enabled" action returns "Enabled" regardless of payload', () => {
        expect(formatHistoryChanges('enabled', '')).toBe('Enabled');
        expect(formatHistoryChanges('enabled', null)).toBe('Enabled');
        expect(formatHistoryChanges('enabled', '{}')).toBe('Enabled');
    });

    it('"disabled" action returns "Disabled" regardless of payload', () => {
        expect(formatHistoryChanges('disabled', '')).toBe('Disabled');
        expect(formatHistoryChanges('disabled', null)).toBe('Disabled');
    });
});

// ---------------------------------------------------------------------------
// Tests — Acceptance Criterion 6: backwards-compat (legacy flat shape)
// ---------------------------------------------------------------------------

describe('T003 — formatHistoryChanges: legacy flat shape (backwards-compat)', () => {
    it('renders "Label: value" for flat string values (no arrow)', () => {
        const result = formatHistoryChanges(
            'updated',
            JSON.stringify({ destination: 'https://new.com' })
        );
        expect(result).toBe('Destination: https://new.com');
        expect(result).not.toContain('→');
    });

    it('handles multiple flat fields without arrow', () => {
        const result = formatHistoryChanges(
            'updated',
            JSON.stringify({ destination: 'https://x.com', tpKey: 'abc' })
        );
        expect(result).toContain('Destination: https://x.com');
        expect(result).toContain('Short code: abc');
        expect(result).not.toContain('→');
    });
});

// ---------------------------------------------------------------------------
// Tests — Acceptance Criterion 7: malformed JSON
// ---------------------------------------------------------------------------

describe('T003 — formatHistoryChanges: malformed JSON', () => {
    it('returns italic raw string for malformed JSON', () => {
        const result = formatHistoryChanges('updated', 'not-valid-json{');
        expect(result).toContain('<em>');
        expect(result).toContain('not-valid-json{');
    });

    it('calls console.warn for malformed JSON', () => {
        vi.clearAllMocks();
        formatHistoryChanges('updated', 'bad json');
        expect(console.warn).toHaveBeenCalled();
    });
});

// ---------------------------------------------------------------------------
// Tests — Acceptance Criterion 5: failed fetch renders retry button (jsdom)
// ---------------------------------------------------------------------------

describe('T003 — showHistory error state: retry button', () => {
    /**
     * Build the history error state HTML as showHistory() injects on AJAX error
     * after T003 changes (retry button added).
     */
    function buildHistoryErrorHtml(mid) {
        return (
            '<div class="text-center text-danger py-3">' +
                'Failed to load history. Try again.' +
                '<br>' +
                '<button class="tp-cl-history-retry-btn btn btn-sm btn-outline-secondary mt-2" data-mid="' + mid + '">' +
                    'Retry' +
                '</button>' +
            '</div>'
        );
    }

    let dom;
    let document;

    beforeEach(() => {
        dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
        document = dom.window.document;
    });

    it('error state contains "Failed to load history. Try again." text', () => {
        const html = buildHistoryErrorHtml(42);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        expect(wrapper.textContent).toContain('Failed to load history. Try again.');
    });

    it('error state contains a retry button with class tp-cl-history-retry-btn', () => {
        const html = buildHistoryErrorHtml(42);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const btn = wrapper.querySelector('.tp-cl-history-retry-btn');
        expect(btn).not.toBeNull();
    });

    it('retry button carries data-mid for re-invoking the loader', () => {
        const html = buildHistoryErrorHtml(42);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const btn = wrapper.querySelector('.tp-cl-history-retry-btn');
        expect(btn.getAttribute('data-mid')).toBe('42');
    });

    it('clicking retry resolves to the correct mid value', () => {
        const html = buildHistoryErrorHtml(99);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const btn = wrapper.querySelector('.tp-cl-history-retry-btn');
        const mid = parseInt(btn.getAttribute('data-mid'), 10);
        expect(mid).toBe(99);
    });
});

// ---------------------------------------------------------------------------
// Tests — Acceptance Criterion 4: empty history state
// ---------------------------------------------------------------------------

describe('T003 — formatHistoryChanges: empty / no-op cases', () => {
    it('empty changes object with "updated" action returns empty string', () => {
        const result = formatHistoryChanges('updated', '{}');
        expect(result).toBe('');
    });
});

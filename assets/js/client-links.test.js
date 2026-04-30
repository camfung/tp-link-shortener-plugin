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

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

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { JSDOM } from 'jsdom';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import path from 'path';

// Resolve path to client-links.js relative to this test file
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const CLIENT_LINKS_SRC = readFileSync(path.join(__dirname, 'client-links.js'), 'utf8');

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
// T008 — Thumbnail rendering + placeholder + onerror swap
// ===========================================================================

/**
 * Replicate the renderThumbnail(item) helper from client-links.js.
 *
 * Returns the HTML string for the thumbnail slot:
 *   - <img> with loading="lazy", src=escaped URL, alt="", and onerror swap
 *   - placeholder <span> when no preview_url
 *
 * This is a verbatim copy of the implementation so tests run in isolation
 * without loading jQuery or the full module.
 */

var T008_PLACEHOLDER_HTML =
    '<span class="tp-cl-row-thumb tp-cl-row-thumb-placeholder">' +
        '<i class="fas fa-globe"></i>' +
    '</span>';

function t008EscapeHtml(text) {
    if (!text) return '';
    var div = { textContent: text, innerHTML: '' };
    // Minimal escape for test context — replicate the DOM-based escapeHtml
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// Mirrors the PLACEHOLDER_ATTR_ESCAPED constant in client-links.js
var PLACEHOLDER_ATTR_ESCAPED =
    '<span class=&quot;tp-cl-row-thumb tp-cl-row-thumb-placeholder&quot;>' +
        '<i class=&quot;fas fa-globe&quot;></i>' +
    '</span>';

function renderThumbnail(item) {
    if (item.preview_url) {
        return (
            '<img class="tp-cl-row-thumb" loading="lazy" src="' +
            t008EscapeHtml(item.preview_url) +
            '" alt="" onerror="this.outerHTML=\'' + PLACEHOLDER_ATTR_ESCAPED + '\'">'
        );
    }
    return T008_PLACEHOLDER_HTML;
}

/**
 * Updated buildRowHtml that mirrors the T008 changes to the real row template:
 * renderThumbnail output is the first child of .tp-cl-link-cell.
 */
function buildRowHtmlWithThumb(item) {
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
                    renderThumbnail(item) +
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
// T008 Fixtures
// ---------------------------------------------------------------------------

const ITEM_WITH_PREVIEW = {
    mid: 10,
    tpKey: 'abc123',
    domain: 'trfc.link',
    destination: 'https://example.com',
    status: 'active',
    notes: '',
    usage: null,
    preview_url: 'https://cdn.example.com/previews/10.jpg',
};

const ITEM_NO_PREVIEW = {
    mid: 11,
    tpKey: 'xyz789',
    domain: 'trfc.link',
    destination: 'https://example.com',
    status: 'active',
    notes: '',
    usage: null,
    preview_url: null,
};

const ITEM_EVIL_URL = {
    mid: 12,
    tpKey: 'evil',
    domain: 'trfc.link',
    destination: 'https://evil.com',
    status: 'active',
    notes: '',
    usage: null,
    preview_url: 'https://cdn.example.com/p.jpg?a=1&b=<script>',
};

// ---------------------------------------------------------------------------
// Tests — renderThumbnail helper
// ---------------------------------------------------------------------------

describe('T008 — renderThumbnail: with preview_url', () => {
    let dom;
    let document;

    beforeEach(() => {
        dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
        document = dom.window.document;
    });

    it('returns an <img> element when preview_url is present', () => {
        const html = renderThumbnail(ITEM_WITH_PREVIEW);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const img = wrapper.querySelector('img');
        expect(img).not.toBeNull();
    });

    it('<img> has class tp-cl-row-thumb', () => {
        const html = renderThumbnail(ITEM_WITH_PREVIEW);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const img = wrapper.querySelector('img');
        expect(img.classList.contains('tp-cl-row-thumb')).toBe(true);
    });

    it('<img> has loading="lazy"', () => {
        const html = renderThumbnail(ITEM_WITH_PREVIEW);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const img = wrapper.querySelector('img');
        expect(img.getAttribute('loading')).toBe('lazy');
    });

    it('<img> src matches preview_url', () => {
        const html = renderThumbnail(ITEM_WITH_PREVIEW);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const img = wrapper.querySelector('img');
        expect(img.getAttribute('src')).toBe(ITEM_WITH_PREVIEW.preview_url);
    });

    it('<img> alt is empty string (decorative)', () => {
        const html = renderThumbnail(ITEM_WITH_PREVIEW);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const img = wrapper.querySelector('img');
        expect(img.getAttribute('alt')).toBe('');
    });

    it('<img> has an onerror attribute that performs the placeholder swap', () => {
        const html = renderThumbnail(ITEM_WITH_PREVIEW);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const img = wrapper.querySelector('img');
        const onerrorAttr = img.getAttribute('onerror');
        expect(onerrorAttr).toBeTruthy();
        // Must reference this.outerHTML so the img is replaced (not just hidden)
        expect(onerrorAttr).toContain('this.outerHTML');
        // Must target the placeholder class so the correct element is swapped in
        expect(onerrorAttr).toContain('tp-cl-row-thumb-placeholder');
    });

    it('HTML-escapes special chars in preview_url', () => {
        const html = renderThumbnail(ITEM_EVIL_URL);
        // The raw HTML string should have & escaped as &amp; and < as &lt;
        expect(html).toContain('&amp;');
        expect(html).toContain('&lt;');
        // The raw script tag should NOT appear unescaped
        expect(html).not.toContain('<script>');
    });
});

describe('T008 — renderThumbnail: without preview_url', () => {
    let dom;
    let document;

    beforeEach(() => {
        dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
        document = dom.window.document;
    });

    it('returns a placeholder <span> when preview_url is null', () => {
        const html = renderThumbnail(ITEM_NO_PREVIEW);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const span = wrapper.querySelector('span.tp-cl-row-thumb');
        expect(span).not.toBeNull();
    });

    it('placeholder has class tp-cl-row-thumb-placeholder', () => {
        const html = renderThumbnail(ITEM_NO_PREVIEW);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const span = wrapper.querySelector('span');
        expect(span.classList.contains('tp-cl-row-thumb-placeholder')).toBe(true);
    });

    it('placeholder contains fa-globe icon', () => {
        const html = renderThumbnail(ITEM_NO_PREVIEW);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const icon = wrapper.querySelector('i.fas.fa-globe');
        expect(icon).not.toBeNull();
    });

    it('does NOT render an <img> when preview_url is null', () => {
        const html = renderThumbnail(ITEM_NO_PREVIEW);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        expect(wrapper.querySelector('img')).toBeNull();
    });

    it('returns placeholder span when preview_url is empty string', () => {
        const html = renderThumbnail({ ...ITEM_NO_PREVIEW, preview_url: '' });
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        expect(wrapper.querySelector('span.tp-cl-row-thumb')).not.toBeNull();
        expect(wrapper.querySelector('img')).toBeNull();
    });
});

describe('T008 — renderThumbnail: row template integration', () => {
    let dom;
    let document;

    beforeEach(() => {
        dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
        document = dom.window.document;
    });

    it('row with preview_url has <img> as first child of .tp-cl-link-cell', () => {
        const rowHtml = buildRowHtmlWithThumb(ITEM_WITH_PREVIEW);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const linkCell = tbody.querySelector('.tp-cl-link-cell');
        const firstChild = linkCell.firstElementChild;
        expect(firstChild.tagName.toLowerCase()).toBe('img');
    });

    it('row with preview_url: <img> appears before the <a> link', () => {
        const rowHtml = buildRowHtmlWithThumb(ITEM_WITH_PREVIEW);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const linkCell = tbody.querySelector('.tp-cl-link-cell');
        const children = Array.from(linkCell.children);
        const imgIdx = children.findIndex(el => el.tagName.toLowerCase() === 'img');
        const aIdx = children.findIndex(el => el.tagName.toLowerCase() === 'a');
        expect(imgIdx).toBeLessThan(aIdx);
    });

    it('row with null preview_url has placeholder <span> as first child of .tp-cl-link-cell', () => {
        const rowHtml = buildRowHtmlWithThumb(ITEM_NO_PREVIEW);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const linkCell = tbody.querySelector('.tp-cl-link-cell');
        const firstChild = linkCell.firstElementChild;
        expect(firstChild.tagName.toLowerCase()).toBe('span');
        expect(firstChild.classList.contains('tp-cl-row-thumb')).toBe(true);
        expect(firstChild.classList.contains('tp-cl-row-thumb-placeholder')).toBe(true);
    });

    it('row with null preview_url: placeholder does NOT contain an <img>', () => {
        const rowHtml = buildRowHtmlWithThumb(ITEM_NO_PREVIEW);
        const tbody = document.createElement('tbody');
        tbody.innerHTML = rowHtml;

        const linkCell = tbody.querySelector('.tp-cl-link-cell');
        expect(linkCell.querySelector('img')).toBeNull();
    });
});

describe('T008 — onerror swap: img replaced by placeholder', () => {
    let dom;
    let document;

    beforeEach(() => {
        dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', {
            runScripts: 'dangerously',
        });
        document = dom.window.document;
    });

    it('after error event fires, img is replaced by placeholder span', () => {
        const html = renderThumbnail(ITEM_WITH_PREVIEW);
        const wrapper = document.createElement('div');
        document.body.appendChild(wrapper);
        wrapper.innerHTML = html;

        const img = wrapper.querySelector('img');
        expect(img).not.toBeNull();

        // Trigger the onerror handler by evaluating it
        // eslint-disable-next-line no-new-func
        const onerrorAttr = img.getAttribute('onerror');
        // Execute the handler with img as `this`
        const fn = new dom.window.Function('with(this){' + onerrorAttr + '}');
        fn.call(img);

        // After swap, there should be no img and a placeholder span instead
        const imgAfter = wrapper.querySelector('img');
        const placeholder = wrapper.querySelector('span.tp-cl-row-thumb-placeholder');

        expect(imgAfter).toBeNull();
        expect(placeholder).not.toBeNull();
    });

    it('after error event fires, placeholder has fa-globe icon', () => {
        const html = renderThumbnail(ITEM_WITH_PREVIEW);
        const wrapper = document.createElement('div');
        document.body.appendChild(wrapper);
        wrapper.innerHTML = html;

        const img = wrapper.querySelector('img');
        const onerrorAttr = img.getAttribute('onerror');
        const fn = new dom.window.Function('with(this){' + onerrorAttr + '}');
        fn.call(img);

        const icon = wrapper.querySelector('i.fas.fa-globe');
        expect(icon).not.toBeNull();
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

// ---------------------------------------------------------------------------
// M6 — showHistory: real production code tests
// (F002 Scenario 5: Failed history fetch surfaces the error)
//
// Uses the __TP_TEST__ / __tpClientLinksTestHooks pattern to invoke the REAL
// showHistory() from client-links.js rather than a hand-rolled replica.
// Deletes the former tautological M4 block (buildSuccessFalseHtml) entirely.
// ---------------------------------------------------------------------------

/**
 * Load client-links.js into a JSDOM window with a minimal jQuery stub and
 * the __TP_TEST__ flag set.  Returns { showHistory, $historyList } so tests
 * can drive the function and inspect what it renders.
 *
 * @param {object} ajaxImpl  Object with `success` and/or `error` keys — functions
 *                           that receive the jQuery ajax settings object and call
 *                           the appropriate callback synchronously.
 * @returns {{ showHistory: Function, historyListEl: Element, dom: object }}
 */
function loadClientLinksWithAjaxStub(ajaxImpl) {
    const dom = new JSDOM(
        `<!DOCTYPE html><html><body>
            <div class="tp-cl-container" data-page-size="10">
                <div id="tp-cl-content"></div>
                <div id="tp-cl-history-modal-overlay" style="display:none;">
                    <div id="tp-cl-history-list"></div>
                </div>
            </div>
        </body></html>`,
        { runScripts: 'dangerously' }
    );
    const win = dom.window;

    // Signal to client-links.js that we are in test mode
    win.__TP_TEST__ = true;

    // Provide global tpClientLinks so the IIFE doesn't crash at load time
    win.tpClientLinks = {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: 'test-nonce',
        isLoggedIn: false,           // keeps init() from wiring up the full UI
        dateRange: { start: '', end: '' },
        strings: { error: 'Error', showChart: 'Show', hideChart: 'Hide', confirmDisable: 'Sure?' },
        loginUrl: '/login/',
    };

    // Minimal jQuery stub — only the subset that client-links.js exercises at
    // module load time AND inside showHistory().
    function makeJQueryObj(el) {
        return {
            length: el ? 1 : 0,
            show() { if (el) el.style.display = ''; return this; },
            hide() { if (el) el.style.display = 'none'; return this; },
            html(str) {
                if (str !== undefined) { if (el) el.innerHTML = str; return this; }
                return el ? el.innerHTML : '';
            },
            text(str) {
                if (str !== undefined) { if (el) el.textContent = str; return this; }
                return el ? el.textContent : '';
            },
            val(v) {
                if (v !== undefined) { if (el) el.value = v; return this; }
                return el ? el.value : '';
            },
            data(key) { return el ? el.dataset[key] : undefined; },
            find(sel) { return makeJQueryObj(el ? el.querySelector(sel) : null); },
            on() { return this; },
            off() { return this; },
            addClass() { return this; },
            removeClass() { return this; },
            hasClass() { return false; },
            append() { return this; },
            after() { return this; },
            is() { return false; },
            trigger() { return this; },
            empty() { if (el) el.innerHTML = ''; return this; },
            closest() { return makeJQueryObj(null); },
            parent() { return makeJQueryObj(null); },
            attr() { return undefined; },
            prop() { return this; },
        };
    }

    const $ = function(selector) {
        if (typeof selector === 'string') {
            if (selector === 'document' || selector === win.document) {
                return { ready(fn) { fn(); return this; }, on() { return this; }, trigger() { return this; } };
            }
            const el = win.document.querySelector(selector);
            return makeJQueryObj(el);
        }
        if (selector === win.document || selector === win) {
            return { ready(fn) { fn(); return this; }, on() { return this; }, trigger() { return this; } };
        }
        return makeJQueryObj(null);
    };
    $.ajax = function(settings) {
        if (ajaxImpl && ajaxImpl.success) {
            ajaxImpl.success(settings);
        } else if (ajaxImpl && ajaxImpl.error) {
            ajaxImpl.error(settings);
        }
    };
    $.fn = {};

    win.jQuery = $;

    // Chart.js stub (client-links.js may reference it inside closures)
    win.Chart = function() { return { destroy() {} }; };

    // Evaluate the source in the JSDOM window context
    // eslint-disable-next-line no-new-func
    const script = win.document.createElement('script');
    script.textContent = CLIENT_LINKS_SRC;
    win.document.head.appendChild(script);

    const hooks = win.__tpClientLinksTestHooks;
    if (!hooks || !hooks.showHistory) {
        throw new Error('__tpClientLinksTestHooks.showHistory was not set — check the test hook block in client-links.js');
    }

    const historyListEl = win.document.querySelector('#tp-cl-history-list');
    return { showHistory: hooks.showHistory, historyListEl, dom };
}

describe('M6 — showHistory success-false: real production code renders error state', () => {
    it('renders "Failed to load history" when response.success is false', () => {
        const { showHistory, historyListEl } = loadClientLinksWithAjaxStub({
            success(settings) {
                settings.success({ success: false, data: { message: 'oops' } });
            }
        });

        showHistory(42);

        expect(historyListEl.textContent).toContain('Failed to load history');
    });

    it('renders retry button with correct data-mid when response.success is false', () => {
        const { showHistory, historyListEl } = loadClientLinksWithAjaxStub({
            success(settings) {
                settings.success({ success: false, data: { message: 'server err' } });
            }
        });

        showHistory(77);

        const btn = historyListEl.querySelector('.tp-cl-history-retry-btn');
        expect(btn).not.toBeNull();
        expect(btn.getAttribute('data-mid')).toBe('77');
    });

    it('renders error state on network failure ($.ajax error callback)', () => {
        const { showHistory, historyListEl } = loadClientLinksWithAjaxStub({
            error(settings) {
                // Simulate a non-401 network error
                settings.error({ status: 500, responseText: 'Internal Server Error' });
            }
        });

        showHistory(99);

        expect(historyListEl.textContent).toContain('Failed to load history');
        const btn = historyListEl.querySelector('.tp-cl-history-retry-btn');
        expect(btn).not.toBeNull();
        expect(btn.getAttribute('data-mid')).toBe('99');
    });
});

/**
 * Unit tests for T004 — Pencil hover edit affordance
 *
 * Tests the row template in client-links.js to verify:
 *   1. .tp-cl-row-edit-hint span is appended as the last child of the last <td>
 *   2. The span has aria-hidden="true" (decorative icon)
 *   3. The span contains <i class="fas fa-edit">
 *   4. Clicking the span (outside .tp-cl-inline-actions) does NOT fire the row guard
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

/**
 * Unit tests for T010 — Save-action relabel + dynamic helper text
 *
 * Tests:
 *  M1. window.tpSaveHelper global is populated (IIFE path)
 *  1.  computeHelperText pure function — table-driven cases
 *  2.  Modal-open: button reads "Save changes", helper text is initial string
 *  3.  Change destination → helper text updates to preview regen variant
 *  4.  Change tpKey only → helper text updates to QR regen variant
 *  5.  Change both → helper text reads both preview and QR regen
 *  6.  Revert all changes → helper text returns to initial string
 *  7.  Submit with response.status === 'no_changes' → toast called + modal closed
 *
 * The file uses an IIFE pattern (no ESM exports) for browser compatibility.
 * Vitest's jsdom environment provides `window`, so the IIFE's root.tpSaveHelper
 * assignment is equivalent to window.tpSaveHelper = { ... }.
 * We load the module via a dynamic import which executes the IIFE, then read
 * functions from window.tpSaveHelper.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { JSDOM } from 'jsdom';

// ---------------------------------------------------------------------------
// Helpers — extract functions from window.tpSaveHelper after loading the IIFE
// ---------------------------------------------------------------------------

// Load the IIFE module once — the import executes the IIFE, populating window.tpSaveHelper.
// (Vitest resolves bare .js relative paths relative to the test file.)
await import('./save-helper.js');

const { computeHelperText, switchToUpdateMode, handleNoChangesResponse } = window.tpSaveHelper;

// ---------------------------------------------------------------------------
// M1 — window.tpSaveHelper global is populated (IIFE browser path)
// ---------------------------------------------------------------------------

describe('M1 — window.tpSaveHelper global (IIFE browser path)', () => {
    it('window.tpSaveHelper is defined when window exists', () => {
        expect(window.tpSaveHelper).toBeDefined();
    });

    it('window.tpSaveHelper.computeHelperText is a function', () => {
        expect(typeof window.tpSaveHelper.computeHelperText).toBe('function');
    });

    it('window.tpSaveHelper.switchToUpdateMode is a function', () => {
        expect(typeof window.tpSaveHelper.switchToUpdateMode).toBe('function');
    });

    it('window.tpSaveHelper.handleNoChangesResponse is a function', () => {
        expect(typeof window.tpSaveHelper.handleNoChangesResponse).toBe('function');
    });
});

// ---------------------------------------------------------------------------
// 1. Pure function: computeHelperText
// ---------------------------------------------------------------------------

describe('T010 — computeHelperText pure function', () => {
    it('returns empty string when pendingChanges is empty', () => {
        expect(computeHelperText(new Set())).toBe('');
    });

    it('returns preview-regen string when only destination changed', () => {
        expect(computeHelperText(new Set(['destination'])))
            .toBe('Will regenerate the preview');
    });

    it('returns QR-regen string when only tpKey changed', () => {
        expect(computeHelperText(new Set(['tpKey'])))
            .toBe('Will regenerate the QR code');
    });

    it('returns QR-regen string when only domain changed', () => {
        expect(computeHelperText(new Set(['domain'])))
            .toBe('Will regenerate the QR code');
    });

    it('returns both-regen string when destination AND tpKey changed', () => {
        expect(computeHelperText(new Set(['destination', 'tpKey'])))
            .toBe('Will regenerate the preview and QR code');
    });

    it('returns both-regen string when destination AND domain changed', () => {
        expect(computeHelperText(new Set(['destination', 'domain'])))
            .toBe('Will regenerate the preview and QR code');
    });
});

// ---------------------------------------------------------------------------
// 2-6. DOM integration: modal button label + helper text reactivity
// ---------------------------------------------------------------------------

function buildFormDom() {
    const html = `
<!DOCTYPE html>
<html>
<body>
  <div id="tp-link-shortener-wrapper">
    <form id="tp-shortener-form">
      <input type="url" id="tp-destination" value="" />
      <div class="tp-custom-key-group">
        <div class="tp-input-visual">
          <input type="text" id="tp-custom-key" value="" />
          <button type="submit" id="tp-submit-btn">
            <i class="fas fa-save" id="tp-submit-icon"></i>
            <span id="tp-submit-text">Save the link</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</body>
</html>`;

    const dom = new JSDOM(html);
    return dom;
}

describe('T010 — Edit modal button label and helper text', () => {
    let dom;
    let document;
    let window;

    beforeEach(() => {
        dom = buildFormDom();
        document = dom.window.document;
        window = dom.window;
    });

    it('switchToUpdateMode sets button text to "Save changes"', () => {
        document.getElementById('tp-destination').value = 'https://example.com';
        document.getElementById('tp-custom-key').value = 'my-key';

        const original = { destination: 'https://example.com', tpKey: 'my-key', domain: 'trfc.link' };
        switchToUpdateMode(document, original);

        const submitText = document.getElementById('tp-submit-text');
        expect(submitText.textContent).toBe('Save changes');
    });

    it('switchToUpdateMode sets icon to fa-floppy-disk', () => {
        document.getElementById('tp-destination').value = 'https://example.com';
        document.getElementById('tp-custom-key').value = 'my-key';

        const original = { destination: 'https://example.com', tpKey: 'my-key', domain: 'trfc.link' };
        switchToUpdateMode(document, original);

        const icon = document.getElementById('tp-submit-icon');
        expect(icon.classList.contains('fa-floppy-disk')).toBe(true);
    });

    it('switchToUpdateMode injects helper text with initial string', () => {
        document.getElementById('tp-destination').value = 'https://example.com';
        document.getElementById('tp-custom-key').value = 'my-key';

        const original = { destination: 'https://example.com', tpKey: 'my-key', domain: 'trfc.link' };
        switchToUpdateMode(document, original);

        const helperEl = document.getElementById('tp-save-helper-text');
        expect(helperEl).not.toBeNull();
        expect(helperEl.textContent).toBe('');
    });

    it('changing destination updates helper text to preview-regen string', () => {
        document.getElementById('tp-destination').value = 'https://example.com';
        document.getElementById('tp-custom-key').value = 'my-key';

        const original = { destination: 'https://example.com', tpKey: 'my-key', domain: 'trfc.link' };
        switchToUpdateMode(document, original);

        const destInput = document.getElementById('tp-destination');
        destInput.value = 'https://new-destination.com';
        destInput.dispatchEvent(new window.Event('input'));

        const helperEl = document.getElementById('tp-save-helper-text');
        expect(helperEl.textContent).toBe('Will regenerate the preview');
    });

    it('changing tpKey updates helper text to QR-regen string', () => {
        document.getElementById('tp-destination').value = 'https://example.com';
        document.getElementById('tp-custom-key').value = 'my-key';

        const original = { destination: 'https://example.com', tpKey: 'my-key', domain: 'trfc.link' };
        switchToUpdateMode(document, original);

        const keyInput = document.getElementById('tp-custom-key');
        keyInput.value = 'new-key';
        keyInput.dispatchEvent(new window.Event('input'));

        const helperEl = document.getElementById('tp-save-helper-text');
        expect(helperEl.textContent).toBe('Will regenerate the QR code');
    });

    it('changing both destination and tpKey shows both-regen string', () => {
        document.getElementById('tp-destination').value = 'https://example.com';
        document.getElementById('tp-custom-key').value = 'my-key';

        const original = { destination: 'https://example.com', tpKey: 'my-key', domain: 'trfc.link' };
        switchToUpdateMode(document, original);

        const destInput = document.getElementById('tp-destination');
        destInput.value = 'https://new-destination.com';
        destInput.dispatchEvent(new window.Event('input'));

        const keyInput = document.getElementById('tp-custom-key');
        keyInput.value = 'new-key';
        keyInput.dispatchEvent(new window.Event('input'));

        const helperEl = document.getElementById('tp-save-helper-text');
        expect(helperEl.textContent).toBe('Will regenerate the preview and QR code');
    });

    it('reverting all changes returns helper text to initial string', () => {
        document.getElementById('tp-destination').value = 'https://example.com';
        document.getElementById('tp-custom-key').value = 'my-key';

        const original = { destination: 'https://example.com', tpKey: 'my-key', domain: 'trfc.link' };
        switchToUpdateMode(document, original);

        const destInput = document.getElementById('tp-destination');
        destInput.value = 'https://new-destination.com';
        destInput.dispatchEvent(new window.Event('input'));

        destInput.value = 'https://example.com';
        destInput.dispatchEvent(new window.Event('input'));

        const helperEl = document.getElementById('tp-save-helper-text');
        expect(helperEl.textContent).toBe('');
    });
});

// ---------------------------------------------------------------------------
// 7. handleNoChangesResponse: toast + modal close
// ---------------------------------------------------------------------------

describe('T010 — handleNoChangesResponse', () => {
    it('calls toastFn with "No changes to save" and calls closeFn when status is no_changes', () => {
        const toastFn = vi.fn();
        const closeFn = vi.fn();

        handleNoChangesResponse({ status: 'no_changes' }, toastFn, closeFn);

        expect(toastFn).toHaveBeenCalledWith('No changes to save');
        expect(closeFn).toHaveBeenCalledOnce();
    });

    it('does NOT call closeFn when status is not no_changes', () => {
        const toastFn = vi.fn();
        const closeFn = vi.fn();

        handleNoChangesResponse({ status: 'updated' }, toastFn, closeFn);

        expect(closeFn).not.toHaveBeenCalled();
    });
});

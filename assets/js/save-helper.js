/**
 * save-helper.js — T010: Save-action relabel + dynamic helper text
 *
 * Exposes three functions via the window.tpSaveHelper global so that
 * client-links.js can call them without a bundler or module system.
 *
 * Browser consumption:
 *   window.tpSaveHelper.computeHelperText(pendingChanges)
 *   window.tpSaveHelper.switchToUpdateMode(doc, original)
 *   window.tpSaveHelper.handleNoChangesResponse(data, toastFn, closeFn)
 *
 * Test consumption (Vitest / Node.js):
 *   The IIFE runs immediately, setting window.tpSaveHelper (jsdom provides window).
 *   Tests access the same functions via window.tpSaveHelper.* or via the named
 *   module.exports set by the CommonJS guard at the bottom (for non-jsdom envs).
 */
(function (root) {
    'use strict';

    // ---------------------------------------------------------------------------
    // Helper text strings (F005 Scenario 4)
    // ---------------------------------------------------------------------------

    var TEXT_BASE    = '';
    var TEXT_PREVIEW = 'Will regenerate the preview';
    var TEXT_QR      = 'Will regenerate the QR code';
    var TEXT_BOTH    = 'Will regenerate the preview and QR code';

    /**
     * Compute the helper text string based on the set of pending field changes.
     *
     * @param {Set<string>} pendingChanges - Set of field keys that differ from original.
     *   Tracked keys: 'destination', 'tpKey', 'domain'
     * @returns {string}
     */
    function computeHelperText(pendingChanges) {
        var hasDestination = pendingChanges.has('destination');
        var hasQr = pendingChanges.has('tpKey') || pendingChanges.has('domain');

        if (hasDestination && hasQr) {
            return TEXT_BOTH;
        }
        if (hasDestination) {
            return TEXT_PREVIEW;
        }
        if (hasQr) {
            return TEXT_QR;
        }
        return TEXT_BASE;
    }

    // ---------------------------------------------------------------------------
    // DOM helpers
    // ---------------------------------------------------------------------------

    /**
     * Inject (or reuse) the helper text element below the submit button group.
     *
     * @param {Document} doc
     * @returns {Element|null}
     */
    function ensureHelperTextEl(doc) {
        var existing = doc.getElementById('tp-save-helper-text');
        if (existing) {
            return existing;
        }

        var inputVisual = doc.querySelector('.tp-custom-key-group .tp-input-visual');
        if (!inputVisual) {
            return null;
        }

        var el = doc.createElement('div');
        el.id = 'tp-save-helper-text';
        el.className = 'tp-save-helper-text';
        el.setAttribute('aria-live', 'polite');

        if (inputVisual.nextSibling) {
            inputVisual.parentNode.insertBefore(el, inputVisual.nextSibling);
        } else {
            inputVisual.parentNode.appendChild(el);
        }

        return el;
    }

    // ---------------------------------------------------------------------------
    // switchToUpdateMode
    // ---------------------------------------------------------------------------

    /**
     * Configure the submit button and helper text for update mode.
     *
     * @param {Document} doc      - The document (or jsdom document in tests)
     * @param {Object}   original - Snapshot of the record being edited:
     *                              { destination, tpKey, domain }
     */
    function switchToUpdateMode(doc, original) {
        var submitText = doc.getElementById('tp-submit-text');
        var submitIcon = doc.getElementById('tp-submit-icon');

        if (submitText) {
            submitText.textContent = 'Save changes';
        }
        if (submitIcon) {
            submitIcon.classList.remove('fa-save', 'fa-edit', 'fa-link');
            submitIcon.classList.add('fa-floppy-disk');
        }

        var helperEl = ensureHelperTextEl(doc);
        if (!helperEl) {
            return;
        }

        var pendingChanges = new Set();
        helperEl.textContent = computeHelperText(pendingChanges);

        var destInput = doc.getElementById('tp-destination');
        var keyInput  = doc.getElementById('tp-custom-key');

        function refresh() {
            pendingChanges = new Set();

            var curDest = destInput ? destInput.value : '';
            var curKey  = keyInput ? keyInput.value : '';

            if (curDest !== original.destination) {
                pendingChanges.add('destination');
            }
            if (curKey !== original.tpKey) {
                pendingChanges.add('tpKey');
            }

            helperEl.textContent = computeHelperText(pendingChanges);
        }

        if (destInput) {
            if (destInput._tpHelperListener) {
                destInput.removeEventListener('input', destInput._tpHelperListener);
            }
            destInput._tpHelperListener = refresh;
            destInput.addEventListener('input', refresh);
        }

        if (keyInput) {
            if (keyInput._tpHelperListener) {
                keyInput.removeEventListener('input', keyInput._tpHelperListener);
            }
            keyInput._tpHelperListener = refresh;
            keyInput.addEventListener('input', refresh);
        }
    }

    // ---------------------------------------------------------------------------
    // handleNoChangesResponse
    // ---------------------------------------------------------------------------

    /**
     * Handle the server's 'no_changes' response after a save attempt.
     *
     * @param {Object}   data     - Server response data object (response.data from AJAX)
     * @param {Function} toastFn  - Callable that shows a toast: toastFn(message)
     * @param {Function} closeFn  - Callable that closes the modal
     */
    function handleNoChangesResponse(data, toastFn, closeFn) {
        if (data && data.status === 'no_changes') {
            toastFn('No changes to save');
            closeFn();
        }
    }

    // ---------------------------------------------------------------------------
    // Export
    // ---------------------------------------------------------------------------

    var api = {
        computeHelperText: computeHelperText,
        switchToUpdateMode: switchToUpdateMode,
        handleNoChangesResponse: handleNoChangesResponse,
    };

    // Browser global
    root.tpSaveHelper = api;

    // CommonJS / Node.js (for Vitest when not running under jsdom)
    if (typeof module === 'object' && typeof module.exports === 'object') {
        module.exports = api;
    }

}(typeof globalThis !== 'undefined' ? globalThis : (typeof window !== 'undefined' ? window : this)));

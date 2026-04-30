/**
 * save-helper.js — T010: Save-action relabel + dynamic helper text
 *
 * Exports three pure/side-effect-isolated functions:
 *   computeHelperText(pendingChanges)  – pure; returns the correct helper string
 *   switchToUpdateMode(doc, original)  – wires button label + helper text + input listeners
 *   handleNoChangesResponse(data, toastFn, closeFn) – surfaces no-op result from server
 */

// ---------------------------------------------------------------------------
// Helper text strings (F005 Scenario 4)
// ---------------------------------------------------------------------------

const TEXT_BASE   = "Updates this link's record";
const TEXT_PREVIEW = "Updates this link's record and regenerates the preview";
const TEXT_QR      = "Updates this link's record and regenerates the QR code";
const TEXT_BOTH    = "Updates this link's record and regenerates the preview and QR code";

/**
 * Compute the helper text string based on the set of pending field changes.
 *
 * @param {Set<string>} pendingChanges - Set of field keys that differ from original.
 *   Tracked keys: 'destination', 'tpKey', 'domain'
 * @returns {string}
 */
export function computeHelperText(pendingChanges) {
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
 * The element is placed after the `.tp-input-visual` div that contains the
 * submit button, inside `.tp-custom-key-group`. If the element already exists
 * it is reused so that repeated calls to switchToUpdateMode are idempotent.
 *
 * @param {Document} doc
 * @returns {Element|null} the helper text element, or null if DOM not ready
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

    // Insert right after .tp-input-visual
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
 * Called from frontend.js's switchToUpdateMode() when the edit modal opens.
 *
 * @param {Document} doc      - The document (or jsdom document in tests)
 * @param {Object}   original - Snapshot of the record being edited:
 *                              { destination, tpKey, domain }
 */
export function switchToUpdateMode(doc, original) {
    // 1. Relabel button
    var submitText = doc.getElementById('tp-submit-text');
    var submitIcon = doc.getElementById('tp-submit-icon');

    if (submitText) {
        submitText.textContent = 'Save changes';
    }
    if (submitIcon) {
        submitIcon.classList.remove('fa-save', 'fa-edit', 'fa-link');
        submitIcon.classList.add('fa-floppy-disk');
    }

    // 2. Inject helper text element
    var helperEl = ensureHelperTextEl(doc);
    if (!helperEl) {
        return; // DOM not ready — bail gracefully
    }

    var pendingChanges = new Set();
    helperEl.textContent = computeHelperText(pendingChanges);

    // 3. Wire input listeners
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

    // Remove previously bound listeners to keep things clean on re-open
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
export function handleNoChangesResponse(data, toastFn, closeFn) {
    if (data && data.status === 'no_changes') {
        toastFn('No changes to save');
        closeFn();
    }
}

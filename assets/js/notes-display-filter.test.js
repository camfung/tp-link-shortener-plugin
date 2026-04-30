/**
 * T001: Client-side notes display filter tests
 *
 * Scenario 3: Existing rows with legacy 'Created via WordPress plugin' notes
 * must render as if notes were empty (display-time filter, no API write).
 */

import { describe, it, expect } from 'vitest';

// ---------------------------------------------------------------------------
// Helper: mirrors the notes rendering logic from client-links.js line ~545
// This is extracted so we can unit-test the filtering decision in isolation.
// ---------------------------------------------------------------------------
const LEGACY_NOTES_TEXT = 'Created via WordPress plugin';

/**
 * Determines the effective display value for a notes string.
 * Returns null when the notes should be hidden (empty or legacy auto-text).
 * Returns the notes string when it should be displayed.
 *
 * @param {string|null|undefined} notes - The raw notes value from the API
 * @returns {string|null} Display value or null to hide
 */
function getDisplayNotes(notes) {
    if (!notes || notes === LEGACY_NOTES_TEXT) {
        return null;
    }
    return notes;
}

/**
 * Renders a notes cell HTML string, matching the logic at client-links.js:545.
 * This is the minimal reproduction of the template expression.
 */
function renderNotesHtml(notes) {
    const displayNotes = getDisplayNotes(notes);
    return displayNotes
        ? `<div class="tp-cl-dest-notes" title="${displayNotes}">${displayNotes}</div>`
        : '';
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('T001 - Notes display filter', () => {
    describe('Scenario 3: Legacy auto-text is hidden on existing rows', () => {
        it('should return null for the legacy auto-text string', () => {
            expect(getDisplayNotes('Created via WordPress plugin')).toBeNull();
        });

        it('should render empty string for legacy auto-text (no notes element in DOM)', () => {
            const html = renderNotesHtml('Created via WordPress plugin');
            expect(html).toBe('');
        });

        it('should not render a notes div for legacy auto-text', () => {
            const html = renderNotesHtml('Created via WordPress plugin');
            expect(html).not.toContain('tp-cl-dest-notes');
            expect(html).not.toContain('Created via WordPress plugin');
        });
    });

    describe('Scenario 1: Empty notes renders nothing', () => {
        it('should return null for empty string', () => {
            expect(getDisplayNotes('')).toBeNull();
        });

        it('should return null for null', () => {
            expect(getDisplayNotes(null)).toBeNull();
        });

        it('should return null for undefined', () => {
            expect(getDisplayNotes(undefined)).toBeNull();
        });

        it('should render empty string for empty notes', () => {
            expect(renderNotesHtml('')).toBe('');
            expect(renderNotesHtml(null)).toBe('');
        });
    });

    describe('Scenario 2: User-supplied notes are shown', () => {
        it('should return user-supplied notes verbatim', () => {
            expect(getDisplayNotes('campaign Q2-launch')).toBe('campaign Q2-launch');
        });

        it('should render a notes div for user-supplied notes', () => {
            const html = renderNotesHtml('campaign Q2-launch');
            expect(html).toContain('tp-cl-dest-notes');
            expect(html).toContain('campaign Q2-launch');
        });

        it('should render user notes that happen to contain similar but not exact legacy text', () => {
            const almostLegacy = 'Created via plugin';
            expect(getDisplayNotes(almostLegacy)).toBe(almostLegacy);
            const html = renderNotesHtml(almostLegacy);
            expect(html).toContain('tp-cl-dest-notes');
        });
    });
});

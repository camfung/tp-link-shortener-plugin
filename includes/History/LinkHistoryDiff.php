<?php

declare(strict_types=1);

namespace TP\History;

/**
 * LinkHistoryDiff — pure-PHP helper for computing field-level diffs.
 *
 * Produces a diff array shaped as:
 *   {"field": {"from": <old>, "to": <new>}}
 *
 * Only fields that actually changed are included. Unchanged fields are absent.
 *
 * Used by:
 *   - TP_API_Handler::ajax_update_link() (T002)
 *   - T009 server diff detection (F005) — reuses compute() for no-op detection
 *
 * @package TP\History
 */
class LinkHistoryDiff
{
    /**
     * Compute a field-level diff between two link state arrays.
     *
     * Both arrays must share the same keys. Only fields whose values differ
     * (strict string comparison) will appear in the result.
     *
     * @param array<string, string> $before Previous link field values
     * @param array<string, string> $after  New link field values
     * @return array<string, array{from: string, to: string}>
     */
    public static function compute(array $before, array $after): array
    {
        $diff = [];

        foreach ($before as $field => $oldValue) {
            $newValue = $after[$field] ?? $oldValue;

            if ($newValue !== $oldValue) {
                $diff[$field] = [
                    'from' => $oldValue,
                    'to'   => $newValue,
                ];
            }
        }

        // Also include fields present in $after but not in $before
        foreach ($after as $field => $newValue) {
            if (!array_key_exists($field, $before)) {
                $diff[$field] = [
                    'from' => '',
                    'to'   => $newValue,
                ];
            }
        }

        return $diff;
    }

    /**
     * Build the stable payload for a 'created' history entry.
     *
     * Shape: {"destination": "...", "tpKey": "..."} plus optional "notes".
     * Empty notes are omitted so the renderer can show "Created with destination ..."
     * cleanly without having to handle a blank notes field.
     *
     * @param string $destination The link's destination URL
     * @param string $tpKey       The short code / TP key
     * @param string $notes       User-supplied notes (omitted if empty)
     * @return array<string, string>
     */
    public static function buildCreatedPayload(string $destination, string $tpKey, string $notes): array
    {
        $payload = [
            'destination' => $destination,
            'tpKey'       => $tpKey,
        ];

        if ($notes !== '') {
            $payload['notes'] = $notes;
        }

        return $payload;
    }
}

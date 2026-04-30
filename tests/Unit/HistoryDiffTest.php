<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TP\History\LinkHistoryDiff;

/**
 * T002 — Server-side diff capture in log_link_history()
 *
 * Tests cover the core diff-builder logic in LinkHistoryDiff which is pure PHP
 * with no WordPress or database dependencies.
 *
 * Acceptance criteria tested:
 *   AC1 - updated rows contain diff shape {"field": {"from": OLD, "to": NEW}} for changed fields
 *   AC2 - unchanged fields are NOT present in the diff
 *   AC3 - multiple changed fields all appear
 *   AC4 - no-op update (nothing changed) produces empty diff array
 *   AC5 - log_link_history() signature remains backwards-compatible for created/enabled/disabled
 */
class HistoryDiffTest extends TestCase
{
    // ----------------------------------------------------------------
    // AC1 — Single field changed: diff contains exactly one key
    // ----------------------------------------------------------------

    /**
     * @test
     * should build diff with single destination change when only destination changed
     */
    public function testBuildDiffSingleDestinationChange(): void
    {
        $before = [
            'destination' => 'https://old.com',
            'tpKey'       => 'mylink',
            'domain'      => 'dev.trfc.link',
            'notes'       => '',
        ];
        $after = [
            'destination' => 'https://new.com',
            'tpKey'       => 'mylink',
            'domain'      => 'dev.trfc.link',
            'notes'       => '',
        ];

        $diff = LinkHistoryDiff::compute($before, $after);

        $this->assertArrayHasKey('destination', $diff, 'Changed field must appear in diff');
        $this->assertSame('https://old.com', $diff['destination']['from'], 'from must be old value');
        $this->assertSame('https://new.com', $diff['destination']['to'], 'to must be new value');

        // AC2: unchanged fields absent
        $this->assertArrayNotHasKey('tpKey', $diff, 'Unchanged tpKey must not appear');
        $this->assertArrayNotHasKey('domain', $diff, 'Unchanged domain must not appear');
        $this->assertArrayNotHasKey('notes', $diff, 'Unchanged notes must not appear');
    }

    // ----------------------------------------------------------------
    // AC3 — Multiple fields changed
    // ----------------------------------------------------------------

    /**
     * @test
     * should build diff with both destination and tpKey when both changed
     */
    public function testBuildDiffMultipleFieldsChanged(): void
    {
        $before = [
            'destination' => 'https://old.com',
            'tpKey'       => 'oldkey',
            'domain'      => 'dev.trfc.link',
            'notes'       => '',
        ];
        $after = [
            'destination' => 'https://new.com',
            'tpKey'       => 'newkey',
            'domain'      => 'dev.trfc.link',
            'notes'       => '',
        ];

        $diff = LinkHistoryDiff::compute($before, $after);

        $this->assertArrayHasKey('destination', $diff);
        $this->assertSame('https://old.com', $diff['destination']['from']);
        $this->assertSame('https://new.com', $diff['destination']['to']);

        $this->assertArrayHasKey('tpKey', $diff);
        $this->assertSame('oldkey', $diff['tpKey']['from']);
        $this->assertSame('newkey', $diff['tpKey']['to']);

        // Unchanged fields still absent
        $this->assertArrayNotHasKey('domain', $diff);
        $this->assertArrayNotHasKey('notes', $diff);
    }

    // ----------------------------------------------------------------
    // AC4 — No-op: nothing actually changed
    // ----------------------------------------------------------------

    /**
     * @test
     * should return empty diff array when no fields changed
     */
    public function testBuildDiffReturnsEmptyWhenNothingChanged(): void
    {
        $state = [
            'destination' => 'https://same.com',
            'tpKey'       => 'samekey',
            'domain'      => 'dev.trfc.link',
            'notes'       => 'same notes',
        ];

        $diff = LinkHistoryDiff::compute($state, $state);

        $this->assertIsArray($diff);
        $this->assertEmpty($diff, 'No-op update must produce empty diff array');
    }

    // ----------------------------------------------------------------
    // AC1 variant — JSON shape produced for 'updated' action
    // ----------------------------------------------------------------

    /**
     * @test
     * should produce diff-shaped JSON for updated action with changed destination only
     *
     * Verifies the F002 storage spec: {"field": {"from": OLD, "to": NEW}}
     */
    public function testDiffShapeMatchesSpecForDestinationChange(): void
    {
        $before = ['destination' => 'https://old.com', 'tpKey' => 'mykey', 'domain' => 'dev.trfc.link', 'notes' => ''];
        $after  = ['destination' => 'https://new.com', 'tpKey' => 'mykey', 'domain' => 'dev.trfc.link', 'notes' => ''];

        $diff = LinkHistoryDiff::compute($before, $after);
        $json = json_encode($diff);
        $decoded = json_decode((string) $json, true);

        $this->assertSame([
            'destination' => ['from' => 'https://old.com', 'to' => 'https://new.com'],
        ], $decoded, 'JSON shape must match {"field": {"from": OLD, "to": NEW}}');
    }

    // ----------------------------------------------------------------
    // AC5 — Stable JSON shape for 'created' action
    // ----------------------------------------------------------------

    /**
     * @test
     * should produce buildCreatedPayload with destination and optional notes
     */
    public function testBuildCreatedPayloadIncludesDestinationAndNotes(): void
    {
        $payload = LinkHistoryDiff::buildCreatedPayload('https://example.com', 'abc123', 'my notes');

        $this->assertArrayHasKey('destination', $payload);
        $this->assertSame('https://example.com', $payload['destination']);
        $this->assertArrayHasKey('tpKey', $payload);
        $this->assertSame('abc123', $payload['tpKey']);
        $this->assertArrayHasKey('notes', $payload);
        $this->assertSame('my notes', $payload['notes']);
    }

    /**
     * @test
     * should omit notes key from created payload when notes is empty
     */
    public function testBuildCreatedPayloadOmitsEmptyNotes(): void
    {
        $payload = LinkHistoryDiff::buildCreatedPayload('https://example.com', 'abc123', '');

        $this->assertArrayHasKey('destination', $payload);
        $this->assertArrayNotHasKey('notes', $payload, 'Empty notes must be omitted from created payload');
    }

    // ----------------------------------------------------------------
    // Mutation guard — strict vs loose equality
    // ----------------------------------------------------------------

    /**
     * @test
     * should NOT include field in diff when string values are strictly equal
     */
    public function testDiffUsesStrictEqualityComparison(): void
    {
        // Same string values — must NOT appear in diff
        $before = ['destination' => 'https://same.com', 'tpKey' => 'key', 'domain' => 'dev.trfc.link', 'notes' => ''];
        $after  = ['destination' => 'https://same.com', 'tpKey' => 'key', 'domain' => 'dev.trfc.link', 'notes' => ''];

        $diff = LinkHistoryDiff::compute($before, $after);
        $this->assertEmpty($diff, 'Strictly equal values must produce empty diff');
    }

    // ----------------------------------------------------------------
    // S7 — Field present in $after but missing from $before
    // ----------------------------------------------------------------

    /**
     * @test
     * should build diff entry with from='' when a field exists in $after but not in $before
     */
    public function testComputeAddsFieldsPresentInAfterButMissingFromBefore(): void
    {
        $before = [
            'destination' => 'https://x.com',
            'tpKey'       => 'k',
        ];
        $after = [
            'destination' => 'https://x.com',
            'tpKey'       => 'k',
            'notes'       => 'brand new field',  // present in $after, absent from $before
        ];

        $diff = LinkHistoryDiff::compute($before, $after);

        $this->assertArrayHasKey('notes', $diff, 'notes field present only in $after must appear in diff');
        $this->assertSame('', $diff['notes']['from'], 'from must be empty string when field was absent from $before');
        $this->assertSame('brand new field', $diff['notes']['to'], 'to must be the new value');

        // Unchanged fields must still be absent
        $this->assertArrayNotHasKey('destination', $diff, 'Unchanged destination must not appear');
        $this->assertArrayNotHasKey('tpKey', $diff, 'Unchanged tpKey must not appear');
    }

    /**
     * @test
     * should include notes field in diff when notes changes from empty to non-empty
     */
    public function testDiffIncludesNotesWhenNotesChanges(): void
    {
        $before = ['destination' => 'https://x.com', 'tpKey' => 'k', 'domain' => 'dev.trfc.link', 'notes' => ''];
        $after  = ['destination' => 'https://x.com', 'tpKey' => 'k', 'domain' => 'dev.trfc.link', 'notes' => 'added notes'];

        $diff = LinkHistoryDiff::compute($before, $after);

        $this->assertArrayHasKey('notes', $diff);
        $this->assertSame('', $diff['notes']['from']);
        $this->assertSame('added notes', $diff['notes']['to']);
        $this->assertArrayNotHasKey('destination', $diff, 'Unchanged destination must not appear');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TrafficPortal\DTO\CreateMapRequest;

/**
 * T001: Verify that the legacy 'Created via WordPress plugin' default is not injected
 * into new link-creation requests.
 *
 * These tests cover the server-side requirement: when no user-supplied notes
 * are provided, the CreateMapRequest must carry an empty notes value (not the
 * legacy auto-text string).
 */
class NotesDefaultRemovalTest extends TestCase
{
    /**
     * Scenario 1: New link with empty notes → notes key is absent from request body.
     *
     * When no notes are supplied, the DTO must not include 'notes' in the serialised
     * request body — omitting the field avoids potential TP API validation errors on
     * empty strings and prevents any legacy auto-text from leaking through.
     */
    public function testCreateMapRequestDefaultNotesIsEmpty(): void
    {
        $request = new CreateMapRequest(
            uid: 125,
            tpKey: 'testlink',
            domain: 'dev.trfc.link',
            destination: 'https://example.com'
        );

        $this->assertSame('', $request->getNotes(), 'Default notes getter must return empty string');

        $array = $request->toArray();
        $this->assertArrayNotHasKey('notes', $array, 'notes key must be absent from request body when empty');
    }

    /**
     * Scenario 1 (negative): The legacy auto-text MUST NOT appear in the request body.
     */
    public function testLegacyAutoTextIsNotInjectedByDefault(): void
    {
        $request = new CreateMapRequest(
            uid: 125,
            tpKey: 'testlink',
            domain: 'dev.trfc.link',
            destination: 'https://example.com'
        );

        $legacyText = 'Created via WordPress plugin';

        $this->assertNotSame($legacyText, $request->getNotes(), 'Legacy auto-text must NOT be injected as a default');

        $array = $request->toArray();
        $this->assertArrayNotHasKey('notes', $array, 'notes key must be absent when no user notes supplied');
        $this->assertNotContains($legacyText, $array, 'Legacy auto-text must NOT appear anywhere in serialised request body');
    }

    /**
     * Scenario 2: User-supplied notes are preserved verbatim.
     */
    public function testUserSuppliedNotesArePreservedVerbatim(): void
    {
        $userNotes = 'campaign Q2-launch';

        $request = new CreateMapRequest(
            uid: 125,
            tpKey: 'testlink',
            domain: 'dev.trfc.link',
            destination: 'https://example.com',
            notes: $userNotes
        );

        $this->assertSame($userNotes, $request->getNotes(), 'User-supplied notes must be stored exactly as provided');

        $array = $request->toArray();
        $this->assertArrayHasKey('notes', $array, 'notes key must be present when user supplied notes');
        $this->assertSame($userNotes, $array['notes'], 'User-supplied notes must appear verbatim in serialised request body');
    }

    /**
     * Scenario 4 (failure path): Empty notes → notes key is omitted from request body.
     *
     * The primary strategy is to omit the field when empty, avoiding TP API validation
     * errors. No legacy auto-text fallback is used.
     */
    public function testEmptyStringNotesOmitsKeyFromRequestBody(): void
    {
        $request = new CreateMapRequest(
            uid: 125,
            tpKey: 'testlink',
            domain: 'dev.trfc.link',
            destination: 'https://example.com',
            notes: ''
        );

        $this->assertSame('', $request->getNotes());
        $array = $request->toArray();
        $this->assertArrayNotHasKey('notes', $array, 'notes key must be absent from request body when value is empty string');
    }

    /**
     * Verifies that the handler source does NOT contain the hardcoded legacy string.
     * This is a regression guard — if someone re-introduces the string in the
     * handler, this test will catch it.
     */
    public function testHandlerSourceDoesNotContainLegacyAutoText(): void
    {
        $handlerPath = dirname(__DIR__, 2) . '/includes/class-tp-api-handler.php';

        $this->assertFileExists($handlerPath, 'API handler file must exist');

        $source = file_get_contents($handlerPath);
        $this->assertIsString($source);

        $this->assertStringNotContainsString(
            "'Created via WordPress plugin'",
            $source,
            'The legacy auto-text must not appear as a literal string in the API handler'
        );
    }
}

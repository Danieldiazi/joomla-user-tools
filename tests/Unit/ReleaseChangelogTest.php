<?php

namespace Danieldiazi\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/ReleaseChangelog.php';

final class ReleaseChangelogTest extends TestCase
{
    public function testPullRequestsBecomeItemsInTheSectionOfTheirLabel(): void
    {
        $xml = simplexml_load_string(\ReleaseChangelog::build('v2026.11.2', [
            ['number' => 81, 'title' => 'Joomla changelog for each release', 'labels' => ['enhancement']],
            ['number' => 82, 'title' => 'Fix the counter', 'labels' => ['bug', 'accessibility']],
            ['number' => 83, 'title' => 'Tidy the review screen', 'labels' => []],
            ['number' => 84, 'title' => 'Document the sync tool', 'labels' => ['documentation']],
        ]));
        self::assertNotFalse($xml);
        $entry = $xml->changelog[0];
        self::assertSame(['pkg_usertools', 'package', '2026.11.2'], [(string) $entry->element, (string) $entry->type, (string) $entry->version]);
        self::assertSame('Joomla changelog for each release (#81)', (string) $entry->addition->item);
        self::assertSame('Fix the counter (#82)', (string) $entry->fix->item, 'the first known label decides');
        self::assertSame('Tidy the review screen (#83)', (string) $entry->change->item, 'unlabelled pull requests are changes');
        self::assertSame('Document the sync tool (#84)', (string) $entry->note->item);
    }

    public function testADescriptionLineCanRewordOrLeaveOutAPullRequest(): void
    {
        self::assertSame('Synchronise a group branch from a list', \ReleaseChangelog::text(
            ['number' => 1, 'title' => 'GroupSync service', 'body' => "Intro\n\nChangelog: Synchronise a group branch from a list\n"]));
        self::assertNull(\ReleaseChangelog::text(['number' => 1, 'title' => 'CI tweak', 'body' => 'changelog: none']));
        self::assertNull(\ReleaseChangelog::text(['number' => 1, 'title' => 'Release 2026.11.0']), 'release preparation is not a change');
        self::assertSame('GPL notice; release 2026.11.1', \ReleaseChangelog::text(['number' => 1, 'title' => 'GPL notice; release 2026.11.1']));
    }

    public function testConventionalPrefixesChooseTheSectionOrLeaveInternalWorkOut(): void
    {
        $xml = simplexml_load_string(\ReleaseChangelog::build('2026.11.2', [
            ['number' => 73, 'title' => 'docs: guía de mantenibilidad'],
            ['number' => 76, 'title' => 'refactor: split the inactivity task'],
            ['number' => 77, 'title' => 'fix(csv): quote leading tabs'],
            ['number' => 78, 'title' => 'feat: sync a branch', 'labels' => ['documentation']],
            ['number' => 79, 'title' => 'Note: keep this title'],
        ]));
        self::assertNotFalse($xml);
        $entry = $xml->changelog[0];
        self::assertSame(['Guía de mantenibilidad (#73)', 'Sync a branch (#78)'], array_map('strval', $entry->xpath('note/item')), 'labels win over the prefix');
        self::assertSame('Quote leading tabs (#77)', (string) $entry->fix->item);
        self::assertSame('Note: keep this title (#79)', (string) $entry->change->item, 'unknown prefixes stay in the text');
        self::assertStringNotContainsString('inactivity', $xml->asXML(), 'refactoring is not listed');
    }

    public function testItemsAreSafeForJoomlasUnescapedOutput(): void
    {
        $text = \ReleaseChangelog::text(['number' => 1, 'title' => "Users & groups:  <b>bold</b>\nnext"]);
        self::assertSame('Users and groups: bbold/b next', $text);
        $xml = \ReleaseChangelog::build('2026.11.2', [['number' => 1, 'title' => 'A & B <script>']]);
        self::assertStringNotContainsString('&', $xml);
        self::assertStringContainsString('<item>A and B script (#1)</item>', $xml);
    }

    public function testAReleaseWithoutChangesStillHasAnEntry(): void
    {
        $xml = simplexml_load_string(\ReleaseChangelog::build('2026.11.3', [['number' => 9, 'title' => 'Release 2026.11.3']]));
        self::assertNotFalse($xml);
        self::assertSame('Maintenance release without user-facing changes.', (string) $xml->changelog[0]->note->item);
    }

    public function testPreviousEntriesAreKeptAfterTheNewOneWithoutDuplicates(): void
    {
        $first  = \ReleaseChangelog::build('2026.11.2', [['number' => 81, 'title' => 'Changelog']]);
        $second = \ReleaseChangelog::build('2026.11.3', [['number' => 85, 'title' => 'Next thing']], $first);
        $rerun  = \ReleaseChangelog::build('2026.11.3', [['number' => 85, 'title' => 'Next thing, reworded']], $second);
        $xml    = simplexml_load_string($rerun);
        self::assertNotFalse($xml);
        self::assertSame(['2026.11.3', '2026.11.2'], array_map('strval', $xml->xpath('changelog/version')));
        self::assertSame('Next thing, reworded (#85)', (string) $xml->changelog[0]->change->item);
        self::assertSame(\ReleaseChangelog::build('2026.11.2', []), \ReleaseChangelog::build('2026.11.2', [], 'not xml'),
            'an unreadable previous changelog is ignored');
    }
}

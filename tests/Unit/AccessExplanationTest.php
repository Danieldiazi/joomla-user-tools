<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccessExplanation;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use PHPUnit\Framework\TestCase;

final class AccessExplanationTest extends TestCase
{
    private const NOW = '2026-10-31 10:00:00';

    /** @return array<string, mixed> */
    private function facts(array $item = [], array $can = [], array $account = [], ?array $workflow = null, array $menus = []): array
    {
        return [
            'account' => $account + ['id' => 7, 'blocked' => false, 'levels' => [1, 2]],
            'guest_levels' => [1, 5],
            'item' => $item + ['type' => 'article', 'state' => 1, 'access' => 1, 'publish_up' => '2020-01-01 00:00:00', 'publish_down' => null,
                'author' => 9, 'checked_out' => 0, 'category' => ['published' => 1, 'access' => 1, 'title' => 'News'], 'ancestors' => []],
            'levels' => [1 => 'Public', 2 => 'Registered', 3 => 'Special', 5 => 'Guest'],
            'can' => $can + ['login_site' => true, 'login_admin' => false, 'manage' => false, 'edit' => false, 'edit_own' => false, 'edit_state' => false],
            'denials' => null,
            'workflow' => $workflow,
            'menus' => $menus,
            'now' => self::NOW,
        ];
    }

    /** @return array<string, string> check key => status */
    private function statuses(array $result): array
    {
        return array_column($result['checks'], 'status', 'key');
    }

    public function testAPublishedArticleWithGrantedLevelsCanBeViewed(): void
    {
        $result = AccessExplanation::explain('view', 'site', $this->facts());
        self::assertSame('yes', $result['verdict']);
        self::assertNotContains(AccessExplanation::FAIL, $this->statuses($result));
        self::assertContains('COM_USERTOOLS_EXPLAIN_NOTE_PLUGINS', $result['notes'], 'what is not modelled is always listed as not checked');
    }

    public function testEveryImpedimentIsListedAtOnce(): void
    {
        $result = AccessExplanation::explain('view', 'site', $this->facts(
            ['access' => 3, 'state' => 0, 'publish_up' => '2027-01-01 00:00:00', 'category' => ['published' => 0, 'access' => 3, 'title' => 'Board']]));
        self::assertSame('no', $result['verdict']);
        self::assertSame(['account' => 'pass', 'login' => 'pass', 'state' => 'fail', 'dates' => 'fail', 'category' => 'fail', 'access' => 'fail', 'category_access' => 'fail'],
            $this->statuses($result));
        self::assertSame(['Special'], $result['checks'][5]['args'], 'the missing level is named');
    }

    public function testLevelAllowedButArticleNotPublishedYet(): void
    {
        $result = AccessExplanation::explain('view', 'site', $this->facts(['access' => 2, 'publish_up' => '2026-11-01 08:00:00']));
        $statuses = $this->statuses($result);
        self::assertSame('pass', $statuses['access']);
        self::assertSame('fail', $statuses['dates']);
        self::assertSame('COM_USERTOOLS_EXPLAIN_DATES_FUTURE', $result['checks'][3]['text']);
        $expired = AccessExplanation::explain('view', 'site', $this->facts(['publish_down' => '2026-10-01 00:00:00']));
        self::assertSame('COM_USERTOOLS_EXPLAIN_DATES_EXPIRED', $expired['checks'][3]['text']);
    }

    public function testEditorsSeeUnpublishedAndOutOfDateArticlesLikeJoomla(): void
    {
        foreach (['edit', 'edit_state'] as $permission) {
            $result = AccessExplanation::explain('view', 'site', $this->facts(['state' => 0, 'publish_up' => '2027-01-01 00:00:00'], [$permission => true]));
            self::assertSame('yes', $result['verdict'], $permission);
            self::assertSame('COM_USERTOOLS_EXPLAIN_STATE_EDITOR', $result['checks'][2]['text']);
        }
        $archived = AccessExplanation::explain('view', 'site', $this->facts(['state' => 2]));
        self::assertSame('yes', $archived['verdict'], 'archived articles are shown on the site');
        $trashed = AccessExplanation::explain('view', 'site', $this->facts(['state' => -2]));
        self::assertSame(['JTRASHED'], $trashed['checks'][2]['args']);
    }

    public function testABlockedAccountIsEvaluatedAsAVisitor(): void
    {
        $member = AccessExplanation::explain('view', 'site', $this->facts(['access' => 2], [], ['blocked' => true]));
        self::assertSame('no', $member['verdict'], 'a visitor lacks the Registered level');
        self::assertSame('info', $this->statuses($member)['account']);
        self::assertSame('info', $this->statuses($member)['guest']);
        $public = AccessExplanation::explain('view', 'site', $this->facts([], ['login_site' => false]));
        self::assertSame('yes', $public['verdict'], 'public content stays visible to a visitor');
        $editing = AccessExplanation::explain('edit', 'administrator', $this->facts([], ['edit' => true, 'manage' => true, 'login_admin' => true], ['blocked' => true]));
        self::assertSame('no', $editing['verdict'], 'a blocked account cannot edit');
        self::assertSame('fail', $this->statuses($editing)['account']);
    }

    public function testEditOwnRequiresAuthorship(): void
    {
        $base = ['login_admin' => true, 'manage' => true, 'edit_own' => true];
        $other = AccessExplanation::explain('edit', 'administrator', $this->facts(['author' => 9], $base));
        self::assertSame('no', $other['verdict']);
        self::assertSame('COM_USERTOOLS_EXPLAIN_EDIT_OWN_FAIL', $other['checks'][3]['text']);
        $own = AccessExplanation::explain('edit', 'administrator', $this->facts(['author' => 7], $base));
        self::assertSame('yes', $own['verdict']);
        $none = AccessExplanation::explain('edit', 'administrator', $this->facts([], ['login_admin' => true]));
        self::assertSame(['account' => 'pass', 'login' => 'pass', 'manage' => 'fail', 'permission' => 'fail', 'checked_out' => 'pass'], $this->statuses($none));
    }

    public function testCheckedOutByAnotherAccountBlocksEditingAndPublishing(): void
    {
        $can = ['login_admin' => true, 'manage' => true, 'edit' => true, 'edit_state' => true];
        self::assertSame('no', AccessExplanation::explain('edit', 'administrator', $this->facts(['checked_out' => 3], $can))['verdict']);
        self::assertSame('no', AccessExplanation::explain('publish', 'administrator', $this->facts(['checked_out' => 3], $can))['verdict']);
        self::assertSame('yes', AccessExplanation::explain('edit', 'administrator', $this->facts(['checked_out' => 7], $can))['verdict'], 'its own check-out does not block it');
    }

    public function testPublishingFollowsTheWorkflowNotJustEditState(): void
    {
        $can = ['login_admin' => true, 'manage' => true, 'edit_state' => true];
        self::assertSame('yes', AccessExplanation::explain('publish', 'administrator', $this->facts([], $can))['verdict'], 'without workflows, Edit State publishes');
        $other = AccessExplanation::explain('publish', 'administrator', $this->facts(['state' => 0], $can, [],
            ['stage' => 'Basic stage', 'transitions' => [['title' => 'Unpublish', 'allowed' => true, 'publishes' => false], ['title' => 'Publish', 'allowed' => false, 'publishes' => true]]]));
        self::assertSame('no', $other['verdict'], 'Edit State is not enough when the publishing transition is not allowed');
        self::assertSame(['Basic stage', 'Unpublish'], $other['checks'][3]['args']);
        $allowed = AccessExplanation::explain('publish', 'administrator', $this->facts(['state' => 0], ['login_admin' => true, 'manage' => true], [],
            ['stage' => 'Basic stage', 'transitions' => [['title' => 'Publish', 'allowed' => true, 'publishes' => true]]]));
        self::assertSame('yes', $allowed['verdict'], 'an allowed publishing transition is enough without Edit State');
        self::assertContains('COM_USERTOOLS_EXPLAIN_NOTE_WORKFLOW', $allowed['notes']);
        $none = AccessExplanation::explain('publish', 'administrator', $this->facts([], $can, [], ['stage' => 'Basic stage', 'transitions' => []]));
        self::assertSame('COM_USERTOOLS_EXPLAIN_TRANSITION_NONE', $none['checks'][4]['text']);
        self::assertSame('info', $this->statuses($none)['current'], 'already published is only information');
    }

    public function testCategoriesNeedTheirParentsPublished(): void
    {
        $item = ['type' => 'category', 'category' => null, 'ancestors' => [['published' => 1, 'title' => 'Root area'], ['published' => 0, 'title' => 'Old section']]];
        $result = AccessExplanation::explain('view', 'site', $this->facts($item));
        self::assertSame('no', $result['verdict']);
        self::assertSame(['Old section'], $result['checks'][3]['args']);
        self::assertContains('COM_USERTOOLS_EXPLAIN_NOTE_CATEGORY_PATH', $result['notes']);
    }

    public function testMenuItemsAreReportedWithoutChangingTheVerdict(): void
    {
        $result = AccessExplanation::explain('view', 'site', $this->facts([], [], [], null,
            [['title' => 'Members', 'access' => 3, 'published' => 1], ['title' => 'Old', 'access' => 1, 'published' => 0], ['title' => 'News', 'access' => 1, 'published' => 1]]));
        self::assertSame('yes', $result['verdict'], 'the content itself is reachable even if a menu item is not');
        self::assertSame(['fail', 'fail', 'pass'], array_column($result['menus'], 'status'));
        self::assertSame(['Special'], $result['menus'][0]['args']);
    }

    public function testOnlySupportedCombinationsAreAccepted(): void
    {
        self::assertSame(['site'], AccessExplanation::contexts('article', 'view'));
        self::assertSame(['administrator'], AccessExplanation::contexts('category', 'edit'));
        $this->expectException(UserFacingException::class);
        AccessExplanation::validate('category', 'edit', 'site');
    }
}

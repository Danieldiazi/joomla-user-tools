<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\AccountProfile;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use PHPUnit\Framework\TestCase;

final class AccountProfileTest extends TestCase
{
    /** @return array<int, object> */
    private function fixtureGroups(): array
    {
        $groups = [];
        foreach ([[1, 0, 'Public'], [2, 1, 'Registered'], [3, 2, 'Author'], [11, 2, 'Members'], [12, 11, 'Board'], [8, 1, 'Super Users']] as [$id, $parent, $title]) {
            $groups[$id] = (object) ['id' => $id, 'parent_id' => $parent, 'title' => $title, 'level' => 0];
        }
        return $groups;
    }

    public function testLevelsExplainAssignedAndInheritedGroupsAndIndividualRules(): void
    {
        $levels = [
            ['id' => 1, 'title' => 'Public', 'rules' => [1]],
            ['id' => 2, 'title' => 'Registered', 'rules' => [2]],
            ['id' => 3, 'title' => 'Board only', 'rules' => [12]],
            ['id' => 4, 'title' => 'Individual', 'rules' => [-41]],
            ['id' => 5, 'title' => 'Other account', 'rules' => [-42]],
        ];
        $result = AccountProfile::levels($this->fixtureGroups(), $levels, Fixtures::user(41, 'alice', [12, 3]), [1, 2, 3, 4]);
        $this->assertSame([1, 2, 3, 4], array_column($result, 'id'));
        $this->assertSame([['state' => 'public', 'group' => 0, 'via' => 0]], $result[0]['sources']);
        $this->assertSame([['state' => 'inherited', 'group' => 3, 'via' => 2],
            ['state' => 'inherited', 'group' => 12, 'via' => 2]], $result[1]['sources']);
        $this->assertSame([['state' => 'direct', 'group' => 12, 'via' => 0]], $result[2]['sources']);
        $this->assertSame([['state' => 'account', 'group' => 0, 'via' => 0]], $result[3]['sources']);
    }

    public function testJoomlaAuthorisedLevelsRemainAuthoritativeForSuperUsersAndRecovery(): void
    {
        $levels = [['id' => 1, 'title' => 'Public', 'rules' => [1]],
            ['id' => 7, 'title' => 'Restricted', 'rules' => [11]]];
        $super = Fixtures::user(41, 'root', [8], true, true);
        $this->assertSame([1], array_column(AccountProfile::levels($this->fixtureGroups(), $levels, $super, [1]), 'id'),
            'being a Super User does not automatically grant every viewing level');
        $recovery = AccountProfile::levels($this->fixtureGroups(), $levels, $super, [1, 7]);
        $this->assertSame([['state' => 'joomla', 'group' => 0, 'via' => 0]], $recovery[1]['sources']);
    }

    public function testActionLinksNeedOwnPermissionsAndProtectPrivilegedAccounts(): void
    {
        $permissions = array_fill_keys(['usertools.ungroup', 'usertools.inactive', 'usertools.anonymize'], true);
        $operator = new Operator(9, false, 'delegate');
        $ordinary = Fixtures::user(41, 'alice');
        $this->assertSame(['ungroup' => true, 'block' => true, 'anonymize' => true], AccountProfile::actions($ordinary, $operator, $permissions));
        $this->assertSame(['ungroup' => false, 'block' => false, 'anonymize' => false], AccountProfile::actions($ordinary, $operator, []));
        foreach ([Fixtures::user(9, 'self'), Fixtures::user(41, 'super', [8], true, true),
            Fixtures::user(41, 'manager', [6], false, true)] as $protected) {
            $this->assertNotContains(true, AccountProfile::actions($protected, $operator, $permissions));
        }
        $this->assertFalse(AccountProfile::actions(Fixtures::user(41, 'blocked', block: 1), $operator, $permissions)['block']);
        $this->assertTrue(AccountProfile::actions(Fixtures::user(41, 'manager', [6], false, true),
            new Operator(9, true, 'admin'), $permissions)['ungroup']);
        $this->assertNotContains(true, AccountProfile::actions(Fixtures::user(41, 'super', [8], true, true),
            new Operator(9, true, 'admin'), $permissions));
    }

    public function testSingleAccountFingerprintTracksReviewedFieldsButNotCredentials(): void
    {
        $account = (object) ['id' => 41, 'username' => 'alice', 'name' => 'Alice', 'email' => 'a@example.test',
            'block' => 0, 'lastvisitDate' => null, 'registerDate' => '2026-01-01 00:00:00', 'groups' => [11 => 11, 2 => 2],
            'password' => 'secret hash', 'otpKey' => 'secret'];
        $before = AccountProfile::fingerprint($account);
        $account->groups = [2, 11];
        $account->password = 'another hash';
        $account->otpKey = 'another secret';
        $this->assertSame($before, AccountProfile::fingerprint($account));
        foreach (['name', 'email', 'lastvisitDate', 'block', 'groups'] as $field) {
            $changed = clone $account;
            $changed->{$field} = match ($field) { 'block' => 1, 'groups' => [2], default => 'changed' };
            $this->assertNotSame($before, AccountProfile::fingerprint($changed), $field . ' changed after review');
        }
    }

    public function testActionLogMatchesActorsAndUserObjectsExactly(): void
    {
        $user = json_encode(['type' => 'COM_USERTOOLS_ACTION_LOG_TYPE_USER', 'id' => 41]);
        $this->assertSame('object', AccountProfile::logRole(41, 9, $user));
        $this->assertSame('both', AccountProfile::logRole(41, 41, $user));
        $this->assertSame('actor', AccountProfile::logRole(41, 41, '{}'));
        $this->assertSame('actor', AccountProfile::logRole(41, 41, 'invalid JSON'));
        $this->assertSame('object', AccountProfile::logRole(41, 9, json_encode([
            'itemlink' => 'index.php?option=com_users&amp;task=user.edit&amp;id=41'])));
        foreach ([['type' => 'COM_USERTOOLS_ACTION_LOG_TYPE_GROUP', 'id' => 41],
            ['type' => 'PLG_ACTIONLOG_JOOMLA_TYPE_ARTICLE', 'id' => 41],
            ['type' => 'COM_USERTOOLS_ACTION_LOG_TYPE_USER', 'id' => 410],
            ['type' => 'COM_USERTOOLS_ACTION_LOG_TYPE_USER', 'id' => '41oops'],
            ['itemlink' => 'index.php?option=com_users&task=group.edit&id=41']] as $other) {
            $this->assertSame('', AccountProfile::logRole(41, 9, json_encode($other)));
        }
        $this->assertSame('', AccountProfile::logRole(41, 9, 'invalid JSON'));
    }

    public function testArticleLinkFiltersExactAuthorAndResetsUnrelatedFilters(): void
    {
        parse_str(parse_url(AccountProfile::articleUrl(41), PHP_URL_QUERY), $params);
        $this->assertSame(['41'], $params['filter']['author_id']);
        $this->assertSame('*', $params['filter']['published']);
        $this->assertSame('', $params['filter']['category_id']);
        $this->assertSame('', $params['filter']['search']);
        $this->assertSame('com_content', $params['option']);
    }
}

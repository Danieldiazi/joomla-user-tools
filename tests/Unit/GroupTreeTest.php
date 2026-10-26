<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Module\UsertoolsGroups\Site\Service\GroupTree;
use PHPUnit\Framework\TestCase;

final class GroupTreeTest extends TestCase
{
    /** Joomla's default tree: Public > (Guest, Manager > Administrator, Registered > Author > Editor > Publisher, Super Users). */
    private function tree(): GroupTree
    {
        $rows = [
            [1, 0, 1, 22, 'Public'], [9, 1, 2, 3, 'Guest'], [6, 1, 4, 7, 'Manager'], [7, 6, 5, 6, 'Administrator'],
            [2, 1, 8, 17, 'Registered'], [3, 2, 9, 16, 'Author'], [4, 3, 10, 15, 'Editor'], [5, 4, 11, 14, 'Publisher'],
            [10, 5, 12, 13, 'Editors Club'], [8, 1, 18, 19, 'Super Users'], [11, 1, 20, 21, 'Socios'],
        ];
        shuffle($rows);
        return new GroupTree(array_map(fn ($r) => (object) array_combine(['id', 'parent_id', 'lft', 'rgt', 'title'], $r), $rows));
    }

    public function testGroupsOfAUserInTreeOrder(): void
    {
        $tree = $this->tree();
        $this->assertSame(['Author', 'Socios'], array_column($tree->forUser([11, 3], false), 'title'));
        $inherited = $tree->forUser([11, 3], true);
        $this->assertSame(['Public', 'Registered', 'Author', 'Socios'], array_column($inherited, 'title'));
        $this->assertSame([false, false, true, true], array_column($inherited, 'direct'));
        $this->assertSame([0, 1, 2, 1], array_column($inherited, 'level'));
        $this->assertSame(['Author', 'Socios'], array_column($tree->forUser([11, 3], true, [1, 2]), 'title'), 'hidden groups are left out');
        $this->assertSame([], $tree->forUser([99], true), 'unknown groups are ignored');
    }

    public function testSubgroups(): void
    {
        $tree = $this->tree();
        $this->assertSame([3, 4, 5, 10], $tree->descendants(3));
        $this->assertSame([3], $tree->descendants(3, false));
        $this->assertSame([], $tree->descendants(99));
        $this->assertSame([1, 2, 3, 4], $tree->ancestors(4));
    }

    public function testBackendGroupsInheritPermissions(): void
    {
        // Root asset defaults: Manager may log in to the administrator, Super Users have core.admin.
        $allow = fn (string $action, array $ids): bool => $action === 'core.admin' ? in_array(8, $ids, true)
            : ($action === 'core.login.admin' && (in_array(6, $ids, true) || in_array(8, $ids, true)));
        $this->assertSame([6, 7, 8], $this->tree()->backendGroups($allow), 'Administrator inherits from Manager');
    }

    public function testLabels(): void
    {
        $this->assertSame('Ana Pérez', GroupTree::label('Ana Pérez', 'ana', 'name'));
        $this->assertSame('ana', GroupTree::label('Ana Pérez', 'ana', 'username'));
        $this->assertSame('Ana Pérez (ana)', GroupTree::label('Ana Pérez', 'ana', 'both'));
        $this->assertSame('ana', GroupTree::label('', 'ana', 'name'), 'an empty name falls back to the username');
        $this->assertSame('ana', GroupTree::label('ana', 'ana', 'both'), 'no repetition when both are equal');
    }
}

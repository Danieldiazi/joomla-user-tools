<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\Navigation;
use PHPUnit\Framework\TestCase;

final class NavigationTest extends TestCase
{
    public function testOneToolDoesNotExposeOtherCategoriesOrTools(): void
    {
        $allowed = static fn (string $permission): bool => in_array($permission, ['core.manage', 'usertools.profile'], true);
        $sections = Navigation::sections($allowed);
        self::assertSame(['accounts'], array_keys($sections));
        self::assertSame(['profile'], array_keys($sections['accounts']['tools']));
        self::assertSame([], Navigation::sections(static fn (string $permission): bool => $permission === 'usertools.profile'));
    }

    public function testCategoriesFollowCurrentPermissionsAndNativeSchedulerAccess(): void
    {
        $permissions = ['core.manage' => true, 'usertools.reports' => true, 'usertools.delete' => true];
        $allowed = static function (string $permission) use (&$permissions): bool { return $permissions[$permission] ?? false; };
        self::assertSame(['automation', 'delete'], array_keys(Navigation::sections($allowed)));
        self::assertSame(['reports'], array_keys(Navigation::sections($allowed)['automation']['tools']));
        $permissions['usertools.reports'] = false;
        self::assertSame(['delete'], array_keys(Navigation::sections($allowed)), 'Revocation is not cached');
        $permissions[Navigation::SCHEDULER_MANAGE] = true;
        self::assertSame(['schedule'], array_keys(Navigation::sections($allowed)['automation']['tools']));
    }

    public function testCatalogueCoversEveryProtectedViewOnceAndKeepsWarnings(): void
    {
        $sections = Navigation::sections(static fn (): bool => true);
        self::assertSame(['accounts', 'groups', 'automation', 'delete'], array_keys($sections));
        $views = [];
        foreach ($sections as $category => $section) {
            foreach ($section['tools'] as $view => $tool) {
                self::assertSame($category, Navigation::categoryForView($view));
                self::assertArrayHasKey($view, Navigation::PERMISSIONS);
                $views[] = $view;
            }
        }
        self::assertSame(array_keys(Navigation::TOOLS), $views);
        self::assertSame(['home', 'report'], array_values(array_diff(array_keys(Navigation::PERMISSIONS), $views)));
        foreach (['delete', 'anonymize'] as $view) {
            self::assertSame('permanent', Navigation::TOOLS[$view][4]);
            self::assertNotEmpty(Navigation::TOOLS[$view][6]);
        }
        self::assertSame(Navigation::SUPER_USERS, Navigation::PERMISSIONS['move']);
    }

    public function testDetailRoutesStayInTheirCategoryAndLinksAreBounded(): void
    {
        self::assertSame('automation', Navigation::categoryForView('report'));
        self::assertSame('accounts', Navigation::categoryForView('unblock'));
        self::assertNull(Navigation::categoryForView('home'));
        self::assertNull(Navigation::categoryForView('invented'));
        self::assertSame('index.php?option=com_usertools&view=home&section=groups', Navigation::categoryLink('groups'));
        $this->expectException(\InvalidArgumentException::class);
        Navigation::categoryLink('groups&task=delete');
    }
}

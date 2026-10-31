<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\GuidedTours;
use Danieldiazi\Component\Usertools\Administrator\Service\Navigation;
use PHPUnit\Framework\TestCase;

final class GuidedToursTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testToursExplainTheToolsThatChangeAccountsOrGroups(): void
    {
        $this->assertSame(['compare', 'inactive', 'unblock', 'delete', 'anonymize', 'addgroups', 'ungroup', 'move', 'schedule'], array_keys(GuidedTours::TOURS));
        foreach (array_keys(GuidedTours::TOURS) as $position => $view) {
            $this->assertArrayHasKey($view, Navigation::PERMISSIONS, $view . ' is a screen with its own permission');
            $tour = GuidedTours::tour($view, $position);
            $this->assertSame(0, $tour['autostart'], 'a tour never starts on its own');
            $this->assertSame('com_usertools.tour-' . $view, $tour['uid']);
            $this->assertSame('administrator/index.php?option=com_usertools&view=' . $view, $tour['url']);
            $this->assertSame(['com_usertools.tours'], json_decode($tour['extensions'], true), 'not a component, so not a context tour in Joomla\'s menu');
            $this->assertGreaterThanOrEqual(GuidedTours::ORDERING, $tour['ordering'], 'after Joomla\'s own tours');
        }
    }

    public function testEveryStepPointsToAStableAttributeOfItsScreen(): void
    {
        $layouts = (string) file_get_contents(self::ROOT . '/admin/layouts/usertools/steps.php') . file_get_contents(self::ROOT . '/admin/layouts/usertools/exclusions.php');
        foreach (array_keys(GuidedTours::TOURS) as $view) {
            $template = (string) file_get_contents(self::ROOT . '/admin/tmpl/' . $view . '/default.php');
            $steps    = GuidedTours::steps($view);
            $this->assertNotEmpty($steps);
            foreach ($steps as $i => $step) {
                $this->assertSame($i + 1, $step['ordering']);
                $this->assertSame(GuidedTours::STEP_NEXT, $step['type'], 'steps only explain: they never click or submit');
                $this->assertContains($step['position'], ['top', 'bottom', 'left', 'right', 'center']);
                $this->assertSame(GuidedTours::url($view), $step['url']);
                $this->assertMatchesRegularExpression('/^\[data-ut-tour="[a-z]+"\]$/', $step['target']);
                $name = substr($step['target'], \strlen('[data-ut-tour="'), -2);
                $uses = $template . (str_contains($template, "'usertools.steps'") || str_contains($template, '"usertools.steps"') ? $layouts : '')
                    . (str_contains($template, "'usertools.exclusions'") ? $layouts : '');
                $this->assertStringContainsString('data-ut-tour="' . $name . '"', $uses, "$view: the $name step has its target on the screen");
            }
        }
    }

    public function testTextsExistInBothLanguagesAndNowhereElse(): void
    {
        $main = [];
        foreach (['es-ES', 'en-GB'] as $tag) {
            $main[$tag] = parse_ini_file(self::ROOT . "/admin/language/$tag/com_usertools.ini", false, INI_SCANNER_RAW) ?: [];
            $this->assertArrayHasKey('COM_USERTOOLS_TOUR_BUTTON', $main[$tag]);
        }
        foreach (array_keys(GuidedTours::TOURS) as $position => $view) {
            $this->assertSame('com_usertools.tour_' . $view . '.ini', GuidedTours::languageFile($view), 'the file Joomla loads for the tour uid');
            $tour     = GuidedTours::tour($view, $position);
            $expected = [$tour['title'], $tour['description']];
            foreach (GuidedTours::steps($view) as $step) {
                $expected[] = $step['title'];
                $expected[] = $step['description'];
            }
            $strings = [];
            foreach (['es-ES', 'en-GB'] as $tag) {
                $strings[$tag] = parse_ini_file(self::ROOT . "/admin/language/$tag/" . GuidedTours::languageFile($view), false, INI_SCANNER_RAW) ?: [];
                $this->assertSame($expected, array_keys($strings[$tag]), "$tag $view: exactly the tour's keys, in order");
                $this->assertSame([], array_intersect_key($strings[$tag], $main[$tag]), "$tag $view: no key repeated in com_usertools.ini");
                foreach ($strings[$tag] as $key => $text) {
                    $this->assertNotSame('', trim($text), "$tag $key");
                }
            }
            $this->assertNotSame($strings['es-ES'], $strings['en-GB'], "$view is translated");
        }
    }
}

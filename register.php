<?php

use Leantime\Plugins\ProjectOverview\Middleware\GetLanguageAssets;
use Leantime\Core\Events;
use Leantime\Core\Frontcontroller as FrontcontrollerCore;

/**
* Adds a menu point for adding fixture data.
* @param array<string, array<int, array<string, mixed>>> $menuStructure The existing menu structure to which the new item will be added.
* @return array<string, array<int, array<string, mixed>>> The modified menu structure with the new item added.
*/
function addProjectOverviewMenuPoint(array $menuStructure): array
{
    // So, why the number 21: In menu.php the menu seem to be ordered by putting menu items into an array by a number
    // I figure this menu-item should be _after_ the other menu-items, and as the last menu item is "20", I figured 21
    // would do. But! I have no idea if this will clash with any other plugins hooking into the menu.
    // https://github.com/ITK-Leantime/leantime/blob/0ff10e759a557af717e905ed5a1d324c9cf8c1d8/app/Domain/Menu/Repositories/Menu.php#L107
    $menuStructure['personal'][21] = [
        'type' => 'item',
        'title' => '<span class="fa-solid fa-list-check"></span> ' . __('projectOverview.menu_title'),
        'icon' => 'fa-solid fa-list-check',
        'tooltip' => __('projectOverview.menu_tooltip'),
        'href' => '/ProjectOverview/projectOverview',
        'active' => ['ProjectOverview'],
        'module' => 'tickets',
    ];
    return $menuStructure;
}

/**
 * Adds Timetable to the personal menu
 * @param array<string, array<int, array<string, mixed>>> $sections The sections in the menu is to do with which menu is displayed on the current page.
 * @return array<string, string> - the sections array, where ProjectOverview.projectOverview is in the "personal" menu.
 */
function addProjectOverviewToPersonalMenu(array $sections): array
{
    $sections['ProjectOverview.projectOverview'] = 'personal';
    return $sections;
}


Events::add_filter_listener('leantime.domain.menu.repositories.menu.getMenuStructure.menuStructures', 'addProjectOverviewMenuPoint');
Events::add_filter_listener('leantime.domain.menu.repositories.menu.getSectionMenuType.menuSections', 'addProjectOverviewToPersonalMenu');

// https://github.com/Leantime/plugin-template/blob/main/register.php#L43-L46
// Register Language Assets
Events::add_filter_listener(
    'leantime.core.httpkernel.handle.plugins_middleware',
    fn (array $middleware) => array_merge($middleware, [GetLanguageAssets::class]),
);

Events::add_event_listener(
    'leantime.core.template.tpl.*.afterScriptLibTags',
    function () {
        if (isset($_SESSION['userdata']['id'])) {
            $scriptUrl = '/dist/js/project-overview.js?' . http_build_query(['v' => '%%VERSION%%']);
            echo '<script src="' . htmlspecialchars($scriptUrl) . '"></script>';
            $cssUrl = '/dist/js/project-overview.css?' . http_build_query(['v' => '%%VERSION%%']);
            echo '<link rel="stylesheet" src="' . htmlspecialchars($cssUrl) . '"></link>';
        }
    },
    5
);


$url = '/dist/js/plugin-MyTimesheetDataExport.js?' . http_build_query(['v' => '%%VERSION%%']);
echo '<script src="' . htmlspecialchars($url) . '"></script>';

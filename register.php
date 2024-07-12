<?php
use Leantime\Plugins\ProjectOverview\Middleware\GetLanguageAssets;
use Leantime\Core\Events;

/**
* Adds a menu point for adding fixture data.
* @param array<string, array<int, array<string, mixed>>> $menuStructure The existing menu structure to which the new item will be added.
* @return array<string, array<int, array<string, mixed>>> The modified menu structure with the new item added.
*/
function addProjectOverviewMenuPoint(array $menuStructure): array
{
    $menuStructure['personal'][21] = [
        'type' => 'item',
        'module' => 'dashboard',
        'title' => '<i class="fa-solid fa-list-check"></i></span> ' . __('projectoverview.menu_title'),
        'icon' => 'fa-solid fa-list-check',
        'tooltip' => __('projectoverview.menu_tooltip'),
        'href' => '/ProjectOverview/projectOverview',
        'active' => ['ProjectOverview'],
    ];
    return $menuStructure;
}

Events::add_filter_listener('leantime.domain.menu.repositories.menu.getMenuStructure.menuStructures', 'addProjectOverviewMenuPoint');

// https://github.com/Leantime/plugin-template/blob/main/register.php#L43-L46
// Register Language Assets
Events::add_filter_listener(
    'leantime.core.httpkernel.handle.plugins_middleware',
    fn (array $middleware) => array_merge($middleware, [GetLanguageAssets::class]),
);

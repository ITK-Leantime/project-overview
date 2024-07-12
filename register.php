<?php

use Leantime\Core\Events;

/**
* Adds a menu point for adding fixture data.
* @param array<string, array<int, array<string, mixed>>> $menuStructure The existing menu structure to which the new item will be added.
* @return array<string, array<int, array<string, mixed>>> The modified menu structure with the new item added.
*/
function addProjectOverviewMenuPoint(array $menuStructure): array
{
    $menuStructure['default'][10]['submenu'][60] = [
        'type' => 'item',
        'module' => 'tickets',
        'title' => '<i class="fa-solid fa-list-check"></i></span> Projektoverblik',
        'icon' => 'fa-solid fa-list-check',
        'tooltip' => 'Projektoverblik',
        'href' => '/ProjectOverview/projectOverview',
        'active' => ['settings'],
    ];

    return $menuStructure;
}

Events::add_filter_listener('leantime.domain.menu.repositories.menu.getMenuStructure.menuStructures', 'addProjectOverviewMenuPoint');

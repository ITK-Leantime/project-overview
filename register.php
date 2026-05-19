<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Plugins\ProjectOverview\Helpers\ProjectOverviewActionHandler;
use Leantime\Plugins\ProjectOverview\Middleware\GetLanguageAssets;

/**
 * Builds the inner HTML for a single view sidebar entry. The Leantime menu
 * partial renders `title` unescaped (`{!! !!}`), so any user-supplied text in
 * here MUST be passed through htmlspecialchars first.
 *
 * @param  array<string, mixed> $userView
 * @return string
 */
function buildProjectOverviewViewMenuTitle(array $userView): string
{
    $title = htmlspecialchars(str_replace('_', ' ', $userView['title'] ?? 'View'), ENT_QUOTES);

    $indicator = '';
    if (!empty($userView['isTransientSubscription'])) {
        $tooltip = htmlspecialchars(
            __('projectOverview.subscription_preview') . ': ' . ($userView['subscribedFromName'] ?? ''),
            ENT_QUOTES
        );
        $indicator = '<span class="subscription-indicator" data-tippy-content="' . $tooltip . '" data-tippy-placement="right"><i class="fa fa-eye"></i></span>';
    } elseif (!empty($userView['isSubscription'])) {
        $tooltip = htmlspecialchars(
            __('projectOverview.subscription_indicator') . ': ' . ($userView['subscribedFromName'] ?? ''),
            ENT_QUOTES
        );
        $indicator = '<span class="subscription-indicator" data-tippy-content="' . $tooltip . '" data-tippy-placement="right"><i class="fa fa-link"></i></span>';
    }

    // The dirty-dot is toggled by JS via .is-dirty on the parent <li>.
    $dot = '<span class="view-dirty-dot" aria-hidden="true"></span>';
    // Vertical 3-dot context trigger — a non-interactive span (so the parent
    // <a> stays valid HTML) wrapping a Font Awesome glyph. Vertical dots
    // distinguish the trigger from the horizontal ellipsis on the truncated
    // label. `aria-label` covers screen readers; we deliberately don't set a
    // visible tippy here because it would stack on top of the view-title
    // tippy the row already has.
    $trigger = '<span class="view-context-menu-trigger"'
        . ' data-context-trigger="true"'
        . ' aria-label="' . htmlspecialchars(__('projectOverview.view_context_menu_tooltip'), ENT_QUOTES) . '"'
        . '><i class="fas fa-ellipsis-v" aria-hidden="true"></i></span>';

    return $dot . '<span class="view-label">' . $title . ' ' . $indicator . '</span>' . $trigger;
}

/**
 * Adds the main "Projektoverblik" menu point plus one entry per saved view
 * (and a trailing "+ New view" item) beneath it in the personal section.
 *
 * The Leantime menu partial only renders `<a>`-level attributes from the
 * `attributes` map and uses `module` + `active` for server-side active-state
 * matching. Since `active` can't differentiate `?view=foo` from `?view=bar`,
 * view items use `'active' => []` and the active class is applied client-side
 * (see project-overview.js).
 *
 * @param  array<string, array<int, array<string, mixed>>> $menuStructure The existing menu structure to which the new item will be added.
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
        'title' => '<span class="fas fa-fw fa-th-list"></span> ' . __('projectOverview.menu_title'),
        'icon' => 'fas fa-fw fa-th-list',
        'tooltip' => __('projectOverview.menu_tooltip'),
        'href' => '/ProjectOverview/ProjectOverview',
        'active' => ['ProjectOverview'],
        'module' => 'ProjectOverview',
    ];

    if (session('userdata.id') === null) {
        return $menuStructure;
    }

    // Only surface the saved-views list when the user is actually on the
    // ProjectOverview page — clicking a view from elsewhere wouldn't htmx-swap
    // anything (no #filtersContainer to target), so hiding the items off-page
    // keeps the sidebar uncluttered.
    if (!str_contains($_SERVER['REQUEST_URI'] ?? '', '/ProjectOverview/ProjectOverview')) {
        return $menuStructure;
    }

    try {
        $actionHandler = app(ProjectOverviewActionHandler::class);
    } catch (\Throwable $e) {
        // Container resolution failed — don't blow up the menu, just skip injecting views.
        return $menuStructure;
    }

    $userViews = $actionHandler->getUserViewsObject();

    // Mirror the helper's transient-subscription injection so a previewed
    // shared view is reachable from the sidebar, not only from the main page.
    $transientSub = session('project_overview.transient_subscription');
    if ($transientSub && isset($transientSub['ownerUserId'], $transientSub['ownerViewId'], $transientSub['tempViewId'])) {
        $ownerViews = $actionHandler->getUserViewsObject($transientSub['ownerUserId']);
        if (isset($ownerViews[$transientSub['ownerViewId']])) {
            $userViews[$transientSub['tempViewId']] = array_merge($ownerViews[$transientSub['ownerViewId']], [
                'id' => $transientSub['tempViewId'],
                'title' => ($ownerViews[$transientSub['ownerViewId']]['title'] ?? 'View') . ' (Live)',
                'isTransientSubscription' => true,
                'subscribeToken' => $transientSub['token'] ?? null,
                'subscribedFromName' => $transientSub['ownerName'] ?? '',
                'order' => PHP_INT_MAX,
            ]);
        }
    }

    // Sort by the same 'order' field the rest of the codebase uses.
    uasort($userViews, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

    // Section label above the view items. The inner <span> class lets us style
    // this specific header without touching every other 'type => header' the
    // menu renders elsewhere in the app.
    $menuStructure['personal'][22] = [
        'type' => 'header',
        'title' => '<span class="projectoverview-views-header-text">'
            . htmlspecialchars(__('projectOverview.views_section_header'), ENT_QUOTES)
            . '</span>',
    ];

    $index = 23;
    foreach ($userViews as $key => $userView) {
        $encodedKey = rawurlencode((string) $key);
        $attributes = [
            'hx-get' => '/ProjectOverview/ProjectOverview/loadFilters/' . $encodedKey,
            'hx-target' => '#filtersContainer',
            'hx-swap' => 'innerHTML',
            'data-view-key' => (string) $key,
            'class' => 'projectoverview-view-item',
        ];
        if (!empty($userView['isTransientSubscription'])) {
            $attributes['data-is-transient-subscription'] = 'true';
        } elseif (!empty($userView['isSubscription'])) {
            $attributes['data-is-subscription'] = 'true';
        }
        if (!empty($userView['subscribeToken'])) {
            $attributes['data-subscribe-token'] = $userView['subscribeToken'];
        }

        $menuStructure['personal'][$index++] = [
            'type' => 'item',
            'title' => buildProjectOverviewViewMenuTitle($userView),
            'tooltip' => $userView['title'] ?? 'View',
            'href' => '/ProjectOverview/ProjectOverview?view=' . $encodedKey,
            // active state is JS-managed (server can't differentiate ?view=X)
            'active' => [],
            'module' => 'ProjectOverview',
            'attributes' => $attributes,
        ];
    }

    // Trailing "+ New view" entry.
    $menuStructure['personal'][$index] = [
        'type' => 'item',
        'title' => '<span class="view-new-icon" aria-hidden="true">+</span> '
            . htmlspecialchars(__('projectOverview.new_view_tab'), ENT_QUOTES),
        'tooltip' => __('projectOverview.new_view_tab'),
        'href' => '/ProjectOverview/ProjectOverview?view=__new',
        'active' => [],
        'module' => 'ProjectOverview',
        'attributes' => [
            'hx-get' => '/ProjectOverview/ProjectOverview/loadFilters/__new',
            'hx-target' => '#filtersContainer',
            'hx-swap' => 'innerHTML',
            'data-view-key' => '__new',
            'class' => 'projectoverview-view-item projectoverview-new-view-item',
        ],
    ];

    return $menuStructure;
}

/**
 * Adds Timetable to the personal menu
 *
 * @param  array<string, array<int, array<string, mixed>>> $sections The sections in the menu is to do with which menu is displayed on the current page.
 * @return array<string, string> - the sections array, where ProjectOverview.projectOverview is in the "personal" menu.
 */
function addProjectOverviewToPersonalMenu(array $sections): array
{
    $sections['ProjectOverview.ProjectOverview'] = 'personal';

    return $sections;
}

EventDispatcher::add_filter_listener('leantime.domain.menu.repositories.menu.getMenuStructure.menuStructures', 'addProjectOverviewMenuPoint');
EventDispatcher::add_filter_listener('leantime.domain.menu.repositories.menu.getSectionMenuType.menuSections', 'addProjectOverviewToPersonalMenu');

// https://github.com/Leantime/plugin-template/blob/main/register.php#L43-L46
// Register Language Assets
EventDispatcher::add_filter_listener(
    'leantime.core.http.httpkernel.handle.plugins_middleware',
    fn (array $middleware) => array_merge($middleware, [GetLanguageAssets::class]),
);

EventDispatcher::add_event_listener(
    'leantime.core.template.tpl.*.afterScriptLibTags',
    function () {

        if (null !== (session('userdata.id')) && str_contains($_SERVER['REQUEST_URI'], '/ProjectOverview/ProjectOverview')) {
            // %%VERSION%% is substituted during release packaging. In dev that
            // placeholder is left as-is, which freezes the URL and lets the
            // browser cache forever; fall back to the bundle's mtime so every
            // rebuild produces a fresh URL.
            $jsPath = __DIR__ . '/dist/js/project-overview.js';
            $cssPath = __DIR__ . '/dist/css/project-overview.css';
            $jsVersion = '%%VERSION%%';
            $cssVersion = '%%VERSION%%';
            if ($jsVersion === '%' . '%VERSION%' . '%' && is_file($jsPath)) {
                $jsVersion = (string) filemtime($jsPath);
            }
            if ($cssVersion === '%' . '%VERSION%' . '%' && is_file($cssPath)) {
                $cssVersion = (string) filemtime($cssPath);
            }

            $projectOverviewUrl = '/dist/js/project-overview.js?' . http_build_query(['v' => $jsVersion]);
            echo '<script type="module" src="' . htmlspecialchars($projectOverviewUrl) . '"></script>';
            $projectOverviewStyle = '/dist/css/project-overview.css?' . http_build_query(['v' => $cssVersion]);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($projectOverviewStyle) . '"></link>';
        }
    },
    5
);

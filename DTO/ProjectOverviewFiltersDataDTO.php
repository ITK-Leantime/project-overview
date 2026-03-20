<?php

namespace Leantime\Plugins\ProjectOverview\DTO;

/**
 * Data Transfer Object for a partial async update of filters.
 */
readonly class ProjectOverviewFiltersDataDTO
{
    /**
     * Constructor method.
     *
     * @param array<string, mixed>                 $allUsers        An array of all users.
     * @param array<int, mixed>                    $allProjects     An array of all projects.
     * @param array<int, string>                   $allPriorities   An array of all priority levels (id => name).
     * @param array<int, array<string, mixed>>     $allStatusLabels An array of all status labels.
     * @param array<string, mixed>                 $allColumns      An array of all column definitions.
     * @param string                               $dateType        The type of date (e.g., 'range', 'custom').
     * @param string                               $fromDate        The starting date in a specific format.
     * @param string                               $toDate          The ending date in a specific format.
     * @param array<int>                           $projectFilters  Filters applied to projects (array of project IDs).
     * @param array<int>                           $priorityFilters Filters applied to priority levels (array of priority IDs).
     * @param array<int>                           $statusFilters   Filters applied to status labels (array of status IDs).
     * @param array<string>                        $customFilters   Custom filters applied (array of filter names).
     * @param string|null                          $title           The title, can be null if not provided.
     * @param array<string>                        $selectedColumns An array of selected column names.
     * @param array<int>                           $users           Users associated or involved (array of user IDs).
     * @param string|null                          $selectedViewId  The ID of the selected view, can be null.
     * @param array<string, array<string, string>> $dateRanges      Pre-calculated date ranges for each date type option.
     * @param bool                                 $isSubscription  Whether the selected view is a live-share subscription.
     */
    public function __construct(
        public array $allUsers,
        public array $allProjects,
        public array $allPriorities,
        public array $allStatusLabels,
        public array $allColumns,
        public string $dateType,
        public string $fromDate,
        public string $toDate,
        public array $projectFilters,
        public array $priorityFilters,
        public array $statusFilters,
        public array $customFilters,
        public ?string $title,
        public array $selectedColumns,
        public array $users,
        public ?string $selectedViewId,
        public array $dateRanges = [],
        public bool $isSubscription = false,
    ) {
    }
}

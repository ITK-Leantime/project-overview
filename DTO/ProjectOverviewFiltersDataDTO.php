<?php

namespace Leantime\Plugins\ProjectOverview\DTO;

readonly class ProjectOverviewFiltersDataDTO
{
    /**
     * Constructor method.
     *
     * @param array $allUsers An array of all users.
     * @param array $allProjects An array of all projects.
     * @param array $allPriorities An array of all priority levels.
     * @param array $allStatusLabels An array of all status labels.
     * @param array $allColumns An array of all column definitions.
     * @param string $fromDate The starting date in a specific format.
     * @param string $toDate The ending date in a specific format.
     * @param array $projectFilters Filters applied to projects.
     * @param array $priorityFilters Filters applied to priority levels.
     * @param array $statusFilters Filters applied to status labels.
     * @param array $customFilters Custom filters applied.
     * @param string|null $title The title, can be null if not provided.
     * @param array $selectedColumns An array of selected columns.
     * @param array $users Users associated or involved.
     * @param string|null $selectedViewId The ID of the selected view, can be null.
     *
     * @return void
     */
    public function __construct(
        public array $allUsers,
        public array $allProjects,
        public array $allPriorities,
        public array $allStatusLabels,
        public array $allColumns,
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
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

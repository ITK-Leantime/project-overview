<?php

namespace Leantime\Plugins\ProjectOverview\DTO;

readonly class ProjectOverviewFiltersDataDTO
{
    public function __construct(
        public array   $allUsers,
        public array   $allProjects,
        public array   $allPriorities,
        public array   $allStatusLabels,
        public array   $allColumns,
        public string  $fromDate,
        public string  $toDate,
        public array   $projectFilters,
        public array   $priorityFilters,
        public array   $statusFilters,
        public array   $customFilters,
        public ?string  $title,
        public array   $selectedColumns,
        public array   $users,
        public ?string $selectedViewId,
    )
    {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

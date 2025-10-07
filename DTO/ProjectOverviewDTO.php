<?php

namespace Leantime\Plugins\ProjectOverview\DTO;

/**
 * Represents the data transfer object for the project overview.
 */
class ProjectOverviewDTO
{
    public function __construct(
        public readonly array   $userViews,
        public readonly array   $statusLabels,
        public readonly array   $allStatusLabels,
        public readonly array   $allPriorities,
        public readonly array   $allProjects,
        public readonly array   $allUsers,
        public readonly ?string $selectedView,
    )
    {
    }
}

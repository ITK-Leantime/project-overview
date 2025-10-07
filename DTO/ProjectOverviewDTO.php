<?php

namespace Leantime\Plugins\ProjectOverview\DTO;

/**
 * Represents the data transfer object for the project overview.
 */
readonly class ProjectOverviewDTO
{
    /**
     * Constructor method.
     *
     * @param array       $userViews       Array representing user views.
     * @param array       $statusLabels    Array representing status labels.
     * @param array       $allStatusLabels Array containing all status labels.
     * @param array       $allPriorities   Array listing all priorities.
     * @param array       $allProjects     Array of all projects.
     * @param array       $allUsers        Array of all users.
     * @param string|null $selectedView    Selected view, or null if not set.
     *
     * @return void
     */
    public function __construct(
        public array $userViews,
        public array $statusLabels,
        public array $allStatusLabels,
        public array $allPriorities,
        public array $allProjects,
        public array $allUsers,
        public ?string $selectedView,
    ) {
    }
}

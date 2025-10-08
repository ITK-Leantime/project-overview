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
     * @param array<string, mixed>             $userViews       Array representing user views.
     * @param array<int, array<int, mixed>>    $statusLabels    Array representing status labels by project.
     * @param array<int, array<string, mixed>> $allStatusLabels Array containing all status labels.
     * @param array<int, string>               $allPriorities   Array listing all priorities (id => name).
     * @param array<int, array<string, mixed>> $allProjects     Array of all projects.
     * @param array<int, array<string, mixed>> $allUsers        Array of all users.
     * @param string|null                      $selectedView    Selected view, or null if not set.
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

<?php

namespace Leantime\Plugins\ProjectOverview\Services;

use Leantime\Plugins\ProjectOverview\Repositories\ProjectOverview as ProjectOverviewRepository;

class ProjectOverview
{
    private static $assets = [
        // source => target
        __DIR__ . '/../assets/project-overview.css' => APP_ROOT . '/public/dist/css/project-overview.css',
        __DIR__ . '/../assets/project-overview.js' => APP_ROOT . '/public/dist/js/project-overview.js',
    ];

    /* Constructor method for the class.
     *
     * @param projectOverviewRepository    $projectOverviewRepository  The ticket repository instance.
     */
    public function __construct(private ProjectOverviewRepository $projectOverviewRepository)
    {
    }

    /**
     * Install plugin.
     *
     * @return void
     */
    public function install(): void
    {
        foreach (static::$assets as $source => $target) {
            if (file_exists($target)) {
                unlink($target);
            }
            symlink($source, $target);
        }
    }

    /**
     * Uninstall plugin.
     *
     * @return void
     */
    public function uninstall(): void
    {
        foreach (static::$assets as $target) {
            if (file_exists($target)) {
                unlink($target);
            }
        }
    }

    /**
     * @return array
     */
    public function getTasks(?string $userId, ?string $searchTerm): array
    {
        return $this->projectOverviewRepository->getTasks($userId, $searchTerm);
    }

    /**
     * @return array
     */
    public function getMilestonesByProjectId(string $projectId): array
    {
        return $this->projectOverviewRepository->getMilestonesByProjectId($projectId);
    }
    /**
     * @return array
     */
    public function getSelectedMilestoneColor(string $milestoneId): ?string
    {
        $milestone = $this->projectOverviewRepository->getSelectedMilestoneColor($milestoneId);
        if (is_array($milestone) && count($milestone) > 0) {
            return $milestone[0]['color'];
        } else {
            return null;
        }
    }
}

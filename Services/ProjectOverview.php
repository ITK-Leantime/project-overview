<?php

namespace Leantime\Plugins\ProjectOverview\Services;

use Carbon\CarbonInterface;
use Leantime\Plugins\ProjectOverview\DTO\ViewDTO;
use Leantime\Plugins\ProjectOverview\Repositories\ProjectOverview as ProjectOverviewRepository;

/**
 * This is the project overview service, for installation, uninstall and getting data from the repository.
 */
class ProjectOverview
{
    /**
     * @var array<string, string>
     */
    private static array $assets = [
        // source => target
        __DIR__ . '/../dist/css/project-overview.css' => APP_ROOT . '/public/dist/css/project-overview.css',
        __DIR__ . '/../dist/js/project-overview.js' => APP_ROOT . '/public/dist/js/project-overview.js',
    ];

    /** Constructor method for the class.
     *
     * @param ProjectOverviewRepository $projectOverviewRepository The ticket repository instance.
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
        foreach (self::getAssets() as $source => $target) {
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
        foreach (self::getAssets() as $target) {
            if (file_exists($target)) {
                unlink($target);
            }
        }
    }

    /**
     * Get assets
     *
     * @return array|string[]
     */
    private static function getAssets(): array
    {
        return self::$assets;
    }

    /**
     * Get tasks.
     *
     * @return array|string[]
     */
    public function getViewTasks(ViewDTO $viewDTO): array
    {
        return $this->projectOverviewRepository->getViewTasks($viewDTO);
    }

    /**
     * @return array<int, mixed>
     */
    public function getMilestonesByProjectId(string $projectId): array
    {
        return $this->projectOverviewRepository->getMilestonesByProjectId($projectId);
    }

    /**
     * Get all projects.
     *
     * @return array<int, mixed> An array containing all projects.
     */
    public function getAllProjects(): array
    {
        return $this->projectOverviewRepository->getAllProjects();
    }
}

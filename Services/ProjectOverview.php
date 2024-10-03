<?php

namespace Leantime\Plugins\ProjectOverview\Services;

use Carbon\CarbonImmutable;
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
        __DIR__ . '/../assets/project-overview.css' => APP_ROOT . '/public/dist/css/project-overview.css',
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
     * @return array<string, mixed>
     */
    public function getTasks(?array $userId, ?string $searchTerm, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): array
    {
        return $this->projectOverviewRepository->getTasks($userId, $searchTerm, $dateFrom, $dateTo);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMilestonesByProjectId(string $projectId): array
    {
        return $this->projectOverviewRepository->getMilestonesByProjectId($projectId);
    }
    /**
     * @return string|null
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

    /**
     * Get all projects.
     *
     * @return array An array containing all projects.
     */
    public function getAllProjects(): array
    {
        return $this->projectOverviewRepository->getAllProjects();
    }
}

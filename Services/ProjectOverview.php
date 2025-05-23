<?php

namespace Leantime\Plugins\ProjectOverview\Services;

use Carbon\CarbonInterface;
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
     * Get tasks based on the given parameters.
     *
     * @param array<int, string>|null $userIdArray    An array of user IDs to filter the tasks. Can be null.
     * @param string|null             $searchTerm     The search term to filter the tasks. Can be null.
     * @param CarbonInterface         $dateFrom       The start date to filter the tasks.
     * @param CarbonInterface         $dateTo         The end date to filter the tasks.
     * @param int                     $noDueDate      include tasks without due date set
     * @param int                     $overdueTickets include overdue tasks
     * @param string                  $sortBy         sort tickets by.
     * @param string                  $sortOrder      sort order.
     * @return array<int, mixed> An array of tasks based on the given parameters.
     */
    public function getTasks(?array $userIdArray, ?string $searchTerm, CarbonInterface $dateFrom, CarbonInterface $dateTo, int $noDueDate, int $overdueTickets, ?string $sortBy, ?string $sortOrder): array
    {
        return $this->projectOverviewRepository->getTasks($userIdArray, $searchTerm, $dateFrom, $dateTo, $noDueDate, $overdueTickets, $sortBy, $sortOrder);
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

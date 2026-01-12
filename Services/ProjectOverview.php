<?php

namespace Leantime\Plugins\ProjectOverview\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Leantime\Plugins\ProjectOverview\DTO\ViewDTO;
use Leantime\Plugins\ProjectOverview\Enum\DateTypeEnum;
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
        __DIR__ . '/../dist/css/project-overview.css' => APP_ROOT . '/public/dist/css/plugin-projectOverview.css',
        __DIR__ . '/../dist/js/project-overview.js' => APP_ROOT . '/public/dist/js/plugin-projectOverview.js',
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
     * @return array<int, mixed>
     */
    public function getViewTasks(ViewDTO $viewDTO): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        $newFromDate = $viewDTO->fromDate;
        $newToDate = $viewDTO->toDate;

        // Determine from and to date based on dateType selection.
        if ($viewDTO->dateType !== DateTypeEnum::CUSTOM) {
            $newFromDate = $today->format('Y-m-d');
            $newToDate = (match ($viewDTO->dateType) {
                DateTypeEnum::THIS_WEEK => $today->modify('monday this week +6 days'),
                DateTypeEnum::NEXT_THREE_WEEKS => $today->modify('monday this week +20 days'),
                default => $today->modify('monday this week +13 days'),
            })->format('Y-m-d');
        } elseif ($viewDTO->fromDate && $viewDTO->toDate) {
            $newFromDate = CarbonImmutable::createFromFormat('d-m-Y', $viewDTO->fromDate)->format('Y-m-d');
            $newToDate = CarbonImmutable::createFromFormat('d-m-Y', $viewDTO->toDate)->format('Y-m-d');
        }

        $processedDTO = new ViewDTO(
            title: $viewDTO->title,
            users: $viewDTO->users,
            dateType: $viewDTO->dateType,
            fromDate: $newFromDate,
            toDate: $newToDate,
            columns: $viewDTO->columns,
            projectFilters: $viewDTO->projectFilters,
            priorityFilters: $viewDTO->priorityFilters,
            statusFilters: $viewDTO->statusFilters,
            customFilters: $viewDTO->customFilters
        );

        return $this->projectOverviewRepository->getViewTasks($processedDTO);
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

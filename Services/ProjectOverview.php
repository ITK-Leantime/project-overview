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
     * @return array<int, mixed>
     */
    public function getViewTasks(ViewDTO $viewDTO): array
    {
        $newFromDate = $viewDTO->fromDate;
        $newToDate = $viewDTO->toDate;

        // Determine from and to date based on dateType selection.
        if ($viewDTO->dateType !== DateTypeEnum::CUSTOM) {
            $dateRange = $this->calculateDateRangeForType($viewDTO->dateType);
            $newFromDate = $dateRange['start'];
            $newToDate = $dateRange['end'];
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
     * Calculate date range for a given date type.
     *
     * @param DateTypeEnum $dateType The date type to calculate range for
     * @return array<string, string> Array with 'start' and 'end' keys in Y-m-d format
     * @api
     */
    public function calculateDateRangeForType(DateTypeEnum $dateType): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $monday = $today->modify('monday this week');

        return match ($dateType) {
            DateTypeEnum::THIS_WEEK => [
                'start' => $monday->format('Y-m-d'),
                'end' => $monday->modify('+6 days')->format('Y-m-d'),
            ],
            DateTypeEnum::NEXT_THREE_WEEKS => [
                'start' => $monday->format('Y-m-d'),
                'end' => $monday->modify('+20 days')->format('Y-m-d'),
            ],
            default => [ // NEXT_TWO_WEEKS
                'start' => $monday->format('Y-m-d'),
                'end' => $monday->modify('+13 days')->format('Y-m-d'),
            ],
        };
    }

    /**
     * Calculate all date ranges for filter options.
     *
     * @return array<string, array<string, string>> Array of date ranges keyed by date type value
     * @api
     */
    public function calculateAllDateRanges(): array
    {
        return [
            DateTypeEnum::THIS_WEEK->value => $this->calculateDateRangeForType(DateTypeEnum::THIS_WEEK),
            DateTypeEnum::NEXT_TWO_WEEKS->value => $this->calculateDateRangeForType(DateTypeEnum::NEXT_TWO_WEEKS),
            DateTypeEnum::NEXT_THREE_WEEKS->value => $this->calculateDateRangeForType(DateTypeEnum::NEXT_THREE_WEEKS),
        ];
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

    /**
     * Get all unique tags from all tickets.
     *
     * @return array<int, string> An array of unique tags.
     * @api
     */
    public function getAllUniqueTags(): array
    {
        return $this->projectOverviewRepository->getAllUniqueTags();
    }
}

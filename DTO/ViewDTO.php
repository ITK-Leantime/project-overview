<?php

namespace Leantime\Plugins\ProjectOverview\DTO;

use Leantime\Plugins\ProjectOverview\Enum\DateTypeEnum;

/**
 * Data Transfer Object for a projectOverview view
 */
readonly class ViewDTO
{
    // Default page size.
    public const int DEFAULT_PAGE_SIZE = 50;

    // Max page size.
    public const int MAX_PAGE_SIZE = 200;

    // Maximum allowed page number.
    public const int MAX_PAGE = 10000;

    /**
     * @param  string|null       $title           Title of the view
     * @param  array<int>        $users           Selected user IDs
     * @param  DateTypeEnum      $dateType        Type of date range
     * @param  string|null       $fromDate        Start date of the view
     * @param  string|null       $toDate          End date of the view
     * @param  array<string>     $columns         Selected columns to display
     * @param  array<int, mixed> $projectFilters  Selected project filters (project IDs)
     * @param  array<int, mixed> $priorityFilters Selected priority filters (priority IDs)
     * @param  array<int, mixed> $statusFilters   Selected status filters (status IDs)
     * @param  array<string>     $customFilters   Selected custom filters (filter names)
     * @param  string|null       $sortBy          Column name to sort by
     * @param  string            $sortDirection   Sort direction (ASC or DESC)
     * @param  int|null          $page            1-based page index. Null disables pagination (returns all rows).
     * @param  int|null          $pageSize        Rows per page. Null disables pagination.
     * @param  string|null       $search          Free-text search (reserved for future use; repo does not yet apply it).
     */
    public function __construct(
        public ?string $title,
        public array $users,
        public DateTypeEnum $dateType,
        public ?string $fromDate,
        public ?string $toDate,
        public array $columns,
        public array $projectFilters = [],
        public array $priorityFilters = [],
        public array $statusFilters = [],
        public array $customFilters = [],
        public ?string $sortBy = 'priority',
        public string $sortDirection = 'ASC',
        public ?int $page = null,
        public ?int $pageSize = null,
        public ?string $search = null,
    ) {
    }
}

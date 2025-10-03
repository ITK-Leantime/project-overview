<?php


namespace Leantime\Plugins\ProjectOverview\DTO;

/**
 * Data Transfer Object for a projectOverview view
 */
readonly class ViewDTO
{
    /**
     * @param string|null $title Title of the view
     * @param array $users Selected user IDs
     * @param string $fromDate Start date of the view
     * @param string $toDate End date of the view
     * @param array $columns Selected columns to display
     * @param array $projectFilters Selected project filters
     * @param array $priorityFilters Selected priority filters
     * @param array $statusFilters Selected status filters
     * @param array $customFilters Selected custom filters
     */
    public function __construct(
        public ?string  $title,
        public array   $users,
        public string  $fromDate,
        public string  $toDate,
        public array   $columns,
        public array   $projectFilters = [],
        public array   $priorityFilters = [],
        public array   $statusFilters = [],
        public array   $customFilters = []
    )
    {
    }
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

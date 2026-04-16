<?php

namespace Leantime\Plugins\ProjectOverview\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Leantime\Plugins\ProjectOverview\DTO\ViewDTO;
use Leantime\Plugins\ProjectOverview\Services\ProjectOverview as ProjectOverviewService;

/**
 * This is the project overview repository that makes the relevant SQL queries.
 */
class ProjectOverview
{
    /**
     * Executes a database query using the specified database connection.
     *
     * @return Builder Returns an instance of the query builder.
     */
    private function query(): Builder
    {
        return app('db')->connection()->query();
    }

    /**
     * @return array<int, mixed>
     */
    public function getMilestonesByProjectId(string $projectId): array
    {
        return $this->query()
            ->from('zp_tickets AS ticket')
            ->select([
                'ticket.id',
                'ticket.headline',
                'ticket.projectId',
                'ticket.tags AS color',
            ])
            ->where('ticket.type', '=', 'milestone')
            ->where('projectId', '=', $projectId)
            ->get()
            ->toArray();
    }

    /**
     * Get all projects from the database
     *
     * @access public
     * @return array<int, mixed> Returns an array of all projects
     */
    public function getAllProjects(): array
    {
        return $this->query()
            ->from('zp_projects')
            ->where(function ($query) {
                $query->where('state', '!=', '1')
                    ->orWhereNull('state');
            })
            ->orderBy('name', 'ASC')
            ->get()
            ->keyBy('id')
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * Retrieves a list of tasks based on the ViewDTO.
     *
     * @param ViewDTO $viewDTO The data transfer object containing filter criteria.
     * @return array<int, mixed> Returns an array of tasks matching the specified filters.
     */
    public function getViewTasks(ViewDTO $viewDTO): array
    {
        $fromDate = $viewDTO->fromDate ?? null;
        $toDate = $viewDTO->toDate ?? null;

        $query = $this->query()
            ->from('zp_tickets AS ticket')
            ->select([
                'ticket.id',
                'ticket.headline',
                'ticket.type',
                'ticket.description',
                'ticket.planHours',
                'ticket.hourRemaining',
                'ticket.date',
                'ticket.milestoneid',
                app('db')->connection()->raw('CAST(ticket.dateToFinish AS DATE) as dueDate'),
                'ticket.projectId',
                'ticket.tags',
                'ticket.priority',
                'ticket.status',
                'ticket.dependingTicketId',
                'parent.headline AS parentHeadline',
                't1.id AS authorId',
                't1.firstname AS authorFirstname',
                't1.lastname AS authorLastname',
                't2.id AS editorId',
                't2.firstname AS editorFirstname',
                't2.lastname AS editorLastname',
                app('db')->connection()->raw("(SELECT GROUP_CONCAT(CONCAT(u.firstname, ' ', u.lastname, ': ', ROUND(IFNULL((SELECT SUM(hours) FROM zp_timesheets WHERE ticketId = ticket.id AND userId = u.id), 0), 2)) SEPARATOR '\n') FROM zp_user u WHERE u.id IN (SELECT DISTINCT userId FROM zp_timesheets WHERE ticketId = ticket.id)) as userHours"),
                app('db')->connection()->raw('(SELECT ROUND(IFNULL(SUM(hours), 0), 2) FROM zp_timesheets WHERE ticketId = ticket.id) as sumHours'),
            ])
            ->leftJoin('zp_user AS t1', 'ticket.userId', '=', 't1.id')
            ->leftJoin('zp_user AS t2', 'ticket.editorId', '=', 't2.id')
            ->leftJoin('zp_tickets AS parent', 'ticket.dependingTicketId', '=', 'parent.id')
            ->where('ticket.type', '<>', 'milestone')
            ->where('ticket.status', '>', '0')
            ->where(function ($query) use ($fromDate, $toDate, $viewDTO) {
                // Date ranges are already calculated in the Service layer and passed via DTO
                if ($fromDate && $toDate) {
                    $startDate = CarbonImmutable::createFromFormat(ProjectOverviewService::BACKEND_DATE_FORMAT, $fromDate)->startOfDay();
                    $endDate = CarbonImmutable::createFromFormat(ProjectOverviewService::BACKEND_DATE_FORMAT, $toDate)->endOfDay();
                    $query->where('ticket.dateToFinish', '>', $startDate)
                        ->where('ticket.dateToFinish', '<', $endDate);
                }

                if (in_array('overdue-tickets', $viewDTO->customFilters ?? [])) {
                    $query->orWhereBetween('ticket.dateToFinish', [
                        CarbonImmutable::createFromFormat(ProjectOverviewService::BACKEND_DATE_FORMAT, '2023-03-14')->endOfDay(),
                        $toDate ? CarbonImmutable::createFromFormat(ProjectOverviewService::BACKEND_DATE_FORMAT, $toDate) : CarbonImmutable::now(),
                    ]);
                }

                if (in_array('empty-due-date', $viewDTO->customFilters ?? [])) {
                    $query->orWhere('ticket.dateToFinish', '=', '0000-00-00 00:00:00');
                }
            });

        if (!empty($viewDTO->users)) {
            $query->where(function ($q) use ($viewDTO) {
                if (in_array('unassigned', $viewDTO->users)) {
                    $q->where('ticket.editorId', '=', '');
                }
                if (count(array_diff($viewDTO->users, ['unassigned'])) > 0) {
                    $q->orWhereIn('ticket.editorId', array_diff($viewDTO->users, ['unassigned']));
                }
            });
        }

        if (!empty($viewDTO->projectFilters)) {
            $query->whereIn('ticket.projectId', $viewDTO->projectFilters);
        }

        if (!empty($viewDTO->priorityFilters) || !empty($viewDTO->statusFilters)) {
            $query->where(function ($q) use ($viewDTO) {
                if (!empty($viewDTO->priorityFilters)) {
                    $q->orWhereIn('ticket.priority', $viewDTO->priorityFilters);
                }
                if (!empty($viewDTO->statusFilters)) {
                    $q->orWhereIn('ticket.status', $viewDTO->statusFilters);
                }
            });
        }
        $allowedSortColumns = [
            'headline' => 'ticket.headline',
            'project' => 'ticket.projectId',
            'status' => 'ticket.status',
            'priority' => 'ticket.priority',
            'dateToFinish' => 'ticket.dateToFinish',
            'editorLastname' => 't2.lastname',
            'planHours' => 'ticket.planHours',
            'hourRemaining' => 'ticket.hourRemaining',
            'sumHours' => 'sumHours',
            'milestoneid' => 'ticket.milestoneid',
            'tags' => 'ticket.tags',
        ];

        $sortColumn = $allowedSortColumns[$viewDTO->sortBy] ?? 'ticket.priority';
        $sortDirection = strtoupper($viewDTO->sortDirection) === 'DESC' ? 'DESC' : 'ASC';
        $query->orderBy($sortColumn, $sortDirection);

        return $query->get()->toArray();
    }

    /**
     * Get all unique tags from all tickets
     *
     * @return array<int, string>
     */
    public function getAllUniqueTags(): array
    {
        $results = $this->query()
            ->from('zp_tickets')
            ->select('tags')
            ->whereNotNull('tags')
            ->where('tags', '!=', '')
            ->distinct()
            ->get()
            ->pluck('tags')
            ->toArray();

        // Split comma-separated tags and collect unique values
        $uniqueTags = [];
        foreach ($results as $tagString) {
            $tags = explode(',', $tagString);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if ($tag !== '' && !in_array($tag, $uniqueTags)) {
                    $uniqueTags[] = $tag;
                }
            }
        }

        // Sort alphabetically
        sort($uniqueTags);

        return $uniqueTags;
    }
}

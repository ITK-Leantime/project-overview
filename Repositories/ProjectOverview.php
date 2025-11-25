<?php

namespace Leantime\Plugins\ProjectOverview\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Leantime\Plugins\ProjectOverview\DTO\ViewDTO;
use Leantime\Plugins\ProjectOverview\Enum\DateTypeEnum;

/**
 * This is the project overview repository, that makes (hopefully) the relevant sql queries.
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
            ->limit(200)
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
        $fromDate = $viewDTO->fromDate;
        $toDate = $viewDTO->toDate;

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
                't1.id AS authorId',
                't1.firstname AS authorFirstname',
                't1.lastname AS authorLastname',
                't2.id AS editorId',
                't2.firstname AS editorFirstname',
                't2.lastname AS editorLastname',
                app('db')->connection()->raw('(SELECT GROUP_CONCAT(CONCAT(u.firstname, " ", u.lastname, ": ", ROUND(IFNULL((SELECT SUM(hours) FROM zp_timesheets WHERE ticketId = ticket.id AND userId = u.id), 0), 2)) SEPARATOR "\n") FROM zp_user u WHERE u.id IN (SELECT DISTINCT userId FROM zp_timesheets WHERE ticketId = ticket.id)) as userHours'),
                app('db')->connection()->raw('(SELECT ROUND(IFNULL(SUM(hours), 0), 2) FROM zp_timesheets WHERE ticketId = ticket.id) as sumHours'),
            ])
            ->leftJoin('zp_user AS t1', 'ticket.userId', '=', 't1.id')
            ->leftJoin('zp_user AS t2', 'ticket.editorId', '=', 't2.id')
            ->where('ticket.type', '<>', 'milestone')
            ->where('ticket.status', '>', '0')
            ->where(function ($query) use ($fromDate, $toDate, $viewDTO) {

                // Use from and to date if set
                if ($fromDate && $toDate) {
                    $query->whereBetween('ticket.dateToFinish', [$fromDate, $toDate]);
                }

                // Filter for overdue tickets if set
                if (!in_array('overdue-tickets', $viewDTO->customFilters ?? [])) {
                    $query->where('ticket.dateToFinish', '>', CarbonImmutable::now()->format('Y-m-d'));
                } else {
                    $query->orWhere('ticket.dateToFinish', '<=', CarbonImmutable::now()->format('Y-m-d'));
                }

                // Filter for empty duedate tickets if set
                if (!in_array('empty-due-date', $viewDTO->customFilters ?? [])) {
                    $query->where('ticket.dateToFinish', '!=', '0000-00-00 00:00:00');
                }
            });

        // User filter
        if (!empty($viewDTO->users)) {
            $query->where(function ($q) use ($viewDTO) {
                // Check if custom "unassigned" user is selected
                if (in_array('unassigned', $viewDTO->users)) {
                    $q->where('ticket.editorId', '=', '');
                }
                // Multiple users selected
                $assignedUsers = array_diff($viewDTO->users, ['unassigned']);
                if (count($assignedUsers) > 0) {
                    $q->orWhereIn('ticket.editorId', $assignedUsers);
                }
            });
        }

        // Project filter
        if (!empty($viewDTO->projectFilters)) {
            $query->whereIn('ticket.projectId', $viewDTO->projectFilters);
        }

        // Priority and status filter
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
        $query->orderBy('ticket.priority', 'ASC');

        return $query->get()->toArray();
    }
}

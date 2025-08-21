<?php

namespace Leantime\Plugins\ProjectOverview\Repositories;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;

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
     * getTasks - Retrieve a list of tasks based on the provided filter criteria and sorting options.
     *
     * @param array<int, string>|null $userIdArray    An array of user IDs to filter tasks by editor, or null for no filtering.
     * @param string|null             $searchTerm     A string to search for in task attributes like ID, tags, or headline, or null for no search.
     * @param CarbonInterface         $dateFrom       The starting date for filtering tasks by their due date range.
     * @param CarbonInterface         $dateTo         The ending date for filtering tasks by their due date range.
     * @param int                     $noDueDate      Indicates whether to include tasks with no due date (set to 1 to include, 0 to exclude).
     * @param int                     $overdueTickets Indicates whether to include only overdue tasks (set to 1 for overdue, 0 otherwise).
     * @param string|null             $sortBy         The column to sort by, or null for the default ordering.
     * @param string|null             $sortOrder      The direction of sorting (e.g., 'ASC' or 'DESC'), or null for default ordering.
     *
     * @return array<int, object> An array of tasks matching the filter criteria and sorted as specified.
     */
    public function getTasks(
        ?array $userIdArray,
        ?string $searchTerm,
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo,
        int $noDueDate,
        int $overdueTickets,
        ?string $sortBy,
        ?string $sortOrder
    ): array {
        $fromDateForQuery = $overdueTickets === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', '2023-03-14')->endOfDay()
            : $dateFrom;

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
            ->where(function ($query) use ($fromDateForQuery, $dateTo, $noDueDate) {
                $query->whereBetween('ticket.dateToFinish', [$fromDateForQuery, $dateTo]);

                if ($noDueDate === 1) {
                    $query->orWhere('ticket.dateToFinish', '=', '0000-00-00 00:00:00');
                }
            });

        if (!empty($userIdArray)) {
            $query->whereIn('editorId', $userIdArray);
        }

        if ($searchTerm) {
            $query->where(function ($query) use ($searchTerm) {
                $query->where('ticket.id', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('ticket.tags', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('ticket.headline', 'LIKE', '%' . $searchTerm . '%');
            });
        }

        if ($sortBy && $sortOrder) {
            if ($sortBy === 'editorLastname') {
                $query->orderBy('t2.lastname', $sortOrder);
            } else {
                $query->orderBy('ticket.' . $sortBy, $sortOrder);
            }
        } else {
            $query->orderBy('ticket.priority', 'ASC');
        }

        return $query->get()->toArray();
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
}

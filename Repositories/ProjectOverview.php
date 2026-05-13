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
     * Get milestones for multiple projects in a single query.
     *
     * @param  array<int, int|string> $projectIds The project IDs to fetch milestones for.
     * @return array<int, array<int, mixed>> Milestones grouped by projectId.
     */
    public function getMilestonesByProjectIds(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $results = $this->query()
            ->from('zp_tickets AS ticket')
            ->select([
                'ticket.id',
                'ticket.headline',
                'ticket.projectId',
                'ticket.tags AS color',
            ])
            ->where('ticket.type', '=', 'milestone')
            ->whereIn('ticket.projectId', $projectIds)
            ->get()
            ->toArray();

        $grouped = [];
        foreach ($results as $row) {
            $grouped[$row->projectId][] = $row;
        }

        return $grouped;
    }

    /**
     * Per-ticket logged-hours rows for the given ticket IDs (one row per (ticketId, userId)).
     * Caller composes the userHours display string in PHP, which sidesteps GROUP_CONCAT
     * truncation and keeps the timesheet aggregation scoped to the rendered tickets.
     *
     * @param  array<int, int|string> $ticketIds
     * @return array<int, array<int, array{firstname: string, lastname: string, hours: float}>>
     */
    public function getUserHoursByTicketIds(array $ticketIds): array
    {
        if (empty($ticketIds)) {
            return [];
        }

        $results = $this->query()
            ->from('zp_timesheets AS ts')
            ->select([
                'ts.ticketId',
                'u.firstname',
                'u.lastname',
                app('db')->connection()->raw('ROUND(SUM(ts.hours), 2) AS userTotal'),
            ])
            ->join('zp_user AS u', 'u.id', '=', 'ts.userId')
            ->whereIn('ts.ticketId', $ticketIds)
            ->groupBy('ts.ticketId', 'ts.userId', 'u.firstname', 'u.lastname')
            ->get();

        $grouped = [];
        foreach ($results as $row) {
            $grouped[(int) $row->ticketId][] = [
                'firstname' => (string) $row->firstname,
                'lastname' => (string) $row->lastname,
                'hours' => (float) $row->userTotal,
            ];
        }

        return $grouped;
    }

    /**
     * Returns users assigned via zp_relationuserproject for the given projects, grouped by projectId.
     * Mirrors the column shape of {@see \Leantime\Domain\Projects\Repositories\Projects::getUsersAssignedToProject}.
     *
     * Each project's user list is deduped by user id (the relation table can yield
     * duplicates) and re-indexed with array_values, so callers iterate a 0-indexed
     * list, not a user-id keyed map.
     *
     * @param  array<int, int|string> $projectIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getProjectAssignedUsersByProjectIds(array $projectIds, bool $includeApiUsers = false): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $query = $this->query()
            ->from('zp_relationuserproject AS rup')
            ->select([
                'rup.projectId',
                'u.id',
                app('db')->connection()->raw('IF(u.firstname IS NOT NULL, u.firstname, u.username) AS firstname'),
                'u.lastname',
                'u.username',
                'u.notifications',
                'u.profileId',
                'u.jobTitle',
                'u.source',
                'u.status',
                'u.modified',
                'u.role',
                'rup.projectRole',
            ])
            ->join('zp_user AS u', 'u.id', '=', 'rup.userId')
            ->whereIn('rup.projectId', $projectIds);

        if ($includeApiUsers === false) {
            $query->whereRaw("!(u.source <=> 'api')");
        }

        $results = $query->orderBy('u.lastname')->get();

        $grouped = [];
        foreach ($results as $row) {
            $projectId = (int) $row->projectId;
            $user = (array) $row;
            unset($user['projectId']);
            // Dedupe by user id (relation table can produce duplicates via join paths)
            $grouped[$projectId][$row->id] = $user;
        }

        return array_map('array_values', $grouped);
    }

    /**
     * Returns users belonging to each given client, grouped by clientId.
     *
     * @param  array<int, int|string> $clientIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getUsersByClientIds(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        $results = $this->query()
            ->from('zp_user')
            ->select([
                'id',
                'firstname',
                'lastname',
                'username',
                'notifications',
                'profileId',
                'phone',
                'status',
                'clientId',
            ])
            ->whereIn('clientId', $clientIds)
            ->whereRaw("!(source <=> 'api')")
            ->get();

        $grouped = [];
        foreach ($results as $row) {
            $clientId = (int) $row->clientId;
            $user = (array) $row;
            unset($user['clientId']);
            $grouped[$clientId][] = $user;
        }

        return $grouped;
    }

    /**
     * Returns the subset of $projectIds that the given user has a direct relation row for.
     *
     * @param  array<int, int|string> $projectIds
     * @return array<int, int>
     */
    public function getUserAssignedProjectIds(int $userId, array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }

        return $this->query()
            ->from('zp_relationuserproject')
            ->where('userId', '=', $userId)
            ->whereIn('projectId', $projectIds)
            ->pluck('projectId')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Get all projects from the database
     *
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
                return (array) $item;
            })
            ->toArray();
    }

    /**
     * Retrieves a page of tasks based on the ViewDTO.
     *
     * Pagination: when both {@see ViewDTO::$page} and {@see ViewDTO::$pageSize} are non-null,
     * the result is limited to that page. Otherwise all matching rows are returned.
     * `hasMore` is detected by fetching `pageSize + 1` rows and trimming the sentinel.
     *
     * @param  ViewDTO $viewDTO The data transfer object containing filter criteria.
     * @return array{rows: array<int, mixed>, hasMore: bool}
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

        if (! empty($viewDTO->users)) {
            $query->where(function ($q) use ($viewDTO) {
                if (in_array('unassigned', $viewDTO->users)) {
                    $q->where('ticket.editorId', '=', '');
                }
                if (count(array_diff($viewDTO->users, ['unassigned'])) > 0) {
                    $q->orWhereIn('ticket.editorId', array_diff($viewDTO->users, ['unassigned']));
                }
            });
        }

        if (! empty($viewDTO->projectFilters)) {
            $query->whereIn('ticket.projectId', $viewDTO->projectFilters);
        }

        if (! empty($viewDTO->priorityFilters) || ! empty($viewDTO->statusFilters)) {
            $query->where(function ($q) use ($viewDTO) {
                if (! empty($viewDTO->priorityFilters)) {
                    $q->orWhereIn('ticket.priority', $viewDTO->priorityFilters);
                }
                if (! empty($viewDTO->statusFilters)) {
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
            'sumHours' => 'ts_agg.sumHours',
            'milestoneid' => 'ticket.milestoneid',
            'tags' => 'ticket.tags',
        ];

        $sortColumn = $allowedSortColumns[$viewDTO->sortBy] ?? 'ticket.priority';
        $sortDirection = strtoupper($viewDTO->sortDirection) === 'DESC' ? 'DESC' : 'ASC';

        // sumHours is composed in PHP from per-user logged-hours (see helper), not
        // selected here. Only when the user explicitly sorts by Logged do we add
        // the timesheet aggregation join. To keep the inner aggregate from
        // touching the full timesheets table, we scope it to the same project
        // set the outer query is restricted to.
        if ($viewDTO->sortBy === 'sumHours') {
            $tsAgg = $this->query()
                ->from('zp_timesheets AS ts')
                ->select([
                    'ts.ticketId',
                    app('db')->connection()->raw('ROUND(SUM(ts.hours), 2) AS sumHours'),
                ])
                ->groupBy('ts.ticketId');

            if (! empty($viewDTO->projectFilters)) {
                $tsAgg->join('zp_tickets AS t_scope', 't_scope.id', '=', 'ts.ticketId')
                    ->whereIn('t_scope.projectId', $viewDTO->projectFilters);
            }

            $query->leftJoinSub($tsAgg, 'ts_agg', 'ts_agg.ticketId', '=', 'ticket.id');
        }

        $query->orderBy($sortColumn, $sortDirection);
        // Stable secondary sort so consecutive pages don't shuffle rows on ties
        $query->orderBy('ticket.id', 'ASC');

        if ($viewDTO->page !== null && $viewDTO->pageSize !== null) {
            $page = max(1, min(ViewDTO::MAX_PAGE, $viewDTO->page));
            $pageSize = max(1, min(ViewDTO::MAX_PAGE_SIZE, $viewDTO->pageSize));
            $query->offset(($page - 1) * $pageSize)->limit($pageSize + 1);

            $rows = $query->get()->toArray();
            $hasMore = count($rows) > $pageSize;
            if ($hasMore) {
                $rows = array_slice($rows, 0, $pageSize);
            }

            return ['rows' => $rows, 'hasMore' => $hasMore];
        }

        return ['rows' => $query->get()->toArray(), 'hasMore' => false];
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
                if ($tag !== '' && ! in_array($tag, $uniqueTags)) {
                    $uniqueTags[] = $tag;
                }
            }
        }

        // Sort alphabetically
        sort($uniqueTags);

        return $uniqueTags;
    }
}

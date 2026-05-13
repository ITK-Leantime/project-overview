<?php

namespace Leantime\Plugins\ProjectOverview\Helpers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Tickets\Services\Tickets;
use Leantime\Domain\Users\Services\Users as UserService;
use Leantime\Plugins\ProjectOverview\DTO\ProjectOverviewDTO;
use Leantime\Plugins\ProjectOverview\DTO\ProjectOverviewFiltersDataDTO;
use Leantime\Plugins\ProjectOverview\DTO\UserViewDTO;
use Leantime\Plugins\ProjectOverview\DTO\ViewDTO;
use Leantime\Plugins\ProjectOverview\Repositories\ProjectOverview as ProjectOverviewRepository;
use Leantime\Plugins\ProjectOverview\Services\ProjectOverview as ProjectOverviewService;

/**
 * Class ProjectOverviewHelper
 */
readonly class ProjectOverviewHelper
{
    /**
     * Initialize dependencies.
     *
     * @return void
     */
    public function __construct(
        private ProjectOverviewActionHandler $actionHandler,
        private ProjectOverviewService $projectOverviewService,
        private Tickets $ticketService,
        private UserService $userService,
        private ProjectOverviewRepository $projectOverviewRepository,
    ) {
    }

    /**
     * Retrieves and processes project overview data.
     *
     * @return ProjectOverviewDTO Returns a data transfer object containing
     *                            The required project overview data.
     *
     * @throws BindingResolutionException
     */
    public function getProjectOverviewData(): ProjectOverviewDTO
    {
        // Gather data and init DTO
        $projectTicketStatuses = [];
        $userViewObject = $this->actionHandler->getUserViewsObject();
        $allProjects = $this->projectOverviewService->getAllProjects();
        $viewId = request()->query('view');
        $allUsers = $this->userService->getAll();

        usort($allUsers, fn ($a, $b) => strcmp($a['firstname'] . $a['lastname'], $b['firstname'] . $b['lastname']));
        array_unshift($allUsers, [
            'id' => 'unassigned',
            'firstname' => 'unassigned',
            'lastname' => '',
        ]);
        $ownerViewsCache = [];
        $removedSubscriptions = [];

        // Inject transient subscription from session if present
        $transientSub = session('project_overview.transient_subscription');
        if ($transientSub) {
            $ownerViews = $this->actionHandler->getUserViewsObject($transientSub['ownerUserId']);
            if (isset($ownerViews[$transientSub['ownerViewId']])) {
                $ownerView = UserViewDTO::fromArray($ownerViews[$transientSub['ownerViewId']]);
                $userViewObject[$transientSub['tempViewId']] = array_merge($ownerView->toArray(), [
                    'id' => $transientSub['tempViewId'],
                    'title' => $ownerView->title . ' (Live)',
                    'shareToken' => null,
                    'order' => PHP_INT_MAX,
                    'isTransientSubscription' => true,
                    'subscribeToken' => $transientSub['token'],
                    'subscribedFromName' => $transientSub['ownerName'],
                    'isSubscription' => true,
                ]);
            }
        }

        // Determine which view to eagerly load (first view if none selected)
        $viewKeys = array_keys($userViewObject);
        $selectedViewKey = $viewId;
        if ($selectedViewKey === null && ! empty($viewKeys)) {
            $selectedViewKey = (string) $viewKeys[0];
        }

        foreach ($userViewObject as $key => $userViewData) {
            $userView = UserViewDTO::fromArray($userViewData);
            // Ensure the template always has the view id (used to build the
            // lazy-load sentinel URL for the next page of rows).
            $userViewObject[$key]['id'] = (string) $key;

            // Skip subscription resolution for transient views (already resolved above)
            $isTransient = $userViewData['isTransientSubscription'] ?? false;
            if ($isTransient) {
                $viewDTO = $userView->view;
                // Preserve the extra flags already set
            } elseif ($userView->isSubscription()) {
                $resolvedView = $this->actionHandler->resolveSubscription($userView);

                if ($resolvedView === null) {
                    // Owner deleted the view — auto-remove the broken subscription
                    $this->actionHandler->removeSubscription($key);
                    $removedSubscriptions[] = $userView->title;
                    unset($userViewObject[$key]);

                    continue;
                }

                // Use the owner's ViewDTO for ticket fetching, keep subscriber metadata
                $viewDTO = $resolvedView->view;
                $userViewObject[$key]['isSubscription'] = true;
                $userViewObject[$key]['subscribedFromName'] = $userView->subscribedFromName;
                // Sync title from owner's view so renames propagate to subscribers
                $userViewObject[$key]['title'] = $resolvedView->title;
                // Update the stored view config so template columns are correct
                $userViewObject[$key]['view'] = $resolvedView->toArray()['view'];
            } else {
                $viewDTO = $userView->view;
                $userViewObject[$key]['isSubscription'] = false;
            }

            // Only load tickets for the selected view; other tabs lazy-load on activation
            if ((string) $key === (string) $selectedViewKey) {
                $paginatedDTO = $this->applyDefaultPagination($viewDTO, page: 1);
                $accessibleIds = $this->computeAccessibleProjectIds(
                    (int) session('userdata.id'),
                    array_keys($allProjects),
                    $allProjects
                );
                $page = $this->projectOverviewService->getViewTasks($paginatedDTO, $accessibleIds);
                [$viewTickets, $ticketStatusLabels] = $this->enrichTickets($page['rows'], $allProjects);
                $projectTicketStatuses = array_merge($projectTicketStatuses, $ticketStatusLabels);
                $userViewObject[$key]['tickets'] = $viewTickets;
                $userViewObject[$key]['hasMore'] = $page['hasMore'];
                $userViewObject[$key]['nextPage'] = $page['hasMore'] ? 2 : null;
                $userViewObject[$key]['pageSize'] = $paginatedDTO->pageSize;
            } else {
                $userViewObject[$key]['tickets'] = null;
                $userViewObject[$key]['hasMore'] = false;
                $userViewObject[$key]['nextPage'] = null;
                $userViewObject[$key]['pageSize'] = ViewDTO::DEFAULT_PAGE_SIZE;
            }
        }
        // Flash notification for auto-removed broken subscriptions
        if (! empty($removedSubscriptions)) {
            session()->flash('project_overview-flash_notification', [
                'message' => __('projectOverview.notification.subscription_removed'),
                'type' => 'info',
            ]);
        }

        return new ProjectOverviewDTO(
            userViews: $userViewObject,
            statusLabels: $projectTicketStatuses,
            allStatusLabels: $this->ticketService->getStatusLabels(),
            allPriorities: $this->ticketService->getPriorityLabels(),
            allProjects: $allProjects,
            allUsers: $allUsers,
            selectedView: $viewId,
        );
    }

    /**
     * Fetches and enriches table data for a single view from POST filter values.
     *
     * Page defaults to 1 unless POST contains `page`. Page size defaults to
     * {@see ViewDTO::DEFAULT_PAGE_SIZE} unless POST contains `pageSize`.
     *
     * @param  array<string, mixed> $postData POST data containing filter values.
     * @param  string|null          $viewId   Route-supplied view id; embedded into the response so the template can build the next-page sentinel URL.
     * @return array{userView: array<string, mixed>, statusLabels: array<int, mixed>, allPriorities: array<int, string>, hasMore: bool, nextPage: int|null, pageSize: int}
     */
    public function getViewTableData(array $postData, ?string $viewId = null): array
    {
        $viewDTO = $this->actionHandler->parseFiltersFromPost($postData);
        $viewDTO = $this->applyDefaultPagination(
            $viewDTO,
            page: $viewDTO->page ?? 1,
            pageSize: $viewDTO->pageSize ?? ViewDTO::DEFAULT_PAGE_SIZE,
        );
        $allProjects = $this->projectOverviewService->getAllProjects();

        $accessibleIds = $this->computeAccessibleProjectIds(
            (int) session('userdata.id'),
            array_keys($allProjects),
            $allProjects
        );

        $page = $this->projectOverviewService->getViewTasks($viewDTO, $accessibleIds);
        [$viewTickets, $statusLabels] = $this->enrichTickets($page['rows'], $allProjects);

        return [
            'userView' => [
                'id' => $viewId ?? '',
                'view' => [
                    'columns' => $viewDTO->columns,
                    'sortBy' => $viewDTO->sortBy,
                    'sortDirection' => $viewDTO->sortDirection,
                ],
                'tickets' => $viewTickets,
                'hasMore' => $page['hasMore'],
                'nextPage' => $page['hasMore'] ? $viewDTO->page + 1 : null,
                'pageSize' => $viewDTO->pageSize,
            ],
            'statusLabels' => $statusLabels,
            'allPriorities' => $this->ticketService->getPriorityLabels(),
            'hasMore' => $page['hasMore'],
            'nextPage' => $page['hasMore'] ? $viewDTO->page + 1 : null,
            'pageSize' => $viewDTO->pageSize,
        ];
    }

    /**
     * Fetches one paginated chunk of rows for a view (used by the infinite-scroll sentinel).
     * Page and pageSize come from POST (`page`, `pageSize`); both default if absent.
     *
     * @param  array<string, mixed> $postData
     * @return array{rows: array<int, mixed>, columns: array<int, string>, statusLabels: array<int, mixed>, allPriorities: array<int, string>, hasMore: bool, nextPage: int|null, pageSize: int}
     */
    public function getViewTableRows(array $postData): array
    {
        $viewDTO = $this->actionHandler->parseFiltersFromPost($postData);
        $viewDTO = $this->applyDefaultPagination(
            $viewDTO,
            page: $viewDTO->page ?? 1,
            pageSize: $viewDTO->pageSize ?? ViewDTO::DEFAULT_PAGE_SIZE,
        );
        $allProjects = $this->projectOverviewService->getAllProjects();

        $accessibleIds = $this->computeAccessibleProjectIds(
            (int) session('userdata.id'),
            array_keys($allProjects),
            $allProjects
        );

        $result = $this->projectOverviewService->getViewTasks($viewDTO, $accessibleIds);
        [$rows, $statusLabels] = $this->enrichTickets($result['rows'], $allProjects);

        return [
            'rows' => $rows,
            'columns' => $viewDTO->columns,
            'statusLabels' => $statusLabels,
            'allPriorities' => $this->ticketService->getPriorityLabels(),
            'hasMore' => $result['hasMore'],
            'nextPage' => $result['hasMore'] ? $viewDTO->page + 1 : null,
            'pageSize' => $viewDTO->pageSize,
        ];
    }

    /**
     * Returns a copy of the DTO with page/pageSize filled in (defaults applied
     * only where the original is null). Use page=1 to force a reset to the first
     * page regardless of what the caller provided.
     *
     * @param  ViewDTO  $dto
     * @param  int|null $page
     * @param  int|null $pageSize
     * @return ViewDTO
     */
    private function applyDefaultPagination(ViewDTO $dto, ?int $page = null, ?int $pageSize = null): ViewDTO
    {
        return new ViewDTO(
            title: $dto->title,
            users: $dto->users,
            dateType: $dto->dateType,
            fromDate: $dto->fromDate,
            toDate: $dto->toDate,
            columns: $dto->columns,
            projectFilters: $dto->projectFilters,
            priorityFilters: $dto->priorityFilters,
            statusFilters: $dto->statusFilters,
            customFilters: $dto->customFilters,
            sortBy: $dto->sortBy,
            sortDirection: $dto->sortDirection,
            page: $page ?? $dto->page ?? 1,
            pageSize: $pageSize ?? $dto->pageSize ?? ViewDTO::DEFAULT_PAGE_SIZE,
            search: $dto->search,
        );
    }

    /**
     * Enriches tickets with project data, users, milestones, status labels, and a userHours tooltip.
     *
     * All cross-ticket lookups are batched to avoid N+1 queries:
     *  - milestones: 1 query for all projectIds
     *  - project-access user lists: bounded number of queries regardless of project count
     *    (relation users + client users + UserService::getAll() when any project uses
     *     psettings='all'; the latter typically resolves from cache)
     *  - per-user logged hours per ticket: 1 query for all ticketIds
     *
     * @param  array<object>                    $tickets     Raw ticket objects from the repository.
     * @param  array<int, array<string, mixed>> $allProjects All projects indexed by ID.
     * @return array{0: array<object>, 1: array<int, mixed>} Enriched tickets and status labels.
     */
    private function enrichTickets(array $tickets, array $allProjects): array
    {
        $projectIds = array_unique(array_column($tickets, 'projectId'));
        $statusLabels = [];

        $milestonesByProject = $this->projectOverviewRepository->getMilestonesByProjectIds($projectIds);

        foreach ($projectIds as $projectId) {
            // getStatusLabels is cached per-project inside core's TicketRepository.
            $statusLabels[$projectId] = $this->ticketService->getStatusLabels($projectId);
        }

        $usersByProject = $this->loadProjectUsers(
            (int) session('userdata.id'),
            $projectIds,
            $allProjects
        );

        $ticketIds = array_unique(array_column($tickets, 'id'));
        $userHoursByTicket = $this->projectOverviewRepository->getUserHoursByTicketIds($ticketIds);

        foreach ($tickets as $ticket) {
            if ($ticket->dueDate == '0000-00-00') {
                $ticket->dueDate = null;
            }
            $ticket->projectUsers = $usersByProject[$ticket->projectId] ?? [];
            $ticket->projectMilestones = $milestonesByProject[$ticket->projectId] ?? [];
            $ticket->projectName = $allProjects[$ticket->projectId]['name'] ?? '';
            $ticket->projectLink = '/projects/changeCurrentProject/' . $ticket->projectId;

            $userRows = $userHoursByTicket[(int) $ticket->id] ?? [];
            $ticket->userHours = $this->formatUserHoursTooltip($userRows);
            $ticket->sumHours = number_format(
                array_sum(array_column($userRows, 'hours')),
                2,
                '.',
                ''
            );
        }

        return [$tickets, $statusLabels];
    }

    /**
     * Resolves "users that have access to project X" for a batch of projects in one pass,
     * mirroring the access logic of {@see UserService::getUsersWithProjectAccess()} but
     * collapsing the per-project queries into a small bounded set:
     *   - 1 query for relation users (when any project needs them)
     *   - 1 query for client users (when any project has psettings='clients')
     *   - 1 call to UserService::getAll() (when any project has psettings='all')
     *
     * @param  int                              $currentUserId
     * @param  array<int, int|string>           $projectIds
     * @param  array<int, array<string, mixed>> $allProjects   All projects indexed by ID (must include psettings, clientId).
     * @return array<int, array<int, array<string, mixed>>> projectId => list of users
     */
    private function loadProjectUsers(int $currentUserId, array $projectIds, array $allProjects): array
    {
        if (empty($projectIds)) {
            return [];
        }

        $accessibleProjectIds = $this->computeAccessibleProjectIds($currentUserId, $projectIds, $allProjects);

        if (empty($accessibleProjectIds)) {
            return [];
        }

        // Step 2: bucket accessible projects by psettings so we know what user list each needs.
        $allUsersProjectIds = [];
        $clientProjectsByClientId = [];
        $relationOnlyProjectIds = [];
        foreach ($accessibleProjectIds as $pid) {
            $project = $allProjects[$pid] ?? null;
            if ($project === null) {
                continue;
            }
            $psettings = $project['psettings'] ?? '';
            if ($psettings === 'all') {
                $allUsersProjectIds[] = $pid;
            } elseif ($psettings === 'clients') {
                $clientProjectsByClientId[(int) ($project['clientId'] ?? 0)][] = $pid;
            } else {
                $relationOnlyProjectIds[] = $pid;
            }
        }

        // Step 3: batch-fetch the data (at most three queries: relation users, client users, all users).
        $relationUsersNeeded = array_merge($relationOnlyProjectIds, ...array_values($clientProjectsByClientId));
        $relationUsersByProject = ! empty($relationUsersNeeded)
            ? $this->projectOverviewRepository->getProjectAssignedUsersByProjectIds($relationUsersNeeded)
            : [];

        $clientUsersByClient = ! empty($clientProjectsByClientId)
            ? $this->projectOverviewRepository->getUsersByClientIds(array_keys($clientProjectsByClientId))
            : [];

        $allUsersList = ! empty($allUsersProjectIds)
            ? $this->userService->getAll()
            : [];

        // Step 4: assemble the per-project map.
        $result = [];
        foreach ($allUsersProjectIds as $pid) {
            $result[$pid] = $allUsersList;
        }
        foreach ($clientProjectsByClientId as $cid => $pids) {
            $clientUsers = $clientUsersByClient[$cid] ?? [];
            foreach ($pids as $pid) {
                $merged = $clientUsers;
                $seenIds = array_column($merged, 'id');
                foreach ($relationUsersByProject[$pid] ?? [] as $u) {
                    if (! in_array($u['id'], $seenIds, true)) {
                        $merged[] = $u;
                        $seenIds[] = $u['id'];
                    }
                }
                $result[$pid] = $merged;
            }
        }
        foreach ($relationOnlyProjectIds as $pid) {
            $result[$pid] = $relationUsersByProject[$pid] ?? [];
        }

        return $result;
    }

    /**
     * Returns the subset of $candidateProjectIds the given user can see.
     * Mirrors Leantime's psettings logic (admin/owner = all; 'all' = all logged-in;
     * 'clients' = same clientId; otherwise relation row required).
     *
     * @param  int                              $currentUserId
     * @param  array<int, int|string>           $candidateProjectIds
     * @param  array<int, array<string, mixed>> $allProjects         Indexed by id (must include psettings, clientId).
     * @return array<int, int>
     */
    private function computeAccessibleProjectIds(int $currentUserId, array $candidateProjectIds, array $allProjects): array
    {
        if (empty($candidateProjectIds)) {
            return [];
        }

        $role = (string) session('userdata.role');
        $userClientId = (int) (session('userdata.clientId') ?? 0);
        $isPrivileged = in_array($role, [Roles::$admin, Roles::$owner], true);

        $needsRelationCheck = [];
        $accessibleProjectIds = [];
        foreach ($candidateProjectIds as $pid) {
            $project = $allProjects[$pid] ?? null;
            if ($project === null) {
                continue;
            }
            $psettings = $project['psettings'] ?? '';

            if ($isPrivileged || $psettings === 'all') {
                $accessibleProjectIds[] = (int) $pid;

                continue;
            }

            if ($psettings === 'clients' && (int) ($project['clientId'] ?? 0) === $userClientId) {
                $accessibleProjectIds[] = (int) $pid;

                continue;
            }

            $needsRelationCheck[] = (int) $pid;
        }

        if (! empty($needsRelationCheck)) {
            $assigned = $this->projectOverviewRepository->getUserAssignedProjectIds($currentUserId, $needsRelationCheck);
            $accessibleProjectIds = array_merge($accessibleProjectIds, $assigned);
        }

        return array_values(array_unique(array_map('intval', $accessibleProjectIds)));
    }

    /**
     * Builds the tooltip string shown over the sumHours cell.
     *
     * @param  array<int, array{firstname: string, lastname: string, hours: float}> $rows
     * @return string
     */
    private function formatUserHoursTooltip(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = trim($row['firstname'] . ' ' . $row['lastname']) . ': ' . number_format($row['hours'], 2, '.', '');
        }

        return implode("\n", $lines);
    }

    /**
     * Retrieves and prepares filter data for the project overview.
     *
     * @param  array<string, string> $data An associative array containing input parameters.
     * @return ProjectOverviewFiltersDataDTO A data transfer object containing all necessary data for populating the project overview filters.
     */
    public function getProjectOverviewFiltersData(array $data): ProjectOverviewFiltersDataDTO
    {
        $selectedViewId = $data['id'] ?? null;

        // Get all users
        $allUsers = $this->userService->getAll();
        // Get all projects
        $allProjects = $this->projectOverviewService->getAllProjects();
        // Get all priorities
        $allPriorities = $this->ticketService->getPriorityLabels();
        // Get all status labels
        $allStatusLabels = $this->ticketService->getStatusLabels();

        // Add unassigned user option
        array_unshift($allUsers, [
            'id' => 'unassigned',
            'firstname' => 'Unassigned',
            'lastname' => '',
        ]);

        // Sort projects alphabetically
        uasort($allProjects, fn ($a, $b) => strcmp($a['name'], $b['name']));

        // Get precalculated date ranges for display (with inclusive end dates)
        $dateRanges = $this->projectOverviewService->calculateDisplayDateRanges();

        // Default user views data
        $userViewsData = [
            'users' => [],
            'allColumns' => $this->actionHandler->getAvailableColumns(),
            'dateType' => '',
            'fromDate' => date(ProjectOverviewService::FRONTEND_DATE_FORMAT, strtotime('last monday')),
            'toDate' => date(ProjectOverviewService::FRONTEND_DATE_FORMAT, strtotime('sunday next week')),
            'projectFilters' => [],
            'priorityFilters' => [],
            'statusFilters' => [],
            'customFilters' => [],
            'title' => '',
            'selectedColumns' => [],
            'selectedViewId' => null,
        ];

        // Override with user view data if available
        $isSubscription = false;
        $isTransientSubscription = false;
        $subscribeToken = null;

        // Check if selected view is a transient subscription from session
        $transientSub = session('project_overview.transient_subscription');
        if ($selectedViewId !== null && $transientSub && $selectedViewId === $transientSub['tempViewId']) {
            $isSubscription = true;
            $isTransientSubscription = true;
            $subscribeToken = $transientSub['token'];

            // Resolve the owner's view
            $ownerViews = $this->actionHandler->getUserViewsObject($transientSub['ownerUserId']);
            if (isset($ownerViews[$transientSub['ownerViewId']])) {
                $ownerView = UserViewDTO::fromArray($ownerViews[$transientSub['ownerViewId']]);
                $viewDTO = $ownerView->view;

                $userViewsData = array_merge($userViewsData, [
                    'title' => $ownerView->title . ' (Live)',
                    'users' => $viewDTO->users,
                    'selectedColumns' => $viewDTO->columns,
                    'dateType' => $viewDTO->dateType->value,
                    'fromDate' => date(ProjectOverviewService::FRONTEND_DATE_FORMAT, strtotime($viewDTO->fromDate)),
                    'toDate' => date(ProjectOverviewService::FRONTEND_DATE_FORMAT, strtotime($viewDTO->toDate)),
                    'projectFilters' => $viewDTO->projectFilters,
                    'priorityFilters' => $viewDTO->priorityFilters,
                    'statusFilters' => $viewDTO->statusFilters,
                    'customFilters' => $viewDTO->customFilters,
                    'selectedViewId' => $selectedViewId,
                ]);
            }
        } elseif ($selectedViewId !== null) {
            $userViewArray = $this->actionHandler->getUserViewsObject();

            if ($userViewArray) {
                $userViewData = $userViewArray[$selectedViewId] ?? null;
                if ($userViewData) {
                    $userView = UserViewDTO::fromArray($userViewData);

                    // If this is a subscription, resolve the owner's view config
                    if ($userView->isSubscription()) {
                        $isSubscription = true;
                        $resolvedView = $this->actionHandler->resolveSubscription($userView);
                        if ($resolvedView !== null) {
                            $viewDTO = $resolvedView->view;
                        } else {
                            $viewDTO = $userView->view;
                        }
                    } else {
                        $viewDTO = $userView->view;
                    }

                    $userViewsData = array_merge($userViewsData, [
                        'title' => $userView->title,
                        'users' => $viewDTO->users,
                        'selectedColumns' => $viewDTO->columns,
                        'dateType' => $viewDTO->dateType->value,
                        'fromDate' => date(ProjectOverviewService::FRONTEND_DATE_FORMAT, strtotime($viewDTO->fromDate)),
                        'toDate' => date(ProjectOverviewService::FRONTEND_DATE_FORMAT, strtotime($viewDTO->toDate)),
                        'projectFilters' => $viewDTO->projectFilters,
                        'priorityFilters' => $viewDTO->priorityFilters,
                        'statusFilters' => $viewDTO->statusFilters,
                        'customFilters' => $viewDTO->customFilters,
                        'selectedViewId' => $selectedViewId,
                    ]);
                }
            }
        }

        return new ProjectOverviewFiltersDataDTO(
            allUsers: $allUsers,
            allProjects: $allProjects,
            allPriorities: $allPriorities,
            allStatusLabels: $allStatusLabels,
            allColumns: $userViewsData['allColumns'],
            dateType: $userViewsData['dateType'],
            fromDate: $userViewsData['fromDate'],
            toDate: $userViewsData['toDate'],
            projectFilters: $userViewsData['projectFilters'],
            priorityFilters: $userViewsData['priorityFilters'],
            statusFilters: $userViewsData['statusFilters'],
            customFilters: $userViewsData['customFilters'],
            title: $userViewsData['title'],
            selectedColumns: $userViewsData['selectedColumns'],
            users: $userViewsData['users'],
            selectedViewId: $userViewsData['selectedViewId'],
            dateRanges: $dateRanges,
            isSubscription: $isSubscription,
            isTransientSubscription: $isTransientSubscription,
            subscribeToken: $subscribeToken,
        );
    }
}

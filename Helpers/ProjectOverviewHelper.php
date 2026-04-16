<?php

namespace Leantime\Plugins\ProjectOverview\Helpers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Domain\Tickets\Services\Tickets;
use Leantime\Domain\Users\Services\Users as UserService;
use Leantime\Plugins\ProjectOverview\DTO\ProjectOverviewDTO;
use Leantime\Plugins\ProjectOverview\DTO\ProjectOverviewFiltersDataDTO;
use Leantime\Plugins\ProjectOverview\DTO\UserViewDTO;
use Leantime\Plugins\ProjectOverview\Services\ProjectOverview as ProjectOverviewService;

/**
 * Class ProjectOverviewHelper
 */
readonly class ProjectOverviewHelper
{
    /**
     * Initialize dependencies.
     * @return void
     */
    public function __construct(
        private ProjectOverviewActionHandler $actionHandler,
        private ProjectOverviewService $projectOverviewService,
        private Tickets $ticketService,
        private UserService $userService
    ) {
    }

    /**
     * Retrieves and processes project overview data.
     *
     * @return ProjectOverviewDTO Returns a data transfer object containing
     * The required project overview data.
     * @throws BindingResolutionException
     */
    public function getProjectOverviewData(): ProjectOverviewDTO
    {
        // Gather data and init DTO
        $projectTicketStatuses = [];
        $userViewObject = $this->actionHandler->getUserViewsObject();
        $allProjects = $this->projectOverviewService->getAllProjects();
        $viewId = $_GET['view'] ?? null;
        $allUsers = $this->userService->getAll();

        usort($allUsers, fn($a, $b) => strcmp($a['firstname'] . $a['lastname'], $b['firstname'] . $b['lastname']));
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

        foreach ($userViewObject as $key => $userViewData) {
            $userView = UserViewDTO::fromArray($userViewData);

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

            $viewTickets = $this->projectOverviewService->getViewTasks($viewDTO);
            [$viewTickets, $ticketStatusLabels] = $this->enrichTickets($viewTickets, $allProjects);
            $projectTicketStatuses = array_merge($projectTicketStatuses, $ticketStatusLabels);
            $userViewObject[$key]['tickets'] = $viewTickets;
        }
        // Flash notification for auto-removed broken subscriptions
        if (!empty($removedSubscriptions)) {
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
     * @param array<string, mixed> $postData POST data containing filter values.
     * @return array{userView: array<string, mixed>, statusLabels: array<int, mixed>, allPriorities: array<int, string>}
     */
    public function getViewTableData(array $postData): array
    {
        $viewDTO = $this->actionHandler->parseFiltersFromPost($postData);
        $allProjects = $this->projectOverviewService->getAllProjects();

        $viewTickets = $this->projectOverviewService->getViewTasks($viewDTO);
        [$viewTickets, $statusLabels] = $this->enrichTickets($viewTickets, $allProjects);

        return [
            'userView' => [
                'view' => [
                    'columns' => $viewDTO->columns,
                    'sortBy' => $viewDTO->sortBy,
                    'sortDirection' => $viewDTO->sortDirection,
                ],
                'tickets' => $viewTickets,
            ],
            'statusLabels' => $statusLabels,
            'allPriorities' => $this->ticketService->getPriorityLabels(),
        ];
    }

    /**
     * Enriches tickets with project data, users, milestones, and status labels.
     *
     * @param array<object>                    $tickets     Raw ticket objects from the repository.
     * @param array<int, array<string, mixed>> $allProjects All projects indexed by ID.
     * @return array{0: array<object>, 1: array<int, mixed>} Enriched tickets and status labels.
     */
    private function enrichTickets(array $tickets, array $allProjects): array
    {
        $projectIds = array_unique(array_column($tickets, 'projectId'));
        $statusLabels = [];
        $userAndProject = [];
        $milestonesAndProject = [];

        foreach ($projectIds as $projectId) {
            $statusLabels[$projectId] = $this->ticketService->getStatusLabels($projectId);
            $userAndProject[$projectId] = $this->userService->getUsersWithProjectAccess(((int)session('userdata.id')), $projectId);
            $milestonesAndProject[$projectId] = $this->projectOverviewService->getMilestonesByProjectId($projectId);
        }

        foreach ($tickets as $ticket) {
            if ($ticket->dueDate == '0000-00-00') {
                $ticket->dueDate = null;
            }
            $ticket->projectUsers = $userAndProject[$ticket->projectId];
            $ticket->projectMilestones = $milestonesAndProject[$ticket->projectId];
            $ticket->projectName = $allProjects[$ticket->projectId]['name'] ?? '';
            $ticket->projectLink = '/projects/changeCurrentProject/' . $ticket->projectId;
        }

        return [$tickets, $statusLabels];
    }

    /**
     * Retrieves and prepares filter data for the project overview.
     *
     * @param array<string, string> $data An associative array containing input parameters.
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
        uasort($allProjects, fn($a, $b) => strcmp($a['name'], $b['name']));


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

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
        foreach ($userViewObject as $key => $userViewData) {
            $userView = UserViewDTO::fromArray($userViewData);
            $viewDTO = $userView->view;

            $viewTickets = $this->projectOverviewService->getViewTasks($viewDTO);
            $projectIds = array_unique(array_column($viewTickets, 'projectId'));
            $userAndProject = [];
            $milestonesAndProject = [];

            foreach ($projectIds as $projectId) {
                $projectTicketStatuses[$projectId] = $this->ticketService->getStatusLabels($projectId);
                $userAndProject[$projectId] = $this->userService->getUsersWithProjectAccess(((int)session('userdata.id')), $projectId);
                $milestonesAndProject[$projectId] = $this->projectOverviewService->getMilestonesByProjectId($projectId);
            }

            foreach ($viewTickets as $ticket) {
                if ($ticket->dueDate == '0000-00-00') {
                    $ticket->dueDate = null;
                }
                $ticket->projectUsers = $userAndProject[$ticket->projectId];
                $ticket->projectMilestones = $milestonesAndProject[$ticket->projectId];
                $ticket->projectName = $allProjects[$ticket->projectId]['name'];
                $ticket->projectLink = '/projects/changeCurrentProject/' . $ticket->projectId;
            }
            $userViewObject[$key]['tickets'] = $viewTickets;
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
        if ($selectedViewId !== null) {
            $userViewArray = $this->actionHandler->getUserViewsObject();

            if ($userViewArray) {
                $userViewData = $userViewArray[$selectedViewId] ?? null;
                if ($userViewData) {
                    $userView = UserViewDTO::fromArray($userViewData);
                    $viewDTO = $userView->view;

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
        );
    }
}

<?php

namespace Leantime\Plugins\ProjectOverview\Helpers;

use Leantime\Domain\Tickets\Services\Tickets;
use Leantime\Domain\Users\Services\Users as UserService;
use Leantime\Domain\Users\Repositories\Users as UserRepository;
use Leantime\Plugins\ProjectOverview\DTO\ProjectOverviewDTO;
use Leantime\Plugins\ProjectOverview\DTO\ProjectOverviewFiltersDataDTO;
use Leantime\Plugins\ProjectOverview\DTO\ViewDTO;
use Leantime\Plugins\ProjectOverview\Services\ProjectOverview;

readonly class ProjectOverviewHelper
{
    /**
     * Initialize dependencies.
     * @return void
     */
    public function __construct(
        private ProjectOverviewActionHandler $actionHandler,
        private ProjectOverview $projectOverviewService,
        private Tickets $ticketService,
        private UserService $userService
    ) {
    }


    /**
     * Retrieves and processes project overview data.
     *
     * @return ProjectOverviewDTO Returns a data transfer object containing
     * the required project overview data.
     */
    public function getProjectOverviewData(): ProjectOverviewDTO
    {
        // Gather data and init DTO
        $projectTicketStatuses = [];
        $userViewObject = $this->actionHandler->getUserViewsObject();
        $allProjects = $this->projectOverviewService->getAllProjects();
        uasort($allProjects, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        $allUsers = $this->userService->getAll();
        usort($allUsers, fn($a, $b) => strcmp($a['firstname'] . $a['lastname'], $b['firstname'] . $b['lastname']));

        foreach ($userViewObject as $key => $userView) {
            $userViewDTO = new ViewDTO(
                title: $userView['title'] ?? null,
                users: $userView['users'],
                fromDate: $userView['fromDate'],
                toDate: $userView['toDate'],
                columns: $userView['columns'],
                projectFilters: $userView['projectFilters'],
                priorityFilters: $userView['priorityFilters'],
                statusFilters: $userView['statusFilters'],
                customFilters: $userView['customFilters']
            );
            $viewTickets = $this->projectOverviewService->getViewTasks($userViewDTO);
            $projectIds = array_unique(array_column($viewTickets, 'projectId'));
            $userViewObject[$key]['tickets'] = [];
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
                $ticket->sumHours = round((float)$ticket->sumHours, 2);
                $userViewObject[$key]['tickets'] = $viewTickets;
            }
        }
        return new ProjectOverviewDTO(
            userViews: $userViewObject,
            statusLabels: $projectTicketStatuses,
            allStatusLabels: $this->ticketService->getStatusLabels(),
            allPriorities: $this->ticketService->getPriorityLabels(),
            allProjects: $allProjects,
            allUsers: $allUsers,
            selectedView: ($_GET['viewId'] ?? null),
        );
    }

    /**
     * Retrieves and prepares filter data for the project overview.
     *
     * @param array $data An associative array containing input parameters.
     * @return ProjectOverviewFiltersDataDTO A data transfer object containing all necessary data for populating the project overview filters.
     */
    public function getProjectOverviewFiltersData(array $data): ProjectOverviewFiltersDataDTO
    {
        $selectedViewId = $data['id'] ?? null;
        $allUsers = $this->userService->getAll();
        usort($allUsers, fn($a, $b) => strcmp($a['firstname'] . $a['lastname'], $b['firstname'] . $b['lastname']));

        $allProjects = $this->projectOverviewService->getAllProjects();
        uasort($allProjects, fn($a, $b) => strcmp($a['name'], $b['name']));

        $allPriorities = $this->ticketService->getPriorityLabels();
        $allStatusLabels = $this->ticketService->getStatusLabels();

        // Default user views data
        $userViewsData = [
            'users' => [],
            'allColumns' => $this->actionHandler->getAvailableColumns(),
            'fromDate' => date('d-m-Y', strtotime('last monday')),
            'toDate' => date('d-m-Y', strtotime('next sunday')),
            'projectFilters' => [],
            'priorityFilters' => [],
            'statusFilters' => [],
            'customFilters' => [],
            'title' => '',
            'selectedColumns' => [],
            'selectedViewId' => null,
        ];
        // Override with user view data if available
        if ($selectedViewId) {
            $userViewArray = $this->actionHandler->getUserViewsObject();
            if ($userViewArray) {
                $userView = $userViewArray[urldecode($selectedViewId)] ?? null;
                if ($userView) {
                    $userViewsData = array_merge($userViewsData, [
                        'title' => $userView['title'],
                        'users' => $userView['users'],
                        'selectedColumns' => $userView['columns'],
                        'fromDate' => date('d-m-Y', strtotime($userView['fromDate'])),
                        'toDate' => date('d-m-Y', strtotime($userView['toDate'])),
                        'projectFilters' => $userView['projectFilters'],
                        'priorityFilters' => $userView['priorityFilters'],
                        'statusFilters' => $userView['statusFilters'],
                        'customFilters' => $userView['customFilters'],
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
        );
    }
}

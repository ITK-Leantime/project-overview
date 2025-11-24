<?php

namespace Leantime\Plugins\ProjectOverview\Helpers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Domain\Tickets\Services\Tickets;
use Leantime\Domain\Users\Services\Users as UserService;
use Leantime\Plugins\ProjectOverview\DTO\ProjectOverviewDTO;
use Leantime\Plugins\ProjectOverview\DTO\ProjectOverviewFiltersDataDTO;
use Leantime\Plugins\ProjectOverview\DTO\ViewDTO;
use Leantime\Plugins\ProjectOverview\Enum\DateTypeEnum;
use Leantime\Plugins\ProjectOverview\Services\ProjectOverview;

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
        private ProjectOverview $projectOverviewService,
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
        $projectTicketStatuses = [];
        $userAndProject = [];
        $milestonesAndProject = [];

        $userViewObject = $this->actionHandler->getUserViewsObject();
        $allProjects = $this->getSortedProjects();
        $allUsers = $this->getSortedUsers();
        $viewId = request()->get('viewId');

        // Sort project-select alphabetically
        uasort($allProjects, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        // Sort user-select alphabetically
        usort($allUsers, fn($a, $b) => strcasecmp($a['firstname'] . $a['lastname'], $b['firstname'] . $b['lastname']));

        // Add custom "unassigned" value to user-select
        array_unshift($allUsers, [
            'id' => 'unassigned',
            'firstname' => 'Unassigned',
            'lastname' => '',
        ]);

        // Loop users' views and attach tickets
        foreach ($userViewObject as $key => $userView) {
            $dateType = DateTypeEnum::tryFrom($userView['dateType']);

            $userViewDTO = new ViewDTO(
                title: $userView['title'] ?? null,
                users: $userView['users'],
                dateType: $dateType,
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

            foreach ($projectIds as $projectId) {
                if (!isset($projectTicketStatuses[$projectId])) {
                    $projectTicketStatuses[$projectId] = $this->ticketService->getStatusLabels($projectId);
                }

                if (!isset($userAndProject[$projectId])) {
                    $userAndProject[$projectId] = $this->userService->getUsersWithProjectAccess(((int)session('userdata.id')), $projectId);
                }

                if (!isset($milestonesAndProject[$projectId])) {
                    $milestonesAndProject[$projectId] = $this->projectOverviewService->getMilestonesByProjectId($projectId);
                }
            }

            foreach ($viewTickets as $ticket) {
                if ($ticket->dueDate == '0000-00-00') {
                    $ticket->dueDate = null;
                }
                $ticket->projectUsers = $userAndProject[$ticket->projectId] ?? [];
                $ticket->projectMilestones = $milestonesAndProject[$ticket->projectId] ?? [];
                $ticket->projectName = $allProjects[$ticket->projectId]['name'] ?? '';
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

        // Default user views data
        $userViewsData = [
            'users' => [],
            'allColumns' => $this->actionHandler->getAvailableColumns(),
            'dateType' => '',
            'fromDate' => date('d-m-Y', strtotime('last monday')),
            'toDate' => date('d-m-Y', strtotime('sunday next week')),
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
                $userView = $userViewArray[urldecode($selectedViewId)] ?? null;
                if ($userView) {
                    $userViewsData = array_merge($userViewsData, [
                        'title' => $userView['title'],
                        'users' => $userView['users'],
                        'selectedColumns' => $userView['columns'],
                        'dateType' => $userView['dateType'],
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
            allUsers: $this->getSortedUsers(),
            allProjects: $this->getSortedProjects(),
            allPriorities: $this->ticketService->getPriorityLabels(),
            allStatusLabels: $this->ticketService->getStatusLabels(),
            allColumns: $this->actionHandler->getAvailableColumns(),
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
        );
    }

    /**
     * Retrieves all users, sorts them alphabetically (case-insensitive),
     * and prepends an 'Unassigned' user option.
     *
     * @return array
     */
    private function getSortedUsers(): array
    {
        $allUsers = $this->userService->getAll();
        usort($allUsers, fn($a, $b) => strcasecmp($a['firstname'] . $a['lastname'], $b['firstname'] . $b['lastname']));

        array_unshift($allUsers, [
            'id' => 'unassigned',
            'firstname' => 'Unassigned',
            'lastname' => '',
        ]);

        return $allUsers;
    }

    /**
     * Retrieves all projects and sorts them alphabetically (case-insensitive) by name.
     *
     * @return array
     */
    private function getSortedProjects(): array
    {
        $allProjects = $this->projectOverviewService->getAllProjects();
        uasort($allProjects, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $allProjects;
    }
}

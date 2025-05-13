<?php

namespace Leantime\Plugins\ProjectOverview\Controllers;

use Carbon\CarbonImmutable;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Plugins\ProjectOverview\Helpers\ProjectOverviewActionHandler;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Leantime\Plugins\ProjectOverview\Services\ProjectOverview as ProjectOverviewService;
use Leantime\Domain\Users\Services\Users as UserService;
use Leantime\Core\UI\Template;

/**
 * ProjectOverview
 */
class ProjectOverview extends Controller
{
    private ProjectOverviewService $projectOverviewService;
    private TicketService $ticketService;
    private UserService $userService;

    /**
     * @param ProjectOverviewService $projectOverviewService
     * @param TicketService          $ticketService
     * @param UserService            $userService
     * @param Template               $tpl
     * @return void
     */
    public function init(ProjectOverviewService $projectOverviewService, TicketService $ticketService, UserService $userService, Template $tpl): void
    {
        $this->projectOverviewService = $projectOverviewService;
        $this->ticketService = $ticketService;
        $this->userService = $userService;
        $this->tpl = $tpl;
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function post(): Response
    {
        if (!AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['Error' => 'Not Authorized'], 403);
        }
        $redirectUrl = BASE_URL . '/ProjectOverview/projectOverview';

        $actionHandler = new ProjectOverviewActionHandler();

        if (isset($_POST['action'])) {
            if ($_POST['action'] == 'adjustPeriod') {
                $redirectUrl = $actionHandler->adjustPeriod($_POST, $redirectUrl);
            }
        }

        return Frontcontroller::redirect($redirectUrl);
    }
    /**
     * Gathers data and feeds it to the template.
     *
     * @return Response
     */
    public function get(): Response
    {
        $userIdArray = [];
        $searchTermForFilter = null;
        $sortByForFilter = null;
        $sortOrderForFilter = null;
        $noDueDateForFilter = 'true';
        $overdueTicketsForFilter = 'true';
        $loadAllConfirm = $_GET['loadAllConfirm'] ?? false;
        $allProjects = $this->projectOverviewService->getAllProjects();

        try {
            if (isset($_GET['fromDate']) && $_GET['fromDate'] !== '') {
                if ($_GET['fromDate'][0] === '+' || $_GET['fromDate'][0] === '-') {
                    $fromDate = CarbonImmutable::now()->startOfDay()->modify($_GET['fromDate']);
                } else {
                    $fromDate = CarbonImmutable::createFromFormat('Y-m-d', $_GET['fromDate'])->startOfDay();
                }
            } else {
                $fromDate = CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
            }

            if (isset($_GET['toDate']) && $_GET['toDate'] !== '') {
                if ($_GET['toDate'][0] === '+' || $_GET['toDate'][0] === '-') {
                    $toDate = CarbonImmutable::now()->startOfDay()->modify($_GET['toDate']);
                } else {
                    $toDate = CarbonImmutable::createFromFormat('Y-m-d', $_GET['toDate'])->endOfDay();
                }
            } else {
                $toDate = CarbonImmutable::now()->endOfWeek(CarbonImmutable::SUNDAY)->endOfDay();
            }
        } catch (\Exception $e) {
            $fromDate = CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
            $toDate = CarbonImmutable::now()->endOfWeek(CarbonImmutable::SUNDAY)->endOfDay();
        }

        if (isset($_GET['userIds']) && $_GET['userIds'] !== '') {
            $userIdArray = explode(',', $_GET['userIds']);
        }
        if (isset($_GET['searchTerm']) && $_GET['searchTerm'] !== '') {
            $searchTermForFilter = $_GET['searchTerm'];
        }
        if (isset($_GET['noDueDate']) && $_GET['noDueDate'] !== '') {
            $noDueDateForFilter = $_GET['noDueDate'];
        }
        if (isset($_GET['overdueTickets']) && $_GET['overdueTickets'] !== '') {
            $overdueTicketsForFilter = $_GET['overdueTickets'];
        }
        if (isset($_GET['sortBy']) && isset($_GET['sortOrder']) && $_GET['sortBy'] !== '' && $_GET['sortOrder'] !== '') {
            $sortByForFilter = $_GET['sortBy'];
            $sortOrderForFilter = $_GET['sortOrder'];
        }

        $this->tpl->assign('fromDate', $fromDate);
        $this->tpl->assign('toDate', $toDate);
        $this->tpl->assign('selectedFilterUser', $userIdArray);
        $this->tpl->assign('currentSearchTerm', $searchTermForFilter);
        $this->tpl->assign('overdueTickets', $overdueTicketsForFilter);
        $this->tpl->assign('noDueDate', $noDueDateForFilter);

        $noDueDate = $noDueDateForFilter === 'false' ? 0 : 1;
        $overdueTickets = $overdueTicketsForFilter === 'false' ? 0 : 1;
        $allTickets = [];
        $projectIds = [];
        if (!empty($userIdArray) || $loadAllConfirm) {
            $allTickets = $this->projectOverviewService->getTasks($userIdArray, $searchTermForFilter, $fromDate, $toDate, $noDueDate, $overdueTickets, $sortByForFilter, $sortOrderForFilter);

            // A list of unique projectids
            $projectIds = array_unique(array_column($allTickets, 'projectId'));
        }


        $userAndProject = [];
        $milestonesAndProject = [];

        $projectTicketStatuses = [];
        // Get users and milestones by project, as these differ by project.
        foreach ($projectIds as &$projectId) {
            $projectTicketStatuses[$projectId] = $this->ticketService->getStatusLabels($projectId);
            $userAndProject[$projectId] = $this->userService->getUsersWithProjectAccess(((int)session('userdata.id')), $projectId);
            $milestonesAndProject[$projectId] = $this->projectOverviewService->getMilestonesByProjectId($projectId);
        }

        // Then the milestones/users are set into the tickets array on each ticket by project.
        foreach ($allTickets as &$ticket) {
            $ticket['projectUsers'] = $userAndProject[$ticket['projectId']];
            $ticket['projectMilestones'] = $milestonesAndProject[$ticket['projectId']];
            $ticket['projectName'] = $allProjects[$ticket['projectId']]['name'];
        }

        $this->tpl->assign('fromDate', $fromDate);
        $this->tpl->assign('toDate', $toDate);
        // The two below gets hardcoded labels from the ticket repo.
        $this->tpl->assign('priorities', $this->ticketService->getPriorityLabels());
        $this->tpl->assign('statusLabels', $projectTicketStatuses);
        $this->tpl->assign('sortBy', $sortByForFilter);
        $this->tpl->assign('sortOrder', $sortOrderForFilter);
        $this->tpl->assign('allSelectedUsers', $userIdArray);

        $this->tpl->assign('allUsers', $this->userService->getAll());

        // All tickets assignet to the template
        $this->tpl->assign('allTickets', $allTickets);

        return $this->tpl->display('ProjectOverview.projectOverview');
    }
}

<?php

namespace Leantime\Plugins\ProjectOverview\Controllers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Plugins\ProjectOverview\DTO\ViewDTO;
use Leantime\Plugins\ProjectOverview\Helpers\ProjectOverviewActionHandler;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Leantime\Plugins\ProjectOverview\Services\ProjectOverview as ProjectOverviewService;
use Leantime\Domain\Users\Services\Users as UserService;
use Leantime\Domain\Users\Repositories\Users as UserRepository;
use Leantime\Core\UI\Template;

/**
 * ProjectOverview
 */
class ProjectOverview extends Controller
{
    private ProjectOverviewService $projectOverviewService;
    private TicketService $ticketService;
    private UserService $userService;

    private UserRepository $userRepository;
    private ProjectOverviewActionHandler $actionHandler;

    /**
     * @param ProjectOverviewService $projectOverviewService
     * @param TicketService          $ticketService
     * @param UserService            $userService
     * @param Template               $tpl
     * @return void
     */
    public function init(ProjectOverviewService $projectOverviewService, TicketService $ticketService, UserService $userService, Template $tpl, UserRepository $userRepository, ProjectOverviewActionHandler $actionHandler): void
    {
        $this->projectOverviewService = $projectOverviewService;
        $this->ticketService = $ticketService;
        $this->userService = $userService;
        $this->tpl = $tpl;
        $this->userRepository = $userRepository;
        $this->actionHandler = $actionHandler;

    }

    /**
     * @throws \Exception
     */
    public function loadFilters($data): ?Response
    {
        $userViewsEncoded = $this->userRepository->getUserSettings(session('userdata.id'), 'projectoverview.view');
        $allUsers = $this->userService->getAll();
        usort($allUsers, fn($a, $b) => strcmp($a['firstname'] . $a['lastname'], $b['firstname'] . $b['lastname']));
        $allProjects = $this->projectOverviewService->getAllProjects();
        uasort($allProjects, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        $allPriorities = $this->ticketService->getPriorityLabels();
        $allStatusLabels = $this->ticketService->getStatusLabels();
        $allColumns = $this->actionHandler->getAvailableColumns();
        $firstDayOfCurrentWeek = date('d-m-Y', strtotime('last monday'));
        $lastDayOfNextWeek = date('d-m-Y', strtotime('next sunday'));
        $this->tpl->assign('users', []);
        $this->tpl->assign('allColumns', $allColumns);
        $this->tpl->assign('fromDate', $firstDayOfCurrentWeek);
        $this->tpl->assign('toDate', $lastDayOfNextWeek);
        $this->tpl->assign('projectFilters', []);
        $this->tpl->assign('priorityFilters', []);
        $this->tpl->assign('statusFilters', []);
        $this->tpl->assign('customFilters', []);
        $this->tpl->assign('allUsers', $allUsers);
        $this->tpl->assign('allProjects', $allProjects);
        $this->tpl->assign('allPriorities', $allPriorities);
        $this->tpl->assign('allStatusLabels', $allStatusLabels);

        if ($userViewsEncoded) {
            $userViewsDecoded = $this->actionHandler->decodeViewSettings($userViewsEncoded);
            $selectedViewId = urldecode($data['id']);
            $userView = $userViewsDecoded[$selectedViewId] ?? null;
            if ($userView) {
                $this->tpl->assign('title', $userView['title']);
                $this->tpl->assign('users', $userView['users']);
                $this->tpl->assign('selectedColumns', $userView['columns']);
                $this->tpl->assign('allColumns', $allColumns);
                $this->tpl->assign('fromDate', date('d-m-Y', strtotime($userView['fromDate'])));
                $this->tpl->assign('toDate', date('d-m-Y', strtotime($userView['toDate'])));
                $this->tpl->assign('projectFilters', $userView['projectFilters']);
                $this->tpl->assign('priorityFilters', $userView['priorityFilters']);
                $this->tpl->assign('statusFilters', $userView['statusFilters']);
                $this->tpl->assign('customFilters', $userView['customFilters']);
                $this->tpl->assign('allUsers', $allUsers);
                $this->tpl->assign('allProjects', $allProjects);
                $this->tpl->assign('allPriorities', $allPriorities);
                $this->tpl->assign('allStatusLabels', $allStatusLabels);
                $this->tpl->assign('selectedViewId', $selectedViewId);
            }
        }

        return $this->tpl->display('ProjectOverview.projectOverviewFilters');
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

        if (isset($_POST['action'])) {
            if ($_POST['action'] == 'SaveView') {
                $redirectUrl = $this->actionHandler->SaveView($_POST, $redirectUrl);
            }
            if ($_POST['action'] == 'adjustPeriod') {
                $redirectUrl = $this->actionHandler->adjustPeriod($_POST, $redirectUrl);
            }
            if ($_POST['action'] == 'deleteView') {
                $redirectUrl = $this->actionHandler->deleteView($_POST, $redirectUrl);
            }
            if ($_POST['action'] == 'renameView') {
                $redirectUrl = $this->actionHandler->renameView($_POST, $redirectUrl);
            }
        }

        return Frontcontroller::redirect($redirectUrl);
    }

    /**
     * Gathers data and feeds it to the template.
     *
     * @return Response
     * @throws BindingResolutionException
     */
    public function get(): Response
    {
        $projectTicketStatuses = [];
        $userViewsDecoded = [];
        $userViewsEncoded = $this->userRepository->getUserSettings(session('userdata.id'), 'projectoverview.view');
        $allProjects = $this->projectOverviewService->getAllProjects();
        uasort($allProjects, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        $allUsers = $this->userService->getAll();
        usort($allUsers, fn($a, $b) => strcmp($a['firstname'] . $a['lastname'], $b['firstname'] . $b['lastname']));
        if ($userViewsEncoded) {

            $userViewsDecoded = $this->actionHandler->decodeViewSettings($userViewsEncoded);
            foreach ($userViewsDecoded as $key => $userView) {
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
                $userViewsDecoded[$key]['tickets'] = [];
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
                    $ticket->sumHours = round($ticket->sumHours, 2);
                    $userViewsDecoded[$key]['tickets'] = $viewTickets;
                }
            }
        }

        $this->tpl->assign('statusLabels', $projectTicketStatuses);
        $this->tpl->assign('allStatusLabels', $this->ticketService->getStatusLabels());
        $this->tpl->assign('allPriorities', $this->ticketService->getPriorityLabels());
        $this->tpl->assign('allProjects', $allProjects);
        $this->tpl->assign('allUsers', $allUsers);
        $this->tpl->assign('userViews', $userViewsDecoded);
        $this->tpl->assign('selectedView', $_GET['viewId'] ?? null);

        return $this->tpl->display('ProjectOverview.projectOverview');
        }
}

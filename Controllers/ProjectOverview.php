<?php

namespace Leantime\Plugins\ProjectOverview\Controllers;

use Exception;
use Leantime\Core\Controller;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Projects\Services\Projects as ProjectService;
use Leantime\Domain\Reactions\Models\Reactions;
use Leantime\Domain\Setting\Repositories\Setting;
use Leantime\Domain\Users\Services\Users as UserService;
use Leantime\Domain\Timesheets\Services\Timesheets as TimesheetService;
use Leantime\Domain\Comments\Services\Comments as CommentService;
use Leantime\Domain\Reactions\Services\Reactions as ReactionService;
use Leantime\Domain\Reports\Services\Reports as ReportService;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Comments\Repositories\Comments as CommentRepository;
use Leantime\Core\Frontcontroller as FrontcontrollerCore;
use Leantime\Core\Frontcontroller;

/**
 *
 */
class ProjectOverview extends Controller
{
    private ProjectService $projectService;
    private TicketService $ticketService;
    private UserService $userService;
    private TimesheetService $timesheetService;
    private CommentService $commentService;
    private ReactionService $reactionsService;
    private Setting $settingRepo;

    /**
     * @param ProjectService   $projectService
     * @param TicketService    $ticketService
     * @param UserService      $userService
     * @param TimesheetService $timesheetService
     * @param CommentService   $commentService
     * @param ReactionService  $reactionsService
     * @return void
     * @throws BindingResolutionException
     * @throws BindingResolutionException
     */
    public function init(
        ProjectService $projectService,
        TicketService $ticketService,
        UserService $userService,
        TimesheetService $timesheetService,
        CommentService $commentService,
        ReactionService $reactionsService,
        Setting $settingRepo
    ): void {
        $this->projectService = $projectService;
        $this->ticketService = $ticketService;
        $this->userService = $userService;
        $this->timesheetService = $timesheetService;
        $this->commentService = $commentService;
        $this->reactionsService = $reactionsService;
        $this->settingRepo = $settingRepo;

        $_SESSION['lastPage'] = BASE_URL . "/dashboard/show";
    }


    /**
     * Gathers data and feeds it to the template.
     *
     * @return Response
     *
     * @throws Exception
     */
    public function get(): Response
    {
        $currentProjectId = $this->projectService->getCurrentProjectId();
        if (0 === $currentProjectId) {
            return FrontcontrollerCore::redirect(BASE_URL . "/dashboard/home");
        }


        [$progressSteps, $percentDone] = $this->projectService->getProjectSetupChecklist($currentProjectId);
        $this->tpl->assign("progressSteps", $progressSteps);
        $this->tpl->assign("percentDone", $percentDone);



        $allTickets = $this->ticketService->getAll(array(
            "orderBy" => "priority",
            "orderDirection" => "ASC"
        ));
        $projectIds = array_unique(array_column($allTickets, 'projectId'));
        $userAndPorject = array();
        foreach ($projectIds as &$projectId) {
            $userAndPorject[$projectId] = $this->userService->getUsersWithProjectAccess($_SESSION['userdata']['id'], $projectId);
        }
        foreach ($allTickets as &$ticket) {
            $ticket['projectUsers'] =$userAndPorject[$ticket["projectId"]];
        }
        // Project Progress
        $progress = $this->projectService->getProjectProgress($currentProjectId);
        $this->tpl->assign('projectProgress', $progress);
        $this->tpl->assign("currentProjectName", $this->projectService->getProjectName($currentProjectId));
        // Milestones

        $allProjectMilestones = $this->ticketService->getAllMilestones(["sprint" => '', "type" => "milestone", "currentProject" => $_SESSION["currentProject"]]);
        $this->tpl->assign('milestones', $allProjectMilestones);


        $completedOnboarding = $this->settingRepo->getSetting("companysettings.completedOnboarding");
        $this->tpl->assign("completedOnboarding", $completedOnboarding);

        // TICKETS
        $this->tpl->assign("onTheClock", $this->timesheetService->isClocked($_SESSION["userdata"]["id"]));
        $this->tpl->assign('priorities', $this->ticketService->getPriorityLabels());
        $this->tpl->assign("types", $this->ticketService->getTicketTypes());
        $this->tpl->assign("statusLabels", $this->ticketService->getStatusLabels());


        $this->tpl->assign('allTickets', $allTickets);
        return $this->tpl->display('ProjectOverview.projectOverview');
    }
}

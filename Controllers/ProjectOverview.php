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

        $project = $this->projectService->getProject($currentProjectId);
        if (isset($project['id']) === false) {
            return FrontcontrollerCore::redirect(BASE_URL . "/dashboard/home");
        }

        $projectRedirectFilter = static::dispatch_filter("dashboardRedirect", "/dashboard/show", array("type" => $project["type"]));
        if ($projectRedirectFilter != "/dashboard/show") {
            return FrontcontrollerCore::redirect(BASE_URL . $projectRedirectFilter);
        }

        [$progressSteps, $percentDone] = $this->projectService->getProjectSetupChecklist($currentProjectId);
        $this->tpl->assign("progressSteps", $progressSteps);
        $this->tpl->assign("percentDone", $percentDone);

        $project['assignedUsers'] = $this->projectService->getProjectUserRelation($currentProjectId);
        $this->tpl->assign('project', $project);

        $userReaction = $this->reactionsService->getUserReactions($_SESSION['userdata']['id'], 'project', $currentProjectId, Reactions::$favorite);
        if ($userReaction && is_array($userReaction) && count($userReaction) > 0) {
            $this->tpl->assign("isFavorite", true);
        } else {
            $this->tpl->assign("isFavorite", false);
        }

        $this->tpl->assign('allUsers', $this->userService->getAll());

        //Project Progress
        $progress = $this->projectService->getProjectProgress($currentProjectId);
        $this->tpl->assign('projectProgress', $progress);
        $this->tpl->assign("currentProjectName", $this->projectService->getProjectName($currentProjectId));

        //Milestones

        $allProjectMilestones = $this->ticketService->getAllMilestones(["sprint" => '', "type" => "milestone", "currentProject" => $_SESSION["currentProject"]]);
        $this->tpl->assign('milestones', $allProjectMilestones);

        $comments = app()->make(CommentRepository::class);

        //Delete comment
        if (isset($_GET['delComment']) === true) {
            $commentId = (int)($_GET['delComment']);

            $comments->deleteComment($commentId);

            $this->tpl->setNotification($this->language->__("notifications.comment_deleted"), "success", "projectcomment_deleted");
        }

        // add replies to comments
        $comment = array_map(function ($comment) use ($comments) {
            $comment['replies'] = $comments->getReplies($comment['id']);
            return $comment;
        }, $comments->getComments('project', $currentProjectId, 0));


        $url = parse_url(CURRENT_URL);
        $this->tpl->assign('delUrlBase', $url['scheme'] . '://' . $url['host'] . $url['path'] . '?delComment='); // for delete comment

        $this->tpl->assign('comments', $comment);
        $this->tpl->assign('numComments', $comments->countComments('project', $currentProjectId));

        $completedOnboarding = $this->settingRepo->getSetting("companysettings.completedOnboarding");
        $this->tpl->assign("completedOnboarding", $completedOnboarding);

        // TICKETS
        $this->tpl->assign("onTheClock", $this->timesheetService->isClocked($_SESSION["userdata"]["id"]));
        $this->tpl->assign('efforts', $this->ticketService->getEffortLabels());
        $this->tpl->assign('priorities', $this->ticketService->getPriorityLabels());
        $this->tpl->assign("types", $this->ticketService->getTicketTypes());
        $this->tpl->assign("statusLabels", $this->ticketService->getStatusLabels());
        $allTickets = $this->ticketService->getAll();
        $this->tpl->assign('allTickets', $allTickets);
        return $this->tpl->display('ProjectOverview.projectOverview');
    }
}

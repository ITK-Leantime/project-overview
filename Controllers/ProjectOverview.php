<?php

namespace Leantime\Plugins\ProjectOverview\Controllers;

use Carbon\CarbonImmutable;
use Leantime\Core\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Leantime\Plugins\ProjectOverview\Services\ProjectOverview as ProjectOverviewService;
use Leantime\Domain\Users\Services\Users as UserService;
use Leantime\Core\Support\DateTimeHelper;
use Leantime\Core\Template;

/**
 * ProjectOverview
 */
class ProjectOverview extends Controller
{
    private ProjectOverviewService $projectOverviewService;
    private TicketService $ticketService;
    private UserService $userService;
    private DateTimeHelper $dateTimeHelper;

    /**
     * @param ProjectOverviewService $projectOverviewService
     * @param TicketService $ticketService
     * @param UserService $userService
     * @param DateTimeHelper $dateTimeHelper
     * @param Template $tpl
     * @return void
     */
    public function init(ProjectOverviewService $projectOverviewService, TicketService $ticketService, UserService $userService, DateTimeHelper $dateTimeHelper, Template $tpl): void
    {
        $this->projectOverviewService = $projectOverviewService;
        $this->ticketService = $ticketService;
        $this->userService = $userService;
        $this->dateTimeHelper = $dateTimeHelper;
        $this->tpl = $tpl;
    }

    /**
     * Gathers data and feeds it to the template.
     *
     * @return Response
     */
    public function get(): Response
    {
        // Filters for the sql select
        $userIdArray = [];
        $searchTermForFilter = null;
        $dateFromForFilter = CarbonImmutable::now();
        $dateToForFilter = CarbonImmutable::now()->addDays(7);
        $allProjects = $this->projectOverviewService->getAllProjects();

        if (isset($_GET['dateFrom'])) {
            $dateFromForFilter = $this->dateTimeHelper->parseUserDateTime($_GET['dateFrom'], 'start');
        }

        if (isset($_GET['dateTo'])) {
            $dateToForFilter = $this->dateTimeHelper->parseUserDateTime($_GET['dateTo'], 'end');
        }

        if (isset($_GET['userIds']) && $_GET['userIds'] !== '') {
            $userIdArray = explode(',', $_GET['userIds']);
        }

        if (isset($_GET['searchTerm']) && $_GET['searchTerm'] !== '') {
            $searchTermForFilter = $_GET['searchTerm'];
        }

        $this->tpl->assign('selectedDateFrom', $dateFromForFilter->toDateString());
        $this->tpl->assign('selectedDateTo', $dateToForFilter->toDateString());
        $this->tpl->assign('selectedFilterUser', $userIdArray);
        $this->tpl->assign('currentSearchTerm', $searchTermForFilter);

        $allTickets = $this->projectOverviewService->getTasks($userIdArray, $searchTermForFilter, $dateFromForFilter, $dateToForFilter);

        // A list of unique projectids
        $projectIds = array_unique(array_column($allTickets, 'projectId'));
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
            if (isset($ticket['milestoneid'])) {
                // If the ticket has a milestone, then the color of that milestone is retrieved here.
                // selectedMilestoneColor is only used for styling
                $ticket['selectedMilestoneColor'] = $this->projectOverviewService->getSelectedMilestoneColor($ticket['milestoneid']);
            }
        }

        // The two below gets hardcoded labels from the ticket repo.
        $this->tpl->assign('priorities', $this->ticketService->getPriorityLabels());
        $this->tpl->assign('statusLabels', $projectTicketStatuses);

        $this->tpl->assign('allUsers', $this->userService->getAll());

        // All tickets assignet to the template
        $this->tpl->assign('allTickets', $allTickets);

        return $this->tpl->display('ProjectOverview.projectOverview');
    }
}

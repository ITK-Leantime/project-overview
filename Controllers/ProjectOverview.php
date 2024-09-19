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
     * @param TicketService          $ticketService
     * @param UserService            $userService
     * @param DateTimeHelper         $dateTimeHelper
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
        $userIdForFilter = null;
        $searchTermForFilter = null;
        $dateFromForFilter = CarbonImmutable::now();
        $dateToForFilter = CarbonImmutable::now()->addDays(7);
        $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : null;
        $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : null;

        if (!is_null($dateFrom)) {
            if (str_contains($dateFrom, '.')){
                $dateFrom = str_replace($dateFrom, ".", "/");
                die(var_dump($dateFrom));
            }
            $dateFromForFilter = $this->dateTimeHelper->parseUserDateTime($dateFrom, 'start');
        }

        if (!is_null($dateTo)) {
            if (str_contains($dateTo, '.')){
                $dateTo = str_replace($dateTo, ".", "/");
            }
            $dateToForFilter = $this->dateTimeHelper->parseUserDateTime($dateTo, 'end');
        }

        if (isset($_GET['userId']) && $_GET['userId'] !== '') {
            $userIdForFilter = $_GET['userId'];
        }

        if (isset($_GET['searchTerm']) && $_GET['searchTerm'] !== '') {
            $searchTermForFilter = $_GET['searchTerm'];
        }

        $this->tpl->assign('selectedDateFrom', $dateFromForFilter->toDateString());
        $this->tpl->assign('selectedDateTo', $dateToForFilter->toDateString());
        $this->tpl->assign('selectedFilterUser', $userIdForFilter);
        $this->tpl->assign('currentSearchTerm', $searchTermForFilter);

        $allTickets = $this->projectOverviewService->getTasks($userIdForFilter, $searchTermForFilter, $dateFromForFilter, $dateToForFilter);

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

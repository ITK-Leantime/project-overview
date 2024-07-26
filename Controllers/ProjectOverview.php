<?php

namespace Leantime\Plugins\ProjectOverview\Controllers;

use Leantime\Core\Controller;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Leantime\Plugins\ProjectOverview\Services\ProjectOverview as ProjectOverviewService;
use Leantime\Domain\Users\Services\Users as UserService;

/**
 * ProjectOverview
 */
class ProjectOverview extends Controller
{
    private ProjectOverviewService $projectOverviewService;
    private TicketService $ticketService;
    private UserService $userService;

    /**
     * @param ProjectOverviewService   projectOverviewService
     * @param TicketService                                   $ticketService
     * @param UserService                                     $userService
     * @return void
     */
    public function init(ProjectOverviewService $projectOverviewService, TicketService $ticketService, UserService $userService): void
    {
        $this->projectOverviewService = $projectOverviewService;
        $this->ticketService = $ticketService;
        $this->userService = $userService;
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
        // Filters for the sql select
        $userIdForFilter = null;
        $searchTermForFilter = null;

        if (isset($_GET['userId']) && $_GET['userId'] !== '') {
            $userIdForFilter = $_GET['userId'];
        }

        if (isset($_GET['searchTerm']) && $_GET['searchTerm'] !== '') {
            $searchTermForFilter = $_GET['searchTerm'];
        }

        $this->tpl->assign('selectedFilterUser', $userIdForFilter);
        $this->tpl->assign('currentSearchTerm', $searchTermForFilter);

        $allTickets = $this->projectOverviewService->getTasks($userIdForFilter, $searchTermForFilter);

        // A list of unique projectids
        $projectIds = array_unique(array_column($allTickets, 'projectId'));
        $userAndProject = [];
        $milestonesAndProject = [];

        // Get users and milestones by project, as these differ by project.
        foreach ($projectIds as &$projectId) {
            $userAndProject[$projectId] = $this->userService->getUsersWithProjectAccess($_SESSION['userdata']['id'], $projectId);
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
        $this->tpl->assign('statusLabels', $this->ticketService->getStatusLabels());

        $this->tpl->assign('allUsers', $this->userService->getAll(true));

        // All tickets assignet to the template
        $this->tpl->assign('allTickets', $allTickets);

        return $this->tpl->display('ProjectOverview.projectOverview');
    }
}

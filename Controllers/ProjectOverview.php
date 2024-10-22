<?php

namespace Leantime\Plugins\ProjectOverview\Controllers;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
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
     * @param Template               $tpl
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
        $dateFromForSelect = CarbonImmutable::now()->addDays(7);
        $dateToForSelect = CarbonImmutable::now();
        $dateToForFilter = CarbonImmutable::now()->addDays(7);
        $allProjects = $this->projectOverviewService->getAllProjects();
        $userTimeZone = $this->dateTimeHelper->getTimezone();

        if (isset($_GET['dateFrom'])) {
            $dateFromForSelect = CarbonImmutable::createFromFormat('d/m/Y', $_GET['dateFrom']);
            $dateFromForFilter = $this->getCarbonImmutable($_GET['dateFrom'], 'start', $userTimeZone);
        }

        if (isset($_GET['dateTo'])) {
            $dateToForSelect = CarbonImmutable::createFromFormat('d/m/Y', $_GET['dateTo']);
            $dateToForFilter = $this->getCarbonImmutable($_GET['dateTo'], 'end', $userTimeZone);
        }

        if (isset($_GET['userIds']) && $_GET['userIds'] !== '') {
            $userIdArray = explode(',', $_GET['userIds']);
        }

        if (isset($_GET['searchTerm']) && $_GET['searchTerm'] !== '') {
            $searchTermForFilter = $_GET['searchTerm'];
        }

        $this->tpl->assign('selectedDateFrom', $dateFromForSelect->toDateString());
        $this->tpl->assign('selectedDateTo', $dateToForSelect->toDateString());
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
        }

        // The two below gets hardcoded labels from the ticket repo.
        $this->tpl->assign('priorities', $this->ticketService->getPriorityLabels());
        $this->tpl->assign('statusLabels', $projectTicketStatuses);

        $this->tpl->assign('allUsers', $this->userService->getAll());

        // All tickets assignet to the template
        $this->tpl->assign('allTickets', $allTickets);

        return $this->tpl->display('ProjectOverview.projectOverview');
    }


    /**
     * Creates a CarbonImmutable object based on the input date, time of day, and timezone.
     *
     * @param string         $inputDate The input date in the format 'd/m/Y'
     * @param string         $timeOfDay Determines whether to set the time to start or end of the day
     * @param CarbonTimeZone $timezone  The timezone for the date
     * @return CarbonImmutable The CarbonImmutable object with the adjusted time
     */
    private function getCarbonImmutable(string $inputDate, string $timeOfDay, CarbonTimeZone $timezone): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('d/m/Y', $inputDate, $timezone);

        if ($timeOfDay === 'start') {
            $date = $date->startOfDay();
        }

        if ($timeOfDay === 'end') {
            $date = $date->endOfDay();
        }

        return $date->setTimeZone('UTC');
    }
}

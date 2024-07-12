<?php

namespace Leantime\Plugins\ProjectOverview\Controllers;

use Exception;
use Leantime\Core\Controller;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;

/**
 * Import class
 */
class ProjectOverview extends Controller
{
    private TicketService $ticketService;

    /**
     * constructor
     *
     * @param ImportHelper $importHelper
     *
     * @return void
     */
    public function init(
        TicketService $ticketService,
    ): void
    {
        $this->ticketService = $ticketService;
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
        $allTickets = $this->ticketService->getAll();
        $this->tpl->assign('allTickets', $allTickets);
        return $this->tpl->display('ProjectOverview.projectOverview');
    }

    /**
     * Handles the data submitted.
     * Reads submitted file, settings and stores data in tmp file.
     *
     * @param array<string, string|int> $params
     *
     * @return void
     *
     * @throws Exception
     */
    public function post(array $params): void
    {
    }
}

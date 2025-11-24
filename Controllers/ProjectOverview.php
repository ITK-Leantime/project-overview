<?php

namespace Leantime\Plugins\ProjectOverview\Controllers;

use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Plugins\ProjectOverview\Helpers\ProjectOverviewActionHandler;
use Leantime\Plugins\ProjectOverview\Helpers\ProjectOverviewHelper;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Core\UI\Template;

/**
 * Class ProjectOverview
 */
class ProjectOverview extends Controller
{
    private ProjectOverviewActionHandler $actionHandler;
    private ProjectOverviewHelper $projectOverviewHelper;

    /**
     * @param Template                     $tpl
     * @param ProjectOverviewActionHandler $actionHandler
     * @param ProjectOverviewHelper        $projectOverviewHelper
     * @return void
     */
    public function init(Template $tpl, ProjectOverviewActionHandler $actionHandler, ProjectOverviewHelper $projectOverviewHelper): void
    {
        $this->tpl = $tpl;
        $this->actionHandler = $actionHandler;
        $this->projectOverviewHelper = $projectOverviewHelper;
    }

    /**
     * Loads filters data and serves it back to the template.
     *
     * @param array<string, string> $data
     *
     * @used Called via HTMX GET request
     * @throws Exception
     * @return Response|null
     */
    public function loadFilters(array $data): ?Response
    {
        $filtersData = $this->projectOverviewHelper->getProjectOverviewFiltersData($data);
        $this->tpl->assign('filtersData', $filtersData);
        return $this->tpl->display('ProjectOverview.projectOverviewFilters');
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function post(): Response
    {
        if (!AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['Error' => 'Not Authorized'], 403);
        }

        $redirectUrl = BASE_URL . '/ProjectOverview/projectOverview';
        $action = $_POST['action'];

        if (isset($action)) {
            match ($action) {
                'saveView' =>
                $redirectUrl = $this->actionHandler->saveView($_POST, $redirectUrl),
                'deleteView' =>
                $this->actionHandler->deleteView($_POST['viewId']),
                'renameView' =>
                $redirectUrl = $this->actionHandler->renameView($_POST['viewId'], $_POST['viewName'], $redirectUrl),
                'saveTabOrder' =>
                $this->actionHandler->saveTabOrder($_POST),
                default => null
            };
        }

        return Frontcontroller::redirect($redirectUrl);
    }

    /**
    /**
     * Gathers data, services it as ProjectOverviewDTO and feeds it to the template.
     *
     * @return Response
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function get(): Response
    {
        // Check for flash notification
        if (session()->has('project_overview-flash_notification')) {
            $notification = session('project_overview-flash_notification');
            $this->tpl->setNotification($notification['message'], $notification['type']);
        }

        $userViewsData = $this->projectOverviewHelper->getProjectOverviewData();
        $this->tpl->assign('userViewsData', $userViewsData);
        return $this->tpl->display('ProjectOverview.projectOverview');
    }
}

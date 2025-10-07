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
     * @used Called via HTMX GET request
     * @throws Exception
     * @return Response|null
     */
    public function loadFilters($data): ?Response
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

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'saveView':
                    $redirectUrl = $this->actionHandler->saveView($_POST, $redirectUrl);
                    break;
                case 'adjustPeriod':
                    $redirectUrl = $this->actionHandler->adjustPeriod($_POST, $redirectUrl);
                    break;
                case 'deleteView':
                    $viewId = $_POST['viewId'];
                    $this->actionHandler->deleteView($viewId);
                    break;
                case 'renameView':
                    $viewId = $_POST['viewId'];
                    $viewName = str_replace(' ', '_', $_POST['viewName']);
                    $this->actionHandler->renameView($viewId, $viewName);
                    break;
            }
        }

        return Frontcontroller::redirect($redirectUrl);
    }

    /**
     * Gathers data, services it as ProjectOverviewDTO and feeds it to the template.
     *
     * @return Response
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function get(): Response
    {
        $userViewsData = $this->projectOverviewHelper->getProjectOverviewData();
        $this->tpl->assign('userViewsData', $userViewsData);
        return $this->tpl->display('ProjectOverview.projectOverview');
    }
}

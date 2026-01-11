<?php

namespace Leantime\Plugins\ProjectOverview\Controllers;

use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Log;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Core\Events\EventDispatcher;
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

        $action = $_POST['action'] ?? null;

        switch ($action) {
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
                $redirectUrl = $this->actionHandler->renameView($viewId, $viewName, $redirectUrl);
                break;
            case 'saveTabOrder':
                $this->actionHandler->saveTabOrder($_POST);
                break;
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
        // Handle shared view import
        if (isset($_GET['share']) && !empty($_GET['share'])) {
            $shareToken = $_GET['share'];
            $sharedView = $this->actionHandler->findViewByShareToken($shareToken);

            if ($sharedView) {
                $newViewId = $this->actionHandler->importSharedView($sharedView);
                $this->tpl->setNotification(__('projectOverview.notification.view_imported'), 'success');
                return Frontcontroller::redirect(BASE_URL . '/ProjectOverview/projectOverview?view=' . $newViewId);
            } else {
                $this->tpl->setNotification(__('projectOverview.notification.view_not_found'), 'error');
            }
        }

        // Check for flash notification
        if (session()->has('project_overview-flash_notification')) {
            $notification = session('project_overview-flash_notification');
            $this->tpl->setNotification($notification['message'], $notification['type']);
        }

        $userViewsData = $this->projectOverviewHelper->getProjectOverviewData();
        $this->tpl->assign('userViewsData', $userViewsData);
        return $this->tpl->display('ProjectOverview.projectOverview');
    }

    /**
     * Generate a share token for a view
     *
     * @return Response
     */
    public function generateShareLink(): Response
    {
        if (!AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        $viewId = $_POST['viewId'] ?? null;

        if (empty($viewId)) {
            return $this->tpl->displayJson(['error' => 'View ID required'], 400);
        }

        $shareToken = $this->actionHandler->generateShareToken($viewId);

        if ($shareToken === false) {
            return $this->tpl->displayJson(['error' => 'View not found'], 404);
        }

        $shareUrl = BASE_URL . '/ProjectOverview/projectOverview?share=' . $shareToken;

        return $this->tpl->displayJson([
            'success' => true,
            'shareUrl' => $shareUrl,
            'shareToken' => $shareToken
        ]);
    }
}

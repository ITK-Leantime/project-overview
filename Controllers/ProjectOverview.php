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

    private \Leantime\Plugins\ProjectOverview\Services\ProjectOverview $projectOverviewService;

    /**
     * @param Template                     $tpl
     * @param ProjectOverviewActionHandler $actionHandler
     * @param ProjectOverviewHelper        $projectOverviewHelper
     * @return void
     */
    public function init(Template $tpl, ProjectOverviewActionHandler $actionHandler, ProjectOverviewHelper $projectOverviewHelper, \Leantime\Plugins\ProjectOverview\Services\ProjectOverview $projectOverviewService): void
    {
        $this->tpl = $tpl;
        $this->actionHandler = $actionHandler;
        $this->projectOverviewHelper = $projectOverviewHelper;
        $this->projectOverviewService = $projectOverviewService;
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
        // Get filters data.
        $filtersData = $this->projectOverviewHelper->getProjectOverviewFiltersData($data);

        // Assign data to template.
        $this->tpl->assign('filtersData', $filtersData);

        // Display template.
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
        $redirectUrl = BASE_URL . '/ProjectOverview/ProjectOverview';

        $action = $_POST['action'] ?? null;

        switch ($action) {
            case 'saveView':
                $redirectUrl = $this->actionHandler->saveView($_POST, $redirectUrl);
                break;
            case 'deleteView':
                $viewId = $_POST['viewId'];
                $this->actionHandler->deleteView($viewId);
                break;
            case 'renameView':
                $viewId = $_POST['viewId'];
                $viewName = $_POST['viewName'];
                $redirectUrl = $this->actionHandler->renameView($viewId, $viewName, $redirectUrl);
                break;
            case 'saveTabOrder':
                $this->actionHandler->saveTabOrder($_POST);
                break;
        }

        return Frontcontroller::redirect($redirectUrl);
    }

    /**
     * Gathers users view data and feeds it to the template.
     *
     * @return Response
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function get(): Response
    {
        // Handle shared view import
        if (!empty($_GET['share'])) {
            $shareToken = $_GET['share'];
            $sharedView = $this->actionHandler->findViewByShareToken($shareToken);

            if ($sharedView) {
                $newViewId = $this->actionHandler->importSharedView($sharedView);
                $this->tpl->setNotification(__('projectOverview.notification.view_imported'), 'success');
                return Frontcontroller::redirect(BASE_URL . '/ProjectOverview/ProjectOverview?view=' . $newViewId);
            } else {
                $this->tpl->setNotification(__('projectOverview.notification.view_not_found'), 'error');
            }
        }

        // Check for flash notification and display it.
        if (session()->has('project_overview-flash_notification')) {
            $notification = session('project_overview-flash_notification');
            $this->tpl->setNotification($notification['message'], $notification['type']);
        }

        // Get user views data.
        $userViewsData = $this->projectOverviewHelper->getProjectOverviewData();

        // Get unique tags for the tag search field.
        $allTags = $this->projectOverviewService->getAllUniqueTags();

        // Assign data to template.
        $this->tpl->assign('userViewsData', $userViewsData);
        $this->tpl->assign('allTags', $allTags);

        // Display template.
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

        // Generate a share token for the view.
        $shareToken = $this->actionHandler->generateShareToken($viewId);

        if ($shareToken === false) {
            return $this->tpl->displayJson(['error' => 'View not found'], 404);
        }

        $shareUrl = BASE_URL . '/ProjectOverview/ProjectOverview?share=' . $shareToken;

        return $this->tpl->displayJson([
            'success' => true,
            'shareUrl' => $shareUrl,
            'shareToken' => $shareToken,
        ]);
    }
}

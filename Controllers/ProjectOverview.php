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
use Leantime\Plugins\ProjectOverview\Services\ProjectOverview as ProjectOverviewService;
use Leantime\Core\UI\Template;

/**
 * Class ProjectOverview
 */
class ProjectOverview extends Controller
{
    private ProjectOverviewActionHandler $actionHandler;
    private ProjectOverviewHelper $projectOverviewHelper;

    private ProjectOverviewService $projectOverviewService;
    private ProjectOverviewActionHandler $projectOverviewActionHandler;

    public const PARAM_VIEW = 'view';

    /**
     * @param Template                     $tpl
     * @param ProjectOverviewActionHandler $actionHandler
     * @param ProjectOverviewHelper        $projectOverviewHelper
     * @return void
     */
    public function init(Template $tpl, ProjectOverviewActionHandler $actionHandler, ProjectOverviewHelper $projectOverviewHelper, ProjectOverviewService $projectOverviewService, ProjectOverviewActionHandler $projectOverviewActionHandler): void
    {
        $this->tpl = $tpl;
        $this->actionHandler = $actionHandler;
        $this->projectOverviewHelper = $projectOverviewHelper;
        $this->projectOverviewService = $projectOverviewService;
        $this->projectOverviewActionHandler = $projectOverviewActionHandler;
    }

    /**
     * Loads filters data and serves it back to the template.
     *
     * @param array<string, string> $data
     *
     * @throws Exception
     * @return Response|null
     *
     * @noinspection PhpUnused Called via HTMX
     */
    public function loadFilters(array $data): ?Response
    {
        // Get filters data.
        $filtersData = $this->projectOverviewHelper->getProjectOverviewFiltersData($data);

        // Get user views data.
        $userViews = $this->projectOverviewActionHandler->getUserViewsObject();

        // Assign data to template.
        $this->tpl->assign('filtersData', $filtersData);
        $this->tpl->assign('userViews', $userViews);

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
                $viewId = $_POST[self::PARAM_VIEW];
                $this->actionHandler->deleteView($viewId);
                break;
            case 'renameView':
                $viewId = $_POST[self::PARAM_VIEW];
                $viewName = $_POST['viewName'];
                $redirectUrl = $this->actionHandler->renameView($viewId, $viewName, $redirectUrl);
                break;
            case 'saveTabOrder':
                $this->actionHandler->saveTabOrder($_POST);
                break;
            case 'saveSortOrder':
                $viewId = $_POST[self::PARAM_VIEW] ?? '';
                $sortBy = $_POST['sortBy'] ?? 'priority';
                $sortDirection = $_POST['sortDirection'] ?? 'ASC';
                $this->actionHandler->saveSortOrder($viewId, $sortBy, $sortDirection);
                break;
            case 'pinSubscription':
                $subscribeToken = $_POST['subscribeToken'] ?? '';
                $lookupResult = $this->actionHandler->findViewByShareToken($subscribeToken);
                if ($lookupResult) {
                    $newViewId = $this->actionHandler->subscribeToView($lookupResult);
                    session()->forget('project_overview.transient_subscription');
                    session()->flash('project_overview-flash_notification', [
                        'message' => __('projectOverview.notification.view_subscribed'),
                        'type' => 'success',
                    ]);
                    $redirectUrl .= '?' . http_build_query([self::PARAM_VIEW => $newViewId]);
                }
                break;
            case 'saveTransientAsCopy':
                $subscribeToken = $_POST['subscribeToken'] ?? '';
                $lookupResult = $this->actionHandler->findViewByShareToken($subscribeToken);
                if ($lookupResult) {
                    $newViewId = $this->actionHandler->saveViewAsCopy($lookupResult);
                    session()->forget('project_overview.transient_subscription');
                    $redirectUrl .= '?' . http_build_query([self::PARAM_VIEW => $newViewId]);
                }
                break;
        }

        return Frontcontroller::redirect($redirectUrl);
    }

    /**
     * HTMX endpoint: returns the table HTML for a single view using filter params from POST.
     *
     * @param array<string, string> $data Route params.
     * @return Response|null
     *
     * @noinspection PhpUnused Called via fetch from JS.
     */
    public function loadViewTable(array $data): ?Response
    {
        $tableData = $this->projectOverviewHelper->getViewTableData($_POST);

        $this->tpl->assign('userView', $tableData['userView']);
        $this->tpl->assign('statusLabels', $tableData['statusLabels']);
        $this->tpl->assign('allPriorities', $tableData['allPriorities']);

        return $this->tpl->displayPartial('projectoverview::partials.projectOverviewTable');
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
        // Handle live-share subscription preview (store in session, show as transient tab)
        if (!empty($_GET['subscribe'])) {
            $lookupResult = $this->actionHandler->findViewByShareToken($_GET['subscribe']);

            if ($lookupResult) {
                $tempViewId = 'transient_' . md5($_GET['subscribe']);
                session()->put('project_overview.transient_subscription', [
                    'token' => $_GET['subscribe'],
                    'ownerUserId' => $lookupResult->ownerUserId,
                    'ownerName' => $lookupResult->ownerName,
                    'ownerViewId' => $lookupResult->view->id,
                    'tempViewId' => $tempViewId,
                ]);
                return Frontcontroller::redirect(BASE_URL . '/ProjectOverview/ProjectOverview?' . http_build_query([self::PARAM_VIEW => $tempViewId]));
            } else {
                $this->tpl->setNotification(__('projectOverview.notification.view_not_found'), 'error');
            }
        }

        // Clean up transient subscription if user navigated away from it
        $transientSub = session('project_overview.transient_subscription');
        if ($transientSub) {
            $currentViewId = $_GET[self::PARAM_VIEW] ?? null;
            if ($currentViewId !== $transientSub['tempViewId']) {
                session()->forget('project_overview.transient_subscription');
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
        $this->tpl->assign('frontendDateFormat', projectOverviewService::FRONTEND_DATE_FORMAT);

        // Display template.
        return $this->tpl->display('ProjectOverview.projectOverview');
    }

    /**
     * Generate a share token for a view
     *
     * @return Response
     *
     * @noinspection PhpUnused Called via AJAX.
     */
    public function generateShareLink(): Response
    {
        if (!AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        $viewId = $_POST[self::PARAM_VIEW] ?? null;

        if (empty($viewId)) {
            return $this->tpl->displayJson(['error' => 'View ID required'], 400);
        }

        // Generate a share token for the view.
        $shareToken = $this->actionHandler->generateShareToken($viewId);

        if ($shareToken === false) {
            return $this->tpl->displayJson(['error' => 'View not found'], 404);
        }

        $shareUrl = BASE_URL . '/ProjectOverview/ProjectOverview?' . http_build_query(['share' => $shareToken]);

        return $this->tpl->displayJson([
            'success' => true,
            'shareUrl' => $shareUrl,
            'shareToken' => $shareToken,
        ]);
    }
}

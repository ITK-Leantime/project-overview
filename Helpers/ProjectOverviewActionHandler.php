<?php

namespace Leantime\Plugins\ProjectOverview\Helpers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Plugins\ProjectOverview\DTO\ViewDTO;
use Leantime\Plugins\ProjectOverview\DTO\UserViewDTO;
use Leantime\Domain\Users\Services\Users as UserService;
use Leantime\Domain\Users\Repositories\Users as UserRepository;
use Leantime\Plugins\ProjectOverview\Enum\DateTypeEnum;

/**
 * Class ProjectOverviewActionHandler
 */
readonly class ProjectOverviewActionHandler
{
    private const FILTER_PREFIX_PROJECT = 'project_';
    private const FILTER_PREFIX_PRIORITY = 'priority_';
    private const FILTER_PREFIX_STATUS = 'status_';
    private const FILTER_PREFIX_CUSTOM = 'custom_';
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * Initialize dependencies.
     * @return void
     */
    public function __construct(private UserService $userService, private UserRepository $userRepository)
    {
    }

    /**
     * Adjusts the period based on the provided POST data.
     *
     * @param array<string, mixed> $postData The POST data containing fromDate, toDate, and backward flag.
     * @return string The adjusted redirect URL.
     */
    public function adjustPeriod(array $postData, string $redirectUrl): string
    {
        $queryParams = [];

        if (isset($postData['showThisWeek'])) {
            $now = CarbonImmutable::now();
            $queryParams['fromDate'] = $now->startOfWeek()->format(self::DATE_FORMAT);
            $queryParams['toDate'] = $now->endOfWeek()->format(self::DATE_FORMAT);
        }

        if (isset($postData['fromDate']) && empty($postData['showThisWeek'])) {
            $queryParams['fromDate'] = $postData['fromDate'];
        }

        if (isset($postData['toDate']) && empty($postData['showThisWeek'])) {
            $queryParams['toDate'] = $postData['toDate'];
        }

        if (isset($postData['fromDate']) && isset($postData['toDate'])) {
            $fromDate = CarbonImmutable::createFromFormat('d-m-Y', $postData['fromDate']);
            $toDate = CarbonImmutable::createFromFormat('d-m-Y', $postData['toDate']);
            $interval = $fromDate->diffInDays($toDate) + 1;

            if (isset($postData['backward']) && $postData['backward'] == '1') {
                $fromDate = $fromDate->subDays($interval);
                $toDate = $toDate->subDays($interval);
            } elseif (isset($postData['forward']) && $postData['forward'] == '1') {
                $fromDate = $fromDate->addDays($interval);
                $toDate = $toDate->addDays($interval);
            }

            $queryParams['fromDate'] = $fromDate->format(self::DATE_FORMAT);
            $queryParams['toDate'] = $toDate->format(self::DATE_FORMAT);
        }

        if (isset($_GET['userIds']) && $_GET['userIds'] !== '') {
            $queryParams['userIds'] = implode(',', array_map('trim', explode(',', $_GET['userIds'])));
        }

        if (isset($_GET['searchTerm']) && $_GET['searchTerm'] !== '') {
            $queryParams['searchTerm'] = $_GET['searchTerm'];
        }

        if (!empty($queryParams)) {
            $redirectUrl .= '?' . http_build_query($queryParams);
        }

        return $redirectUrl;
    }

    /**
     * Saves a view.
     *
     * @param array<string, mixed> $postData    An associative array containing view data.
     * @param string               $redirectUrl The URL to redirect to after saving the view.
     *
     * @return string The updated redirect URL after the view has been saved or updated.
     * @throws BindingResolutionException
     */
    public function saveView(array $postData, string $redirectUrl): string
    {
        $overwriteView = (bool)($postData['overwriteView'] ?? false);
        $users = $postData['users'] ?? [];
        $dateType = DateTypeEnum::tryFrom($postData['dateType']);
        $fromDate = null;
        $toDate = null;
        if ($dateType === null) {
            $dateType = DateTypeEnum::NEXT_TWO_WEEKS;
        }
        if ($dateType === DateTypeEnum::CUSTOM) {
            $fromDate = $postData['fromDate'] ?? null;
            $toDate = $postData['toDate'] ?? null;
        }
        $columns = $postData['columns'] ?? [];
        $filters = $postData['filters'] ?? [];
        $groupedFilters = [
            'projects' => [],
            'priorities' => [],
            'statuses' => [],
            'custom' => [],
        ];

        foreach ($filters as $filter) {
            if (preg_match('/^([^_]+)_(.+)/', $filter, $matches)) {
                [, $group, $value] = $matches;
                // Map the filter prefix to the grouped filter key
                $filterMap = [
                    rtrim(self::FILTER_PREFIX_PROJECT, '_') => 'projects',
                    rtrim(self::FILTER_PREFIX_PRIORITY, '_') => 'priorities',
                    rtrim(self::FILTER_PREFIX_STATUS, '_') => 'statuses',
                    rtrim(self::FILTER_PREFIX_CUSTOM, '_') => 'custom',
                ];

                if (isset($filterMap[$group])) {
                    $groupedFilters[$filterMap[$group]][] = $value;
                }
            }
        }

        $viewDTO = new ViewDTO(
            title: null,
            users: (array)$users,
            dateType: $dateType,
            fromDate: $fromDate,
            toDate: $toDate,
            columns: $columns,
            projectFilters: $groupedFilters['projects'],
            priorityFilters: $groupedFilters['priorities'],
            statusFilters: $groupedFilters['statuses'],
            customFilters: $groupedFilters['custom']
        );

        $userViewsObject = $this->getUserViewsObject();
        $existingViewId = $postData['viewId'] ?? null;

        if (!empty($existingViewId) && $overwriteView && isset($userViewsObject[$existingViewId])) {
            // Update existing view, preserve share token
            $existingView = UserViewDTO::fromArray($userViewsObject[$existingViewId]);
            $userViewsObject[$existingViewId] = new UserViewDTO(
                id: $existingView->id,
                title: $existingView->title,
                view: $viewDTO,
                shareToken: $existingView->shareToken,
                createdAt: $existingView->createdAt
            );
            $redirectUrl .= '?view=' . $existingViewId;
            session()->flash('project_overview-flash_notification', [
                'message' => __('projectOverview.notification.view_updated'),
                'type' => 'success',
            ]);
        } else {
            // Create new view with unique ID
            $newViewId = uniqid('view_', true);
            $userViewsObject[$newViewId] = new UserViewDTO(
                id: $newViewId,
                title: 'View ' . (count($userViewsObject) + 1),
                view: $viewDTO,
                shareToken: null,
                createdAt: time()
            );
            $redirectUrl .= '?view=' . $newViewId;
            session()->flash('project_overview-flash_notification', [
                'message' => __('projectOverview.notification.view_created'),
                'type' => 'success',
            ]);
        }

        $this->saveUserViewsObject($userViewsObject);

        return $redirectUrl;
    }


    /**
     * Deletes a view.
     *
     * @param string $viewId The id of the view to be deleted.
     * @return void
     * @throws BindingResolutionException
     */
    public function deleteView(string $viewId): void
    {
        $userViewsObject = $this->getUserViewsObject();
        if (isset($userViewsObject[$viewId])) {
            unset($userViewsObject[$viewId]);
            $this->saveUserViewsObject($userViewsObject);
            session()->flash('project_overview-flash_notification', [
                'message' => __('projectOverview.notification.view_deleted'),
                'type' => 'success',
            ]);
        } else {
            session()->flash('project_overview-flash_notification', [
                'message' => __('projectOverview.notification.view_not_found'),
                'type' => 'error',
            ]);
        }
    }

    /**
     * Renames a view.
     *
     * @param string $viewId   Id of the view to be renamed.
     * @param string $viewName New name of the view.
     * @return string|false Returns the redirect URL if successful, false if the target name already exists
     * @throws BindingResolutionException
     */
    public function renameView(string $viewId, string $viewName, string $redirectUrl): string|false
    {
        $userViewsObject = $this->getUserViewsObject();

        if (isset($userViewsObject[$viewId])) {
            $existingView = UserViewDTO::fromArray($userViewsObject[$viewId]);

            // Update the view with the new title
            $userViewsObject[$viewId] = new UserViewDTO(
                id: $existingView->id,
                title: $viewName,
                view: $existingView->view,
                shareToken: $existingView->shareToken,
                createdAt: $existingView->createdAt
            );

            $this->saveUserViewsObject($userViewsObject);

            session()->flash('project_overview-flash_notification', [
                'message' => __('projectOverview.notification.view_renamed'),
                'type' => 'success',
            ]);
        } else {
            session()->flash('project_overview-flash_notification', [
                'message' => __('projectOverview.notification.view_not_found'),
                'type' => 'error',
            ]);
        }

        $redirectUrl .= '?view=' . $viewId;

        return $redirectUrl;
    }

    /**
     * Generate a share token for a view and enable sharing
     *
     * @param string $viewId The ID of the view to share
     * @return string|false The share token if successful, false if view not found
     */
    public function generateShareToken(string $viewId): string|false
    {
        $userViewsObject = $this->getUserViewsObject();

        if (!isset($userViewsObject[$viewId])) {
            return false;
        }

        $existingView = UserViewDTO::fromArray($userViewsObject[$viewId]);

        // Generate a unique share token if one doesn't exist
        $shareToken = $existingView->shareToken ?? bin2hex(random_bytes(16));

        // Update the view with the share token
        $userViewsObject[$viewId] = new UserViewDTO(
            id: $existingView->id,
            title: $existingView->title,
            view: $existingView->view,
            shareToken: $shareToken,
            createdAt: $existingView->createdAt
        );

        $this->saveUserViewsObject($userViewsObject);

        return $shareToken;
    }

    /**
     * Find a view by its share token across all users
     *
     * @param string $shareToken The share token to search for
     * @return UserViewDTO|null The view if found, null otherwise
     */
    public function findViewByShareToken(string $shareToken): ?UserViewDTO
    {
        // Get all users
        $allUsers = $this->userService->getAll();

        // Loop users, get views and try to find a match.
        foreach ($allUsers as $user) {
            $userViews = $this->getUserViewsObject($user['id']);

            foreach ($userViews as $viewData) {
                $view = UserViewDTO::fromArray($viewData);
                if ($view->shareToken === $shareToken) {
                    return $view;
                }
            }
        }

        return null;
    }

    /**
     * Import a shared view into the current user's views
     *
     * @param UserViewDTO $sharedView The shared view to import
     * @return string The new view ID
     */
    public function importSharedView(UserViewDTO $sharedView): string
    {
        $userViewsObject = $this->getUserViewsObject();

        // Create a new view with a unique ID (no share token for the copy)
        $newViewId = uniqid('view_', true);
        $userViewsObject[$newViewId] = new UserViewDTO(
            id: $newViewId,
            title: $sharedView->title . ' (Shared)',
            view: $sharedView->view,
            shareToken: null,
            createdAt: time()
        );

        $this->saveUserViewsObject($userViewsObject);

        return $newViewId;
    }

    /**
     * Encodes and saves the user-views object.
     *
     * @param array<string, mixed> $userViewsObject Array containing view objects to be saved.
     * @return void A base64 encoded JSON string representing the array of view objects.
     */
    private function saveUserViewsObject(array $userViewsObject): void
    {
        // Convert UserViewDTO objects to arrays
        $viewsArray = [];
        foreach ($userViewsObject as $key => $view) {
            if ($view instanceof UserViewDTO) {
                $viewsArray[$key] = $view->toArray();
            } else {
                $viewsArray[$key] = $view;
            }
        }

        // Json encode
        $json = json_encode($viewsArray);
        // Base64 encode
        $encodedViewObjects = base64_encode($json);

        // Save to user settings in user table
        $this->userService->updateUserSettings('projectoverview', 'view', $encodedViewObjects);
    }

    /**
     * Retrieves the user views object for a given user ID.
     *
     * @param ?string $userId The ID of the user whose views object is to be retrieved.
     * @return array<string, mixed> The user's views object as an associative array. Returns an empty array if no settings exist or decoding fails.
     */
    public function getUserViewsObject(?string $userId = null): array
    {
        if (!$userId) {
            $userId = session('userdata.id');
        }
        // Retrieve user settings from user table
        $userViewsEncoded = $this->userRepository->getUserSettings($userId, 'projectoverview.view');

        if (!$userViewsEncoded) {
            return [];
        }
        // base64 decode
        $json = base64_decode($userViewsEncoded, true);

        if ($json === false) {
            return [];
        }
        // Json decode
        return json_decode($json, true) ?? [];
    }

    /**
     * Retrieves the list of available columns.
     *
     * @return array<string> An array of column names that are available.
     */
    public function getAvailableColumns(): array
    {
        return [
            'headline',
            'project',
            'status',
            'priority',
            'dateToFinish',
            'editorLastname',
            'planHours',
            'hourRemaining',
            'sumHours',
            'milestoneid',
            'tags',
        ];
    }


    /**
     * Saves the updated tab order based on the given post data.
     *
     * @param array<string, mixed> $postData An associative array containing the new tab order data.
     * @return void
     */
    public function saveTabOrder(array $postData): void
    {
        try {
            $userViewsObject = $this->getUserViewsObject();
            $newOrder = $postData['order'] ?? [];

            if (empty($newOrder)) {
                exit(json_encode([
                    'status' => 'error',
                    'message' => __('projectOverview.notification.tab_order_empty_order'),
                ]));
            }

            if (empty($userViewsObject)) {
                exit(json_encode([
                    'status' => 'error',
                    'message' => __('projectOverview.notification.tab_order_no_views'),
                ]));
            }

            $reorderedUserViews = [];

            foreach ($newOrder as $viewKey) {
                if (isset($userViewsObject[$viewKey])) {
                    $reorderedUserViews[$viewKey] = $userViewsObject[$viewKey];
                }
            }

            foreach ($userViewsObject as $key => $view) {
                if (!isset($reorderedUserViews[$key])) {
                    $reorderedUserViews[$key] = $view;
                }
            }

            $this->saveUserViewsObject($reorderedUserViews);

            exit(json_encode([
                'status' => 'success',
                'message' => __('projectOverview.notification.tab_order_saved'),
            ]));
        } catch (\Exception $e) {
            exit(json_encode([
                'status' => 'error',
                'message' => __('projectOverview.notification.tab_order_error'),
                'debug' => $e->getMessage(),
            ]));
        }
    }
}

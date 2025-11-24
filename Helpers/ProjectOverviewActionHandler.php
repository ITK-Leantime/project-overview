<?php

namespace Leantime\Plugins\ProjectOverview\Helpers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Plugins\ProjectOverview\DTO\ViewDTO;
use Leantime\Domain\Users\Services\Users as UserService;
use Leantime\Domain\Users\Repositories\Users as UserRepository;
use Leantime\Plugins\ProjectOverview\Enum\DateTypeEnum;

/**
 * Class ProjectOverviewActionHandler
 */
readonly class ProjectOverviewActionHandler
{
    /**
     * Initialize dependencies.
     * @return void
     */
    public function __construct(private UserService $userService, private UserRepository $userRepository)
    {
    }

    /**
     * Saves or updates a view configuration.
     *
     * @param array $postData POST data containing view configuration
     * @param string $redirectUrl URL to redirect to after saving
     * @return string Updated redirect URL with viewId parameter
     */
    public function saveView(array $postData, string $redirectUrl): string
    {
        $overwriteView = (bool)($postData['overwriteView'] ?? false);
        $viewDTO = $this->createViewDTO($postData);

        $userViewsObject = $this->getUserViewsObject();
        $viewId = $postData['viewId'] ?? null;

        // Check if we are updating an existing view
        if (($viewId === '0' || !empty($viewId)) && $overwriteView) {
            $userViewsObject[$viewId] = $viewDTO;
            $message = 'projectOverview.notification.view_updated';
        } else {
            // Create new view
            $viewId = $this->generateUniqueViewId($userViewsObject);
            $userViewsObject[$viewId] = $viewDTO;
            $message = 'projectOverview.notification.view_created';
        }

        $this->saveUserViewsObject($userViewsObject);

        session()->flash('project_overview-flash_notification', [
            'message' => __($message),
            'type' => 'success',
        ]);

        return $redirectUrl . '?viewId=' . $viewId;
    }

    /**
     * Generates a unique view ID that doesn't conflict with existing views.
     *
     * @param array $userViewsObject Array of existing user views
     * @return string Generated unique view ID
     */
    private function generateUniqueViewId(array $userViewsObject): string
    {
        $baseViewName = 'view_';
        $counter = count($userViewsObject);
        $viewName = $baseViewName . $counter;

        while (isset($userViewsObject[$viewName])) {
            $counter++;
            $viewName = $baseViewName . $counter;
        }

        return $viewName;
    }

    /**
     * Creates a ViewDTO object from POST data.
     *
     * @param array $postData Array containing view configuration parameters
     * @return ViewDTO Data transfer object containing view configuration
     */
    private function createViewDTO(array $postData): ViewDTO
    {
        $users = $postData['users'] ?? [];
        $dateType = DateTypeEnum::tryFrom($postData['dateType'] ?? null);
        $fromDate = null;
        $toDate = null;

        if ($dateType === null) {
            $dateType = DateTypeEnum::NEXT_TWO_WEEKS;
        }

        if ($dateType === DateTypeEnum::CUSTOM && !empty($postData['dateRange'])) {
            list($fromDate, $toDate) = explode(' til ', $postData['dateRange']);
        }

        $columns = $postData['columns'] ?? [];
        $filters = $this->parseFilters($postData['filters'] ?? []);

        return new ViewDTO(
            title: null,
            users: (array)$users,
            dateType: $dateType,
            fromDate: $fromDate,
            toDate: $toDate,
            columns: $columns,
            projectFilters: $filters['projects'],
            priorityFilters: $filters['priorities'],
            statusFilters: $filters['statuses'],
            customFilters: $filters['custom']
        );
    }

    /**
     * Parses input filters array and groups them into categories based on their prefixes.
     *
     * @param array<string> $filters Input array of filters with prefixes (e.g. 'project_', 'priority_', etc.)
     * @return array{projects: string[], priorities: string[], statuses: string[], custom: string[]} Grouped filters by category
     */
    private function parseFilters(array $filters): array
    {
        $groupedFilters = [
            'projects' => [],
            'priorities' => [],
            'statuses' => [],
            'custom' => [],
        ];

        foreach ($filters as $filter) {
            if (str_starts_with($filter, 'project_')) {
                $groupedFilters['projects'][] = substr($filter, 8);
            } elseif (str_starts_with($filter, 'priority_')) {
                $groupedFilters['priorities'][] = substr($filter, 9);
            } elseif (str_starts_with($filter, 'status_')) {
                $groupedFilters['statuses'][] = substr($filter, 7);
            } elseif (str_starts_with($filter, 'custom_')) {
                $groupedFilters['custom'][] = substr($filter, 7);
            }
        }

        return $groupedFilters;
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
        if (!isset($userViewsObject[$viewId])) {
            session()->flash('project_overview-flash_notification', [
                'message' => __('projectOverview.notification.view_not_found'),
                'type' => 'error',
            ]);
            return;
        }
        unset($userViewsObject[$viewId]);
        $this->saveUserViewsObject($userViewsObject);

        session()->flash('project_overview-flash_notification', [
            'message' => __('projectOverview.notification.view_deleted'),
            'type' => 'success',
            ]);
    }

    /**
     * Renames a view.
     *
     * @param string $viewId   Id of the view to be renamed.
     * @param string $viewName New name of the view.
     * @return string Returns the redirect URL.
     * @throws BindingResolutionException
     */
    public function renameView(string $viewId, string $viewName, string $redirectUrl): string
    {
        $userViewsObject = $this->getUserViewsObject();
        $viewName = str_replace(' ', '_', $viewName);

        if (!isset($userViewsObject[$viewId])) {
            session()->flash('project_overview-flash_notification', [
                'message' => __('projectOverview.notification.view_not_found'),
                'type' => 'error',
            ]);
            return $redirectUrl;
        }

        if ($viewName !== $viewId && isset($userViewsObject[$viewName])) {
            session()->flash('project_overview-flash_notification', [
                'message' => __('projectOverview.notification.view_name_already_exists'),
                'type' => 'error',
            ]);
            return $redirectUrl;
        }

        $keys = array_keys($userViewsObject);
        $index = array_search($viewId, $keys);

        if ($index !== false) {
            $keys[$index] = $viewName;
            // Combine the modified keys with the original values to keep order
            $userViewsObject = array_combine($keys, array_values($userViewsObject));

            $this->saveUserViewsObject($userViewsObject);

            session()->flash('project_overview-flash_notification', [
                'message' => __('projectOverview.notification.view_renamed'),
                'type' => 'success',
            ]);
        }

        return $redirectUrl . '?viewId=' . $viewName;
    }

    /**
     * Encodes and saves the user-views object.
     *
     * @param array<string, mixed> $userViewsObject Array containing view objects to be saved.
     * @return void A base64 encoded JSON string representing the array of view objects.
     * @throws \JsonException
     */
    private function saveUserViewsObject(array $userViewsObject): void
    {
        //
        $encodedViewObjects = base64_encode(json_encode($userViewsObject, JSON_THROW_ON_ERROR));

        // Save to user settings in user table
        $this->userService->updateUserSettings('projectoverview', 'view', $encodedViewObjects);
    }

    /**
     * Retrieves and decodes the users-views object.
     *
     * @return array<string, mixed> Decoded array representing user views. Returns empty array on any failure.
     */
    public function getUserViewsObject(): array
    {
        // Retrieve user settings from user table
        $userViewsEncoded = $this->userRepository->getUserSettings(session('userdata.id'), 'projectoverview.view');

        if (empty($userViewsEncoded) || !is_string($userViewsEncoded)) {
            return [];
        }

        $json = base64_decode($userViewsEncoded, true);

        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);

        // Ensure we actually got an array back, not a scalar or null
        return is_array($data) ? $data : [];
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
     * Saves the order of tabs/views for a user.
     *
     * @param array<string, mixed> $postData Array containing the new order of tabs in 'order' key
     * @return void
     * @throws \Exception
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

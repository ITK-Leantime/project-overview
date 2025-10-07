<?php

namespace Leantime\Plugins\ProjectOverview\Helpers;

use Carbon\CarbonImmutable;
use Leantime\Plugins\ProjectOverview\DTO\ViewDTO;
use Leantime\Domain\Users\Services\Users as UserService;
use Leantime\Domain\Users\Repositories\Users as UserRepository;

/**
 *
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
            $queryParams['fromDate'] = $now->startOfWeek()->format('Y-m-d');
            $queryParams['toDate'] = $now->endOfWeek()->format('Y-m-d');
        } elseif (isset($postData['dateRange'])) {
            list($postData['fromDate'], $postData['toDate']) = explode(' til ', $postData['dateRange']);
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

            $queryParams['fromDate'] = $fromDate->format('Y-m-d');
            $queryParams['toDate'] = $toDate->format('Y-m-d');
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

    public function SaveView(array $postData, string $redirectUrl): string
    {
        $overwriteView = (bool)($postData['overwriteView'] ?? false);
        $users = $postData['users'] ?? [];
        list($postData['fromDate'], $postData['toDate']) = explode(' til ', $postData['dateRange']);
        $columns = $postData['columns'] ?? [];
        $filters = $postData['filters'] ?? [];
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
        $viewDTO = new ViewDTO(
            title: null,
            users: (array)$users,
            fromDate: $postData['fromDate'],
            toDate: $postData['toDate'],
            columns: $columns,
            projectFilters: $groupedFilters['projects'],
            priorityFilters: $groupedFilters['priorities'],
            statusFilters: $groupedFilters['statuses'],
            customFilters: $groupedFilters['custom']
        );
        $userViewsObject = $this->getUserViewsObject();

        if ($overwriteView) {
            $userViewsObject[$postData['viewId']] = $viewDTO;
            $redirectUrl .= '?viewId=' . $postData['viewId'];
        } else {
            $userViewsObject[] = $viewDTO;
        }
        $this->saveUserViewsObject($userViewsObject);

        return $redirectUrl;
    }




    public function deleteView(array $postData, string $redirectUrl): string
    {
        $viewId = $postData['viewId'];
        $userViewsObject = $this->getUserViewsObject();
        if (isset($userViewsObject[$viewId])) {
            unset($userViewsObject[$viewId]);
            $this->saveUserViewsObject($userViewsObject);
        }
        return $redirectUrl;
    }



    public function renameView(array $postData, string $redirectUrl): string
    {
        $viewId = $postData['viewId'];
        $viewName = str_replace(' ', '_', $postData['viewName']);
        $userViewsObject = $this->getUserViewsObject();
        if (isset($userViewsObject[$viewId])) {
            $userViewsObject[$viewName] = $userViewsObject[$viewId];
            unset($userViewsObject[$viewId]);

            $encodedViewObjects = $this->encodeViewSettings($userViewsObject);
            $this->userService->updateUserSettings('projectoverview', 'view', $encodedViewObjects);
        }
        return $redirectUrl;
    }

    private function saveUserViewsObject(array $arrayOfViewObjects): string
    {
        // Turn array into JSON
        $json = json_encode($arrayOfViewObjects);

        // Encode to base64 so htmlspecialchars() won't break it
        return base64_encode($json);
    }
    public function getUserViewsObject()
    {
        $userViewsEncoded = $this->userRepository->getUserSettings(session('userdata.id'), 'projectoverview.view');

        if (!$userViewsEncoded) {
            return null;
        }

        $json = base64_decode($userViewsEncoded, true);

        if ($json === false) {
            return null;
        }

        return json_decode($json, true) ?? null;
    }

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
}

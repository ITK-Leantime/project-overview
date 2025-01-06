<?php

namespace Leantime\Plugins\ProjectOverview\Helpers;

use Carbon\CarbonImmutable;

/**
 *
 */
class ProjectOverviewActionHandler
{
    /**
     * Initialize dependencies.
     * @return void
     */
    public function __construct()
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
}

<?php

namespace Leantime\Plugins\ProjectOverview\DTO;

use Leantime\Plugins\ProjectOverview\Enum\DateTypeEnum;

/**
 * Data Transfer Object for a user's view with metadata
 */
readonly class UserViewDTO
{
    /**
     * @param string      $id                   Unique identifier for the view
     * @param string      $title                User-friendly title of the view
     * @param ViewDTO     $view                 The view configuration
     * @param string|null $shareToken            Token for sharing this view (optional)
     * @param int|null    $createdAt             Unix timestamp when view was created
     * @param int         $order                 Display order of the view (lower numbers appear first)
     * @param string|null $subscribedToUserId    The view owner's user ID (for live-share subscriptions)
     * @param string|null $subscribedToViewId    The owner's view ID (for live-share subscriptions)
     * @param string|null $subscribedFromName    Owner's display name (avoids extra lookups for tab rendering)
     */
    public function __construct(
        public string $id,
        public string $title,
        public ViewDTO $view,
        public ?string $shareToken = null,
        public ?int $createdAt = null,
        public int $order = 0,
        public ?string $subscribedToUserId = null,
        public ?string $subscribedToViewId = null,
        public ?string $subscribedFromName = null,
    ) {
    }

    /**
     * Check if this view is a live-share subscription.
     *
     * @return bool
     */
    public function isSubscription(): bool
    {
        return $this->subscribedToUserId !== null;
    }

    /**
     * Convert to array for storage
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'view' => [
                'title' => $this->view->title,
                'users' => $this->view->users,
                'dateType' => $this->view->dateType->value,
                'fromDate' => $this->view->fromDate,
                'toDate' => $this->view->toDate,
                'columns' => $this->view->columns,
                'projectFilters' => $this->view->projectFilters,
                'priorityFilters' => $this->view->priorityFilters,
                'statusFilters' => $this->view->statusFilters,
                'customFilters' => $this->view->customFilters,
            ],
            'shareToken' => $this->shareToken,
            'createdAt' => $this->createdAt,
            'order' => $this->order,
            'subscribedToUserId' => $this->subscribedToUserId,
            'subscribedToViewId' => $this->subscribedToViewId,
            'subscribedFromName' => $this->subscribedFromName,
        ];
    }

    /**
     * Create from array (for loading from storage)
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $viewData = $data['view'] ?? $data; // Backward compatibility

        // Handle dateType - convert string to enum if needed
        $dateType = $viewData['dateType'] ?? null;
        if (is_string($dateType)) {
            $dateType = DateTypeEnum::tryFrom($dateType);
        }
        if (!$dateType instanceof DateTypeEnum) {
            $dateType = DateTypeEnum::NEXT_TWO_WEEKS;
        }

        return new self(
            id: $data['id'] ?? uniqid('view_', true),
            title: $data['title'] ?? 'Untitled View',
            view: new ViewDTO(
                title: $viewData['title'] ?? null,
                users: $viewData['users'] ?? [],
                dateType: $dateType,
                fromDate: $viewData['fromDate'] ?? null,
                toDate: $viewData['toDate'] ?? null,
                columns: $viewData['columns'] ?? [],
                projectFilters: $viewData['projectFilters'] ?? [],
                priorityFilters: $viewData['priorityFilters'] ?? [],
                statusFilters: $viewData['statusFilters'] ?? [],
                customFilters: $viewData['customFilters'] ?? []
            ),
            shareToken: $data['shareToken'] ?? null,
            createdAt: $data['createdAt'] ?? null,
            order: $data['order'] ?? 0,
            subscribedToUserId: $data['subscribedToUserId'] ?? null,
            subscribedToViewId: $data['subscribedToViewId'] ?? null,
            subscribedFromName: $data['subscribedFromName'] ?? null,
        );
    }
}

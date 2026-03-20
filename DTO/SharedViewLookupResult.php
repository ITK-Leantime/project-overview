<?php

namespace Leantime\Plugins\ProjectOverview\DTO;

/**
 * Result of looking up a shared view by token, including owner context.
 */
readonly class SharedViewLookupResult
{
    /**
     * @param UserViewDTO $view        The matched view configuration
     * @param string      $ownerUserId The user ID of the view owner
     * @param string      $ownerName   The display name of the view owner
     */
    public function __construct(
        public UserViewDTO $view,
        public string $ownerUserId,
        public string $ownerName,
    ) {
    }
}

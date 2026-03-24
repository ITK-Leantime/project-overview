@use(Leantime\Plugins\ProjectOverview\Enum\DateTypeEnum)
<form method="POST" id="filtersForm" {{ $filtersData->isSubscription ? 'data-is-subscription=true' : '' }}>
    <input type="hidden" name="action" value="saveView" />
    <div>
        <select name="users[]" id="userSelect" multiple {{ $filtersData->isSubscription ? 'disabled' : '' }}>
            @foreach ($filtersData->allUsers as $user)
                <option value="{{ $user['id'] }}"
                    {{ in_array($user['id'], $filtersData->users ?? []) ? 'selected' : '' }}>
                    {{ $user['firstname'] }} {{ $user['lastname'] }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="date-options">
        <select name="dateType" id="dateOptions" {{ $filtersData->isSubscription ? 'disabled' : '' }}>
            <option value="{{ DateTypeEnum::THIS_WEEK->value }}"
                {{ $filtersData->dateType === DateTypeEnum::THIS_WEEK->value ? 'selected' : '' }}
                data-start-date="{{ $filtersData->dateRanges[DateTypeEnum::THIS_WEEK->value]['start'] ?? '' }}"
                data-end-date="{{ $filtersData->dateRanges[DateTypeEnum::THIS_WEEK->value]['end'] ?? '' }}">
                {{ __('projectOverview.this_week') }}</option>
            <option value="{{ DateTypeEnum::NEXT_TWO_WEEKS->value }}"
                {{ $filtersData->dateType === DateTypeEnum::NEXT_TWO_WEEKS->value ? 'selected' : '' }}
                data-start-date="{{ $filtersData->dateRanges[DateTypeEnum::NEXT_TWO_WEEKS->value]['start'] ?? '' }}"
                data-end-date="{{ $filtersData->dateRanges[DateTypeEnum::NEXT_TWO_WEEKS->value]['end'] ?? '' }}">
                {{ __('projectOverview.next_two_weeks') }}</option>
            <option value="{{ DateTypeEnum::NEXT_THREE_WEEKS->value }}"
                {{ $filtersData->dateType === DateTypeEnum::NEXT_THREE_WEEKS->value ? 'selected' : '' }}
                data-start-date="{{ $filtersData->dateRanges[DateTypeEnum::NEXT_THREE_WEEKS->value]['start'] ?? '' }}"
                data-end-date="{{ $filtersData->dateRanges[DateTypeEnum::NEXT_THREE_WEEKS->value]['end'] ?? '' }}">
                {{ __('projectOverview.next_three_weeks') }}</option>
            <option value="{{ DateTypeEnum::CUSTOM->value }}"
                {{ $filtersData->dateType === DateTypeEnum::CUSTOM->value ? 'selected' : '' }}>
                Custom
            </option>
        </select>
    </div>

    <div class="date-range-filter">
        <input type="text" name="dateRange" id="dateRange"
            value="{{ $filtersData->fromDate }} til {{ $filtersData->toDate }}" readonly>
        <input type="hidden" name="fromDate" id="fromDate" value="{{ $filtersData->fromDate }}">
        <input type="hidden" name="toDate" id="toDate" value="{{ $filtersData->toDate }}">
    </div>

    <div class="filters">
        <select name="filters[]" id="filterSelect" multiple {{ $filtersData->isSubscription ? 'disabled' : '' }}>
            <optgroup label="Projects">
                @foreach ($filtersData->allProjects as $project)
                    <option value="project_{{ $project['id'] }}"
                        {{ in_array($project['id'], $filtersData->projectFilters ?? []) ? 'selected' : '' }}>
                        {{ $project['name'] }}
                    </option>
                @endforeach
            </optgroup>

            <optgroup label="Priority">
                @foreach ($filtersData->allPriorities as $id => $name)
                    <option value="priority_{{ $id }}"
                        {{ in_array($id, $filtersData->priorityFilters ?? []) ? 'selected' : '' }}>
                        {{ $name }}
                    </option>
                @endforeach
            </optgroup>

            <optgroup label="Status">
                @foreach ($filtersData->allStatusLabels as $id => $status)
                    <option value="status_{{ $id }}"
                        {{ in_array($id, $filtersData->statusFilters ?? []) ? 'selected' : '' }}>
                        {{ $status['name'] }}
                    </option>
                @endforeach
            </optgroup>

            <optgroup label="Custom filters">
                @foreach (['empty-due-date', 'overdue-tickets'] as $filter)
                    <option value="custom_{{ $filter }}"
                        {{ in_array($filter, $filtersData->customFilters ?? []) ? 'selected' : '' }}>
                        {{ __('projectOverview.' . $filter) }}
                    </option>
                @endforeach
            </optgroup>
        </select>
    </div>


    <div class="columns-display">
        <select name="columns[]" id="columnSelect" multiple {{ $filtersData->isSubscription ? 'disabled' : '' }}>
            @foreach ($filtersData->allColumns ?? [] as $column)
                <option value="{{ $column }}"
                    {{ empty($filtersData->selectedColumns) || in_array($column, $filtersData->selectedColumns) ? 'selected' : '' }}>
                    {{ __('projectOverview.' . strtolower($column) . '_table_header') }}</option>
            @endforeach
        </select>
    </div>

    <div class="save-view">
        @if ($filtersData->isTransientSubscription)
            <input type="hidden" name="subscribeToken" value="{{ $filtersData->subscribeToken }}" />
            <button type="submit" name="action" value="pinSubscription"
                class="btn btn-success save-as-new-btn">{{ __('projectOverview.pin_to_my_views') }}</button>
        @else
            @if (!empty($userViews) && !$filtersData->isSubscription)
                <button type="submit" name="overwriteView" value="1"
                    onclick="return confirm('{{ __('projectOverview.save_view_confirm') }}')"
                    class="btn btn-default save-view-btn">{{ __('projectOverview.save_view') }}</button>
            @endif
            <button type="submit"
                class="btn btn-success save-as-new-btn">{{ __('projectOverview.save_as_new_view') }}</button>
            <input type="hidden" name="view" value="{{ $filtersData->selectedViewId }}" />
            @if (!empty($userViews) && !$filtersData->isSubscription)
                <button type="button" class="copy-live-share-button"
                    data-original="{{ __('projectOverview.share_view') }}" name="copyLiveShare">
                    {{ __('projectOverview.share_view') }}
                </button>
            @endif
        @endif
    </div>
</form>

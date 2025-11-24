@use(Leantime\Plugins\ProjectOverview\Enum\DateTypeEnum)
<form method="POST">
    <input type="hidden" name="action" value="saveView" />
    <div>
        <select name="users[]" id="userSelect" multiple>
            @foreach ($filtersData->allUsers as $user)
                <option value="{{ $user['id'] }}"
                    {{ in_array($user['id'], $filtersData->users ?? []) ? 'selected' : '' }}>
                    {{ $user['firstname'] }} {{ $user['lastname'] }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="date-options">
        <select name="dateType" id="dateOptions">
            <option value="{{ DateTypeEnum::THIS_WEEK->value }}"
                {{ $filtersData->dateType === DateTypeEnum::THIS_WEEK->value ? 'selected' : '' }}>
                {{ __('projectOverview.this_week') }}</option>
            <option value="{{ DateTypeEnum::NEXT_TWO_WEEKS->value }}"
                {{ $filtersData->dateType === DateTypeEnum::NEXT_TWO_WEEKS->value ? 'selected' : '' }}>
                {{ __('projectOverview.next_two_weeks') }}</option>
            <option value="{{ DateTypeEnum::NEXT_THREE_WEEKS->value }}"
                {{ $filtersData->dateType === DateTypeEnum::NEXT_THREE_WEEKS->value ? 'selected' : '' }}>
                {{ __('projectOverview.next_three_weeks') }}</option>
            <option value="{{ DateTypeEnum::CUSTOM->value }}"
                {{ $filtersData->dateType === DateTypeEnum::CUSTOM->value ? 'selected' : '' }}>
                Custom
            </option>
        </select>
    </div>

    <div class="date-range-filter">
        <input type="text" name="dateRange" id="dateRange"
            value="{{ $filtersData->fromDate }} til {{ $filtersData->toDate }}">
    </div>

    <div class="filters">
        <select name="filters[]" id="filterSelect" multiple>
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
        <select name="columns[]" id="columnSelect" multiple>
            @foreach ($filtersData->allColumns ?? [] as $column)
                <option value="{{ $column }}"
                    {{ empty($filtersData->selectedColumns) || in_array($column, $filtersData->selectedColumns) ? 'selected' : '' }}>
                    {{ __('projectOverview.' . strtolower($column) . '_table_header') }}</option>
            @endforeach
        </select>
    </div>

    <div class="save-view">
        <button type="submit" class="btn btn-default">+ {{ __('projectOverview.save_view') }}</button>
        <input type="hidden" name="viewId" value="{{ $filtersData->selectedViewId }}" />
        <button type="submit" name="overwriteView" value="1"
            class="btn btn-default">{{ __('projectOverview.overwrite_view') }}</button>
    </div>
</form>

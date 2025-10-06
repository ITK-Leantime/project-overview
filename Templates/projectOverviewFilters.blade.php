<form method="POST">
    <input type="hidden" name="action" value="SaveView" />
    <div>
        <select name="users[]" id="userSelect" multiple>
            @foreach ($allUsers as $user)
                <option value="{{ $user['id'] }}"
                    {{ in_array($user['id'], $users ?? []) ? 'selected' : '' }}>
                    {{ $user['firstname'] }} {{ $user['lastname'] }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="date-range-filter">
        <input type="text" name="dateRange" id="dateRange"
               value="{{$fromDate }} til {{ $toDate}}">
    </div>

    <div class="filters">
        <select name="filters[]" id="filterSelect" multiple>
            <optgroup label="Projects">
                @foreach ($allProjects as $project)
                    <option value="project_{{ $project['id'] }}"
                        {{ in_array($project['id'], $projectFilters ?? []) ? 'selected' : '' }}>
                        {{ $project['name'] }}
                    </option>
                @endforeach
            </optgroup>

            <optgroup label="Priority">
                @foreach ($allPriorities as $id => $name)
                    <option value="priority_{{ $id }}"
                        {{ in_array($id, $priorityFilters ?? []) ? 'selected' : '' }}>
                        {{ $name }}
                    </option>
                @endforeach
            </optgroup>

            <optgroup label="Status">
                @foreach ($allStatusLabels as $id => $status)
                    <option value="status_{{ $id }}"
                        {{ in_array($id, $statusFilters ?? []) ? 'selected' : '' }}>
                        {{ $status['name'] }}
                    </option>
                @endforeach
            </optgroup>

            <optgroup label="Custom filters">
                @foreach (['empty-due-date', 'overdue-tickets'] as $filter)
                    <option value="custom_{{ $filter }}"
                        {{ in_array($filter, $customFilters ?? []) ? 'selected' : '' }}>
                        {{ __('projectOverview.' . $filter) }}
                    </option>
                @endforeach
            </optgroup>
        </select>
    </div>

    <div class="columns-display">
        <select name="columns[]" id="columnSelect" multiple>
            @foreach ($allColumns ?? [] as $column)
                <option
                    value="{{ $column }}" {{ in_array($column, $selectedColumns ?? []) ? 'selected' : '' }}>{{ __('projectOverview.' . strtolower($column) . '_table_header') }}</option>
            @endforeach
        </select>
    </div>

    <div class="save-view">
        <button type="submit" class="btn btn-default">+ Save View</button>
        <input type="hidden" name="viewId" value="{{ $selectedViewId }}"/>
        <button type="submit" name="overrideView" value="1" class="btn btn-default">Override view</button>
    </div>
</form>

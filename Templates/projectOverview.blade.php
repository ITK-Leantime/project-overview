@extends($layout)
@section('content')
    <div class="pageheader">
        <div class="pageicon"><span class="fa fa-cogs"></span></div>
        <div class="pagetitle">
            <h5>{{ __("label.table-columns") }}</h5>
            <h1>{{ __("projectOverview.dashboard_title") }}</h1>
        </div>
    </div>
    <div class="project-overview-container">
        <div class="search-and-filter">
            <form method="POST">
                <input type="hidden" name="action" value="adjustPeriod">

                <div>
                    <label>{{ __('projectOverview.filter_user_label') }}</label>

                    <select class="form-select project-overview-assignee-select" id="user-filter" multiple="multiple">
                        @foreach ($allUsers as $user)
                            <option
                                value={{ $user['id'] }} {{ ($selectedFilterUser !== null && in_array($user['id'], $selectedFilterUser)) ? 'selected' : '' }}>
                                {{ $user['firstname'] }}
                                {{ $user['lastname'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="date-range-filter">
                    <label>{{ __('projectOverview.date_label') }}</label>
                    <input type="text" name="dateRange" id="dateRange"
                           value="{{ $fromDate->format('d-m-Y') }} til {{ $toDate->format('d-m-Y') }}">
                </div>
                <div class="employee-and-search-filter">

                    <div class="margin-left">
                        <div class="input-group">
                            <i class="fa fa-search"></i>
                            <div class="input-group-prepend"></div>
                            <label>{{ __('projectOverview.search_label') }}</label>
                            <input value="{!! $currentSearchTerm !!}" type="text" class="form-control"
                                   placeholder="{{ __('projectOverview.empty_search_label') }}" id="search-term"
                                   aria-describedby="basic-addon1">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <table class="table table-striped">
            <thead>
            <tr>
                <th scope="col">{{ __('projectOverview.id_table_header') }}</th>
                <th scope="col">{{ __('projectOverview.project_table_header') }}</th>
                <th scope="col">{{ __('projectOverview.todo_table_header') }}</th>
                <th scope="col">{{ __('projectOverview.status_table_header') }}</th>
                <th scope="col">{{ __('projectOverview.priority_table_header') }}</th>
                <th scope="col">{{ __('projectOverview.due_date_table_header') }}</th>
                <th scope="col">{{ __('projectOverview.todo_assigned_table_header') }}</th>
                <th scope="col">{{ __('projectOverview.todo_planned_hours_table_header') }}</th>
                <th scope="col">{{ __('projectOverview.todo_remaining_hours_table_header') }}</th>
                <th scope="col">{{ __('projectOverview.todo_milestone_table_header') }}</th>
                <th scope="col">{{ __('projectOverview.todo_tags_table_header') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($allTickets as $key => $row)
                <tr>
                    <th scope="row">{{ $row['id'] }}</th>
                    <th scope="row">{{ $row['projectName'] }}</th>
                    <td>
                        <a href="#/tickets/showTicket/{{ $row['id'] }}">
                            {{ $row['headline'] }}
                        </a>
                        @if (isset($row['dependingTicketId']) && $row['dependingTicketId'] > 0)
                            (
                            <a href="#/tickets/showTicket/{{ $row['dependingTicketId'] }}">{{ $row['parentHeadline'] }}</a>
                            )
                        @endif
                    </td>
                    <td>
                        <div class="btn-group">
                            <button type="button" id="status-ticket-{{ $row['id'] }}"
                                    class="table-button {!! $statusLabels[$row['projectId']][$row['status']]['class'] ?? '' !!}"
                                    data-toggle="dropdown">
                                <span
                                    id="status-label">{!! $statusLabels[$row['projectId']][$row['status']]['name'] !!} </span>
                                <i class="fa fa-caret-down"></i>
                            </button>
                            <div class="dropdown-menu" id="status-dropdown-menu">
                                @foreach ($statusLabels[$row['projectId']] as $newStatusId => $label)
                                    <li class="dropdown-item">
                                        <button class="table-button status {!! $label['class'] !!}"
                                                data-args="{{ $row['id'] }},{{ $newStatusId }},{{ $label['class'] }},{{ $label['name'] }}">
                                            {{ $label['name'] }}
                                        </button>
                                    </li>
                                @endforeach
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="btn-group">
                            <button type="button" id="priority-ticket-{{ $row['id'] }}"
                                    class="table-button priority-bg-{!! $row['priority'] !!}"
                                    data-toggle="dropdown">
                                @if (is_numeric($row['priority']) && isset($priorities[$row['priority']]))
                                    <span id="priority-label">{!! $priorities[$row['priority']] !!}</span>
                                    <i class="fa fa-caret-down"></i>
                                @endif
                                @if (!is_numeric($row['priority']))
                                    <span id="priority-label">{{ __('projectOverview.no_priority_label') }} </span>
                                    <i class="fa fa-caret-down"></i>
                                @endif
                            </button>
                            <div class="dropdown-menu">
                                @foreach ($priorities as $newPriorityId => $priorityLabel)
                                    <li class="dropdown-item">
                                        <button type="button"
                                                data-args="{{ $row['id'] }},{{ $newPriorityId }},{{ $priorityLabel }}"
                                                class="table-button priority priority-bg-{!! $newPriorityId !!}">
                                            <div> {{ $priorityLabel }}</div>
                                        </button>
                                    </li>
                                @endforeach
                            </div>
                        </div>
                    </td>
                    <td class="specific">
                        <input type="date" data-ticketid="{{ $row['id'] }}" id="due-date-{{ $row['id'] }}"
                               value="{{ date($row['dueDate']) }}"/>
                    </td>
                    <td class="spacious">
                        <select class="form-select" id="assigned-user-{{ $row['id'] }}">
                            <option value="-1"></option>
                            @foreach ($row['projectUsers'] as $projectUser)
                                <option value={{ $projectUser['id'] }}
                                    {{ (int) $row['editorId'] === (int) $projectUser['id'] ? 'selected' : '' }}>
                                    {{ $projectUser['firstname'] }}
                                    {{ $projectUser['lastname'] }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td class="confined">
                        <div class="input-group input-group-sm mb-3">
                            <input type="number" id="plan-hours-{{ $row['id'] }}" class="form-control"
                                   value="{{ $row['planHours'] }}">
                        </div>
                    </td>
                    <td class="confined">
                        <div class="input-group input-group-sm mb-3">
                            <input type="number" id="remaining-hours-{{ $row['id'] }}" class="form-control"
                                   value="{{ $row['hourRemaining'] }}">
                        </div>
                    </td>
                    <td>
                        @if (count($row['projectMilestones']) > 0)
                            <select id="milestone-select-{{ $row['id'] }}"
                                    class="form-select"

                            >
                                <option value="-1"></option>
                                @foreach ($row['projectMilestones'] as $projectMilestone)
                                    <option value={{ $projectMilestone['id'] }}
                                        id="milestone-option-{{ $projectMilestone['id'] }}"
                                        {{ (int) $row['milestoneid'] === (int) $projectMilestone['id'] ? 'selected' : '' }}>
                                        {{ $projectMilestone['headline'] }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        @if (count($row['projectMilestones']) === 0)
                            {{ __('projectOverview.no_project_milestones') }}
                        @endif
                    </td>
                    <td class="spacious">
                        <div class="input-group input-group-sm mb-3">
                            <input type="text" id="tags-{{ $row['id'] }}" class="form-control"
                                   value="{{ $row['tags'] }}">
                        </div>
                    </td>
                </tr>
            @endforeach
            @if (count($allTickets) === 0)
                <td colspan="99">{{ __('projectOverview.empty_list') }}</td>
            @endif
            </tbody>
        </table>

    </div>
@endsection

@extends($layout)

@section('content')
    <div class="project-overview-container">
        <h1 class="h1">{{ __('projectOverview.dashboard_title') }}</h1>
        @if (count($allTickets) === 0)
            {{ __('projectOverview.empty_list') }}
        @endif
        <table class="table table-striped">
            <thead>
                <tr>
                    <th scope="col">{{ __('projectOverview.id_table_header') }}</th>
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
                @foreach ($allTickets as $row)
                    <tr>
                        <th scope="row">{{ $row['id'] }}</th>
                        <td>
                            <a href="#/tickets/showTicket/{{ $row['id'] }}">
                                {{ $row['headline'] }}
                            </a>
                            {{-- if the ticket does not depend on another ticket, this "id" is set to 0 --}}
                            @if ($row['dependingTicketId'] > 0)
                                (<a
                                    href="#/tickets/showTicket/{{ $row['dependingTicketId'] }}">{{ $row['parentHeadline'] }}</a>)
                            @endif
                        </td>
                        <td>
                            <div class="btn-group">
                                <button type="button" id="status-ticket-{{ $row['id'] }}"
                                    class="table-button {!! $statusLabels[$row['status']]['class'] !!}" data-toggle="dropdown">
                                    <span id="status-label">{!! $statusLabels[$row['status']]['name'] !!}</span> <i class="fa fa-caret-down"
                                        aria-hidden="true"></i>
                                </button>
                                <div class="dropdown-menu" id="status-dropdown-menu">
                                    @foreach ($statusLabels as $newStatusId => $label)
                                        <li class="dropdown-item">
                                            <button class="table-button {!! $label['class'] !!}"
                                                onclick="changeStatus({{ $row['id'] }}, {{ $newStatusId }}, '{{ $label['class'] }}', '{{ $label['name'] }}')">
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
                                    class="table-button label-default priority-bg-{!! $row['priority'] !!}"
                                    data-toggle="dropdown">
                                    @if (is_numeric($row['priority']))
                                        <span id="priority-label">{!! $priorities[$row['priority']] !!} </span>
                                        <i class="fa fa-caret-down" aria-hidden="true"></i>
                                    @endif
                                    @if (!is_numeric($row['priority']))
                                        <span id="priority-label">{{ __('projectOverview.no_priority_label') }}</span>
                                        <i class="fa fa-caret-down" aria-hidden="true"></i>
                                    @endif
                                </button>
                                <div class="dropdown-menu">
                                    @foreach ($priorities as $newPriorityId => $priorityLabel)
                                        <li class="dropdown-item">
                                            <button type="button"
                                                onclick="changePriority({{ $row['id'] }}, {{ $newPriorityId }}, '{{ $priorityLabel }}')"
                                                class="table-button priority-bg-{!! $newPriorityId !!}">
                                                <div> {{ $priorityLabel }}</div>
                                            </button>
                                        </li>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                        <td class="spacious">
                            <input type="date" onchange="changeDueDate({{ $row['id'] }}, this.value)"
                                value="{{ format($row['dateToFinish'])->date(__('text.anytime')) }}" />
                        </td>
                        <td class="spacious">
                            <select onchange="changeAssignedUser({{ $row['id'] }}, this.value)" class="form-select" style="width: 100%;">
                                @foreach ($row['projectUsers'] as $projectUser)
                                    <option value={{ $projectUser['id'] }}
                                        {{ (int) $row['editorId'] === (int) $projectUser['id'] ? 'selected' : '' }}>
                                        {{ $projectUser['firstname'] }}
                                        {{ $projectUser['lastname'] }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <div class="input-group input-group-sm mb-3">
                                <input onchange="changePlanHours({{ $row['id'] }}, this.value)" type="number"
                                    class="form-control" value="{{ $row['planHours'] }}">
                            </div>
                        </td>
                        <td>
                            <div class="input-group input-group-sm mb-3">
                                <input onchange="changeHoursRemaining({{ $row['id'] }}, this.value)" type="number"
                                    class="form-control" value="{{ $row['hourRemaining'] }}">
                            </div>
                        </td>
                        <td>
                            @if (count($row['projectMilestones']) > 0)
                                <select id="milestone-select" onchange="changeMilestone({{ $row['id'] }}, this.value)"
                                    class="form-select" style="background: {!! $row['selectedMilestoneColor']  !!}">
                                    @foreach ($row['projectMilestones'] as $projectMilestone)
                                        <option value={{ $projectMilestone['id'] }}
                                            id="milestone-option-{{ $projectMilestone['id'] }}"
                                            data-color="{!! $projectMilestone['color'] !!}"
                                            {{ (int) $row['milestoneid'] === (int) $projectMilestone['id'] ? 'selected=true' : 'selected=false' }}>
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
                                <input onchange="changeTags({{ $row['id'] }}, this.value)" type="text"
                                    class="form-control" value="{{ $row['tags'] }}">
                            </div>
                      </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

@extends($layout)
@section('content')
    <div class="project-overview-container" >
        <div id="filtersContainer" class="search-and-filter"
             hx-get="/projectOverview/projectOverview/loadFilters/{{ isset($selectedView) ? urlencode($selectedView) : (!empty($userViews) ? urlencode(array_key_first($userViews)) : '') }}"
             hx-target="#filtersContainer"
             hx-trigger="load">
            <div id="filters-loader">
                <div class="spinner"></div>
                Loading filters...
            </div>
        </div>

        @if(!empty($userViews))
        <div id="projectOverviewTabs">
            <ul>
                @foreach($userViews as $key => $userView)
                    <li data-target="{{ $key }}">
                        <a href="#view-{{ $key }}" class="tab-link"
                           data-view-key="{{ $key }}"
                           hx-get="/projectOverview/projectOverview/loadFilters/{{urlencode($key)}}"
                           hx-target="#filtersContainer"
                           hx-swap="innerHTML">
                            {{ str_replace('_', ' ', $key) }}
                        </a>
                        <span class="tab-context-menu">...</span>
                    </li>
                @endforeach
            </ul>


        @foreach($userViews as $key => $userView)
                <div id="view-{{$key}}">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            @foreach($userView['columns'] as $column)
                                <th id="sort_{{str_replace('.', '', $column)}}" scope="col">
                                    <div class="label-and-caret-wrapper">
                                        {{ __('projectOverview.' . strtolower($column) . '_table_header') }}
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @if(empty($userView['tickets']))
                            @foreach($userView['columns'] as $column)
                                <td>No tickets</td>
                            @endforeach
                        @else
                            @foreach ($userView['tickets'] as $key => $row)
                                <tr>
                                    @foreach($userView['columns'] as $column)
                                        @if($column == 'headline')
                                            <td class="spacious">
                                                <a href="#/tickets/showTicket/{{ $row->id }}">
                                                    {{ $row->headline }}
                                                </a>
                                            </td>
                                        @endif

                                        @if($column == 'project')
                                            <td class="spacious">
                                                <a class="project-link"
                                                   href="{{ $row->projectLink }}">{{ $row->projectName }}</a>
                                                @if (isset($row->dependingTicketId) && $row->dependingTicketId > 0)
                                                    (
                                                    <a href="#/tickets/showTicket/{{ $row->dependingTicketId }}">{{ $row->parentHeadline }}</a>
                                                    )
                                                @endif
                                            </td>
                                        @endif

                                        @if($column == 'status')
                                            <td class="spacious">
                                                <div class="btn-group status">
                                                    <button type="button" id="status-ticket-{{ $row->id }}"
                                                            class="table-button {!! $statusLabels[$row->projectId][$row->status]['class'] ?? '' !!}"
                                                            data-toggle="dropdown">
                                                    <span
                                                        id="status-label">{{ $statusLabels[$row->projectId][$row->status]['name'] ?? '' }}</span>
                                                        <i class="fa fa-caret-down"></i>
                                                    </button>
                                                    <div class="dropdown-menu" id="status-dropdown-menu">
                                                        @foreach ($statusLabels[$row->projectId] as $newStatusId => $label)
                                                            @if ($newStatusId != $row->status)
                                                                <li class="dropdown-item">
                                                                    <button
                                                                        class="table-button status {!! $label['class'] !!}"
                                                                        data-args="{{ $row->id }},{{ $newStatusId }},{{ $label['class'] }},{{ $label['name'] }}">
                                                                        {{ $label['name'] }}
                                                                    </button>
                                                                </li>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        @endif

                                        @if($column == 'priority')
                                            <td class="spacious">
                                                <div class="btn-group priority">
                                                    <button type="button" id="priority-ticket-{{ $row->id }}"
                                                            class="table-button priority-bg-{!! $row->priority !!}"
                                                            data-toggle="dropdown">
                                                        @if (is_numeric($row->priority) && isset($allPriorities[$row->priority]))
                                                            <span
                                                                id="priority-label">{!! $allPriorities[$row->priority] !!}</span>
                                                            <i class="fa fa-caret-down"></i>
                                                        @endif
                                                        @if (!is_numeric($row->priority))
                                                            <span
                                                                id="priority-label">{{ __('projectOverview.no_priority_label') }} </span>
                                                            <i class="fa fa-caret-down"></i>
                                                        @endif
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        @foreach ($allPriorities as $newPriorityId => $priorityLabel)
                                                            @if (is_numeric($row->priority) && isset($allPriorities[$row->priority]) && $allPriorities[$row->priority] == $priorityLabel)
                                                                @continue
                                                            @endif
                                                            <li class="dropdown-item">
                                                                <button type="button"
                                                                        data-args="{{ $row->id }},{{ $newPriorityId }},{{ $priorityLabel }}"
                                                                        class="table-button priority priority-bg-{!! $newPriorityId !!}">
                                                                    <div> {{ $priorityLabel }}</div>
                                                                </button>
                                                            </li>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        @endif

                                        @if($column == 'dateToFinish')
                                            <td class="specific">
                                                <input type="date" data-ticketid="{{ $row->id }}"
                                                       id="due-date-{{ $row->id }}"
                                                       value="{{ $row->dueDate ?? date($row->dueDate) }}"/>
                                            </td>
                                        @endif

                                        @if($column == 'editorLastname')
                                            @php
                                                $selectedUser = $row->editorId !== null && $row->editorId !== -1 ? collect($row->projectUsers)->firstWhere('id', $row->editorId) : null;
                                            @endphp
                                            <td data-selected-name="{{ $selectedUser ? $selectedUser['firstname'] . ' ' . $selectedUser['lastname'] : '' }}">
                                                <select class="form-select assigned-user-select"
                                                        id="assigned-user-{{ $row->id }}"
                                                        data-ticket-id="{{ $row->id }}">
                                                    <option value="-1"></option>
                                                    @foreach ($row->projectUsers as $projectUser)
                                                        <option value="{{ $projectUser['id'] }}"
                                                            {{ (int) $row->editorId === (int) $projectUser['id'] ? 'selected' : '' }}>
                                                            {{ $projectUser['firstname'] }} {{ $projectUser['lastname'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endif

                                        @if($column == 'planHours')
                                            <td>
                                                <div class="input-group input-group-sm mb-3">
                                                    <input type="number" id="plan-hours-{{ $row->id }}"
                                                           class="form-control"
                                                           value="{{ $row->planHours }}">
                                                </div>
                                            </td>
                                        @endif

                                        @if($column == 'hourRemaining')
                                            <td>
                                                <div class="input-group input-group-sm mb-3">
                                                    <input type="number" id="remaining-hours-{{ $row->id }}"
                                                           class="form-control"
                                                           value="{{ $row->hourRemaining }}">
                                                </div>
                                            </td>
                                        @endif

                                        @if($column == 'sumHours')
                                            <td>
                                                <div class="center-wrapper">
                                                <span class="logged-hours"
                                                      title="{{ $row->userHours }}">{{ $row->sumHours }}</span>
                                                </div>
                                            </td>
                                        @endif

                                        @if($column == 'milestoneid')
                                            <td>
                                                @if (count($row->projectMilestones) > 0)
                                                    <select id="milestone-select-{{ $row->id }}" class="form-select">
                                                        <option value="-1"></option>
                                                        @foreach ($row->projectMilestones as $projectMilestone)
                                                            <option value={{ $projectMilestone->id }}
                                                            id="milestone-option-{{ $projectMilestone->id }}"
                                                                {{ (int) $row->milestoneid === (int) $projectMilestone->id ? 'selected' : '' }}>
                                                                {{ $projectMilestone->headline }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                                @if (count($row->projectMilestones) === 0)
                                                    {{ __('projectOverview.no_project_milestones') }}
                                                @endif
                                            </td>
                                        @endif

                                        @if($column == 'tags')
                                            <td>
                                                <div class="input-group input-group-sm mb-3">
                                                    <input type="text" id="tags-{{ $row->id }}" class="form-control"
                                                           value="{{ $row->tags }}">
                                                </div>
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>
            @endforeach

            <script>
                jQuery(function () {
                    jQuery("#projectOverviewTabs").tabs({
                        @if(isset($selectedView) && in_array($selectedView, array_keys($userViews)))
                        active: {{ array_search($selectedView, array_keys($userViews)) }},
                        @endif
                        activate: function () {
                            jQuery("#edit-time-log-modal").removeClass("shown");
                        }
                    });
                });
            </script>


        </div>
        @endif
        <div id="view-context-menu">
            <form method="POST">
                <span>settings for view <span class="settings-for-target"></span>:</span>
                <input type="hidden" name="viewId" value="" />
                <button type="submit" name="action" value="deleteView" class="view-delete btn">Delete</button>
                <input name="viewName" type="text" />
                <button type="submit" name="action" value="renameView" class="view-rename btn">Omdøb</button>
            </form>
        </div>
@endsection

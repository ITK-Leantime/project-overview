@extends($layout)
@section('content')
    <script>
        window.allTags = @json($allTags ?? []);
    </script>
    <?php if (isset($tpl)) {
        echo $tpl->displayNotification();
    } ?>
    <!-- page header -->
    <div class="pageheader">
        <div class="pageicon"><span class="fa-regular fa-clock"></span></div>
        <div class="pagetitle">
            <h1>{{ __('projectOverview.dashboard_title') }}</h1>
        </div>
    </div>

    <div class="maincontent">
        <div class="maincontentinner">
            <div class="project-overview-container">
                <input type="hidden" id="frontendDateFormat" value="{{ $frontendDateFormat }}">
                <input type="hidden" id="selectedViewId"
                    value="{{ $userViewsData->selectedView !== null ? urlencode($userViewsData->selectedView) : (!empty($userViewsData->userViews) ? urlencode(array_key_first($userViewsData->userViews)) : '') }}" />
                <div id="filtersContainer" class="search-and-filter"
                    hx-get="/ProjectOverview/ProjectOverview/loadFilters/{{ $userViewsData->selectedView !== null ? urlencode($userViewsData->selectedView) : (!empty($userViewsData->userViews) ? urlencode(array_key_first($userViewsData->userViews)) : '') }}"
                    hx-target="#filtersContainer" hx-trigger="load">
                    <div id="filters-loader">
                        <div class="spinner"></div>
                        Loading filters...
                    </div>
                </div>


                @if (!empty($userViewsData->userViews))
                    <div id="projectOverviewTabs" class="is-hidden">
                        <ul>
                            @foreach ($userViewsData->userViews as $key => $userView)
                                <li data-target="{{ $key }}">
                                    <a href="#view-{{ $key }}" class="tab-link" data-view-key="{{ $key }}"
                                        hx-get="/ProjectOverview/ProjectOverview/loadFilters/{{ urlencode($key) }}"
                                        hx-target="#filtersContainer" hx-swap="innerHTML">
                                        {{ str_replace('_', ' ', $userView['title'] ?? 'View') }}
                                    </a>
                                    <span class="tab-context-menu">...</span>
                                </li>
                            @endforeach
                        </ul>


                        @foreach ($userViewsData->userViews as $key => $userView)
                            <div id="view-{{ $key }}">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            @foreach ($userView['view']['columns'] ?? ($userView['columns'] ?? []) as $column)
                                                <th id="sort_{{ str_replace('.', '', $column) }}" scope="col">
                                                    <div class="label-and-caret-wrapper">
                                                        {{ __('projectOverview.' . strtolower($column) . '_table_header') }}
                                                    </div>
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (empty($userView['tickets']))
                                            @foreach ($userView['view']['columns'] ?? ($userView['columns'] ?? []) as $column)
                                                <td>No tickets</td>
                                            @endforeach
                                        @else
                                            @foreach ($userView['tickets'] as $key => $row)
                                                <tr>
                                                    @foreach ($userView['view']['columns'] ?? ($userView['columns'] ?? []) as $column)
                                                        @if ($column == 'headline')
                                                            <td class="spacious">
                                                                <a href="#/tickets/showTicket/{{ $row->id }}"
                                                                    data-tippy-content="#{{ $row->id }} - {{ $row->headline }}"
                                                                    data-tippy-placement="top">
                                                                    {{ $row->headline }}
                                                                </a>
                                                                @if ($row->type === 'subtask' && isset($row->dependingTicketId) && $row->dependingTicketId > 0)
                                                                    <a href="#/tickets/showTicket/{{ $row->dependingTicketId }}"
                                                                        class="subtask-icon-link"
                                                                        data-tippy-content="Subtask of [#{{ $row->dependingTicketId }} - {{ $row->parentHeadline }}]"
                                                                        data-tippy-placement="top">
                                                                        <i class="fa fa-code-branch subtask-icon"></i>
                                                                    </a>
                                                                @endif
                                                            </td>
                                                        @endif

                                                        @if ($column == 'project')
                                                            <td class="spacious">
                                                                <a class="project-link"
                                                                    href="{{ $row->projectLink }}">{{ $row->projectName }}</a>
                                                            </td>
                                                        @endif

                                                        @if ($column == 'status')
                                                            <td class="spacious">
                                                                <div class="btn-group status">
                                                                    <button type="button"
                                                                        id="status-ticket-{{ $row->id }}"
                                                                        class="table-button table-button-status {!! $userViewsData->statusLabels[$row->projectId][$row->status]['class'] ?? '' !!}"
                                                                        data-toggle="dropdown">
                                                                        <span
                                                                            class="status-circle {!! $userViewsData->statusLabels[$row->projectId][$row->status]['class'] ?? '' !!}"></span>
                                                                        <span
                                                                            id="status-label">{{ $userViewsData->statusLabels[$row->projectId][$row->status]['name'] ?? '' }}</span>
                                                                        <i class="fa fa-caret-down"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu" id="status-dropdown-menu">
                                                                        @foreach ($userViewsData->statusLabels[$row->projectId] as $newStatusId => $label)
                                                                            @if ($newStatusId != $row->status)
                                                                                <li class="dropdown-item">
                                                                                    <button
                                                                                        class="table-button table-button-status status {!! $label['class'] !!}"
                                                                                        data-args="{{ $row->id }},{{ $newStatusId }},{{ $label['class'] }},{{ $label['name'] }}">
                                                                                        <span
                                                                                            class="status-circle {!! $label['class'] !!}"></span>
                                                                                        {{ $label['name'] }}
                                                                                    </button>
                                                                                </li>
                                                                            @endif
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        @endif

                                                        @if ($column == 'priority')
                                                            <td class="spacious">
                                                                <div class="btn-group priority">
                                                                    <button type="button"
                                                                        id="priority-ticket-{{ $row->id }}"
                                                                        class="table-button table-button-status"
                                                                        data-toggle="dropdown">
                                                                        @if (is_numeric($row->priority) && isset($userViewsData->allPriorities[$row->priority]))
                                                                            <span
                                                                                id="priority-label">{!! $userViewsData->allPriorities[$row->priority] !!}</span>
                                                                            <i class="fa fa-caret-down"></i>
                                                                        @endif
                                                                        @if (!is_numeric($row->priority))
                                                                            <span
                                                                                id="priority-label">{{ __('projectOverview.no_priority_label') }}
                                                                            </span>
                                                                            <i class="fa fa-caret-down"></i>
                                                                        @endif
                                                                    </button>
                                                                    <div class="dropdown-menu">
                                                                        @foreach ($userViewsData->allPriorities as $newPriorityId => $priorityLabel)
                                                                            @if (is_numeric($row->priority) && isset($userViewsData->allPriorities[$row->priority]) && $userViewsData->allPriorities[$row->priority] == $priorityLabel)
                                                                                @continue
                                                                            @endif
                                                                            <li class="dropdown-item">
                                                                                <button type="button"
                                                                                    data-args="{{ $row->id }},{{ $newPriorityId }},{{ $priorityLabel }}"
                                                                                    class="table-button table-button-status priority">
                                                                                    {{ $priorityLabel }}
                                                                                </button>
                                                                            </li>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        @endif

                                                        @if ($column == 'dateToFinish')
                                                            <td class="specific">
                                                                <input type="date" data-ticketid="{{ $row->id }}"
                                                                    id="due-date-{{ $row->id }}"
                                                                    value="{{ $row->dueDate ?? date($row->dueDate) }}" />
                                                            </td>
                                                        @endif

                                                        @if ($column == 'editorLastname')
                                                            @php
                                                                $selectedUser = $row->editorId !== null && $row->editorId !== -1 ? collect($row->projectUsers)->firstWhere('id', $row->editorId) : null;
                                                            @endphp
                                                            <td class="spacious"
                                                                data-selected-name="{{ $selectedUser ? $selectedUser['firstname'] . ' ' . $selectedUser['lastname'] : '' }}">
                                                                <div class="editor-select">
                                                                    <select class="form-select assigned-user-select"
                                                                        id="assigned-user-{{ $row->id }}"
                                                                        data-ticket-id="{{ $row->id }}">
                                                                        <option value="-1"></option>
                                                                        @foreach ($row->projectUsers as $projectUser)
                                                                            <option value="{{ $projectUser['id'] }}"
                                                                                {{ (int) $row->editorId === (int) $projectUser['id'] ? 'selected' : '' }}>
                                                                                {{ $projectUser['firstname'] }}
                                                                                {{ $projectUser['lastname'] }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                    <i class="fa fa-caret-down"></i>
                                                                </div>
                                                            </td>
                                                        @endif

                                                        @if ($column == 'planHours')
                                                            <td class="confined">
                                                                <div class="input-group input-group-sm mb-3">
                                                                    <input type="number"
                                                                        id="plan-hours-{{ $row->id }}"
                                                                        class="form-control"
                                                                        value="{{ $row->planHours }}">
                                                                </div>
                                                            </td>
                                                        @endif

                                                        @if ($column == 'hourRemaining')
                                                            <td class="confined">
                                                                <div class="input-group input-group-sm mb-3">
                                                                    <input type="number"
                                                                        id="remaining-hours-{{ $row->id }}"
                                                                        class="form-control"
                                                                        value="{{ $row->hourRemaining }}">
                                                                </div>
                                                            </td>
                                                        @endif

                                                        @if ($column == 'sumHours')
                                                            <td class="confined">
                                                                <div class="center-wrapper">
                                                                    <span class="logged-hours"
                                                                        title="{{ $row->userHours }}">{{ $row->sumHours }}</span>
                                                                </div>
                                                            </td>
                                                        @endif

                                                        @if ($column == 'milestoneid')
                                                            <td>
                                                                @if (count($row->projectMilestones) > 0)
                                                                    <div class="milestone-select">
                                                                        <select id="milestone-select-{{ $row->id }}"
                                                                            class="form-select">
                                                                            <option value="-1">
                                                                                {{ __('projectOverview.no_project_milestone_selected') }}
                                                                            </option>
                                                                            @foreach ($row->projectMilestones as $projectMilestone)
                                                                                <option value={{ $projectMilestone->id }}
                                                                                    id="milestone-option-{{ $projectMilestone->id }}"
                                                                                    {{ (int) $row->milestoneid === (int) $projectMilestone->id ? 'selected' : '' }}>
                                                                                    {{ $projectMilestone->headline }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                        <i class="fa fa-caret-down"></i>
                                                                    </div>
                                                                @endif
                                                                @if (count($row->projectMilestones) === 0)
                                                                    <p class="no-project-milestones"
                                                                        data-tippy-content="{{ __('projectOverview.no_project_milestones_text') }}"
                                                                        data-tippy-placement="top">
                                                                        {{ __('projectOverview.no_project_milestones') }}
                                                                    </p>

                                                                @endif
                                                            </td>
                                                        @endif

                                                        @if ($column == 'tags')
                                                            <td>
                                                                <select class="ticket-tags-select"
                                                                    id="tags-{{ $row->id }}"
                                                                    data-ticket-id="{{ $row->id }}" name="tags[]"
                                                                    multiple autocomplete="off">
                                                                    @if (!empty($row->tags))
                                                                        @foreach (explode(',', $row->tags) as $tag)
                                                                            @php $trimmedTag = trim($tag); @endphp
                                                                            @if (!empty($trimmedTag))
                                                                                <option value="{{ $trimmedTag }}"
                                                                                    selected>{{ $trimmedTag }}</option>
                                                                            @endif
                                                                        @endforeach
                                                                    @endif
                                                                </select>
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
                    </div>
                @endif
            </div>
        </div>
        <div id="view-context-menu">
            <form method="POST">
                <input type="hidden" name="view" />
                <span>Edit view name:</span>
                <input name="viewName" type="text" />
                <div class="buttons flex-container gap-1">
                    <button type="submit" name="action" value="renameView" class="view-rename btn">Rename
                    </button>
                    <button type="submit" name="action" value="deleteView" class="view-delete btn">Delete
                    </button>
                </div>
            </form>
        </div>
    @endsection

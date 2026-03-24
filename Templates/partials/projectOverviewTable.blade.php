@php
    $viewSortBy = $userView['view']['sortBy'] ?? 'priority';
    $viewSortDir = strtolower($userView['view']['sortDirection'] ?? 'ASC');
    $columns = $userView['view']['columns'] ?? ($userView['columns'] ?? []);
@endphp
<table class="table table-striped" data-sort-by="{{ $viewSortBy }}"
    data-sort-dir="{{ $viewSortDir }}">
    <thead>
        <tr>
            @foreach ($columns as $column)
                <th id="sort_{{ str_replace('.', '', $column) }}" scope="col"
                    class="{{ $viewSortBy === $column ? 'sort-' . $viewSortDir : '' }}">
                    <div class="label-and-caret-wrapper">
                        {{ __('projectOverview.' . strtolower($column) . '_table_header') }}
                    </div>
                </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @if (empty($userView['tickets']))
            @foreach ($columns as $column)
                <td>No tickets</td>
            @endforeach
        @else
            @foreach ($userView['tickets'] as $row)
                <tr>
                    @foreach ($columns as $column)
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
                            @php $projectStatuses = $statusLabels[$row->projectId] ?? []; @endphp
                            <td class="spacious">
                                <div class="btn-group status">
                                    <button type="button"
                                        id="status-ticket-{{ $row->id }}"
                                        class="table-button table-button-status {!! $projectStatuses[$row->status]['class'] ?? '' !!}"
                                        data-toggle="dropdown">
                                        <span
                                            class="status-circle {!! $projectStatuses[$row->status]['class'] ?? '' !!}"></span>
                                        <span
                                            id="status-label">{{ $projectStatuses[$row->status]['name'] ?? '' }}</span>
                                        <i class="fa fa-caret-down"></i>
                                    </button>
                                    <div class="dropdown-menu" id="status-dropdown-menu">
                                        @foreach ($projectStatuses as $newStatusId => $label)
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
                            <td class="spacious" data-sort-value="{{ $row->priority }}">
                                <div class="btn-group priority">
                                    <button type="button"
                                        id="priority-ticket-{{ $row->id }}"
                                        class="table-button table-button-status"
                                        data-toggle="dropdown">
                                        @if (is_numeric($row->priority) && isset($allPriorities[$row->priority]))
                                            <span class="priority-circle priority-bg-{{ $row->priority }}"></span>
                                            <span
                                                id="priority-label">{!! $allPriorities[$row->priority] !!}</span>
                                            <i class="fa fa-caret-down"></i>
                                        @endif
                                        @if (!is_numeric($row->priority))
                                            <span class="priority-circle"></span>
                                            <span
                                                id="priority-label">{{ __('projectOverview.no_priority_label') }}
                                            </span>
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
                                                    class="table-button table-button-status priority">
                                                    <span class="priority-circle priority-bg-{{ $newPriorityId }}"></span>
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

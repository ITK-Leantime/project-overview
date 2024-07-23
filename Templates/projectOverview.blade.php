@extends($layout)

@section('content')
    <div class="project-overview-container">
        <h1>{{ __('projectOverview.dashboard_title') }}</h1>
        <ul>
            @if (count($allTickets) === 0)
                {{ __('projectOverview.empty_list') }}
            @endif
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">{{ __('projectOverview.id_table_header') }}</th>
                        <th scope="col">{{ __('projectOverview.todo_table_header') }}</th>
                        <th scope="col">{{ __('projectOverview.status_table_header') }}</th>
                        <th scope="col">{{ __('projectOverview.parent_todo_table_header') }}</th>
                        <th scope="col">{{ __('projectOverview.due_date_table_header') }}</th>
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
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" id="status-ticket-{{ $row['id'] }}"
                                        class="{!! $statusLabels[$row['status']]['class'] !!}" data-toggle="dropdown" aria-haspopup="true"
                                        aria-expanded="false">
                                        {!! $statusLabels[$row['status']]['name'] !!}
                                    </button>
                                    <div class="dropdown-menu" id="status-dropdown-menu">
                                        @foreach ($statusLabels as $newStatusId => $label)
                                            <li class="dropdown-item">
                                                <button class="{!! $label['class'] !!}"
                                                    onclick="changeStatus({{ $row['id'] }}, {{ $newStatusId }}, '{{ $label['class'] }}', '{{ $label['name'] }}')">
                                                    {{ $label['name'] }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <td>
                                {{-- if the ticket does not depend on another ticket, this "id" is set to 0 --}}
                                @if ($row['dependingTicketId'] > 0)
                                    <a href="#/tickets/showTicket/{{ $row['dependingTicketId'] }}">
                                        {{ $row['parentHeadline'] }}
                                    </a>
                                @endif
                            </td>
                            <td>
                                <input type="date" onchange="changeDueDate({{ $row['id'] }}, this.value)" value="{{ format($row['dateToFinish'])->date(__('text.anytime')) }}" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </ul>
    </div>
@endsection

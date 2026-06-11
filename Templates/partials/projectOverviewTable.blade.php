@php
    $viewSortBy = $userView['view']['sortBy'] ?? 'priority';
    $viewSortDir = strtolower($userView['view']['sortDirection'] ?? 'ASC');
    $columns = $userView['view']['columns'] ?? ($userView['columns'] ?? []);
    $rows = $userView['tickets'] ?? [];
    $hasMore = $userView['hasMore'] ?? false;
    $nextPage = $userView['nextPage'] ?? null;
    $viewId = $userView['id'] ?? '';
    $columnCount = max(1, count($columns));
    $total = (int) ($userView['total'] ?? count($rows));
    $loaded = (int) ($userView['loaded'] ?? count($rows));
    $nextPageUrl = $hasMore && $nextPage !== null && $viewId !== ''
        ? '/ProjectOverview/ProjectOverview/loadViewTableRows/' . urlencode((string) $viewId)
        : null;
@endphp
{{-- On the synthetic "__new" tab, render the onboarding help above the live
     preview table so the user sees the guidance and the result side by side. --}}
@if ($viewId === '__new')
    @include('projectoverview::partials.newViewHelp')
@endif
<div class="result-count" data-total="{{ $total }}" data-loaded="{{ $loaded }}">
    @if ($total === 0)
        {{ __('projectOverview.result_count_none') }}
    @elseif ($loaded >= $total)
        {{ strtr(__('projectOverview.result_count_all'), ['{total}' => $total]) }}
    @else
        {{ strtr(__('projectOverview.result_count_partial'), ['{loaded}' => $loaded, '{total}' => $total]) }}
    @endif
</div>
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
        @if (empty($rows))
            <tr>
                <td colspan="{{ $columnCount }}">No tickets</td>
            </tr>
        @else
            @include('projectoverview::partials.projectOverviewTableRows', [
                'rows' => $rows,
                'columns' => $columns,
                'statusLabels' => $statusLabels,
                'allPriorities' => $allPriorities,
                'columnCount' => $columnCount,
                'nextPageUrl' => $nextPageUrl,
                'nextPage' => $nextPage,
            ])
        @endif
    </tbody>
</table>

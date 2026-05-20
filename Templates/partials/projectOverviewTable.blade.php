@php
    $viewSortBy = $userView['view']['sortBy'] ?? 'priority';
    $viewSortDir = strtolower($userView['view']['sortDirection'] ?? 'ASC');
    $columns = $userView['view']['columns'] ?? ($userView['columns'] ?? []);
    $rows = $userView['tickets'] ?? [];
    $hasMore = $userView['hasMore'] ?? false;
    $nextPage = $userView['nextPage'] ?? null;
    $viewId = $userView['id'] ?? '';
    $columnCount = max(1, count($columns));
    $nextPageUrl = $hasMore && $nextPage !== null && $viewId !== ''
        ? '/ProjectOverview/ProjectOverview/loadViewTableRows/' . urlencode((string) $viewId)
        : null;
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

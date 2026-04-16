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
        <div class="pageicon"><span class="fas fa-fw fa-th-list"></span></div>
        <div class="pagetitle">
            <h1>{{ __('projectOverview.dashboard_title') }}</h1>
        </div>
    </div>

    <div class="maincontent">
        <div class="maincontentinner">
            <div class="project-overview-container">
                <input type="hidden" id="frontendDateFormat" value="{{ $frontendDateFormat }}">
                <input type="hidden" id="selectedViewId"
                    value="{{ $userViewsData->selectedView !== null ? urlencode($userViewsData->selectedView) : (!empty($userViewsData->userViews) ? urlencode(array_key_first($userViewsData->userViews)) : '__preview') }}" />
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
                        <button type="button" id="filtersToggle" class="filters-toggle"
                            data-show="{{ __('projectOverview.show_filters') }}"
                            data-hide="{{ __('projectOverview.hide_filters') }}">
                            <span>{{ __('projectOverview.hide_filters') }}</span>
                            <i class="fas fa-chevron-up toggle-arrow"></i>
                        </button>
                        <ul>
                            @foreach ($userViewsData->userViews as $key => $userView)
                                <li data-target="{{ $key }}"
                                    {{ !empty($userView['isSubscription']) ? 'data-is-subscription=true' : '' }}>
                                    <a href="#view-{{ $key }}" class="tab-link"
                                        data-view-key="{{ $key }}"
                                        hx-get="/ProjectOverview/ProjectOverview/loadFilters/{{ urlencode($key) }}"
                                        hx-target="#filtersContainer" hx-swap="innerHTML">
                                        {{ str_replace('_', ' ', $userView['title'] ?? 'View') }}
                                        @if (!empty($userView['isTransientSubscription']))
                                            <span class="subscription-indicator"
                                                data-tippy-content="{{ __('projectOverview.subscription_preview') }}: {{ $userView['subscribedFromName'] ?? '' }}"
                                                data-tippy-placement="top">
                                                <i class="fa fa-eye"></i>
                                            </span>
                                        @elseif (!empty($userView['isSubscription']))
                                            <span class="subscription-indicator"
                                                data-tippy-content="{{ __('projectOverview.subscription_indicator') }}: {{ $userView['subscribedFromName'] ?? '' }}"
                                                data-tippy-placement="top">
                                                <i class="fa fa-link"></i>
                                            </span>
                                        @endif
                                    </a>
                                    @if (empty($userView['isTransientSubscription']))
                                        <span class="tab-context-menu">...</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>


                        @foreach ($userViewsData->userViews as $key => $userView)
                            <div id="view-{{ $key }}">
                                @include('projectoverview::partials.projectOverviewTable', [
                                'userView' => $userView,
                                'statusLabels' => $userViewsData->statusLabels,
                                'allPriorities' => $userViewsData->allPriorities,
                                ])
                            </div>
                        @endforeach
                    </div>
                @else
                    <div id="view-__preview"></div>
                @endif
            </div>
        </div>
        <div id="view-context-menu">
            <form method="POST">
                <input type="hidden" name="view" />
                <div class="context-menu-header">
                    {{ __('projectOverview.view_settings') }} <span id="contextMenuTitle"></span>
                </div>
                <div class="context-menu-section rename-section">
                    <label for="viewNameInput">{{ __('projectOverview.edit_view_name') }}</label>
                    <div class="rename-input-group">
                        <input name="viewName" id="viewNameInput" type="text" />
                        <button type="submit" name="action" value="renameView" class="view-rename btn btn-default">
                            {{ __('projectOverview.save_view') }}
                        </button>
                    </div>
                </div>
                <ul class="context-menu-actions">
                    <li>
                        <button type="button" class="view-share">
                            <i class="fa fa-share-alt"></i>
                            {{ __('projectOverview.share_view') }}
                        </button>
                    </li>
                    <li>
                        <button type="submit" name="action" value="deleteView" class="view-delete"
                            onclick="return confirm('{{ __('projectOverview.delete_view_confirm') }}')">
                            <i class="fa fa-trash"></i>
                            {{ __('projectOverview.delete_view') }}
                        </button>
                    </li>
                </ul>
            </form>
        </div>
        <div id="share-view-modal">
            <div class="share-modal-content">
                <div class="share-modal-header">
                    <h3>{{ __('projectOverview.share_view') }}</h3>
                    <button type="button" class="share-modal-close">&times;</button>
                </div>
                <p>{{ __('projectOverview.share_view_description') }}</p>
                <div class="share-modal-input-group">
                    <input type="text" id="share-link-input" readonly />
                    <button type="button" class="share-modal-copy-btn"
                        data-copied="{{ __('projectOverview.link_copied') }}">{{ __('projectOverview.copy_link') }}</button>
                </div>
            </div>
        </div>
    @endsection

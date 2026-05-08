@extends($layout)
@section('content')
    <script>
        window.allTags = @json($allTags ?? []);
        window.projectOverviewI18n = {
            couldNotLoadView: @json(__('projectOverview.could_not_load_view')),
            couldNotLoadMoreRows: @json(__('projectOverview.could_not_load_more_rows')),
            failedToInsertRows: @json(__('projectOverview.failed_to_insert_rows')),
            sessionExpired: @json(__('projectOverview.session_expired')),
            newViewPromptName: @json(__('projectOverview.new_view_prompt_name')),
        };
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
                    value="{{ $userViewsData->selectedView !== null ? urlencode($userViewsData->selectedView) : (!empty($userViewsData->userViews) ? urlencode(array_key_first($userViewsData->userViews)) : '__new') }}" />
                <div class="filters-row">
                    <div id="filtersContainer" class="search-and-filter"
                        hx-get="/ProjectOverview/ProjectOverview/loadFilters/{{ $userViewsData->selectedView !== null ? urlencode($userViewsData->selectedView) : (!empty($userViewsData->userViews) ? urlencode(array_key_first($userViewsData->userViews)) : '__new') }}"
                        hx-target="#filtersContainer" hx-trigger="load">
                        <div id="filters-loader">
                            <div class="spinner"></div>
                            Loading filters...
                        </div>
                    </div>
                    <button type="button" id="filtersToggle" class="filters-toggle"
                        data-show="{{ __('projectOverview.show_filters') }}"
                        data-hide="{{ __('projectOverview.hide_filters') }}">
                        <span>{{ __('projectOverview.hide_filters') }}</span>
                        <i class="fas fa-chevron-up toggle-arrow"></i>
                    </button>
                </div>


                <div id="projectOverviewTabs" class="is-hidden">
                    <ul>
                        @foreach ($userViewsData->userViews as $key => $userView)
                            <li data-target="{{ $key }}"
                                {{ !empty($userView['isSubscription']) ? 'data-is-subscription=true' : '' }}
                                {{ !empty($userView['isTransientSubscription']) ? 'data-is-transient-subscription=true' : '' }}
                                @if (!empty($userView['subscribeToken'])) data-subscribe-token="{{ $userView['subscribeToken'] }}" @endif>
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
                                <span class="tab-context-menu">...</span>
                            </li>
                        @endforeach
                        <li data-target="__new"
                            class="new-view-tab{{ empty($userViewsData->userViews) ? ' is-only-tab' : '' }}">
                            <a href="#view-__new" class="tab-link"
                                data-view-key="__new"
                                hx-get="/ProjectOverview/ProjectOverview/loadFilters/__new"
                                hx-target="#filtersContainer" hx-swap="innerHTML">
                                {{ __('projectOverview.new_view_tab') }}
                            </a>
                        </li>
                    </ul>


                    @foreach ($userViewsData->userViews as $key => $userView)
                        <div id="view-{{ $key }}">
                            @if ($userView['tickets'] !== null)
                                @include('projectoverview::partials.projectOverviewTable', [
                                'userView' => $userView,
                                'statusLabels' => $userViewsData->statusLabels,
                                'allPriorities' => $userViewsData->allPriorities,
                                ])
                            @else
                                <div class="view-lazy-load" data-view-key="{{ $key }}">
                                    <span class="view-lazy-load-spinner" aria-hidden="true"></span>
                                    <span class="view-lazy-load-text">{{ __('projectOverview.loading_view') }}</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                    <div id="view-__new">
                        <section class="new-view-tips-section">
                            <h3 class="new-view-tips-heading">{{ __('projectOverview.tips_heading') }}</h3>
                            <div class="new-view-tips">
                                <div class="new-view-tip-card">
                                    <h4>{{ __('projectOverview.tip_filters_title') }}</h4>
                                    <p>{{ __('projectOverview.tip_filters_body') }}</p>
                                </div>
                                <div class="new-view-tip-card">
                                    <h4>{{ __('projectOverview.tip_menu_title') }}</h4>
                                    <p>{{ __('projectOverview.tip_menu_body') }}</p>
                                </div>
                                <div class="new-view-tip-card">
                                    <h4>{{ __('projectOverview.tip_reorder_title') }}</h4>
                                    <p>{{ __('projectOverview.tip_reorder_body') }}</p>
                                </div>
                                <div class="new-view-tip-card">
                                    <h4>{{ __('projectOverview.tip_save_title') }}</h4>
                                    <p>{{ __('projectOverview.tip_save_body') }}</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
        <div id="view-context-menu" data-mode="owned">
            <form method="POST">
                <input type="hidden" name="view" />
                <input type="hidden" name="subscribeToken" value="" />
                <div class="context-menu-header">
                    {{ __('projectOverview.view_settings') }} <span id="contextMenuTitle"></span>
                </div>
                <div class="context-menu-section rename-section owned-only">
                    <label for="viewNameInput">{{ __('projectOverview.edit_view_name') }}</label>
                    <div class="rename-input-group">
                        <input name="viewName" id="viewNameInput" type="text" />
                        <button type="submit" name="action" value="renameView" class="view-rename btn btn-default">
                            {{ __('projectOverview.save_view') }}
                        </button>
                    </div>
                </div>
                <ul class="context-menu-actions">
                    <li class="owned-only">
                        <button type="button" class="view-share">
                            <i class="fa fa-share-alt"></i>
                            {{ __('projectOverview.share_view') }}
                        </button>
                    </li>
                    <li class="owned-only">
                        <button type="submit" name="action" value="duplicateView" class="view-duplicate">
                            <i class="fa fa-copy"></i>
                            {{ __('projectOverview.duplicate_view') }}
                        </button>
                    </li>
                    <li class="owned-only">
                        <button type="submit" name="action" value="deleteView" class="view-delete"
                            onclick="return confirm('{{ __('projectOverview.delete_view_confirm') }}')">
                            <i class="fa fa-trash"></i>
                            {{ __('projectOverview.delete_view') }}
                        </button>
                    </li>
                    <li class="subscription-only">
                        <button type="submit" name="action" value="pinSubscription" class="view-pin">
                            <i class="fa fa-thumbtack"></i>
                            {{ __('projectOverview.pin_to_my_views') }}
                        </button>
                    </li>
                    <li class="subscription-only">
                        <button type="submit" name="action" value="saveTransientAsCopy" class="view-copy">
                            <i class="fa fa-copy"></i>
                            {{ __('projectOverview.save_as_copy') }}
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
        <button type="button" id="scrollToTopBtn" class="scroll-to-top-btn"
            aria-label="{{ __('projectOverview.scroll_to_top') }}" hidden>
            <i class="fa fa-chevron-up" aria-hidden="true"></i>
        </button>
    @endsection

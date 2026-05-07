import 'select2';
import 'select2/dist/css/select2.css';
import flatpickr from 'flatpickr';
import { Danish } from 'flatpickr/dist/l10n/da.js';
import 'flatpickr/dist/flatpickr.min.css';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.css';
import './project-overview.css';

$(document).ready(function () {
  window.frontendDateFormat = $(document).find('#frontendDateFormat').val();
  initFiltersToggle();
  initProjectOverviewFilters();
  initProjectOverviewTable();
  initScrollToTopButton();
  initUnsavedNoticeScrollLink();

  // begin HTMX swap events
  document.body.addEventListener('htmx:beforeSettle', (e) => {
    e.detail.target.style.visibility = 'hidden';
  });
  document.addEventListener('htmx:afterSettle', function (e) {
    e.detail.target.style.visibility = '';
    if (e.target.id === 'filtersContainer') {
      initProjectOverviewFilters();

      // Restore cached unsaved form state if returning to a dirty view
      const activeViewId = document.getElementById('selectedViewId');
      if (
        activeViewId &&
        window._viewCachedFormData &&
        window._viewCachedFormData[activeViewId.value]
      ) {
        restoreFormState(window._viewCachedFormData[activeViewId.value]);
      }

      // Restore save button state after HTMX replaces the filters DOM
      const saveBtn = document.querySelector('.save-view-btn');
      if (saveBtn && activeViewId && window._viewsWithUnsavedChanges) {
        saveBtn.classList.toggle(
          'has-unsaved-changes',
          !!window._viewsWithUnsavedChanges[activeViewId.value]
        );
      }

      // Lazy-load: if the active view panel has a placeholder, trigger a table refresh
      if (activeViewId && activeViewId.value) {
        const activePanel = document.getElementById('view-' + activeViewId.value);
        if (activePanel && activePanel.querySelector('.view-lazy-load')) {
          const form = document.getElementById('filtersForm');
          if (form) {
            refreshViewTable(form);
          }
        }
      }
    }
  });
  // end HTMX swap events
});

/**
 * Show the floating "scroll to top" button after the user scrolls down enough,
 * and smoothly return them to the top on click.
 */
function initScrollToTopButton() {
  const btn = document.getElementById('scrollToTopBtn');
  if (!btn) return;

  const SHOW_AFTER_PX = 320;
  let raf = 0;

  function update() {
    raf = 0;
    const shouldShow = window.scrollY > SHOW_AFTER_PX;
    if (shouldShow && btn.hasAttribute('hidden')) {
      btn.removeAttribute('hidden');
      // Force a paint so the transition runs from opacity:0 to 1
      requestAnimationFrame(() => btn.classList.add('is-visible'));
    } else if (!shouldShow && !btn.hasAttribute('hidden')) {
      btn.classList.remove('is-visible');
      // Wait for the fade-out before removing from layout
      setTimeout(() => {
        if (window.scrollY <= SHOW_AFTER_PX) btn.setAttribute('hidden', '');
      }, 200);
    }
  }

  window.addEventListener(
    'scroll',
    () => {
      if (raf) return;
      raf = requestAnimationFrame(update);
    },
    { passive: true }
  );

  btn.addEventListener('click', () => {
    const reduceMotion = window.matchMedia(
      '(prefers-reduced-motion: reduce)'
    ).matches;
    window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
  });

  update();
}

/**
 * Make the "unsaved changes" notice clickable: clicking it scrolls the
 * save button into view so the user can find it without hunting.
 */
function initUnsavedNoticeScrollLink() {
  // Delegated so it survives htmx swaps of #filtersContainer.
  document.addEventListener('click', function (e) {
    const notice = e.target.closest('#unsavedChangesNotice');
    if (!notice) return;
    const saveBtn =
      document.querySelector('.save-view-btn') ||
      document.querySelector('.save-as-new-btn');
    if (!saveBtn) return;
    const reduceMotion = window.matchMedia(
      '(prefers-reduced-motion: reduce)'
    ).matches;
    saveBtn.scrollIntoView({
      behavior: reduceMotion ? 'auto' : 'smooth',
      block: 'center',
    });
    // Quick highlight pulse so the user's eye lands on it
    saveBtn.classList.add('save-btn-pulse');
    setTimeout(() => saveBtn.classList.remove('save-btn-pulse'), 1200);
  });
}

/**
 * Initializes the collapsible filters toggle with localStorage persistence.
 */
function initFiltersToggle() {
  const STORAGE_KEY = 'projectOverview.filtersCollapsed';
  const toggle = document.getElementById('filtersToggle');
  const container = document.getElementById('filtersContainer');
  if (!toggle || !container) return;

  var label = toggle.querySelector('span');

  function updateLabel(collapsed) {
    label.textContent = collapsed ? toggle.dataset.show : toggle.dataset.hide;
  }

  // Restore saved state (disable transition to prevent animation on load)
  if (localStorage.getItem(STORAGE_KEY) === '1') {
    container.style.transition = 'none';
    container.classList.add('collapsed');
    toggle.classList.add('collapsed');
    updateLabel(true);
    // Re-enable transition after the browser has painted
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        container.style.transition = '';
      });
    });
  }

  toggle.addEventListener('click', function () {
    const isCollapsed = container.classList.toggle('collapsed');
    toggle.classList.toggle('collapsed', isCollapsed);
    updateLabel(isCollapsed);
    localStorage.setItem(STORAGE_KEY, isCollapsed ? '1' : '0');
  });
}

/**
 * Initializes the project overview filters by setting up various UI components.
 *
 * @return {void} This function does not return a value.
 */
function initProjectOverviewFilters() {
  // Tooltip on the unsaved-changes notice — init defensively even if Leantime
  // core already runs a global tippy pass.
  if (typeof tippy === 'function') {
    const notice = document.getElementById('unsavedChangesNotice');
    if (notice && !notice._tippy) {
      tippy(notice);
    }
  }

  // Init date range select
  const dateRange = flatpickr('#dateRange', {
    mode: 'range',
    dateFormat: window.frontendDateFormat,
    allowInput: false,
    readonly: false,
    weekNumbers: true,
    locale: Danish,
    onChange: function (selectedDates) {
      if (selectedDates && selectedDates.length === 2) {
        const [startDate, endDate] = selectedDates;

        // Format dates to d-m-Y
        const formatDate = (date) => {
          const day = String(date.getDate()).padStart(2, '0');
          const month = String(date.getMonth() + 1).padStart(2, '0');
          const year = date.getFullYear();
          return `${day}-${month}-${year}`;
        };

        $('#fromDate').val(formatDate(startDate));
        $('#toDate').val(formatDate(endDate));
      }
    },
  });

  // Init filter select2
  const filterSelect = $('#filterSelect')
    .select2({
      closeOnSelect: false,
      dropdownCssClass: 'project-overview-dropdown',
    })
    .on('select2:select', () => {
      $(this).val(null).trigger('change');
    })
    .on('change.select2', () => {
      $(filterSelect)
        .next('.select2')
        .attr('data-length', function () {
          return filterSelect.select2('data')?.length;
        });
    });

  filterSelect.next('.select2').attr('data-length', function () {
    return filterSelect.select2('data')?.length;
  });

  // Init column select2
  const columnSelect = $('#columnSelect')
    .select2({
      closeOnSelect: false,
      dropdownCssClass: 'project-overview-dropdown',
    })
    .on('change.select2', () => {
      $(columnSelect)
        .next('.select2')
        .attr('data-length', function () {
          return columnSelect.select2('data')?.length;
        });
    });

  columnSelect.next('.select2').attr('data-length', function () {
    return columnSelect.select2('data')?.length;
  });

  // Init date range select
  $('#dateOptions')
    .on('change', function () {
      const dateRangeElement = $(document).find('div.date-range-filter');
      const dateRangeInput = $('#dateRange');
      const selectedOption = $(this).find('option:selected');

      // Get pre-calculated dates from data attributes
      const startDate = selectedOption.data('start-date');
      const endDate = selectedOption.data('end-date');

      if (startDate && endDate) {
        // Parse YYYY-MM-DD format to Date objects
        const [startYear, startMonth, startDay] = startDate
          .split('-')
          .map(Number);
        const [endYear, endMonth, endDay] = endDate.split('-').map(Number);

        const start = new Date(startYear, startMonth - 1, startDay);
        const end = new Date(endYear, endMonth - 1, endDay);

        dateRange.setDate([start, end]);
        dateRange.set('clickOpens', false);
        $(dateRangeElement).addClass('date-range-disabled');
        dateRangeInput.prop('readonly', true);
      } else if (selectedOption.val() === 'custom') {
        dateRange.set('clickOpens', true);
        $(dateRangeElement).removeClass('date-range-disabled');
        dateRangeInput.prop('readonly', false);
      }
    })
    .trigger('change');

  // Init assignee select2
  const userSelect = $('#userSelect')
    .select2({
      closeOnSelect: false,
      dropdownCssClass: 'project-overview-dropdown',
      matcher: function (params, data) {
        if (!params.term) return data;
        const keywords = params.term.split(' ');
        const text = data.text.toUpperCase();
        for (const keyword of keywords) {
          if (text.indexOf(keyword.toUpperCase()) === -1) return null;
        }
        return data;
      },
    })
    .on('change.select2', () => {
      $(userSelect)
        .next('.select2')
        .attr('data-length', function () {
          return userSelect.select2('data')?.length;
        });
    });
  userSelect.next('.select2').attr('data-length', function () {
    return userSelect.select2('data')?.length;
  });

  // Re-enable disabled fields on submit so their values are included in POST data
  const filtersForm = document.getElementById('filtersForm');
  if (filtersForm) {
    filtersForm.addEventListener('submit', function () {
      filtersForm
        .querySelectorAll('select[disabled], input[disabled]')
        .forEach(function (el) {
          el.disabled = false;
        });
    });
  }

  // --- Live filter update: refresh table on filter change ---
  if (!filtersForm || filtersForm.dataset.isSubscription === 'true') return;

  const viewId = document.getElementById('selectedViewId');
  const currentViewId = viewId ? viewId.value : null;

  // Store the initial state for the current view
  if (currentViewId) {
    if (!window._viewInitialStates) window._viewInitialStates = {};
    window._viewInitialStates[currentViewId] = serializeFilterForm(filtersForm);
  }

  let filterDebounceTimer = null;

  function onFilterChange() {
    const activeViewId = viewId ? viewId.value : null;
    const initialState =
      activeViewId && window._viewInitialStates
        ? window._viewInitialStates[activeViewId]
        : null;
    const hasChanges =
      initialState !== null &&
      serializeFilterForm(filtersForm) !== initialState;
    toggleUnsavedIndicator(activeViewId, hasChanges);

    clearTimeout(filterDebounceTimer);
    filterDebounceTimer = setTimeout(function () {
      refreshViewTable(filtersForm);
    }, 300);
  }

  $('#userSelect').on('change.select2', onFilterChange);
  $('#filterSelect').on('change.select2', onFilterChange);
  $('#columnSelect').on('change.select2', onFilterChange);
  $('#dateOptions').on('change', onFilterChange);

  // Extend flatpickr onChange to also trigger filter refresh
  const fpInstance = document.getElementById('dateRange')?._flatpickr;
  if (fpInstance) {
    const originalOnChange = fpInstance.config.onChange;
    fpInstance.config.onChange.push(function (selectedDates) {
      if (selectedDates && selectedDates.length === 2) {
        onFilterChange();
      }
    });
  }
}

function initProjectOverviewTable() {
  // Init tags select for each row.
  initTagsSelects();

  // Wire the lazy-load buttons on the initially-rendered active panel.
  // (Inactive tabs hold a placeholder until activated, then refreshViewTable
  // re-attaches the buttons for them.)
  document.querySelectorAll('[id^="view-"]').forEach(function (panel) {
    if (panel.querySelector('.lazy-row-sentinel')) {
      attachLazyLoad(panel);
    }
  });

  const contextMenu = $('#view-context-menu');

  // Start sorting
  // Status change
  $(document).on('click', '.dropdown-item .table-button.status', function () {
    const [ticketId, newStatus, className, name] = $(this)
      .data('args')
      .split(',');
    changeStatus(ticketId, newStatus, className, name);
  });

  // Priority change
  $(document).on('click', '.dropdown-item .table-button.priority', function () {
    const [ticketId, newPriority, label] = $(this).data('args').split(',');
    changePriority(ticketId, newPriority, label);
  });

  // begin sorting
  document.addEventListener('click', function (e) {
    const th = e.target.closest('[id^=sort_]');
    if (th) {
      changeSortBy(th.id.replace('sort_', ''), th);
    }
  });

  $(document).on('change', '[id^=due-date-]', function () {
    const ticketId = $(this).data('ticketid');
    changeDueDate(event, ticketId, $(this).val());
  });

  $(document).on('change', '[id^=assigned-user-]', function () {
    const idArg = this.id.split('-')[2];
    changeAssignedUser(event, idArg, this.value);
  });

  $(document).on('change', '[id^=plan-hours-]', function () {
    const idArg = this.id.split('-')[2];
    changePlanHours(event, idArg, this.value);
  });

  $(document).on('change', '[id^=remaining-hours-]', function () {
    const idArg = this.id.split('-')[2];
    changeHoursRemaining(event, idArg, this.value);
  });

  $(document).on('change', '[id^=milestone-select-]', function () {
    const idArg = this.id.split('-')[2];
    changeMilestone(event, idArg, this.value);
  });
  // end sorting

  // Init click event on context menu
  $(document).on('click', 'span.tab-context-menu', ({ target }) => {
    const currentName = $(target).siblings('.tab-link').first().text().trim();
    const rect = target.parentElement.getBoundingClientRect();
    const tab = $(target).parent();
    const viewId = tab.data('target');
    const isSubscription = tab.data('is-subscription') === true;
    $('.settings-for-target').text(viewId);
    contextMenu
      .css({
        left: `${rect.left + window.scrollX - 175}px`,
        top: `${rect.top + window.scrollY - rect.height - 25}px`,
      })
      .addClass('shown')
      .find('#contextMenuTitle')
      .text(currentName)
      .end()
      .find('input[name="viewName"]')
      .val(currentName)
      .end()
      .find('input[name="view"]')
      .val(viewId);

    // Hide rename/share controls for subscribed views
    contextMenu.find('.rename-section, .view-share').toggle(!isSubscription);

    if (!isSubscription) {
      requestAnimationFrame(() => {
        contextMenu.find('input[name="viewName"]').focus();
      });
    }
  });

  // Close context menu when clicked outside.
  $(document).on('click', function (event) {
    if (
      !$(event.target).closest('#view-context-menu').length &&
      !$(event.target).closest('span.tab-context-menu').length
    ) {
      contextMenu.removeClass('shown');
    }
  });
  // Close .tab-context-menu when clicking on any other tab.
  $(document).on('click', '#projectOverviewTabs > ul > li', ({ target }) => {
    if (!$(target).closest('span.tab-context-menu').length) {
      contextMenu.removeClass('shown');
    }
  });
  // Expand Project results.
  $(document).on('click', '.select2-results__group', ({ target }) => {
    $(target).toggleClass('show-all-projects');
  });

  // Check if URL has a view parameter
  const urlParams = new URLSearchParams(window.location.search);
  const urlViewId = urlParams.get('view');
  let selectedViewId = $(document).find('#selectedViewId').val();

  // If URL has a view parameter and it exists in the tabs, use that
  if (urlViewId && window.jQuery(`li[data-target='${urlViewId}']`).length > 0) {
    selectedViewId = urlViewId;
    window.jQuery('#selectedViewId').val(urlViewId);
  }

  // Use window.jQuery to access the globally loaded jQuery UI
  const $projectOverviewTabs = window.jQuery('#projectOverviewTabs');

  // Init view tabs with sorting
  $projectOverviewTabs
    .tabs({
      beforeActivate: function (event, ui) {
        // Cache unsaved form state before switching away
        const currentViewId = window.jQuery('#selectedViewId').val();
        if (
          currentViewId &&
          window._viewsWithUnsavedChanges &&
          window._viewsWithUnsavedChanges[currentViewId]
        ) {
          const form = document.getElementById('filtersForm');
          if (form) {
            if (!window._viewCachedFormData) window._viewCachedFormData = {};
            window._viewCachedFormData[currentViewId] = captureFormState(form);
          }
        }
      },
      activate: function (event, ui) {
        window.jQuery('#edit-time-log-modal').removeClass('shown');

        // Update URL when tab is activated
        const viewId = ui.newPanel.attr('id').replace('view-', '');

        // Sync save button and unsaved banner with the newly active view
        const viewHasChanges = !!(
          window._viewsWithUnsavedChanges &&
          window._viewsWithUnsavedChanges[viewId]
        );
        const saveBtn = document.querySelector('.save-view-btn');
        if (saveBtn) {
          saveBtn.classList.toggle('has-unsaved-changes', viewHasChanges);
        }
        const banner = document.getElementById('unsavedChangesNotice');
        if (banner) {
          banner.style.display = viewHasChanges ? '' : 'none';
        }
        const url = new URL(window.location.href);
        url.searchParams.set('view', viewId);
        window.history.pushState({ view: viewId }, '', url);

        // Update hidden input
        window.jQuery('#selectedViewId').val(viewId);
      },
      active: window.jQuery(`li[data-target='${selectedViewId}']`).index(),
    })
    .find('ul')
    .sortable({
      items: 'li',
      axis: 'x',
      tolerance: 'pointer',
      update: function (event, ui) {
        var newOrder = window
          .jQuery(this)
          .sortable('toArray', { attribute: 'data-target' });

        // Send AJAX request to save the new order
        window.jQuery.ajax({
          dataType: 'json',
          url: 'ProjectOverview/ProjectOverview/post',
          method: 'POST',
          data: {
            action: 'saveTabOrder',
            order: newOrder,
          },
          success: function (response) {
            if (response.status === 'success') {
              window.jQuery.growl({
                message: response.message || 'Tab order saved successfully',
              });
            } else {
              window.jQuery.growl({
                message: response.message || 'Failed to save tab order',
              });
            }
          },
          error: function (xhr, status, error) {
            window.jQuery.growl({
              message: 'Error saving tab order: ' + error,
            });
          },
        });
      },
    });

  // Fade in after initialization
  $projectOverviewTabs.removeClass('is-hidden');

  // Set initial URL state
  if (!urlViewId) {
    const url = new URL(window.location.href);
    url.searchParams.set('view', selectedViewId);
    window.history.replaceState({ view: selectedViewId }, '', url);
  }

  // Handle browser back/forward buttons
  window.addEventListener('popstate', function (event) {
    if (event.state && event.state.viewId) {
      const viewIndex = window
        .jQuery(`li[data-target='${event.state.viewId}']`)
        .index();
      if (viewIndex >= 0) {
        $projectOverviewTabs.tabs('option', 'active', viewIndex);
        window.jQuery('#selectedView').val(event.state.viewId);

        // Trigger HTMX to load the filters for this view
        const hxGetUrl = `/ProjectOverview/ProjectOverview/loadFilters/${encodeURIComponent(event.state.viewId)}`;
        window.jQuery('#filtersContainer').attr('hx-get', hxGetUrl);
        htmx.trigger('#filtersContainer', 'load');
      }
    }
  });

  // Open share modal from context menu
  document.addEventListener('click', function (e) {
    const shareBtn = e.target.closest('button.view-share');
    if (!shareBtn) return;

    e.preventDefault();
    const viewId = document.querySelector(
      '#view-context-menu input[name="view"]'
    ).value;
    const modal = document.getElementById('share-view-modal');
    const input = document.getElementById('share-link-input');

    input.value = 'Loading...';
    modal.classList.add('shown');
    document.getElementById('view-context-menu').classList.remove('shown');

    jQuery.ajax({
      type: 'POST',
      url: '/ProjectOverview/ProjectOverview/generateShareLink',
      data: { view: viewId },
      dataType: 'json',
      success: function (response) {
        if (response.success && response.shareToken) {
          input.value =
            window.location.origin +
            '/ProjectOverview/ProjectOverview?subscribe=' +
            response.shareToken;
        } else {
          input.value = 'Error generating link';
        }
      },
      error: function () {
        input.value = 'Error generating link';
      },
    });
  });

  // Copy share link from modal input
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.share-modal-copy-btn')) return;
    const input = document.getElementById('share-link-input');
    const btn = e.target.closest('.share-modal-copy-btn');
    const originalText = btn.textContent;
    const copiedText = btn.dataset.copied || 'Copied';

    navigator.clipboard.writeText(input.value).then(function () {
      btn.textContent = copiedText;
      setTimeout(function () {
        btn.textContent = originalText;
      }, 2000);
    });
  });

  // Close share modal
  document.addEventListener('click', function (e) {
    if (e.target.closest('.share-modal-close')) {
      document.getElementById('share-view-modal').classList.remove('shown');
    }
    if (e.target.id === 'share-view-modal') {
      e.target.classList.remove('shown');
    }
  });
}

function initSingleTagSelect(selectElement) {
  if (!selectElement || selectElement.tomselect) return;

  const allTags = window.allTags || [];
  const ticketId = selectElement.dataset.ticketId;

  try {
    new TomSelect(selectElement, {
      plugins: ['remove_button'],
      maxItems: null,
      create: true,
      persist: false,
      openOnFocus: false,
      loadThrottle: 300,
      load: function (query, callback) {
        if (!query.length) {
          this.close();
          return callback();
        }

        const filtered = allTags
          .filter((tag) => tag.toLowerCase().includes(query.toLowerCase()))
          .slice(0, 50)
          .map((tag) => ({ value: tag, text: tag }));

        callback(filtered);
      },
      onChange: function (values) {
        const tagsString = Array.isArray(values) ? values.join(',') : values;
        changeTags({ target: selectElement }, ticketId, tagsString);
      },
    });
  } catch (err) {
    console.error(
      '[ProjectOverview] TomSelect init failed for',
      selectElement,
      err
    );
  }
}

function initTagsSelects() {
  document.querySelectorAll('.ticket-tags-select').forEach(initSingleTagSelect);
}

function changeStatus(ticketId, newStatusId, newClass, newLabel) {
  if (newStatusId !== undefined && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          status: newStatusId,
        },
      })
      .done(() => {
        // In this way, the UI does not reflect the actual data, which is not good.
        // But if I instead create a get-request it returns 200 and an otherwise empty
        // response. So this is what I chose to do, and is also what is done in
        // in other places (I am looking at you ticketcontroller.js).

        // Update ALL buttons with this ID (same ticket can appear in multiple views)
        document
          .querySelectorAll(`#status-ticket-${ticketId}`)
          .forEach((button) => {
            button.className = `table-button table-button-status ${newClass}`;
            const circle = button.querySelector('.status-circle');
            if (circle) {
              circle.className = `status-circle ${newClass}`;
            }
            const label = button.querySelector('#status-label');
            if (label) {
              label.textContent = newLabel;
            }
          });
      });
  }
}

// change priority ajax
function changePriority(ticketId, newPriorityId, newLabel) {
  if (newPriorityId && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          priority: newPriorityId,
        },
      })
      .done(() => {
        // In this way, the UI does not reflect the actual data, which is not good.
        // But if I instead create a get-request it returns 200 and an otherwise empty
        // response. So this is what I chose to do, and is also what is done in
        // in other places (I am looking at you ticketcontroller.js).

        // Update ALL buttons with this ID (same ticket can appear in multiple views)
        document
          .querySelectorAll(`#priority-ticket-${ticketId}`)
          .forEach((button) => {
            button.className = `table-button table-button-status`;
            const circle = button.querySelector('.priority-circle');
            if (circle) {
              circle.className = `priority-circle priority-bg-${newPriorityId}`;
            }
            const label = button.querySelector('#priority-label');
            if (label) {
              label.textContent = newLabel;
            }
          });
      });
  }
}

// Change duedate ajax
function changeDueDate(event, ticketId, newDueDate) {
  const parentElement = jQuery(event.target).closest('td');

  if (newDueDate && ticketId) {
    const dueDate = window.jQuery.datepicker.formatDate(
      leantime.dateHelper.getFormatFromSettings('dateformat', 'jquery'),
      new Date(newDueDate)
    );
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          dateToFinish: dueDate,
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change assigned user ajax
function changeAssignedUser(event, ticketId, userId) {
  const parentElement = jQuery(event.target).closest('td');

  if (userId && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          editorId: userId,
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change plan hours ajax
function changePlanHours(event, ticketId, newPlanHours) {
  const parentElement = jQuery(event.target).closest('td');

  if (newPlanHours && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          planHours: newPlanHours,
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change hours remaining ajax
function changeHoursRemaining(event, ticketId, newHoursRemaining) {
  const parentElement = jQuery(event.target).closest('td');

  if (newHoursRemaining && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          hourRemaining: newHoursRemaining,
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change milestone ajax
function changeMilestone(event, ticketId, newMilestoneId) {
  const parentElement = jQuery(event.target).closest('td');
  if (newMilestoneId && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          milestoneid: newMilestoneId,
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change tags ajax
function changeTags(event, ticketId, newTags) {
  const parentElement = jQuery(event.target).closest('td');

  if (ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          tags: newTags || '',
        },
      })
      .then(() => {
        saveSuccess(parentElement);
      })
      .fail(() => {
        saveError(parentElement);
      });
  }
}

// Change sort — client-side DOM sort + silent persist
function changeSortBy(sortBy, clickedTh) {
  const table = clickedTh.closest('table');
  if (!table) return;

  const headers = Array.from(table.querySelectorAll('thead th'));

  // Toggle direction
  const currentCol = table.dataset.sortBy;
  const currentDir = table.dataset.sortDir;
  let direction = 'asc';
  if (currentCol === sortBy && currentDir === 'asc') direction = 'desc';
  table.dataset.sortBy = sortBy;
  table.dataset.sortDir = direction;

  // Update visual indicators immediately
  headers.forEach(function (th) {
    th.classList.remove('sort-asc', 'sort-desc');
  });
  clickedTh.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');

  // Persist sort preference (silent — server is the source of truth on next render)
  const viewId = document.getElementById('selectedViewId');
  if (viewId && viewId.value) {
    fetch(window.location.pathname, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: new URLSearchParams({
        action: 'saveSortOrder',
        view: viewId.value,
        sortBy: sortBy,
        sortDirection: direction.toUpperCase(),
      }),
    });
  }

  // Re-fetch from server with the new sort. With pagination, client-side sort
  // would only reorder the visible page; the server sorts the full dataset and
  // resets pagination to page 1.
  const form = document.getElementById('filtersForm');
  if (form) {
    refreshViewTable(form);
  }
}

// --- Live filter helpers ---

/**
 * Capture all filter field values from the form for later restoration.
 *
 * @param {HTMLFormElement} form
 * @returns {object} Field values keyed by name.
 */
function captureFormState(form) {
  return {
    users: $('#userSelect', form).val() || [],
    filters: $('#filterSelect', form).val() || [],
    columns: $('#columnSelect', form).val() || [],
    dateType: $('#dateOptions', form).val(),
    fromDate: $('#fromDate', form).val(),
    toDate: $('#toDate', form).val(),
    dateRangeText: $('#dateRange', form).val(),
  };
}

/**
 * Restore previously captured form state into the current filter form.
 * Triggers change events so select2/flatpickr update, and the table refreshes.
 *
 * @param {object} state The state object from captureFormState.
 */
function restoreFormState(state) {
  // Restore select2 multi-selects (set values without triggering change yet)
  $('#userSelect').val(state.users).trigger('change.select2');
  $('#filterSelect').val(state.filters).trigger('change.select2');
  $('#columnSelect').val(state.columns).trigger('change.select2');

  // Restore date type (triggers the dateRange toggle handler)
  $('#dateOptions').val(state.dateType).trigger('change');

  // For custom dates, restore the actual date values after the dateType handler ran
  if (state.dateType === 'custom') {
    $('#fromDate').val(state.fromDate);
    $('#toDate').val(state.toDate);

    const fp = document.getElementById('dateRange')?._flatpickr;
    if (fp && state.fromDate && state.toDate) {
      // Parse dd-mm-yyyy to Date objects
      const parseDMY = (str) => {
        const [d, m, y] = str.split('-').map(Number);
        return new Date(y, m - 1, d);
      };
      fp.setDate([parseDMY(state.fromDate), parseDMY(state.toDate)], false);
    }
  }
}

function serializeFilterForm(form) {
  const formData = new FormData(form);
  // Exclude metadata fields that don't represent filter state
  formData.delete('action');
  formData.delete('overwriteView');
  formData.delete('view');
  formData.delete('subscribeToken');
  return new URLSearchParams(formData).toString();
}

function refreshViewTable(form) {
  const viewId = document.getElementById('selectedViewId');
  if (!viewId || !viewId.value) return;

  const formData = new FormData(form);

  // Include current sort state from the active table
  const activePanel = document.getElementById('view-' + viewId.value);
  if (activePanel) {
    const table = activePanel.querySelector('table');
    if (table) {
      formData.set('sortBy', table.dataset.sortBy || 'priority');
      formData.set(
        'sortDirection',
        (table.dataset.sortDir || 'asc').toUpperCase()
      );
    }
  }

  // Always start at page 1 — refreshViewTable is called for filter, sort, and
  // lazy-load events, all of which should reset pagination.
  formData.set('page', '1');

  // Cancel any in-flight refresh or lazy-load on this panel so a slow
  // earlier response can't overwrite fresh data.
  if (activePanel) {
    teardownLazyLoad(activePanel);
    if (activePanel._refreshController) {
      activePanel._refreshController.abort();
    }
  }
  const controller = new AbortController();
  if (activePanel) activePanel._refreshController = controller;

  fetch(
    '/ProjectOverview/ProjectOverview/loadViewTable/' +
      encodeURIComponent(viewId.value),
    {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams(formData),
      signal: controller.signal,
    }
  )
    .then(function (response) {
      if (!response.ok) {
        return response.text().then(function (body) {
          const err = new Error('HTTP ' + response.status);
          err.responseBody = body;
          throw err;
        });
      }
      return response.text();
    })
    .then(function (html) {
      if (controller.signal.aborted) return;
      if (!activePanel) return;
      activePanel.innerHTML = html;
      // Re-init components inside the new table
      initTagsSelects();
      if (typeof tippy === 'function') {
        tippy(activePanel.querySelectorAll('[data-tippy-content]'));
      }
      attachLazyLoad(activePanel);
    })
    .catch(function (err) {
      if (err.name === 'AbortError') return;
      console.error(
        '[ProjectOverview] View load failed:',
        err,
        err.responseBody || ''
      );
      if (activePanel) {
        const msg =
          (window.projectOverviewI18n && window.projectOverviewI18n.couldNotLoadView) ||
          '[i18n missing] could_not_load_view';
        const errEl = document.createElement('div');
        errEl.className = 'lazy-row-status lazy-row-error';
        errEl.style.padding = '24px';

        const icon = document.createElement('span');
        icon.className = 'lazy-row-status-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = '⚠';

        const text = document.createElement('span');
        text.className = 'lazy-row-status-text';
        text.textContent = msg;

        errEl.append(icon, text);
        activePanel.replaceChildren(errEl);
      }
    })
    .finally(function () {
      if (activePanel && activePanel._refreshController === controller) {
        activePanel._refreshController = null;
      }
    });
}

/**
 * Wire the manual "Load more" + Retry buttons inside the sentinel for `panel`.
 * Idempotent — clones the buttons before re-binding so we never stack
 * listeners across repeated calls (e.g., after a splice).
 *
 * @param {HTMLElement} panel
 */
function attachLazyLoad(panel) {
  if (!panel) return;
  teardownLazyLoad(panel);

  const sentinel = panel.querySelector('.lazy-row-sentinel');
  if (!sentinel) return;

  bindSentinelButtons(panel, sentinel);
}

/**
 * Cancel any in-flight lazy-load fetch on `panel`. Safe to call before or
 * after the panel's DOM has been replaced.
 *
 * @param {HTMLElement} panel
 */
function teardownLazyLoad(panel) {
  if (!panel) return;
  if (panel._lazyController) {
    panel._lazyController.abort();
    panel._lazyController = null;
  }
}

function bindSentinelButtons(panel, sentinel) {
  const loadBtn = sentinel.querySelector('.lazy-row-load-more');
  if (loadBtn) {
    loadBtn.addEventListener('click', function () {
      loadNextLazyPage(panel, sentinel);
    });
  }
  const retryBtn = sentinel.querySelector('.lazy-row-retry');
  if (retryBtn) {
    retryBtn.addEventListener('click', function () {
      loadNextLazyPage(panel, sentinel);
    });
  }
}

/**
 * Fetch the next page for `panel`'s sentinel and splice the response in.
 *
 * @param {HTMLElement} panel
 * @param {HTMLTableRowElement} sentinel
 */
function loadNextLazyPage(panel, sentinel) {
  if (sentinel.dataset.state === 'loading') return;
  const url = sentinel.dataset.nextUrl;
  const page = sentinel.dataset.nextPage;
  if (!url || !page) return;

  sentinel.dataset.state = 'loading';
  showLazyLoading(sentinel);

  const form = document.getElementById('filtersForm');
  const formData = form ? new FormData(form) : new FormData();

  const table = panel.querySelector('table');
  if (table) {
    formData.set('sortBy', table.dataset.sortBy || 'priority');
    formData.set(
      'sortDirection',
      (table.dataset.sortDir || 'asc').toUpperCase()
    );
  }
  formData.set('page', page);

  // Cancel any in-flight lazy-load fetch on this panel before issuing a new one.
  if (panel._lazyController) {
    panel._lazyController.abort();
  }
  const controller = new AbortController();
  panel._lazyController = controller;

  fetch(url, {
    method: 'POST',
    credentials: 'include',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams(formData),
    signal: controller.signal,
  })
    .then(function (response) {
      if (!response.ok) {
        // Read the body so the message isn't a useless "HTTP 500"
        return response.text().then(function (body) {
          const err = new Error('HTTP ' + response.status);
          err.responseBody = body;
          throw err;
        });
      }
      return response.text();
    })
    .then(function (html) {
      if (controller.signal.aborted) return;
      if (!sentinel.isConnected) return;

      // Use DOMParser for robust fragment parsing — handles whitespace,
      // partial markup, and edge cases more reliably than innerHTML on tbody.
      const doc = new DOMParser().parseFromString(
        '<table><tbody>' + (html || '') + '</tbody></table>',
        'text/html'
      );
      const parsedTbody = doc.querySelector('tbody');
      const newRows = parsedTbody ? Array.from(parsedTbody.children) : [];

      const parent = sentinel.parentNode;
      if (!parent) return;

      try {
        // Splice in the new rows, then drop the spent sentinel.
        for (const row of newRows) {
          parent.insertBefore(row, sentinel);
        }
        sentinel.remove();

        // Initialize TomSelect on any tag-selects we just added. We do this
        // explicitly per-row in addition to the global initTagsSelects() call
        // so newly-inserted selects get wired even if the global pass misses
        // them for any reason.
        for (const row of newRows) {
          if (row.querySelectorAll) {
            row.querySelectorAll('.ticket-tags-select').forEach(function (sel) {
              if (!sel.tomselect) initSingleTagSelect(sel);
            });
          }
        }
        // And cover any holdouts via the global pass.
        initTagsSelects();

        if (typeof tippy === 'function') {
          tippy(
            panel.querySelectorAll('[data-tippy-content]:not([data-tippy-instance])')
          );
        }

        // Re-attach to the new sentinel (no-op when this was the last page).
        attachLazyLoad(panel);
      } catch (spliceErr) {
        console.error('[ProjectOverview] Lazy-load splice failed:', spliceErr);
        const live = panel.querySelector('.lazy-row-sentinel');
        const target = live && live.isConnected ? live : sentinel;
        if (target && target.isConnected) {
          target.dataset.state = 'error';
          showLazyError(
            target,
            (window.projectOverviewI18n && window.projectOverviewI18n.failedToInsertRows) ||
              '[i18n missing] failed_to_insert_rows'
          );
        }
      }
    })
    .catch(function (err) {
      if (err.name === 'AbortError') return;
      console.error('[ProjectOverview] Lazy-load failed:', err, err.responseBody || '');
      // Make sure the sentinel is in a clickable error state, even if
      // something downstream blew up before we got there.
      const live = panel.querySelector('.lazy-row-sentinel');
      const target = live || sentinel;
      if (target && target.isConnected) {
        target.dataset.state = 'error';
        showLazyError(
          target,
          (window.projectOverviewI18n && window.projectOverviewI18n.couldNotLoadMoreRows) ||
            '[i18n missing] could_not_load_more_rows'
        );
      }
    })
    .finally(function () {
      if (panel._lazyController === controller) {
        panel._lazyController = null;
      }
    });
}

function setSentinelState(sentinel, visible) {
  ['ready', 'loading', 'error'].forEach(function (key) {
    const node = sentinel.querySelector('.lazy-row-' + key);
    if (!node) return;
    if (key === visible) node.removeAttribute('hidden');
    else node.setAttribute('hidden', '');
  });
}

function showLazyLoading(sentinel) {
  setSentinelState(sentinel, 'loading');
}

function showLazyError(sentinel, message) {
  setSentinelState(sentinel, 'error');
  const error = sentinel.querySelector('.lazy-row-error');
  const text = error ? error.querySelector('.lazy-row-status-text') : null;
  if (text && message) text.textContent = message;
  // After error, allow another click to retry.
  sentinel.dataset.state = 'ready';
}

function toggleUnsavedIndicator(targetViewId, hasChanges) {
  if (!targetViewId) return;

  // Track which views have unsaved changes
  if (!window._viewsWithUnsavedChanges) window._viewsWithUnsavedChanges = {};
  window._viewsWithUnsavedChanges[targetViewId] = hasChanges;

  // Clear cached form data when changes are reverted
  if (!hasChanges && window._viewCachedFormData) {
    delete window._viewCachedFormData[targetViewId];
  }

  const tab = document.querySelector(
    '#projectOverviewTabs > ul > li[data-target="' + targetViewId + '"]'
  );
  if (tab) {
    tab.classList.toggle('has-unsaved-changes', hasChanges);
  }

  // Show save button highlight and banner if the currently active view has unsaved changes
  const activeViewId = document.getElementById('selectedViewId');
  const saveBtn = document.querySelector('.save-view-btn');
  const banner = document.getElementById('unsavedChangesNotice');
  if (activeViewId) {
    const activeHasChanges =
      !!window._viewsWithUnsavedChanges[activeViewId.value];
    if (saveBtn) {
      saveBtn.classList.toggle('has-unsaved-changes', activeHasChanges);
    }
    if (banner) {
      banner.style.display = activeHasChanges ? '' : 'none';
    }
  }
}

// Save success animation
function saveSuccess(elem) {
  elem.addClass('save-success');

  setTimeout(() => {
    elem.removeClass('save-success');
  }, 1000);
}

// Save error animation
function saveError(elem) {
  elem.addClass('save-error');

  setTimeout(() => {
    elem.removeClass('save-error');
  }, 1000);
}

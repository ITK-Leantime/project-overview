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
  initProjectOverviewFilters();
  initProjectOverviewTable();

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
    }
  });
  // end HTMX swap events
});

/**
 * Initializes the project overview filters by setting up various UI components.
 *
 * @return {void} This function does not return a value.
 */
function initProjectOverviewFilters() {
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

  // --- Live filter update: refresh table on filter change ---
  const filtersForm = document.getElementById('filtersForm');
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
      .find('input[name="viewName"]')
      .val(currentName)
      .end()
      .find('input[name="view"]')
      .val(viewId);

    // Hide rename controls for subscribed views
    contextMenu
      .find('> form > span, > form > input[name="viewName"], .view-rename')
      .toggle(!isSubscription);

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

        // Sync save button state with the newly active view
        const saveBtn = document.querySelector('.save-view-btn');
        if (saveBtn && window._viewsWithUnsavedChanges) {
          saveBtn.classList.toggle(
            'has-unsaved-changes',
            !!window._viewsWithUnsavedChanges[viewId]
          );
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

  // Init share view button click (share as subscription)
  $(document).on('click', 'button.copy-live-share-button', function (e) {
    e.preventDefault();
    copyShareLink($(this));
  });
}

/**
 * Generate a share link and copy it to clipboard.
 *
 * @param {jQuery} button The button element that was clicked.
 */
function copyShareLink(button) {
  const originalText = button.data('original') || button.text();
  const viewId = $('#selectedViewId').val();

  // Request share link from server
  $.ajax({
    type: 'POST',
    url: '/ProjectOverview/ProjectOverview/generateShareLink',
    data: {
      view: viewId,
    },
    dataType: 'json',
    success: function (response) {
      if (response.success && response.shareToken) {
        const shareUrl =
          window.location.origin +
          '/ProjectOverview/ProjectOverview?subscribe=' +
          response.shareToken;

        // Copy to clipboard
        navigator.clipboard
          .writeText(shareUrl)
          .then(function () {
            button.data('original', originalText);
            button.text('✓');
            setTimeout(function () {
              button.text(originalText);
            }, 2000);
          })
          .catch(function (err) {
            console.error('Failed to copy to clipboard:', err);
            button.text('Failed');
            setTimeout(function () {
              button.text(originalText);
            }, 2000);
          });
      } else {
        console.error('Failed to generate share link:', response);
        button.text('Error');
        setTimeout(function () {
          button.text(originalText);
        }, 2000);
      }
    },
    error: function (xhr, status, error) {
      console.error('AJAX error:', error);
      button.text('Error');
      setTimeout(function () {
        button.text(originalText);
      }, 2000);
    },
  });
}

function initTagsSelects() {
  const allTags = window.allTags || [];

  // Loop tags select and init Tomselect
  document.querySelectorAll('.ticket-tags-select').forEach((selectElement) => {
    if (selectElement.tomselect) {
      return;
    }

    const ticketId = selectElement.dataset.ticketId;

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

        // Use allTags array bound to window in template
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
  });
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

  const tbody = table.querySelector('tbody');
  const headers = Array.from(table.querySelectorAll('thead th'));
  const rows = Array.from(tbody.querySelectorAll('tr'));
  if (!rows.length) return;

  // Find column index from the clicked header
  const colIndex = headers.indexOf(clickedTh);
  if (colIndex < 0) return;

  // Toggle direction
  const currentCol = table.dataset.sortBy;
  const currentDir = table.dataset.sortDir;
  let direction = 'asc';
  if (currentCol === sortBy && currentDir === 'asc') direction = 'desc';
  table.dataset.sortBy = sortBy;
  table.dataset.sortDir = direction;

  const numericCols = [
    'planHours',
    'hourRemaining',
    'sumHours',
    'milestoneid',
    'priority',
    'status',
  ];
  const dateCols = ['dateToFinish'];

  rows.sort(function (a, b) {
    const aCell = a.cells[colIndex];
    const bCell = b.cells[colIndex];
    let aVal, bVal;

    if (dateCols.includes(sortBy)) {
      aVal = (aCell.querySelector('input[type=date]') || {}).value || '';
      bVal = (bCell.querySelector('input[type=date]') || {}).value || '';
    } else if (numericCols.includes(sortBy)) {
      aVal = parseFloat(getCellNumericValue(aCell)) || 0;
      bVal = parseFloat(getCellNumericValue(bCell)) || 0;
    } else {
      aVal = getCellTextValue(aCell).trim().toLowerCase();
      bVal = getCellTextValue(bCell).trim().toLowerCase();
    }

    let result;
    result = aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
    return direction === 'desc' ? -result : result;
  });

  rows.forEach(function (row) {
    tbody.appendChild(row);
  });

  // Update visual indicators
  headers.forEach(function (th) {
    th.classList.remove('sort-asc', 'sort-desc');
  });
  clickedTh.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');

  // Silent save
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
}

function getCellNumericValue(cell) {
  if (cell.dataset.sortValue !== undefined) return cell.dataset.sortValue;
  const input = cell.querySelector('input[type=number]');
  if (input) return input.value;
  const span = cell.querySelector('.logged-hours');
  if (span) return span.textContent;
  const select = cell.querySelector('select');
  if (select) return select.value;
  return cell.textContent;
}

function getCellTextValue(cell) {
  if (cell.dataset.selectedName) return cell.dataset.selectedName;
  const label = cell.querySelector('#status-label, #priority-label');
  if (label) return label.textContent;
  const link = cell.querySelector('a');
  if (link) return link.textContent;
  return cell.textContent;
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

  fetch(
    '/ProjectOverview/ProjectOverview/loadViewTable/' +
      encodeURIComponent(viewId.value),
    {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams(formData),
    }
  )
    .then(function (response) {
      return response.text();
    })
    .then(function (html) {
      if (activePanel) {
        activePanel.innerHTML = html;
        // Re-init components inside the new table
        initTagsSelects();
        if (typeof tippy === 'function') {
          tippy(activePanel.querySelectorAll('[data-tippy-content]'));
        }
      }
    });
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

  // Show save button highlight if the currently active view has unsaved changes
  const activeViewId = document.getElementById('selectedViewId');
  const saveBtn = document.querySelector('.save-view-btn');
  if (saveBtn && activeViewId) {
    const activeHasChanges =
      !!window._viewsWithUnsavedChanges[activeViewId.value];
    saveBtn.classList.toggle('has-unsaved-changes', activeHasChanges);
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

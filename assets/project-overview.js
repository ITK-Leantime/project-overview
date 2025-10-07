import 'select2';
import 'select2/dist/css/select2.css';
import flatpickr from 'flatpickr';
import { Danish } from 'flatpickr/dist/l10n/da.js';
import 'flatpickr/dist/flatpickr.min.css';

$(document).ready(function () {
  initProjectOverviewFilters();
  initProjectOverviewTable();

  document.body.addEventListener('htmx:beforeSettle', (e) => {
    e.detail.target.style.visibility = 'hidden';
  });
  document.addEventListener('htmx:afterSettle', function (e) {
    e.detail.target.style.visibility = '';
    if (e.target.id === 'filtersContainer') {
      initProjectOverviewFilters();
    }
  });
});

function initProjectOverviewFilters() {
  // Flatpickr
  flatpickr('#dateRange', {
    mode: 'range',
    dateFormat: 'd-m-Y',
    allowInput: false,
    readonly: false,
    weekNumbers: true,
    locale: Danish,
  });

  // Common select2 config
  const commonConfig = {
    closeOnSelect: false,
  };

  // Filter select2
  const filterSelect = $('#filterSelect')
    .select2({
      ...commonConfig,
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

  // Columns select2
  const columnSelect = $('#columnSelect')
    .select2(commonConfig)
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

  // Assignee select2
  const userSelect = $('#userSelect')
    .select2({
      ...commonConfig,
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
}

function initProjectOverviewTable() {
  const contextMenu = $('#view-context-menu');

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

  // Sorting
  $(document).on('click', '[id^=sort_]', function () {
    changeSortBy(this.id.replace('sort_', ''));
  });

  // Ticket field changes
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

  $(document).on('change', '[id^=tags-]', function () {
    const idArg = this.id.split('-')[1];
    changeTags(event, idArg, this.value);
  });

  // Tab context menu
  $(document).on('click', 'span.tab-context-menu', ({ target }) => {
    const rect = target.getBoundingClientRect();
    const viewId = $(target).parent().data('target');
    $('.settings-for-target').text(viewId);
    contextMenu
      .css({
        left: `${rect.left + window.scrollX - 215}px`,
        top: `${rect.top + window.scrollY + rect.height - 50}px`,
      })
      .addClass('shown')
      .find('input[name="viewId"]')
      .val(viewId);
  });

  $(document).on('click', '#projectOverviewTabs > ul > li', ({ target }) => {
    if (!$(target).closest('span.tab-context-menu').length) {
      contextMenu.removeClass('shown');
    }
  });
  $(document).on('click', '.select2-results__group', ({ target }) => {
    $(target).toggleClass('show-all-projects');
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
        jQuery(`#status-ticket-${ticketId}`)
          .removeClass()
          .addClass(`table-button ${newClass}`);
        jQuery(`#status-ticket-${ticketId} #status-label`).text(newLabel);
      });
  }
}

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
        jQuery(`#priority-ticket-${ticketId}`)
          .removeClass()
          .addClass(`table-button priority-bg-${newPriorityId}`);
        jQuery(`#priority-ticket-${ticketId} #priority-label`).text(newLabel);
      });
  }
}

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

function changeTags(event, ticketId, newTags) {
  const parentElement = jQuery(event.target).closest('td');

  if (newTags && ticketId) {
    jQuery
      .ajax({
        type: 'PATCH',
        url: leantime.appUrl + '/api/tickets',
        data: {
          id: ticketId,
          tags: newTags,
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

function redirectWithUserId(searchUserIds) {
  if (Array.isArray(searchUserIds)) {
    searchUserIds = searchUserIds.join(',');
  }
  searchUserIds === 'all'
    ? updateLocation('userIds', '')
    : updateLocation('userIds', searchUserIds);
}

function redirectWithSearchTerm(searchTerm) {
  searchTerm === 'all'
    ? updateLocation('searchTerm', '')
    : updateLocation('searchTerm', searchTerm);
}

function changeDateFrom(dateFrom) {
  dateFrom === ''
    ? updateLocation('dateFrom', '')
    : updateLocation('dateFrom', formatDate(dateFrom));
}

function changeTicketsWithoutDueDateIncluded(checked) {
  checked
    ? updateLocation('noDueDate', true)
    : updateLocation('noDueDate', false);
}

function changeSortBy(sortBy) {
  const params = new URLSearchParams(document.location.search);
  const url = new URL(window.location.href);
  const currentSortOrder = params.get('sortOrder');
  const currentSortBy = params.get('sortBy');

  if (currentSortBy === sortBy) {
    const newSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
    url.searchParams.set('sortOrder', newSortOrder);
  } else {
    url.searchParams.set('sortOrder', 'asc');
    url.searchParams.set('sortBy', sortBy);
  }

  window.location.assign(url);
}

function changeOverdueTickets(checked) {
  checked
    ? updateLocation('overdueTickets', true)
    : updateLocation('overdueTickets', false);
}

function changeDateTo(dateTo) {
  dateTo === ''
    ? updateLocation('dateTo', '')
    : updateLocation('dateTo', formatDate(dateTo));
}

function updateLocation(key, value) {
  let params = new URLSearchParams(document.location.search);
  let url = new URL(window.location.href);

  if (params.has(key)) {
    url.searchParams.delete(key);
  }

  if (value !== '' && typeof value === 'string' && value.includes(',')) {
    url.searchParams.set(key, value.split(','));
  }

  if (value !== '') {
    url.searchParams.set(key, value);
  }

  window.location.assign(url);
}

function saveSuccess(elem) {
  elem.addClass('save-success');

  setTimeout(() => {
    elem.removeClass('save-success');
  }, 1000);
}

function saveError(elem) {
  elem.addClass('save-error');

  setTimeout(() => {
    elem.removeClass('save-error');
  }, 1000);
}

function formatDate(date) {
  const localDate = new Date(date);
  const yyyy = localDate.getFullYear();
  let mm = localDate.getMonth() + 1; // Months start at 0!
  let dd = localDate.getDate();

  dd = dd < 10 ? `0${dd}` : dd;
  mm = mm < 10 ? `0${mm}` : mm;

  return dd + '/' + mm + '/' + yyyy;
}

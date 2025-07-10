import 'select2';
import 'select2/dist/css/select2.css';
import flatpickr from 'flatpickr';
import { Danish } from 'flatpickr/dist/l10n/da.js';
import 'flatpickr/dist/flatpickr.min.css';

$(document).ready(function () {
  const table = document.getElementById('sortable-table');

  flatpickr('#dateRange', {
    mode: 'range',
    dateFormat: 'd-m-Y',
    allowInput: false,
    readonly: false,
    weekNumbers: true,
    locale: Danish,
    onChange: function (selectedDates, dateStr, instance) {
      if (selectedDates && selectedDates.length === 2) {
        instance.element.form.submit();
      }
    },
  });

  $.fn.select2.amd.require(['select2/selection/search'], function (Search) {
    const oldRemoveChoice = Search.prototype.searchRemoveChoice;

    Search.prototype.searchRemoveChoice = function () {
      oldRemoveChoice.apply(this, arguments);
      $(select2).select2('close');
    };

    const select2 = $('.project-overview-assignee-select')
      .select2({
        closeOnSelect: true,
        tags: false,
        matcher: function (params, data) {
          if ($.trim(params.term) === '') {
            return data;
          }

          if (!params.term) {
            return data;
          }

          const keywords = params.term.split(' ');
          const text = data.text.toUpperCase();

          for (const keyword of keywords) {
            if (text.indexOf(keyword.toUpperCase()) === -1) {
              return null;
            }
          }
          return data;
        },
      })
      .on('select2:unselect', function (e) {
        let self = this;

        // after a delay, refresh the select2
        setTimeout(function () {
          $(self)
            .data('select2')
            .$container.find('.select2-search__field')
            .val('');
        }, 100);
      });

    select2
      .data('select2')
      .$container.find('.select2-search__field')
      .on('keydown', function () {
        setTimeout(function () {
          let select2Results = $(
            '.select2-results__option:not(.select2-results__option--selected)'
          );
          if (select2Results.length === 1) {
            select2Results.trigger('mouseenter');
          }
        }, 100);
      });

    // Assign event handlers to dynamic elements
    $(document).on('click', '.dropdown-item .table-button.status', function () {
      const idArgs = $(this).data('args').split(',');
      changeStatus(idArgs[0], idArgs[1], idArgs[2], idArgs[3]);
    });

    $(document).on(
      'click',
      '.dropdown-item .table-button.priority',
      function () {
        const idArgs = $(this).data('args').split(',');
        changePriority(idArgs[0], idArgs[1], idArgs[2]);
      }
    );
    $(document).on('click', '[id^=sort_]', function () {
      changeSortBy(this.id.replace('sort_', ''));
    });
    $(document).on('change', '[id^=due-date-]', function () {
      const date = $(this).val();
      const ticketId = $(this).data('ticketid');
      changeDueDate(event, ticketId, date);
    });

    $(document).on('change', '[id^=assigned-user-]', function () {
      const idArg = $(this).attr('id').split('-')[2];
      changeAssignedUser(event, idArg, this.value);
    });

    $(document).on('change', '[id^=plan-hours-]', function () {
      const idArg = $(this).attr('id').split('-')[2];
      changePlanHours(event, idArg, this.value);
    });

    $(document).on('change', '[id^=remaining-hours-]', function () {
      const idArg = $(this).attr('id').split('-')[2];
      changeHoursRemaining(event, idArg, this.value);
    });

    $(document).on('change', '[id^=milestone-select-]', function () {
      const idArg = $(this).attr('id').split('-')[2];
      changeMilestone(event, idArg, this.value);
    });

    $(document).on('change', '[id^=tags-]', function () {
      const idArg = $(this).attr('id').split('-')[1];
      changeTags(event, idArg, this.value);
    });

    // Event handlers for static elements
    $('#search-term').on('change', function () {
      redirectWithSearchTerm(this.value);
    });
    $('#overdue-tickets').on('change', function () {
      changeOverdueTickets(this.checked);
    });

    $('#empty-due-date').on('change', function () {
      changeTicketsWithoutDueDateIncluded(this.checked);
    });

    $('#user-filter').on('change', function () {
      let values = $(select2).select2('data');
      let ids = values.map(function (item) {
        return item.id;
      });
      redirectWithUserId(ids);
    });

    $('#date-from').on('change', function () {
      changeDateFrom(this.value);
    });

    $('#date-to').on('focusout', function () {
      changeDateTo(this.value);
    });
    const userSelecthasSelectedValues =
      select2.val() && select2.val().length > 0;
    if (!userSelecthasSelectedValues) {
      $(document)
        .find('select.project-overview-assignee-select')
        .select2('open');
    } else {
      $(document).find('select.project-overview-assignee-select').focus();
    }
  });
});

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

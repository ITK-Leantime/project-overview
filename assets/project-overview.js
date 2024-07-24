function changeStatus(ticketId, newStatusId, newClass, newLabel) {
  if (newStatusId && ticketId) {
    jQuery
      .ajax({
        type: "PATCH",
        url: leantime.appUrl + "/api/tickets",
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
        jQuery(`#status-ticket-${ticketId}`).removeClass().addClass(`table-button ${newClass}`);
        jQuery(`#status-ticket-${ticketId} #status-label`).text(newLabel);
      });
  }
}
function changePriority(ticketId, newPriorityId, newLabel) {
  if (newPriorityId && ticketId) {
    jQuery
      .ajax({
        type: "PATCH",
        url: leantime.appUrl + "/api/tickets",
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
        jQuery(`#priority-ticket-${ticketId}`).removeClass().addClass(`table-button priority-bg-${newPriorityId}`);
        jQuery(`#priority-ticket-${ticketId} #priority-label`).text(newLabel);
      });
  }
}

function changeDueDate(ticketId, newDueDate) {
  if (newDueDate && ticketId) {
    const dueDate = jQuery.datepicker.formatDate(leantime.dateHelper.getFormatFromSettings("dateformat", "jquery"), new Date(newDueDate));
    jQuery
      .ajax({
        type: "PATCH",
        url: leantime.appUrl + "/api/tickets",
        data: {
          id: ticketId,
          dateToFinish: dueDate,
        },
      });
  }
}

function changeAssignedUser(ticketId, userId){
  if (userId && ticketId) {
    jQuery
      .ajax({
        type: "PATCH",
        url: leantime.appUrl + "/api/tickets",
        data: {
          id: ticketId,
          editorId: userId,
        },
      });
  }
}

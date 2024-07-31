function changeStatus(ticketId, newStatusId, newClass, newLabel) {
  if (newStatusId !== undefined && ticketId) {
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
        jQuery(`#priority-ticket-${ticketId}`)
          .removeClass()
          .addClass(`table-button priority-bg-${newPriorityId}`);
        jQuery(`#priority-ticket-${ticketId} #priority-label`).text(newLabel);
      });
  }
}

function changeDueDate(ticketId, newDueDate) {
  if (newDueDate && ticketId) {
    const dueDate = jQuery.datepicker.formatDate(
      leantime.dateHelper.getFormatFromSettings("dateformat", "jquery"),
      new Date(newDueDate),
    );
    jQuery.ajax({
      type: "PATCH",
      url: leantime.appUrl + "/api/tickets",
      data: {
        id: ticketId,
        dateToFinish: dueDate,
      },
    });
  }
}

function changeAssignedUser(ticketId, userId) {
  if (userId && ticketId) {
    jQuery.ajax({
      type: "PATCH",
      url: leantime.appUrl + "/api/tickets",
      data: {
        id: ticketId,
        editorId: userId,
      },
    });
  }
}

function changePlanHours(ticketId, newPlanHours) {
  if (newPlanHours && ticketId) {
    jQuery.ajax({
      type: "PATCH",
      url: leantime.appUrl + "/api/tickets",
      data: {
        id: ticketId,
        planHours: newPlanHours,
      },
    });
  }
}

function changeHoursRemaining(ticketId, newHoursRemaining) {
  if (newHoursRemaining && ticketId) {
    jQuery.ajax({
      type: "PATCH",
      url: leantime.appUrl + "/api/tickets",
      data: {
        id: ticketId,
        hourRemaining: newHoursRemaining,
      },
    });
  }
}

function changeMilestone(ticketId, newMilestoneId) {
  if (newMilestoneId && ticketId) {
    jQuery
      .ajax({
        type: "PATCH",
        url: leantime.appUrl + "/api/tickets",
        data: {
          id: ticketId,
          milestoneid: newMilestoneId,
        },
      })
      .done(() => {
        // In this way, the UI does not reflect the actual data, which is not good.
        // But if I instead create a get-request it returns 200 and an otherwise empty
        // response. So this is what I chose to do, and is also what is done in
        // in other places (I am looking at you ticketcontroller.js).
        const hexColorRegExp = new RegExp("^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$");
        const newMilestoneColor = jQuery(
          `#milestone-option-${newMilestoneId}`,
        ).attr("data-color");
        const isItAHexColor = hexColorRegExp.exec(newMilestoneColor);
        if (isItAHexColor) {
          jQuery(`#milestone-select`).css("background", newMilestoneColor);
        } else {
          jQuery(`#milestone-select`).css("background", "transparent");
        }
      });
  }
}

function changeTags(ticketId, newTags) {
  if (newTags && ticketId) {
    jQuery.ajax({
      type: "PATCH",
      url: leantime.appUrl + "/api/tickets",
      data: {
        id: ticketId,
        tags: newTags,
      },
    });
  }
}

function redirectWithUserId(searchUserId) {
  searchUserId === "all"
    ? updateLocation("userId", "")
    : updateLocation("userId", searchUserId);
}

function redirectWithSearchTerm(searchTerm) {
  searchTerm === "all"
    ? updateLocation("searchTerm", "")
    : updateLocation("searchTerm", searchTerm);
}

function changeDateFrom(dateFrom) {
  dateFrom === ""
    ? updateLocation("dateFrom", "")
    : updateLocation("dateFrom", new Date(dateFrom).toLocaleDateString());
}

function changeDateTo(dateTo) {
  dateTo === ""
    ? updateLocation("dateTo", "")
    : updateLocation("dateTo", new Date(dateTo).toLocaleDateString());
}

function updateLocation(key, value) {
  let params = new URLSearchParams(document.location.search);
  if (params.has(key)) {
    params.delete(key);
  }
  if (value !== "") {
    params.append(key, value);
  }
  window.location = `?${params.toString()}`;
}

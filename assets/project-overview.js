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
        jQuery(`#status-ticket-${ticketId}`).removeClass().addClass(newClass);
        jQuery(`#status-ticket-${ticketId}`).text(newLabel);
      });
  }
}

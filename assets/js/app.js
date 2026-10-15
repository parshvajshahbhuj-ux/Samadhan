$(document).ready(function () {


    let notificationTicketId = new URLSearchParams(window.location.search).get("open_ticket");
    let openedNotificationTicket = false;
    let supportInquiryId = new URLSearchParams(window.location.search).get("open_inquiry");


    console.log("APP.JS LOADED");





    // =========================================================

    // ESCAPE HTML

    // =========================================================



    function escapeHtml(value) {



        if (value === null || value === undefined) {

            return "";

        }

        return String(value)

            .replace(/&/g, "&amp;")

            .replace(/</g, "&lt;")

            .replace(/>/g, "&gt;")

            .replace(/"/g, "&quot;")

            .replace(/'/g, "&#039;");

    }

    function formatTicketTimelineDate(value) {
        const timestamp = new Date(String(value || "").replace(" ", "T"));
        return Number.isNaN(timestamp.getTime())
            ? String(value || "")
            : timestamp.toLocaleString([], {
                year: "numeric",
                month: "short",
                day: "numeric",
                hour: "numeric",
                minute: "2-digit"
            });
    }

    function loadTicketTimeline(ticketId) {
        const timeline = $("#ticketTimeline");
        timeline.html('<p class="ticket-timeline-message">Loading ticket progress...</p>');

        $.ajax({
            url: "api/ticket_timeline.php",
            method: "GET",
            dataType: "json",
            data: { ticket_id: ticketId },
            cache: false
        }).done(function (response) {
            if (!response || !response.success || !Array.isArray(response.data)) {
                timeline.html('<p class="ticket-timeline-message ticket-timeline-error">Unable to load ticket progress.</p>');
                return;
            }

            if (!response.data.length) {
                timeline.html('<p class="ticket-timeline-message">No progress updates are available yet.</p>');
                return;
            }

            const events = response.data.map(function (event) {
                const detail = event.detail
                    ? `<p class="ticket-timeline-detail">${escapeHtml(event.detail).replace(/\r?\n/g, "<br>")}</p>`
                    : "";
                return `<li class="ticket-timeline-event is-${escapeHtml(event.type)}">
                    <span class="ticket-timeline-marker" aria-hidden="true"></span>
                    <div class="ticket-timeline-event-content">
                        <div class="ticket-timeline-event-heading">
                            <strong>${escapeHtml(event.title)}</strong>
                            <time>${escapeHtml(formatTicketTimelineDate(event.created_at))}</time>
                        </div>
                        <span class="ticket-timeline-actor">${escapeHtml(event.actor)}</span>
                        ${detail}
                    </div>
                </li>`;
            }).join("");

            timeline.html(`<ol class="ticket-timeline-list">${events}</ol>`);
        }).fail(function (xhr) {
            const response = xhr.responseJSON;
            const message = response && response.message
                ? response.message
                : "Unable to load ticket progress. Please try again.";
            timeline.html(`<p class="ticket-timeline-message ticket-timeline-error">${escapeHtml(message)}</p>`);
        });
    }

    function loadTicketMessages(ticketId) {
        const list = $("#ticketMessagesList");
        if (!list.length) {
            return;
        }

        list.html('<p class="ticket-message-state">Loading conversation...</p>');
        $.ajax({
            url: "api/ticket_messages.php",
            method: "GET",
            dataType: "json",
            data: { ticket_id: ticketId },
            cache: false
        }).done(function (response) {
            if (!response || !response.success || !Array.isArray(response.data)) {
                list.html(`<p class="ticket-message-state is-error">${escapeHtml(response && response.message ? response.message : "Unable to load this conversation.")}</p>`);
                return;
            }
            if (!response.data.length) {
                list.html('<p class="ticket-message-state">No replies yet. Send the first update below.</p>');
                return;
            }

            const roleLabels = {
                admin: "Main Admin",
                support: "Support Member",
                user: "Customer"
            };
            const messages = response.data.map(function (message) {
                const role = String(message.user_role || "").toLowerCase();
                const roleClass = Object.prototype.hasOwnProperty.call(roleLabels, role) ? role : "user";
                const roleLabel = roleLabels[roleClass];
                return `<article class="ticket-message-item is-${roleClass}">
                    <div class="ticket-message-meta">
                        <strong>${escapeHtml(message.user_name || roleLabel)}</strong>
                        <span>${roleLabel}</span>
                        <time>${escapeHtml(formatTicketTimelineDate(message.created_at))}</time>
                    </div>
                    <p>${escapeHtml(message.message).replace(/\r?\n/g, "<br>")}</p>
                </article>`;
            }).join("");
            list.html(messages);
            list.scrollTop(list[0].scrollHeight);
        }).fail(function (xhr) {
            const response = xhr.responseJSON;
            const message = response && response.message
                ? response.message
                : "Unable to load this conversation. Please try again.";
            list.html(`<p class="ticket-message-state is-error">${escapeHtml(message)}</p>`);
        });
    }

    $(document).on("submit", ".ticket-message-form", function (event) {
        event.preventDefault();
        const form = $(this);
        const textarea = form.find("textarea[name='message']");
        const button = form.find("button[type='submit']");
        const status = form.find(".ticket-message-status");
        const ticketId = Number(form.attr("data-ticket-id"));
        const message = String(textarea.val() || "").trim();

        status.removeClass("is-error").text("");
        if (!message || !ticketId) {
            status.addClass("is-error").text("Enter a reply before sending.");
            return;
        }

        button.prop("disabled", true).text("Sending...");
        $.ajax({
            url: "api/ticket_messages.php",
            method: "POST",
            dataType: "json",
            data: {
                ticket_id: ticketId,
                message: message,
                csrf_token: window.ticketMessagesCsrf || ""
            }
        }).done(function (response) {
            if (!response || !response.success) {
                status.addClass("is-error").text(response && response.message
                    ? response.message
                    : "Your reply could not be sent. Please try again.");
                return;
            }

            textarea.val("");
            status.text(response.message || "Reply sent.");
            loadTicketMessages(ticketId);
            loadTicketTimeline(ticketId);
        }).fail(function (xhr) {
            const response = xhr.responseJSON;
            status.addClass("is-error").text(response && response.message
                ? response.message
                : "Your reply could not be sent. Please try again.");
        }).always(function () {
            button.prop("disabled", false).text("Send reply");
        });
    });

    $(document).on("submit", ".ticket-reopen-form", function (event) {
        event.preventDefault();
        const form = $(this);
        const reasonField = form.find("textarea[name='reason']");
        const button = form.find("button[type='submit']");
        const status = form.find(".ticket-reopen-status");
        const ticketId = Number(form.attr("data-ticket-id"));
        const reason = String(reasonField.val() || "").trim();

        status.removeClass("is-error").text("");
        if (!ticketId || reason.length < 5) {
            status.addClass("is-error").text("Please explain what still needs attention (at least 5 characters).");
            return;
        }

        button.prop("disabled", true).text("Reopening...");
        $.ajax({
            url: "api/reopen_ticket.php",
            method: "POST",
            dataType: "json",
            data: {
                ticket_id: ticketId,
                reason: reason,
                csrf_token: window.ticketMessagesCsrf || ""
            }
        }).done(function (response) {
            if (!response || !response.success) {
                status.addClass("is-error").text(response && response.message
                    ? response.message
                    : "Unable to reopen this ticket. Please try again.");
                return;
            }

            $("#viewContent .ticket-view-status")
                .attr("class", "ticket-view-status status-open")
                .text("Open");
            form.replaceWith(`<p class="ticket-reopen-success" role="status">${escapeHtml(response.message || "Ticket reopened.")}</p>`);
            loadTicketMessages(ticketId);
            loadTicketTimeline(ticketId);
            loadTickets();
            loadStats();
        }).fail(function (xhr) {
            const response = xhr.responseJSON;
            status.addClass("is-error").text(response && response.message
                ? response.message
                : "Unable to reopen this ticket. Please try again.");
        }).always(function () {
            button.prop("disabled", false).text("Reopen ticket");
        });
    });

    function renderTicketFeedback(ticketId, response, notice) {
        const panel = $("#ticketFeedback");
        const feedback = response.data || null;

        if (!response.eligible) {
            panel.html(`
                <div class="ticket-feedback-heading">
                    <span class="ticket-feedback-icon" aria-hidden="true">★</span>
                    <div><h3>Share your experience</h3><p>Your feedback helps us improve support.</p></div>
                </div>
                <p class="ticket-feedback-locked">You can rate this ticket after it has been resolved or closed.</p>
            `);
            return;
        }

        const currentRating = feedback ? Number(feedback.rating) : 0;
        const stars = [1, 2, 3, 4, 5].map(function (rating) {
            const selected = rating <= currentRating ? " is-selected" : "";
            return `<button type="button" class="ticket-feedback-star${selected}" data-rating="${rating}" aria-label="${rating} ${rating === 1 ? "star" : "stars"}" aria-pressed="${rating === currentRating}">★</button>`;
        }).join("");
        const comment = feedback && feedback.comment
            ? escapeHtml(feedback.comment)
            : "";
        const csrfToken = $("#userNotifications").attr("data-csrf-token") || "";
        const noticeMarkup = notice
            ? `<p class="ticket-feedback-notice" role="status">${escapeHtml(notice)}</p>`
            : "";

        panel.html(`
            <div class="ticket-feedback-heading">
                <span class="ticket-feedback-icon" aria-hidden="true">★</span>
                <div>
                    <span class="ticket-feedback-eyebrow">YOUR FEEDBACK</span>
                    <h3>${feedback ? "How was your support experience?" : "How did we do?"}</h3>
                    <p>Your feedback helps us make support better for everyone.</p>
                </div>
            </div>
            <form id="ticketFeedbackForm" class="ticket-feedback-form" data-ticket-id="${Number(ticketId)}" data-has-feedback="${feedback ? "true" : "false"}">
                <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}">
                <input type="hidden" name="rating" value="${currentRating}">
                <div class="ticket-feedback-rating" role="group" aria-label="Rate your support experience">
                    <div class="ticket-feedback-stars">${stars}</div>
                    <span class="ticket-feedback-rating-label">${currentRating ? `${currentRating} out of 5 stars` : "Select a rating"}</span>
                </div>
                <label class="ticket-feedback-comment-label" for="ticketFeedbackComment">Anything you’d like us to know? <span>Optional</span></label>
                <textarea id="ticketFeedbackComment" name="comment" maxlength="1000" rows="3" placeholder="Share a comment about your experience...">${comment}</textarea>
                <div class="ticket-feedback-form-footer">
                    ${noticeMarkup}
                    <button type="submit" class="ticket-feedback-submit" ${currentRating ? "" : "disabled"}>
                        ${feedback ? "Update feedback" : "Send feedback"}
                    </button>
                </div>
            </form>
        `);
    }

    function loadTicketFeedback(ticketId, notice) {
        const panel = $("#ticketFeedback");
        if (!panel.length || ["admin", "support"].includes(String(window.userRole || "").toLowerCase())) {
            return;
        }

        panel.html('<p class="ticket-feedback-loading">Checking feedback availability...</p>');
        $.ajax({
            url: "api/ticket_feedback.php",
            method: "GET",
            dataType: "json",
            data: { ticket_id: ticketId },
            cache: false
        }).done(function (response) {
            if (!response || !response.success) {
                panel.html(`<p class="ticket-feedback-error">${escapeHtml(response && response.message ? response.message : "Feedback could not be loaded.")}</p>`);
                return;
            }
            renderTicketFeedback(ticketId, response, notice);
        }).fail(function (xhr) {
            const response = xhr.responseJSON;
            panel.html(`<p class="ticket-feedback-error">${escapeHtml(response && response.message ? response.message : "Feedback could not be loaded. Please try again.")}</p>`);
        });
    }

    $(document).on("click", ".ticket-feedback-star", function () {
        const rating = Number($(this).attr("data-rating"));
        const form = $(this).closest("#ticketFeedbackForm");

        form.find("input[name='rating']").val(rating);
        form.find(".ticket-feedback-star").each(function () {
            const selected = Number($(this).attr("data-rating")) <= rating;
            $(this)
                .toggleClass("is-selected", selected)
                .attr("aria-pressed", selected ? "true" : "false");
        });
        form.find(".ticket-feedback-rating-label").text(`${rating} out of 5 stars`);
        form.find(".ticket-feedback-submit").prop("disabled", false);
    });

    $(document).on("submit", "#ticketFeedbackForm", function (event) {
        event.preventDefault();
        const form = $(this);
        const button = form.find(".ticket-feedback-submit");
        const rating = Number(form.find("input[name='rating']").val());
        if (rating < 1 || rating > 5) {
            form.find(".ticket-feedback-rating-label").text("Choose a star rating before sending.");
            return;
        }

        button.prop("disabled", true).text("Sending...");
        $.ajax({
            url: "api/ticket_feedback.php",
            method: "POST",
            dataType: "json",
            data: {
                ticket_id: form.attr("data-ticket-id"),
                rating: rating,
                comment: form.find("textarea[name='comment']").val(),
                csrf_token: form.find("input[name='csrf_token']").val()
            }
        }).done(function (response) {
            if (!response || !response.success) {
                const message = response && response.message
                    ? response.message
                    : "Feedback could not be saved. Please try again.";
                form.find(".ticket-feedback-notice").remove();
                form.find(".ticket-feedback-form-footer").prepend(
                    `<p class="ticket-feedback-notice is-error" role="alert">${escapeHtml(message)}</p>`
                );
                button.prop("disabled", false).text(form.attr("data-has-feedback") === "true" ? "Update feedback" : "Send feedback");
                return;
            }
            loadTicketFeedback(form.attr("data-ticket-id"), response.message || "Thank you for your feedback.");
        }).fail(function (xhr) {
            const response = xhr.responseJSON;
            const message = response && response.message
                ? response.message
                : "Feedback could not be saved. Please try again.";
            form.find(".ticket-feedback-notice").remove();
            form.find(".ticket-feedback-form-footer").prepend(
                `<p class="ticket-feedback-notice is-error" role="alert">${escapeHtml(message)}</p>`
            );
            button.prop("disabled", false).text(form.attr("data-has-feedback") === "true" ? "Update feedback" : "Send feedback");
        });
    });

    function updateSlaOverview(summary) {
        const responseOverdue = Number(summary.response_overdue || 0);
        const resolutionOverdue = Number(summary.resolution_overdue || 0);

        $("#responseSlaOverdue").text(responseOverdue);
        $("#resolutionSlaOverdue").text(resolutionOverdue);
        $("#responseSlaCard").toggleClass("is-alert", responseOverdue > 0);
        $("#resolutionSlaCard").toggleClass("is-alert", resolutionOverdue > 0);
    }

    function updateAssignmentWorkload(assignees, unassignedActive) {
        const workload = $("#assignmentWorkload");
        const select = $("#assigneeFilter");
        const selectedAssignee = String(select.val() || "");
        const safeAssignees = Array.isArray(assignees) ? assignees : [];
        select.find("option").not('[value=""], [value="unassigned"]').remove();
        const creationAssignee = $("#createTicketAssignee");
        const selectedCreationAssignee = String(creationAssignee.val() || "");
        creationAssignee.find("option").not('[value=""], [data-self-option]').remove();
        safeAssignees.forEach(function (assignee) {
            const userId = String(assignee.user_id);
            select.append(new Option(String(assignee.name), userId));
            if (userId !== String(window.userId || "")) {
                creationAssignee.append(new Option(String(assignee.name), userId));
            }
        });
        select.val(selectedAssignee);
        creationAssignee.val(selectedCreationAssignee);

        if (!workload.length) {
            return;
        }

        const cards = safeAssignees.map(function (assignee) {
            const active = Number(assignee.active_tickets || 0);
            const total = Number(assignee.total_tickets || 0);
            return `<article class="assignment-workload-card">
                <div class="assignment-workload-avatar" aria-hidden="true">${escapeHtml(String(assignee.name || "?").trim().charAt(0).toUpperCase())}</div>
                <div class="assignment-workload-person"><strong>${escapeHtml(assignee.name)}</strong><span>${total} total assigned</span></div>
                <div class="assignment-workload-count"><strong>${active}</strong><span>active</span></div>
            </article>`;
        });
        cards.push(`<article class="assignment-workload-card is-unassigned">
            <div class="assignment-workload-avatar" aria-hidden="true">—</div>
            <div class="assignment-workload-person"><strong>Self</strong><span>Awaiting assignment</span></div>
            <div class="assignment-workload-count"><strong>${Number(unassignedActive || 0)}</strong><span>active</span></div>
        </article>`);
        $("#assignmentWorkloadGrid").html(cards.join(""));
        workload.prop("hidden", false);
    }

    function renderSlaLine(label, sla) {
        const stateLabels = {
            met: "Met",
            due: "On track",
            overdue: "Overdue",
            breached: "Late",
            unavailable: "Unavailable"
        };
        const state = Object.prototype.hasOwnProperty.call(stateLabels, sla && sla.state)
            ? sla.state
            : "unavailable";
        const dueAt = sla && sla.due_at ? ` <small>Due ${escapeHtml(sla.due_at)}</small>` : "";

        return `<span class="sla-line"><span class="sla-caption">${label}</span><span class="sla-pill sla-${state}">${stateLabels[state]}</span>${dueAt}</span>`;
    }

    let activeStatusPartition = "all";
    let currentTicketData = [];

    function partitionForStatus(status) {
        if (status === "Open") {
            return "open";
        }
        if (status === "In Progress") {
            return "progress";
        }
        if (status === "Resolved" || status === "Closed") {
            return "completed";
        }
        return "all";
    }

    function applyTicketPartition(tickets) {
        const counts = { all: tickets.length, open: 0, progress: 0, completed: 0 };
        const ticketsById = new Map();
        tickets.forEach(function (ticket) {
            ticketsById.set(String(ticket.id), ticket);
            if (ticket.status === "Open") {
                counts.open++;
            } else if (ticket.status === "In Progress") {
                counts.progress++;
            } else if (ticket.status === "Resolved" || ticket.status === "Closed") {
                counts.completed++;
            }
        });

        Object.keys(counts).forEach(function (partition) {
            $(`[data-status-count="${partition}"]`).text(counts[partition]);
        });
        $(".ticket-status-partition").each(function () {
            $(this).attr("aria-pressed", $(this).data("status-partition") === activeStatusPartition ? "true" : "false");
        });

        let visibleCount = 0;
        let responseOverdue = 0;
        let resolutionOverdue = 0;
        $("#ticketBody .ticket-row").each(function () {
            const row = $(this);
            const status = row.attr("data-ticket-status");
            const isCompleted = status === "Resolved" || status === "Closed";
            const matchesPartition = activeStatusPartition === "all"
                || (activeStatusPartition === "open" && status === "Open")
                || (activeStatusPartition === "progress" && status === "In Progress")
                || (activeStatusPartition === "completed" && isCompleted);
            const exactStatus = $("#statusFilter").val() || "";
            const isVisible = matchesPartition && (!exactStatus || status === exactStatus);
            row.toggle(isVisible);
            if (isVisible) {
                visibleCount++;
                row.find(".ticket-list-number").text(visibleCount);
                const ticket = ticketsById.get(String(row.attr("data-ticket-id")));
                if (ticket && ticket.response_sla && ticket.response_sla.state === "overdue") {
                    responseOverdue++;
                }
                if (ticket && ticket.resolution_sla && ticket.resolution_sla.state === "overdue") {
                    resolutionOverdue++;
                }
            }
        });

        $("#ticketCount").text(visibleCount + (visibleCount === 1 ? " ticket" : " tickets"));
        $("#emptyMessage").toggle(visibleCount === 0);
        updateSlaOverview({
            response_overdue: responseOverdue,
            resolution_overdue: resolutionOverdue
        });
    }




    // =========================================================

    // LOAD TICKETS

    // =========================================================



    function loadTickets() {



        let search =

            $("#searchTicket").val() || "";



        let priority =

            $("#priorityFilter").val() || "";

        let assignedTo = $("#assigneeFilter").val() || "";





        $.ajax({



            url: "api/tickets.php",



            type: "GET",



            data: {

                search: search,

                status: "",
                priority: priority,
                assigned_to: assignedTo

            },



            dataType: "json",



            success: function (response) {



                console.log(
                    "Tickets response:",
                    response
                );

                closeTicketMenus();
                $("#ticketBody").empty();
                if (!response.success || !Array.isArray(response.data)) {
                    currentTicketData = [];
                    applyTicketPartition(currentTicketData);
                    return;
                }
                currentTicketData = response.data;
                updateAssignmentWorkload(response.assignees, response.unassigned_active_tickets);

                $.each(

                    response.data,

                    function (index, ticket) {



                        let priorityClass =

                            String(ticket.priority)

                                .toLowerCase()

                                .replace(/\s+/g, "-");





                        let statusClass =

                            String(ticket.status)

                                .toLowerCase()

                                .replace(/\s+/g, "-");





                        /*

                        IMPORTANT:

                        We put the ID in TWO attributes.

                        This prevents the undefined problem.

                        */



                        const isSupportMember = window.userRole === "support";
                        const isSupportStaff = window.userRole === "admin" || isSupportMember;
                        const assigneeLabel = !ticket.assigned_to_user_id
                            ? "Self"
                            : Number(ticket.assigned_to_user_id) === Number(window.userId)
                                ? "Self"
                                : String(ticket.assignee_name || "Support Member");
                        const phoneDigits = String(ticket.phone || "").replace(/\D/g, "");
                        const statusOptions = ["Open", "In Progress", "Resolved", "Closed"]
                            .map(function (status) {
                                return `<option value="${status}"${ticket.status === status ? " selected" : ""}>${status}</option>`;
                            })
                            .join("");
                        const isCompleted = ticket.status === "Resolved" || ticket.status === "Closed";
                        const quickStatusAction = isSupportStaff && !isCompleted
                            ? `<div class="ticket-quick-status">
                                <button type="button" class="ticket-quick-status-toggle" aria-label="Quick status for ticket ${escapeHtml(ticket.id)}" aria-expanded="false" title="Quick status">
                                    <span aria-hidden="true">✓</span>
                                </button>
                                <div class="ticket-quick-status-menu" hidden>
                                    <label>
                                        <span>Status</span>
                                        <select class="ticket-quick-status-select" data-ticket-id="${escapeHtml(ticket.id)}">${statusOptions}</select>
                                    </label>
                                    <button type="button" class="ticket-quick-status-apply" data-ticket-id="${escapeHtml(ticket.id)}" title="Save status" aria-label="Save ticket status">✓</button>
                                    <span class="ticket-quick-status-message" role="status" aria-live="polite"></span>
                                </div>
                            </div>`
                            : "";
                        const whatsappAction = isSupportStaff && phoneDigits.length >= 7 && phoneDigits.length <= 15
                            ? `<a class="btn btn-whatsapp" href="https://wa.me/${phoneDigits}?text=${encodeURIComponent(`Hello ${ticket.name || ""}, regarding support ticket #${ticket.id}: ${ticket.subject || ""}`)}" target="_blank" rel="noopener noreferrer" aria-label="Message ${escapeHtml(ticket.name || "ticket contact")} about ticket ${escapeHtml(ticket.id)}">WhatsApp</a>`
                            : "";
                        const editAction = isSupportStaff
                            ? `<button type="button" class="ticket-more-item editBtn" data-id="${escapeHtml(ticket.id)}">Edit ticket</button>`
                            : "";
                        const deleteAction = isSupportStaff
                            ? `<button type="button" class="ticket-more-item deleteBtn" data-id="${escapeHtml(ticket.id)}">Delete ticket</button>`
                            : "";
                        const moreActions = `<div class="ticket-more-actions">
                            <button type="button" class="ticket-more-toggle" aria-label="More actions for ticket ${escapeHtml(ticket.id)}" aria-expanded="false" title="More actions">•••</button>
                            <div class="ticket-more-menu" role="menu" hidden>
                                <a class="ticket-more-item" role="menuitem" href="ticket_receipt.php?id=${encodeURIComponent(ticket.id)}">View receipt</a>
                                ${editAction}
                                ${whatsappAction
                                    ? whatsappAction.replace('class="btn btn-whatsapp"', 'class="ticket-more-item btn-whatsapp"').replace('aria-label="Message', 'role="menuitem" aria-label="Message')
                                    : ""}
                                ${deleteAction}
                            </div>
                        </div>`;
                        let row = `



                            <tr

                                class="ticket-row"

                                data-ticket-id="${ticket.id}"
                                data-ticket-status="${escapeHtml(ticket.status)}"

                            >



                                <td class="ticket-number-cell">
                                    <span class="ticket-list-number">${index + 1}</span>
                                    <span class="ticket-permanent-id">Ticket #${escapeHtml(ticket.id)}</span>
                                </td>

                                <td>

                                    ${escapeHtml(

                                        ticket.subject

                                    )}

                                </td>



                                <td>

                                    ${escapeHtml(

                                        ticket.name

                                    )}

                                </td>



                                <td>

                                    ${escapeHtml(

                                        ticket.category

                                    )}

                                </td>



                                <td>

                                    <span

                                        class="badge priority-${priorityClass}"

                                    >

                                        ${escapeHtml(

                                            ticket.priority

                                        )}

                                    </span>



                                </td>

                                <td>



                                    <span

                                        class="badge status-${statusClass}"

                                    >

                                        ${escapeHtml(

                                            ticket.status

                                        )}

                                    </span>



                                </td>

                                ${isSupportStaff ? `<td class="ticket-assignee-cell">${escapeHtml(assigneeLabel)}</td>` : ""}

                                <td class="sla-cell">
                                    ${renderSlaLine("Response", ticket.response_sla)}
                                    ${renderSlaLine("Resolution", ticket.resolution_sla)}
                                </td>



                                <td class="ticket-actions-cell">
                                    <div class="ticket-actions">
                                        ${quickStatusAction}
                                        <button
                                            type="button"
                                            class="btn btn-secondary viewBtn"
                                            data-id="${ticket.id}"
                                        >View</button>
                                        ${moreActions}
                                    </div>
                                </td>



                            </tr>



                        `;





                        $("#ticketBody")

                            .append(row);



                    }

                );

                applyTicketPartition(currentTicketData);


                if (notificationTicketId && !openedNotificationTicket) {
                    const ticketButton = $("#ticketBody .viewBtn").filter(function () {
                        return String($(this).data("id")) === notificationTicketId;
                    }).first();
                    if (ticketButton.length) {
                        openedNotificationTicket = true;
                        const cleanUrl = new URL(window.location.href);
                        cleanUrl.searchParams.delete("open_ticket");
                        window.history.replaceState({}, "", cleanUrl);
                        ticketButton.trigger("click");
                    }
                }


            },





            error: function (xhr) {



                console.log(

                    "Tickets AJAX Error:",

                    xhr.status

                );



                console.log(

                    xhr.responseText

                );



            }



        });



    }



    // =========================================================

// DELETE TICKET

// =========================================================



$(document).on(

    "click",

    ".deleteBtn",

    function () {



        let ticketId =

            $(this).attr("data-id");





        console.log(

            "DELETE BUTTON CLICKED"

        );



        console.log(

            "Ticket ID:",

            ticketId

        );





        if (!ticketId) {



            alert(

                "Ticket ID not found."

            );



            return;

        }





        // -----------------------------------------------------

        // CONFIRMATION

        // -----------------------------------------------------



        let confirmed =

            confirm(

                "Are you sure you want to delete Ticket #" +

                ticketId +

                "?"

            );





        if (!confirmed) {



            return;

        }





        // -----------------------------------------------------

        // AJAX DELETE REQUEST

        // -----------------------------------------------------



        $.ajax({



                url: "api/tickets.php",



                type: "POST",



                data: {



                    action: "delete_ticket",



                    id: ticketId



                },



                dataType: "json",





                success: function (response) {



                    console.log(

                        "DELETE RESPONSE:",

                        response

                    );





                    if (response.success) {



                    alert(

                            "Ticket deleted successfully!"

                        );





                        // Reload tickets

                        loadTickets();





                        // Reload dashboard

                        loadStats();



                    } else {



                        alert(

                            response.message ||

                            "Unable to delete ticket."

                        );



                    }



                },





                error: function (xhr) {



                    console.log(

                        "DELETE ERROR:",

                        xhr.status

                    );



                    console.log(

                        xhr.responseText

                    );





                    if (xhr.status === 403) {



                        alert(

                            "You do not have permission to perform this action."

                        );



                    } else {



                        alert(

                            "Unable to delete ticket."

                        );



                    }



                }



            });



        }

    );







    // =========================================================

    // LOAD STATISTICS

    // =========================================================



    function loadStats() {



        $.ajax({



            url: "api/stats.php",



            type: "GET",



            dataType: "json",



            success: function (response) {



                console.log(

                    "Stats:",

                    response

                );





                if (response.success) {



                    $("#totalTickets")

                        .text(

                            response.data.total

                        );



                    $("#openTickets")

                        .text(

                            response.data.open

                        );



                    $("#progressTickets")

                        .text(

                            response.data.progress

                        );



                    $("#resolvedTickets")

                        .text(

                            response.data.resolved

                        );



                }



            }



        });



    }







    // =========================================================

    // SEARCH

    // =========================================================



    let searchTimer;





    $("#searchTicket").on(

        "input",

        function () {



            clearTimeout(

                searchTimer

            );





            searchTimer =

                setTimeout(

                    function () {



                        loadTickets();



                    },

                    300

                );



        }

    );







    // =========================================================

    // STATUS FILTER

    // =========================================================



    $("#statusFilter").on(

        "change",

        function () {

            activeStatusPartition = partitionForStatus($(this).val());
            applyTicketPartition(currentTicketData);

        }

    );

    $(".ticket-status-partition").on("click", function () {
        activeStatusPartition = String($(this).data("status-partition") || "all");
        $("#statusFilter").val("");
        applyTicketPartition(currentTicketData);
    });

    function closeTicketMenus() {
        $(".ticket-quick-status-menu, .ticket-more-menu").prop("hidden", true);
        $(".ticket-quick-status").removeClass("is-open");
        $(".ticket-more-menu").each(function () {
            const owner = $(this).data("owner");
            if (owner && owner.length) {
                $(this).appendTo(owner.parent()).removeData("owner");
            }
        });
        $(".ticket-quick-status-toggle, .ticket-more-toggle").attr("aria-expanded", "false");
    }

    $(document).on("click", ".ticket-quick-status-toggle", function (event) {
        event.stopPropagation();
        const button = $(this);
        const menu = button.siblings(".ticket-quick-status-menu");
        const shouldOpen = menu.prop("hidden");

        closeTicketMenus();
        if (shouldOpen) {
            menu.prop("hidden", false);
            button.closest(".ticket-quick-status").addClass("is-open");
            button.attr("aria-expanded", "true");
            menu.find(".ticket-quick-status-select").trigger("focus");
            menu.find(".ticket-quick-status-message").text("");
        }
    });

    $(document).on("click", ".ticket-more-toggle", function (event) {
        event.stopPropagation();
        const button = $(this);
        const menu = button.siblings(".ticket-more-menu");
        const shouldOpen = menu.prop("hidden");

        closeTicketMenus();
        if (!shouldOpen) {
            return;
        }

        menu.data("owner", button);
        menu.appendTo(document.body);
        menu.prop("hidden", false);
        button.attr("aria-expanded", "true");
        const buttonRect = this.getBoundingClientRect();
        const menuWidth = menu.outerWidth();
        const menuHeight = menu.outerHeight();
        const left = Math.max(8, Math.min(buttonRect.right - menuWidth, window.innerWidth - menuWidth - 8));
        const top = buttonRect.bottom + menuHeight + 8 <= window.innerHeight
            ? buttonRect.bottom + 6
            : Math.max(8, buttonRect.top - menuHeight - 6);
        menu.css({ left: left + "px", top: top + "px" });
    });

    $(document).on("click", ".ticket-more-item", function () {
        closeTicketMenus();
    });

    $(document).on("click", ".ticket-quick-status-apply", function () {
        const button = $(this);
        const ticketId = String(button.data("ticket-id") || "");
        const menu = button.closest(".ticket-quick-status-menu");
        const select = menu.find(".ticket-quick-status-select");
        const newStatus = String(select.val() || "");
        const row = button.closest(".ticket-row");
        const currentStatus = String(row.attr("data-ticket-status") || "");
        const message = menu.find(".ticket-quick-status-message");

        if (!ticketId || !newStatus) {
            message.text("Choose a valid status.");
            return;
        }
        if (newStatus === currentStatus) {
            message.text("This ticket already has that status.");
            return;
        }
        if ((newStatus === "Resolved" || newStatus === "Closed")
            && !window.confirm("Set ticket #" + ticketId + " to " + newStatus + "?")) {
            select.val(currentStatus);
            return;
        }

        button.prop("disabled", true);
        message.text("Saving...");
        $.ajax({
            url: "api/tickets.php",
            type: "POST",
            dataType: "json",
            data: {
                action: "update_status",
                id: ticketId,
                status: newStatus
            },
            success: function (response) {
                if (!response.success) {
                    message.text(response.message || "Unable to update status.");
                    return;
                }
                if (response.email_sent === false) {
                    window.alert(response.message || "Status updated, but the customer email could not be sent.");
                }
                closeTicketMenus();
                loadTickets();
                loadStats();
            },
            error: function (xhr) {
                const response = xhr.responseJSON;
                message.text(response && response.message
                    ? response.message
                    : "Unable to update status. Please try again.");
            },
            complete: function () {
                button.prop("disabled", false);
            }
        });
    });

    $(document).on("click", function (event) {
        if (!$(event.target).closest(".ticket-quick-status, .ticket-quick-status-menu, .ticket-more-actions").length) {
            closeTicketMenus();
        }
    });

    $(document).on("keydown", function (event) {
        if (event.key === "Escape") {
            closeTicketMenus();
        }
    });
    $(window).on("resize", closeTicketMenus);
    window.addEventListener("scroll", closeTicketMenus, true);



    // =========================================================

    // UPDATE TICKET STATUS

    // =========================================================



    $(document).on(

        "change",

        ".statusSelect",

        function () {



            let ticketId =

                $(this).attr("data-ticket-id");



            let newStatus =

                $(this).val();





            console.log("STATUS UPDATE");



            console.log(

                "Ticket ID:",

                ticketId

            );



            console.log(

                "New Status:",

                newStatus

            );





            if (!ticketId) {



                alert("Ticket ID not found.");



                return;

            }





            $.ajax({



                url: "api/tickets.php",



                type: "POST",



                data: {



                    action: "update_status",



                    id: ticketId,



                    status: newStatus



                },



                dataType: "json",





                success: function (response) {



                    console.log(

                        "STATUS RESPONSE:",

                        response

                    );





                    if (response.success) {



                        alert(

                            "Ticket status updated successfully!"

                        );
                        if (
                            String($("#simpleViewModal").attr("data-ticket-id")) === String(ticketId)
                        ) {
                            $("#simpleViewModal .ticket-view-status")
                                .removeClass("status-open status-in-progress status-resolved status-closed")
                                .addClass("status-" + String(newStatus).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, ""))
                                .text(newStatus);
                            loadTicketFeedback(ticketId);
                        }
                        if (
                            $("#ticketTimeline").length &&
                            String($("#simpleViewModal").attr("data-ticket-id")) === String(ticketId)
                        ) {
                            loadTicketTimeline(ticketId);
                        }



                        loadTickets();



                        loadStats();



                    } else {



                        alert(

                            response.message ||

                            "Unable to update status."

                        );



                    }



                },





                error: function (xhr) {



                    console.log(

                        "STATUS UPDATE ERROR:",

                        xhr.status

                    );



                    console.log(

                        xhr.responseText

                    );





                    alert(

                        "Unable to update ticket status."

                    );



                }



            });



        }

    );



    // =========================================================

    // PRIORITY FILTER

    // =========================================================



    $("#priorityFilter").on(

        "change",

        function () {



            loadTickets();



        }

    );

    $("#assigneeFilter").on("change", function () {
        loadTickets();
    });







    // =========================================================

    // VIEW TICKET

    // =========================================================

    $(document).on(

        "click",

        ".viewBtn",

        function (event) {



            event.preventDefault();



            console.log("VIEW BUTTON CLICKED");





            const ticketId = $(this).attr("data-id")
                || $(this).closest("tr").attr("data-ticket-id")
                || "";

            console.log("TICKET ID:", ticketId);





            // Check ID

            if (

                ticketId === "" ||

                isNaN(ticketId)

            ) {



                alert(

                    "Ticket ID could not be found."

                );



                return;



            }





            // Remove old modal

            $("#simpleViewModal").remove();





            // =====================================================

            // CREATE MODAL

            // =====================================================



            let modal = `



                <div

                    id="simpleViewModal"
                    class="ticket-view-overlay"
                    data-ticket-id="${Number(ticketId)}"

                    style="

                        position:fixed;

                        inset:0;

                        background:rgba(0,0,0,0.55);

                        z-index:999999;

                        display:flex;

                        align-items:center;

                        justify-content:center;

                        padding:20px;

                    "

                >



                    <div
                        class="ticket-view-dialog"

                        style="

                            background:#ffffff;

                            width:100%;

                            max-width:700px;

                            max-height:90vh;

                            overflow:auto;

                            border-radius:14px;

                            padding:30px;

                            box-sizing:border-box;

                            box-shadow:0 20px 50px rgba(0,0,0,0.25);

                        "

                    >



                        <div
                            class="ticket-view-header"

                            style="

                                display:flex;

                                justify-content:space-between;

                                align-items:center;

                                border-bottom:1px solid #e5e7eb;

                                padding-bottom:15px;

                                margin-bottom:20px;

                            "

                        >



                            <div>

                                <span class="ticket-view-eyebrow">SUPPORT TICKET</span>

                                <h2

                                    id="viewTitle"

                                    style="

                                        margin:0;

                                        color:#0f172a;

                                    "

                                >

                                    Ticket #${ticketId}

                                </h2>



                                <p

                                    style="

                                        margin:5px 0 0;

                                        color:#64748b;

                                    "

                                >

                                    Ticket Details

                                </p>



                            </div>





                            <button

                                type="button"

                                id="closeViewModal"
                                class="ticket-view-close"

                                style="

                                    width:42px;

                                    height:42px;

                                    border:none;

                                    border-radius:50%;

                                    background:#f1f5f9;

                                    font-size:24px;

                                    cursor:pointer;

                                "

                            >

                                ×

                            </button>



                        </div>





                        <div id="viewLoading" class="ticket-view-loading">



                            <p

                                style="

                                    text-align:center;

                                    padding:30px;

                                "

                            >

                                Loading ticket...

                            </p>



                        </div>





                        <div

                            id="viewContent"
                            class="ticket-view-content"

                            style="display:none;"

                        >

                        </div>



                    </div>



                </div>



            `;





            $("body").append(modal);





            // =====================================================

            // AJAX - GET SINGLE TICKET

            // =====================================================



            $.ajax({



                url: "api/tickets.php",



                type: "GET",



                data: {

                    id: ticketId

                },



                dataType: "json",





                success: function (response) {



                    console.log(

                        "SINGLE TICKET RESPONSE:",

                        response

                    );





                    if (!response.success) {



                        $("#viewLoading").html(`



                            <p

                                style="

                                    color:#dc2626;

                                    text-align:center;

                                "

                            >

                                ${escapeHtml(

                                    response.message ||

                                    "Ticket not found."

                                )}

                            </p>



                        `);



                        return;

                    }





                    let ticket =

                        response.data;





                    console.log(

                        "TICKET DATA:",

                        ticket

                    );





                    // =================================================

                    // DISPLAY TICKET

                    // =================================================

                    const priorityClass = String(ticket.priority || "")
                        .toLowerCase()
                        .replace(/[^a-z0-9-]/g, "");
                    const statusClass = String(ticket.status || "")
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, "-")
                        .replace(/^-|-$/g, "");
                    const canManageTicketStatus = ["admin", "support"].includes(
                        String(window.userRole || "").toLowerCase()
                    );
                    const ticketStatusControl = canManageTicketStatus
                        ? `<select class="statusSelect" data-ticket-id="${Number(ticket.id)}">
                            ${["Open", "In Progress", "Resolved", "Closed"].map(function (status) {
                                return `<option value="${status}"${ticket.status === status ? " selected" : ""}>${status}</option>`;
                            }).join("")}
                        </select>`
                        : `<span class="ticket-view-readonly-status">${escapeHtml(ticket.status)}</span>`;
                    const customerReopenPanel = !canManageTicketStatus && Number(ticket.can_reopen) === 1
                        ? `<section class="ticket-reopen-card" aria-labelledby="ticketReopenHeading">
                            <div>
                                <span class="ticket-timeline-eyebrow">NEED MORE HELP?</span>
                                <h3 id="ticketReopenHeading">Is the issue still happening?</h3>
                                <p>Tell the support team what still needs attention. This resolved ticket can be reopened within 7 days.</p>
                            </div>
                            <form class="ticket-reopen-form" data-ticket-id="${Number(ticket.id)}">
                                <label for="ticketReopenReason">What still needs attention?</label>
                                <textarea id="ticketReopenReason" name="reason" maxlength="1000" minlength="5" rows="3" required placeholder="Describe what is still unresolved..."></textarea>
                                <div class="ticket-reopen-form-footer">
                                    <small>5 to 1,000 characters</small>
                                    <button type="submit">Reopen ticket</button>
                                </div>
                                <p class="ticket-reopen-status" role="status" aria-live="polite"></p>
                            </form>
                        </section>`
                        : "";

                    $("#viewContent").html(`

                        <section class="ticket-view-summary">
                            <div class="ticket-view-summary-copy">
                                <span class="ticket-view-summary-id">TICKET #${Number(ticket.id)} <i></i> ${escapeHtml(ticket.category)}</span>
                                <h3>${escapeHtml(ticket.subject)}</h3>
                                <p>Submitted by ${escapeHtml(ticket.name)}</p>
                            </div>
                            <div class="ticket-view-summary-badges">
                                <span class="ticket-view-priority priority-${priorityClass}">${escapeHtml(ticket.priority)} priority</span>
                                <span class="ticket-view-status status-${statusClass}">${escapeHtml(ticket.status)}</span>
                            </div>
                        </section>

                        <div
                            class="ticket-view-fields"

                            style="

                                display:grid;

                                grid-template-columns:140px 1fr;

                            "

                        >



                            <div

                                style="

                                    padding:15px 0;

                                    font-weight:600;

                                    color:#64748b;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                Ticket ID

                            </div>



                            <div

                                style="

                                    padding:15px 0;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                #${ticket.id}

                            </div>





                            <div

                                style="

                                    padding:15px 0;

                                    font-weight:600;

                                    color:#64748b;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                Subject

                            </div>



                            <div

                                style="

                                    padding:15px 0;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                ${escapeHtml(ticket.subject)}

                            </div>





                            <div

                                style="

                                    padding:15px 0;

                                    font-weight:600;

                                    color:#64748b;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                Name

                            </div>



                            <div

                                style="

                                    padding:15px 0;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                ${escapeHtml(ticket.name)}

                            </div>





                            <div

                                style="

                                    padding:15px 0;

                                    font-weight:600;

                                    color:#64748b;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                Email

                            </div>



                            <div

                                style="

                                    padding:15px 0;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                ${escapeHtml(ticket.email)}

                            </div>





                            <div

                                style="

                                    padding:15px 0;

                                    font-weight:600;

                                    color:#64748b;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                Category

                            </div>



                            <div

                                style="

                                    padding:15px 0;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                ${escapeHtml(ticket.category)}

                            </div>





                            <div

                                style="

                                    padding:15px 0;

                                    font-weight:600;

                                    color:#64748b;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                Priority

                            </div>



                            <div

                                style="

                                    padding:15px 0;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                ${escapeHtml(ticket.priority)}

                            </div>





                            <div

                                style="

                                    padding:15px 0;

                                    font-weight:600;

                                    color:#64748b;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                Status

                            </div>



                            <div

                                style="

                                    padding:15px 0;

                                    border-bottom:1px solid #e5e7eb;

                                "

                            >

                                ${ticketStatusControl}

                            </div>



                        </div>

                        <div
                            class="ticket-view-description"
                        >
                            <div class="ticket-view-description-heading">
                                <span class="ticket-view-description-icon" aria-hidden="true">“</span>
                                <div>
                                    <strong>Description</strong>
                                    <span>Original ticket request</span>
                                </div>
                            </div>
                            <div
                                class="ticket-view-description-text"
                            >
                                <p>${escapeHtml(ticket.description)}</p>
                            </div>
                        </div>

                        ${customerReopenPanel}

                        <section class="ticket-conversation-card" aria-labelledby="ticketMessagesHeading">
                            <div class="ticket-conversation-heading">
                                <div>
                                    <span class="ticket-timeline-eyebrow">CUSTOMER &amp; SUPPORT</span>
                                    <h3 id="ticketMessagesHeading">Conversation</h3>
                                    <p>Replies are shared with the customer and support team.</p>
                                </div>
                                <span class="ticket-conversation-icon" aria-hidden="true">↩</span>
                            </div>
                            <div id="ticketMessagesList" class="ticket-messages-list" aria-live="polite">
                                <p class="ticket-message-state">Loading conversation...</p>
                            </div>
                            <form class="ticket-message-form" data-ticket-id="${Number(ticket.id)}">
                                <label for="ticketMessageReply">Write a reply</label>
                                <textarea id="ticketMessageReply" name="message" maxlength="5000" rows="3" required placeholder="Share a helpful update or ask a question..."></textarea>
                                <div class="ticket-message-form-footer">
                                    <small>Maximum 5,000 characters</small>
                                    <button type="submit">Send reply</button>
                                </div>
                                <p class="ticket-message-status" role="status" aria-live="polite"></p>
                            </form>
                        </section>

                        <section class="ticket-timeline-card" aria-labelledby="ticketTimelineHeading">
                            <div class="ticket-timeline-header">
                                <div class="ticket-timeline-heading-copy">
                                    <span class="ticket-timeline-eyebrow">Ticket activity</span>
                                    <h3 id="ticketTimelineHeading">Progress timeline</h3>
                                    <p>Submission, status changes, and ticket replies</p>
                                </div>
                                <a class="ticket-timeline-header-mark"
                                   href="ticket-activity.php?id=${encodeURIComponent(ticketId)}"
                                   aria-label="View full ticket activity"
                                   title="View full ticket activity">↗</a>
                            </div>
                            <div id="ticketTimeline" aria-live="polite">
                                <p class="ticket-timeline-message">Loading ticket progress...</p>
                            </div>
                        </section>

                        ${["admin", "support"].includes(String(window.userRole || "").toLowerCase()) ? "" : `
                            <section class="ticket-feedback-card" id="ticketFeedback" aria-label="Customer satisfaction feedback">
                                <p class="ticket-feedback-loading">Checking feedback availability...</p>
                            </section>
                        `}




                        <div

                            style="

                                margin-top:25px;

                                text-align:right;

                            "

                        >



                            <button

                                type="button"

                                id="closeViewButton"
                                class="ticket-view-close-button"

                                style="

                                    border:none;

                                    background:#e2e8f0;

                                    padding:11px 25px;

                                    border-radius:8px;

                                    font-weight:600;

                                    cursor:pointer;

                                "

                            >

                                Close

                            </button>



                        </div>



                    `);





                    $("#viewTitle").text(

                        "Ticket #" + ticket.id

                    );





                    $("#viewLoading").hide();



                    $("#viewContent").fadeIn(200);
                    loadTicketMessages(ticket.id);
                    loadTicketTimeline(ticket.id);
                    loadTicketFeedback(ticket.id);


                },





                error: function (xhr) {



                    console.log(

                        "VIEW AJAX ERROR:",

                        xhr.status

                    );



                    console.log(

                        "RESPONSE:",

                        xhr.responseText

                    );





                    $("#viewLoading").html(`



                        <p

                            style="

                                color:#dc2626;

                                text-align:center;

                            "

                        >

                            Unable to load ticket.

                        </p>



                    `);



                }



            });



        }

    );





    // =========================================================

    // CLOSE VIEW MODAL

    // =========================================================



    $(document).on(

        "click",

        "#closeViewModal, #closeViewButton",

        function () {



            $("#simpleViewModal")

                .fadeOut(

                    200,

                    function () {



                        $(this).remove();



                    }

                );



        }

    );







    // =========================================================

    // CREATE TICKET MODAL

    // =========================================================



    $("#addTicketBtn").on(

        "click",

        function () {



            $("#ticketForm")[0]

                .reset();



            $(".error")

                .text("");



            $("#ticketModal")

                .fadeIn(300);



        }

    );





    $("#closeModal").on(

        "click",

        function () {



            $("#ticketModal")

                .fadeOut(300);



        }

    );

    if (supportInquiryId && /^\d+$/.test(supportInquiryId)) {
        $.ajax({
            url: "api/support_inquiries.php",
            type: "GET",
            data: { id: supportInquiryId },
            dataType: "json",
            success: function (response) {
                if (!response.success || !response.data || response.data.ticket_id) {
                    alert(response.message || "This support request has already been linked to a ticket.");
                    return;
                }

                const inquiry = response.data;
                $("#ticketForm")[0].reset();
                $("#supportInquiryId").val(inquiry.id);
                $("#name").val(inquiry.name || "");
                $("#email").val(inquiry.email || "");
                $("#subject").val(inquiry.subject || "");
                $("#description").val("Customer support request:\n\n" + (inquiry.message || ""));
                $("#category").val("Other");
                $("#priority").val("Medium");
                $(".error").text("");
                $("#ticketModal").fadeIn(300);
            },
            error: function () {
                alert("Unable to load this support request. Please return to the admin notifications and try again.");
            }
        });
    }





    $("#cancelBtn").on(

        "click",

        function () {



            $("#ticketModal")

                .fadeOut(300);



        }

    );







    // =========================================================

    // VALIDATION

    // =========================================================



    function validateName() {



        let value =

            $("#name")

                .val()

                .trim();





        if (value.length < 3) {



            $("#nameError")

                .text(

                    "Name must contain at least 3 characters."

                );



            return false;



        }





        $("#nameError")

            .text("");



        return true;



    }





    function validateEmail() {



        let value =

            $("#email")

                .val()

                .trim();





        let pattern =

            /^[^\s@]+@[^\s@]+\.[^\s@]+$/;





        if (!pattern.test(value)) {



            $("#emailError")

                .text(

                    "Enter a valid email."

                );



            return false;



        }





        $("#emailError")

            .text("");



        return true;



    }





    function validateSubject() {



        let value =

            $("#subject")

                .val()

                .trim();





        if (value.length < 5) {



            $("#subjectError")

                .text(

                    "Subject must contain at least 5 characters."

                );



            return false;



        }





        $("#subjectError")

            .text("");



        return true;



    }





    function validateCategory() {



        if (!$("#category").val()) {



            $("#categoryError")

                .text(

                    "Please select a category."

                );



            return false;



        }





        $("#categoryError")

            .text("");



        return true;



    }





    function validatePriority() {



        if (!$("#priority").val()) {



            $("#priorityError")

                .text(

                    "Please select a priority."

                );



            return false;



        }





        $("#priorityError")

            .text("");



        return true;



    }





    function validateDescription() {



        let value =

            $("#description")

                .val()

                .trim();





        if (value.length < 10) {



            $("#descriptionError")

                .text(

                    "Description must contain at least 10 characters."

                );



            return false;



        }





        $("#descriptionError")

            .text("");



        return true;



    }







    // =========================================================

    // FORM EVENTS

    // =========================================================



    $("#name").on(

        "blur",

        validateName

    );



    $("#email").on(

        "blur",

        validateEmail

    );



    $("#subject").on(

        "blur",

        validateSubject

    );



    $("#category").on(

        "change",

        validateCategory

    );



    $("#priority").on(

        "change",

        validatePriority

    );



    $("#description").on(

        "blur",

        validateDescription

    );







    // =========================================================

    // CREATE TICKET

    // =========================================================



    $("#ticketForm").on(

        "submit",

        function (event) {



            event.preventDefault();





            let valid =

                validateName() &&

                validateEmail() &&

                validateSubject() &&

                validateCategory() &&

                validatePriority() &&

                validateDescription();





            if (!valid) {



                alert(

                    "Please correct the errors."

                );



                return;



            }





            $.ajax({



                url:

                    "api/tickets.php",



                type:

                    "POST",



                data:

                    $("#ticketForm").serialize()

                    + "&action=create_ticket",



                dataType:

                    "json",





                success:

                    function (response) {



                        console.log(

                            "Create:",

                            response

                        );





                        if (response.success) {



                            const openReceipt = response.ticket_id
                                && window.confirm(response.message + "\n\nWould you like to view or print the ticket receipt now?");





                            $("#ticketModal")

                                .fadeOut(300);





                            $("#ticketForm")[0]

                                .reset();





                            loadTickets();



                            loadStats();

                            if (openReceipt) {
                                window.location.assign("ticket_receipt.php?id=" + encodeURIComponent(response.ticket_id));
                            }



                        } else {



                            alert(

                                response.message

                            );



                        }



                    },





                error:

                    function (xhr) {



                        console.log(

                            "CREATE ERROR:",

                            xhr.responseText

                        );



                    }



            });



        }

    );





    // =========================================================

    // EDIT TICKET

    // =========================================================



    $(document).on(

        "click",

        ".editBtn",

        function () {



            let ticketId =

                $(this).attr("data-id");





            console.log(

                "EDIT BUTTON CLICKED"

            );



            console.log(

                "Ticket ID:",

                ticketId

            );





            if (!ticketId) {



                alert(

                    "Ticket ID not found."

                );



                return;

            }





            // -------------------------------------------------

            // GET SINGLE TICKET

            // -------------------------------------------------



            $.ajax({



                url: "api/tickets.php",



                type: "GET",



                data: {

                    id: ticketId

                },



                dataType: "json",





                success: function (response) {



                    console.log(

                        "EDIT TICKET RESPONSE:",

                        response

                    );





                    if (!response.success) {



                        alert(

                            response.message ||

                            "Unable to load ticket."

                        );



                        return;

                    }





                    let ticket =

                        response.data;





                    // -------------------------------------------------

                    // FILL FORM

                    // -------------------------------------------------



                    $("#editTicketId")

                        .val(ticket.id);



                    $("#editName")

                        .val(ticket.name);



                    $("#editEmail")

                        .val(ticket.email);

                    $("#editPhone").val(ticket.phone || "");



                    $("#editSubject")

                        .val(ticket.subject);



                    $("#editCategory")

                        .val(ticket.category);



                    $("#editPriority")

                        .val(ticket.priority);



                    $("#editDescription")

                        .val(ticket.description);





                    // -------------------------------------------------

                    // SHOW MODAL

                    // -------------------------------------------------



                    $("#editTicketModal")

                        .css("display", "flex");



                },





                error: function (xhr) {



                    console.log(

                        "EDIT LOAD ERROR:",

                        xhr.status

                    );



                    console.log(

                        xhr.responseText

                    );





                    alert(

                        "Unable to load ticket."

                    );



                }



            });



        }

    );





    // =========================================================

    // UPDATE TICKET

    // =========================================================



    $(document).on(

        "submit",

        "#editTicketForm",

        function (e) {



            e.preventDefault();





            let ticketId =

                $("#editTicketId").val();



            let name =

                $("#editName").val().trim();



            let email =

                $("#editEmail").val().trim();

            let phone = $("#editPhone").val().trim();



            let subject =

                $("#editSubject").val().trim();



            let category =

                $("#editCategory").val();



            let priority =

                $("#editPriority").val();



            let description =

                $("#editDescription")

                    .val()

                    .trim();





            // -------------------------------------------------

            // VALIDATION

            // -------------------------------------------------



            if (

                !ticketId ||

                !name ||

                !email ||

                !subject ||

                !category ||

                !priority ||

                !description

            ) {



                alert(

                    "All fields are required."

                );



                return;

            }





            if (subject.length < 5) {



                alert(

                    "Subject must contain at least 5 characters."

                );



                return;

            }





            if (description.length < 10) {



                alert(

                    "Description must contain at least 10 characters."

                );



                return;

            }





            // -------------------------------------------------

            // AJAX

            // -------------------------------------------------



            $.ajax({



                url: "api/tickets.php",



                type: "POST",



                data: {



                    action: "update_ticket",



                    id: ticketId,



                    name: name,



                    email: email,

                    phone: phone,

                    subject: subject,



                    category: category,



                    priority: priority,



                    description: description



                },



                dataType: "json",





                success: function (response) {



                    console.log(

                        "UPDATE RESPONSE:",

                        response

                    );





                    if (response.success) {



                        alert(

                            "Ticket updated successfully!"

                        );





                        // Close modal



                        $("#editTicketModal")

                            .hide();





                        // Reload tickets



                        loadTickets();





                        // Reload statistics



                        loadStats();



                    } else {



                        alert(

                            response.message ||

                            "Unable to update ticket."

                        );



                    }



                },





                error: function (xhr) {



                    console.log(

                        "UPDATE ERROR:",

                        xhr.status

                    );



                    console.log(

                        xhr.responseText

                    );





                    if (xhr.status === 403) {



                        alert(

                            "Only administrators can edit tickets."

                        );



                    } else {



                        alert(

                            "Unable to update ticket."

                        );



                    }



                }



            });



        }

    );





    // =========================================================

    // CLOSE EDIT MODAL

    // =========================================================



    $(document).on(

        "click",

        "#closeEditModal, #cancelEdit",

        function () {



            $("#editTicketModal").hide();



        }

    );





    // Close when clicking outside modal



    $(document).on(

        "click",

        "#editTicketModal",

        function (e) {



            if (

                e.target === this

            ) {



                $(this).hide();



            }



        }

    );





    // =========================================================

    // =========================================================
    // TICKET ATTACHMENTS
    // =========================================================

    function loadTicketAttachments(ticketId) {
        $.ajax({
            url: "api/attachments.php",
            type: "GET",
            data: { ticket_id: ticketId },
            dataType: "json",
            success: function (response) {
                const list = $("#ticketAttachmentsList");
                if (!list.length) return;
                if (!response.success) {
                    list.text(response.message || "Unable to load attachments.");
                    return;
                }
                const attachments = Array.isArray(response.data) ? response.data : [];
                if (attachments.length === 0) {
                    list.html('<p style="margin:0;">No attachments uploaded yet.</p>');
                    return;
                }
                let html = '<div style="display:grid;gap:10px;">';
                attachments.forEach(function (file) {
                    const name = escapeHtml(file.original_name || "Attachment");
                    const bytes = Number(file.file_size || 0);
                    const size = bytes < 1048576
                        ? (bytes / 1024).toFixed(1) + " KB"
                        : (bytes / 1048576).toFixed(2) + " MB";
                    const url = "download_attachment.php?id=" + encodeURIComponent(file.id);
                    html += `
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;border:1px solid #e2e8f0;border-radius:9px;padding:12px;background:#f8fafc;">
                            <div style="min-width:0;">
                                <div style="font-weight:600;color:#0f172a;overflow-wrap:anywhere;">${name}</div>
                                <div style="font-size:12px;color:#64748b;margin-top:4px;">${escapeHtml(size)}${file.created_at ? " · " + escapeHtml(file.created_at) : ""}</div>
                            </div>
                            <a href="${url}" style="background:#eff6ff;color:#1d4ed8;padding:8px 12px;border-radius:7px;text-decoration:none;font-weight:600;white-space:nowrap;">Download</a>
                        </div>`;
                });
                html += '</div>';
                list.html(html);
            },
            error: function (xhr) {
                console.error("Attachment list error:", xhr.status, xhr.responseText);
                $("#ticketAttachmentsList").text("Unable to load attachments. Check the API response.");
            }
        });
    }

    $(document).on("submit", "#ticketAttachmentForm", function (event) {
        event.preventDefault();
        const form = this;
        const ticketId = $(form).attr("data-ticket-id");
        const input = form.querySelector('input[type="file"]');
        const file = input && input.files ? input.files[0] : null;
        if (!file) {
            alert("Please select a file first.");
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert("File size must not exceed 5 MB.");
            return;
        }
        const formData = new FormData();
        formData.append("ticket_id", ticketId);
        formData.append("attachment", file);
        const button = $(form).find('button[type="submit"]');
        button.prop("disabled", true).text("Uploading...");
        $.ajax({
            url: "api/attachments.php",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            success: function (response) {
                if (response.success) {
                    alert("Attachment uploaded successfully!");
                    form.reset();
                    loadTicketAttachments(ticketId);
                } else {
                    alert(response.message || "Attachment upload failed.");
                }
            },
            error: function (xhr) {
                console.error("Attachment upload error:", xhr.status, xhr.responseText);
                const message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : "Upload failed. Check the server response.";
                alert(message);
            },
            complete: function () {
                button.prop("disabled", false).text("Upload File");
            }
        });
    });


    // INITIAL LOAD

    // =========================================================



    loadTickets();



    loadStats();



});

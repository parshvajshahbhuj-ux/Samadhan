(function () {
    const history = document.getElementById("ticketActivityHistory");
    if (!history) {
        return;
    }

    const ticketId = history.dataset.ticketId;
    const countElement = document.getElementById("activityEventCount");
    const statusCountElement = document.getElementById("activityStatusCount");
    const replyCountElement = document.getElementById("activityReplyCount");

    function formatDate(value) {
        const date = new Date(String(value || "").replace(" ", "T"));
        if (Number.isNaN(date.getTime())) {
            return String(value || "");
        }
        return date.toLocaleString([], {
            weekday: "short",
            year: "numeric",
            month: "long",
            day: "numeric",
            hour: "numeric",
            minute: "2-digit"
        });
    }

    function formatElapsed(previousValue, currentValue) {
        const previous = new Date(String(previousValue || "").replace(" ", "T")).getTime();
        const current = new Date(String(currentValue || "").replace(" ", "T")).getTime();
        if (!Number.isFinite(previous) || !Number.isFinite(current) || current < previous) {
            return "";
        }

        const minutes = Math.floor((current - previous) / 60000);
        if (minutes < 1) {
            return "Less than a minute later";
        }
        if (minutes < 60) {
            return `${minutes} ${minutes === 1 ? "minute" : "minutes"} later`;
        }

        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        if (hours < 24) {
            return remainingMinutes
                ? `${hours}h ${remainingMinutes}m later`
                : `${hours} ${hours === 1 ? "hour" : "hours"} later`;
        }

        const days = Math.floor(hours / 24);
        const remainingHours = hours % 24;
        return remainingHours
            ? `${days}d ${remainingHours}h later`
            : `${days} ${days === 1 ? "day" : "days"} later`;
    }

    function createElement(tag, className, text) {
        const element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        if (text !== undefined) {
            element.textContent = text;
        }
        return element;
    }

    function renderTimeline(events) {
        const list = createElement("ol", "ticket-activity-timeline");

        events.forEach(function (event, index) {
            const item = createElement("li", `ticket-activity-event is-${event.type}`);
            const marker = createElement("span", "ticket-activity-event-marker");
            marker.setAttribute("aria-hidden", "true");

            const card = createElement("article", "ticket-activity-event-card");
            const top = createElement("div", "ticket-activity-event-top");
            const headingGroup = createElement("div", "ticket-activity-event-heading-group");
            const kind = event.type === "created"
                ? "Request received"
                : event.type === "status"
                    ? "Status update"
                    : event.type === "completed"
                        ? "Completion"
                    : "Conversation";

            headingGroup.appendChild(createElement("span", "ticket-activity-event-kind", kind));
            headingGroup.appendChild(createElement("h3", "", event.title || "Ticket updated"));
            top.appendChild(headingGroup);

            const date = createElement("time", "ticket-activity-event-date", formatDate(event.created_at));
            date.dateTime = String(event.created_at || "").replace(" ", "T");
            top.appendChild(date);
            card.appendChild(top);

            const footer = createElement("div", "ticket-activity-event-footer");
            footer.appendChild(createElement("span", "ticket-activity-event-actor", event.actor || "Support team"));
            if (index > 0) {
                const elapsed = formatElapsed(events[index - 1].created_at, event.created_at);
                if (elapsed) {
                    footer.appendChild(createElement("span", "ticket-activity-event-elapsed", elapsed));
                }
            }
            card.appendChild(footer);

            if (event.detail) {
                card.appendChild(createElement("p", "ticket-activity-event-detail", event.detail));
            }

            item.appendChild(marker);
            item.appendChild(card);
            list.appendChild(item);
        });

        history.replaceChildren(list);
    }

    fetch(`api/ticket_timeline.php?ticket_id=${encodeURIComponent(ticketId)}`, {
        credentials: "same-origin",
        headers: { Accept: "application/json" }
    })
        .then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok || !body || !body.success || !Array.isArray(body.data)) {
                    throw new Error(body && body.message
                        ? body.message
                        : "Ticket activity could not be loaded. Please try again.");
                }
                return body.data;
            });
        })
        .then(function (events) {
            countElement.textContent = String(events.length);
            statusCountElement.textContent = String(events.filter(event => event.type === "status").length);
            replyCountElement.textContent = String(events.filter(event => event.type === "reply").length);

            if (!events.length) {
                history.replaceChildren(createElement(
                    "p",
                    "ticket-activity-empty",
                    "No activity updates are available for this ticket yet."
                ));
                return;
            }

            renderTimeline(events);
        })
        .catch(function (error) {
            history.replaceChildren(createElement("p", "ticket-activity-error", error.message));
            countElement.textContent = "—";
            statusCountElement.textContent = "—";
            replyCountElement.textContent = "—";
        });
})();

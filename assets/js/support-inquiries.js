(function () {
    "use strict";

    function formatDuration(seconds) {
        var minutes = Math.max(1, Math.ceil(seconds / 60));
        var hours = Math.floor(minutes / 60);
        var remainingMinutes = minutes % 60;
        if (hours >= 24) {
            var days = Math.floor(hours / 24);
            return days + "d " + (hours % 24) + "h";
        }
        if (hours > 0) {
            return hours + "h " + remainingMinutes + "m";
        }
        return minutes + "m";
    }

    function updateDeadlines() {
        document.querySelectorAll("[data-request-deadline]").forEach(function (element) {
            if (element.dataset.deadlineState === "completed") {
                return;
            }

            var deadline = Number(element.dataset.deadlineUnix);
            if (!Number.isFinite(deadline) || deadline <= 0) {
                return;
            }

            var remaining = deadline - Math.floor(Date.now() / 1000);
            var state = remaining <= 0 ? "overdue" : (remaining <= 21600 ? "due-soon" : "on-track");
            var previousState = element.dataset.deadlineState;
            if (state !== previousState) {
                element.classList.remove("is-on-track", "is-due-soon", "is-overdue");
                element.classList.add("is-" + state);
                element.dataset.deadlineState = state;
            }

            var title = element.querySelector("[data-deadline-title]");
            var detail = element.querySelector("[data-deadline-detail]");
            if (!title || !detail) {
                return;
            }
            var deadlineText = new Date(deadline * 1000).toLocaleString([], {
                month: "short",
                day: "numeric",
                year: "numeric",
                hour: "numeric",
                minute: "2-digit"
            });
            if (state === "overdue") {
                title.textContent = "Overdue by " + formatDuration(Math.abs(remaining));
                detail.textContent = "The 24-hour target was " + deadlineText + ".";
            } else {
                title.textContent = "Due in " + formatDuration(remaining);
                detail.textContent = "The 24-hour target is " + deadlineText + ".";
            }
        });
    }

    updateDeadlines();
    window.setInterval(updateDeadlines, 60000);

    var inboxFilters = document.getElementById("supportInboxFilters");
    var inboxItems = Array.prototype.slice.call(document.querySelectorAll("[data-request-item]"));
    if (inboxFilters && inboxItems.length) {
        var searchInput = document.getElementById("supportInboxSearch");
        var statusFilter = document.getElementById("supportInboxStatus");
        var priorityFilter = document.getElementById("supportInboxPriority");
        var dateFilter = document.getElementById("supportInboxDate");
        var resultStatus = document.getElementById("supportInboxResults");
        var filterEmpty = document.getElementById("supportInboxFilterEmpty");
        var clearFilters = document.getElementById("supportInboxClearFilters");

        function filterRequests() {
            var query = searchInput.value.trim().toLocaleLowerCase();
            var selectedStatus = statusFilter.value;
            var selectedPriority = priorityFilter.value;
            var days = Number(dateFilter.value);
            var cutoff = days > 0 ? Math.floor(Date.now() / 1000) - days * 86400 : 0;
            var visibleCount = 0;

            inboxItems.forEach(function (item) {
                var matches = (!query || item.dataset.requestSearch.toLocaleLowerCase().includes(query))
                    && (!selectedStatus || item.dataset.requestStatus === selectedStatus)
                    && (!selectedPriority || item.dataset.requestPriority === selectedPriority)
                    && (!cutoff || Number(item.dataset.requestCreated) >= cutoff);
                item.hidden = !matches;
                if (matches) {
                    visibleCount++;
                }
            });

            resultStatus.textContent = "Showing " + visibleCount + " of " + inboxItems.length
                + (inboxItems.length === 1 ? " request" : " requests");
            if (filterEmpty) {
                filterEmpty.hidden = visibleCount > 0;
            }
        }

        inboxFilters.addEventListener("submit", function (event) {
            event.preventDefault();
        });
        inboxFilters.addEventListener("input", filterRequests);
        inboxFilters.addEventListener("change", filterRequests);
        if (clearFilters) {
            clearFilters.addEventListener("click", function () {
                inboxFilters.reset();
                filterRequests();
                searchInput.focus();
            });
        }
        filterRequests();
    }

    var conversation = document.getElementById("supportConversation");
    if (!conversation) {
        return;
    }

    var inquiryId = conversation.dataset.inquiryId;
    var isAdmin = conversation.dataset.isAdmin === "1";
    var messageList = document.getElementById("supportMessageList");
    var messageCount = document.getElementById("supportMessageCount");
    var replyForm = document.getElementById("supportReplyForm");
    var replyInput = document.getElementById("supportReply");
    var replyStatus = document.getElementById("supportReplyStatus");
    var submitButton = replyForm.querySelector('button[type="submit"]');
    var currentCsrf = conversation.dataset.csrfToken;
    var loading = false;

    function formatDate(value) {
        var parsed = new Date(String(value || "").replace(" ", "T"));
        if (Number.isNaN(parsed.getTime())) {
            return String(value || "");
        }
        return parsed.toLocaleString([], {
            month: "short",
            day: "numeric",
            year: "numeric",
            hour: "numeric",
            minute: "2-digit"
        });
    }

    function appendMessage(message, isOriginal) {
        var article = document.createElement("article");
        var isOwn = !isOriginal && ((message.user_role === "admin") === isAdmin);
        article.className = "support-thread-message"
            + (isOwn ? " is-own" : "")
            + (isOriginal ? " is-original" : "");

        var avatar = document.createElement("span");
        avatar.className = "support-thread-avatar" + (message.user_role === "admin" ? " is-admin" : "");
        avatar.setAttribute("aria-hidden", "true");
        avatar.textContent = String(message.user_name || "U").trim().charAt(0).toUpperCase();

        var content = document.createElement("div");
        content.className = "support-thread-message-content";

        var header = document.createElement("div");
        header.className = "support-thread-message-header";
        var author = document.createElement("strong");
        author.textContent = String(message.user_name || "User");
        var role = document.createElement("span");
        role.className = "support-thread-message-role"
            + (message.user_role === "admin" ? " is-admin" : "");
        role.textContent = isOriginal
            ? "Original request"
            : (message.user_role === "admin" ? "Support team" : "Customer");
        var time = document.createElement("time");
        time.dateTime = String(message.created_at || "");
        time.textContent = formatDate(message.created_at);
        header.append(author, role, time);

        var body = document.createElement("p");
        body.textContent = String(message.message || "");
        content.append(header, body);
        article.append(avatar, content);
        messageList.appendChild(article);
    }

    function renderConversation(payload, scrollToBottom) {
        if (!payload.success || !payload.data || !Array.isArray(payload.messages)) {
            throw new Error(payload.message || "Unable to load this conversation.");
        }

        var previousScrollHeight = messageList.scrollHeight;
        var wasNearBottom = messageList.scrollTop + messageList.clientHeight >= previousScrollHeight - 80;
        messageList.replaceChildren();
        appendMessage({
            user_name: payload.data.name,
            user_role: "user",
            message: payload.data.message,
            created_at: payload.data.created_at
        }, true);
        payload.messages.forEach(function (message) {
            appendMessage(message, false);
        });
        var totalMessages = payload.messages.length + 1;
        messageCount.textContent = totalMessages + (totalMessages === 1 ? " message" : " messages");
        if (wasNearBottom || scrollToBottom) {
            messageList.scrollTop = messageList.scrollHeight;
        }
        conversation.classList.remove("has-load-error");
    }

    function loadConversation(scrollToBottom) {
        if (loading) {
            return Promise.resolve();
        }
        loading = true;
        return fetch("api/support_inquiries.php?id=" + encodeURIComponent(inquiryId), {
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Accept": "application/json" }
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) {
                    throw new Error(payload.message || "Unable to load this conversation.");
                }
                return payload;
            });
        }).then(function (payload) {
            renderConversation(payload, scrollToBottom);
        }).catch(function (error) {
            if (!messageList.children.length || messageList.querySelector(".support-message-loading")) {
                messageList.replaceChildren();
                var errorMessage = document.createElement("p");
                errorMessage.className = "support-message-error";
                errorMessage.textContent = error.message;
                messageList.appendChild(errorMessage);
            }
            conversation.classList.add("has-load-error");
        }).finally(function () {
            loading = false;
        });
    }

    replyForm.addEventListener("submit", function (event) {
        event.preventDefault();
        var message = replyInput.value.trim();
        if (!message) {
            replyStatus.textContent = "Write a message before sending.";
            replyStatus.classList.add("is-error");
            return;
        }

        replyStatus.textContent = "";
        replyStatus.classList.remove("is-error");
        submitButton.disabled = true;
        submitButton.textContent = "Sending...";

        var body = new URLSearchParams(new FormData(replyForm));
        body.set("csrf_token", currentCsrf);
        fetch("api/support_inquiries.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                "Accept": "application/json"
            },
            body: body.toString()
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || "Unable to send your reply.");
                }
                return payload;
            });
        }).then(function (payload) {
            currentCsrf = payload.csrf_token || currentCsrf;
            conversation.dataset.csrfToken = currentCsrf;
            replyInput.value = "";
            replyStatus.textContent = payload.email_sent
                ? "Reply sent and email notification delivered."
                : "Reply saved, but its email notification could not be delivered.";
            return loadConversation(true);
        }).catch(function (error) {
            replyStatus.textContent = error.message;
            replyStatus.classList.add("is-error");
        }).finally(function () {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Send reply <span aria-hidden="true">&rarr;</span>';
        });
    });

    loadConversation(false);
    window.setInterval(function () {
        loadConversation(true);
    }, 15000);
})();

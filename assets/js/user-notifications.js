(function () {
    "use strict";

    var root = document.getElementById("userNotifications");
    if (!root) {
        return;
    }

    var toggle = root.querySelector(".notification-toggle");
    var panel = root.querySelector(".notification-panel");
    var count = root.querySelector(".notification-count");
    var list = root.querySelector(".notification-list");
    var status = root.querySelector(".notification-status");
    var markAll = root.querySelector(".notification-mark-all");
    var csrfToken = root.dataset.csrfToken || "";
    var knownUnread = new Set();
    var requestInProgress = false;

    function renderCount(value) {
        var unread = Number(value) || 0;
        count.textContent = unread > 99 ? "99+" : String(unread);
        count.hidden = unread === 0;
        toggle.setAttribute("aria-label", unread
            ? "Support updates, " + unread + " unread"
            : "Support updates, none unread");
    }

    function relativeTime(value) {
        var timestamp = Date.parse(String(value || "").replace(" ", "T"));
        if (!Number.isFinite(timestamp)) {
            return String(value || "");
        }
        var minutes = Math.max(0, Math.floor((Date.now() - timestamp) / 60000));
        if (minutes < 1) {
            return "Just now";
        }
        if (minutes < 60) {
            return minutes + " min ago";
        }
        var hours = Math.floor(minutes / 60);
        if (hours < 24) {
            return hours + (hours === 1 ? " hour ago" : " hours ago");
        }
        var days = Math.floor(hours / 24);
        return days + (days === 1 ? " day ago" : " days ago");
    }

    function destination(notification) {
        if (notification.support_inquiry_id) {
            return "support-inquiries.php?id=" + encodeURIComponent(notification.support_inquiry_id);
        }
        return "index.php?open_ticket=" + encodeURIComponent(notification.ticket_id);
    }

    function showToast(notification) {
        var toast = document.createElement("div");
        toast.className = "admin-notification-toast";
        toast.setAttribute("role", "status");
        var title = document.createElement("strong");
        title.textContent = notification.title || "New support update";
        var details = document.createElement("span");
        details.textContent = notification.message || "";
        toast.append(title, details);
        document.body.appendChild(toast);
        window.setTimeout(function () {
            toast.classList.add("is-hiding");
            window.setTimeout(function () {
                toast.remove();
            }, 250);
        }, 6500);
    }

    function renderNotifications(notifications) {
        list.replaceChildren();
        if (!notifications.length) {
            var empty = document.createElement("p");
            empty.className = "notification-empty";
            empty.textContent = "No support team updates yet.";
            list.appendChild(empty);
            return;
        }

        notifications.forEach(function (notification) {
            var link = document.createElement("a");
            link.className = "notification-item";
            if (!notification.read_at) {
                link.classList.add("is-unread");
            }
            link.href = destination(notification);
            link.dataset.notificationId = String(notification.id);

            var header = document.createElement("span");
            header.className = "notification-item-header";
            var title = document.createElement("strong");
            title.textContent = notification.title || "Support team update";
            var date = document.createElement("time");
            date.textContent = relativeTime(notification.created_at);
            header.append(title, date);

            var preview = document.createElement("span");
            preview.className = "notification-item-preview";
            preview.textContent = notification.message || "";
            link.append(header, preview);
            list.appendChild(link);
        });
    }

    function loadNotifications(announceNew) {
        if (requestInProgress) {
            return Promise.resolve();
        }
        requestInProgress = true;
        return fetch("api/user_notifications.php", {
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Accept": "application/json" }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("Unable to load support updates.");
            }
            return response.json();
        }).then(function (response) {
            if (!response.success || !Array.isArray(response.data)) {
                throw new Error(response.message || "Unable to load support updates.");
            }

            var unread = response.data.filter(function (notification) {
                return !notification.read_at;
            });
            if (announceNew) {
                unread.forEach(function (notification) {
                    var key = String(notification.id);
                    if (!knownUnread.has(key)) {
                        showToast(notification);
                    }
                });
            }
            knownUnread = new Set(unread.map(function (notification) {
                return String(notification.id);
            }));
            renderCount(response.unread_count);
            renderNotifications(response.data);
            status.textContent = "";
        }).catch(function () {
            status.textContent = "Support updates could not be refreshed.";
        }).finally(function () {
            requestInProgress = false;
        });
    }

    function postAction(action, notificationId) {
        var fields = {
            action: action,
            csrf_token: csrfToken
        };
        if (notificationId) {
            fields.notification_id = notificationId;
        }
        return fetch("api/user_notifications.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                "Accept": "application/json"
            },
            body: new URLSearchParams(fields).toString()
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("Unable to update support notifications.");
            }
            return response.json();
        }).then(function (response) {
            if (!response.success) {
                throw new Error(response.message || "Unable to update support notifications.");
            }
        });
    }

    toggle.addEventListener("click", function () {
        var isOpen = !panel.hidden;
        panel.hidden = isOpen;
        toggle.setAttribute("aria-expanded", String(!isOpen));
        if (!isOpen) {
            loadNotifications(false);
        }
    });

    markAll.addEventListener("click", function () {
        postAction("mark_all_read").then(function () {
            knownUnread.clear();
            return loadNotifications(false);
        }).catch(function () {
            status.textContent = "Unable to mark support updates as read.";
        });
    });

    list.addEventListener("click", function (event) {
        var link = event.target.closest("a[data-notification-id]");
        if (!link) {
            return;
        }
        event.preventDefault();
        var target = link.href;
        postAction("mark_read", link.dataset.notificationId)
            .catch(function () {
                status.textContent = "Could not update the notification. Opening it anyway.";
            })
            .finally(function () {
                window.location.assign(target);
            });
    });

    document.addEventListener("click", function (event) {
        if (!root.contains(event.target) && !panel.hidden) {
            panel.hidden = true;
            toggle.setAttribute("aria-expanded", "false");
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && !panel.hidden) {
            panel.hidden = true;
            toggle.setAttribute("aria-expanded", "false");
            toggle.focus();
        }
    });

    loadNotifications(false);
    window.setInterval(function () {
        loadNotifications(true);
    }, 30000);
})();

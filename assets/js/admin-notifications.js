(function () {
    "use strict";

    var root = document.getElementById("adminNotifications");
    if (!root) {
        return;
    }

    var toggle = root.querySelector(".notification-toggle");
    var panel = root.querySelector(".notification-panel");
    var count = root.querySelector(".notification-count");
    var list = root.querySelector(".notification-list");
    var status = root.querySelector(".notification-status");
    var markAll = root.querySelector(".notification-mark-all");
    var knownUnread = new Set();
    var requestInProgress = false;

    function renderCount(unreadCount) {
        var amount = Number(unreadCount) || 0;
        count.textContent = amount > 99 ? "99+" : String(amount);
        count.hidden = amount === 0;
        toggle.setAttribute("aria-label", amount
            ? "Notifications, " + amount + " unread"
            : "Notifications, none unread");
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

    function showToast(notification) {
        var toast = document.createElement("div");
        toast.className = "admin-notification-toast";
        toast.setAttribute("role", "status");
        var title = document.createElement("strong");
        title.textContent = notification.notification_type === "overdue"
            ? "Support request overdue"
            : (notification.notification_type === "inquiry" ? "New support request" : "New customer reply");
        var details = document.createElement("span");
        details.textContent = notification.notification_type === "reply"
            ? "Ticket #" + notification.ticket_id + " · " + notification.sender_name
            : notification.sender_name + " · " + notification.ticket_subject;
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
            empty.textContent = "No support notifications yet.";
            list.appendChild(empty);
            return;
        }

        notifications.forEach(function (notification) {
            var link = document.createElement("a");
            link.className = "notification-item";
            if (!notification.read_at) {
                link.classList.add("is-unread");
            }
            var isInquiry = notification.notification_type === "inquiry"
                || notification.notification_type === "overdue";
            link.href = isInquiry
                ? "support-inquiries.php?id=" + encodeURIComponent(notification.support_inquiry_id)
                : "index.php?open_ticket=" + encodeURIComponent(notification.ticket_id);
            link.dataset.notificationId = String(notification.notification_id);
            link.dataset.notificationType = notification.notification_type || "reply";
            link.dataset.ticketId = String(notification.ticket_id);

            var header = document.createElement("span");
            header.className = "notification-item-header";
            var title = document.createElement("strong");
            title.textContent = notification.notification_type === "overdue"
                ? "Overdue · " + (notification.sender_name || "Customer")
                : (isInquiry
                    ? "Support request · " + (notification.sender_name || "Customer")
                    : "Ticket #" + notification.ticket_id + " · " + (notification.sender_name || "Customer"));
            var date = document.createElement("time");
            date.textContent = relativeTime(notification.created_at);
            header.append(title, date);

            var subject = document.createElement("span");
            subject.className = "notification-item-subject";
            subject.textContent = notification.ticket_subject || (isInquiry ? "Support request" : "Support ticket");

            var preview = document.createElement("span");
            preview.className = "notification-item-preview";
            preview.textContent = notification.message_preview || "";

            link.append(header, subject, preview);
            list.appendChild(link);
        });
    }

    function loadNotifications(announceNew) {
        if (requestInProgress) {
            return Promise.resolve();
        }
        requestInProgress = true;

        return fetch("api/admin_notifications.php", {
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Accept": "application/json" }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("Unable to load admin notifications.");
                }
                return response.json();
            })
            .then(function (response) {
                if (!response.success || !Array.isArray(response.data)) {
                    throw new Error(response.message || "Unable to load admin notifications.");
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
            })
            .catch(function () {
                status.textContent = "Notifications could not be refreshed.";
            })
            .finally(function () {
                requestInProgress = false;
            });
    }

    function updateNotification(notificationId, notificationType) {
        return fetch("api/admin_notifications.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                "Accept": "application/json"
            },
            body: new URLSearchParams({
                action: "mark_read",
                notification_id: notificationId,
                notification_type: notificationType
            }).toString()
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("Unable to mark notification as read.");
            }
            return response.json();
        }).then(function (response) {
            if (!response.success) {
                throw new Error(response.message || "Unable to mark notification as read.");
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
        fetch("api/admin_notifications.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                "Accept": "application/json"
            },
            body: new URLSearchParams({ action: "mark_all_read" }).toString()
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("Unable to mark notifications as read.");
            }
            return response.json();
        }).then(function (response) {
            if (!response.success) {
                throw new Error(response.message || "Unable to mark notifications as read.");
            }
            knownUnread.clear();
            return loadNotifications(false);
        }).catch(function () {
            status.textContent = "Unable to mark notifications as read.";
        });
    });

    list.addEventListener("click", function (event) {
        var link = event.target.closest("a[data-notification-id]");
        if (!link) {
            return;
        }
        event.preventDefault();
        var destination = link.href;
        updateNotification(link.dataset.notificationId, link.dataset.notificationType)
            .catch(function () {
                status.textContent = "Could not update the notification. Opening it anyway.";
            })
            .finally(function () {
                window.location.assign(destination);
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

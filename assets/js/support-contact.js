(function () {
    "use strict";

    var form = document.getElementById("supportContactForm");
    if (!form) {
        return;
    }

    var status = document.getElementById("supportContactStatus");
    var submitButton = form.querySelector('button[type="submit"]');

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        status.textContent = "";
        status.classList.remove("is-error");
        submitButton.disabled = true;
        submitButton.textContent = "Sending...";

        fetch("api/support_inquiries.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                "Accept": "application/json"
            },
            body: new URLSearchParams(new FormData(form)).toString()
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || "We could not send your message. Please try again.");
                }
                return payload;
            });
        }).then(function (payload) {
            status.textContent = payload.confirmation_sent
                ? "Thank you. Your request was sent, and a confirmation was emailed to your account."
                : "Thank you. Your request was sent. We could not email a confirmation, but our support team has been notified.";
            form.reset();
            var csrf = form.querySelector('input[name="csrf_token"]');
            if (csrf && payload.csrf_token) {
                csrf.value = payload.csrf_token;
            }
        }).catch(function (error) {
            status.textContent = error.message;
            status.classList.add("is-error");
        }).finally(function () {
            submitButton.disabled = false;
            submitButton.textContent = "Send message";
        });
    });
})();

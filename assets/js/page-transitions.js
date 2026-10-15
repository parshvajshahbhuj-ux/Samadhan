(function () {
    "use strict";

    var transitionPages = [
        "login.php",
        "welcome.php",
        "index.php",
        "analytics.php",
        "activity-log.php",
        "support.php",
        "logout.php"
    ];
    document.addEventListener("DOMContentLoaded", function () {
        document.body.classList.add("page-transition-ready");
    });

    document.addEventListener("click", function (event) {
        if (
            event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        var link = event.target.closest("a[href]");
        if (
            !link ||
            link.hasAttribute("download") ||
            (link.target && link.target !== "_self") ||
            link.hasAttribute("data-no-transition")
        ) {
            return;
        }

        var destination;
        try {
            destination = new URL(link.href, window.location.href);
        } catch (error) {
            return;
        }

        if (
            destination.origin !== window.location.origin ||
            destination.pathname.replace(/\/+$/, "").split("/").pop() ===
                window.location.pathname.replace(/\/+$/, "").split("/").pop() ||
            transitionPages.indexOf(destination.pathname.replace(/\/+$/, "").split("/").pop()) === -1
        ) {
            return;
        }

        event.preventDefault();
        var loadingIndicator = document.createElement("div");
        var logo = document.createElement("img");
        loadingIndicator.className = "page-loading-indicator";
        loadingIndicator.setAttribute("role", "progressbar");
        loadingIndicator.setAttribute("aria-label", "Loading page");
        loadingIndicator.setAttribute("aria-valuetext", "Loading");
        logo.className = "page-loading-logo";
        logo.src = "assets/images/samadhan-logo.svg";
        logo.alt = "Samadhan";
        logo.width = 84;
        logo.height = 84;
        loadingIndicator.appendChild(logo);
        document.body.appendChild(loadingIndicator);
        document.body.classList.add("page-transition-leaving");
        window.setTimeout(function () {
            window.location.assign(destination.href);
        }, 420);
    });

    window.addEventListener("pageshow", function () {
        document.body.classList.remove("page-transition-leaving");
    });
})();

(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var sections = document.querySelectorAll("[data-reveal]");
        var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        var tour = document.querySelector(".public-tour-card");

        if (tour) {
            var tourSteps = [
                {
                    title: "Request received",
                    updateTitle: "The request is captured",
                    description: "Details and priority are kept with the ticket."
                },
                {
                    title: "Support in progress",
                    updateTitle: "Progress stays visible",
                    description: "Status changes and replies remain connected."
                },
                {
                    title: "Request resolved",
                    updateTitle: "The outcome is recorded",
                    description: "The team can see how the request was closed."
                }
            ];
            var tourButtons = tour.querySelectorAll("[data-tour-step]");
            var tourMarkers = tour.querySelectorAll(".public-tour-track span");
            var tourLines = tour.querySelectorAll(".public-tour-track i");
            var tourLabels = tour.querySelectorAll(".public-tour-track-labels span");
            var tourTitle = document.getElementById("tourStageTitle");
            var updateTitle = document.getElementById("tourUpdateTitle");
            var updateDescription = document.getElementById("tourUpdateDescription");

            function showTourStep(stepIndex) {
                var step = tourSteps[stepIndex];
                tourTitle.textContent = step.title;
                updateTitle.textContent = step.updateTitle;
                updateDescription.textContent = step.description;
                tourButtons.forEach(function (button, index) {
                    button.setAttribute("aria-pressed", index === stepIndex ? "true" : "false");
                });
                tourMarkers.forEach(function (marker, index) {
                    marker.classList.toggle("is-complete", index < stepIndex);
                    marker.classList.toggle("is-active", index === stepIndex);
                });
                tourLines.forEach(function (line, index) {
                    line.classList.toggle("is-complete", index < stepIndex);
                });
                tourLabels.forEach(function (label, index) {
                    label.classList.toggle("is-current", index === stepIndex);
                });
            }

            tourButtons.forEach(function (button) {
                button.addEventListener("click", function () {
                    showTourStep(Number(button.dataset.tourStep));
                });
            });
            showTourStep(0);
        }

        if (reducedMotion || !("IntersectionObserver" in window) || !sections.length) {
            sections.forEach(function (section) {
                section.classList.add("is-visible");
            });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12 });

        document.body.classList.add("public-motion-ready");
        sections.forEach(function (section) {
            observer.observe(section);
        });
    });
})();

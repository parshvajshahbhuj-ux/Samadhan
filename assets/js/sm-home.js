/* Samadhan — Redesigned Home interactions (sm-home.js) */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    /* ---------- sticky header shadow ---------- */
    var header = document.getElementById("smHeader");
    var onScroll = function () {
        if (header) header.classList.toggle("is-scrolled", window.scrollY > 8);
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();

    /* ---------- mobile burger menu ---------- */
    var burger = document.getElementById("smBurger");
    var nav = document.querySelector(".sm-nav");
    if (burger && nav) {
        burger.addEventListener("click", function () {
            var open = nav.classList.toggle("is-open");
            burger.classList.toggle("is-open", open);
            burger.setAttribute("aria-expanded", open ? "true" : "false");
        });
        nav.querySelectorAll("a").forEach(function (link) {
            link.addEventListener("click", function () {
                nav.classList.remove("is-open");
                burger.classList.remove("is-open");
                burger.setAttribute("aria-expanded", "false");
            });
        });
    }

    /* ---------- interactive product tour ---------- */
    var tourData = [
        {
            title: "Request received",
            icon: "↗",
            updateTitle: "The request is captured",
            updateDesc: "Details and priority are kept with the ticket.",
            status: "New",
            statusClass: "is-new",
            iconClass: null
        },
        {
            title: "Team is responding",
            icon: "◷",
            updateTitle: "Progress stays visible",
            updateDesc: "Status updates and ticket activity show what has changed.",
            status: "In progress",
            statusClass: "is-progress",
            iconClass: "#d98a17"
        },
        {
            title: "Request resolved",
            icon: "✓",
            updateTitle: "The outcome is recorded",
            updateDesc: "The resolution is saved so the team has a clear record.",
            status: "Resolved",
            statusClass: "is-done",
            iconClass: "#2faf7a"
        }
    ];

    var tourButtons = Array.prototype.slice.call(document.querySelectorAll("[data-sm-tour]"));
    var nodes = Array.prototype.slice.call(document.querySelectorAll(".sm-node"));
    var segments = Array.prototype.slice.call(document.querySelectorAll(".sm-track-seg i"));
    var labels = Array.prototype.slice.call(document.querySelectorAll(".sm-tour-labels span"));

    function setTour(step) {
        var d = tourData[step] || tourData[0];

        var t = document.getElementById("smTourTitle");
        var ic = document.getElementById("smTourIcon");
        var ut = document.getElementById("smTourUpdateTitle");
        var ud = document.getElementById("smTourUpdateDesc");
        var st = document.getElementById("smTourStatus");
        if (t) t.textContent = d.title;
        if (ic) {
            ic.textContent = d.icon;
            ic.style.background = d.iconClass ? (d.iconClass === null ? "" : "") : "";
            ic.style.color = d.iconClass || "";
            if (!d.iconClass) { ic.style.background = ""; ic.style.color = ""; }
            else { ic.style.background = d.iconClass + "22"; }
        }
        if (ut) ut.textContent = d.updateTitle;
        if (ud) ud.textContent = d.updateDesc;
        if (st) { st.textContent = d.status; st.className = "sm-tag " + d.statusClass; }

        nodes.forEach(function (n, i) { n.classList.toggle("is-active", i <= step); });
        segments.forEach(function (s, i) { s.style.width = i < step ? "100%" : "0%"; });
        labels.forEach(function (l, i) { l.classList.toggle("is-active", i <= step); });
        tourButtons.forEach(function (b, i) { b.setAttribute("aria-pressed", i === step ? "true" : "false"); });
    }

    tourButtons.forEach(function (btn, i) {
        btn.addEventListener("click", function () { setTour(i); });
    });
    setTour(0);

    /* auto-advance the tour until the user interacts */
    var auto = setInterval(function () {
        var current = tourButtons.findIndex(function (b) { return b.getAttribute("aria-pressed") === "true"; });
        setTour((current + 1) % tourData.length);
    }, 4200);
    tourButtons.forEach(function (btn) {
        btn.addEventListener("click", function () { clearInterval(auto); }, { once: true });
    });

    /* ---------- reveal on scroll ---------- */
    var revealTargets = document.querySelectorAll(".sm-features, .sm-how, .sm-getting-started, .sm-tour, .sm-access, .sm-entry-paths, .sm-customer-journey, .sm-roles, .sm-stats, .sm-faq, .sm-industries, .sm-cta, .sm-strip, .sm-footer");
    revealTargets.forEach(function (el) { el.classList.add("sm-reveal"); });

    if ("IntersectionObserver" in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add("is-in");
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.12 });
        revealTargets.forEach(function (el) { io.observe(el); });
    } else {
        revealTargets.forEach(function (el) { el.classList.add("is-in"); });
    }

    /* ---------- smooth anchor scroll ---------- */
    document.querySelectorAll('a[href^="#sm"]').forEach(function (a) {
        a.addEventListener("click", function (e) {
            var href = a.getAttribute("href");
            var target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                var behavior = window.matchMedia("(prefers-reduced-motion: reduce)").matches
                    ? "auto"
                    : "smooth";
                if (href === "#smHeader") {
                    window.scrollTo({ top: 0, behavior: behavior });
                    return;
                }
                target.scrollIntoView({ behavior: behavior, block: "start" });
            }
        });
    });
});

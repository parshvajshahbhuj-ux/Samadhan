(function ($) {
    "use strict";

    function escapeReportText(value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (ch) {
            return ({"&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#039;"})[ch];
        });
    }

    function renderBars(target, rows) {
        var $target = $(target).empty();
        if (!Array.isArray(rows) || rows.length === 0) {
            $target.html('<p class="report-empty">No ticket data available yet.</p>');
            return;
        }
        var max = Math.max.apply(null, rows.map(function (row) { return Number(row.total) || 0; }).concat([1]));
        rows.forEach(function (row) {
            var value = Number(row.total) || 0;
            var percent = Math.max(0, Math.min(100, (value / max) * 100));
            $target.append('<div class="report-row"><span class="report-label" title="' + escapeReportText(row.label) + '">' + escapeReportText(row.label) + '</span><div class="report-track"><div class="report-fill" style="width:' + percent + '%"></div></div><span class="report-value">' + value + '</span></div>');
        });
    }

    function renderTrend(target, rows) {
        var $target = $(target).empty();
        if (!Array.isArray(rows) || rows.length === 0) {
            $target.html('<p class="report-empty">No recent activity available.</p>');
            return;
        }
        var max = Math.max.apply(null, rows.map(function (row) { return Number(row.total) || 0; }).concat([1]));
        rows.forEach(function (row) {
            var value = Number(row.total) || 0;
            var height = value === 0 ? 2 : Math.max(8, (value / max) * 90);
            $target.append('<div class="trend-column" title="' + escapeReportText(row.date) + ': ' + value + ' tickets"><span class="trend-value">' + value + '</span><div class="trend-track"><div class="trend-fill" style="height:' + height + 'px"></div></div><span class="trend-label">' + escapeReportText(row.label) + '</span></div>');
        });
    }

    function loadReports() {
        var $refresh = $("#refreshReportsBtn");
        $refresh.prop("disabled", true).text("Refreshing…");
        $.ajax({ url: "api/report_stats.php", method: "GET", dataType: "json", cache: false })
            .done(function (response) {
                if (!response || !response.success || !response.data) throw new Error("Invalid report response");
                var data = response.data;
                var s = data.summary || {};
                $("#totalTickets").text(s.total || 0);
                $("#openTickets").text(s.open || 0);
                $("#progressTickets").text(s.progress || 0);
                $("#resolvedTickets").text(s.resolved || 0);
                $("#closedTickets").text(s.closed || 0);
                $("#criticalTickets").text(s.critical || 0);
                renderBars("#statusReport", data.status);
                renderBars("#priorityReport", data.priority);
                renderTrend("#trendReport", data.trend);
                renderBars("#categoryReport", data.category);
                var stamp = data.generated_at ? new Date(data.generated_at) : new Date();
                $("#reportsUpdatedAt").text("Updated " + stamp.toLocaleTimeString([], {hour:"2-digit", minute:"2-digit"}));
            })
            .fail(function () {
                $("#statusReport, #priorityReport, #trendReport, #categoryReport").html('<p class="report-empty">Reports could not load. Please try again.</p>');
            })
            .always(function () { $refresh.prop("disabled", false).text("Refresh reports"); });
    }

    $(function () {
        loadReports();
        $(document).on("click", "#refreshReportsBtn", loadReports);
    });
})(jQuery);

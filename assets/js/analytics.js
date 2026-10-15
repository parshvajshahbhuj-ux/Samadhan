(function ($) {
    "use strict";

    var charts = {};
    var loadedRange = null;
    var loadedStartDate = "";
    var loadedEndDate = "";
    var latestRequestId = 0;
    var chartColors = ["#138fc0", "#0a3c58", "#f59e0b", "#ef4444", "#8b5cf6", "#48c2d1", "#64748b"];
    var chartPalette = {
        "Open": "#138fc0",
        "In Progress": "#f59e0b",
        "Resolved": "#10b981",
        "Closed": "#64748b",
        "Low": "#10b981",
        "Medium": "#f59e0b",
        "High": "#f97316",
        "Critical": "#ef4444"
    };

    function destroyChart(key) {
        if (charts[key]) {
            charts[key].destroy();
            delete charts[key];
        }
    }

    function displayEmpty(canvasId, message) {
        var canvas = document.getElementById(canvasId);
        var empty = canvas.parentElement.querySelector(".chart-empty");
        canvas.hidden = true;
        empty.textContent = message;
        empty.hidden = false;
    }

    function makeChart(key, canvasId, config, hasData, emptyMessage) {
        var canvas = document.getElementById(canvasId);
        var empty = canvas.parentElement.querySelector(".chart-empty");
        destroyChart(key);

        if (!hasData) {
            displayEmpty(canvasId, emptyMessage || "No ticket data available for this date range.");
            return;
        }

        if (typeof window.Chart !== "function") {
            displayEmpty(canvasId, "Charts could not load. Please refresh the page.");
            return;
        }

        canvas.hidden = false;
        empty.hidden = true;
        try {
            charts[key] = new window.Chart(canvas, config);
        } catch (error) {
            canvas.hidden = true;
            empty.textContent = "This chart could not be rendered. Check the browser console for details.";
            empty.hidden = false;
            console.error("Analytics chart failed to render (" + key + "):", error);
        }
    }

    function rowColors(rows) {
        return rows.map(function (row, index) {
            return Object.prototype.hasOwnProperty.call(chartPalette, row.label)
                ? chartPalette[row.label]
                : chartColors[index % chartColors.length];
        });
    }

    function verticalGradient(chart, topColor, bottomColor) {
        var area = chart.chartArea;
        if (!area) return bottomColor;
        var gradient = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
        gradient.addColorStop(0, topColor);
        gradient.addColorStop(1, bottomColor);
        return gradient;
    }

    function makeValueLabels(id, horizontal, color) {
        return {
            id: id,
            afterDatasetsDraw: function (chart) {
                var context = chart.ctx;
                var meta = chart.getDatasetMeta(0);
                var dataset = chart.data.datasets[0];
                context.save();
                context.font = "700 11px Inter, Segoe UI, sans-serif";
                context.fillStyle = color || "#496477";
                context.textBaseline = "middle";

                meta.data.forEach(function (bar, index) {
                    var value = Number(dataset.data[index]) || 0;
                    if (!value) return;

                    if (horizontal) {
                        context.textAlign = "left";
                        context.fillText(String(value), bar.x + 8, bar.y);
                    } else {
                        context.textAlign = "center";
                        context.fillText(String(value), bar.x, bar.y - 11);
                    }
                });
                context.restore();
            }
        };
    }

    function renderCharts(data) {
        var monthly = data.monthly || [];
        var status = data.status || [];
        var priority = data.priority || [];
        var category = data.category || [];
        var weekday = data.weekday || [];
        var daily = data.daily || [];
        var satisfaction = data.satisfaction || {};
        var satisfactionDistribution = (satisfaction.distribution || []).slice().sort(function (left, right) {
            return Number(right.rating) - Number(left.rating);
        });
        var monthLabels = monthly.map(function (row) {
            var parts = String(row.month).split("-");
            var date = new Date(Number(parts[0]), Number(parts[1]) - 1, 1);
            return Number.isNaN(date.getTime())
                ? row.month
                : date.toLocaleDateString([], { month: "short", year: "numeric" });
        });
        var monthCreated = monthly.map(function (row) { return Number(row.created) || 0; });
        var monthResolved = monthly.map(function (row) { return Number(row.resolved) || 0; });

        makeChart("monthly", "monthlyChart", {
            type: "line",
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: "Created",
                        data: monthCreated,
                        borderColor: "#138fc0",
                        backgroundColor: function (context) {
                            return verticalGradient(context.chart, "rgba(19, 143, 192, .27)", "rgba(19, 143, 192, .015)");
                        },
                        fill: true,
                        tension: .38,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointBorderWidth: 2,
                        pointBorderColor: "#fff",
                        pointBackgroundColor: "#138fc0"
                    },
                    {
                        label: "Resolved",
                        data: monthResolved,
                        borderColor: "#10a779",
                        backgroundColor: function (context) {
                            return verticalGradient(context.chart, "rgba(16, 167, 121, .2)", "rgba(16, 167, 121, .01)");
                        },
                        fill: true,
                        tension: .38,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointBorderWidth: 2,
                        pointBorderColor: "#fff",
                        pointBackgroundColor: "#10a779"
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 850, easing: "easeOutQuart" },
                interaction: { mode: "index", intersect: false },
                layout: { padding: { top: 8, right: 12, bottom: 2 } },
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: { usePointStyle: true, pointStyle: "circle", boxWidth: 8, padding: 20, color: "#526a7a", font: { size: 11, weight: "600" } }
                    },
                    tooltip: { padding: 12, cornerRadius: 10, displayColors: true, usePointStyle: true }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: "#8093a0", font: { size: 11, weight: "600" } } },
                    y: { beginAtZero: true, ticks: { precision: 0, color: "#8093a0", padding: 8 }, grid: { color: "rgba(148, 163, 184, .16)", drawBorder: false } }
                }
            }
        }, monthCreated.concat(monthResolved).some(function (value) { return value > 0; }));

        [
            { key: "status", id: "statusChart", rows: status, centerLabel: "TICKETS" },
            { key: "priority", id: "priorityChart", rows: priority, centerLabel: "PRIORITY" }
        ].forEach(function (chart) {
            var total = chart.rows.reduce(function (sum, row) {
                return sum + (Number(row.total) || 0);
            }, 0);
            var centerText = {
                id: chart.key + "CenterText",
                afterDraw: function (instance) {
                    var chartArea = instance.chartArea;
                    if (!chartArea || !total) return;
                    var context = instance.ctx;
                    var centerX = (chartArea.left + chartArea.right) / 2;
                    var centerY = (chartArea.top + chartArea.bottom) / 2;
                    context.save();
                    context.textAlign = "center";
                    context.textBaseline = "middle";
                    context.fillStyle = "#18384c";
                    context.font = "750 25px Inter, Segoe UI, sans-serif";
                    context.fillText(String(total), centerX, centerY - 7);
                    context.fillStyle = "#8294a0";
                    context.font = "700 9px Inter, Segoe UI, sans-serif";
                    context.fillText(chart.centerLabel, centerX, centerY + 13);
                    context.restore();
                }
            };

            makeChart(chart.key, chart.id, {
                type: "doughnut",
                data: {
                    labels: chart.rows.map(function (row) { return row.label; }),
                    datasets: [{
                        data: chart.rows.map(function (row) { return Number(row.total) || 0; }),
                        backgroundColor: rowColors(chart.rows),
                        borderColor: "#ffffff",
                        borderWidth: 5,
                        hoverOffset: 9,
                        borderRadius: 5,
                        spacing: 2
                    }]
                },
                plugins: [centerText],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 750, easing: "easeOutQuart" },
                    cutout: "72%",
                    radius: "92%",
                    plugins: {
                        legend: {
                            position: "bottom",
                            labels: {
                                usePointStyle: true,
                                pointStyle: "circle",
                                boxWidth: 8,
                                padding: 14,
                                color: "#526a7a",
                                font: { size: 10, weight: "600" },
                                generateLabels: function (instance) {
                                    return chart.rows.map(function (row, index) {
                                        var count = Number(row.total) || 0;
                                        var percentage = total ? Math.round((count / total) * 100) : 0;
                                        return {
                                            text: row.label + "  " + count + " · " + percentage + "%",
                                            fillStyle: rowColors(chart.rows)[index],
                                            strokeStyle: "#ffffff",
                                            lineWidth: 1,
                                            hidden: !instance.getDataVisibility(index),
                                            index: index
                                        };
                                    });
                                }
                            }
                        },
                        tooltip: {
                            padding: 12,
                            cornerRadius: 10,
                            displayColors: true,
                            callbacks: {
                                label: function (context) {
                                    var count = Number(context.raw) || 0;
                                    return " " + context.label + ": " + count + " (" + (total ? Math.round((count / total) * 100) : 0) + "%)";
                                }
                            }
                        }
                    }
                }
            }, chart.rows.some(function (row) { return Number(row.total) > 0; }));
        });

        var categoryCanvas = document.getElementById("categoryChart");
        categoryCanvas.parentElement.style.height = Math.max(270, category.length * 42 + 36) + "px";
        var categoryMaximum = category.reduce(function (maximum, row) {
            return Math.max(maximum, Number(row.total) || 0);
        }, 0);
        makeChart("category", "categoryChart", {
            type: "bar",
            data: {
                labels: category.map(function (row) { return row.label; }),
                datasets: [{
                    label: "Tickets",
                    data: category.map(function (row) { return Number(row.total) || 0; }),
                    backgroundColor: function (context) {
                        return verticalGradient(context.chart, "#39a7ca", "#127fa9");
                    },
                    borderRadius: 7,
                    borderSkipped: false,
                    barPercentage: .68,
                    categoryPercentage: .78,
                    maxBarThickness: 26
                }]
            },
            plugins: [makeValueLabels("categoryValues", true)],
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 750, easing: "easeOutQuart" },
                layout: { padding: { right: 28, top: 4, bottom: 4 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            title: function (items) { return category[items[0].dataIndex].label; },
                            label: function (context) { return " " + context.raw + (Number(context.raw) === 1 ? " ticket" : " tickets"); }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        suggestedMax: categoryMaximum + Math.max(1, Math.ceil(categoryMaximum * .12)),
                        ticks: { precision: 0, color: "#8496a2", padding: 7 },
                        grid: { color: "rgba(148, 163, 184, .14)", drawBorder: false }
                    },
                    y: {
                        grid: { display: false, drawBorder: false },
                        ticks: {
                            color: "#526a7a",
                            padding: 8,
                            font: { size: 10, weight: "600" },
                            callback: function (value) {
                                var label = String(this.getLabelForValue(value));
                                return label.length > 24 ? label.slice(0, 22) + "…" : label;
                            }
                        }
                    }
                }
            }
        }, category.some(function (row) { return Number(row.total) > 0; }));

        var weekdayMaximum = weekday.reduce(function (maximum, row) {
            return Math.max(maximum, Number(row.total) || 0);
        }, 0);
        var weekdayPeak = weekday.findIndex(function (row) {
            return Number(row.total) === weekdayMaximum && weekdayMaximum > 0;
        });
        makeChart("weekday", "weekdayChart", {
            type: "bar",
            data: {
                labels: weekday.map(function (row) { return row.label; }),
                datasets: [{
                    label: "Tickets created",
                    data: weekday.map(function (row) { return Number(row.total) || 0; }),
                    backgroundColor: function (context) {
                        if (context.dataIndex === weekdayPeak) {
                            return verticalGradient(context.chart, "#35bd91", "#16855f");
                        }
                        return verticalGradient(context.chart, "#58b5d1", "#218aad");
                    },
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 42
                }]
            },
            plugins: [makeValueLabels("weekdayValues", false)],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 750, easing: "easeOutQuart" },
                layout: { padding: { top: 13, right: 8, left: 4 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function (context) {
                                return " " + context.raw + (Number(context.raw) === 1 ? " ticket created" : " tickets created");
                            },
                            afterLabel: function (context) {
                                return context.dataIndex === weekdayPeak ? "Busiest day in this period" : "";
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false, drawBorder: false }, ticks: { color: "#657d8d", font: { size: 10, weight: "600" }, maxRotation: 0 } },
                    y: {
                        beginAtZero: true,
                        suggestedMax: weekdayMaximum + Math.max(1, Math.ceil(weekdayMaximum * .18)),
                        ticks: { precision: 0, color: "#8a9ba6", padding: 7 },
                        grid: { color: "rgba(148, 163, 184, .14)", drawBorder: false }
                    }
                }
            }
        }, weekday.some(function (row) { return Number(row.total) > 0; }));

        var dailyCanvas = document.getElementById("dailyChart");
        dailyCanvas.style.width = Math.max(680, daily.length * 27) + "px";
        makeChart("daily", "dailyChart", {
            type: "line",
            data: {
                labels: daily.map(function (row) { return row.date; }),
                datasets: [{
                    label: "Tickets created",
                    data: daily.map(function (row) { return Number(row.total) || 0; }),
                    borderColor: "#138fc0",
                    backgroundColor: function (context) {
                        return verticalGradient(context.chart, "rgba(19, 143, 192, .24)", "rgba(19, 143, 192, .015)");
                    },
                    pointBackgroundColor: "#fff",
                    pointBorderColor: "#138fc0",
                    pointBorderWidth: 2,
                    pointRadius: daily.length > 45 ? 0 : 3,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: .34,
                    borderWidth: 2.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 850, easing: "easeOutQuart" },
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            title: function (items) {
                                var value = items[0].label;
                                var parts = String(value).split("-");
                                var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
                                return Number.isNaN(date.getTime())
                                    ? value
                                    : date.toLocaleDateString([], { weekday: "long", month: "short", day: "numeric", year: "numeric" });
                            },
                            label: function (context) {
                                return " " + context.raw + (Number(context.raw) === 1 ? " ticket created" : " tickets created");
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 14,
                            maxRotation: 0,
                            color: "#8093a0",
                            font: { size: 10, weight: "600" },
                            callback: function (value) {
                                var label = String(this.getLabelForValue(value));
                                var parts = label.split("-");
                                var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
                                return Number.isNaN(date.getTime())
                                    ? label
                                    : date.toLocaleDateString([], { month: "short", day: "numeric" });
                            }
                        }
                    },
                    y: { beginAtZero: true, ticks: { precision: 0, color: "#8496a2", padding: 7 }, grid: { color: "rgba(148, 163, 184, .14)", drawBorder: false } }
                }
            }
        }, daily.some(function (row) { return Number(row.total) > 0; }));

        var satisfactionTotal = Number(satisfaction.rating_count) || 0;
        var satisfactionMaximum = satisfactionDistribution.reduce(function (maximum, row) {
            return Math.max(maximum, Number(row.total) || 0);
        }, 0);
        var satisfactionColors = {
            1: "#e46b67",
            2: "#ed9953",
            3: "#e4b947",
            4: "#82b866",
            5: "#279b72"
        };
        var satisfactionLabels = {
            id: "satisfactionLabels",
            afterDatasetsDraw: function (chart) {
                var context = chart.ctx;
                var dataset = chart.data.datasets[0];
                var meta = chart.getDatasetMeta(0);
                context.save();
                context.font = "600 11px Inter, Segoe UI, sans-serif";
                context.textBaseline = "middle";

                meta.data.forEach(function (bar, index) {
                    var count = Number(dataset.data[index]) || 0;
                    if (count === 0) return;

                    var percentage = satisfactionTotal > 0
                        ? Math.round((count / satisfactionTotal) * 100)
                        : 0;
                    var label = count + (count === 1 ? " rating" : " ratings") + "  ·  " + percentage + "%";
                    var barWidth = Math.abs(bar.x - bar.base);
                    var inside = barWidth > context.measureText(label).width + 18;
                    context.textAlign = inside ? "right" : "left";
                    context.fillStyle = inside ? "#ffffff" : "#536c7c";
                    context.fillText(label, inside ? bar.x - 10 : bar.x + 9, bar.y);
                });

                context.restore();
            }
        };

        makeChart("satisfaction", "satisfactionChart", {
            type: "bar",
            data: {
                labels: satisfactionDistribution.map(function (row) {
                    return row.rating + " " + (Number(row.rating) === 1 ? "star" : "stars");
                }),
                datasets: [{
                    label: "Customer ratings",
                    data: satisfactionDistribution.map(function (row) { return Number(row.total) || 0; }),
                    backgroundColor: satisfactionDistribution.map(function (row) {
                        return satisfactionColors[Number(row.rating)] || "#138fc0";
                    }),
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: .68,
                    categoryPercentage: .78,
                    maxBarThickness: 38
                }]
            },
            plugins: [satisfactionLabels],
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 750, easing: "easeOutQuart" },
                layout: { padding: { right: 112, left: 4 } },
                interaction: { mode: "nearest", intersect: true },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        displayColors: true,
                        padding: 12,
                        cornerRadius: 9,
                        callbacks: {
                            label: function (context) {
                                var count = Number(context.raw) || 0;
                                var percentage = satisfactionTotal
                                    ? Math.round((count / satisfactionTotal) * 100)
                                    : 0;
                                return " " + count + (count === 1 ? " response" : " responses") + " (" + percentage + "%)";
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        suggestedMax: Math.max(2, satisfactionMaximum + Math.max(1, Math.ceil(satisfactionMaximum * .22))),
                        ticks: { precision: 0, stepSize: 1, color: "#8798a4", padding: 7 },
                        grid: { color: "rgba(148, 163, 184, .16)", drawBorder: false }
                    },
                    y: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: "#40596b", font: { size: 12, weight: "600" }, padding: 8 }
                    }
                }
            }
        }, satisfactionDistribution.some(function (row) { return Number(row.total) > 0; }),
        "No customer ratings have been submitted in this date range.");
    }

    function formatDuration(seconds) {
        var minutes = Math.floor(seconds / 60);
        if (minutes < 1) return "Less than 1 min";
        var days = Math.floor(minutes / 1440);
        var hours = Math.floor((minutes % 1440) / 60);
        var remainingMinutes = minutes % 60;
        var parts = [];
        if (days) parts.push(days + "d");
        if (hours) parts.push(hours + "h");
        if (remainingMinutes) parts.push(remainingMinutes + "m");
        return parts.join(" ") || "Less than 1 min";
    }

    function clearAnalytics() {
        [
            { id: "monthlyChart", key: "monthly" },
            { id: "statusChart", key: "status" },
            { id: "priorityChart", key: "priority" },
            { id: "categoryChart", key: "category" },
            { id: "weekdayChart", key: "weekday" },
            { id: "dailyChart", key: "daily" },
            { id: "satisfactionChart", key: "satisfaction" }
        ].forEach(function (chart) {
            destroyChart(chart.key);
            displayEmpty(chart.id, "Analytics could not load. Please try again.");
        });
        $("#metricTotal, #metricOpen, #metricProgress, #metricResolved, #metricClosed, #metricCritical, #metricResolutionRate, #metricAverageResolution").text("—");
        $("#metricSatisfaction").text("—");
        $("#metricSatisfactionCount").text("0");
        $("#metricSatisfactionStars").text("☆☆☆☆☆").attr("aria-label", "No customer rating yet");
        $("#metricSatisfactionPositive").text("—");
        $("#analyticsUpdated").text("");
    }

    function loadAnalytics() {
        var range = $("#analyticsRange").val();
        var requestData = { range: range };

        if (range === "custom") {
            var startDate = $("#analyticsStartDate").val();
            var endDate = $("#analyticsEndDate").val();
            if (!startDate || !endDate || startDate > endDate) {
                $("#analyticsError").text("Choose a valid start and end date.").prop("hidden", false);
                return;
            }
            requestData.start_date = startDate;
            requestData.end_date = endDate;
        }

        var requestId = ++latestRequestId;
        loadedRange = null;
        $("#analyticsError").prop("hidden", true).text("");
        $("#applyAnalyticsRange, #analyticsExportForm button").prop("disabled", true);
        $.ajax({
            url: "api/analytics_stats.php",
            method: "GET",
            data: requestData,
            dataType: "json",
            cache: false
        }).done(function (response) {
            if (requestId !== latestRequestId) return;
            if (!response || !response.success || !response.data || !response.data.summary || !response.data.range) {
                clearAnalytics();
                $("#analyticsError").text("Analytics could not load. Please try again.").prop("hidden", false);
                return;
            }

            var data = response.data;
            var summary = data.summary;
            loadedRange = data.range.preset;
            loadedStartDate = data.range.start_date;
            loadedEndDate = data.range.end_date;

            $("#analyticsStartDate").val(loadedStartDate);
            $("#analyticsEndDate").val(loadedEndDate);
            $("#exportStartDate").val(loadedStartDate);
            $("#exportEndDate").val(loadedEndDate);
            $("#metricTotal").text(summary.total);
            $("#metricOpen").text(summary.open);
            $("#metricProgress").text(summary.progress);
            $("#metricResolved").text(summary.resolved);
            $("#metricClosed").text(summary.closed);
            $("#metricCritical").text(summary.critical);
            $("#metricResolutionRate").text(summary.resolution_rate + "%");
            var satisfaction = data.satisfaction || {};
            var averageRating = satisfaction.average_rating;
            $("#metricSatisfaction").text(
                averageRating === null || averageRating === undefined
                    ? "—"
                    : Number(averageRating).toFixed(1) + " / 5"
            );
            var ratingCount = Number(satisfaction.rating_count) || 0;
            var averageValue = Number(averageRating) || 0;
            $("#metricSatisfactionCount").text(ratingCount);
            $("#metricSatisfactionStars")
                .text("★".repeat(Math.max(0, Math.min(5, Math.round(averageValue)))) + "☆".repeat(Math.max(0, 5 - Math.round(averageValue))))
                .attr("aria-label", ratingCount > 0 ? averageValue.toFixed(1) + " out of 5 stars" : "No customer rating yet");
            var positiveCount = (satisfaction.distribution || []).reduce(function (total, row) {
                return total + (Number(row.rating) >= 4 ? Number(row.total) || 0 : 0);
            }, 0);
            $("#metricSatisfactionPositive").text(
                ratingCount > 0 ? Math.round((positiveCount / ratingCount) * 100) + "%" : "—"
            );

            if (summary.average_resolution_seconds === null) {
                $("#metricAverageResolution").text("Not available");
                $("#averageResolutionNote").text("No recorded resolution history");
            } else {
                $("#metricAverageResolution").text(formatDuration(summary.average_resolution_seconds));
                $("#averageResolutionNote").text("Based on recorded status changes");
            }

            renderCharts(data);
            var stamp = data.generated_at ? new Date(data.generated_at) : new Date();
            $("#analyticsUpdated").text("Updated " + stamp.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }));
        }).fail(function (xhr) {
            if (requestId !== latestRequestId) return;
            clearAnalytics();
            var message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : "Analytics could not load. Please try again.";
            $("#analyticsError").text(message).prop("hidden", false);
        }).always(function () {
            if (requestId === latestRequestId) {
                $("#applyAnalyticsRange, #analyticsExportForm button").prop("disabled", false);
            }
        });
    }

    $(function () {
        $("#analyticsRange").on("change", function () {
            var custom = $(this).val() === "custom";
            $("#analyticsStartDate, #analyticsEndDate").prop("disabled", !custom);
            if (!custom) loadAnalytics();
        });

        $("#applyAnalyticsRange").on("click", loadAnalytics);

        $("#analyticsExportForm").on("submit", function (event) {
            var range = $("#analyticsRange").val();
            if (!loadedRange || range !== loadedRange) {
                event.preventDefault();
                $("#analyticsError").text("Wait for analytics to finish loading before exporting.").prop("hidden", false);
                return;
            }

            if (range === "custom") {
                var startDate = $("#analyticsStartDate").val();
                var endDate = $("#analyticsEndDate").val();
                if (!startDate || !endDate || startDate > endDate) {
                    event.preventDefault();
                    $("#analyticsError").text("Choose a valid start and end date before exporting.").prop("hidden", false);
                    return;
                }
                if (range !== loadedRange || startDate !== loadedStartDate || endDate !== loadedEndDate) {
                    event.preventDefault();
                    $("#analyticsError").text("Apply the custom date range before exporting.").prop("hidden", false);
                    return;
                }
            }

            if (!loadedStartDate || !loadedEndDate) {
                event.preventDefault();
                $("#analyticsError").text("Wait for analytics to load before exporting.").prop("hidden", false);
                return;
            }
            $("#exportStartDate").val(loadedStartDate);
            $("#exportEndDate").val(loadedEndDate);
        });

        loadAnalytics();
    });
})(jQuery);

<?php

require_once "auth.php";

requireLogin();

if (!isSupportStaff()) {
    http_response_code(403);
    echo "Access denied. Support staff access required.";
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Activity Log | Samadhan</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/samadhan-logo.svg">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Arial,
                sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        /* =========================================
           TOP BAR
        ========================================= */

        .topbar {
            height: 70px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 32px;

            position: sticky;
            top: 0;
            z-index: 10;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 38px;
            height: 38px;

            border-radius: 10px;

            background: #2563eb;
            color: #ffffff;

            display: flex;
            align-items: center;
            justify-content: center;

            font-weight: 700;
            font-size: 16px;
        }

        .brand-name {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .admin-badge {
            padding: 8px 13px;

            border-radius: 20px;

            background: #eff6ff;
            color: #1d4ed8;

            font-size: 13px;
            font-weight: 600;
        }

        .logout-btn {
            text-decoration: none;

            color: #dc2626;

            font-size: 14px;
            font-weight: 600;
        }

        /* =========================================
           PAGE
        ========================================= */

        .page {
            width: min(100%, 1400px);
            max-width: 1400px;

            margin: 0 auto;

            padding: 32px;
        }

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;

            gap: 20px;

            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 7px;

            font-size: 28px;

            color: #111827;
        }

        .page-header p {
            margin: 0;

            color: #6b7280;

            font-size: 14px;
        }

        .back-btn {
            text-decoration: none;

            background: #ffffff;

            color: #374151;

            border: 1px solid #d1d5db;

            padding: 10px 16px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: 600;
        }

        .back-btn:hover {
            background: #f9fafb;
        }

        /* =========================================
           STAT CARDS
        ========================================= */

        .stats {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 18px;

            margin-bottom: 22px;
        }

        .stat-card {
            background: #ffffff;

            border: 1px solid #e5e7eb;

            border-radius: 12px;

            padding: 18px 20px;
        }

        .stat-label {
            color: #6b7280;

            font-size: 13px;

            margin-bottom: 7px;
        }

        .stat-value {
            color: #111827;

            font-size: 25px;

            font-weight: 700;
        }

        /* =========================================
           PANEL
        ========================================= */

        .panel {
            background: #ffffff;

            border: 1px solid #e5e7eb;

            border-radius: 12px;

            overflow: hidden;
        }

        /* =========================================
           TOOLBAR
        ========================================= */

        .toolbar {
            padding: 18px;

            border-bottom: 1px solid #e5e7eb;

            display: flex;

            gap: 12px;

            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;

            min-width: 260px;
        }

        .input,
        .select {
            width: 100%;

            height: 42px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            padding: 0 12px;

            font-size: 14px;

            outline: none;

            background: #ffffff;
        }

        .input:focus,
        .select:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);
        }

        .filter-box {
            width: 190px;
        }

        .clear-btn {
            height: 42px;

            padding: 0 16px;

            border: 1px solid #d1d5db;

            background: #ffffff;

            color: #374151;

            border-radius: 8px;

            cursor: pointer;

            font-weight: 600;
        }

        .clear-btn:hover {
            background: #f3f4f6;
        }

        /* =========================================
           TABLE
        ========================================= */

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 950px;
        }

        th {
            text-align: left;

            padding: 14px 16px;

            background: #f8fafc;

            color: #6b7280;

            font-size: 12px;

            text-transform: uppercase;

            letter-spacing: 0.03em;

            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 15px 16px;

            border-bottom: 1px solid #f0f1f3;

            font-size: 14px;

            vertical-align: middle;
        }

        tbody tr:hover {
            background: #fafbfc;
        }

        .id {
            color: #6b7280;

            font-weight: 600;
        }

        .user-name {
            color: #111827;

            font-weight: 600;
        }

        .user-role {
            color: #6b7280;

            font-size: 12px;

            margin-top: 3px;
        }

        .ticket {
            color: #2563eb;

            font-weight: 600;
        }

        /* =========================================
           ACTION BADGES
        ========================================= */

        .action {
            display: inline-flex;

            padding: 5px 9px;

            border-radius: 6px;

            font-size: 11px;

            font-weight: 700;

            letter-spacing: 0.02em;
        }

        .action-create {
            background: #ecfdf5;

            color: #047857;
        }

        .action-update {
            background: #eff6ff;

            color: #1d4ed8;
        }

        .action-status {
            background: #fff7ed;

            color: #c2410c;
        }

        .action-delete {
            background: #fef2f2;

            color: #b91c1c;
        }

        .action-login {
            background: #eff6ff;

            color: #1d4ed8;
        }

        .action-register {
            background: #f0fdfa;

            color: #0f766e;
        }

        .description {
            max-width: 470px;

            color: #4b5563;

            line-height: 1.45;
        }

        .date {
            white-space: nowrap;

            color: #6b7280;

            font-size: 13px;
        }

        /* =========================================
           STATES
        ========================================= */

        .loading,
        .empty,
        .error {
            text-align: center;

            padding: 45px 20px;

            color: #6b7280;
        }

        .error {
            color: #dc2626;
        }

        /* =========================================
           FOOTER
        ========================================= */

        .footer {
            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 16px 18px;

            gap: 15px;

            flex-wrap: wrap;
        }

        .record-info {
            color: #6b7280;

            font-size: 13px;
        }

        /* =========================================
           PAGINATION
        ========================================= */

        .pagination {
            display: flex;

            gap: 6px;

            align-items: center;
        }

        .page-btn {
            min-width: 36px;

            height: 36px;

            padding: 0 10px;

            border: 1px solid #d1d5db;

            border-radius: 7px;

            background: #ffffff;

            color: #374151;

            cursor: pointer;

            font-size: 13px;

            font-weight: 600;
        }

        .page-btn:hover:not(:disabled) {
            background: #f3f4f6;
        }

        .page-btn.active {
            background: #2563eb;

            color: #ffffff;

            border-color: #2563eb;
        }

        .page-btn:disabled {
            opacity: 0.45;

            cursor: not-allowed;
        }

        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 768px) {

            .topbar {
                padding: 0 18px;
            }

            .page {
                padding: 20px 15px;
            }

            .page-header {
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .filter-box {
                width: 100%;
            }

            .search-box {
                min-width: 100%;
            }

            .brand-name {
                font-size: 17px;
            }

            .admin-badge {
                display: none;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .toolbar {
                padding: 14px;
            }

            .toolbar .search-box,
            .toolbar .filter-box,
            .toolbar .clear-btn {
                width: 100%;
                min-width: 0;
                flex: 1 1 100%;
            }

            .toolbar .clear-btn {
                min-height: 42px;
            }

            .stat-card {
                min-width: 0;
                padding: 16px;
            }
        }

    </style>
    <link rel="stylesheet" href="assets/css/navigation.css?v=20">
    <link rel="stylesheet" href="assets/css/samadhan-theme.css?v=3">
    <script src="assets/js/page-transitions.js?v=5" defer></script>

</head>


<body class="app-page-body">


<!-- =========================================
     TOP BAR
========================================= -->

<?php
$activePage = "activity";
require "partials/navigation.php";
?>


<!-- =========================================
     MAIN PAGE
========================================= -->

<main class="page page-transition-surface">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div>

            <h1>
                Activity Log
            </h1>

            <p>
                Monitor important actions performed
                in Samadhan.
            </p>

        </div>


        <a
            href="index.php"
            class="back-btn"
        >

            ← Back to Dashboard

        </a>

    </div>


    <!-- =========================================
         STAT CARDS
    ========================================= -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-label">
                Total Activities
            </div>

            <div
                class="stat-value"
                id="totalActivities"
            >
                0
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Current Page
            </div>

            <div
                class="stat-value"
                id="currentPage"
            >
                1
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Records Per Page
            </div>

            <div class="stat-value">
                10
            </div>

        </div>


    </div>


    <!-- =========================================
         ACTIVITY PANEL
    ========================================= -->

    <section class="panel">


        <!-- TOOLBAR -->

        <div class="toolbar">


            <div class="search-box">

                <input
                    type="text"
                    id="activitySearch"
                    class="input"
                    placeholder="Search user, ticket, action or description..."
                >

            </div>


            <div class="filter-box">

                <select
                    id="actionFilter"
                    class="select"
                >

                    <option value="">
                        All Actions
                    </option>

                    <option value="CREATE">
                        Create
                    </option>

                    <option value="UPDATE">
                        Update
                    </option>

                    <option value="STATUS_CHANGE">
                        Status Change
                    </option>

                    <option value="ASSIGNMENT">
                        Assignment
                    </option>

                    <option value="DELETE">
                        Delete
                    </option>

                    <option value="LOGIN">
                        Sign In
                    </option>

                    <option value="REGISTER">
                        Company Registration
                    </option>

                </select>

            </div>


            <button
                type="button"
                id="clearFilters"
                class="clear-btn"
            >

                Clear

            </button>


        </div>


        <!-- TABLE -->

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            User
                        </th>

                        <th>
                            Ticket
                        </th>

                        <th>
                            Action
                        </th>

                        <th>
                            Description
                        </th>

                        <th>
                            Date & Time
                        </th>

                    </tr>

                </thead>


                <tbody id="activityTableBody">

                    <tr>

                        <td
                            colspan="6"
                            class="loading"
                        >

                            Loading activity logs...

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>


        <!-- FOOTER -->

        <div class="footer">


            <div
                class="record-info"
                id="recordInfo"
            >

                Loading...

            </div>


            <div
                class="pagination"
                id="pagination"
            >
            </div>


        </div>


    </section>


</main>

<?php require "partials/footer.php"; ?>

<script>

$(document).ready(function () {


    let currentPage = 1;

    let searchTimer = null;


    /* =========================================
       ESCAPE HTML
    ========================================= */

    function escapeHtml(value) {

        return $("<div>")
            .text(value ?? "")
            .html();

    }


    /* =========================================
       ACTION BADGE
    ========================================= */

    function actionBadge(action) {


        let className = "action-update";


        if (action === "CREATE") {

            className = "action-create";

        }


        if (action === "STATUS_CHANGE") {

            className = "action-status";

        }

        if (action === "ASSIGNMENT") {

            className = "action-status";

        }


        if (action === "DELETE") {

            className = "action-delete";

        }

        if (action === "LOGIN") {

            className = "action-login";

        }

        if (action === "REGISTER") {

            className = "action-register";

        }

        let label = {
            LOGIN: "Sign In",
            REGISTER: "Company Registration"
        }[action] || action.replace("_", " ");

        return `

            <span class="action ${className}">

                ${escapeHtml(label)}

            </span>

        `;

    }


    /* =========================================
       LOAD ACTIVITIES
    ========================================= */

    function loadActivities(page = 1) {


        currentPage = page;


        $("#activityTableBody").html(`

            <tr>

                <td
                    colspan="6"
                    class="loading"
                >

                    Loading activity logs...

                </td>

            </tr>

        `);


        $.ajax({

            url: "api/activity.php",

            type: "GET",

            dataType: "json",

            data: {

                search:
                    $("#activitySearch")
                    .val()
                    .trim(),

                action:
                    $("#actionFilter")
                    .val(),

                page: page

            },


            success: function (response) {


                if (!response.success) {

                    showError(
                        response.message ||
                        "Unable to load activity logs."
                    );

                    return;

                }


                renderActivities(
                    response.data || []
                );


                renderPagination(
                    response.pagination
                );

            },


            error: function (xhr) {


                console.error(
                    "Activity API Error:",
                    xhr.status,
                    xhr.responseText
                );


                if (xhr.status === 403) {

                    showError(
                        "Admin access required."
                    );

                } else {

                    showError(
                        "Unable to load activity logs."
                    );

                }

            }

        });

    }


    /* =========================================
       RENDER ACTIVITIES
    ========================================= */

    function renderActivities(activities) {


        if (!activities.length) {


            $("#activityTableBody").html(`

                <tr>

                    <td
                        colspan="6"
                        class="empty"
                    >

                        No activity records found.

                    </td>

                </tr>

            `);


            return;

        }


        let html = "";


        $.each(
            activities,
            function (index, item) {


                html += `

                    <tr>


                        <td class="id">

                            #

                            ${escapeHtml(
                                item.id
                            )}

                        </td>


                        <td>

                            <div class="user-name">

                                ${escapeHtml(
                                    item.user_name
                                )}

                            </div>


                            <div class="user-role">
                                ${escapeHtml({
                                    admin: "Main Admin",
                                    support: "Support Member",
                                    user: "Customer"
                                }[String(item.user_role || "").toLowerCase()] || item.user_role)}
                            </div>

                        </td>


                        <td class="ticket">

                            ${
                                item.ticket_id
                                ? "#" +
                                  escapeHtml(
                                      item.ticket_id
                                  )
                                : "—"
                            }

                        </td>


                        <td>

                            ${actionBadge(
                                item.action
                            )}

                        </td>


                        <td class="description">

                            ${escapeHtml(
                                item.description
                            )}

                        </td>


                        <td class="date">

                            ${escapeHtml(
                                item.created_at
                            )}

                        </td>


                    </tr>

                `;

            }
        );


        $("#activityTableBody")
            .html(html);

    }


    /* =========================================
       PAGINATION
    ========================================= */

    function renderPagination(
        pagination
    ) {


        if (!pagination) {

            return;

        }


        const totalRecords =
            Number(
                pagination.total_records || 0
            );


        const totalPages =
            Number(
                pagination.total_pages || 0
            );


        const page =
            Number(
                pagination.current_page || 1
            );


        $("#totalActivities")
            .text(totalRecords);


        $("#currentPage")
            .text(page);


        if (totalRecords === 0) {


            $("#recordInfo")
                .text("No records found.");


            $("#pagination")
                .empty();


            return;

        }


        const start =
            ((page - 1) * 10) + 1;


        const end =
            Math.min(
                page * 10,
                totalRecords
            );


        $("#recordInfo").text(

            `Showing ${start}-${end} of ${totalRecords} activities`

        );


        let html = "";


        html += `

            <button

                class="page-btn"

                data-page="${page - 1}"

                ${page <= 1 ? "disabled" : ""}

            >

                ←

            </button>

        `;


        for (
            let i = 1;
            i <= totalPages;
            i++
        ) {


            html += `

                <button

                    class="page-btn
                        ${
                            i === page
                            ? "active"
                            : ""
                        }
                    "

                    data-page="${i}"

                >

                    ${i}

                </button>

            `;

        }


        html += `

            <button

                class="page-btn"

                data-page="${page + 1}"

                ${
                    page >= totalPages
                    ? "disabled"
                    : ""
                }

            >

                →

            </button>

        `;


        $("#pagination")
            .html(html);

    }


    /* =========================================
       PAGINATION CLICK
    ========================================= */

    $(document).on(
        "click",
        ".page-btn",
        function () {


            if ($(this).prop("disabled")) {

                return;

            }


            const page =
                Number(
                    $(this).data("page")
                );


            if (page > 0) {

                loadActivities(page);

            }

        }
    );


    /* =========================================
       SEARCH
    ========================================= */

    $("#activitySearch").on(
        "input",
        function () {


            clearTimeout(
                searchTimer
            );


            searchTimer = setTimeout(
                function () {

                    loadActivities(1);

                },
                350
            );

        }
    );


    /* =========================================
       ACTION FILTER
    ========================================= */

    $("#actionFilter").on(
        "change",
        function () {

            loadActivities(1);

        }
    );


    /* =========================================
       CLEAR FILTERS
    ========================================= */

    $("#clearFilters").on(
        "click",
        function () {


            $("#activitySearch")
                .val("");


            $("#actionFilter")
                .val("");


            loadActivities(1);

        }
    );


    /* =========================================
       ERROR
    ========================================= */

    function showError(message) {


        $("#activityTableBody").html(`

            <tr>

                <td
                    colspan="6"
                    class="error"
                >

                    ${escapeHtml(message)}

                </td>

            </tr>

        `);


        $("#recordInfo")
            .text("");


        $("#pagination")
            .empty();

    }


    /* =========================================
       INITIAL LOAD
    ========================================= */

    loadActivities(1);


});

</script>


</body>

</html>
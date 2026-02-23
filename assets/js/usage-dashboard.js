/**
 * Usage Dashboard JavaScript
 * Fetches usage data via AJAX, manages loading/error/content states,
 * and provides retry functionality.
 * Renders stats table, summary cards, sorting, and pagination.
 */

(function($) {
    'use strict';

    // State
    var state = {
        isLoading: false,
        dateStart: tpUsageDashboard.dateRange.start,
        dateEnd: tpUsageDashboard.dateRange.end,
        data: null,
        sort: 'date:desc',
        currentPage: 1,
        pageSize: 10
    };

    // DOM cache
    var $skeleton,
        $error,
        $errorMsg,
        $content,
        $retryBtn,
        $adminError,
        $tableContainer,
        $tbody,
        $paginationInfo,
        $paginationList,
        $pagination,
        $summaryStrip,
        $emptyState,
        $emptyRange;

    /* ---------------------------------------------------------------
     * Cache DOM elements
     * ------------------------------------------------------------- */
    function cacheElements() {
        $skeleton       = $('#tp-ud-skeleton');
        $error          = $('#tp-ud-error');
        $errorMsg       = $('#tp-ud-error-msg');
        $content        = $('#tp-ud-content');
        $retryBtn       = $('#tp-ud-retry');
        $adminError     = $('#tp-ud-admin-error');
        $tableContainer = $('#tp-ud-table-container');
        $tbody          = $('#tp-ud-tbody');
        $paginationInfo = $('#tp-ud-pagination-info');
        $paginationList = $('#tp-ud-pagination-list');
        $pagination     = $('#tp-ud-pagination');
        $summaryStrip   = $('#tp-ud-summary-strip');
        $emptyState     = $('#tp-ud-empty');
        $emptyRange     = $('#tp-ud-empty-range');
    }

    /* ---------------------------------------------------------------
     * State toggle functions
     * ------------------------------------------------------------- */
    function showSkeleton() {
        $skeleton.show();
        $error.hide();
        $content.hide();
    }

    function hideSkeleton() {
        $skeleton.hide();
    }

    function showError(msg, responseData) {
        $errorMsg.text(msg);
        $error.show();

        // Admin error detail
        if ($adminError && $adminError.length) {
            $adminError.remove();
        }

        if (tpUsageDashboard.isAdmin && responseData && responseData.error_type) {
            var detailHtml = '<p class="tp-ud-error-detail text-muted small" id="tp-ud-admin-error">' +
                'Error type: ' + responseData.error_type;
            if (responseData.error_detail) {
                detailHtml += ' - ' + responseData.error_detail;
            }
            detailHtml += '</p>';
            $errorMsg.after(detailHtml);
            $adminError = $('#tp-ud-admin-error');
        }
    }

    function hideError() {
        $error.hide();
        if ($adminError && $adminError.length) {
            $adminError.remove();
        }
    }

    function showContent() {
        $content.show();
    }

    function hideContent() {
        $content.hide();
    }

    /* ---------------------------------------------------------------
     * Helper functions
     * ------------------------------------------------------------- */

    /**
     * Deterministic mock split of totalHits into clicks and QR scans.
     * Guarantees clicks + qr === totalHits (no rounding mismatch).
     */
    function splitHits(totalHits) {
        var qr = Math.round(totalHits * 0.3);
        var clicks = totalHits - qr;
        return { clicks: clicks, qr: qr };
    }

    /**
     * Safe currency formatting. Snaps to cents before toFixed to kill
     * floating-point drift (e.g. 0.1 + 0.2 !== 0.3).
     */
    function formatCurrency(value) {
        var rounded = Math.round(value * 100) / 100;
        var abs = Math.abs(rounded);
        var formatted = '$' + abs.toFixed(2);
        return rounded < 0 ? '-' + formatted : formatted;
    }

    /**
     * Relative date formatting.
     * Today, Yesterday, X days ago, then Mon DD, YYYY for older.
     */
    function formatDate(dateString) {
        if (!dateString) return '-';
        var date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        var now = new Date();
        var days = Math.floor((now - date) / (1000 * 60 * 60 * 24));
        if (days === 0) return 'Today';
        if (days === 1) return 'Yesterday';
        if (days < 7) return days + ' days ago';
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    /**
     * Format a date range for the empty state message.
     */
    function formatDateRange(startDate, endDate) {
        var start = new Date(startDate);
        var end = new Date(endDate);
        var opts = { year: 'numeric', month: 'short', day: 'numeric' };
        var startFormatted = start.toLocaleDateString(undefined, opts);
        var endFormatted = end.toLocaleDateString(undefined, opts);
        return startFormatted + ' to ' + endFormatted;
    }

    /* ---------------------------------------------------------------
     * Sorting
     * ------------------------------------------------------------- */

    /**
     * Return a sorted shallow copy of state.data based on state.sort.
     */
    function getSortedData() {
        var parts = state.sort.split(':');
        var field = parts[0];
        var dir = parts[1];
        var sorted = state.data.slice();

        sorted.sort(function(a, b) {
            var aVal = a[field];
            var bVal = b[field];

            if (field === 'date') {
                aVal = new Date(aVal).getTime();
                bVal = new Date(bVal).getTime();
            }

            var cmp = 0;
            if (aVal < bVal) cmp = -1;
            else if (aVal > bVal) cmp = 1;

            return dir === 'asc' ? cmp : -cmp;
        });

        return sorted;
    }

    /**
     * Update sort indicator icons on table headers.
     * Same pattern as client-links updateSortIndicators().
     */
    function updateSortIndicators() {
        var parts = state.sort.split(':');
        var field = parts[0];
        var dir = parts[1];

        $('.tp-ud-sortable').each(function() {
            var $th = $(this);
            var $icon = $th.find('.tp-ud-sort-icon');
            $th.removeClass('tp-ud-sort-active');
            $icon.removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');

            if ($th.data('sort') === field) {
                $th.addClass('tp-ud-sort-active');
                $icon.removeClass('fa-sort').addClass(dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
            }
        });
    }

    /* ---------------------------------------------------------------
     * Rendering functions
     * ------------------------------------------------------------- */

    /**
     * Master render: sorts data, paginates, renders rows + pagination + sort indicators.
     */
    function renderTable() {
        var sorted = getSortedData();
        var totalRecords = sorted.length;
        var totalPages = Math.ceil(totalRecords / state.pageSize);

        // Clamp current page
        if (state.currentPage > totalPages) {
            state.currentPage = totalPages;
        }
        if (state.currentPage < 1) {
            state.currentPage = 1;
        }

        var startIdx = (state.currentPage - 1) * state.pageSize;
        var pageData = sorted.slice(startIdx, startIdx + state.pageSize);

        renderRows(pageData);
        renderPagination(totalRecords, totalPages);
        updateSortIndicators();

        $tableContainer.show();
        $emptyState.hide();
    }

    /**
     * Render table rows for the current page of data.
     */
    function renderRows(pageData) {
        $tbody.empty();

        for (var i = 0; i < pageData.length; i++) {
            var day = pageData[i];
            var split = splitHits(day.totalHits);

            var row = '<tr>' +
                '<td class="tp-ud-col-date" data-label="Date"><span class="tp-ud-date">' + formatDate(day.date) + '</span></td>' +
                '<td class="tp-ud-col-hits" data-label="Hits">' +
                    '<div class="tp-ud-hits-cell">' +
                        '<span class="tp-ud-hits-total">' + day.totalHits.toLocaleString() + '</span>' +
                        '<span class="tp-ud-hits-breakdown">' +
                            '<i class="fas fa-mouse-pointer"></i> ' + split.clicks.toLocaleString() +
                            ' <i class="fas fa-qrcode ms-1"></i> ' + split.qr.toLocaleString() +
                        '</span>' +
                    '</div>' +
                '</td>' +
                '<td class="tp-ud-col-cost" data-label="Cost"><span class="tp-ud-cost">' + formatCurrency(day.hitCost) + '</span></td>' +
                '<td class="tp-ud-col-balance" data-label="Balance"><span class="tp-ud-balance">' + formatCurrency(day.balance) + '</span></td>' +
            '</tr>';

            $tbody.append(row);
        }
    }

    /**
     * Windowed pagination with maxVisible=5.
     * Same algorithm as client-links renderPagination().
     */
    function renderPagination(totalRecords, totalPages) {
        $paginationList.empty();

        if (totalPages <= 1) {
            $pagination.hide();
            $paginationInfo.text(totalRecords + ' day' + (totalRecords !== 1 ? 's' : ''));
            return;
        }

        $pagination.show();

        var maxVisible = 5;
        var startPage = Math.max(1, state.currentPage - Math.floor(maxVisible / 2));
        var endPage = Math.min(totalPages, startPage + maxVisible - 1);
        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        // Prev
        $paginationList.append(
            '<li class="page-item ' + (state.currentPage === 1 ? 'disabled' : '') + '">' +
                '<a class="page-link" href="#" data-page="' + (state.currentPage - 1) + '"><i class="fas fa-chevron-left"></i></a>' +
            '</li>'
        );

        // First page + ellipsis
        if (startPage > 1) {
            $paginationList.append('<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>');
            if (startPage > 2) {
                $paginationList.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
            }
        }

        // Page numbers
        for (var i = startPage; i <= endPage; i++) {
            $paginationList.append(
                '<li class="page-item ' + (i === state.currentPage ? 'active' : '') + '">' +
                    '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>' +
                '</li>'
            );
        }

        // Last page + ellipsis
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                $paginationList.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
            }
            $paginationList.append('<li class="page-item"><a class="page-link" href="#" data-page="' + totalPages + '">' + totalPages + '</a></li>');
        }

        // Next
        $paginationList.append(
            '<li class="page-item ' + (state.currentPage === totalPages ? 'disabled' : '') + '">' +
                '<a class="page-link" href="#" data-page="' + (state.currentPage + 1) + '"><i class="fas fa-chevron-right"></i></a>' +
            '</li>'
        );

        // Info text
        var start = (state.currentPage - 1) * state.pageSize + 1;
        var end = Math.min(state.currentPage * state.pageSize, totalRecords);
        $paginationInfo.text('Showing ' + start + '-' + end + ' of ' + totalRecords + ' days');
    }

    /**
     * Build HTML for a single stat card.
     */
    function buildStatCard(icon, value, label, secondary) {
        return '<div class="tp-ud-stat-card">' +
            '<div class="tp-ud-stat-icon"><i class="fas ' + icon + '"></i></div>' +
            '<div class="tp-ud-stat-body">' +
                '<div class="tp-ud-stat-value">' + value + '</div>' +
                '<div class="tp-ud-stat-label">' + label + '</div>' +
                '<div class="tp-ud-stat-secondary">' + secondary + '</div>' +
            '</div>' +
        '</div>';
    }

    /**
     * Render summary cards with aggregated totals.
     * Uses integer-cent accumulation to avoid floating-point drift.
     */
    function renderSummaryCards(data) {
        if (!data || data.length === 0) {
            $summaryStrip.hide();
            return;
        }

        var totalHits = 0;
        var totalCostCents = 0;

        for (var i = 0; i < data.length; i++) {
            totalHits += data[i].totalHits;
            totalCostCents += Math.round(data[i].hitCost * 100);
        }

        var totalCost = totalCostCents / 100;
        var latestBalance = data[data.length - 1].balance;
        var dailyAvg = data.length > 0 ? Math.round(totalHits / data.length) : 0;

        var html = buildStatCard('fa-chart-line', totalHits.toLocaleString(), 'Total Hits', '~' + dailyAvg.toLocaleString() + '/day');
        html += buildStatCard('fa-dollar-sign', formatCurrency(totalCost), 'Total Cost', data.length + ' days');
        html += buildStatCard('fa-wallet', formatCurrency(latestBalance), 'Balance', 'Current');

        $summaryStrip.html(html).show();
    }

    /**
     * Show empty state with queried date range.
     */
    function showEmptyState() {
        $tableContainer.hide();
        $summaryStrip.hide();
        $emptyRange.text('No activity from ' + formatDateRange(state.dateStart, state.dateEnd));
        $emptyState.show();
    }

    /* ---------------------------------------------------------------
     * Load data via AJAX
     * ------------------------------------------------------------- */
    function loadData() {
        if (state.isLoading) {
            return;
        }

        state.isLoading = true;
        showSkeleton();
        hideError();
        hideContent();

        $.ajax({
            url: tpUsageDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tp_get_usage_summary',
                nonce: tpUsageDashboard.nonce,
                start_date: state.dateStart,
                end_date: state.dateEnd
                // NOTE: No uid field -- server determines it (DATA-02)
            },
            timeout: 20000,
            success: function(response) {
                state.isLoading = false;

                if (response.success && response.data && response.data.days) {
                    state.data = response.data.days;
                    hideSkeleton();

                    if (state.data.length === 0) {
                        showContent();
                        showEmptyState();
                    } else {
                        state.currentPage = 1;
                        showContent();
                        renderSummaryCards(state.data);
                        renderTable();
                    }
                } else {
                    // Server returned error response
                    hideSkeleton();
                    var msg = tpUsageDashboard.strings.error;
                    var responseData = null;

                    if (response.data) {
                        msg = response.data.message || msg;
                        responseData = response.data;
                    }

                    showError(msg, responseData);
                }
            },
            error: function() {
                state.isLoading = false;
                hideSkeleton();
                showError(tpUsageDashboard.strings.error, null);
            }
        });
    }

    /* ---------------------------------------------------------------
     * Event binding
     * ------------------------------------------------------------- */
    function bindEvents() {
        // Retry button re-fetches without page reload
        $retryBtn.on('click', function() {
            loadData();
        });

        // Sort handler (delegated -- survives DOM re-renders)
        $(document).on('click', '.tp-ud-sortable', function() {
            var field = $(this).data('sort');
            if (!field) return;

            var parts = state.sort.split(':');
            if (parts[0] === field) {
                state.sort = field + ':' + (parts[1] === 'asc' ? 'desc' : 'asc');
            } else {
                state.sort = field + ':asc';
            }
            state.currentPage = 1;
            renderTable();
        });

        // Pagination handler (delegated)
        $(document).on('click', '#tp-ud-pagination-list .page-link', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            if (page && page !== state.currentPage && page >= 1) {
                state.currentPage = page;
                renderTable();
            }
        });
    }

    /* ---------------------------------------------------------------
     * Document ready
     * ------------------------------------------------------------- */
    $(document).ready(function() {
        cacheElements();
        bindEvents();
        loadData();
    });

})(jQuery);

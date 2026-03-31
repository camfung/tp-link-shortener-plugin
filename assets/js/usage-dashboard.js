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
        currentWalletBalance: null,
        sort: 'date:desc',
        currentPage: 1,
        pageSize: 10,
        chart: null
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
        $emptyRange,
        $dateStart,
        $dateEnd,
        $dateApply,
        $customToggle,
        $customPanel,
        $dateDisplay;

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
        $dateStart      = $('#tp-ud-date-start');
        $dateEnd        = $('#tp-ud-date-end');
        $dateApply      = $('#tp-ud-date-apply');
        $customToggle   = $('#tp-ud-custom-toggle');
        $customPanel    = $('#tp-ud-custom-panel');
        $dateDisplay    = $('#tp-ud-date-display');
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
     * Escape HTML entities to prevent XSS in dynamic content.
     */
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Escape string for use inside an HTML attribute value.
     */
    function escapeAttr(str) {
        return escapeHtml(str);
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

    /**
     * Format a Date object as YYYY-MM-DD using UTC.
     * The API stores data in UTC, so all date math must use UTC
     * to avoid timezone drift for users in non-UTC timezones.
     */
    function formatDateISO(date) {
        var y = date.getUTCFullYear();
        var m = String(date.getUTCMonth() + 1).padStart(2, '0');
        var d = String(date.getUTCDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    /**
     * Initialize date inputs: set values from state, enforce max=today,
     * and highlight the matching preset button (if any).
     */
    function initDateInputs() {
        var today = formatDateISO(new Date());

        // Populate inputs from state defaults (matches client-links.js:92-96)
        $dateStart.val(state.dateStart);
        $dateEnd.val(state.dateEnd);

        // Enforce max=today (UTC) on both inputs
        $dateStart.attr('max', today);
        $dateEnd.attr('max', today);

        // Highlight the preset button whose date range matches the current state.
        // This respects the shortcode `days` attribute (not always 30).
        if (state.dateEnd === today) {
            $('.tp-ud-preset-btn').each(function() {
                var days = parseInt($(this).data('days'), 10);
                if (!days) return; // skip Custom button
                var presetStart = new Date();
                presetStart.setUTCDate(presetStart.getUTCDate() - days);
                if (formatDateISO(presetStart) === state.dateStart) {
                    $(this).addClass('active');
                }
            });
        }

        updateDateDisplay();
    }

    /**
     * Update the human-readable date range display next to the preset pills.
     */
    function updateDateDisplay() {
        if (!$dateDisplay || !$dateDisplay.length) return;
        $dateDisplay.text(formatDateRange(state.dateStart, state.dateEnd));
    }

    /* ---------------------------------------------------------------
     * Other Services helpers
     * ------------------------------------------------------------- */

    /**
     * Build tooltip HTML content from an array of wallet transaction items.
     * Single item: just the escaped description.
     * Multiple items: each as "Description (+$amount)" on separate lines.
     */
    function buildTooltipContent(items) {
        if (!items || items.length === 0) return '';
        if (items.length === 1) {
            return escapeHtml(items[0].description);
        }
        return items.map(function(item) {
            return escapeHtml(item.description) + ' (+' + formatCurrency(item.amount) + ')';
        }).join('<br>');
    }

    /**
     * Build the Other Services table cell for a given day record.
     * Null-safe: returns $0.00 if otherServices is null or amount is 0.
     */
    function buildOtherServicesCell(day) {
        var os = day.otherServices;
        if (!os || !os.amount || os.amount <= 0) {
            return '<td class="tp-ud-col-other" data-label="Credits"><span class="tp-ud-other-zero">$0.00</span></td>';
        }
        var tooltipHtml = buildTooltipContent(os.items);
        return '<td class="tp-ud-col-other" data-label="Credits">' +
            '<span class="tp-ud-other-amount"' +
            ' data-bs-toggle="tooltip"' +
            ' data-bs-html="true"' +
            ' data-bs-title="' + escapeAttr(tooltipHtml) + '"' +
            '>+' + formatCurrency(os.amount) + '</span></td>';
    }

    /**
     * Dispose all Bootstrap tooltips inside the table body.
     * Must be called before emptying tbody to prevent memory leaks.
     */
    function disposeTooltips() {
        if (!$tbody || !$tbody.length) return;
        $tbody.find('[data-bs-toggle="tooltip"]').each(function() {
            var instance = bootstrap.Tooltip.getInstance(this);
            if (instance) {
                instance.dispose();
            }
        });
    }

    /**
     * Initialize Bootstrap tooltips on newly rendered Other Services cells.
     */
    function initTooltips() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
        $('#tp-ud-tbody [data-bs-toggle="tooltip"]').each(function() {
            new bootstrap.Tooltip(this, {
                trigger: 'hover focus',
                placement: 'top',
                container: 'body'
            });
        });
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
            } else if (field === 'otherServices') {
                aVal = (a.otherServices && a.otherServices.amount) || 0;
                bVal = (b.otherServices && b.otherServices.amount) || 0;
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
        disposeTooltips();
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
                buildOtherServicesCell(day) +
                '<td class="tp-ud-col-cost" data-label="Cost"><span class="tp-ud-cost">' + formatCurrency(day.hitCost) + '</span></td>' +
                '<td class="tp-ud-col-balance" data-label="Balance"><span class="tp-ud-balance">' + (day.balance != null ? formatCurrency(day.balance) : '--') + '</span></td>' +
            '</tr>';

            $tbody.append(row);
        }

        initTooltips();
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
     * Balance comes from the authoritative wallet balance, not from row data.
     */
    function renderSummaryCards(data) {
        if (!data || data.length === 0) {
            $summaryStrip.hide();
            return;
        }

        var totalHits = 0;
        var totalCostCents = 0;
        var creditsCents = 0;
        var daysWithCredits = 0;

        for (var i = 0; i < data.length; i++) {
            totalHits += data[i].totalHits;
            totalCostCents += Math.round(data[i].hitCost * 100);

            var os = data[i].otherServices;
            if (os && os.amount && os.amount > 0) {
                creditsCents += Math.round(os.amount * 100);
                daysWithCredits++;
            }
        }

        var totalCost = totalCostCents / 100;
        var creditsTotal = creditsCents / 100;
        var dailyAvg = data.length > 0 ? Math.round(totalHits / data.length) : 0;

        // Balance from authoritative wallet source, not row data
        var balanceDisplay = state.currentWalletBalance !== null
            ? formatCurrency(state.currentWalletBalance)
            : '--';

        var html = buildStatCard('fa-wallet', balanceDisplay, 'Current Balance', 'Wallet');
        html += buildStatCard('fa-chart-line', totalHits.toLocaleString(), 'Total Hits', '~' + dailyAvg.toLocaleString() + '/day');
        html += buildStatCard('fa-dollar-sign', formatCurrency(totalCost), 'Total Cost', data.length + ' days');
        html += buildStatCard('fa-hand-holding-dollar', '+' + formatCurrency(creditsTotal), 'Credits', daysWithCredits + ' day' + (daysWithCredits !== 1 ? 's' : '') + ' with credits');

        $summaryStrip.html(html).show();
    }

    /**
     * Render area chart with stacked clicks and QR scans series.
     * Uses Chart.js type:'line' with fill:'origin' for area effect.
     * Manages canvas lifecycle: destroy before recreate (CHART-03).
     */
    function renderChart(data) {
        var ctx = document.getElementById('tp-ud-chart');
        if (!ctx) return;
        if (typeof Chart === 'undefined') return;

        // Destroy previous instance to prevent "Canvas already in use" error
        if (state.chart) {
            state.chart.destroy();
            state.chart = null;
        }

        // Empty data: leave canvas blank
        if (!data || data.length === 0) {
            return;
        }

        // Build arrays from data (already sorted by date from API)
        var labels = [];
        var clicksData = [];
        var qrData = [];

        for (var i = 0; i < data.length; i++) {
            labels.push(data[i].date);
            var split = splitHits(data[i].totalHits);
            clicksData.push(split.clicks);
            qrData.push(split.qr);
        }

        state.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Clicks',
                        data: clicksData,
                        borderColor: '#f5a623',
                        backgroundColor: 'rgba(245, 166, 35, 0.15)',
                        fill: 'origin',
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: '#f5a623',
                        pointBorderColor: '#f5a623',
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        order: 2
                    },
                    {
                        label: 'QR Scans',
                        data: qrData,
                        borderColor: '#22b573',
                        backgroundColor: 'rgba(34, 181, 115, 0.12)',
                        fill: 'origin',
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: '#22b573',
                        pointBorderColor: '#22b573',
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: "'Poppins', sans-serif", size: 12 },
                            usePointStyle: true,
                            padding: 16
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(30, 47, 80, 0.9)',
                        titleFont: { family: "'Poppins', sans-serif", size: 13 },
                        bodyFont: { family: "'Poppins', sans-serif", size: 12 },
                        padding: 10,
                        cornerRadius: 6
                    }
                },
                scales: {
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: { size: 11 }
                        },
                        grid: {
                            color: 'rgba(207, 226, 255, 0.4)'
                        }
                    },
                    x: {
                        ticks: {
                            font: { size: 11 },
                            maxRotation: 45,
                            minRotation: 0,
                            maxTicksLimit: 15
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
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
    function loadData(silent) {
        if (state.isLoading) {
            return;
        }

        state.isLoading = true;
        hideError();

        if (silent) {
            // Keep existing content visible, just dim it
            $content.css('opacity', '.5').css('pointer-events', 'none');
        } else {
            showSkeleton();
            hideContent();
        }

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
                $content.css('opacity', '').css('pointer-events', '');

                if (response.success && response.data && response.data.days) {
                    state.data = response.data.days;
                    var rawBalance = response.data.currentWalletBalance;
                    var parsed = rawBalance != null ? parseFloat(rawBalance) : NaN;
                    state.currentWalletBalance = isFinite(parsed) ? parsed : null;
                    hideSkeleton();

                    if (state.data.length === 0) {
                        showContent();
                        showEmptyState();
                        renderChart([]);
                    } else {
                        state.currentPage = 1;
                        showContent();
                        renderSummaryCards(state.data);
                        renderChart(state.data);
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
                $content.css('opacity', '').css('pointer-events', '');
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

        // Custom panel toggle
        $customToggle.on('click', function() {
            var isOpen = $customPanel.is(':visible');
            if (isOpen) {
                $customPanel.slideUp(200);
                $(this).removeClass('active');
            } else {
                $customPanel.slideDown(200);
                // Mark Custom as active, clear preset highlights
                $('.tp-ud-preset-btn').not(this).removeClass('active');
                $(this).addClass('active');
            }
        });

        // Apply button -- reads date inputs, validates, updates state, reloads
        $dateApply.on('click', function() {
            var newStart = $dateStart.val();
            var newEnd = $dateEnd.val();

            // Reject empty dates
            if (!newStart || !newEnd) {
                return;
            }

            // Auto-swap if start > end (ISO date strings compare lexicographically)
            if (newStart > newEnd) {
                var tmp = newStart;
                newStart = newEnd;
                newEnd = tmp;
                $dateStart.val(newStart);
                $dateEnd.val(newEnd);
            }

            state.dateStart = newStart;
            state.dateEnd = newEnd;
            state.currentPage = 1;

            // Clear preset active state, keep Custom highlighted
            $('.tp-ud-preset-btn').not($customToggle).removeClass('active');

            updateDateDisplay();
            loadData(true);
        });

        // Preset buttons (delegated -- skips Custom trigger)
        $(document).on('click', '.tp-ud-preset-btn[data-days]', function() {
            var days = parseInt($(this).data('days'), 10);
            var today = new Date();
            var start = new Date();
            start.setUTCDate(today.getUTCDate() - days);

            var endStr = formatDateISO(today);
            var startStr = formatDateISO(start);

            $dateStart.val(startStr);
            $dateEnd.val(endStr);
            state.dateStart = startStr;
            state.dateEnd = endStr;
            state.currentPage = 1;

            // Update active state and collapse custom panel
            $('.tp-ud-preset-btn').removeClass('active');
            $(this).addClass('active');
            $customPanel.slideUp(200);

            updateDateDisplay();
            loadData(true);
        });

        // Clear preset active state when user manually edits date inputs
        $dateStart.add($dateEnd).on('change', function() {
            $('.tp-ud-preset-btn').not($customToggle).removeClass('active');
        });

        // ── Wallet Modal ──────────────────────────────────────
        // Amount preset selection
        $(document).on('click', '.tp-ud-wallet-amt-btn', function() {
            $('.tp-ud-wallet-amt-btn').removeClass('tp-ud-wallet-amt-selected');
            $(this).addClass('tp-ud-wallet-amt-selected');
            $('#tp-ud-wallet-custom').val('');
        });

        // Custom input clears preset selection
        $('#tp-ud-wallet-custom').on('input', function() {
            if ($(this).val()) {
                $('.tp-ud-wallet-amt-btn').removeClass('tp-ud-wallet-amt-selected');
            }
        });

        // Populate wallet modal when opened
        $('#tp-ud-wallet-modal').on('show.bs.modal', function() {
            // Set balance from state
            var balanceText = state.currentWalletBalance !== null
                ? formatCurrency(state.currentWalletBalance)
                : '--';
            $('#tp-ud-wallet-balance').text(balanceText);

            // Load recent transactions
            loadWalletTransactions();
        });

        // Add Funds button — AJAX add-to-cart then redirect to checkout
        $('#tp-ud-wallet-add-btn').on('click', function() {
            var $button = $(this);
            var $message = $('#tp-ud-wallet-topup-message');
            var $status = $('#tp-ud-wallet-topup-status');
            var $checkoutLink = $('#tp-ud-wallet-checkout-link');
            var $customInput = $('#tp-ud-wallet-custom');
            var $presetButtons = $('.tp-ud-wallet-amt-btn');
            var $selected = $('.tp-ud-wallet-amt-selected');
            var customVal = $customInput.val();
            var amount = 0;
            var minAmount = 5;
            var maxAmount = 500;
            var originalLabel = $button.data('original-label') || $.trim($button.text());
            var fallbackTimer = null;

            function resetUi() {
                if (fallbackTimer) { window.clearTimeout(fallbackTimer); fallbackTimer = null; }
                $button.prop('disabled', false).text(originalLabel);
                $customInput.prop('disabled', false);
                $presetButtons.prop('disabled', false);
                $status.hide().text('');
            }

            $button.data('original-label', originalLabel);
            $message.hide().text('');
            $status.hide().text('');
            $checkoutLink.hide().attr('href', '#');

            if (customVal !== '') {
                amount = parseFloat(customVal);
            } else if ($selected.length) {
                amount = parseFloat($selected.data('amount'));
            }

            if (!amount || isNaN(amount)) {
                $message.text('Please select or enter an amount.').show();
                return;
            }
            if (amount < minAmount) {
                $message.text('Minimum top-up amount is $5.00.').show();
                return;
            }
            if (amount > maxAmount) {
                $message.text('Maximum top-up amount is $500.00.').show();
                return;
            }

            // Enter loading state — keep modal open
            $button.prop('disabled', true).text('Redirecting to checkout...');
            $customInput.prop('disabled', true);
            $presetButtons.prop('disabled', true);
            $status.text('Redirecting to checkout...').show();

            // Fallback link after 4 seconds
            fallbackTimer = window.setTimeout(function() {
                $checkoutLink.show();
            }, 4000);

            $.ajax({
                url: tpUsageDashboard.ajaxUrl,
                type: 'POST',
                timeout: 15000,
                data: {
                    action: 'tp_wallet_topup_checkout',
                    nonce: tpUsageDashboard.nonce,
                    amount: amount.toFixed(2)
                }
            }).done(function(response) {
                if (response && response.success && response.data && response.data.checkout_url) {
                    $checkoutLink.attr('href', response.data.checkout_url).show();
                    window.location.href = response.data.checkout_url;
                    return;
                }
                resetUi();
                $message.text(
                    response && response.data && response.data.message
                        ? response.data.message
                        : 'Could not start checkout. Please try again.'
                ).show();
            }).fail(function(xhr) {
                if (xhr && (xhr.status === 401 || xhr.status === 403)) {
                    if (fallbackTimer) { window.clearTimeout(fallbackTimer); }
                    $status.text('Your session expired. Reloading...').show();
                    window.setTimeout(function() { window.location.reload(); }, 1000);
                    return;
                }
                resetUi();
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    $message.text(xhr.responseJSON.data.message).show();
                    return;
                }
                $message.text('Could not start checkout. Please try again.').show();
            });
        });
    }

    /**
     * Load recent wallet transactions into the modal table.
     */
    function loadWalletTransactions() {
        var $tbody = $('#tp-ud-wallet-tx-body');
        $tbody.html('<tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: tpUsageDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tp_wallet_transactions',
                nonce: tpUsageDashboard.nonce,
                per_page: 10,
                page: 1
            },
            timeout: 15000,
            success: function(response) {
                var txList = response.data && response.data.transactions
                    ? response.data.transactions
                    : (Array.isArray(response.data) ? response.data : []);
                if (response.success && txList.length > 0) {
                    var html = '';
                    for (var i = 0; i < txList.length; i++) {
                        var tx = txList[i];
                        var amountClass = tx.type === 'credit'
                            ? 'tp-ud-wallet-tx-credit'
                            : 'tp-ud-wallet-tx-debit';
                        var prefix = tx.type === 'credit' ? '+' : '-';
                        var dateStr = tx.date ? tx.date.substring(0, 10) : '';
                        html += '<tr>' +
                            '<td>' + escapeHtml(dateStr) + '</td>' +
                            '<td>' + escapeHtml(tx.details || tx.description || '') + '</td>' +
                            '<td class="text-right ' + amountClass + '">' + prefix + formatCurrency(Math.abs(tx.amount)) + '</td>' +
                            '</tr>';
                    }
                    $tbody.html(html);
                } else {
                    $tbody.html('<tr><td colspan="3" class="text-center text-muted">No transactions found.</td></tr>');
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="3" class="text-center text-muted">Could not load transactions.</td></tr>');
            }
        });
    }

    /* ---------------------------------------------------------------
     * Document ready
     * ------------------------------------------------------------- */
    $(document).ready(function() {
        cacheElements();
        initDateInputs();
        bindEvents();
        loadData();

    });

})(jQuery);

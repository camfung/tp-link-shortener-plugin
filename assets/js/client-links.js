/**
 * Client Links JavaScript
 * Link management page with sortable columns, date range filtering,
 * per-link performance chart, and change history.
 */

(function($) {
    'use strict';

    // State
    var state = {
        currentPage: 1,
        pageSize: 10,
        totalRecords: 0,
        totalPages: 0,
        sort: 'updated_at:desc',
        status: '',
        search: '',
        isLoading: false,
        searchDebounceTimer: null,
        items: [],
        dateStart: '',
        dateEnd: '',
        chart: null
    };

    // DOM cache
    var $container,
        $content,
        $loading,
        $skeletonTbody,
        $error,
        $empty,
        $tableWrapper,
        $tbody,
        $paginationInfo,
        $paginationList,
        $totalCount,
        $searchInput,
        $statusFilter,
        $refreshBtn,
        $addLinkBtn,
        $retryBtn,
        $searchClear,
        $copyTooltip,
        $chartWrapper,
        $dateStart,
        $dateEnd,
        $dateApply,
        // QR dialog
        $qrDialogOverlay,
        $qrDialogClose,
        $qrContainer,
        $qrUrl,
        $qrDownloadBtn,
        $qrCopyBtn,
        $qrOpenBtn,
        // Edit modal
        $editModalOverlay,
        $editModalClose,
        $editModalBody,
        $editModalTitle,
        $formPlaceholder,
        // History modal
        $historyModalOverlay,
        $historyModalClose,
        $historyList,
        currentQrUrl = null;

    /* ---------------------------------------------------------------
     * Init
     * ------------------------------------------------------------- */
    function init() {
        $container = $('.tp-cl-container');
        if (!$container.length) return;

        cacheDom();

        state.pageSize = parseInt($container.data('page-size')) || 10;
        generateSkeletonRows(state.pageSize);

        if (!tpClientLinks.isLoggedIn) {
            $content.hide();
            return;
        }

        // Set default date range
        state.dateStart = tpClientLinks.dateRange.start;
        state.dateEnd = tpClientLinks.dateRange.end;
        $dateStart.val(state.dateStart);
        $dateEnd.val(state.dateEnd);

        // Hide the shortener form on the page — only accessible via modal
        var $formWrapper = $('#tp-link-shortener-wrapper');
        if ($formWrapper.length) {
            $formPlaceholder = $('<div id="tp-cl-form-placeholder" style="display:none;"></div>');
            $formWrapper.before($formPlaceholder);
            $formWrapper.hide();
        }

        bindEvents();
        loadData();
    }

    function cacheDom() {
        $content         = $('#tp-cl-content');
        $loading         = $('#tp-cl-loading');
        $skeletonTbody   = $('#tp-cl-skeleton-tbody');
        $error           = $('#tp-cl-error');
        $empty           = $('#tp-cl-empty');
        $tableWrapper    = $('#tp-cl-table-wrapper');
        $tbody           = $('#tp-cl-tbody');
        $paginationInfo  = $('#tp-cl-pagination-info');
        $paginationList  = $('#tp-cl-pagination-list');
        $totalCount      = $('#tp-cl-total-count');
        $searchInput     = $('#tp-cl-search');
        $statusFilter    = $('#tp-cl-filter-status');
        $refreshBtn      = $('#tp-cl-refresh-btn');
        $addLinkBtn      = $('#tp-cl-add-link-btn');
        $retryBtn        = $('#tp-cl-retry-btn');
        $searchClear     = $('#tp-cl-search-clear');
        $copyTooltip     = $('#tp-cl-copy-tooltip');
        $chartWrapper    = $('#tp-cl-chart-wrapper');
        $dateStart       = $('#tp-cl-date-start');
        $dateEnd         = $('#tp-cl-date-end');
        $dateApply       = $('#tp-cl-date-apply');

        // QR
        $qrDialogOverlay = $('#tp-cl-qr-dialog-overlay');
        $qrDialogClose   = $('#tp-cl-qr-dialog-close');
        $qrContainer     = $('#tp-cl-qr-container');
        $qrUrl           = $('#tp-cl-qr-url');
        $qrDownloadBtn   = $('#tp-cl-qr-download-btn');
        $qrCopyBtn       = $('#tp-cl-qr-copy-btn');
        $qrOpenBtn       = $('#tp-cl-qr-open-btn');

        // Edit modal
        $editModalOverlay = $('#tp-cl-edit-modal-overlay');
        $editModalClose   = $('#tp-cl-edit-modal-close');
        $editModalBody    = $('#tp-cl-edit-modal-body');
        $editModalTitle   = $editModalOverlay.find('.tp-cl-modal-title');

        // History modal
        $historyModalOverlay = $('#tp-cl-history-modal-overlay');
        $historyModalClose   = $('#tp-cl-history-modal-close');
        $historyList         = $('#tp-cl-history-list');
    }

    /* ---------------------------------------------------------------
     * Skeleton loader
     * ------------------------------------------------------------- */
    function generateSkeletonRows(count) {
        $skeletonTbody.empty();
        for (var i = 0; i < count; i++) {
            $skeletonTbody.append(
                '<tr class="tp-cl-skeleton-row">' +
                    '<td class="tp-cl-col-link"><div class="tp-cl-skel tp-cl-skel-lg"></div></td>' +
                    '<td class="tp-cl-col-dest"><div class="tp-cl-skel tp-cl-skel-lg"></div><div class="tp-cl-skel tp-cl-skel-xs"></div></td>' +
                    '<td class="tp-cl-col-clicks"><div class="tp-cl-skel tp-cl-skel-md" style="width:50px"></div></td>' +
                    '<td class="tp-cl-col-date"><div class="tp-cl-skel tp-cl-skel-md" style="width:80px"></div></td>' +
                    '<td class="tp-cl-col-status"><div class="tp-cl-skel tp-cl-skel-sm" style="width:60px"></div></td>' +
                '</tr>'
            );
        }
    }

    /* ---------------------------------------------------------------
     * Events
     * ------------------------------------------------------------- */
    function bindEvents() {
        // Search with debounce
        $searchInput.on('input', function() {
            clearTimeout(state.searchDebounceTimer);
            state.searchDebounceTimer = setTimeout(function() {
                state.search = $searchInput.val().trim();
                state.currentPage = 1;
                loadData();
            }, 300);
        });

        $searchClear.on('click', function() {
            $searchInput.val('');
            state.search = '';
            state.currentPage = 1;
            loadData();
        });

        // Status filter
        $statusFilter.on('change', function() {
            state.status = $(this).val();
            state.currentPage = 1;
            loadData();
        });

        // Sortable column headers
        $(document).on('click', '.tp-cl-sortable', function() {
            var field = $(this).data('sort');
            if (!field) return;

            var parts = state.sort.split(':');
            if (parts[0] === field) {
                // Toggle direction
                state.sort = field + ':' + (parts[1] === 'asc' ? 'desc' : 'asc');
            } else {
                state.sort = field + ':asc';
            }
            state.currentPage = 1;
            updateSortIndicators();
            loadData();
        });

        // Date range
        $dateApply.on('click', function() {
            state.dateStart = $dateStart.val();
            state.dateEnd = $dateEnd.val();
            state.currentPage = 1;
            loadData();
        });

        // Refresh
        $refreshBtn.on('click', function() { loadData(); });
        $retryBtn.on('click', function() { loadData(); });

        // Pagination
        $paginationList.on('click', '.page-link', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            if (page && page !== state.currentPage) {
                state.currentPage = page;
                loadData();
            }
        });

        // Inline copy (delegated)
        $tbody.on('click', '.tp-cl-copy-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            copyToClipboard($(this).data('url'), $(this));
        });

        // Inline QR (delegated)
        $tbody.on('click', '.tp-cl-qr-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showQrDialog($(this).data('url'));
        });

        // Inline history (delegated)
        $tbody.on('click', '.tp-cl-history-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var mid = parseInt($(this).data('mid'));
            showHistory(mid);
        });

        // Toggle status (delegated)
        $tbody.on('change', '.tp-cl-status-toggle', function(e) {
            e.stopPropagation();
            var mid = parseInt($(this).data('mid'));
            var enabled = $(this).is(':checked');
            toggleLinkStatus(mid, enabled, $(this));
        });

        // Row click -> edit
        $tbody.on('click', 'tr[data-mid]', function(e) {
            if ($(e.target).closest('a, button, .tp-cl-inline-btn, .tp-cl-status-toggle, label').length) return;
            var mid = parseInt($(this).data('mid'));
            emitEditItem(mid);
        });

        // Add link
        $addLinkBtn.on('click', function() {
            $(document).trigger('tp:resetForm');
            openEditModal('add');
        });

        // Edit modal close
        $editModalClose.on('click', closeEditModal);
        $editModalOverlay.on('click', function(e) {
            if (e.target === $editModalOverlay[0]) closeEditModal();
        });

        // History modal close
        $historyModalClose.on('click', function() { $historyModalOverlay.hide(); });
        $historyModalOverlay.on('click', function(e) {
            if (e.target === $historyModalOverlay[0]) $historyModalOverlay.hide();
        });

        // QR dialog
        $qrDialogOverlay.on('click', function(e) {
            if (e.target === $qrDialogOverlay[0]) $qrDialogOverlay.hide();
        });
        $qrDialogClose.on('click', function() { $qrDialogOverlay.hide(); });
        $qrDownloadBtn.on('click', function() {
            window.TPQRUtils.download($qrContainer);
            $qrDialogOverlay.hide();
        });
        $qrCopyBtn.on('click', function() {
            window.TPQRUtils.copyToClipboard(
                $qrContainer,
                function() {
                    var orig = $qrCopyBtn.html();
                    $qrCopyBtn.html('<i class="fas fa-check"></i><span>Copied!</span>');
                    setTimeout(function() { $qrCopyBtn.html(orig); $qrDialogOverlay.hide(); }, 1000);
                },
                function(err) { console.error('Copy QR failed:', err); }
            );
        });
        $qrOpenBtn.on('click', function() {
            if (currentQrUrl) { window.open(currentQrUrl, '_blank'); $qrDialogOverlay.hide(); }
        });

        // Listen for form-save event to refresh table
        $(document).on('tp:linkSaved tp:linkUpdated', function() {
            closeEditModal();
            loadData();
        });
    }

    /* ---------------------------------------------------------------
     * Sort indicators
     * ------------------------------------------------------------- */
    function updateSortIndicators() {
        var parts = state.sort.split(':');
        var field = parts[0];
        var dir = parts[1];

        $('.tp-cl-sortable').each(function() {
            var $th = $(this);
            var $icon = $th.find('.tp-cl-sort-icon');
            $th.removeClass('tp-cl-sort-active');
            $icon.removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');

            if ($th.data('sort') === field) {
                $th.addClass('tp-cl-sort-active');
                $icon.removeClass('fa-sort').addClass(dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
            }
        });
    }

    /* ---------------------------------------------------------------
     * Load data
     * ------------------------------------------------------------- */
    function loadData() {
        if (state.isLoading) return;
        state.isLoading = true;
        showLoading();

        $.ajax({
            url: tpClientLinks.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tp_get_user_map_items',
                nonce: tpClientLinks.nonce,
                page: state.currentPage,
                page_size: state.pageSize,
                sort: state.sort,
                status: state.status || null,
                search: state.search || null,
                include_usage: true
            },
            success: function(response) {
                state.isLoading = false;

                if (response.success) {
                    state.totalRecords = response.data.total_records;
                    state.totalPages = response.data.total_pages;
                    state.items = response.data.source;
                    renderTable(state.items);
                    renderPagination();
                    updateTotalCount();
                    renderChart(state.items);

                    if (state.items.length === 0) {
                        showEmpty();
                    } else {
                        showTable();
                    }
                } else {
                    showError(response.data.message || tpClientLinks.strings.error);
                }
            },
            error: function(xhr, status, error) {
                state.isLoading = false;
                console.error('Client links AJAX error:', error);
                if (xhr.status === 401) {
                    redirectToLogin();
                    return;
                }
                showError(tpClientLinks.strings.error);
            }
        });
    }

    /* ---------------------------------------------------------------
     * Render table
     * ------------------------------------------------------------- */
    function renderTable(items) {
        $tbody.empty();

        // Group by domain
        var groups = {};
        var groupOrder = [];
        items.forEach(function(item) {
            var domain = item.domain || 'unknown';
            if (!groups[domain]) {
                groups[domain] = [];
                groupOrder.push(domain);
            }
            groups[domain].push(item);
        });

        groupOrder.forEach(function(domain) {
            var groupItems = groups[domain];

            // Domain totals
            var totalUsage = 0, totalQr = 0, totalRegular = 0;
            groupItems.forEach(function(item) {
                if (item.usage) {
                    totalUsage += item.usage.total || 0;
                    totalQr += item.usage.qr || 0;
                    totalRegular += item.usage.regular || 0;
                }
            });

            // Domain group header
            $tbody.append(
                '<tr class="tp-cl-domain-row">' +
                    '<td colspan="3">' +
                        '<div class="tp-cl-domain-label">' +
                            '<i class="fas fa-globe"></i> ' +
                            '<span>' + escapeHtml(domain) + '</span>' +
                        '</div>' +
                    '</td>' +
                    '<td colspan="2">' +
                        '<div class="tp-cl-domain-usage">' +
                            '<span>' + totalUsage.toLocaleString() + ' total</span> ' +
                            '<span><i class="fas fa-qrcode"></i> ' + totalQr.toLocaleString() + '</span> ' +
                            '<span><i class="fas fa-mouse-pointer"></i> ' + totalRegular.toLocaleString() + '</span>' +
                        '</div>' +
                    '</td>' +
                '</tr>'
            );

            // Item rows
            groupItems.forEach(function(item) {
                var shortUrl = 'https://' + item.domain + '/' + item.tpKey;
                var isActive = (item.status || 'active') !== 'disabled';

                // Usage
                var clicksHtml = '<span class="text-muted">-</span>';
                if (item.usage) {
                    clicksHtml =
                        '<div class="tp-cl-clicks-cell">' +
                            '<span class="tp-cl-clicks-total">' + item.usage.total.toLocaleString() + '</span>' +
                            '<span class="tp-cl-clicks-breakdown">' +
                                '<i class="fas fa-qrcode"></i> ' + item.usage.qr +
                                ' <i class="fas fa-mouse-pointer ms-1"></i> ' + item.usage.regular +
                            '</span>' +
                        '</div>';
                }

                var row =
                    '<tr data-mid="' + item.mid + '" class="' + (isActive ? '' : 'tp-cl-row-disabled') + '">' +
                        '<td class="tp-cl-col-link" data-label="Link">' +
                            '<div class="tp-cl-link-cell">' +
                                '<a href="' + escapeHtml(shortUrl) + '" target="_blank" class="tp-cl-link" title="' + escapeHtml(shortUrl) + '">' + escapeHtml(item.tpKey) + '</a>' +
                                '<span class="tp-cl-inline-actions">' +
                                    '<button class="tp-cl-inline-btn tp-cl-copy-btn" data-url="' + escapeHtml(shortUrl) + '" title="Copy"><i class="fas fa-copy"></i></button>' +
                                    '<button class="tp-cl-inline-btn tp-cl-qr-btn" data-url="' + escapeHtml(shortUrl) + '" title="QR"><i class="fas fa-qrcode"></i></button>' +
                                    '<button class="tp-cl-inline-btn tp-cl-history-btn" data-mid="' + item.mid + '" title="History"><i class="fas fa-history"></i></button>' +
                                '</span>' +
                            '</div>' +
                        '</td>' +
                        '<td class="tp-cl-col-dest" data-label="Destination">' +
                            '<div class="tp-cl-dest-cell">' +
                                '<a href="' + escapeHtml(item.destination) + '" target="_blank" class="tp-cl-dest" title="' + escapeHtml(item.destination) + '">' + escapeHtml(item.destination) + '</a>' +
                                (item.notes ? '<div class="tp-cl-dest-notes" title="' + escapeHtml(item.notes) + '">' + escapeHtml(item.notes) + '</div>' : '') +
                            '</div>' +
                        '</td>' +
                        '<td class="tp-cl-col-clicks" data-label="Clicks">' + clicksHtml + '</td>' +
                        '<td class="tp-cl-col-date" data-label="Created">' +
                            '<span class="tp-cl-date">' + formatDate(item.created_at) + '</span>' +
                        '</td>' +
                        '<td class="tp-cl-col-status" data-label="Status">' +
                            '<label class="tp-cl-toggle" title="' + (isActive ? 'Active' : 'Disabled') + '">' +
                                '<input type="checkbox" class="tp-cl-status-toggle" data-mid="' + item.mid + '" ' + (isActive ? 'checked' : '') + '>' +
                                '<span class="tp-cl-toggle-slider"></span>' +
                            '</label>' +
                        '</td>' +
                    '</tr>';

                $tbody.append(row);
            });
        });
    }

    /* ---------------------------------------------------------------
     * Chart
     * ------------------------------------------------------------- */
    function renderChart(items) {
        if (!items.length) {
            $chartWrapper.hide();
            return;
        }
        $chartWrapper.show();

        // Build per-link data for a bar chart
        var labels = [];
        var clicksData = [];
        var qrData = [];
        var colors = ['#3c7ae5', '#22b573', '#f0ad4e', '#5f8cf3', '#f05a5a', '#9b59b6', '#1abc9c', '#e67e22'];

        items.forEach(function(item) {
            labels.push(item.tpKey || 'unknown');
            clicksData.push(item.usage ? item.usage.regular : 0);
            qrData.push(item.usage ? item.usage.qr : 0);
        });

        var ctx = document.getElementById('tp-cl-chart');
        if (!ctx) return;

        // Destroy previous chart
        if (state.chart) {
            state.chart.destroy();
            state.chart = null;
        }

        if (typeof Chart === 'undefined') return;

        state.chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Clicks',
                        data: clicksData,
                        backgroundColor: 'rgba(60, 122, 229, 0.7)',
                        borderColor: '#3c7ae5',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'QR Scans',
                        data: qrData,
                        backgroundColor: 'rgba(34, 181, 115, 0.7)',
                        borderColor: '#22b573',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: "'Poppins', sans-serif", size: 12 } }
                    },
                    title: {
                        display: true,
                        text: 'Link Performance — ' + state.dateStart + ' to ' + state.dateEnd,
                        font: { family: "'Poppins', sans-serif", size: 14, weight: '600' },
                        color: '#1e2f50'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 } },
                        grid: { color: 'rgba(207, 226, 255, 0.4)' }
                    },
                    x: {
                        ticks: { font: { size: 11 }, maxRotation: 45, minRotation: 0 },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    /* ---------------------------------------------------------------
     * Toggle link status
     * ------------------------------------------------------------- */
    function toggleLinkStatus(mid, enable, $toggle) {
        var item = state.items.find(function(i) { return i.mid === mid; });
        if (!item) return;

        var newStatus = enable ? 'active' : 'disabled';
        var confirmMsg = enable ? null : tpClientLinks.strings.confirmDisable;

        if (confirmMsg && !confirm(confirmMsg)) {
            // Revert the toggle
            $toggle.prop('checked', !enable);
            return;
        }

        $.ajax({
            url: tpClientLinks.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tp_toggle_link_status',
                nonce: tpClientLinks.nonce,
                mid: mid,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // Update local state
                    item.status = newStatus;
                    var $row = $tbody.find('tr[data-mid="' + mid + '"]');
                    $row.toggleClass('tp-cl-row-disabled', !enable);
                    $toggle.closest('.tp-cl-toggle').attr('title', enable ? 'Active' : 'Disabled');
                } else {
                    $toggle.prop('checked', !enable);
                    alert(response.data.message || 'Failed to update status.');
                }
            },
            error: function(xhr) {
                $toggle.prop('checked', !enable);
                if (xhr.status === 401) {
                    redirectToLogin();
                    return;
                }
                alert('Network error. Please try again.');
            }
        });
    }

    /* ---------------------------------------------------------------
     * History
     * ------------------------------------------------------------- */
    function showHistory(mid) {
        $historyList.html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
        $historyModalOverlay.show();

        $.ajax({
            url: tpClientLinks.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tp_get_link_history',
                nonce: tpClientLinks.nonce,
                mid: mid
            },
            success: function(response) {
                if (response.success && response.data.length) {
                    var html = '';
                    response.data.forEach(function(entry) {
                        html +=
                            '<div class="tp-cl-history-entry">' +
                                '<div class="tp-cl-history-action">' +
                                    '<i class="fas ' + historyIcon(entry.action) + '"></i> ' +
                                    escapeHtml(entry.action) +
                                '</div>' +
                                '<div class="tp-cl-history-details">' + escapeHtml(entry.changes || '') + '</div>' +
                                '<div class="tp-cl-history-time">' + formatDate(entry.created_at) + '</div>' +
                            '</div>';
                    });
                    $historyList.html(html);
                } else {
                    $historyList.html('<div class="text-center text-muted py-3">No history found.</div>');
                }
            },
            error: function(xhr) {
                if (xhr.status === 401) {
                    redirectToLogin();
                    return;
                }
                $historyList.html('<div class="text-center text-danger py-3">Failed to load history.</div>');
            }
        });
    }

    function historyIcon(action) {
        switch (action) {
            case 'created':  return 'fa-plus-circle text-success';
            case 'updated':  return 'fa-edit text-primary';
            case 'enabled':  return 'fa-check-circle text-success';
            case 'disabled': return 'fa-ban text-warning';
            default:         return 'fa-circle text-muted';
        }
    }

    /* ---------------------------------------------------------------
     * Edit modal
     * ------------------------------------------------------------- */
    function emitEditItem(mid) {
        var item = state.items.find(function(i) { return i.mid === mid; });
        if (!item) return;
        openEditModal('edit');
        $(document).trigger('tp:editItem', [item]);
    }

    function openEditModal(mode) {
        $editModalTitle.text(mode === 'edit' ? 'Edit link' : 'Add a link');
        var $formWrapper = $('#tp-link-shortener-wrapper');
        if ($formWrapper.length) {
            $editModalBody.append($formWrapper);
            $formWrapper.show();
        }
        $editModalOverlay.show();
    }

    function closeEditModal() {
        var $formWrapper = $('#tp-link-shortener-wrapper');
        if ($formWrapper.length && $formPlaceholder && $formPlaceholder.length) {
            $formWrapper.hide();
            $formPlaceholder.after($formWrapper);
        }
        $editModalOverlay.hide();
    }

    /* ---------------------------------------------------------------
     * QR dialog
     * ------------------------------------------------------------- */
    function showQrDialog(url) {
        currentQrUrl = url;
        $qrUrl.text(url);
        window.TPQRUtils.generate($qrContainer, url);
        $qrDialogOverlay.show();
    }

    /* ---------------------------------------------------------------
     * Pagination
     * ------------------------------------------------------------- */
    function renderPagination() {
        $paginationList.empty();

        if (state.totalPages <= 1) {
            $('#tp-cl-pagination').hide();
            return;
        }
        $('#tp-cl-pagination').show();

        var maxVisible = 5;
        var startPage = Math.max(1, state.currentPage - Math.floor(maxVisible / 2));
        var endPage = Math.min(state.totalPages, startPage + maxVisible - 1);
        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        // Prev
        $paginationList.append(
            '<li class="page-item ' + (state.currentPage === 1 ? 'disabled' : '') + '">' +
                '<a class="page-link" href="#" data-page="' + (state.currentPage - 1) + '"><i class="fas fa-chevron-left"></i></a>' +
            '</li>'
        );

        if (startPage > 1) {
            $paginationList.append('<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>');
            if (startPage > 2) {
                $paginationList.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
            }
        }

        for (var i = startPage; i <= endPage; i++) {
            $paginationList.append(
                '<li class="page-item ' + (i === state.currentPage ? 'active' : '') + '">' +
                    '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>' +
                '</li>'
            );
        }

        if (endPage < state.totalPages) {
            if (endPage < state.totalPages - 1) {
                $paginationList.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
            }
            $paginationList.append('<li class="page-item"><a class="page-link" href="#" data-page="' + state.totalPages + '">' + state.totalPages + '</a></li>');
        }

        // Next
        $paginationList.append(
            '<li class="page-item ' + (state.currentPage === state.totalPages ? 'disabled' : '') + '">' +
                '<a class="page-link" href="#" data-page="' + (state.currentPage + 1) + '"><i class="fas fa-chevron-right"></i></a>' +
            '</li>'
        );

        var start = (state.currentPage - 1) * state.pageSize + 1;
        var end = Math.min(state.currentPage * state.pageSize, state.totalRecords);
        $paginationInfo.text('Showing ' + start + '-' + end + ' of ' + state.totalRecords);
    }

    function updateTotalCount() {
        var text = state.totalRecords === 1 ? '1 link' : state.totalRecords + ' links';
        $totalCount.text(text);
    }

    /* ---------------------------------------------------------------
     * UI states
     * ------------------------------------------------------------- */
    function redirectToLogin() { window.location.href = tpClientLinks.loginUrl || '/login/'; }
    function showLoading() { $loading.show(); $error.hide(); $empty.hide(); $tableWrapper.hide(); }
    function showError(msg) { $loading.hide(); $error.show(); $('#tp-cl-error-message').text(msg); $empty.hide(); $tableWrapper.hide(); }
    function showEmpty() { $loading.hide(); $error.hide(); $empty.show(); $tableWrapper.hide(); }
    function showTable() { $loading.hide(); $error.hide(); $empty.hide(); $tableWrapper.show(); }

    /* ---------------------------------------------------------------
     * Clipboard
     * ------------------------------------------------------------- */
    function copyToClipboard(text, $btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyTooltip($btn);
            }).catch(function() {
                fallbackCopy(text, $btn);
            });
        } else {
            fallbackCopy(text, $btn);
        }
    }

    function fallbackCopy(text, $btn) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); showCopyTooltip($btn); } catch (e) { /* noop */ }
        document.body.removeChild(ta);
    }

    function showCopyTooltip($btn) {
        var off = $btn.offset();
        $copyTooltip
            .css({ top: off.top - 40, left: off.left + ($btn.outerWidth() / 2) - ($copyTooltip.outerWidth() / 2) })
            .addClass('show');
        setTimeout(function() { $copyTooltip.removeClass('show'); }, 1500);
    }

    /* ---------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------- */
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

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Init on ready
    $(document).ready(init);

})(jQuery);

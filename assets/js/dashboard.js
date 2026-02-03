/**
 * Dashboard JavaScript
 * POC for displaying user's map items in a paginated table
 */

(function($) {
    'use strict';

    // Dashboard state
    const state = {
        currentPage: 1,
        pageSize: 10,
        totalRecords: 0,
        totalPages: 0,
        sort: 'updated_at:desc',
        status: '',
        search: '',
        isLoading: false,
        searchDebounceTimer: null,
        items: [] // Store current page items for lookup
    };

    // DOM elements
    let $container,
        $loginRequired,
        $content,
        $loading,
        $skeletonTbody,
        $error,
        $empty,
        $tableWrapper,
        $tbody,
        $pagination,
        $paginationInfo,
        $paginationList,
        $totalCount,
        $searchInput,
        $statusFilter,
        $sortOrder,
        $refreshBtn,
        $retryBtn,
        $searchClear,
        $copyTooltip,
        $qrDialogOverlay,
        $qrDialogClose,
        $qrContainer,
        $qrUrl,
        $qrDownloadBtn,
        $qrCopyBtn,
        $qrOpenBtn,
        currentQrUrl = null;

    /**
     * Initialize the dashboard
     */
    function init() {
        $container = $('.tp-dashboard-container');
        if (!$container.length) return;

        // Cache DOM elements
        $loginRequired = $('#tp-dashboard-login-required');
        $content = $('#tp-dashboard-content');
        $loading = $('#tp-dashboard-loading');
        $skeletonTbody = $('#tp-skeleton-tbody');
        $error = $('#tp-dashboard-error');
        $empty = $('#tp-dashboard-empty');
        $tableWrapper = $('#tp-dashboard-table-wrapper');
        $tbody = $('#tp-dashboard-tbody');
        $pagination = $('#tp-dashboard-pagination');
        $paginationInfo = $('#tp-pagination-info');
        $paginationList = $('#tp-pagination-list');
        $totalCount = $('#tp-total-count');
        $searchInput = $('#tp-dashboard-search');
        $statusFilter = $('#tp-filter-status');
        $sortOrder = $('#tp-sort-order');
        $refreshBtn = $('#tp-refresh-btn');
        $retryBtn = $('#tp-retry-btn');
        $searchClear = $('#tp-search-clear');
        $copyTooltip = $('#tp-copy-tooltip');
        $qrDialogOverlay = $('#tp-qr-dialog-overlay');
        $qrDialogClose = $('#tp-qr-dialog-close');
        $qrContainer = $('#tp-qr-code-container');
        $qrUrl = $('#tp-qr-url');
        $qrDownloadBtn = $('#tp-qr-download-btn');
        $qrCopyBtn = $('#tp-qr-copy-btn');
        $qrOpenBtn = $('#tp-qr-open-btn');

        // Get page size from container data attribute
        state.pageSize = parseInt($container.data('page-size')) || 10;

        // Generate skeleton rows based on page size
        generateSkeletonRows(state.pageSize);

        // Check if user is logged in
        if (!tpDashboard.isLoggedIn) {
            showLoginRequired();
            return;
        }

        // Bind events
        bindEvents();

        // Load initial data
        loadData();
    }

    /**
     * Generate skeleton loading rows based on page size
     */
    function generateSkeletonRows(count) {
        $skeletonTbody.empty();

        for (let i = 0; i < count; i++) {
            const row = `
                <tr class="tp-skeleton-row">
                    <td class="tp-col-shortlink" data-label="Link">
                        <div class="tp-skeleton-cell">
                            <div class="tp-skeleton-placeholder tp-skeleton-placeholder-lg"></div>
                            <div class="tp-skeleton-placeholder tp-skeleton-placeholder-sm"></div>
                        </div>
                    </td>
                    <td class="tp-col-destination" data-label="Destination">
                        <div class="tp-skeleton-cell">
                            <div class="tp-skeleton-placeholder tp-skeleton-placeholder-lg"></div>
                            <div class="tp-skeleton-placeholder tp-skeleton-placeholder-xs"></div>
                        </div>
                    </td>
                    <td class="tp-col-usage" data-label="Usage">
                        <div class="tp-skeleton-cell">
                            <div class="tp-skeleton-placeholder tp-skeleton-placeholder-md" style="width: 50px;"></div>
                            <div class="tp-skeleton-placeholder tp-skeleton-placeholder-xs" style="width: 80px;"></div>
                        </div>
                    </td>
                    <td class="tp-col-date" data-label="Created">
                        <div class="tp-skeleton-placeholder tp-skeleton-placeholder-md" style="width: 80px;"></div>
                    </td>
                    <td class="tp-col-actions" data-label="Actions">
                        <div class="tp-skeleton-actions">
                            <div class="tp-skeleton-btn"></div>
                            <div class="tp-skeleton-btn"></div>
                            <div class="tp-skeleton-btn"></div>
                            <div class="tp-skeleton-btn"></div>
                        </div>
                    </td>
                </tr>
            `;
            $skeletonTbody.append(row);
        }
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Search input with debounce
        $searchInput.on('input', function() {
            clearTimeout(state.searchDebounceTimer);
            state.searchDebounceTimer = setTimeout(function() {
                state.search = $searchInput.val().trim();
                state.currentPage = 1;
                loadData();
            }, 300);
        });

        // Clear search
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

        // Sort order
        $sortOrder.on('change', function() {
            state.sort = $(this).val();
            state.currentPage = 1;
            loadData();
        });

        // Refresh button
        $refreshBtn.on('click', function() {
            loadData();
        });

        // Retry button
        $retryBtn.on('click', function() {
            loadData();
        });

        // Pagination clicks (delegated)
        $paginationList.on('click', '.page-link', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page && page !== state.currentPage) {
                state.currentPage = page;
                loadData();
            }
        });

        // Copy button clicks (delegated)
        $tbody.on('click', '.tp-copy-btn', function(e) {
            e.preventDefault();
            const url = $(this).data('url');
            copyToClipboard(url, $(this));
        });

        // QR button clicks (delegated)
        $tbody.on('click', '.tp-qr-btn', function(e) {
            e.preventDefault();
            const url = $(this).data('url');
            showQrDialog(url);
        });

        // Edit button clicks (delegated) - emit event to populate frontend form
        $tbody.on('click', '.tp-edit-btn', function(e) {
            e.preventDefault();
            const mid = parseInt($(this).data('mid'));
            emitEditItem(mid);
        });

        // Row clicks (delegated) - emit event to populate frontend form
        $tbody.on('click', 'tr', function(e) {
            // Ignore clicks on interactive elements (buttons, links)
            const $target = $(e.target);
            if ($target.closest('a, button, .tp-action-btn').length) {
                return;
            }

            const mid = parseInt($(this).data('mid'));
            emitEditItem(mid);
        });

        // QR dialog event handlers
        $qrDialogOverlay.on('click', handleQrDialogOverlayClick);
        $qrDialogClose.on('click', hideQrDialog);
        $qrDownloadBtn.on('click', downloadQrCode);
        $qrCopyBtn.on('click', copyQrCode);
        $qrOpenBtn.on('click', openQrUrl);
    }

    /**
     * Emit edit item event for frontend form to consume
     * @param {number} mid - Map item ID
     */
    function emitEditItem(mid) {
        const item = state.items.find(function(i) {
            return i.mid === mid;
        });

        if (!item) {
            console.error('Dashboard: Item not found for mid:', mid);
            return;
        }

        // Emit custom event with item data for frontend to consume
        $(document).trigger('tp:editItem', [item]);

        // Scroll to form if it exists on the page
        const $form = $('#tp-shortener-form');
        if ($form.length) {
            $('html, body').animate({
                scrollTop: $form.offset().top - 100
            }, 500);
        }
    }

    /**
     * Load data from AJAX endpoint
     */
    function loadData() {
        if (state.isLoading) return;

        state.isLoading = true;
        showLoading();

        $.ajax({
            url: tpDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tp_get_user_map_items',
                nonce: tpDashboard.nonce,
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
                    state.items = response.data.source; // Store items for lookup
                    renderTable(response.data.source);
                    renderPagination();
                    updateTotalCount();

                    if (response.data.source.length === 0) {
                        showEmpty();
                    } else {
                        showTable();
                    }
                } else {
                    showError(response.data.message || tpDashboard.strings.error);
                }
            },
            error: function(xhr, status, error) {
                state.isLoading = false;
                console.error('Dashboard AJAX error:', error);
                showError(tpDashboard.strings.error);
            }
        });
    }

    /**
     * Render table rows
     */
    function renderTable(items) {
        $tbody.empty();

        items.forEach(function(item) {
            const shortUrl = 'https://' + item.domain + '/' + item.tpKey;
            const createdDate = formatDate(item.created_at);

            // Usage stats
            let usageHtml = '<span class="text-muted">-</span>';
            if (item.usage) {
                usageHtml = `
                    <div class="tp-usage-cell">
                        <span class="tp-usage-total">${item.usage.total.toLocaleString()}</span>
                        <span class="tp-usage-breakdown">
                            <i class="fas fa-qrcode"></i>${item.usage.qr}
                            <i class="fas fa-mouse-pointer ms-2"></i>${item.usage.regular}
                        </span>
                    </div>
                `;
            }

            const row = `
                <tr data-mid="${item.mid}">
                    <td class="tp-col-shortlink" data-label="Short Link">
                        <div class="tp-shortlink-cell">
                            <a href="${escapeHtml(shortUrl)}" target="_blank" class="tp-shortlink">${escapeHtml(shortUrl)}</a>
                            <span class="tp-shortlink-key"><i class="fas fa-key me-1"></i>${escapeHtml(item.tpKey)}</span>
                        </div>
                    </td>
                    <td class="tp-col-destination" data-label="Destination">
                        <div class="tp-destination-cell">
                            <a href="${escapeHtml(item.destination)}" target="_blank" class="tp-destination" title="${escapeHtml(item.destination)}">${escapeHtml(item.destination)}</a>
                            ${item.notes ? `<div class="tp-destination-notes" title="${escapeHtml(item.notes)}">${escapeHtml(item.notes)}</div>` : ''}
                        </div>
                    </td>
                    <td class="tp-col-usage" data-label="Usage">
                        ${usageHtml}
                    </td>
                    <td class="tp-col-date" data-label="Created">
                        <span class="tp-date-cell">${createdDate}</span>
                    </td>
                    <td class="tp-col-actions" data-label="Actions">
                        <div class="tp-actions-cell">
                            <button class="btn btn-sm btn-outline-primary tp-action-btn tp-copy-btn" data-url="${escapeHtml(shortUrl)}" title="Copy link">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary tp-action-btn tp-qr-btn" data-url="${escapeHtml(shortUrl)}" title="QR Code">
                                <i class="fas fa-qrcode"></i>
                            </button>
                            <a href="${escapeHtml(shortUrl)}" target="_blank" class="btn btn-sm btn-outline-secondary tp-action-btn" title="Open link">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-info tp-action-btn tp-edit-btn" data-mid="${item.mid}" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;

            $tbody.append(row);
        });
    }

    /**
     * Render pagination
     */
    function renderPagination() {
        $paginationList.empty();

        if (state.totalPages <= 1) {
            $pagination.hide();
            return;
        }

        $pagination.show();

        // Calculate page range
        const maxVisiblePages = 5;
        let startPage = Math.max(1, state.currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(state.totalPages, startPage + maxVisiblePages - 1);

        if (endPage - startPage < maxVisiblePages - 1) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }

        // Previous button
        $paginationList.append(`
            <li class="page-item ${state.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${state.currentPage - 1}" aria-label="Previous">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `);

        // First page
        if (startPage > 1) {
            $paginationList.append(`
                <li class="page-item">
                    <a class="page-link" href="#" data-page="1">1</a>
                </li>
            `);
            if (startPage > 2) {
                $paginationList.append(`
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                `);
            }
        }

        // Page numbers
        for (let i = startPage; i <= endPage; i++) {
            $paginationList.append(`
                <li class="page-item ${i === state.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        // Last page
        if (endPage < state.totalPages) {
            if (endPage < state.totalPages - 1) {
                $paginationList.append(`
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                `);
            }
            $paginationList.append(`
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${state.totalPages}">${state.totalPages}</a>
                </li>
            `);
        }

        // Next button
        $paginationList.append(`
            <li class="page-item ${state.currentPage === state.totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${state.currentPage + 1}" aria-label="Next">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `);

        // Update pagination info
        const start = (state.currentPage - 1) * state.pageSize + 1;
        const end = Math.min(state.currentPage * state.pageSize, state.totalRecords);
        $paginationInfo.text(`Showing ${start}-${end} of ${state.totalRecords}`);
    }

    /**
     * Update total count badge
     */
    function updateTotalCount() {
        const text = state.totalRecords === 1 ? '1 link' : `${state.totalRecords} links`;
        $totalCount.text(text);
    }

    /**
     * Copy text to clipboard
     */
    function copyToClipboard(text, $button) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyTooltip($button);
            }).catch(function(err) {
                console.error('Copy failed:', err);
                fallbackCopy(text, $button);
            });
        } else {
            fallbackCopy(text, $button);
        }
    }

    /**
     * Fallback copy method
     */
    function fallbackCopy(text, $button) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            showCopyTooltip($button);
        } catch (err) {
            console.error('Fallback copy failed:', err);
        }

        document.body.removeChild(textarea);
    }

    /**
     * Show copy tooltip
     */
    function showCopyTooltip($button) {
        const offset = $button.offset();
        $copyTooltip
            .css({
                top: offset.top - 40,
                left: offset.left + ($button.outerWidth() / 2) - ($copyTooltip.outerWidth() / 2)
            })
            .addClass('show');

        setTimeout(function() {
            $copyTooltip.removeClass('show');
        }, 1500);
    }

    /**
     * Format date string
     */
    function formatDate(dateString) {
        if (!dateString) return '-';

        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;

        const now = new Date();
        const diff = now - date;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

        if (days === 0) {
            return 'Today';
        } else if (days === 1) {
            return 'Yesterday';
        } else if (days < 7) {
            return `${days} days ago`;
        } else {
            return date.toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Show/hide UI states
     */
    function showLoginRequired() {
        $content.hide();
        // Display nothing when user is not signed in
    }

    function showLoading() {
        $loading.show();
        $error.hide();
        $empty.hide();
        $tableWrapper.hide();
    }

    function showError(message) {
        $loading.hide();
        $error.show();
        $('#tp-error-message').text(message);
        $empty.hide();
        $tableWrapper.hide();
    }

    function showEmpty() {
        $loading.hide();
        $error.hide();
        $empty.show();
        $tableWrapper.hide();
    }

    function showTable() {
        $loading.hide();
        $error.hide();
        $empty.hide();
        $tableWrapper.show();
    }

    /**
     * Show QR code dialog
     */
    function showQrDialog(url) {
        currentQrUrl = url;
        $qrUrl.text(url);

        // Generate QR code using shared utility
        const qrCode = window.TPQRUtils.generate($qrContainer, url);

        if (!qrCode) {
            $qrContainer.html('<p class="text-danger">Failed to generate QR code</p>');
        }

        // Show dialog
        $qrDialogOverlay.show();
    }

    /**
     * Hide QR code dialog
     */
    function hideQrDialog() {
        $qrDialogOverlay.hide();
    }

    /**
     * Handle click on dialog overlay (close if clicking outside dialog)
     */
    function handleQrDialogOverlayClick(e) {
        if (e.target === $qrDialogOverlay[0]) {
            hideQrDialog();
        }
    }

    /**
     * Download QR code as PNG
     */
    function downloadQrCode() {
        window.TPQRUtils.download($qrContainer);
        hideQrDialog();
    }

    /**
     * Copy QR code to clipboard
     */
    function copyQrCode() {
        window.TPQRUtils.copyToClipboard(
            $qrContainer,
            function() {
                // Show success feedback
                const originalHtml = $qrCopyBtn.html();
                $qrCopyBtn.html('<i class="fas fa-check"></i><span>Copied!</span>');
                setTimeout(function() {
                    $qrCopyBtn.html(originalHtml);
                    hideQrDialog();
                }, 1000);
            },
            function(err) {
                console.error('Failed to copy QR code:', err);
            }
        );
    }

    /**
     * Open QR URL in new tab
     */
    function openQrUrl() {
        if (currentQrUrl) {
            window.open(currentQrUrl, '_blank');
            hideQrDialog();
        }
    }

    // Initialize on DOM ready
    $(document).ready(init);

})(jQuery);

/**
 * Usage Dashboard JavaScript
 * Fetches usage data via AJAX, manages loading/error/content states,
 * and provides retry functionality.
 */

(function($) {
    'use strict';

    // State
    var state = {
        isLoading: false,
        dateStart: tpUsageDashboard.dateRange.start,
        dateEnd: tpUsageDashboard.dateRange.end,
        data: null
    };

    // DOM cache
    var $skeleton,
        $error,
        $errorMsg,
        $content,
        $retryBtn,
        $adminError;

    /* ---------------------------------------------------------------
     * Cache DOM elements
     * ------------------------------------------------------------- */
    function cacheElements() {
        $skeleton   = $('#tp-ud-skeleton');
        $error      = $('#tp-ud-error');
        $errorMsg   = $('#tp-ud-error-msg');
        $content    = $('#tp-ud-content');
        $retryBtn   = $('#tp-ud-retry');
        $adminError = $('#tp-ud-admin-error');
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
                        // No data for this date range
                        var $noData = $content.find('.tp-ud-no-data');
                        if (!$noData.length) {
                            $content.prepend(
                                '<div class="tp-ud-no-data text-center text-muted py-4">' +
                                    '<i class="fas fa-chart-bar fa-2x mb-2 d-block"></i>' +
                                    '<p>' + tpUsageDashboard.strings.noData + '</p>' +
                                '</div>'
                            );
                        }
                        showContent();
                    } else {
                        // Remove any previous no-data message
                        $content.find('.tp-ud-no-data').remove();
                        showContent();
                        // Actual table/chart rendering is deferred to Phases 6-7
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

<?php
/**
 * Client Links Template
 * Link management page with sortable table, date range, performance chart, and history.
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_size = isset($atts['page_size']) ? intval($atts['page_size']) : 10;
$show_search = isset($atts['show_search']) ? ($atts['show_search'] === 'true') : true;
$show_filters = isset($atts['show_filters']) ? ($atts['show_filters'] === 'true') : true;
?>

<div class="tp-cl-container" data-page-size="<?php echo esc_attr($page_size); ?>">
    <!-- Dashboard Content (shown when logged in) -->
    <div class="tp-cl-content" id="tp-cl-content">
        <!-- Header -->
        <div class="tp-cl-header">
            <div class="tp-cl-title">
                <h3><i class="fas fa-link me-2"></i><?php esc_html_e('Client Links', 'tp-link-shortener'); ?></h3>
                <span class="tp-cl-total-count badge bg-secondary" id="tp-cl-total-count">0 links</span>
            </div>

            <div class="tp-cl-controls">
                <!-- Date Range -->
                <div class="tp-cl-date-range">
                    <label class="tp-cl-date-label" for="tp-cl-date-start">
                        <i class="fas fa-calendar-alt"></i>
                    </label>
                    <input type="date" class="form-control form-control-sm" id="tp-cl-date-start">
                    <span class="tp-cl-date-sep">&ndash;</span>
                    <input type="date" class="form-control form-control-sm" id="tp-cl-date-end">
                    <button class="btn btn-sm btn-outline-primary" id="tp-cl-date-apply" title="<?php esc_attr_e('Apply', 'tp-link-shortener'); ?>">
                        <i class="fas fa-check"></i>
                    </button>
                </div>

                <?php if ($show_search): ?>
                <div class="tp-cl-search">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="tp-cl-search" placeholder="<?php esc_attr_e('Search links...', 'tp-link-shortener'); ?>">
                        <button class="btn btn-outline-secondary" type="button" id="tp-cl-search-clear" title="<?php esc_attr_e('Clear', 'tp-link-shortener'); ?>">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($show_filters): ?>
                <div class="tp-cl-filters">
                    <select class="form-select form-select-sm" id="tp-cl-filter-status">
                        <option value=""><?php esc_html_e('All Statuses', 'tp-link-shortener'); ?></option>
                        <option value="active"><?php esc_html_e('Active', 'tp-link-shortener'); ?></option>
                        <option value="disabled"><?php esc_html_e('Disabled', 'tp-link-shortener'); ?></option>
                    </select>
                </div>
                <?php endif; ?>

                <button class="btn btn-sm btn-primary" id="tp-cl-add-link-btn" title="<?php esc_attr_e('Add a link', 'tp-link-shortener'); ?>">
                    <i class="fas fa-plus me-1"></i><?php esc_html_e('Add a link', 'tp-link-shortener'); ?>
                </button>

                <button class="btn btn-sm btn-outline-primary" id="tp-cl-refresh-btn" title="<?php esc_attr_e('Refresh', 'tp-link-shortener'); ?>">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="tp-cl-chart-wrapper" id="tp-cl-chart-wrapper">
            <canvas id="tp-cl-chart" height="200"></canvas>
        </div>

        <!-- Error State -->
        <div class="tp-cl-error alert alert-danger" id="tp-cl-error" style="display: none;">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <span id="tp-cl-error-message"><?php esc_html_e('Error loading links.', 'tp-link-shortener'); ?></span>
            <button class="btn btn-sm btn-outline-danger ms-2" id="tp-cl-retry-btn">
                <?php esc_html_e('Retry', 'tp-link-shortener'); ?>
            </button>
        </div>

        <!-- Empty State -->
        <div class="tp-cl-empty" id="tp-cl-empty" style="display: none;">
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5><?php esc_html_e('No links found', 'tp-link-shortener'); ?></h5>
                <p class="text-muted"><?php esc_html_e('Create your first short link to get started!', 'tp-link-shortener'); ?></p>
            </div>
        </div>

        <!-- Loading State (Skeleton) -->
        <div class="tp-cl-loading" id="tp-cl-loading">
            <div class="table-responsive">
                <table class="table tp-cl-table tp-cl-skeleton-table">
                    <thead>
                        <tr>
                            <th class="tp-cl-col-link"><?php esc_html_e('Link', 'tp-link-shortener'); ?></th>
                            <th class="tp-cl-col-dest"><?php esc_html_e('Destination', 'tp-link-shortener'); ?></th>
                            <th class="tp-cl-col-clicks"><?php esc_html_e('Clicks', 'tp-link-shortener'); ?></th>
                            <th class="tp-cl-col-date"><?php esc_html_e('Created', 'tp-link-shortener'); ?></th>
                            <th class="tp-cl-col-status"><?php esc_html_e('Status', 'tp-link-shortener'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="tp-cl-skeleton-tbody">
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Table -->
        <div class="tp-cl-table-wrapper" id="tp-cl-table-wrapper" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover tp-cl-table" id="tp-cl-table">
                    <thead>
                        <tr>
                            <th class="tp-cl-col-link tp-cl-sortable" data-sort="tpKey">
                                <?php esc_html_e('Link', 'tp-link-shortener'); ?>
                                <i class="fas fa-sort tp-cl-sort-icon"></i>
                            </th>
                            <th class="tp-cl-col-dest tp-cl-sortable" data-sort="destination">
                                <?php esc_html_e('Destination', 'tp-link-shortener'); ?>
                                <i class="fas fa-sort tp-cl-sort-icon"></i>
                            </th>
                            <th class="tp-cl-col-clicks tp-cl-sortable" data-sort="clicks">
                                <?php esc_html_e('Clicks', 'tp-link-shortener'); ?>
                                <i class="fas fa-sort tp-cl-sort-icon"></i>
                            </th>
                            <th class="tp-cl-col-date tp-cl-sortable" data-sort="created_at">
                                <?php esc_html_e('Created', 'tp-link-shortener'); ?>
                                <i class="fas fa-sort tp-cl-sort-icon"></i>
                            </th>
                            <th class="tp-cl-col-status">
                                <?php esc_html_e('Status', 'tp-link-shortener'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tp-cl-tbody">
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="tp-cl-pagination" id="tp-cl-pagination">
                <div class="tp-cl-pagination-info">
                    <span id="tp-cl-pagination-info">Showing 0-0 of 0</span>
                </div>
                <nav aria-label="Links pagination">
                    <ul class="pagination pagination-sm mb-0" id="tp-cl-pagination-list">
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Edit/Add Modal -->
<div id="tp-cl-edit-modal-overlay" class="tp-cl-modal-overlay" style="display:none;">
    <div class="tp-cl-modal">
        <div class="tp-cl-modal-header">
            <h5 class="tp-cl-modal-title"><?php esc_html_e('Add a link', 'tp-link-shortener'); ?></h5>
            <button type="button" id="tp-cl-edit-modal-close" class="tp-cl-modal-close" aria-label="<?php esc_attr_e('Close', 'tp-link-shortener'); ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="tp-cl-modal-body" id="tp-cl-edit-modal-body">
        </div>
    </div>
</div>

<!-- History Modal -->
<div id="tp-cl-history-modal-overlay" class="tp-cl-modal-overlay" style="display:none;">
    <div class="tp-cl-modal tp-cl-modal-sm">
        <div class="tp-cl-modal-header">
            <h5 class="tp-cl-modal-title"><?php esc_html_e('Change History', 'tp-link-shortener'); ?></h5>
            <button type="button" id="tp-cl-history-modal-close" class="tp-cl-modal-close" aria-label="<?php esc_attr_e('Close', 'tp-link-shortener'); ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="tp-cl-modal-body" id="tp-cl-history-modal-body">
            <div class="tp-cl-history-list" id="tp-cl-history-list">
            </div>
        </div>
    </div>
</div>

<!-- Copy tooltip -->
<div class="tp-cl-copy-tooltip" id="tp-cl-copy-tooltip"><?php esc_html_e('Copied!', 'tp-link-shortener'); ?></div>

<!-- QR Code Dialog -->
<div id="tp-cl-qr-dialog-overlay" class="tp-cl-modal-overlay" style="display: none;">
    <div class="tp-cl-qr-dialog">
        <div class="tp-cl-modal-header">
            <h5 class="tp-cl-modal-title"><?php esc_html_e('QR Code', 'tp-link-shortener'); ?></h5>
            <button type="button" id="tp-cl-qr-dialog-close" class="tp-cl-modal-close" aria-label="<?php esc_attr_e('Close', 'tp-link-shortener'); ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="tp-cl-qr-dialog-body">
            <div class="tp-cl-qr-preview">
                <div class="tp-cl-qr-container" id="tp-cl-qr-container"></div>
                <div class="tp-cl-qr-url" id="tp-cl-qr-url"></div>
            </div>
            <div class="tp-cl-qr-buttons">
                <button type="button" id="tp-cl-qr-download-btn" class="tp-cl-qr-btn">
                    <i class="fas fa-download"></i>
                    <span><?php esc_html_e('Download', 'tp-link-shortener'); ?></span>
                </button>
                <button type="button" id="tp-cl-qr-open-btn" class="tp-cl-qr-btn">
                    <i class="fas fa-external-link-alt"></i>
                    <span><?php esc_html_e('Open Link', 'tp-link-shortener'); ?></span>
                </button>
                <button type="button" id="tp-cl-qr-copy-btn" class="tp-cl-qr-btn">
                    <i class="fas fa-copy"></i>
                    <span><?php esc_html_e('Copy to Clipboard', 'tp-link-shortener'); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Dashboard Template
 * POC for displaying user's map items in a paginated table
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$page_size = isset($atts['page_size']) ? intval($atts['page_size']) : 10;
$show_search = isset($atts['show_search']) ? ($atts['show_search'] === 'true') : true;
$show_filters = isset($atts['show_filters']) ? ($atts['show_filters'] === 'true') : true;
?>

<div class="tp-dashboard-container" data-page-size="<?php echo esc_attr($page_size); ?>">
    <!-- Login Required Message (shown when not logged in) -->
    <div class="tp-dashboard-login-required" id="tp-dashboard-login-required" style="display: none;">
        <div class="alert alert-warning">
            <i class="fas fa-lock me-2"></i>
            <?php esc_html_e('Please log in to view your links.', 'tp-link-shortener'); ?>
        </div>
    </div>

    <!-- Dashboard Content (shown when logged in) -->
    <div class="tp-dashboard-content" id="tp-dashboard-content">
        <!-- Header with search and filters -->
        <div class="tp-dashboard-header">
            <div class="tp-dashboard-title">
                <h3><i class="fas fa-link me-2"></i><?php esc_html_e('My Links', 'tp-link-shortener'); ?></h3>
                <span class="tp-dashboard-total-count badge bg-secondary" id="tp-total-count">0 links</span>
            </div>

            <?php if ($show_search || $show_filters): ?>
            <div class="tp-dashboard-controls">
                <?php if ($show_search): ?>
                <div class="tp-dashboard-search">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="tp-dashboard-search" placeholder="<?php esc_attr_e('Search links...', 'tp-link-shortener'); ?>">
                        <button class="btn btn-outline-secondary" type="button" id="tp-search-clear" title="<?php esc_attr_e('Clear', 'tp-link-shortener'); ?>">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($show_filters): ?>
                <div class="tp-dashboard-filters">
                    <select class="form-select" id="tp-filter-status">
                        <option value=""><?php esc_html_e('All Statuses', 'tp-link-shortener'); ?></option>
                        <option value="active"><?php esc_html_e('Active', 'tp-link-shortener'); ?></option>
                        <option value="disabled"><?php esc_html_e('Disabled', 'tp-link-shortener'); ?></option>
                    </select>

                    <select class="form-select" id="tp-sort-order">
                        <option value="updated_at:desc"><?php esc_html_e('Recently Updated', 'tp-link-shortener'); ?></option>
                        <option value="created_at:desc"><?php esc_html_e('Newest First', 'tp-link-shortener'); ?></option>
                        <option value="created_at:asc"><?php esc_html_e('Oldest First', 'tp-link-shortener'); ?></option>
                        <option value="tpKey:asc"><?php esc_html_e('Key (A-Z)', 'tp-link-shortener'); ?></option>
                        <option value="tpKey:desc"><?php esc_html_e('Key (Z-A)', 'tp-link-shortener'); ?></option>
                    </select>
                </div>
                <?php endif; ?>

                <button class="btn btn-primary" id="tp-refresh-btn" title="<?php esc_attr_e('Refresh', 'tp-link-shortener'); ?>">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Loading State -->
        <div class="tp-dashboard-loading" id="tp-dashboard-loading">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden"><?php esc_html_e('Loading...', 'tp-link-shortener'); ?></span>
            </div>
            <p class="mt-2"><?php esc_html_e('Loading your links...', 'tp-link-shortener'); ?></p>
        </div>

        <!-- Error State -->
        <div class="tp-dashboard-error alert alert-danger" id="tp-dashboard-error" style="display: none;">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <span id="tp-error-message"><?php esc_html_e('Error loading links.', 'tp-link-shortener'); ?></span>
            <button class="btn btn-sm btn-outline-danger ms-2" id="tp-retry-btn">
                <?php esc_html_e('Retry', 'tp-link-shortener'); ?>
            </button>
        </div>

        <!-- Empty State -->
        <div class="tp-dashboard-empty" id="tp-dashboard-empty" style="display: none;">
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5><?php esc_html_e('No links found', 'tp-link-shortener'); ?></h5>
                <p class="text-muted"><?php esc_html_e('Create your first short link to get started!', 'tp-link-shortener'); ?></p>
            </div>
        </div>

        <!-- Table -->
        <div class="tp-dashboard-table-wrapper" id="tp-dashboard-table-wrapper" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover tp-dashboard-table" id="tp-dashboard-table">
                    <thead>
                        <tr>
                            <th class="tp-col-shortlink"><?php esc_html_e('Link', 'tp-link-shortener'); ?></th>
                            <th class="tp-col-destination"><?php esc_html_e('Destination', 'tp-link-shortener'); ?></th>
                            <th class="tp-col-usage"><?php esc_html_e('Usage', 'tp-link-shortener'); ?></th>
                            <th class="tp-col-date"><?php esc_html_e('Created', 'tp-link-shortener'); ?></th>
                            <th class="tp-col-actions"><?php esc_html_e('Actions', 'tp-link-shortener'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="tp-dashboard-tbody">
                        <!-- Rows will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="tp-dashboard-pagination" id="tp-dashboard-pagination">
                <div class="tp-pagination-info">
                    <span id="tp-pagination-info">Showing 0-0 of 0</span>
                </div>
                <nav aria-label="Links pagination">
                    <ul class="pagination pagination-sm mb-0" id="tp-pagination-list">
                        <!-- Pagination will be populated by JavaScript -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Copy tooltip -->
<div class="tp-copy-tooltip" id="tp-copy-tooltip"><?php esc_html_e('Copied!', 'tp-link-shortener'); ?></div>

<!-- QR Code Modal -->
<div class="modal fade" id="tp-qr-modal" tabindex="-1" aria-labelledby="tp-qr-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content tp-qr-modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tp-qr-modal-label">
                    <i class="fas fa-qrcode me-2"></i><?php esc_html_e('QR Code', 'tp-link-shortener'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e('Close', 'tp-link-shortener'); ?>"></button>
            </div>
            <div class="modal-body text-center">
                <div class="tp-qr-code-container" id="tp-qr-code-container">
                    <!-- QR code will be generated here -->
                </div>
                <div class="tp-qr-url mt-2" id="tp-qr-url"></div>
            </div>
            <div class="modal-footer tp-qr-modal-footer">
                <button type="button" class="btn btn-primary tp-qr-action-btn" id="tp-qr-download-btn">
                    <i class="fas fa-download me-1"></i><?php esc_html_e('Download', 'tp-link-shortener'); ?>
                </button>
                <button type="button" class="btn btn-outline-secondary tp-qr-action-btn" id="tp-qr-copy-btn">
                    <i class="fas fa-copy me-1"></i><?php esc_html_e('Copy', 'tp-link-shortener'); ?>
                </button>
                <button type="button" class="btn btn-outline-secondary tp-qr-action-btn" id="tp-qr-open-btn">
                    <i class="fas fa-external-link-alt me-1"></i><?php esc_html_e('Open', 'tp-link-shortener'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

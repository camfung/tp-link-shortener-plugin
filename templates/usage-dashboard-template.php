<?php
/**
 * Usage Dashboard Template
 * Three-state template: loading skeleton, error state, content state.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tp-ud-container">

    <!-- Loading Skeleton (visible by default) -->
    <div id="tp-ud-skeleton">
        <div class="tp-ud-skeleton-chart"></div>

        <!-- Summary cards skeleton -->
        <div class="tp-ud-skeleton-stats tp-ud-summary-strip">
            <div class="tp-ud-stat-card-skeleton">
                <div class="tp-ud-skel tp-ud-skel-sm" style="width: 50%; margin-bottom: 8px;"></div>
                <div class="tp-ud-skel tp-ud-skel-lg" style="width: 70%;"></div>
            </div>
            <div class="tp-ud-stat-card-skeleton">
                <div class="tp-ud-skel tp-ud-skel-sm" style="width: 60%; margin-bottom: 8px;"></div>
                <div class="tp-ud-skel tp-ud-skel-lg" style="width: 65%;"></div>
            </div>
            <div class="tp-ud-stat-card-skeleton">
                <div class="tp-ud-skel tp-ud-skel-sm" style="width: 45%; margin-bottom: 8px;"></div>
                <div class="tp-ud-skel tp-ud-skel-lg" style="width: 75%;"></div>
            </div>
        </div>

        <!-- Table skeleton rows -->
        <div class="tp-ud-skeleton-table-wrapper">
            <div class="table-responsive">
                <table class="table tp-ud-table tp-ud-skeleton-table">
                    <thead>
                        <tr>
                            <th class="tp-ud-col-date"><?php esc_html_e('Date', 'tp-link-shortener'); ?></th>
                            <th class="tp-ud-col-hits"><?php esc_html_e('Hits (est.)', 'tp-link-shortener'); ?></th>
                            <th class="tp-ud-col-cost"><?php esc_html_e('Cost', 'tp-link-shortener'); ?></th>
                            <th class="tp-ud-col-balance"><?php esc_html_e('Balance', 'tp-link-shortener'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < 5; $i++): ?>
                        <tr class="tp-ud-skeleton-row">
                            <td><div class="tp-ud-skel tp-ud-skel-md"></div></td>
                            <td><div class="tp-ud-skel tp-ud-skel-lg"></div></td>
                            <td><div class="tp-ud-skel tp-ud-skel-sm"></div></td>
                            <td><div class="tp-ud-skel tp-ud-skel-md"></div></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Error State (hidden by default) -->
    <div id="tp-ud-error" style="display: none;">
        <div class="tp-ud-error">
            <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
            <p id="tp-ud-error-msg"><?php esc_html_e('Error loading usage data.', 'tp-link-shortener'); ?></p>
            <button class="btn btn-outline-primary" id="tp-ud-retry">
                <i class="fas fa-redo me-1"></i><?php esc_html_e('Retry', 'tp-link-shortener'); ?>
            </button>
        </div>
    </div>

    <!-- Content State (hidden by default) -->
    <div id="tp-ud-content" style="display: none;">

        <!-- Date Range Header -->
        <div class="tp-ud-date-header">
            <div class="tp-ud-date-bar">
                <div class="tp-ud-preset-group" role="group" aria-label="<?php esc_attr_e('Date range presets', 'tp-link-shortener'); ?>">
                    <button class="tp-ud-preset-btn" data-days="7">7d</button>
                    <button class="tp-ud-preset-btn" data-days="30">30d</button>
                    <button class="tp-ud-preset-btn" data-days="90">90d</button>
                    <button class="tp-ud-preset-btn tp-ud-custom-trigger" id="tp-ud-custom-toggle">
                        <i class="fas fa-calendar-alt"></i> <?php esc_html_e('Custom', 'tp-link-shortener'); ?>
                    </button>
                </div>
                <span class="tp-ud-date-display" id="tp-ud-date-display"></span>
            </div>
            <div class="tp-ud-custom-panel" id="tp-ud-custom-panel" style="display: none;">
                <div class="tp-ud-custom-inputs">
                    <div class="tp-ud-input-group">
                        <label class="tp-ud-input-label" for="tp-ud-date-start"><?php esc_html_e('From', 'tp-link-shortener'); ?></label>
                        <input type="date" class="tp-ud-date-input" id="tp-ud-date-start">
                    </div>
                    <span class="tp-ud-input-sep"><i class="fas fa-arrow-right"></i></span>
                    <div class="tp-ud-input-group">
                        <label class="tp-ud-input-label" for="tp-ud-date-end"><?php esc_html_e('To', 'tp-link-shortener'); ?></label>
                        <input type="date" class="tp-ud-date-input" id="tp-ud-date-end">
                    </div>
                    <button class="tp-ud-apply-btn" id="tp-ud-date-apply">
                        <i class="fas fa-check me-1"></i><?php esc_html_e('Apply', 'tp-link-shortener'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Stats Strip -->
        <div id="tp-ud-summary-strip"></div>

        <!-- Chart -->
        <div class="tp-ud-chart-wrapper">
            <canvas id="tp-ud-chart"></canvas>
        </div>

        <!-- Stats Table -->
        <div id="tp-ud-table-container" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover tp-ud-table" id="tp-ud-table">
                    <thead>
                        <tr>
                            <th class="tp-ud-col-date tp-ud-sortable" data-sort="date">
                                <?php esc_html_e('Date', 'tp-link-shortener'); ?> <i class="fas fa-sort tp-ud-sort-icon"></i>
                            </th>
                            <th class="tp-ud-col-hits tp-ud-sortable" data-sort="totalHits">
                                <?php esc_html_e('Hits (est.)', 'tp-link-shortener'); ?> <i class="fas fa-sort tp-ud-sort-icon"></i>
                            </th>
                            <th class="tp-ud-col-cost tp-ud-sortable" data-sort="hitCost">
                                <?php esc_html_e('Cost', 'tp-link-shortener'); ?> <i class="fas fa-sort tp-ud-sort-icon"></i>
                            </th>
                            <th class="tp-ud-col-balance tp-ud-sortable" data-sort="balance">
                                <?php esc_html_e('Balance', 'tp-link-shortener'); ?> <i class="fas fa-sort tp-ud-sort-icon"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tp-ud-tbody"></tbody>
                </table>
            </div>

            <!-- Estimated disclaimer footnote -->
            <p class="tp-ud-estimated-note">
                <i class="fas fa-info-circle"></i>
                <?php esc_html_e('Click/QR breakdown is estimated from total hits.', 'tp-link-shortener'); ?>
            </p>

            <!-- Pagination -->
            <div class="tp-ud-pagination" id="tp-ud-pagination" style="display: none;">
                <div class="tp-ud-pagination-info">
                    <span id="tp-ud-pagination-info"><?php esc_html_e('Showing 0-0 of 0', 'tp-link-shortener'); ?></span>
                </div>
                <nav aria-label="<?php esc_attr_e('Usage stats pagination', 'tp-link-shortener'); ?>">
                    <ul class="pagination pagination-sm mb-0" id="tp-ud-pagination-list"></ul>
                </nav>
            </div>
        </div>

        <!-- Empty State -->
        <div id="tp-ud-empty" class="tp-ud-empty" style="display: none;">
            <i class="fas fa-chart-bar fa-3x text-muted mb-3 d-block"></i>
            <h5><?php esc_html_e('No usage data', 'tp-link-shortener'); ?></h5>
            <p class="text-muted" id="tp-ud-empty-range"></p>
        </div>

    </div>

</div>

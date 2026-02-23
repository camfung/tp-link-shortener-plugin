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
        <div class="tp-ud-skeleton-stats">
            <div class="tp-ud-skeleton-stat-block"></div>
            <div class="tp-ud-skeleton-stat-block"></div>
            <div class="tp-ud-skeleton-stat-block"></div>
        </div>
        <div class="tp-ud-skeleton-rows">
            <div class="tp-ud-skeleton-row" style="width: 100%;"></div>
            <div class="tp-ud-skeleton-row" style="width: 85%;"></div>
            <div class="tp-ud-skeleton-row" style="width: 70%;"></div>
            <div class="tp-ud-skeleton-row" style="width: 95%;"></div>
            <div class="tp-ud-skeleton-row" style="width: 60%;"></div>
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
            <div class="tp-ud-date-range">
                <label class="tp-ud-date-label" for="tp-ud-date-start">
                    <i class="fas fa-calendar-alt"></i>
                </label>
                <input type="date" class="form-control form-control-sm" id="tp-ud-date-start">
                <span class="tp-ud-date-sep">&ndash;</span>
                <input type="date" class="form-control form-control-sm" id="tp-ud-date-end">
                <button class="btn btn-sm btn-outline-primary" id="tp-ud-date-apply" title="<?php esc_attr_e('Apply', 'tp-link-shortener'); ?>">
                    <i class="fas fa-check me-1"></i><?php esc_html_e('Apply', 'tp-link-shortener'); ?>
                </button>
            </div>
        </div>

        <!-- Summary Stats Strip -->
        <div id="tp-ud-summary-strip"></div>

        <!-- Chart -->
        <div class="tp-ud-chart-wrapper">
            <canvas id="tp-ud-chart"></canvas>
        </div>

        <!-- Stats Table -->
        <div id="tp-ud-table-container"></div>

    </div>

</div>

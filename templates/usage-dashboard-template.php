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
            <div class="tp-ud-stat-card-skeleton">
                <div class="tp-ud-skel tp-ud-skel-sm" style="width: 55%; margin-bottom: 8px;"></div>
                <div class="tp-ud-skel tp-ud-skel-lg" style="width: 60%;"></div>
            </div>
        </div>

        <!-- Table skeleton rows -->
        <div class="tp-ud-skeleton-table-wrapper">
            <div class="table-responsive">
                <table class="table tp-ud-table tp-ud-skeleton-table">
                    <thead>
                        <tr>
                            <th class="tp-ud-col-date"><?php esc_html_e('Date', 'tp-link-shortener'); ?></th>
                            <th class="tp-ud-col-hits"><?php esc_html_e('Hits', 'tp-link-shortener'); ?></th>
                            <th class="tp-ud-col-other"><?php esc_html_e('Credits', 'tp-link-shortener'); ?></th>
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

        <!-- Add Funds Button (below summary cards) -->
        <div class="tp-ud-add-funds-bar">
            <button class="tp-ud-add-funds-btn" id="tp-ud-add-funds-btn" type="button" data-bs-toggle="modal" data-bs-target="#tp-ud-wallet-modal">
                <i class="fas fa-plus-circle me-1"></i><?php esc_html_e('Add Funds', 'tp-link-shortener'); ?>
            </button>
        </div>

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
                                <?php esc_html_e('Hits', 'tp-link-shortener'); ?> <i class="fas fa-sort tp-ud-sort-icon"></i>
                            </th>
                            <th class="tp-ud-col-other tp-ud-sortable" data-sort="otherServices">
                                <?php esc_html_e('Credits', 'tp-link-shortener'); ?> <i class="fas fa-sort tp-ud-sort-icon"></i>
                            </th>
                            <th class="tp-ud-col-cost tp-ud-sortable" data-sort="hitCost">
                                <?php esc_html_e('Cost', 'tp-link-shortener'); ?> <i class="fas fa-sort tp-ud-sort-icon"></i>
                            </th>
                            <th class="tp-ud-col-balance">
                                <?php esc_html_e('Balance', 'tp-link-shortener'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tp-ud-tbody"></tbody>
                </table>
            </div>


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

    <!-- Wallet Top-Up Modal -->
    <div class="modal fade" id="tp-ud-wallet-modal" tabindex="-1" aria-labelledby="tp-ud-wallet-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content tp-ud-wallet-modal-content">
                <button type="button" class="btn-close tp-ud-wallet-close" data-bs-dismiss="modal" aria-label="<?php esc_attr_e('Close', 'tp-link-shortener'); ?>"></button>

                <!-- Wide banner -->
                <div class="tp-ud-wallet-banner">
                    <div class="tp-ud-wallet-balance-section">
                        <div class="tp-ud-wallet-balance-label"><?php esc_html_e('Your Balance', 'tp-link-shortener'); ?></div>
                        <div class="tp-ud-wallet-balance-amount" id="tp-ud-wallet-balance">--</div>
                    </div>
                    <div class="tp-ud-wallet-topup-section">
                        <div class="tp-ud-wallet-amounts">
                            <button class="tp-ud-wallet-amt-btn" data-amount="5">$5</button>
                            <button class="tp-ud-wallet-amt-btn tp-ud-wallet-amt-selected" data-amount="10">$10</button>
                            <button class="tp-ud-wallet-amt-btn" data-amount="25">$25</button>
                            <input type="number" class="tp-ud-wallet-custom-input" id="tp-ud-wallet-custom" placeholder="$" min="5" max="500" step="0.01" inputmode="decimal">
                        </div>
                        <div id="tp-ud-wallet-topup-message" role="alert" aria-live="polite" style="display:none; width:100%; color:#fff; font-size:.85rem; font-weight:600;"></div>
                        <div id="tp-ud-wallet-topup-status" aria-live="polite" style="display:none; width:100%; color:rgba(255,255,255,.9); font-size:.85rem;"></div>
                        <button class="tp-ud-wallet-add-btn" id="tp-ud-wallet-add-btn">
                            <?php esc_html_e('Add Funds', 'tp-link-shortener'); ?>
                        </button>
                        <a href="#" id="tp-ud-wallet-checkout-link" style="display:none; color:#fff; width:100%; font-size:.85rem; text-decoration:underline;">
                            <?php esc_html_e('Taking too long? Continue to checkout.', 'tp-link-shortener'); ?>
                        </a>
                    </div>
                    <div class="tp-ud-wallet-auto-section">
                        <label class="tp-ud-wallet-toggle">
                            <input type="checkbox" id="tp-ud-wallet-auto-toggle">
                            <span class="tp-ud-wallet-toggle-slider"></span>
                        </label>
                        <span><?php esc_html_e('Auto top-up', 'tp-link-shortener'); ?></span>
                    </div>
                </div>

                <!-- Transactions -->
                <div class="tp-ud-wallet-transactions">
                    <div class="tp-ud-wallet-tx-title"><?php esc_html_e('Recent Transactions', 'tp-link-shortener'); ?></div>
                    <div class="tp-ud-wallet-tx-table-wrap">
                        <table class="tp-ud-wallet-tx-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Date', 'tp-link-shortener'); ?></th>
                                    <th><?php esc_html_e('Description', 'tp-link-shortener'); ?></th>
                                    <th class="text-right"><?php esc_html_e('Amount', 'tp-link-shortener'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="tp-ud-wallet-tx-body">
                                <tr><td colspan="3" class="text-center text-muted"><?php esc_html_e('Loading...', 'tp-link-shortener'); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="tp-ud-wallet-tx-pagination" id="tp-ud-wallet-tx-pagination" style="display:none;">
                        <button class="tp-ud-wallet-tx-page-btn" id="tp-ud-wallet-tx-prev" disabled>
                            <i class="fas fa-chevron-left"></i> <?php esc_html_e('Prev', 'tp-link-shortener'); ?>
                        </button>
                        <span class="tp-ud-wallet-tx-page-info" id="tp-ud-wallet-tx-page-info"></span>
                        <button class="tp-ud-wallet-tx-page-btn" id="tp-ud-wallet-tx-next">
                            <?php esc_html_e('Next', 'tp-link-shortener'); ?> <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

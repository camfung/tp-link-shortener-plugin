<?php
/**
 * Shortcode Template - Matches screenshot design
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$is_premium_only = TP_Link_Shortener::is_premium_only();
$domain = isset($atts['domain']) ? esc_attr($atts['domain']) : TP_Link_Shortener::get_domain();
?>

<div id="tp-link-shortener-wrapper" class="tp-link-shortener-wrapper pb-5">

                <div id="tp-hero-header" class="tp-hero-header text-center mb-2 d-flex flex-row">
                    <span id="tp-title-icon" class="tp-title-icon">
                        <i id="tp-title-icon-link" class="fas fa-link"></i>
                    </span>
                    <h2 id="tp-title" class="tp-title mb-0">
                        <?php esc_html_e('Instant FREE Link Simplifier', 'tp-link-shortener'); ?>
                    </h2>
                </div>
    <div id="tp-row-container" class="row justify-content-center">
        <div id="tp-col-main" class="col-12">
            <div id="tp-hero-shell" class="tp-hero-shell border-0 shadow-sm tp-card">

                <form id="tp-shortener-form" class="tp-shortener-form">
                    <!-- Try It Now Message (for non-logged-in users, shown after link creation) -->
                    <?php if (!is_user_logged_in()): ?>
                    <div id="tp-try-it-message" class="alert alert-info d-none mb-4 tp-try-it">
                        <div id="tp-try-it-content" class="d-flex align-items-center gap-2">
                            <i id="tp-try-it-icon" class="fas fa-hand-pointer fs-5"></i>
                            <strong id="tp-try-it-text" class="text-uppercase"><?php esc_html_e('TRY IT NOW - CLICK THE LINK OR SCAN THE QR CODE', 'tp-link-shortener'); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Main URL Input -->
                    <div id="tp-destination-field-group" class="tp-form-group tp-destination-field mb-4">
                        <div id="tp-destination-input-visual" class="tp-input-visual">
                            <button
                                type="button"
                                class="tp-icon-btn"
                                id="tp-paste-btn"
                                title="<?php esc_attr_e('Paste from clipboard', 'tp-link-shortener'); ?>"
                                aria-label="<?php esc_attr_e('Paste from clipboard', 'tp-link-shortener'); ?>"
                            >
                                <i id="tp-paste-icon" class="fas fa-paste" aria-hidden="true"></i>
                            </button>
                            <div class="tp-floating-label-wrapper">
                                <input
                                    type="url"
                                    id="tp-destination"
                                    name="destination"
                                    class="form-control tp-form-control tp-plain-input"
                                    placeholder="<?php esc_attr_e('Paste your long URL to simplify it', 'tp-link-shortener'); ?>"
                                    required
                                    autocomplete="off"
                                    maxlength="2000"
                                    aria-describedby="tp-destination-hint"
                                />
                                <label id="tp-destination-label" for="tp-destination" class="tp-label tp-floating-label text-uppercase">
                                    <?php esc_html_e('Destination URL', 'tp-link-shortener'); ?>
                                </label>
                            </div>
                        </div>
                        <div id="tp-url-validation-message" class="form-text mt-2 text-end" style="display: none;"></div>
                    </div>

                    <!-- Custom Shortcode Input (only show if not premium-only OR user is premium) -->
                    <?php if (!$is_premium_only || is_user_logged_in()): ?>
                    <div id="tp-custom-key-group" class="tp-form-group tp-custom-key-group tp-keyword-field mb-4" style="display: none;">
                        <div id="tp-custom-key-input-visual" class="tp-input-visual">
                            <button
                                type="button"
                                class="tp-icon-btn"
                                id="tp-suggest-btn"
                                title="<?php esc_attr_e('Get suggestion', 'tp-link-shortener'); ?>"
                                aria-label="<?php esc_attr_e('Get suggestion', 'tp-link-shortener'); ?>"
                            >
                                <i id="tp-suggest-icon" class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                            </button>
                            <div class="tp-floating-label-wrapper">
                                <input
                                    type="text"
                                    id="tp-custom-key"
                                    name="custom_key"
                                    class="form-control tp-form-control tp-plain-input"
                                    placeholder=" "
                                    pattern="[a-zA-Z0-9\.\-_]+"
                                />
                                <label id="tp-custom-key-label" for="tp-custom-key" class="tp-label tp-floating-label text-uppercase">
                                    <?php esc_html_e('Magic Keyword', 'tp-link-shortener'); ?>
                                </label>
                            </div>
                            <button
                                type="submit"
                                class="tp-icon-btn tp-submit-btn"
                                id="tp-submit-btn"
                                title="<?php esc_attr_e('Save the link and it never expires', 'tp-link-shortener'); ?>"
                                aria-label="<?php esc_attr_e('Save the link and it never expires', 'tp-link-shortener'); ?>"
                            >
                                <i class="fas fa-link" id="tp-submit-icon" aria-hidden="true"></i>
                                <span id="tp-submit-text" class="visually-hidden"><?php esc_html_e('Save the link and it never expires', 'tp-link-shortener'); ?></span>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Update Mode Message (hidden by default) -->
                    <div id="tp-update-mode-message" class="alert alert-info d-none mb-4">
                        <div id="tp-update-mode-content" class="d-flex align-items-start gap-3">
                            <i id="tp-update-mode-icon" class="fas fa-info-circle fs-5 mt-1"></i>
                            <div id="tp-update-mode-text-container" class="flex-grow-1">
                                <strong id="tp-update-mode-title">Update your existing link</strong>
                                <p id="tp-update-mode-description" class="mb-0 mt-1">You can update the destination URL for your existing short link below.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Result Section (hidden initially) -->
                    <div id="tp-result-section" class="tp-result-panel mb-4 d-none">
                        <div id="tp-result-grid" class="tp-result-grid">
                            <div id="tp-result-details" class="tp-result-details">
                                <div id="tp-short-url-display" class="tp-short-url-display">
                                    <label id="tp-short-url-label" class="form-label fw-semibold mb-2"><?php esc_html_e('Short link', 'tp-link-shortener'); ?></label>
                                    <div id="tp-short-url-row" class="tp-short-url-row">
                                        <i id="tp-short-url-copy-icon" class="far fa-copy"></i>
                                        <a
                                            href="#"
                                            id="tp-short-url-output"
                                            class="tp-short-url-link"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        ></a>
                                        <button type="button" class="btn tp-btn tp-btn-copy" id="tp-copy-btn">
                                            <i id="tp-copy-btn-icon" class="fas fa-copy me-2"></i>
                                            <?php esc_html_e('Copy', 'tp-link-shortener'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="tp-result-qr" class="tp-result-qr">
                                <div id="tp-qr-section" class="tp-qr-section-wrapper d-none">
                                    <div id="tp-qr-and-preview-container" class="tp-qr-and-preview-container">
                                        <div id="tp-qr-card" class="tp-qr-card">
                                            <div id="tp-qr-code-container" class="tp-qr-code-container rounded-4 d-inline-block" style="cursor: pointer;" title="<?php esc_attr_e('Click to download QR Code', 'tp-link-shortener'); ?>"></div>
                                        </div>
                                        <div id="tp-screenshot-card" class="tp-screenshot-card">
                                            <?php TP_Shortcode::render_screenshot_preview(); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading Indicator -->
                    <div id="tp-loading" class="tp-loading d-none text-center py-4">
                        <div id="tp-spinner" class="spinner-border text-primary tp-spinner" role="status">
                            <span id="tp-spinner-text" class="visually-hidden"><?php esc_html_e('Loading...', 'tp-link-shortener'); ?></span>
                        </div>
                        <p id="tp-loading-text" class="mt-3 mb-0 text-muted"><?php esc_html_e('Creating your short link...', 'tp-link-shortener'); ?></p>
                    </div>

                    <!-- Error Message -->
                    <div id="tp-error-message" class="tp-error-message alert alert-danger d-none" role="alert"></div>

                    <div id="tp-save-link-reminder" class="tp-save-link-reminder d-none mt-3" role="status">
                        <span id="tp-save-link-reminder-text" class="fw-semibold"><?php esc_html_e('Save the link and it never expires', 'tp-link-shortener'); ?></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

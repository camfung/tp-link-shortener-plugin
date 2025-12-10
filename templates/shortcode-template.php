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

<div class="tp-link-shortener-wrapper py-5">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="tp-hero-shell border-0 shadow-sm tp-card">
                <div class="tp-hero-header text-center mb-4">
                    <span class="tp-title-icon">
                        <i class="fas fa-link"></i>
                    </span>
                    <h2 class="tp-title mb-0">
                        <?php esc_html_e('Instant FREE Link Shortener', 'tp-link-shortener'); ?>
                    </h2>
                </div>

                <form id="tp-shortener-form" class="tp-shortener-form">
                    <!-- Try It Now Message (for non-logged-in users, shown after link creation) -->
                    <?php if (!is_user_logged_in()): ?>
                    <div id="tp-try-it-message" class="alert alert-info d-none mb-4 tp-try-it">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-hand-pointer fs-5"></i>
                            <strong class="text-uppercase"><?php esc_html_e('TRY IT NOW - CLICK THE LINK OR SCAN THE QR CODE', 'tp-link-shortener'); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Main URL Input -->
                    <div class="tp-form-group tp-destination-field mb-4">
                        <label for="tp-destination" class="tp-label mb-2 text-uppercase">
                            <?php esc_html_e('Destination URL', 'tp-link-shortener'); ?>
                        </label>
                        <div class="tp-input-visual">
                            <button
                                type="button"
                                class="tp-icon-btn"
                                id="tp-paste-btn"
                                title="<?php esc_attr_e('Paste from clipboard', 'tp-link-shortener'); ?>"
                                aria-label="<?php esc_attr_e('Paste from clipboard', 'tp-link-shortener'); ?>"
                            >
                                <i class="fas fa-paste" aria-hidden="true"></i>
                            </button>
                            <input
                                type="url"
                                id="tp-destination"
                                name="destination"
                                class="form-control tp-form-control tp-plain-input"
                                placeholder="<?php esc_attr_e('Paste the destination URL you want to shorten (e.g., https://example.com/your-page)', 'tp-link-shortener'); ?>"
                                required
                                autocomplete="off"
                                maxlength="2000"
                                aria-describedby="tp-destination-hint"
                            />
                        </div>
                        <small id="tp-destination-hint" class="tp-field-hint">
                            <?php esc_html_e('Enter the full URL where this short link should redirect', 'tp-link-shortener'); ?>
                        </small>
                        <div id="tp-url-validation-message" class="form-text mt-2" style="display: none;"></div>
                    </div>

                    <!-- Custom Shortcode Input (only show if not premium-only OR user is premium) -->
                    <?php if (!$is_premium_only || is_user_logged_in()): ?>
                    <div class="tp-form-group tp-custom-key-group tp-keyword-field mb-4" style="display: none;">
                        <label for="tp-custom-key" class="tp-label mb-2 text-uppercase">
                            <?php esc_html_e('Magic Keyword', 'tp-link-shortener'); ?>
                        </label>
                        <div class="tp-input-visual">
                            <button
                                type="button"
                                class="tp-icon-btn"
                                id="tp-suggest-btn"
                                title="<?php esc_attr_e('Get suggestion', 'tp-link-shortener'); ?>"
                                aria-label="<?php esc_attr_e('Get suggestion', 'tp-link-shortener'); ?>"
                            >
                                <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                            </button>
                            <input
                                type="text"
                                id="tp-custom-key"
                                name="custom_key"
                                class="form-control tp-form-control tp-plain-input"
                                placeholder="<?php esc_attr_e('educator', 'tp-link-shortener'); ?>"
                                pattern="[a-zA-Z0-9\.\-_]+"
                            />
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Trial Message -->
                    <?php if (!is_user_logged_in()): ?>
                    <div class="tp-trial-message alert d-flex flex-column flex-md-row align-items-md-center gap-3 mb-4">
                        <div class="d-flex align-items-start gap-3">
                            <span class="tp-trial-icon">
                                <i class="fas fa-info-circle"></i>
                            </span>
                            <p class="mb-0">
                                <?php esc_html_e('Trial links expire in 24 hours.', 'tp-link-shortener'); ?>
                            </p>
                        </div>
                        <div class="tp-action-buttons d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 ms-md-auto">
                            <button type="button" class="btn tp-btn tp-btn-register w-100">
                                <i class="fas fa-user-plus me-2"></i>
                                <?php esc_html_e('Register', 'tp-link-shortener'); ?>
                            </button>
                            <span class="tp-or text-center mx-sm-2"><?php esc_html_e('or', 'tp-link-shortener'); ?></span>
                            <button type="button" class="btn tp-btn tp-btn-login w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                <?php esc_html_e('Login', 'tp-link-shortener'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Success Message (hidden initially) -->
                    <div id="tp-success-message" class="alert alert-success d-flex align-items-center gap-3 mb-4 d-none">
                        <i class="fas fa-check-circle fs-4"></i>
                        <span class="fw-semibold"><?php esc_html_e('Link created successfully!', 'tp-link-shortener'); ?></span>
                    </div>

                    <div class="tp-submit-row">
                        <button type="submit" class="btn tp-btn tp-btn-primary tp-cta-button w-100" id="tp-submit-btn">
                            <i class="fas fa-link me-2"></i>
                            <?php esc_html_e('Save the link and it never expires', 'tp-link-shortener'); ?>
                        </button>
                    </div>

                    <!-- Result Section (hidden initially) -->
                    <div id="tp-result-section" class="tp-result-panel mb-4 d-none">
                        <div class="tp-result-grid">
                            <div class="tp-result-details">
                                <?php if (!is_user_logged_in()): ?>
                                <div class="tp-meta-row mb-3" id="tp-expiry-row" style="display: none;">
                                    <div class="tp-meta-label">
                                        <?php esc_html_e('The link expires in', 'tp-link-shortener'); ?>
                                    </div>
                                    <div class="tp-expiry-time">
                                        <i class="far fa-clock me-2"></i>
                                        <span class="tp-expiry-counter">00:00:00</span>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="tp-short-url-display">
                                    <label class="form-label fw-semibold mb-2"><?php esc_html_e('Short link', 'tp-link-shortener'); ?></label>
                                    <div class="tp-short-url-row">
                                        <i class="far fa-copy"></i>
                                        <input
                                            type="text"
                                            id="tp-short-url-output"
                                            class="form-control tp-form-control tp-short-url-input"
                                            readonly
                                        />
                                        <button type="button" class="btn tp-btn tp-btn-copy" id="tp-copy-btn">
                                            <i class="fas fa-copy me-2"></i>
                                            <?php esc_html_e('Copy', 'tp-link-shortener'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="tp-result-qr">
                                <div id="tp-qr-section" class="tp-qr-section-wrapper d-none">
                                    <div class="tp-qr-and-preview-container">
                                        <div class="tp-qr-card">
                                            <div id="tp-qr-code-container" class="tp-qr-code-container rounded-4 d-inline-block"></div>
                                            <button type="button" class="btn tp-btn tp-btn-download-qr w-100 mt-3" id="tp-download-qr-btn">
                                                <i class="fas fa-download me-2"></i>
                                                <?php esc_html_e('Download QR Code', 'tp-link-shortener'); ?>
                                            </button>
                                        </div>
                                        <div class="tp-screenshot-card">
                                            <?php TP_Shortcode::render_screenshot_preview(); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading Indicator -->
                    <div id="tp-loading" class="tp-loading d-none text-center py-4">
                        <div class="spinner-border text-primary tp-spinner" role="status">
                            <span class="visually-hidden"><?php esc_html_e('Loading...', 'tp-link-shortener'); ?></span>
                        </div>
                        <p class="mt-3 mb-0 text-muted"><?php esc_html_e('Creating your short link...', 'tp-link-shortener'); ?></p>
                    </div>

                    <!-- Error Message -->
                    <div id="tp-error-message" class="tp-error-message alert alert-danger d-none" role="alert"></div>

                    <div id="tp-save-link-reminder" class="tp-save-link-reminder d-none mt-3" role="status">
                        <span class="fw-semibold"><?php esc_html_e('Save the link and it never expires', 'tp-link-shortener'); ?></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

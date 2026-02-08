<?php
/**
 * Profile Edit Form Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure shared renderer is available
if (!function_exists('tp_render_uwp_field_input')) {
    include_once TP_LINK_SHORTENER_PLUGIN_DIR . 'templates/register-template.php';
}
?>

<div class="tp-auth-container tp-profile-container">
    <div class="tp-card tp-auth-card border-0 shadow-sm">
        <div class="tp-auth-header text-center mb-4">
            <span class="tp-auth-icon">
                <i class="fas fa-user-edit"></i>
            </span>
            <h2 class="tp-auth-title"><?php esc_html_e('Edit Profile', 'tp-link-shortener'); ?></h2>
        </div>

        <form id="tp-profile-form" class="tp-auth-form" novalidate>
            <!-- Alert area -->
            <div id="tp-profile-alert" class="alert d-none mb-3" role="alert"></div>

            <!-- Account Info Section -->
            <div class="tp-profile-section mb-4">
                <h3 class="tp-profile-section-title">
                    <i class="fas fa-id-card"></i>
                    <?php esc_html_e('Account Information', 'tp-link-shortener'); ?>
                </h3>

                <!-- Username (read-only) -->
                <div class="tp-form-group mb-3">
                    <div class="tp-input-visual tp-auth-input-visual tp-input-readonly">
                        <span class="tp-auth-field-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <div class="tp-floating-label-wrapper">
                            <input
                                type="text"
                                id="tp-profile-username"
                                class="form-control tp-form-control tp-plain-input"
                                value="<?php echo esc_attr($current_user->user_login); ?>"
                                readonly
                                disabled
                            />
                            <label for="tp-profile-username" class="tp-label tp-floating-label text-uppercase">
                                <?php esc_html_e('Username', 'tp-link-shortener'); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Email -->
                <div class="tp-form-group mb-3">
                    <div class="tp-input-visual tp-auth-input-visual">
                        <span class="tp-auth-field-icon">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <div class="tp-floating-label-wrapper">
                            <input
                                type="email"
                                id="tp-profile-email"
                                name="email"
                                class="form-control tp-form-control tp-plain-input"
                                placeholder=" "
                                value="<?php echo esc_attr($current_user->user_email); ?>"
                                required
                                autocomplete="email"
                            />
                            <label for="tp-profile-email" class="tp-label tp-floating-label text-uppercase">
                                <?php esc_html_e('Email', 'tp-link-shortener'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="tp-field-error text-danger small mt-1" style="display:none;"></div>
                </div>

                <!-- First Name -->
                <div class="tp-form-group mb-3">
                    <div class="tp-input-visual tp-auth-input-visual">
                        <span class="tp-auth-field-icon">
                            <i class="fas fa-id-badge"></i>
                        </span>
                        <div class="tp-floating-label-wrapper">
                            <input
                                type="text"
                                id="tp-profile-first-name"
                                name="first_name"
                                class="form-control tp-form-control tp-plain-input"
                                placeholder=" "
                                value="<?php echo esc_attr($current_user->first_name); ?>"
                                autocomplete="given-name"
                            />
                            <label for="tp-profile-first-name" class="tp-label tp-floating-label text-uppercase">
                                <?php esc_html_e('First Name', 'tp-link-shortener'); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Last Name -->
                <div class="tp-form-group mb-3">
                    <div class="tp-input-visual tp-auth-input-visual">
                        <span class="tp-auth-field-icon">
                            <i class="fas fa-id-badge"></i>
                        </span>
                        <div class="tp-floating-label-wrapper">
                            <input
                                type="text"
                                id="tp-profile-last-name"
                                name="last_name"
                                class="form-control tp-form-control tp-plain-input"
                                placeholder=" "
                                value="<?php echo esc_attr($current_user->last_name); ?>"
                                autocomplete="family-name"
                            />
                            <label for="tp-profile-last-name" class="tp-label tp-floating-label text-uppercase">
                                <?php esc_html_e('Last Name', 'tp-link-shortener'); ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($uwp_fields)): ?>
            <!-- UWP Custom Fields Section -->
            <div class="tp-profile-section mb-4">
                <h3 class="tp-profile-section-title">
                    <i class="fas fa-list-alt"></i>
                    <?php esc_html_e('Additional Information', 'tp-link-shortener'); ?>
                </h3>

                <?php foreach ($uwp_fields as $field):
                    $meta_value = get_user_meta($current_user->ID, 'uwp_meta_' . $field->htmlvar_name, true);
                ?>
                <div class="tp-form-group mb-3">
                    <div class="tp-input-visual tp-auth-input-visual">
                        <span class="tp-auth-field-icon">
                            <i class="<?php echo esc_attr($field->field_icon ?: 'fas fa-info-circle'); ?>"></i>
                        </span>
                        <div class="tp-floating-label-wrapper">
                            <?php tp_render_uwp_field_input($field, 'profile', $meta_value); ?>
                            <label for="tp-profile-uwp-<?php echo esc_attr($field->htmlvar_name); ?>" class="tp-label tp-floating-label text-uppercase">
                                <?php echo esc_html($field->site_title); ?>
                            </label>
                        </div>
                    </div>
                    <div class="tp-field-error text-danger small mt-1" style="display:none;"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Change Password Section (Collapsible) -->
            <div class="tp-profile-section mb-4">
                <button type="button" class="tp-profile-section-toggle" data-bs-toggle="collapse" data-bs-target="#tp-password-change-section" aria-expanded="false">
                    <h3 class="tp-profile-section-title mb-0">
                        <i class="fas fa-key"></i>
                        <?php esc_html_e('Change Password', 'tp-link-shortener'); ?>
                    </h3>
                    <i class="fas fa-chevron-down tp-toggle-icon"></i>
                </button>

                <div id="tp-password-change-section" class="collapse mt-3">
                    <!-- Current Password -->
                    <div class="tp-form-group mb-3">
                        <div class="tp-input-visual tp-auth-input-visual">
                            <span class="tp-auth-field-icon">
                                <i class="fas fa-lock"></i>
                            </span>
                            <div class="tp-floating-label-wrapper">
                                <input
                                    type="password"
                                    id="tp-profile-current-password"
                                    name="current_password"
                                    class="form-control tp-form-control tp-plain-input"
                                    placeholder=" "
                                    autocomplete="current-password"
                                />
                                <label for="tp-profile-current-password" class="tp-label tp-floating-label text-uppercase">
                                    <?php esc_html_e('Current Password', 'tp-link-shortener'); ?>
                                </label>
                            </div>
                            <button type="button" class="tp-password-toggle" tabindex="-1" aria-label="<?php esc_attr_e('Toggle password visibility', 'tp-link-shortener'); ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div class="tp-form-group mb-3">
                        <div class="tp-input-visual tp-auth-input-visual">
                            <span class="tp-auth-field-icon">
                                <i class="fas fa-lock"></i>
                            </span>
                            <div class="tp-floating-label-wrapper">
                                <input
                                    type="password"
                                    id="tp-profile-new-password"
                                    name="new_password"
                                    class="form-control tp-form-control tp-plain-input"
                                    placeholder=" "
                                    minlength="8"
                                    autocomplete="new-password"
                                />
                                <label for="tp-profile-new-password" class="tp-label tp-floating-label text-uppercase">
                                    <?php esc_html_e('New Password', 'tp-link-shortener'); ?>
                                </label>
                            </div>
                            <button type="button" class="tp-password-toggle" tabindex="-1" aria-label="<?php esc_attr_e('Toggle password visibility', 'tp-link-shortener'); ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="tp-password-strength mt-2" style="display:none;">
                            <div class="tp-password-strength-bar">
                                <div class="tp-password-strength-fill"></div>
                            </div>
                            <span class="tp-password-strength-text small"></span>
                        </div>
                    </div>

                    <!-- Confirm New Password -->
                    <div class="tp-form-group mb-3">
                        <div class="tp-input-visual tp-auth-input-visual">
                            <span class="tp-auth-field-icon">
                                <i class="fas fa-lock"></i>
                            </span>
                            <div class="tp-floating-label-wrapper">
                                <input
                                    type="password"
                                    id="tp-profile-confirm-password"
                                    name="confirm_password"
                                    class="form-control tp-form-control tp-plain-input"
                                    placeholder=" "
                                    minlength="8"
                                    autocomplete="new-password"
                                />
                                <label for="tp-profile-confirm-password" class="tp-label tp-floating-label text-uppercase">
                                    <?php esc_html_e('Confirm New Password', 'tp-link-shortener'); ?>
                                </label>
                            </div>
                            <button type="button" class="tp-password-toggle" tabindex="-1" aria-label="<?php esc_attr_e('Toggle password visibility', 'tp-link-shortener'); ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="tp-field-error text-danger small mt-1" style="display:none;"></div>
                    </div>
                </div>
            </div>

            <!-- Hidden redirect URL -->
            <?php if (!empty($atts['redirect'])): ?>
            <input type="hidden" name="redirect_url" value="<?php echo esc_url($atts['redirect']); ?>" />
            <?php endif; ?>

            <!-- Submit -->
            <div class="d-grid mt-4">
                <button type="submit" class="btn tp-btn tp-btn-primary tp-cta-button" id="tp-profile-submit">
                    <i class="fas fa-save me-2"></i>
                    <?php esc_html_e('Save Changes', 'tp-link-shortener'); ?>
                </button>
            </div>
        </form>

        <!-- Logout link -->
        <div class="tp-auth-link text-center mt-3">
            <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="tp-link">
                <i class="fas fa-sign-out-alt me-1"></i>
                <?php esc_html_e('Log out', 'tp-link-shortener'); ?>
            </a>
        </div>
    </div>
</div>

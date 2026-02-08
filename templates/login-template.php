<?php
/**
 * Login Form Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tp-auth-container">
    <div class="tp-card tp-auth-card border-0 shadow-sm">
        <div class="tp-auth-header text-center mb-4">
            <span class="tp-auth-icon">
                <i class="fas fa-sign-in-alt"></i>
            </span>
            <h2 class="tp-auth-title"><?php esc_html_e('Log In', 'tp-link-shortener'); ?></h2>
        </div>

        <form id="tp-login-form" class="tp-auth-form" novalidate>
            <!-- Alert area -->
            <div id="tp-login-alert" class="alert d-none mb-3" role="alert"></div>

            <!-- Username / Email -->
            <div class="tp-form-group mb-3">
                <div class="tp-input-visual tp-auth-input-visual">
                    <span class="tp-auth-field-icon">
                        <i class="fas fa-user"></i>
                    </span>
                    <div class="tp-floating-label-wrapper">
                        <input
                            type="text"
                            id="tp-login-username"
                            name="username"
                            class="form-control tp-form-control tp-plain-input"
                            placeholder=" "
                            required
                            autocomplete="username"
                        />
                        <label for="tp-login-username" class="tp-label tp-floating-label text-uppercase">
                            <?php esc_html_e('Username or Email', 'tp-link-shortener'); ?>
                        </label>
                    </div>
                </div>
                <div class="tp-field-error text-danger small mt-1" style="display:none;"></div>
            </div>

            <!-- Password -->
            <div class="tp-form-group mb-3">
                <div class="tp-input-visual tp-auth-input-visual">
                    <span class="tp-auth-field-icon">
                        <i class="fas fa-lock"></i>
                    </span>
                    <div class="tp-floating-label-wrapper">
                        <input
                            type="password"
                            id="tp-login-password"
                            name="password"
                            class="form-control tp-form-control tp-plain-input"
                            placeholder=" "
                            required
                            autocomplete="current-password"
                        />
                        <label for="tp-login-password" class="tp-label tp-floating-label text-uppercase">
                            <?php esc_html_e('Password', 'tp-link-shortener'); ?>
                        </label>
                    </div>
                    <button type="button" class="tp-password-toggle" tabindex="-1" aria-label="<?php esc_attr_e('Toggle password visibility', 'tp-link-shortener'); ?>">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="tp-field-error text-danger small mt-1" style="display:none;"></div>
            </div>

            <!-- Remember Me -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input type="checkbox" id="tp-login-remember" name="remember" class="form-check-input" value="1" />
                    <label for="tp-login-remember" class="form-check-label small text-muted">
                        <?php esc_html_e('Remember me', 'tp-link-shortener'); ?>
                    </label>
                </div>
                <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="tp-link small">
                    <?php esc_html_e('Forgot password?', 'tp-link-shortener'); ?>
                </a>
            </div>

            <!-- Hidden redirect URL -->
            <?php if (!empty($atts['redirect'])): ?>
            <input type="hidden" name="redirect_url" value="<?php echo esc_url($atts['redirect']); ?>" />
            <?php endif; ?>

            <!-- Submit -->
            <div class="d-grid mt-4">
                <button type="submit" class="btn tp-btn tp-btn-primary tp-cta-button" id="tp-login-submit">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    <?php esc_html_e('Log In', 'tp-link-shortener'); ?>
                </button>
            </div>
        </form>

        <!-- Register link -->
        <div class="tp-auth-link text-center mt-3">
            <?php
            $register_url = !empty($atts['register_url']) ? $atts['register_url'] : wp_registration_url();
            ?>
            <span class="text-muted"><?php esc_html_e("Don't have an account?", 'tp-link-shortener'); ?></span>
            <a href="<?php echo esc_url($register_url); ?>" class="tp-link">
                <?php esc_html_e('Register', 'tp-link-shortener'); ?>
            </a>
        </div>
    </div>
</div>

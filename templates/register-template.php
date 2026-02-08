<?php
/**
 * Registration Form Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define shared UWP field renderer if not already loaded
if (!function_exists('tp_render_uwp_field_input')) {
    function tp_render_uwp_field_input($field, $context, $value = '') {
        $prefix = $context === 'register' ? 'tp-reg' : 'tp-profile';
        $id = $prefix . '-uwp-' . esc_attr($field->htmlvar_name);
        $name = 'uwp_' . esc_attr($field->htmlvar_name);
        $required = $field->is_required ? 'required' : '';
        $esc_value = esc_attr($value);

        switch ($field->field_type) {
            case 'textarea':
                echo '<textarea id="' . $id . '" name="' . $name . '" class="form-control tp-form-control tp-plain-input" placeholder=" " ' . $required . ' rows="3">' . esc_textarea($value) . '</textarea>';
                break;

            case 'select':
                echo '<select id="' . $id . '" name="' . $name . '" class="form-control tp-form-control tp-plain-input" ' . $required . '>';
                echo '<option value="">' . esc_html__('Select...', 'tp-link-shortener') . '</option>';
                if (!empty($field->option_values)) {
                    $options = explode(',', $field->option_values);
                    foreach ($options as $opt) {
                        $opt = trim($opt);
                        if (strpos($opt, '/') !== false) {
                            list($val, $label) = explode('/', $opt, 2);
                        } else {
                            $val = $label = $opt;
                        }
                        $val = trim($val);
                        $selected = ($val === $value) ? ' selected' : '';
                        echo '<option value="' . esc_attr($val) . '"' . $selected . '>' . esc_html(trim($label)) . '</option>';
                    }
                }
                echo '</select>';
                break;

            case 'checkbox':
                $checked = $value ? ' checked' : '';
                echo '<div class="form-check tp-auth-checkbox">';
                echo '<input type="checkbox" id="' . $id . '" name="' . $name . '" value="1" class="form-check-input" ' . $required . $checked . ' />';
                echo '</div>';
                break;

            case 'radio':
                if (!empty($field->option_values)) {
                    $options = explode(',', $field->option_values);
                    foreach ($options as $i => $opt) {
                        $opt = trim($opt);
                        if (strpos($opt, '/') !== false) {
                            list($val, $label) = explode('/', $opt, 2);
                        } else {
                            $val = $label = $opt;
                        }
                        $val = trim($val);
                        $checked = ($val === $value) ? ' checked' : '';
                        echo '<div class="form-check form-check-inline">';
                        echo '<input type="radio" id="' . $id . '-' . $i . '" name="' . $name . '" value="' . esc_attr($val) . '" class="form-check-input" ' . ($i === 0 ? $required : '') . $checked . ' />';
                        echo '<label class="form-check-label" for="' . $id . '-' . $i . '">' . esc_html(trim($label)) . '</label>';
                        echo '</div>';
                    }
                }
                break;

            case 'url':
                echo '<input type="url" id="' . $id . '" name="' . $name . '" class="form-control tp-form-control tp-plain-input" placeholder=" " value="' . $esc_value . '" ' . $required . ' />';
                break;

            case 'number':
                echo '<input type="number" id="' . $id . '" name="' . $name . '" class="form-control tp-form-control tp-plain-input" placeholder=" " value="' . $esc_value . '" ' . $required . ' />';
                break;

            case 'email':
                echo '<input type="email" id="' . $id . '" name="' . $name . '" class="form-control tp-form-control tp-plain-input" placeholder=" " value="' . $esc_value . '" ' . $required . ' />';
                break;

            default:
                echo '<input type="text" id="' . $id . '" name="' . $name . '" class="form-control tp-form-control tp-plain-input" placeholder=" " value="' . $esc_value . '" ' . $required . ' />';
                break;
        }
    }
}
?>

<div class="tp-auth-container">
    <div class="tp-card tp-auth-card border-0 shadow-sm">
        <div class="tp-auth-header text-center mb-4">
            <span class="tp-auth-icon">
                <i class="fas fa-user-plus"></i>
            </span>
            <h2 class="tp-auth-title"><?php esc_html_e('Create Account', 'tp-link-shortener'); ?></h2>
        </div>

        <form id="tp-register-form" class="tp-auth-form" novalidate>
            <!-- Alert area -->
            <div id="tp-register-alert" class="alert d-none mb-3" role="alert"></div>

            <!-- Username -->
            <div class="tp-form-group mb-3">
                <div class="tp-input-visual tp-auth-input-visual">
                    <span class="tp-auth-field-icon">
                        <i class="fas fa-user"></i>
                    </span>
                    <div class="tp-floating-label-wrapper">
                        <input
                            type="text"
                            id="tp-reg-username"
                            name="username"
                            class="form-control tp-form-control tp-plain-input"
                            placeholder=" "
                            required
                            autocomplete="username"
                        />
                        <label for="tp-reg-username" class="tp-label tp-floating-label text-uppercase">
                            <?php esc_html_e('Username', 'tp-link-shortener'); ?>
                        </label>
                    </div>
                </div>
                <div class="tp-field-error text-danger small mt-1" style="display:none;"></div>
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
                            id="tp-reg-email"
                            name="email"
                            class="form-control tp-form-control tp-plain-input"
                            placeholder=" "
                            required
                            autocomplete="email"
                        />
                        <label for="tp-reg-email" class="tp-label tp-floating-label text-uppercase">
                            <?php esc_html_e('Email', 'tp-link-shortener'); ?>
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
                            id="tp-reg-password"
                            name="password"
                            class="form-control tp-form-control tp-plain-input"
                            placeholder=" "
                            required
                            minlength="8"
                            autocomplete="new-password"
                        />
                        <label for="tp-reg-password" class="tp-label tp-floating-label text-uppercase">
                            <?php esc_html_e('Password', 'tp-link-shortener'); ?>
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
                <div class="tp-field-error text-danger small mt-1" style="display:none;"></div>
            </div>

            <!-- Confirm Password -->
            <div class="tp-form-group mb-3">
                <div class="tp-input-visual tp-auth-input-visual">
                    <span class="tp-auth-field-icon">
                        <i class="fas fa-lock"></i>
                    </span>
                    <div class="tp-floating-label-wrapper">
                        <input
                            type="password"
                            id="tp-reg-password-confirm"
                            name="password_confirm"
                            class="form-control tp-form-control tp-plain-input"
                            placeholder=" "
                            required
                            minlength="8"
                            autocomplete="new-password"
                        />
                        <label for="tp-reg-password-confirm" class="tp-label tp-floating-label text-uppercase">
                            <?php esc_html_e('Confirm Password', 'tp-link-shortener'); ?>
                        </label>
                    </div>
                    <button type="button" class="tp-password-toggle" tabindex="-1" aria-label="<?php esc_attr_e('Toggle password visibility', 'tp-link-shortener'); ?>">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="tp-field-error text-danger small mt-1" style="display:none;"></div>
            </div>

            <?php if (!empty($uwp_fields)): ?>
            <!-- UWP Custom Fields -->
            <?php foreach ($uwp_fields as $field): ?>
            <div class="tp-form-group mb-3">
                <div class="tp-input-visual tp-auth-input-visual">
                    <span class="tp-auth-field-icon">
                        <i class="<?php echo esc_attr($field->field_icon ?: 'fas fa-info-circle'); ?>"></i>
                    </span>
                    <div class="tp-floating-label-wrapper">
                        <?php tp_render_uwp_field_input($field, 'register'); ?>
                        <label for="tp-reg-uwp-<?php echo esc_attr($field->htmlvar_name); ?>" class="tp-label tp-floating-label text-uppercase">
                            <?php echo esc_html($field->site_title); ?>
                        </label>
                    </div>
                </div>
                <div class="tp-field-error text-danger small mt-1" style="display:none;"></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Hidden redirect URL -->
            <?php if (!empty($atts['redirect'])): ?>
            <input type="hidden" name="redirect_url" value="<?php echo esc_url($atts['redirect']); ?>" />
            <?php endif; ?>

            <!-- Submit -->
            <div class="d-grid mt-4">
                <button type="submit" class="btn tp-btn tp-btn-primary tp-cta-button" id="tp-register-submit">
                    <i class="fas fa-user-plus me-2"></i>
                    <?php esc_html_e('Create Account', 'tp-link-shortener'); ?>
                </button>
            </div>
        </form>

        <!-- Login link -->
        <div class="tp-auth-link text-center mt-3">
            <?php
            $login_url = !empty($atts['login_url']) ? $atts['login_url'] : wp_login_url();
            ?>
            <span class="text-muted"><?php esc_html_e('Already have an account?', 'tp-link-shortener'); ?></span>
            <a href="<?php echo esc_url($login_url); ?>" class="tp-link">
                <?php esc_html_e('Log in', 'tp-link-shortener'); ?>
            </a>
        </div>
    </div>
</div>

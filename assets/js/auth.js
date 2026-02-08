/**
 * Traffic Portal Auth Forms — JavaScript
 * jQuery IIFE module handling register, login, and profile forms
 */
(function ($) {
    'use strict';

    if (typeof tpAuth === 'undefined') {
        return;
    }

    var TPAuth = {

        init: function () {
            this.bindEvents();
            this.loadProfileData();
        },

        bindEvents: function () {
            $(document).on('submit', '#tp-register-form', this.handleRegister.bind(this));
            $(document).on('submit', '#tp-login-form', this.handleLogin.bind(this));
            $(document).on('submit', '#tp-profile-form', this.handleProfileUpdate.bind(this));

            // Password toggle
            $(document).on('click', '.tp-password-toggle', this.togglePassword);

            // Password strength
            $(document).on('input', '#tp-reg-password, #tp-profile-new-password', this.onPasswordInput);

            // Real-time validation
            $(document).on('blur', '.tp-auth-form input[required]', this.onFieldBlur);
            $(document).on('input', '.tp-auth-form input.is-invalid', this.onFieldInput);
        },

        // ─── Registration ────────────────────────────────────────────

        handleRegister: function (e) {
            e.preventDefault();

            var $form = $('#tp-register-form');
            if (!this.validateForm($form)) {
                return;
            }

            // Check password match
            var pw = $('#tp-reg-password').val();
            var pwConfirm = $('#tp-reg-password-confirm').val();
            if (pw !== pwConfirm) {
                this.showFieldError($('#tp-reg-password-confirm'), tpAuth.strings.passwordMismatch);
                return;
            }

            var $btn = $('#tp-register-submit');
            this.setLoading($btn, tpAuth.strings.registering);

            $.ajax({
                url: tpAuth.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=tp_register&nonce=' + tpAuth.nonce,
                success: function (response) {
                    if (response.success) {
                        TPAuth.showAlert('#tp-register-alert', 'success', response.data.message);
                        if (response.data.redirect) {
                            setTimeout(function () {
                                window.location.href = response.data.redirect;
                            }, 800);
                        }
                    } else {
                        TPAuth.showAlert('#tp-register-alert', 'danger', response.data.message);
                        TPAuth.resetLoading($btn, '<i class="fas fa-user-plus me-2"></i>' + 'Create Account');
                    }
                },
                error: function () {
                    TPAuth.showAlert('#tp-register-alert', 'danger', tpAuth.strings.error);
                    TPAuth.resetLoading($btn, '<i class="fas fa-user-plus me-2"></i>' + 'Create Account');
                }
            });
        },

        // ─── Login ───────────────────────────────────────────────────

        handleLogin: function (e) {
            e.preventDefault();

            var $form = $('#tp-login-form');
            if (!this.validateForm($form)) {
                return;
            }

            var $btn = $('#tp-login-submit');
            this.setLoading($btn, tpAuth.strings.loggingIn);

            $.ajax({
                url: tpAuth.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=tp_login&nonce=' + tpAuth.nonce,
                success: function (response) {
                    if (response.success) {
                        TPAuth.showAlert('#tp-login-alert', 'success', response.data.message);
                        if (response.data.redirect) {
                            setTimeout(function () {
                                window.location.href = response.data.redirect;
                            }, 800);
                        }
                    } else {
                        TPAuth.showAlert('#tp-login-alert', 'danger', response.data.message);
                        TPAuth.resetLoading($btn, '<i class="fas fa-sign-in-alt me-2"></i>' + 'Log In');
                    }
                },
                error: function () {
                    TPAuth.showAlert('#tp-login-alert', 'danger', tpAuth.strings.error);
                    TPAuth.resetLoading($btn, '<i class="fas fa-sign-in-alt me-2"></i>' + 'Log In');
                }
            });
        },

        // ─── Profile Update ──────────────────────────────────────────

        handleProfileUpdate: function (e) {
            e.preventDefault();

            var $form = $('#tp-profile-form');
            if (!this.validateProfileForm($form)) {
                return;
            }

            var $btn = $('#tp-profile-submit');
            this.setLoading($btn, tpAuth.strings.saving);

            $.ajax({
                url: tpAuth.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=tp_update_profile&nonce=' + tpAuth.nonce,
                success: function (response) {
                    if (response.success) {
                        TPAuth.showAlert('#tp-profile-alert', 'success', response.data.message);
                        TPAuth.showSnackbar(response.data.message, 'success');
                        // Clear password fields
                        $('#tp-profile-current-password, #tp-profile-new-password, #tp-profile-confirm-password').val('');
                        $('.tp-password-strength').hide();
                    } else {
                        TPAuth.showAlert('#tp-profile-alert', 'danger', response.data.message);
                    }
                    TPAuth.resetLoading($btn, '<i class="fas fa-save me-2"></i>' + 'Save Changes');
                },
                error: function () {
                    TPAuth.showAlert('#tp-profile-alert', 'danger', tpAuth.strings.error);
                    TPAuth.resetLoading($btn, '<i class="fas fa-save me-2"></i>' + 'Save Changes');
                }
            });
        },

        // ─── Load Profile ────────────────────────────────────────────

        loadProfileData: function () {
            if ($('#tp-profile-form').length === 0 || !tpAuth.isLoggedIn) {
                return;
            }

            $.ajax({
                url: tpAuth.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_get_profile',
                    nonce: tpAuth.nonce
                },
                success: function (response) {
                    if (!response.success) {
                        return;
                    }
                    var data = response.data;

                    // Populate UWP fields (core fields are pre-filled via PHP)
                    $.each(data, function (key, val) {
                        if (key.indexOf('uwp_') === 0) {
                            var $input = $('[name="' + key + '"]');
                            if ($input.length) {
                                if ($input.is(':checkbox')) {
                                    $input.prop('checked', !!val);
                                } else if ($input.is(':radio')) {
                                    $input.filter('[value="' + val + '"]').prop('checked', true);
                                } else {
                                    $input.val(val);
                                }
                            }
                        }
                    });
                }
            });
        },

        // ─── Validation ──────────────────────────────────────────────

        validateForm: function ($form) {
            var valid = true;
            $form.find('input[required], select[required], textarea[required]').each(function () {
                if (!TPAuth.validateField($(this))) {
                    valid = false;
                }
            });
            return valid;
        },

        validateProfileForm: function ($form) {
            var valid = true;
            var $email = $form.find('#tp-profile-email');
            if ($email.length && !this.validateField($email)) {
                valid = false;
            }

            // Validate password fields only if new password is filled
            var newPw = $('#tp-profile-new-password').val();
            if (newPw) {
                if (newPw.length < 8) {
                    this.showFieldError($('#tp-profile-new-password'), tpAuth.strings.passwordShort);
                    valid = false;
                }
                var confirmPw = $('#tp-profile-confirm-password').val();
                if (newPw !== confirmPw) {
                    this.showFieldError($('#tp-profile-confirm-password'), tpAuth.strings.passwordMismatch);
                    valid = false;
                }
                if (!$('#tp-profile-current-password').val()) {
                    this.showFieldError($('#tp-profile-current-password'), tpAuth.strings.required);
                    valid = false;
                }
            }

            return valid;
        },

        validateField: function ($input) {
            var val = $input.val().trim();
            var type = $input.attr('type');
            var $error = $input.closest('.tp-form-group').find('.tp-field-error');

            // Required check
            if ($input.prop('required') && !val) {
                this.showFieldError($input, tpAuth.strings.required);
                return false;
            }

            // Email validation
            if (type === 'email' && val) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(val)) {
                    this.showFieldError($input, tpAuth.strings.invalidEmail);
                    return false;
                }
            }

            // Password length
            if (type === 'password' && val && $input.attr('minlength')) {
                var min = parseInt($input.attr('minlength'), 10);
                if (val.length < min) {
                    this.showFieldError($input, tpAuth.strings.passwordShort);
                    return false;
                }
            }

            // Clear error, mark valid
            $input.removeClass('is-invalid').addClass('is-valid');
            $error.hide().text('');
            return true;
        },

        showFieldError: function ($input, message) {
            var $error = $input.closest('.tp-form-group').find('.tp-field-error');
            $input.removeClass('is-valid').addClass('is-invalid');
            $error.text(message).show();
            // Scroll to first error
            if ($('.is-invalid').first().is($input)) {
                $input[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        },

        onFieldBlur: function () {
            TPAuth.validateField($(this));
        },

        onFieldInput: function () {
            // Re-validate on input if currently showing error
            TPAuth.validateField($(this));
        },

        // ─── Password Strength ───────────────────────────────────────

        onPasswordInput: function () {
            var $input = $(this);
            var pw = $input.val();
            var $meter = $input.closest('.tp-form-group').find('.tp-password-strength');
            var $fill = $meter.find('.tp-password-strength-fill');
            var $text = $meter.find('.tp-password-strength-text');

            if (!pw) {
                $meter.hide();
                return;
            }

            $meter.show();
            var strength = TPAuth.calculatePasswordStrength(pw);

            $fill.attr('data-strength', strength.level);

            var labels = [
                '',
                tpAuth.strings.passwordWeak,
                tpAuth.strings.passwordFair,
                tpAuth.strings.passwordGood,
                tpAuth.strings.passwordStrong
            ];
            $text.text(labels[strength.level] || '');
        },

        calculatePasswordStrength: function (password) {
            var score = 0;
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
            if (/\d/.test(password)) score++;
            if (/[^a-zA-Z0-9]/.test(password)) score++;

            var level = 0;
            if (score >= 5) level = 4;
            else if (score >= 4) level = 3;
            else if (score >= 3) level = 2;
            else if (score >= 1) level = 1;

            return { level: level, score: score };
        },

        // ─── Password Toggle ─────────────────────────────────────────

        togglePassword: function () {
            var $btn = $(this);
            var $input = $btn.closest('.tp-input-visual').find('input');
            var $icon = $btn.find('i');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        },

        // ─── UI Helpers ──────────────────────────────────────────────

        showAlert: function (selector, type, message) {
            $(selector)
                .removeClass('d-none alert-success alert-danger alert-warning alert-info')
                .addClass('alert-' + type)
                .html(message);

            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function () {
                    $(selector).addClass('d-none');
                }, 5000);
            }
        },

        setLoading: function ($btn, text) {
            $btn.data('original-html', $btn.html());
            $btn.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + text
            );
        },

        resetLoading: function ($btn, html) {
            var original = html || $btn.data('original-html');
            $btn.prop('disabled', false).html(original);
        },

        showSnackbar: function (message, type) {
            // Remove any existing snackbar
            $('.tp-snackbar').remove();

            var iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            var $snackbar = $(
                '<div class="tp-snackbar tp-snackbar-' + type + '">' +
                '<i class="fas ' + iconClass + '"></i>' +
                '<span>' + message + '</span>' +
                '</div>'
            );

            $('body').append($snackbar);

            // Trigger show animation
            setTimeout(function () {
                $snackbar.addClass('tp-snackbar-show');
            }, 10);

            // Auto-hide
            setTimeout(function () {
                $snackbar.removeClass('tp-snackbar-show');
                setTimeout(function () {
                    $snackbar.remove();
                }, 400);
            }, 3500);
        }
    };

    $(document).ready(function () {
        TPAuth.init();
    });

})(jQuery);

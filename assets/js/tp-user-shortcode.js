jQuery(document).ready(function($) {
    
    // Handle signup form submission
    $('#user-signup-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('.submit-btn');
        var messages = $('#signup-messages');
        
        // Disable submit button
        submitBtn.prop('disabled', true).text('Creating Account...');
        messages.removeClass('error success').empty();
        
        var formData = {
            action: 'user_signup',
            username: form.find('input[name="username"]').val(),
            email: form.find('input[name="email"]').val(),
            password: form.find('input[name="password"]').val(),
            first_name: form.find('input[name="first_name"]').val(),
            last_name: form.find('input[name="last_name"]').val(),
            redirect_url: form.find('input[name="redirect_url"]').val(),
            user_plugin_nonce: form.find('input[name="user_plugin_nonce"]').val()
        };
        
        $.post(ajax_object.ajax_url, formData, function(response) {
            if (response.success) {
                messages.addClass('success').html('<p>' + response.data.message + '</p>');
                form[0].reset();
                
                // Redirect after successful signup
                if (response.data.redirect_url) {
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1500);
                }
            } else {
                messages.addClass('error').html('<p>' + response.data + '</p>');
            }
        }).fail(function() {
            messages.addClass('error').html('<p>An error occurred. Please try again.</p>');
        }).always(function() {
            submitBtn.prop('disabled', false).text(submitBtn.data('original-text') || 'Sign Up');
        });
    });
    
    // Handle signin form submission
    $('#user-signin-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('.submit-btn');
        var messages = $('#signin-messages');
        
        // Store original button text
        if (!submitBtn.data('original-text')) {
            submitBtn.data('original-text', submitBtn.text());
        }
        
        // Disable submit button
        submitBtn.prop('disabled', true).text('Signing In...');
        messages.removeClass('error success').empty();
        
        var formData = {
            action: 'user_signin',
            username: form.find('input[name="username"]').val(),
            password: form.find('input[name="password"]').val(),
            remember: form.find('input[name="remember"]').is(':checked') ? 1 : 0,
            redirect_url: form.find('input[name="redirect_url"]').val(),
            user_plugin_nonce: form.find('input[name="user_plugin_nonce"]').val()
        };
        
        $.post(ajax_object.ajax_url, formData, function(response) {
            if (response.success) {
                messages.addClass('success').html('<p>' + response.data.message + '</p>');
                
                // Redirect after successful signin
                if (response.data.redirect_url) {
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1500);
                }
            } else {
                messages.addClass('error').html('<p>' + response.data + '</p>');
            }
        }).fail(function() {
            messages.addClass('error').html('<p>An error occurred. Please try again.</p>');
        }).always(function() {
            submitBtn.prop('disabled', false).text(submitBtn.data('original-text') || 'Sign In');
        });
    });
    
    // Password strength indicator for signup form
    $('#signup_password').on('input', function() {
        var password = $(this).val();
        var strength = calculatePasswordStrength(password);
        var indicator = $('.password-strength');
        
        if (indicator.length === 0 && password.length > 0) {
            $(this).after('<div class="password-strength"></div>');
            indicator = $('.password-strength');
        }
        
        if (password.length === 0) {
            indicator.remove();
            return;
        }
        
        indicator.removeClass('weak medium strong').addClass(strength.class);
        indicator.text(strength.text);
    });
    
    // Calculate password strength
    function calculatePasswordStrength(password) {
        var score = 0;
        var feedback = [];
        
        if (password.length >= 8) score += 1;
        else feedback.push('at least 8 characters');
        
        if (/[a-z]/.test(password)) score += 1;
        else feedback.push('lowercase letters');
        
        if (/[A-Z]/.test(password)) score += 1;
        else feedback.push('uppercase letters');
        
        if (/[0-9]/.test(password)) score += 1;
        else feedback.push('numbers');
        
        if (/[^A-Za-z0-9]/.test(password)) score += 1;
        else feedback.push('special characters');
        
        switch (score) {
            case 0:
            case 1:
            case 2:
                return {
                    class: 'weak',
                    text: 'Weak password - Add ' + feedback.slice(0, 2).join(', ')
                };
            case 3:
            case 4:
                return {
                    class: 'medium',
                    text: 'Medium password - Add ' + feedback.join(', ')
                };
            case 5:
                return {
                    class: 'strong',
                    text: 'Strong password'
                };
        }
    }
    
    // Form validation
    $('.user-form input[required]').on('blur', function() {
        var input = $(this);
        var value = input.val().trim();
        
        input.removeClass('invalid');
        
        if (!value) {
            input.addClass('invalid');
            return;
        }
        
        // Email validation
        if (input.attr('type') === 'email') {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                input.addClass('invalid');
            }
        }
        
        // Username validation
        if (input.attr('name') === 'username') {
            var usernameRegex = /^[a-zA-Z0-9_-]{3,20}$/;
            if (!usernameRegex.test(value)) {
                input.addClass('invalid');
            }
        }
    });
    
    // Toggle password visibility
    $('.user-form').each(function() {
        var form = $(this);
        var passwordFields = form.find('input[type="password"]');
        
        passwordFields.after('<button type="button" class="toggle-password" tabindex="-1">Show</button>');
        
        form.on('click', '.toggle-password', function() {
            var btn = $(this);
            var passwordField = btn.prev('input');
            
            if (passwordField.attr('type') === 'password') {
                passwordField.attr('type', 'text');
                btn.text('Hide');
            } else {
                passwordField.attr('type', 'password');
                btn.text('Show');
            }
        });
    });
    
});
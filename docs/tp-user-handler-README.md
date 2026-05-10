# TP User Manager

A WordPress plugin that provides shortcodes for user registration, sign-in, and profile management with WordPress Users API integration.

## Features

- User registration form with shortcode
- User sign-in form with shortcode  
- User profile display with shortcode
- AJAX form submissions for seamless user experience
- Password strength indicator
- Form validation
- Responsive design
- WordPress Users API integration
- Custom hooks for extensibility

## Installation

1. Upload the plugin files to `/wp-content/plugins/tp-user-manager/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the shortcodes in your posts, pages, or widgets

## Available Shortcodes

### User Registration Form
```
[user_signup_form]
```

#### Parameters:
- `redirect_url` - URL to redirect after successful signup (default: home_url())
- `show_labels` - Show form field labels (default: 'true')  
- `button_text` - Custom text for submit button (default: 'Sign Up')

#### Example:
```
[user_signup_form redirect_url="https://example.com/welcome" show_labels="false" button_text="Create Account"]
```

### User Sign-In Form
```
[user_signin_form]
```

#### Parameters:
- `redirect_url` - URL to redirect after successful signin (default: home_url())
- `show_labels` - Show form field labels (default: 'true')
- `button_text` - Custom text for submit button (default: 'Sign In')

#### Example:
```
[user_signin_form redirect_url="https://example.com/dashboard" button_text="Login"]
```

### User Profile Display
```
[user_profile]
```

#### Parameters:
- `show_avatar` - Display user avatar (default: 'true')
- `avatar_size` - Avatar size in pixels (default: '96')

#### Example:
```
[user_profile show_avatar="true" avatar_size="128"]
```

## Form Fields

### Registration Form Fields:
- Username (required)
- Email (required)
- Password (required)
- First Name (optional)
- Last Name (optional)

### Sign-In Form Fields:
- Username or Email (required)
- Password (required)
- Remember Me (checkbox)

## WordPress Users API Integration

The plugin integrates with WordPress's built-in user management system:

### User Creation
- Uses `wp_insert_user()` to create new users
- Validates username and email uniqueness
- Automatically logs in user after successful registration
- Triggers `shortcode_user_plugin_user_created` action hook

### User Sign-In
- Uses `wp_signon()` for authentication
- Supports "Remember Me" functionality
- Triggers `shortcode_user_plugin_user_signed_in` action hook

## Custom Hooks

### Actions

#### `shortcode_user_plugin_user_created`
Fired after a new user is successfully created.

```php
add_action('shortcode_user_plugin_user_created', 'my_custom_function');
function my_custom_function($user_id) {
    // Your custom code here
    error_log('New user created: ' . $user_id);
}
```

#### `shortcode_user_plugin_user_signed_in`
Fired after a user successfully signs in.

```php
add_action('shortcode_user_plugin_user_signed_in', 'my_custom_function');
function my_custom_function($user_id) {
    // Your custom code here
    error_log('User signed in: ' . $user_id);
}
```

#### `shortcode_user_plugin_activated`
Fired when the plugin is activated.

#### `shortcode_user_plugin_deactivated`
Fired when the plugin is deactivated.

## Security Features

- Nonce verification for all AJAX requests
- Data sanitization for all user inputs
- WordPress security best practices
- CSRF protection

## Styling

The plugin includes responsive CSS styling that can be customized. The main CSS classes:

- `.user-signup-form-container`
- `.user-signin-form-container`
- `.user-profile-container`
- `.user-form`
- `.form-group`
- `.submit-btn`
- `.form-messages`

## JavaScript Features

- AJAX form submissions
- Real-time form validation
- Password strength indicator
- Password visibility toggle
- Loading states for buttons

## Requirements

- WordPress 4.0 or higher
- PHP 5.6 or higher
- jQuery (automatically loaded)

## File Structure

```
tp-user-manager/
├── tp-user-manager.php (main plugin file)
├── assets/
│   ├── shortcode-user-plugin.js
│   └── shortcode-user-plugin.css
└── README.md
```

## License

GPL v2 or later

## Support

For support and feature requests, please contact the plugin developer.